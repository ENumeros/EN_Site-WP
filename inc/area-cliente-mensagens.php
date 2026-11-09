<?php
/**
 * Mensagens — várias conversas por empresa, cada uma com o seu assunto,
 * entre o cliente e a equipa. Guardado como postmeta no post da empresa,
 * mesmo princípio de "caixa de correio" usado em Lembretes/Pendentes —
 * mas esta é a primeira ferramenta que atualiza sem recarregar a página:
 * leitura e escrita vão por REST (ver ─── REST ───), não por admin-post.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Dados: conversas ───────────────────────────────────────────────────────

function cp_ac_conversas_raw( $empresa_id ) {
	$conversas = get_post_meta( $empresa_id, '_cp_conversas', true );
	return is_array( $conversas ) ? $conversas : [];
}

// Mensagens já existentes antes de haver conversas (lançadas direto no
// fio único, sem 'conversa_id') ficam todas juntas numa conversa "Geral"
// criada aqui — lazy, sem script de migração à parte, e idempotente: na
// próxima chamada já não há nenhuma por migrar.
function cp_ac_conversas_migrar_legado( $empresa_id ) {
	$mensagens = cp_ac_mensagens( $empresa_id );
	$sem_conversa = array_values( array_filter( $mensagens, function ( $mensagem ) {
		return empty( $mensagem['conversa_id'] );
	} ) );
	if ( empty( $sem_conversa ) ) return;

	usort( $sem_conversa, function ( $a, $b ) {
		return strcmp( $a['enviado_em'], $b['enviado_em'] );
	} );

	$conversas   = cp_ac_conversas_raw( $empresa_id );
	$conversa_id = wp_generate_uuid4();
	$conversas[] = [
		'id'                 => $conversa_id,
		'assunto'            => __( 'Geral', 'contabilidade-pro' ),
		'criado_por_tipo'    => $sem_conversa[0]['autor_tipo'],
		'criado_por_user_id' => $sem_conversa[0]['autor_user_id'],
		'criado_em'          => $sem_conversa[0]['enviado_em'],
	];
	update_post_meta( $empresa_id, '_cp_conversas', $conversas );

	$mensagens = array_map( function ( $mensagem ) use ( $conversa_id ) {
		if ( empty( $mensagem['conversa_id'] ) ) {
			$mensagem['conversa_id'] = $conversa_id;
		}
		return $mensagem;
	}, $mensagens );
	update_post_meta( $empresa_id, '_cp_mensagens', $mensagens );
}

// Lista de conversas, mais recente primeiro, cada uma com a última
// mensagem e quantas ainda não foram lidas por $user_id (omitido =
// utilizador atual). A "última mensagem" nunca é guardada à parte — é
// sempre calculada a partir de _cp_mensagens, para nunca desalinhar.
// $estado filtra por 'ativas' (por omissão), 'arquivadas' ou 'todas'.
function cp_ac_conversas( $empresa_id, $user_id = null, $estado = 'ativas' ) {
	cp_ac_conversas_migrar_legado( $empresa_id );
	$user_id   = $user_id ?: get_current_user_id();
	$mensagens = cp_ac_mensagens( $empresa_id );

	$conversas = array_map( function ( $conversa ) use ( $mensagens, $user_id ) {
		$da_conversa = array_values( array_filter( $mensagens, function ( $mensagem ) use ( $conversa ) {
			return $mensagem['conversa_id'] === $conversa['id'];
		} ) );
		$ultima = ! empty( $da_conversa ) ? end( $da_conversa ) : null;

		$conversa['ultima_mensagem']    = $ultima ? $ultima['texto'] : '';
		$conversa['ultima_mensagem_em'] = $ultima ? $ultima['enviado_em'] : $conversa['criado_em'];
		$conversa['por_ler']            = cp_ac_mensagens_por_ler( $conversa['id'], $da_conversa, $user_id );
		$conversa['arquivada']          = ! empty( $conversa['arquivada'] );
		return $conversa;
	}, cp_ac_conversas_raw( $empresa_id ) );

	if ( 'todas' !== $estado ) {
		$quer_arquivadas = 'arquivadas' === $estado;
		$conversas = array_values( array_filter( $conversas, function ( $conversa ) use ( $quer_arquivadas ) {
			return $conversa['arquivada'] === $quer_arquivadas;
		} ) );
	}

	usort( $conversas, function ( $a, $b ) {
		return strcmp( $b['ultima_mensagem_em'], $a['ultima_mensagem_em'] );
	} );

	return $conversas;
}

// Arquivar/repor — nunca apaga nada, só deixa de aparecer no separador
// "Ativas". Responder a uma conversa arquivada repõe-na automaticamente
// (ver cp_ac_rest_mensagens_criar()).
function cp_ac_conversa_arquivar( $empresa_id, $conversa_id, $arquivar ) {
	$conversas = cp_ac_conversas_raw( $empresa_id );
	$alterado  = false;
	foreach ( $conversas as &$conversa ) {
		if ( $conversa['id'] === $conversa_id ) {
			$conversa['arquivada'] = (bool) $arquivar;
			$alterado = true;
			break;
		}
	}
	unset( $conversa );
	if ( $alterado ) update_post_meta( $empresa_id, '_cp_conversas', $conversas );
	return $alterado;
}

// Só pode ser apagada enquanto só uma pessoa escreveu nela — assim que a
// outra parte responde, a conversa já é uma troca que o outro lado viu e
// fica para sempre (pode sempre ser arquivada, nunca apagada).
function cp_ac_conversa_pode_apagar( $empresa_id, $conversa_id ) {
	$mensagens = cp_ac_mensagens_conversa( $empresa_id, $conversa_id );
	if ( empty( $mensagens ) ) return true;
	$autores = array_unique( wp_list_pluck( $mensagens, 'autor_user_id' ) );
	return count( $autores ) <= 1;
}

function cp_ac_conversa_apagar( $empresa_id, $conversa_id ) {
	if ( ! cp_ac_conversa_pode_apagar( $empresa_id, $conversa_id ) ) return false;

	$conversas = array_values( array_filter( cp_ac_conversas_raw( $empresa_id ), function ( $conversa ) use ( $conversa_id ) {
		return $conversa['id'] !== $conversa_id;
	} ) );
	update_post_meta( $empresa_id, '_cp_conversas', $conversas );

	$mensagens = array_values( array_filter( cp_ac_mensagens( $empresa_id ), function ( $mensagem ) use ( $conversa_id ) {
		return $mensagem['conversa_id'] !== $conversa_id;
	} ) );
	update_post_meta( $empresa_id, '_cp_mensagens', $mensagens );

	return true;
}

function cp_ac_conversa_adicionar( $empresa_id, $assunto, $user_id, $equipa ) {
	$conversas = cp_ac_conversas_raw( $empresa_id );
	$conversa  = [
		'id'                 => wp_generate_uuid4(),
		'assunto'            => $assunto,
		'criado_por_tipo'    => $equipa ? 'equipa' : 'cliente',
		'criado_por_user_id' => (int) $user_id,
		'criado_em'          => current_time( 'mysql' ),
	];
	$conversas[] = $conversa;
	update_post_meta( $empresa_id, '_cp_conversas', $conversas );
	return $conversa;
}

// Nunca confiar que um conversa_id vindo de um pedido é mesmo da
// empresa já resolvida (cp_ac_mensagem_resolver_empresa()) — confirma
// sempre antes de listar ou escrever mensagens.
function cp_ac_conversa_pertence( $conversa_id, $empresa_id ) {
	foreach ( cp_ac_conversas_raw( $empresa_id ) as $conversa ) {
		if ( $conversa['id'] === $conversa_id ) return true;
	}
	return false;
}

// ─── Dados: mensagens ───────────────────────────────────────────────────────

function cp_ac_mensagens( $empresa_id ) {
	$mensagens = get_post_meta( $empresa_id, '_cp_mensagens', true );
	return is_array( $mensagens ) ? $mensagens : [];
}

function cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) {
	return array_values( array_filter( cp_ac_mensagens( $empresa_id ), function ( $mensagem ) use ( $conversa_id ) {
		return $mensagem['conversa_id'] === $conversa_id;
	} ) );
}

function cp_ac_mensagem_adicionar( $empresa_id, $conversa_id, $texto, $user_id, $equipa ) {
	$utilizador = get_userdata( $user_id );
	$mensagens  = cp_ac_mensagens( $empresa_id );
	$mensagem   = [
		'id'            => wp_generate_uuid4(),
		'conversa_id'   => $conversa_id,
		'texto'         => $texto,
		'autor_tipo'    => $equipa ? 'equipa' : 'cliente',
		'autor_user_id' => (int) $user_id,
		'autor_nome'    => $utilizador ? $utilizador->display_name : '',
		'enviado_em'    => current_time( 'mysql' ),
	];
	$mensagens[] = $mensagem;
	update_post_meta( $empresa_id, '_cp_mensagens', $mensagens );
	return $mensagem;
}

// A que empresa um pedido se refere: a equipa escolhe explicitamente
// (vários clientes); nunca confiar num empresa_id vindo de um cliente —
// é sempre a empresa ativa da sua própria sessão. Mesma lógica de
// cp_ac_tarefa_resolver_empresa() em inc/area-cliente-tarefas.php.
function cp_ac_mensagem_resolver_empresa( $empresa_id_pedido, $user_id ) {
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		return (int) $empresa_id_pedido;
	}
	return cp_ac_empresa_ativa( $user_id );
}

// ─── Lidas / por ler (por conversa, não por empresa) ───────────────────────
// Usermeta 'cp_mensagens_lidas' = [ conversa_id => data/hora até à qual
// já viu ]. Um utilizador pode ter acesso a várias empresas (equipa, ou
// um cliente com mais do que uma) — por isso por conversa, nunca um
// único marcador global.

function cp_ac_mensagens_marcador_lido( $user_id, $conversa_id ) {
	$marcadores = get_user_meta( $user_id, 'cp_mensagens_lidas', true );
	$marcadores = is_array( $marcadores ) ? $marcadores : [];
	return isset( $marcadores[ $conversa_id ] ) ? $marcadores[ $conversa_id ] : '';
}

function cp_ac_mensagens_marcar_lida( $user_id, $conversa_id ) {
	$marcadores = get_user_meta( $user_id, 'cp_mensagens_lidas', true );
	$marcadores = is_array( $marcadores ) ? $marcadores : [];
	$marcadores[ $conversa_id ] = current_time( 'mysql' );
	update_user_meta( $user_id, 'cp_mensagens_lidas', $marcadores );
}

// Mensagens da outra parte (nunca as que o próprio utilizador escreveu)
// enviadas depois do marcador de leitura dessa conversa. Recebe as
// mensagens já filtradas pela conversa (quem chama já as tem, em
// cp_ac_conversas()) para não repetir a leitura do postmeta.
function cp_ac_mensagens_por_ler( $conversa_id, $mensagens_da_conversa, $user_id ) {
	$marcador = cp_ac_mensagens_marcador_lido( $user_id, $conversa_id );
	$por_ler  = 0;
	foreach ( $mensagens_da_conversa as $mensagem ) {
		if ( (int) $mensagem['autor_user_id'] === (int) $user_id ) continue;
		if ( '' === $marcador || $mensagem['enviado_em'] > $marcador ) $por_ler++;
	}
	return $por_ler;
}

// ─── REST ───────────────────────────────────────────────────────────────────
// A validação de que a empresa/conversa pertence mesmo ao utilizador
// fica dentro de cada callback — o permission_callback só confirma
// sessão iniciada, mesmo padrão de cp_push_rest_permissao() em
// inc/area-cliente-push.php.

function cp_ac_mensagens_rest_routes() {
	register_rest_route( 'cp/v1', '/conversas', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_conversas_listar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_conversas_criar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/mensagens', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_mensagens_listar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_mensagens_criar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/mensagens/marcar-lida', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_mensagens_marcar_lida',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/conversas/arquivar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_conversa_arquivar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/conversas/apagar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_conversa_apagar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_mensagens_rest_routes' );

function cp_ac_mensagens_rest_permissao() {
	return is_user_logged_in();
}

// Resolve e valida a empresa de um pedido; devolve o ID ou um WP_Error.
function cp_ac_mensagens_rest_resolver_empresa( $request, $user_id ) {
	$empresa_id = cp_ac_mensagem_resolver_empresa( (int) $request->get_param( 'empresa_id' ), $user_id );
	if ( ! $empresa_id || 'cp_empresa' !== get_post_type( $empresa_id ) ) {
		return new WP_Error( 'cp_empresa_invalida', __( 'Empresa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	return $empresa_id;
}

function cp_ac_rest_conversas_listar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$estado = sanitize_key( (string) $request->get_param( 'estado' ) );
	if ( ! in_array( $estado, [ 'ativas', 'arquivadas', 'todas' ], true ) ) $estado = 'ativas';

	$conversas = array_map( function ( $conversa ) use ( $empresa_id ) {
		$conversa['ultima_mensagem_ha'] = human_time_diff( strtotime( $conversa['ultima_mensagem_em'] ), current_time( 'timestamp' ) );
		$conversa['pode_apagar']        = cp_ac_conversa_pode_apagar( $empresa_id, $conversa['id'] );
		return $conversa;
	}, cp_ac_conversas( $empresa_id, $user_id, $estado ) );

	// O total de não lidas no badge é sempre das conversas ativas, mesmo a
	// ver as arquivadas — uma conversa arquivada não pede atenção.
	$por_ler_total = 'ativas' === $estado
		? array_sum( wp_list_pluck( $conversas, 'por_ler' ) )
		: array_sum( wp_list_pluck( cp_ac_conversas( $empresa_id, $user_id, 'ativas' ), 'por_ler' ) );

	return rest_ensure_response( [
		'conversas'     => $conversas,
		'por_ler_total' => $por_ler_total,
	] );
}

function cp_ac_rest_conversa_arquivar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	$arquivar = (bool) $request->get_param( 'arquivar' );
	cp_ac_conversa_arquivar( $empresa_id, $conversa_id, $arquivar );
	return rest_ensure_response( [ 'ok' => true, 'arquivada' => $arquivar ] );
}

function cp_ac_rest_conversa_apagar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	if ( ! cp_ac_conversa_apagar( $empresa_id, $conversa_id ) ) {
		return new WP_Error( 'cp_conversa_tem_resposta', __( 'Já há resposta nesta conversa — não pode ser apagada.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

function cp_ac_rest_conversas_criar( $request ) {
	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$assunto = sanitize_text_field( (string) $request->get_param( 'assunto' ) );
	$texto   = sanitize_textarea_field( (string) $request->get_param( 'texto' ) );
	if ( '' === trim( $assunto ) ) {
		return new WP_Error( 'cp_assunto_vazio', __( 'Escreva um assunto.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	if ( '' === trim( $texto ) ) {
		return new WP_Error( 'cp_mensagem_vazia', __( 'Escreva uma mensagem.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	$conversa = cp_ac_conversa_adicionar( $empresa_id, $assunto, $user_id, $equipa );
	$mensagem = cp_ac_mensagem_adicionar( $empresa_id, $conversa['id'], $texto, $user_id, $equipa );

	cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa );

	return rest_ensure_response( [ 'conversa' => $conversa, 'mensagem' => $mensagem ] );
}

function cp_ac_rest_mensagens_listar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	// O "há X tempo" é calculado aqui (fuso do site) e não em JS — evita
	// ter de repetir o cálculo de datas em JavaScript e desalinhar com o
	// fuso horário usado no resto do tema (human_time_diff()).
	$mensagens = array_map( function ( $mensagem ) {
		$mensagem['enviado_ha'] = human_time_diff( strtotime( $mensagem['enviado_em'] ), current_time( 'timestamp' ) );
		return $mensagem;
	}, cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) );

	return rest_ensure_response( [ 'mensagens' => $mensagens ] );
}

function cp_ac_rest_mensagens_marcar_lida( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	cp_ac_mensagens_marcar_lida( $user_id, $conversa_id );
	return rest_ensure_response( [ 'ok' => true ] );
}

function cp_ac_rest_mensagens_criar( $request ) {
	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	$texto = sanitize_textarea_field( (string) $request->get_param( 'texto' ) );
	if ( '' === trim( $texto ) ) {
		return new WP_Error( 'cp_mensagem_vazia', __( 'Escreva uma mensagem.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	$mensagem = cp_ac_mensagem_adicionar( $empresa_id, $conversa_id, $texto, $user_id, $equipa );

	// Responder a uma conversa arquivada repõe-na — já não fazia sentido
	// estar fora de "Ativas" com uma mensagem nova por ler.
	cp_ac_conversa_arquivar( $empresa_id, $conversa_id, false );

	$assunto = '';
	foreach ( cp_ac_conversas_raw( $empresa_id ) as $conversa ) {
		if ( $conversa['id'] === $conversa_id ) { $assunto = $conversa['assunto']; break; }
	}
	cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa );

	return rest_ensure_response( [ 'mensagem' => $mensagem ] );
}

function cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa ) {
	if ( $equipa ) {
		cp_push_notificar_empresa(
			$empresa_id,
			sprintf( /* translators: %s: assunto da conversa */ __( 'Nova mensagem — %s', 'contabilidade-pro' ), $assunto ),
			$texto,
			home_url( '/area-de-cliente/' )
		);
	} else {
		cp_push_notificar_equipa(
			sprintf( /* translators: %s: assunto da conversa */ __( 'Nova mensagem de cliente — %s', 'contabilidade-pro' ), $assunto ),
			sprintf(
				/* translators: 1: nome da empresa, 2: texto da mensagem */
				__( '%1$s: %2$s', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				$texto
			),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id
		);
	}
}

