<?php
/**
 * Área de Cliente — Notificações Push (Web Push / PWA)
 *
 * Implementação própria do protocolo Web Push (VAPID + aes128gcm, RFC
 * 8291/8292), só com `ext-openssl` — sem nenhuma dependência de
 * Composer em runtime. Motivo: o deploy para produção (`.cpanel.yml`)
 * é uma cópia de ficheiros (`cp -R *`), sem `composer install`; uma
 * dependência de runtime obrigaria a comitar `vendor/` ou a mudar o
 * deploy. Ver CLAUDE.md.
 *
 * Fluxo:
 *  1. O browser subscreve-se (push.js) → POST /wp-json/cp/v1/push/subscrever
 *     → guardado em usermeta 'cp_push_subscricoes'.
 *  2. Um evento do backend (ex.: novo pendente da Work) chama
 *     cp_push_notificar_empresa() → resolve utilizadores com acesso a
 *     essa empresa (reaproveita cp_ac_user_empresas()) → envia a cada
 *     subscrição.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Base64url ──────────────────────────────────────────────────────────────

function cp_push_base64url_encode( $dados ) {
	return rtrim( strtr( base64_encode( $dados ), '+/', '-_' ), '=' );
}

function cp_push_base64url_decode( $dados ) {
	$dados = strtr( $dados, '-_', '+/' );
	$resto = strlen( $dados ) % 4;
	if ( $resto ) {
		$dados .= str_repeat( '=', 4 - $resto );
	}
	return base64_decode( $dados );
}

// ─── Chaves VAPID ───────────────────────────────────────────────────────────
// Geradas uma única vez (lazy, na primeira notificação ou subscrição) e
// guardadas na opção 'cp_push_vapid_keys'. A chave privada nunca sai do
// servidor; a pública é enviada ao browser para a subscrição.

function cp_push_vapid_keys() {
	$guardadas = get_option( 'cp_push_vapid_keys' );
	if ( is_array( $guardadas ) && ! empty( $guardadas['public'] ) && ! empty( $guardadas['private_pem'] ) ) {
		return $guardadas;
	}

	$par = openssl_pkey_new( [
		'curve_name'       => 'prime256v1',
		'private_key_type' => OPENSSL_KEYTYPE_EC,
	] );
	if ( false === $par ) {
		return null; // ext-openssl indisponível ou sem suporte a EC.
	}

	openssl_pkey_export( $par, $private_pem );
	$detalhes = openssl_pkey_get_details( $par );
	$ponto    = cp_push_ponto_ec_nao_comprimido( $detalhes['ec']['x'], $detalhes['ec']['y'] );

	$chaves = [
		'public'      => cp_push_base64url_encode( $ponto ),
		'private_pem' => $private_pem,
	];
	update_option( 'cp_push_vapid_keys', $chaves, false );
	return $chaves;
}

// Ponto EC não comprimido (0x04 || X || Y), X e Y sempre com 32 bytes
// (openssl_pkey_get_details() devolve sem zeros à esquerda).
function cp_push_ponto_ec_nao_comprimido( $x, $y ) {
	return "\x04" . str_pad( $x, 32, "\x00", STR_PAD_LEFT ) . str_pad( $y, 32, "\x00", STR_PAD_LEFT );
}

// Converte um ponto EC P-256 não comprimido (65 bytes) numa chave
// pública OpenSSL utilizável, embrulhando-o no cabeçalho DER fixo de
// SubjectPublicKeyInfo para id-ecPublicKey/prime256v1 (RFC 5480).
function cp_push_ponto_para_chave_publica( $ponto ) {
	$prefixo_der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
	$pem = "-----BEGIN PUBLIC KEY-----\n"
		. chunk_split( base64_encode( $prefixo_der . $ponto ), 64, "\n" )
		. "-----END PUBLIC KEY-----\n";
	return openssl_pkey_get_public( $pem );
}

// ─── HKDF (RFC 5869) ────────────────────────────────────────────────────────

function cp_push_hkdf_extract( $salt, $ikm ) {
	return hash_hmac( 'sha256', $ikm, $salt, true );
}

function cp_push_hkdf_expand( $prk, $info, $length ) {
	// Só precisamos de comprimentos ≤ 32 (o tamanho do hash SHA-256),
	// por isso uma única ronda (T(1)) já dá os bytes todos.
	return substr( hash_hmac( 'sha256', $info . chr( 1 ), $prk, true ), 0, $length );
}

// ─── JWT de autorização VAPID (RFC 8292) ───────────────────────────────────

function cp_push_vapid_jwt( $endpoint ) {
	$chaves = cp_push_vapid_keys();
	if ( ! $chaves ) return null;

	$origem = wp_parse_url( $endpoint );
	$aud    = $origem['scheme'] . '://' . $origem['host'];

	$cabecalho = cp_push_base64url_encode( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'ES256' ] ) );
	$claims    = cp_push_base64url_encode( wp_json_encode( [
		'aud' => $aud,
		'exp' => time() + ( 12 * HOUR_IN_SECONDS ),
		'sub' => 'mailto:' . cp_opt( 'cp_email', get_option( 'admin_email' ) ),
	] ) );
	$por_assinar = $cabecalho . '.' . $claims;

	$chave_privada = openssl_pkey_get_private( $chaves['private_pem'] );
	if ( ! $chave_privada || ! openssl_sign( $por_assinar, $assinatura_der, $chave_privada, OPENSSL_ALGO_SHA256 ) ) {
		return null;
	}

	$assinatura = cp_push_der_para_raw_ecdsa( $assinatura_der, 32 );
	return $por_assinar . '.' . cp_push_base64url_encode( $assinatura );
}

// Converte uma assinatura ECDSA em DER (duas INTEGER — r e s) para o
// formato "raw" r||s de tamanho fixo exigido pelo JWS (RFC 7518 §3.4).
function cp_push_der_para_raw_ecdsa( $der, $tamanho_componente ) {
	$offset = 2; // salta SEQUENCE + tamanho total
	$extrair = function ( $der, &$offset ) use ( $tamanho_componente ) {
		$offset++; // tag INTEGER (0x02)
		$comprimento = ord( $der[ $offset ] );
		$offset++;
		$valor = substr( $der, $offset, $comprimento );
		$offset += $comprimento;
		$valor = ltrim( $valor, "\x00" ); // remove zero de padding de sinal
		return str_pad( $valor, $tamanho_componente, "\x00", STR_PAD_LEFT );
	};
	$r = $extrair( $der, $offset );
	$s = $extrair( $der, $offset );
	return $r . $s;
}

// ─── Cifra do payload (aes128gcm, RFC 8188 + RFC 8291) ─────────────────────

function cp_push_cifrar( $payload_json, array $subscricao ) {
	$chave_utilizador = cp_push_base64url_decode( $subscricao['keys']['p256dh'] );
	$segredo_auth     = cp_push_base64url_decode( $subscricao['keys']['auth'] );

	$efemero = openssl_pkey_new( [ 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ] );
	if ( ! $efemero ) return null;
	$detalhes_efemero = openssl_pkey_get_details( $efemero );
	$ponto_servidor   = cp_push_ponto_ec_nao_comprimido( $detalhes_efemero['ec']['x'], $detalhes_efemero['ec']['y'] );

	$chave_publica_utilizador = cp_push_ponto_para_chave_publica( $chave_utilizador );
	if ( ! $chave_publica_utilizador ) return null;

	$segredo_partilhado = openssl_pkey_derive( $chave_publica_utilizador, $efemero, 32 );
	if ( ! $segredo_partilhado ) return null;

	// RFC 8291 §3.3 — deriva o IKM final a partir do segredo partilhado
	// e do segredo de autenticação da subscrição.
	$info_ikm = "WebPush: info\x00" . $chave_utilizador . $ponto_servidor;
	$prk_ikm  = cp_push_hkdf_extract( $segredo_auth, $segredo_partilhado );
	$ikm      = cp_push_hkdf_expand( $prk_ikm, $info_ikm, 32 );

	// RFC 8188 §2.1 — deriva chave de cifra (CEK) e nonce a partir de um
	// salt aleatório por mensagem.
	$salt = random_bytes( 16 );
	$prk  = cp_push_hkdf_extract( $salt, $ikm );
	$cek  = cp_push_hkdf_expand( $prk, "Content-Encoding: aes128gcm\x00", 16 );
	$nonce = cp_push_hkdf_expand( $prk, "Content-Encoding: nonce\x00", 12 );

	// Delimitador de registo único (0x02) sem padding adicional — a
	// mensagem cabe sempre num só "record" (record_size = 4096).
	$texto_cifrado = openssl_encrypt(
		$payload_json . "\x02",
		'aes-128-gcm',
		$cek,
		OPENSSL_RAW_DATA,
		$nonce,
		$tag,
		'',
		16
	);
	if ( false === $texto_cifrado ) return null;

	$cabecalho_rfc8188 = $salt . pack( 'N', 4096 ) . chr( strlen( $ponto_servidor ) ) . $ponto_servidor;
	return $cabecalho_rfc8188 . $texto_cifrado . $tag;
}

// ─── Envio ao serviço de push do browser ───────────────────────────────────

function cp_push_enviar_subscricao( $user_id, array $subscricao, $payload_json ) {
	$corpo = cp_push_cifrar( $payload_json, $subscricao );
	if ( null === $corpo ) return false;

	$chaves = cp_push_vapid_keys();
	$jwt    = cp_push_vapid_jwt( $subscricao['endpoint'] );
	if ( ! $chaves || ! $jwt ) return false;

	$resposta = wp_remote_post( $subscricao['endpoint'], [
		'headers' => [
			'Content-Type'     => 'application/octet-stream',
			'Content-Encoding' => 'aes128gcm',
			'TTL'              => (string) DAY_IN_SECONDS,
			'Urgency'          => 'normal',
			'Authorization'    => 'vapid t=' . $jwt . ', k=' . $chaves['public'],
		],
		'body'    => $corpo,
		'timeout' => 15,
	] );

	if ( is_wp_error( $resposta ) ) {
		return false;
	}

	$codigo = wp_remote_retrieve_response_code( $resposta );
	if ( in_array( $codigo, [ 404, 410 ], true ) ) {
		// Subscrição morta (desinstalada, permissão revogada, etc.).
		cp_push_remover_subscricao( $user_id, $subscricao['endpoint'] );
	}
	return $codigo >= 200 && $codigo < 300;
}

// ─── Subscrições (usermeta 'cp_push_subscricoes') ──────────────────────────

function cp_push_subscricoes_utilizador( $user_id ) {
	$subscricoes = get_user_meta( $user_id, 'cp_push_subscricoes', true );
	return is_array( $subscricoes ) ? $subscricoes : [];
}

function cp_push_guardar_subscricao( $user_id, array $subscricao ) {
	if ( empty( $subscricao['endpoint'] ) || empty( $subscricao['keys']['p256dh'] ) || empty( $subscricao['keys']['auth'] ) ) {
		return false;
	}
	$subscricoes = array_values( array_filter( cp_push_subscricoes_utilizador( $user_id ), function ( $s ) use ( $subscricao ) {
		return $s['endpoint'] !== $subscricao['endpoint'];
	} ) );
	$subscricoes[] = [
		'endpoint' => $subscricao['endpoint'],
		'keys'     => [
			'p256dh' => $subscricao['keys']['p256dh'],
			'auth'   => $subscricao['keys']['auth'],
		],
		'criada_em' => current_time( 'mysql' ),
	];
	update_user_meta( $user_id, 'cp_push_subscricoes', $subscricoes );
	return true;
}

function cp_push_remover_subscricao( $user_id, $endpoint ) {
	$subscricoes = array_values( array_filter( cp_push_subscricoes_utilizador( $user_id ), function ( $s ) use ( $endpoint ) {
		return $s['endpoint'] !== $endpoint;
	} ) );
	update_user_meta( $user_id, 'cp_push_subscricoes', $subscricoes );
}

// ─── Rotas REST: subscrever / cancelar ──────────────────────────────────────

function cp_push_rest_routes() {
	register_rest_route( 'cp/v1', '/push/subscrever', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_push_rest_subscrever',
			'permission_callback' => 'cp_push_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/push/cancelar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_push_rest_cancelar',
			'permission_callback' => 'cp_push_rest_permissao',
		],
	] );
}
add_action( 'rest_api_init', 'cp_push_rest_routes' );

function cp_push_rest_permissao() {
	return is_user_logged_in();
}

function cp_push_rest_subscrever( $request ) {
	$subscricao = $request->get_json_params();
	if ( ! is_array( $subscricao ) || ! cp_push_guardar_subscricao( get_current_user_id(), $subscricao ) ) {
		return new WP_Error( 'cp_push_subscricao_invalida', __( 'Subscrição inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

function cp_push_rest_cancelar( $request ) {
	$endpoint = $request->get_param( 'endpoint' );
	if ( ! $endpoint ) {
		return new WP_Error( 'cp_push_endpoint_em_falta', __( 'endpoint é obrigatório.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	cp_push_remover_subscricao( get_current_user_id(), $endpoint );
	return rest_ensure_response( [ 'ok' => true ] );
}

// ─── Notificar todos os utilizadores de uma empresa ────────────────────────

function cp_push_notificar_empresa( $empresa_id, $titulo, $corpo, $url = '' ) {
	$empresa_id = (int) $empresa_id;
	if ( ! $empresa_id ) return;

	// Quem tem mais do que uma empresa não saberia a qual se refere a
	// notificação sem isto — mesmo quem só tem uma, o nome não atrapalha.
	$payload = wp_json_encode( [
		'titulo' => $titulo,
		'corpo'  => sprintf(
			/* translators: 1: nome da empresa, 2: texto original da notificação */
			__( '%1$s — %2$s', 'contabilidade-pro' ),
			get_the_title( $empresa_id ),
			$corpo
		),
		'url'    => $url ? $url : home_url( '/area-de-cliente/' ),
	] );

	$utilizadores = get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] );
	foreach ( $utilizadores as $utilizador ) {
		if ( ! in_array( $empresa_id, cp_ac_user_empresas( $utilizador->ID ), true ) ) {
			continue;
		}
		foreach ( cp_push_subscricoes_utilizador( $utilizador->ID ) as $subscricao ) {
			cp_push_enviar_subscricao( $utilizador->ID, $subscricao, $payload );
		}
	}
}

