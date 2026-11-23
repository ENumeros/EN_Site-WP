<?php
/**
 * Backoffice — central de notificações + atalhos para a equipa.
 *
 * Fase 1 (ver CLAUDE.md): só mostra o que mudou recentemente e aponta
 * para o wp-admin para agir — não substitui nenhum ecrã de gestão.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Papel da equipa ────────────────────────────────────────────────────────
// Criado uma única vez (idempotente). A Work atribui-o ao criar um
// utilizador do tipo "equipa" (POST /cp/v1/utilizadores, campo `tipo`);
// também se pode escolher no ecrã nativo wp-admin → Utilizadores.
// Também corre em `init`: `after_switch_theme` só dispara ao ativar o
// tema, por isso um site onde o tema já estava ativo nunca tinha o papel.

function cp_backoffice_registar_papel_equipa() {
	if ( get_role( 'equipa_en' ) ) return;
	add_role( 'equipa_en', __( 'Equipa Entre Números', 'contabilidade-pro' ), [
		'read'          => true,
		'cp_backoffice' => true,
	] );
}
add_action( 'after_switch_theme', 'cp_backoffice_registar_papel_equipa' );
add_action( 'init', 'cp_backoffice_registar_papel_equipa' );

function cp_backoffice_utilizador_tem_acesso( $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'cp_backoffice' );
}

// ─── Equipa sem empresas → Backoffice ──────────────────────────────────────
// Quem é da equipa (cp_backoffice ou administrador) e não tem empresas como
// cliente não tem nada a ver na Área de Cliente: ao iniciar sessão vai logo
// para o Backoffice, e se abrir /area-de-cliente também é levado para lá.
// Exceção: um administrador que pediu o wp-admin (ex.: wp-login.php a
// partir do painel) continua a ir para lá.

function cp_backoffice_url() {
	$pagina = get_page_by_path( 'backoffice' );
	return $pagina ? get_permalink( $pagina ) : home_url( '/backoffice/' );
}

function cp_backoffice_e_destino_do_utilizador( $user_id ) {
	return $user_id && cp_backoffice_utilizador_tem_acesso( $user_id ) && empty( cp_ac_user_empresas( $user_id ) );
}

function cp_backoffice_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! cp_backoffice_e_destino_do_utilizador( $user->ID ) ) {
		return $redirect_to;
	}
	if ( false !== strpos( (string) $requested_redirect_to, '/wp-admin' ) && user_can( $user, 'manage_options' ) ) {
		return $redirect_to;
	}
	return cp_backoffice_url();
}
add_filter( 'login_redirect', 'cp_backoffice_login_redirect', 20, 3 );

function cp_backoffice_desviar_area_cliente() {
	if ( is_page( 'area-de-cliente' ) && is_user_logged_in() && cp_backoffice_e_destino_do_utilizador( get_current_user_id() ) ) {
		wp_safe_redirect( cp_backoffice_url() );
		exit;
	}
}
add_action( 'template_redirect', 'cp_backoffice_desviar_area_cliente' );

// ─── Ver como o cliente (pré-visualização, só leitura) ─────────────────────
// A equipa abre /area-de-cliente exatamente como uma empresa a vê (matriz
// de ferramentas da empresa, sem restrições por utilizador), a partir do
// Backoffice. Fica num cookie de sessão do browser (não em usermeta), por
// isso só vale nesse browser e nunca contamina pedidos REST da Work (que
// usam Application Password, sem cookie). Só é aplicado ao desenhar a
// página /area-de-cliente — formulários e REST continuam a ver a equipa
// como equipa. Não é uma fronteira de segurança (a equipa já pode agir em
// qualquer empresa): o "só leitura" é para não agir por engano em nome do
// cliente, e é garantido no browser (submit e POSTs bloqueados).

const CP_BO_COOKIE_VER_EMPRESA = 'cp_bo_ver_empresa';

// Decisão pura: que empresa mostrar em pré-visualização (0 = nenhuma).
function cp_bo_calc_empresa_pre_visualizacao( $e_equipa, $pedida, array $empresas_validas ) {
	$pedida = (int) $pedida;
	if ( ! $e_equipa || $pedida <= 0 ) return 0;
	return in_array( $pedida, array_map( 'intval', $empresas_validas ), true ) ? $pedida : 0;
}

function cp_bo_empresa_publicada( $empresa_id ) {
	return $empresa_id && get_post_type( $empresa_id ) === 'cp_empresa' && get_post_status( $empresa_id ) === 'publish';
}

// Empresa em pré-visualização neste pedido (0 = modo normal).
function cp_bo_pre_visualizacao() {
	return isset( $GLOBALS['cp_bo_pre_visualizacao'] ) ? (int) $GLOBALS['cp_bo_pre_visualizacao'] : 0;
}

function cp_bo_url_ver_como_cliente( $empresa_id ) {
	$url = add_query_arg( 'cp_ver_empresa', (int) $empresa_id, home_url( '/area-de-cliente/' ) );
	return wp_nonce_url( $url, 'cp_ver_empresa_' . (int) $empresa_id );
}

function cp_bo_url_sair_pre_visualizacao() {
	return add_query_arg( 'cp_sair_ver', '1', home_url( '/area-de-cliente/' ) );
}

function cp_bo_gravar_cookie_ver_empresa( $valor, $expira ) {
	setcookie( CP_BO_COOKIE_VER_EMPRESA, (string) $valor, [
		'expires'  => $expira,
		'path'     => COOKIEPATH ? COOKIEPATH : '/',
		'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );
}

// Prioridade 1: antes de cp_ac_handle_pedidos_url() e do desvio da equipa
// para o Backoffice (ambos a 10), que já têm de ver a empresa escolhida.
function cp_bo_tratar_pre_visualizacao() {
	if ( ! is_page( 'area-de-cliente' ) || ! is_user_logged_in() ) return;
	$user_id  = get_current_user_id();
	$e_equipa = cp_backoffice_utilizador_tem_acesso( $user_id );

	if ( isset( $_GET['cp_sair_ver'] ) ) {
		$empresa = isset( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) ? absint( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) : 0;
		cp_bo_gravar_cookie_ver_empresa( '', time() - HOUR_IN_SECONDS );
		$destino = $e_equipa ? cp_backoffice_url() : home_url( '/area-de-cliente/' );
		if ( $e_equipa && $empresa ) $destino = add_query_arg( 'empresa_id', $empresa, $destino );
		wp_safe_redirect( $destino );
		exit;
	}

	if ( isset( $_GET['cp_ver_empresa'] ) ) {
		$pedida = absint( $_GET['cp_ver_empresa'] );
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( $e_equipa && cp_bo_empresa_publicada( $pedida ) && wp_verify_nonce( $nonce, 'cp_ver_empresa_' . $pedida ) ) {
			cp_bo_gravar_cookie_ver_empresa( $pedida, 0 ); // cookie de sessão
		}
		wp_safe_redirect( get_permalink() );
		exit;
	}

	$no_cookie = isset( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) ? absint( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) : 0;
	$empresa   = cp_bo_calc_empresa_pre_visualizacao( $e_equipa, $no_cookie, cp_bo_empresa_publicada( $no_cookie ) ? [ $no_cookie ] : [] );
	if ( $empresa ) {
		$GLOBALS['cp_bo_pre_visualizacao'] = $empresa;
	}
}
add_action( 'template_redirect', 'cp_bo_tratar_pre_visualizacao', 1 );

// Ao iniciar sessão começa sempre fora da pré-visualização.
function cp_bo_limpar_pre_visualizacao_no_login() {
	if ( isset( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) ) {
		cp_bo_gravar_cookie_ver_empresa( '', time() - HOUR_IN_SECONDS );
	}
}
add_action( 'wp_login', 'cp_bo_limpar_pre_visualizacao_no_login' );

function cp_bo_body_class_pre_visualizacao( $classes ) {
	if ( cp_bo_pre_visualizacao() ) $classes[] = 'ac-pre-visualizacao';
	return $classes;
}
add_filter( 'body_class', 'cp_bo_body_class_pre_visualizacao' );

// Faixa no topo da Área de Cliente + bloqueio de submissões/POSTs no browser.
function cp_bo_render_faixa_pre_visualizacao() {
	$empresa = cp_bo_pre_visualizacao();
	if ( ! $empresa ) return;
	$aviso = __( 'Pré-visualização só de leitura — nada é gravado. Para agir, use o Backoffice.', 'contabilidade-pro' );
	?>
	<div class="ac-pre-visualizacao-faixa" role="status">
		<span>
			<strong><?php esc_html_e( 'Está a ver como o cliente', 'contabilidade-pro' ); ?></strong> ·
			<?php echo esc_html( cp_ac_empresa_label( $empresa ) ); ?> ·
			<?php esc_html_e( 'só leitura', 'contabilidade-pro' ); ?>
		</span>
		<a class="ac-btn ac-btn-ghost" href="<?php echo esc_url( cp_bo_url_sair_pre_visualizacao() ); ?>"><?php esc_html_e( 'Voltar ao Backoffice', 'contabilidade-pro' ); ?></a>
	</div>
	<script>
	window.cpPreVisualizacao = true;
	window.addEventListener( 'submit', function ( evento ) {
		evento.preventDefault();
		evento.stopImmediatePropagation();
		window.alert( <?php echo wp_json_encode( $aviso ); ?> );
	}, true );
	</script>
	<?php
}

// ─── Registo de eventos (opção 'cp_eventos_staff', mais recente primeiro) ──

// $tipo: documentos | mensagens | pendentes | lembretes — usado nos filtros
// do Resumo (cp_bo_calc_tipo_evento() deduz o dos eventos antigos).
function cp_backoffice_registar_evento( $titulo, $corpo, $url, $empresa_id = 0, $tipo = '' ) {
	$eventos = cp_backoffice_listar_eventos( 0 );
	array_unshift( $eventos, [
		'titulo'     => $titulo,
		'corpo'      => $corpo,
		'url'        => $url,
		'empresa_id' => (int) $empresa_id,
		'tipo'       => (string) $tipo,
		'criado_em'  => current_time( 'mysql' ),
	] );
	update_option( 'cp_eventos_staff', array_slice( $eventos, 0, 100 ), false );
}

function cp_backoffice_listar_eventos( $limite = 50 ) {
	$eventos = get_option( 'cp_eventos_staff' );
	$eventos = is_array( $eventos ) ? $eventos : [];
	return $limite > 0 ? array_slice( $eventos, 0, $limite ) : $eventos;
}
