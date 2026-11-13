<?php
/**
 * Área de Cliente — Documentos.
 *
 * O cliente envia ficheiros (faturas, despesas, etc.) que ficam guardados
 * na media library do WordPress, anexados à empresa (post_parent = ID do
 * post cp_empresa) e marcados com '_cp_doc_cliente' para os distinguir de
 * qualquer outro anexo que o post possa vir a ter. Mesmo padrão de
 * admin-post.php + nonce usado em Pendentes e Lembretes — sem REST, sem JS.
 *
 * Daqui seguem para o ERP (Contabilidade Digital da CentralGest) pela Work:
 * a Work vai buscar os novos (GET cp/v1/documentos/novos), descarrega-os e
 * confirma (POST cp/v1/documentos/confirmar). A confirmação só marca o
 * anexo como recebido ('_cp_doc_work_recebido') — **não apaga nada**: o
 * WordPress guarda o ficheiro até haver indicação em contrário (decisão de
 * 2026-11-12). Ao cliente só interessa saber que está enviado; o estado na
 * CentralGest é controlado do lado da Work.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Só PDF e imagens (decisão de 2026-11-12). A Work volta a validar.
function cp_ac_documento_mimes_permitidos() {
	return [
		'pdf'          => 'application/pdf',
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
	];
}

// Tamanho máximo por ficheiro, em MB. Ainda por avaliar: 10 MB é um ponto
// de partida, ajustável pelo filtro sem mexer no código. O limite real
// também depende do upload_max_filesize do PHP no alojamento.
function cp_ac_documento_max_bytes() {
	return (int) apply_filters( 'cp_ac_documento_max_mb', 10 ) * MB_IN_BYTES;
}

function cp_ac_documento_erro_texto( $erro ) {
	$textos = [
		'documento_em_falta'    => __( 'Escolha um ficheiro e tente novamente.', 'contabilidade-pro' ),
		'documento_invalido'    => __( 'Escolha um ficheiro e tente novamente.', 'contabilidade-pro' ),
		'documento_sem_empresa' => __( 'Escolha um ficheiro e tente novamente.', 'contabilidade-pro' ),
		'documento_tipo'        => __( 'Só são aceites ficheiros PDF ou imagens (JPG, PNG).', 'contabilidade-pro' ),
		'documento_grande'      => sprintf(
			/* translators: %s: tamanho máximo, ex. "10 MB" */
			__( 'O ficheiro é demasiado grande. O máximo é %s.', 'contabilidade-pro' ),
			size_format( cp_ac_documento_max_bytes() )
		),
		'documento_ja_recebido' => __( 'Este documento já foi recebido pela contabilidade e não pode ser apagado.', 'contabilidade-pro' ),
		'documento_destino'     => __( 'Escolha a pasta, o ano e o mês do documento.', 'contabilidade-pro' ),
	];
	return isset( $textos[ $erro ] ) ? $textos[ $erro ] : '';
}

function cp_ac_documento_recebido_work( $anexo_id ) {
	return '' !== (string) get_post_meta( $anexo_id, '_cp_doc_work_recebido', true );
}

// Pastas de documentos da empresa na CentralGest, enviadas pela Work (POST
// cp/v1/documentos/pastas): [ { id, nome, anos: [2026, ...] } ]. O cliente
// escolhe nelas a pasta, o ano e o mês de cada documento. Sem pastas, o
// upload fica escondido (decisão de 2026-11-13). O WordPress nunca fala
// com a CentralGest: só guarda a escolha, e a Work envia o documento.
function cp_ac_documento_pastas( $empresa_id ) {
	$pastas = $empresa_id ? get_post_meta( $empresa_id, '_cp_doc_pastas', true ) : [];
	return is_array( $pastas ) ? $pastas : [];
}

function cp_ac_documento_upload_disponivel( $empresa_id ) {
	return ! empty( cp_ac_documento_pastas( $empresa_id ) );
}

function cp_ac_documento_nomes_meses() {
	return [
		1 => __( 'Janeiro', 'contabilidade-pro' ), 2 => __( 'Fevereiro', 'contabilidade-pro' ), 3 => __( 'Março', 'contabilidade-pro' ),
		4 => __( 'Abril', 'contabilidade-pro' ), 5 => __( 'Maio', 'contabilidade-pro' ), 6 => __( 'Junho', 'contabilidade-pro' ),
		7 => __( 'Julho', 'contabilidade-pro' ), 8 => __( 'Agosto', 'contabilidade-pro' ), 9 => __( 'Setembro', 'contabilidade-pro' ),
		10 => __( 'Outubro', 'contabilidade-pro' ), 11 => __( 'Novembro', 'contabilidade-pro' ), 12 => __( 'Dezembro', 'contabilidade-pro' ),
	];
}