// ─── Notificar a equipa (backoffice) ────────────────────────────────────────
// Ao contrário de cp_push_notificar_empresa(), "quem" aqui não depende de
// nenhuma empresa — é qualquer utilizador com acesso ao backoffice
// (administrador ou papel 'equipa_en', ver cp_backoffice_utilizador_tem_acesso()
// em inc/area-cliente-backoffice.php). Regista sempre o evento no feed do
// backoffice, mesmo que o envio do push falhe para todos os dispositivos.

function cp_push_notificar_equipa( $titulo, $corpo, $url = '', $empresa_id = 0 ) {
	if ( function_exists( 'cp_backoffice_registar_evento' ) ) {
		cp_backoffice_registar_evento( $titulo, $corpo, $url, $empresa_id );
	}

	$payload = wp_json_encode( [
		'titulo' => $titulo,
		'corpo'  => $corpo,
		'url'    => $url ? $url : home_url( '/backoffice/' ),
	] );

	foreach ( get_users() as $utilizador ) {
		if ( ! cp_backoffice_utilizador_tem_acesso( $utilizador->ID ) ) {
			continue;
		}
		foreach ( cp_push_subscricoes_utilizador( $utilizador->ID ) as $subscricao ) {
			cp_push_enviar_subscricao( $utilizador->ID, $subscricao, $payload );
		}
	}
}

