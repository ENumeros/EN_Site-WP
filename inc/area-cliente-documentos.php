<?php
/**
 * Área de Cliente — Documentos.
 *
 * O cliente envia ficheiros (faturas, despesas, etc.) que ficam guardados
 * na media library do WordPress, anexados à empresa (post_parent = ID do
 * post cp_empresa) e marcados com '_cp_doc_cliente' para os distinguir de
 * qualquer outro anexo que o post possa vir a ter. Mesmo padrão de
 * admin-post.php + nonce usado em Pendentes e Lembretes — sem REST, sem JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cp_ac_documentos( $empresa_id ) {
	return get_posts( [
		'post_type'      => 'attachment',
		'post_parent'    => (int) $empresa_id,
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_key'       => '_cp_doc_cliente',
		'meta_value'     => '1',
	] );
}

// Quem enviou o documento, ou qualquer elemento da equipa, pode apagá-lo.
function cp_ac_documento_pode_apagar( $anexo, $user_id ) {
	return cp_backoffice_utilizador_tem_acesso( $user_id ) || (int) $anexo->post_author === (int) $user_id;
}

function cp_ac_handle_documento_upload() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_documento_nonce'] ) || ! wp_verify_nonce( $_POST['cp_documento_nonce'], 'cp_documento_upload' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}

	$empresa_id = cp_ac_empresa_ativa();
	$voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! $empresa_id ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_sem_empresa', $voltar ) );
		exit;
	}
	if ( empty( $_FILES['ficheiro'] ) || UPLOAD_ERR_NO_FILE === $_FILES['ficheiro']['error'] ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_em_falta', $voltar ) );
		exit;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	$anexo_id = media_handle_upload( 'ficheiro', $empresa_id, [], [ 'test_form' => false ] );
	if ( is_wp_error( $anexo_id ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_invalido', $voltar ) );
		exit;
	}
	update_post_meta( $anexo_id, '_cp_doc_cliente', '1' );

	cp_push_notificar_equipa(
		__( 'Novo documento de cliente', 'contabilidade-pro' ),
		sprintf(
			/* translators: 1: nome da empresa, 2: nome do ficheiro */
			__( '%1$s enviou "%2$s".', 'contabilidade-pro' ),
			get_the_title( $empresa_id ),
			get_the_title( $anexo_id )
		),
		home_url( '/backoffice/' ) . '?empresa_id=' . $empresa_id,
		$empresa_id
	);

	wp_safe_redirect( add_query_arg( 'cp_sucesso', 'documento_enviado', $voltar ) );
	exit;
}
add_action( 'admin_post_cp_documento_upload', 'cp_ac_handle_documento_upload' );

function cp_ac_handle_documento_apagar() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_documento_nonce'] ) || ! wp_verify_nonce( $_POST['cp_documento_nonce'], 'cp_documento_apagar' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}

	$empresa_id = cp_ac_empresa_ativa();
	$anexo_id   = isset( $_POST['anexo_id'] ) ? absint( $_POST['anexo_id'] ) : 0;
	$voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$anexo      = $anexo_id ? get_post( $anexo_id ) : null;

	if ( ! $anexo || (int) $anexo->post_parent !== (int) $empresa_id || ! cp_ac_documento_pode_apagar( $anexo, get_current_user_id() ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_invalido', $voltar ) );
		exit;
	}

	wp_delete_attachment( $anexo_id, true );
	wp_safe_redirect( add_query_arg( 'cp_sucesso', 'documento_apagado', $voltar ) );
	exit;
}
add_action( 'admin_post_cp_documento_apagar', 'cp_ac_handle_documento_apagar' );

// ─── UI partilhada ──────────────────────────────────────────────────────────

// Só o formulário de envio (dropzone) — reutilizado pela vista Documentos
// e pelo botão flutuante do Dashboard.
function cp_ac_render_documento_form() {
	?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'cp_documento_upload', 'cp_documento_nonce' ); ?>
		<input type="hidden" name="action" value="cp_documento_upload">
		<div class="ac-dropzone">
			<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
			<div style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Enviar um novo documento', 'contabilidade-pro' ); ?></div>
			<input type="file" name="ficheiro" required style="font-size:12.5px;max-width:260px">
			<button class="ac-btn ac-btn-primary" type="submit"><?php esc_html_e( 'Carregar Documento', 'contabilidade-pro' ); ?></button>
		</div>
	</form>
	<?php
}

// Entrada no menu de ações rápidas do Dashboard (botão "+" flutuante) —
// ver cp_ac_render_fab_acoes_rapidas() em area-cliente-vistas.php. Por ora
// é a única ação, mas o menu já suporta mais no futuro.
function cp_ac_render_documento_fab_item() {
	?>
	<button type="button" class="ac-fab-menu-item" data-cp-fab-target="cp-doc-modal" role="menuitem">
		<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
		<span><?php esc_html_e( 'Adicionar documento', 'contabilidade-pro' ); ?></span>
	</button>
	<?php
}

// Modal aberta por essa ação — formulário de envio.
function cp_ac_render_documento_modal() {
	?>
	<div id="cp-doc-modal-backdrop" class="ac-modal-backdrop" data-cp-fab-modal hidden></div>
	<div id="cp-doc-modal" class="ac-modal" role="dialog" aria-modal="true" aria-labelledby="cp-doc-modal-title" hidden>
		<div class="ac-modal-head">
			<h3 id="cp-doc-modal-title" style="font-size:16px"><?php esc_html_e( 'Enviar documento', 'contabilidade-pro' ); ?></h3>
			<button type="button" class="ac-modal-close" data-cp-fab-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'contabilidade-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
			</button>
		</div>
		<?php cp_ac_render_documento_form(); ?>
	</div>
	<?php
}

function cp_ac_render_documentos( $empresa_id ) {
	$documentos = cp_ac_documentos( $empresa_id );
	$user_id    = get_current_user_id();
	?>
	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Envie aqui, a qualquer momento, faturas, despesas e outros documentos correntes.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>

		<?php cp_ac_render_documento_form(); ?>

		<?php if ( empty( $documentos ) ) : ?>
			<div style="margin-top:16px;color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não enviou nenhum documento.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<div style="margin-top:6px">
				<?php foreach ( $documentos as $doc ) : ?>
					<div class="ac-req-row">
						<div>
							<div style="font-size:13.5px;font-weight:600"><?php echo esc_html( get_the_title( $doc ) ); ?></div>
							<div style="font-size:12px;color:var(--ac-muted)"><?php printf( /* translators: %s: há quanto tempo */ esc_html__( 'enviado há %s', 'contabilidade-pro' ), esc_html( human_time_diff( get_post_time( 'U', true, $doc ), current_time( 'U', true ) ) ) ); ?></div>
						</div>
						<div style="display:flex;align-items:center;gap:6px">
							<a href="<?php echo esc_url( wp_get_attachment_url( $doc->ID ) ); ?>" target="_blank" rel="noopener" class="ac-btn ac-btn-ghost" style="padding:8px 14px;font-size:12.5px"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></a>
							<?php if ( cp_ac_documento_pode_apagar( $doc, $user_id ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Apagar este documento?', 'contabilidade-pro' ) ); ?>');">
									<?php wp_nonce_field( 'cp_documento_apagar', 'cp_documento_nonce' ); ?>
									<input type="hidden" name="action" value="cp_documento_apagar">
									<input type="hidden" name="anexo_id" value="<?php echo esc_attr( $doc->ID ); ?>">
									<button type="submit" class="ac-todo-btn ac-todo-btn-del" aria-label="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>">
										<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