// A pasta escolhida, se o destino é válido: pasta desta empresa, ano que
// existe nela e mês que não é futuro. A Work volta a validar.
function cp_ac_documento_destino_valido( $empresa_id, $pasta_id, $ano, $mes ) {
	$ano = (int) $ano;
	$mes = (int) $mes;
	if ( $mes < 1 || $mes > 12 ) return null;
	$ano_atual = (int) current_time( 'Y' );
	$mes_atual = (int) current_time( 'n' );
	if ( $ano > $ano_atual || ( $ano === $ano_atual && $mes > $mes_atual ) ) return null;
	foreach ( cp_ac_documento_pastas( $empresa_id ) as $pasta ) {
		if ( (string) $pasta['id'] === (string) $pasta_id ) {
			return in_array( $ano, array_map( 'intval', (array) $pasta['anos'] ), true ) ? $pasta : null;
		}
	}
	return null;
}

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
// Depois de a Work o receber, só a equipa: o documento já seguiu para a
// contabilidade e apagá-lo aqui não o tirava da CentralGest.
function cp_ac_documento_pode_apagar( $anexo, $user_id ) {
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) return true;
	return (int) $anexo->post_author === (int) $user_id && ! cp_ac_documento_recebido_work( $anexo->ID );
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
	$pasta_id = isset( $_POST['pasta_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pasta_id'] ) ) : '';
	$ano      = isset( $_POST['ano'] ) ? absint( $_POST['ano'] ) : 0;
	$mes      = isset( $_POST['mes'] ) ? absint( $_POST['mes'] ) : 0;
	if ( ! cp_ac_documento_destino_valido( $empresa_id, $pasta_id, $ano, $mes ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_destino', $voltar ) );
		exit;
	}

	if ( UPLOAD_ERR_INI_SIZE === $_FILES['ficheiro']['error'] || UPLOAD_ERR_FORM_SIZE === $_FILES['ficheiro']['error']
		|| (int) $_FILES['ficheiro']['size'] > cp_ac_documento_max_bytes() ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_grande', $voltar ) );
		exit;
	}
	$tipo = wp_check_filetype_and_ext( $_FILES['ficheiro']['tmp_name'], $_FILES['ficheiro']['name'], cp_ac_documento_mimes_permitidos() );
	if ( empty( $tipo['type'] ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_tipo', $voltar ) );
		exit;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	$anexo_id = media_handle_upload( 'ficheiro', $empresa_id, [], [ 'test_form' => false, 'mimes' => cp_ac_documento_mimes_permitidos() ] );
	if ( is_wp_error( $anexo_id ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_invalido', $voltar ) );
		exit;
	}
	update_post_meta( $anexo_id, '_cp_doc_cliente', '1' );
	update_post_meta( $anexo_id, '_cp_doc_pasta', $pasta_id );
	update_post_meta( $anexo_id, '_cp_doc_ano', $ano );
	update_post_meta( $anexo_id, '_cp_doc_mes', $mes );

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

	if ( ! $anexo || (int) $anexo->post_parent !== (int) $empresa_id || '1' !== get_post_meta( $anexo_id, '_cp_doc_cliente', true ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_invalido', $voltar ) );
		exit;
	}
	if ( ! cp_ac_documento_pode_apagar( $anexo, get_current_user_id() ) ) {
		$codigo = cp_ac_documento_recebido_work( $anexo_id ) ? 'documento_ja_recebido' : 'documento_invalido';
		wp_safe_redirect( add_query_arg( 'cp_erro', $codigo, $voltar ) );
		exit;
	}

	wp_delete_attachment( $anexo_id, true );
	wp_safe_redirect( add_query_arg( 'cp_sucesso', 'documento_apagado', $voltar ) );
	exit;
}
add_action( 'admin_post_cp_documento_apagar', 'cp_ac_handle_documento_apagar' );

// ─── REST para a Work (Área de Cliente → Work → CentralGest) ────────────────

function cp_ac_rest_routes_documentos() {
	register_rest_route( 'cp/v1', '/documentos/novos', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_documentos_novos',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id' => [ 'required' => true, 'type' => 'string' ],
			],
		],
	] );
	register_rest_route( 'cp/v1', '/documentos/pastas', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_documentos_pastas',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id' => [ 'required' => true, 'type' => 'string' ],
				'pastas'  => [ 'required' => true, 'type' => 'array' ],
			],
		],
	] );
	register_rest_route( 'cp/v1', '/documentos/confirmar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_documentos_confirmar',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id' => [ 'required' => true, 'type' => 'string' ],
				'ids'     => [ 'required' => true, 'type' => 'array' ],
			],
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_documentos' );

