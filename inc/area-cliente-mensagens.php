<?php
/**
 * Mensagens — troca livre de mensagens por empresa, entre o cliente e a
 * equipa. Guardado como postmeta no post da empresa, mesmo princípio de
 * "caixa de correio" usado em Lembretes/Pendentes — mas esta é a primeira
 * ferramenta que atualiza sem recarregar a página: leitura e escrita vão
 * por REST (ver ─── REST ───), não por admin-post.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Dados ──────────────────────────────────────────────────────────────────

function cp_ac_mensagens( $empresa_id ) {
	$mensagens = get_post_meta( $empresa_id, '_cp_mensagens', true );
	return is_array( $mensagens ) ? $mensagens : [];
}

function cp_ac_mensagem_adicionar( $empresa_id, $texto, $user_id, $equipa ) {
	$utilizador = get_userdata( $user_id );
	$mensagens  = cp_ac_mensagens( $empresa_id );
	$mensagem   = [
		'id'            => wp_generate_uuid4(),
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

// ─── REST ───────────────────────────────────────────────────────────────────
// A validação de que a empresa pertence mesmo ao utilizador fica dentro de
// cada callback (via resolver acima) — o permission_callback só confirma
// sessão iniciada, mesmo padrão de cp_push_rest_permissao() em
// inc/area-cliente-push.php.

function cp_ac_mensagens_rest_routes() {
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
}
add_action( 'rest_api_init', 'cp_ac_mensagens_rest_routes' );

function cp_ac_mensagens_rest_permissao() {
	return is_user_logged_in();
}

function cp_ac_rest_mensagens_listar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagem_resolver_empresa( (int) $request->get_param( 'empresa_id' ), $user_id );
	if ( ! $empresa_id || 'cp_empresa' !== get_post_type( $empresa_id ) ) {
		return new WP_Error( 'cp_empresa_invalida', __( 'Empresa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	// O "há X tempo" é calculado aqui (fuso do site) e não em JS — evita
	// ter de repetir o cálculo de datas em JavaScript e desalinhar com o
	// fuso horário usado no resto do tema (human_time_diff()).
	$mensagens = array_map( function ( $mensagem ) {
		$mensagem['enviado_ha'] = human_time_diff( strtotime( $mensagem['enviado_em'] ), current_time( 'timestamp' ) );
		return $mensagem;
	}, cp_ac_mensagens( $empresa_id ) );

	return rest_ensure_response( [ 'mensagens' => $mensagens ] );
}

function cp_ac_rest_mensagens_criar( $request ) {
	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_mensagem_resolver_empresa( (int) $request->get_param( 'empresa_id' ), $user_id );
	$texto      = sanitize_textarea_field( (string) $request->get_param( 'texto' ) );

	if ( ! $empresa_id || 'cp_empresa' !== get_post_type( $empresa_id ) ) {
		return new WP_Error( 'cp_empresa_invalida', __( 'Empresa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	if ( '' === trim( $texto ) ) {
		return new WP_Error( 'cp_mensagem_vazia', __( 'Escreva uma mensagem.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	$mensagem = cp_ac_mensagem_adicionar( $empresa_id, $texto, $user_id, $equipa );

	if ( $equipa ) {
		cp_push_notificar_empresa(
			$empresa_id,
			__( 'Nova mensagem', 'contabilidade-pro' ),
			$texto,
			home_url( '/area-de-cliente/' )
		);
	} else {
		cp_push_notificar_equipa(
			__( 'Nova mensagem de cliente', 'contabilidade-pro' ),
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

	return rest_ensure_response( [ 'mensagem' => $mensagem ] );
}

// ─── UI partilhada (Área de Cliente + Backoffice) ──────────────────────────

function cp_ac_render_mensagens( $empresa_id, $mostrar_nome_empresa = false ) {
	$mensagens = cp_ac_mensagens( $empresa_id );
	?>
	<?php if ( $mostrar_nome_empresa ) : ?>
		<h3 style="font-size:16px"><?php echo esc_html( get_the_title( $empresa_id ) ); ?></h3>
	<?php endif; ?>

	<div class="ac-card" style="padding:22px">
		<div id="cp-mensagens-lista" class="ac-mensagens-lista">
			<?php if ( empty( $mensagens ) ) : ?>
				<div class="ac-mensagens-vazio"><?php esc_html_e( 'Sem mensagens — escreva aqui para falar com a Entre Números.', 'contabilidade-pro' ); ?></div>
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

		<form id="cp-mensagens-form" style="display:flex;gap:10px;margin-top:14px">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
			<textarea name="texto" required rows="1" placeholder="<?php esc_attr_e( 'Escreva uma mensagem…', 'contabilidade-pro' ); ?>" style="flex:1;min-width:0;padding:10px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px;font-family:inherit;resize:vertical"></textarea>
			<button class="ac-btn ac-btn-ghost" type="submit"><?php esc_html_e( 'Enviar', 'contabilidade-pro' ); ?></button>
		</form>
	</div>
	<?php
}