// ─── UI partilhada (Área de Cliente + Backoffice) ──────────────────────────

// Caixa de escrever + enviar — usada em qualquer sítio onde se escreve
// numa conversa já aberta (Backoffice, modal da conversa). Precisa de
// $conversa_id; para "Nova conversa" usa-se cp_ac_render_conversa_form().
function cp_ac_render_mensagem_form( $empresa_id, $conversa_id, $form_id ) {
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" data-cp-mensagens-form class="ac-mensagem-form">
		<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
		<input type="hidden" name="conversa_id" value="<?php echo esc_attr( $conversa_id ); ?>">
		<textarea name="texto" required rows="1" class="ac-mensagem-campo" placeholder="<?php esc_attr_e( 'Escreva uma mensagem…', 'contabilidade-pro' ); ?>"></textarea>
		<button class="ac-btn ac-btn-ghost" type="submit"><?php esc_html_e( 'Enviar', 'contabilidade-pro' ); ?></button>
	</form>
	<?php
}

// Formulário de "Nova conversa" (assunto + primeira mensagem) — usado no
// Backoffice e na modal do atalho rápido do "+".
function cp_ac_render_conversa_form( $empresa_id, $form_id ) {
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" data-cp-conversa-form style="display:flex;flex-direction:column;gap:10px">
		<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
		<input type="text" name="assunto" required placeholder="<?php esc_attr_e( 'Assunto — ex.: dúvida sobre o IVA', 'contabilidade-pro' ); ?>" style="padding:10px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
		<textarea name="texto" required rows="2" placeholder="<?php esc_attr_e( 'Escreva a mensagem…', 'contabilidade-pro' ); ?>" style="padding:10px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px;font-family:inherit;resize:vertical"></textarea>
		<button class="ac-btn ac-btn-primary" type="submit" style="align-self:flex-start"><?php esc_html_e( 'Iniciar conversa', 'contabilidade-pro' ); ?></button>
	</form>
	<?php
}