// Documentos do cliente que a Work ainda não confirmou, mais antigo primeiro.
function cp_ac_rest_documentos_novos( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$documentos = [];
	foreach ( array_reverse( cp_ac_documentos( $empresa_id ) ) as $doc ) {
		if ( cp_ac_documento_recebido_work( $doc->ID ) ) continue;
		$caminho      = get_attached_file( $doc->ID );
		$documentos[] = [
			'id'         => (string) $doc->ID,
			'nome'       => $caminho ? wp_basename( $caminho ) : get_the_title( $doc ),
			'url'        => wp_get_attachment_url( $doc->ID ),
			'mime'       => get_post_mime_type( $doc ),
			'tamanho'    => ( $caminho && file_exists( $caminho ) ) ? filesize( $caminho ) : null,
			'enviado_em' => get_post_time( 'Y-m-d H:i:s', false, $doc ),
			// Destino escolhido pelo cliente (vazio nos enviados antes de haver pastas).
			'pasta_id'   => (string) get_post_meta( $doc->ID, '_cp_doc_pasta', true ),
			'ano'        => (int) get_post_meta( $doc->ID, '_cp_doc_ano', true ) ?: null,
			'mes'        => (int) get_post_meta( $doc->ID, '_cp_doc_mes', true ) ?: null,
		];
	}
	return rest_ensure_response( [ 'documentos' => $documentos ] );
}

// Substitui as pastas da empresa pelas que a Work envia (lista completa;
// vazia esconde o upload).
function cp_ac_rest_documentos_pastas( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$pastas = [];
	foreach ( (array) $request->get_param( 'pastas' ) as $pasta ) {
		if ( ! is_array( $pasta ) || empty( $pasta['id'] ) || empty( $pasta['nome'] ) ) continue;
		$anos = array_values( array_filter( array_map( 'absint', (array) ( $pasta['anos'] ?? [] ) ) ) );
		if ( ! $anos ) continue;
		$pastas[] = [
			'id'   => sanitize_text_field( (string) $pasta['id'] ),
			'nome' => sanitize_text_field( $pasta['nome'] ),
			'anos' => $anos,
		];
	}
	update_post_meta( $empresa_id, '_cp_doc_pastas', $pastas );
	return rest_ensure_response( [ 'ok' => true, 'pastas' => count( $pastas ) ] );
}

// Marca como recebidos pela Work. Não apaga: o ficheiro fica no WordPress.
function cp_ac_rest_documentos_confirmar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$validos     = array_map( 'intval', wp_list_pluck( cp_ac_documentos( $empresa_id ), 'ID' ) );
	$confirmados = 0;
	foreach ( (array) $request->get_param( 'ids' ) as $id ) {
		$id = absint( $id );
		if ( ! $id || ! in_array( $id, $validos, true ) ) continue;
		if ( ! cp_ac_documento_recebido_work( $id ) ) {
			update_post_meta( $id, '_cp_doc_work_recebido', current_time( 'mysql' ) );
		}
		$confirmados++;
	}
	return rest_ensure_response( [ 'ok' => true, 'confirmados' => $confirmados ] );
}

// ─── UI partilhada ──────────────────────────────────────────────────────────