// ─── Rotas estáticas: service worker e manifesto PWA ───────────────────────
// Hand-rolled, sem add_rewrite_rule/flush — mesmo padrão já usado por
// cp_portao_site_publico() em functions.php.

function cp_push_rotas_estaticas() {
	$pedido = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';

	if ( '/sw-push.js' === $pedido ) {
		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		readfile( get_template_directory() . '/assets/js/push-sw.js' );
		exit;
	}

	if ( '/manifest.webmanifest' === $pedido || '/manifest-backoffice.webmanifest' === $pedido ) {
		$backoffice = '/manifest-backoffice.webmanifest' === $pedido;
		status_header( 200 );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( [
			'name'             => get_bloginfo( 'name' ) . ' — ' . ( $backoffice ? __( 'Backoffice', 'contabilidade-pro' ) : __( 'Área de Cliente', 'contabilidade-pro' ) ),
			'short_name'       => get_bloginfo( 'name' ),
			'start_url'        => home_url( $backoffice ? '/backoffice/' : '/area-de-cliente/' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'background_color' => '#101A27',
			'theme_color'      => '#1F3752',
			'icons'            => [
				[
					'src'   => get_template_directory_uri() . '/assets/img/favicon-192.png',
					'sizes' => '192x192',
					'type'  => 'image/png',
				],
				[
					'src'   => get_template_directory_uri() . '/assets/img/favicon-512.png',
					'sizes' => '512x512',
					'type'  => 'image/png',
				],
			],
		] );
		exit;
	}
}
add_action( 'template_redirect', 'cp_push_rotas_estaticas', 0 );