// Lista de mensagens de uma conversa (sem moldura à volta) — partilhada
// entre o Backoffice e a modal de conversa da Área de Cliente.
function cp_ac_render_mensagens_lista( $mensagens ) {
	?>
	<div id="cp-mensagens-lista" class="ac-mensagens-lista">
		<?php if ( empty( $mensagens ) ) : ?>
			<div class="ac-mensagens-vazio"><?php esc_html_e( 'Ainda sem mensagens nesta conversa.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<?php foreach ( $mensagens as $mensagem ) : ?>
				<div class="ac-mensagem ac-mensagem-<?php echo esc_attr( $mensagem['autor_tipo'] ); ?>" data-id="<?php echo esc_attr( $mensagem['id'] ); ?>">
					<div class="ac-mensagem-meta">
						<?php
						printf(
							/* translators: 1: nome de quem enviou, 2: há quanto tempo, ex. "3 horas" */
							esc_html__( '%1$s — há %2$s', 'contabilidade-pro' ),
							esc_html( $mensagem['autor_nome'] ),
							esc_html( human_time_diff( strtotime( $mensagem['enviado_em'] ), current_time( 'timestamp' ) ) )
						);
						?>
					</div>
					<div class="ac-mensagem-texto"><?php echo esc_html( $mensagem['texto'] ); ?></div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

// Backoffice: seletor de conversa (populado a partir de $empresa_id) +
// a conversa escolhida (histórico completo + enviar), ou "Nova conversa".
function cp_ac_render_mensagens( $empresa_id, $conversa_id = '' ) {
	$mostrar_arquivadas = ! empty( $_GET['cp_mostrar_arquivadas'] );
	$conversas = cp_ac_conversas( $empresa_id, null, $mostrar_arquivadas ? 'todas' : 'ativas' );
	?>
	<div class="ac-card" style="padding:22px;margin-bottom:18px">
		<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
			<?php if ( $mostrar_arquivadas ) : ?><input type="hidden" name="cp_mostrar_arquivadas" value="1"><?php endif; ?>
			<label for="cp-bo-conversa" style="font-size:12.5px;font-weight:600"><?php esc_html_e( 'Conversa', 'contabilidade-pro' ); ?></label>
			<select name="conversa_id" id="cp-bo-conversa" onchange="this.form.submit()" style="padding:9px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
				<option value=""><?php esc_html_e( 'Nova conversa…', 'contabilidade-pro' ); ?></option>
				<?php foreach ( $conversas as $conversa ) : ?>
					<option value="<?php echo esc_attr( $conversa['id'] ); ?>" <?php selected( $conversa_id, $conversa['id'] ); ?>><?php echo esc_html( $conversa['assunto'] ); ?><?php echo $conversa['por_ler'] ? ' (' . (int) $conversa['por_ler'] . ')' : ''; ?><?php echo $conversa['arquivada'] ? ' — ' . esc_html__( 'arquivada', 'contabilidade-pro' ) : ''; ?></option>
				<?php endforeach; ?>
			</select>
			<a href="<?php echo esc_url( add_query_arg( [ 'empresa_id' => $empresa_id, 'cp_mostrar_arquivadas' => $mostrar_arquivadas ? false : '1' ] ) ); ?>" style="font-size:12.5px;font-weight:600;color:var(--ac-muted)">
				<?php echo $mostrar_arquivadas ? esc_html__( 'Ocultar arquivadas', 'contabilidade-pro' ) : esc_html__( 'Mostrar arquivadas', 'contabilidade-pro' ); ?>
			</a>
		</form>
	</div>

	<?php if ( '' !== $conversa_id && cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) :
		$conversa_atual = null;
		foreach ( cp_ac_conversas( $empresa_id, null, 'todas' ) as $c ) {
			if ( $c['id'] === $conversa_id ) { $conversa_atual = $c; break; }
		}
		$pode_apagar = cp_ac_conversa_pode_apagar( $empresa_id, $conversa_id );
		?>
		<div class="ac-card" style="padding:22px">
			<div class="ac-sec-head">
				<h3 style="font-size:16px"><?php echo esc_html( $conversa_atual ? $conversa_atual['assunto'] : '' ); ?></h3>
				<div style="display:flex;gap:8px">
					<button type="button" class="ac-btn ac-btn-ghost" style="padding:7px 14px;font-size:12.5px" data-cp-conversa-arquivar data-conversa-id="<?php echo esc_attr( $conversa_id ); ?>" data-arquivar="<?php echo ( $conversa_atual && $conversa_atual['arquivada'] ) ? '0' : '1'; ?>">
						<?php echo ( $conversa_atual && $conversa_atual['arquivada'] ) ? esc_html__( 'Repor', 'contabilidade-pro' ) : esc_html__( 'Arquivar', 'contabilidade-pro' ); ?>
					</button>
					<?php if ( $pode_apagar ) : ?>
						<button type="button" class="ac-btn ac-btn-ghost" style="padding:7px 14px;font-size:12.5px;color:#B3432B" data-cp-conversa-apagar data-conversa-id="<?php echo esc_attr( $conversa_id ); ?>">
							<?php esc_html_e( 'Apagar', 'contabilidade-pro' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<?php cp_ac_render_mensagens_lista( cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) ); ?>
			<div style="margin-top:14px">
				<?php cp_ac_render_mensagem_form( $empresa_id, $conversa_id, 'cp-mensagens-form' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="ac-card" style="padding:22px">
			<div class="ac-sec-head">
				<h3 style="font-size:16px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></h3>
			</div>
			<?php cp_ac_render_conversa_form( $empresa_id, 'cp-conversa-form' ); ?>
		</div>
	<?php endif; ?>
	<?php
}

// Área de Cliente: a lista de conversas tem página própria agora
// (cp_ac_vista_mensagens(), em area-cliente-vistas.php) — cresce mal
// dentro de um dropdown pequeno. O dropdown do utilizador só mostra um
// link, com o total de não lidas ao lado.
function cp_ac_render_mensagens_dropdown_link( $empresa_id ) {
	$por_ler = array_sum( wp_list_pluck( cp_ac_conversas( $empresa_id ), 'por_ler' ) );
	?>
	<a class="ac-dropdown-item" href="<?php echo esc_url( cp_ac_vista_url( 'mensagens' ) ); ?>">
		<span><?php esc_html_e( 'Mensagens', 'contabilidade-pro' ); ?></span>
		<span id="cp-mensagens-dropdown-badge" class="ac-conversa-badge" <?php echo $por_ler ? '' : 'hidden'; ?>><?php echo (int) $por_ler; ?></span>
	</a>
	<?php
}

// Página própria (Área de Cliente): duas colunas — conversas à esquerda,
// a conversa escolhida sempre visível à direita (conteúdo montado por
// JS a cada clique numa linha — não dá para pré-renderizar todas as
// conversas sem saber quantas há), estilo caixa de entrada normal, sem
// abrir/fechar nada. Sem acesso pelo menu lateral (de propósito, como
// Lembretes) — só pelo link no dropdown do utilizador ou pelo atalho do
// "+" no Dashboard.
function cp_ac_render_mensagens_pagina( $empresa_id ) {
	$conversas = cp_ac_conversas( $empresa_id );
	?>
	<div class="ac-mensagens-colunas">
		<div class="ac-mensagens-col-lista">
			<div class="ac-card" style="padding:22px">
				<div class="ac-sec-head">
					<h3 style="font-size:16px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></h3>
				</div>
				<?php cp_ac_render_conversa_form( $empresa_id, 'cp-conversa-form-pagina' ); ?>
			</div>

			<div class="ac-card" style="padding:22px">
				<div class="ac-sec-head">
					<h3 style="font-size:16px"><?php esc_html_e( 'Conversas', 'contabilidade-pro' ); ?></h3>
					<div style="display:flex;gap:6px">
						<button type="button" class="ac-btn ac-btn-primary" style="padding:6px 12px;font-size:12.5px" data-cp-conversas-filtro="ativas"><?php esc_html_e( 'Ativas', 'contabilidade-pro' ); ?></button>
						<button type="button" class="ac-btn ac-btn-ghost" style="padding:6px 12px;font-size:12.5px" data-cp-conversas-filtro="arquivadas"><?php esc_html_e( 'Arquivadas', 'contabilidade-pro' ); ?></button>
					</div>
				</div>
				<div id="cp-conversas-lista">
					<?php if ( empty( $conversas ) ) : ?>
						<div class="ac-mensagens-vazio"><?php esc_html_e( 'Sem conversas — comece uma acima.', 'contabilidade-pro' ); ?></div>
					<?php else : ?>
						<?php foreach ( $conversas as $conversa ) : ?>
							<div class="ac-conversa-row" data-conversa-id="<?php echo esc_attr( $conversa['id'] ); ?>">
								<button type="button" class="ac-conversa-row-click" data-conversa-id="<?php echo esc_attr( $conversa['id'] ); ?>">
									<span class="ac-conversa-row-head">
										<span class="ac-conversa-assunto"><?php echo esc_html( $conversa['assunto'] ); ?></span>
										<?php if ( $conversa['por_ler'] ) : ?><span class="ac-conversa-badge"><?php echo (int) $conversa['por_ler']; ?></span><?php endif; ?>
									</span>
									<span class="ac-conversa-resumo"><?php echo esc_html( wp_trim_words( $conversa['ultima_mensagem'], 10 ) ); ?></span>
								</button>
								<span class="ac-conversa-row-acoes">
									<button type="button" class="ac-conversa-btn" data-cp-conversa-arquivar data-conversa-id="<?php echo esc_attr( $conversa['id'] ); ?>" data-arquivar="<?php echo $conversa['arquivada'] ? '0' : '1'; ?>" aria-label="<?php echo $conversa['arquivada'] ? esc_attr__( 'Repor', 'contabilidade-pro' ) : esc_attr__( 'Arquivar', 'contabilidade-pro' ); ?>" title="<?php echo $conversa['arquivada'] ? esc_attr__( 'Repor', 'contabilidade-pro' ) : esc_attr__( 'Arquivar', 'contabilidade-pro' ); ?>">
										<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h18M4 7v12a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7M9.5 12h5"></path></svg>
									</button>
									<?php if ( cp_ac_conversa_pode_apagar( $empresa_id, $conversa['id'] ) ) : ?>
										<button type="button" class="ac-conversa-btn ac-conversa-btn-apagar" data-cp-conversa-apagar data-conversa-id="<?php echo esc_attr( $conversa['id'] ); ?>" aria-label="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>" title="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>">
											<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
										</button>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="ac-mensagens-col-painel">
			<div id="cp-conversa-painel" class="ac-card" style="padding:22px">
				<div id="cp-conversa-painel-vazio" class="ac-mensagens-vazio"><?php esc_html_e( 'Escolha uma conversa à esquerda.', 'contabilidade-pro' ); ?></div>
				<div id="cp-conversa-painel-conteudo" hidden>
					<h3 id="cp-conversa-painel-titulo" style="font-size:16px;margin-bottom:16px"></h3>
					<div id="cp-conversa-painel-corpo"></div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

// Entrada no menu de ações rápidas do Dashboard (botão "+" flutuante) —
// mesmo padrão de cp_ac_render_documento_fab_item() em
// inc/area-cliente-documentos.php: abre a modal de "Nova conversa".
function cp_ac_render_mensagem_fab_item() {
	?>
	<button type="button" class="ac-fab-menu-item" data-cp-fab-target="cp-nova-conversa-modal" role="menuitem">
		<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5h16v11H8l-4 3.5z"></path></svg>
		<span><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></span>
	</button>
	<?php
}

function cp_ac_render_mensagem_modal( $empresa_id ) {
	?>
	<div id="cp-nova-conversa-modal-backdrop" class="ac-modal-backdrop" data-cp-fab-modal hidden></div>
	<div id="cp-nova-conversa-modal" class="ac-modal" role="dialog" aria-modal="true" aria-labelledby="cp-nova-conversa-modal-title" hidden>
		<div class="ac-modal-head">
			<h3 id="cp-nova-conversa-modal-title" style="font-size:16px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></h3>
			<button type="button" class="ac-modal-close" data-cp-fab-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'contabilidade-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
			</button>
		</div>
		<?php cp_ac_render_conversa_form( $empresa_id, 'cp-conversa-form-fab' ); ?>
	</div>
	<?php
}