// Só o formulário de envio (dropzone) — reutilizado pela vista Documentos
// e pelo botão flutuante do Dashboard.
function cp_ac_render_documento_form() {
	$pastas = cp_ac_documento_pastas( cp_ac_empresa_ativa() );
	if ( ! $pastas ) return;
	$ano_atual = (int) current_time( 'Y' );
	$mes_atual = (int) current_time( 'n' );
	$anos      = [];
	foreach ( $pastas as $pasta ) $anos = array_merge( $anos, array_map( 'intval', (array) $pasta['anos'] ) );
	$anos = array_unique( $anos );
	rsort( $anos );
	?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'cp_documento_upload', 'cp_documento_nonce' ); ?>
		<input type="hidden" name="action" value="cp_documento_upload">
		<div class="ac-dropzone">
			<div class="ac-dropzone-icon">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
			</div>
			<div style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Enviar um novo documento', 'contabilidade-pro' ); ?></div>
			<div style="font-size:12px;color:var(--ac-muted)"><?php esc_html_e( 'Fatura, despesa ou outro documento', 'contabilidade-pro' ); ?></div>
			<input type="file" name="ficheiro" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="ac-file-input" style="font-size:12.5px;max-width:260px">
			<div class="ac-doc-destino">
				<label class="ac-doc-campo ac-doc-campo-pasta">
					<span><?php esc_html_e( 'Pasta', 'contabilidade-pro' ); ?></span>
					<select name="pasta_id" required data-cp-doc-pasta>
						<?php foreach ( $pastas as $pasta ) : ?>
							<option value="<?php echo esc_attr( $pasta['id'] ); ?>" data-anos="<?php echo esc_attr( implode( ',', array_map( 'intval', (array) $pasta['anos'] ) ) ); ?>"><?php echo esc_html( $pasta['nome'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ac-doc-campo">
					<span><?php esc_html_e( 'Mês', 'contabilidade-pro' ); ?></span>
					<select name="mes" required data-cp-doc-mes>
						<?php foreach ( cp_ac_documento_nomes_meses() as $n => $nome_mes ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $n, $mes_atual ); ?>><?php echo esc_html( $nome_mes ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ac-doc-campo">
					<span><?php esc_html_e( 'Ano', 'contabilidade-pro' ); ?></span>
					<select name="ano" required data-cp-doc-ano>
						<?php foreach ( $anos as $a ) : if ( $a > $ano_atual ) continue; ?>
							<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $a, $ano_atual ); ?>><?php echo esc_html( $a ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<div style="font-size:11.5px;color:var(--ac-muted)"><?php printf( /* translators: %s: tamanho máximo */ esc_html__( 'PDF ou imagem, até %s', 'contabilidade-pro' ), esc_html( size_format( cp_ac_documento_max_bytes() ) ) ); ?></div>
			<button class="ac-btn ac-btn-primary" type="submit" style="margin-top:4px"><?php esc_html_e( 'Enviar', 'contabilidade-pro' ); ?></button>
		</div>
	</form>
	<?php
	cp_ac_render_documento_form_script( $ano_atual, $mes_atual );
}

// Em cada formulário: só os anos que existem na pasta escolhida e, no ano
// corrente, só os meses até ao atual. Impresso uma vez por página.
function cp_ac_render_documento_form_script( $ano_atual, $mes_atual ) {
	static $impresso = false;
	if ( $impresso ) return;
	$impresso = true;
	?>
	<script>
	(function () {
		var anoAtual = <?php echo (int) $ano_atual; ?>, mesAtual = <?php echo (int) $mes_atual; ?>;
		function ligar(form) {
			var pasta = form.querySelector('[data-cp-doc-pasta]'), ano = form.querySelector('[data-cp-doc-ano]'), mes = form.querySelector('[data-cp-doc-mes]');
			if (!pasta || !ano || !mes || form.dataset.cpDocLigado) return;
			form.dataset.cpDocLigado = '1';
			function atualizar() {
				var anos = (pasta.options[pasta.selectedIndex].getAttribute('data-anos') || '').split(',');
				var ultimo = null;
				Array.prototype.forEach.call(ano.options, function (o) { var ok = anos.indexOf(o.value) !== -1; o.hidden = !ok; o.disabled = !ok; if (ok && !ultimo) ultimo = o; });
				if (ano.selectedOptions.length === 0 || ano.selectedOptions[0].disabled) { if (ultimo) ano.value = ultimo.value; }
				var limite = parseInt(ano.value, 10) === anoAtual ? mesAtual : 12;
				Array.prototype.forEach.call(mes.options, function (o) { var ok = parseInt(o.value, 10) <= limite; o.hidden = !ok; o.disabled = !ok; });
				if (parseInt(mes.value, 10) > limite) mes.value = String(limite);
			}
			pasta.addEventListener('change', atualizar);
			ano.addEventListener('change', atualizar);
			atualizar();
		}
		document.querySelectorAll('form').forEach(ligar);
	})();
	</script>
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
	if ( ! cp_ac_documento_upload_disponivel( cp_ac_empresa_ativa() ) ) return;
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

// Grupos da lista de documentos: um por pasta, pela ordem das pastas que a
// Work enviou, e no fim "Outros documentos" (enviados antes de haver pastas,
// ou de uma pasta que entretanto deixou de existir). Dentro de cada grupo,
// do ano/mês mais recente para o mais antigo.
function cp_ac_documentos_por_pasta( $empresa_id ) {
	$grupos = [];
	foreach ( cp_ac_documento_pastas( $empresa_id ) as $pasta ) {
		$grupos[ (string) $pasta['id'] ] = [ 'nome' => $pasta['nome'], 'docs' => [] ];
	}
	$outros = [ 'nome' => __( 'Outros documentos', 'contabilidade-pro' ), 'docs' => [] ];
	foreach ( cp_ac_documentos( $empresa_id ) as $doc ) {
		$pasta = (string) get_post_meta( $doc->ID, '_cp_doc_pasta', true );
		if ( isset( $grupos[ $pasta ] ) ) {
			$grupos[ $pasta ]['docs'][] = $doc;
		} else {
			$outros['docs'][] = $doc;
		}
	}
	$grupos[''] = $outros;
	foreach ( $grupos as &$grupo ) {
		usort( $grupo['docs'], function ( $a, $b ) {
			$chave = function ( $d ) {
				return sprintf( '%04d%02d', (int) get_post_meta( $d->ID, '_cp_doc_ano', true ), (int) get_post_meta( $d->ID, '_cp_doc_mes', true ) )
					. get_post_time( 'YmdHis', true, $d );
			};
			return strcmp( $chave( $b ), $chave( $a ) );
		} );
	}
	unset( $grupo );
	return array_filter( $grupos, function ( $g ) { return ! empty( $g['docs'] ); } );
}

function cp_ac_documento_nome_ficheiro( $doc ) {
	$caminho = get_attached_file( $doc->ID );
	return $caminho ? wp_basename( $caminho ) : get_the_title( $doc );
}

function cp_ac_render_documentos( $empresa_id ) {
	$grupos    = cp_ac_documentos_por_pasta( $empresa_id );
	$user_id   = get_current_user_id();
	$nomes_mes = cp_ac_documento_nomes_meses();
	?>
	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Envie aqui, a qualquer momento, faturas, despesas e outros documentos correntes.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>

		<?php cp_ac_render_documento_form(); ?>

		<?php if ( empty( $grupos ) ) : ?>
			<div style="margin-top:16px;color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não enviou nenhum documento.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<?php foreach ( $grupos as $grupo ) : ?>
				<section class="ac-doc-grupo">
					<div class="ac-doc-grupo-head">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
						<span><?php echo esc_html( $grupo['nome'] ); ?></span>
						<span class="ac-doc-grupo-n"><?php echo esc_html( count( $grupo['docs'] ) ); ?></span>
					</div>
					<div class="ac-doc-cab" aria-hidden="true">
						<span><?php esc_html_e( 'Ficheiro', 'contabilidade-pro' ); ?></span>
						<span><?php esc_html_e( 'Enviado em', 'contabilidade-pro' ); ?></span>
						<span><?php esc_html_e( 'Ano / Mês', 'contabilidade-pro' ); ?></span>
						<span></span>
					</div>
					<?php foreach ( $grupo['docs'] as $doc ) :
						$nome    = cp_ac_documento_nome_ficheiro( $doc );
						$url     = wp_get_attachment_url( $doc->ID );
						$e_pdf   = 'application/pdf' === get_post_mime_type( $doc );
						$doc_mes = (int) get_post_meta( $doc->ID, '_cp_doc_mes', true );
						$doc_ano = (int) get_post_meta( $doc->ID, '_cp_doc_ano', true );
						?>
						<div class="ac-doc-linha">
							<div class="ac-doc-nome">
								<span class="ac-doc-icone" aria-hidden="true">
									<?php if ( $e_pdf ) : ?>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M9 13h6M9 17h4"></path></svg>
									<?php else : ?>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="1.6"></circle><path d="m21 16-5-5-9 9"></path></svg>
									<?php endif; ?>
								</span>
								<span class="ac-doc-nome-texto" title="<?php echo esc_attr( $nome ); ?>"><?php echo esc_html( $nome ); ?></span>
							</div>
							<?php $periodo = ( $doc_ano && isset( $nomes_mes[ $doc_mes ] ) ) ? $nomes_mes[ $doc_mes ] . ' ' . $doc_ano : ''; ?>
							<div class="ac-doc-dado ac-doc-dado-data" data-periodo="<?php echo esc_attr( $periodo ? ' · ' . $periodo : '' ); ?>"><?php echo esc_html( get_post_time( 'd/m/Y', false, $doc ) ); ?></div>
							<div class="ac-doc-dado ac-doc-dado-periodo"><?php echo $periodo ? esc_html( $periodo ) : '—'; ?></div>
							<div class="ac-doc-acoes">
								<button type="button" class="ac-btn ac-btn-ghost ac-doc-ver" data-cp-fab-target="cp-doc-viewer"
									data-cp-doc-url="<?php echo esc_url( $url ); ?>" data-cp-doc-tipo="<?php echo $e_pdf ? 'pdf' : 'imagem'; ?>"
									data-cp-doc-nome="<?php echo esc_attr( $nome ); ?>"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></button>
								<?php if ( cp_ac_documento_pode_apagar( $doc, $user_id ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Apagar este documento?', 'contabilidade-pro' ) ); ?>');">
										<?php wp_nonce_field( 'cp_documento_apagar', 'cp_documento_nonce' ); ?>
										<input type="hidden" name="action" value="cp_documento_apagar">
										<input type="hidden" name="anexo_id" value="<?php echo esc_attr( $doc->ID ); ?>">
										<button type="submit" class="ac-todo-btn ac-todo-btn-del" title="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>" aria-label="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>">
											<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path></svg>
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
			<?php cp_ac_render_documento_viewer(); ?>
		<?php endif; ?>
	</div>
	<?php
}

// Visualizador na própria página: o PDF no leitor do browser (iframe) e as
// imagens diretamente. Abre e fecha como as outras modais (ac-fab.js: botão
// com data-cp-fab-target, fechar, clicar fora, Escape); este script só põe e
// tira o conteúdo. O Chrome em Android não mostra PDFs embutidos: aí abre
// noutro separador.
function cp_ac_render_documento_viewer() {
	?>
	<div id="cp-doc-viewer-backdrop" class="ac-modal-backdrop" data-cp-fab-modal hidden></div>
	<div id="cp-doc-viewer" class="ac-modal ac-modal-doc" role="dialog" aria-modal="true" aria-labelledby="cp-doc-viewer-title" hidden>
		<div class="ac-modal-head">
			<h3 id="cp-doc-viewer-title" class="ac-doc-viewer-titulo"></h3>
			<div class="ac-doc-viewer-acoes">
				<a class="ac-btn ac-btn-ghost ac-doc-viewer-abrir" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir noutro separador', 'contabilidade-pro' ); ?></a>
				<button type="button" class="ac-modal-close" data-cp-fab-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'contabilidade-pro' ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
				</button>
			</div>
		</div>
		<div class="ac-doc-viewer-corpo"></div>
	</div>
	<script>
	(function () {
		var modal = document.getElementById('cp-doc-viewer');
		if (!modal) return;
		var corpo = modal.querySelector('.ac-doc-viewer-corpo'), titulo = modal.querySelector('.ac-doc-viewer-titulo'), abrir = modal.querySelector('.ac-doc-viewer-abrir');
		var android = /Android/i.test(navigator.userAgent);
		document.querySelectorAll('.ac-doc-ver').forEach(function (botao) {
			botao.addEventListener('click', function (evento) {
				var url = botao.getAttribute('data-cp-doc-url'), tipo = botao.getAttribute('data-cp-doc-tipo');
				if (tipo === 'pdf' && android) {
					evento.stopImmediatePropagation();
					window.open(url, '_blank', 'noopener');
					return;
				}
				titulo.textContent = botao.getAttribute('data-cp-doc-nome');
				abrir.href = url;
				corpo.innerHTML = '';
				var el;
				if (tipo === 'pdf') { el = document.createElement('iframe'); el.title = titulo.textContent; }
				else { el = document.createElement('img'); el.alt = titulo.textContent; }
				el.src = url;
				corpo.appendChild(el);
			}, true);
		});
		// Ao fechar (ac-fab.js põe hidden), tira o documento da página.
		new MutationObserver(function () { if (modal.hidden) corpo.innerHTML = ''; }).observe(modal, { attributes: true, attributeFilter: ['hidden'] });
	})();
	</script>
	<?php
}
