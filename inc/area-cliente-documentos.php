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
 * CentralGest é controlado do lado da Work. Quando a equipa apaga um
 * documento na Work, a Work pede aqui para o apagar também
 * (POST cp/v1/documentos/apagar).
 *
 * Apagar no site (2026-11-23): enquanto a Work não recebeu o documento,
 * apaga logo. Depois de recebido, fica só um pedido (meta
 * '_cp_doc_apagar_pedido'), que a Work lê em /documentos/novos
 * ("apagar_pedidos") e trata com as regras da CentralGest: só se apaga o
 * que ainda está em "Documentos não classificados" na Client Connect. Se
 * apagar, chama /documentos/apagar; se recusar, devolve o motivo em
 * /documentos/apagar-recusado (meta '_cp_doc_apagar_recusado') e o
 * documento deixa de ter o botão de apagar.
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

// Quantos ficheiros de uma vez. O PHP também limita (max_file_uploads, 20
// por omissão, e post_max_size para o total do pedido).
function cp_ac_documento_max_ficheiros() {
	return max( 1, min( (int) apply_filters( 'cp_ac_documento_max_ficheiros', 20 ), (int) ini_get( 'max_file_uploads' ) ?: 20 ) );
}

// Título do aviso verde depois de enviar ou apagar (Dashboard e Documentos).
// Com vários ficheiros, $n diz quantos foram aceites e $recusados quantos não.
function cp_ac_documento_sucesso_titulo( $sucesso, $n = 1, $recusados = 0 ) {
	if ( 'documento_apagado' === $sucesso ) return __( 'Documento apagado', 'contabilidade-pro' );
	if ( 'documento_apagar_pedido' === $sucesso ) return __( 'Pedido para apagar enviado · fica apagado dentro de minutos, se ainda não tiver sido lançado na contabilidade', 'contabilidade-pro' );
	if ( 'documento_enviado' !== $sucesso ) return '';
	$n = max( 1, (int) $n );
	$titulo = 1 === $n
		? __( 'Documento enviado', 'contabilidade-pro' )
		/* translators: %d: número de documentos */
		: sprintf( __( '%d documentos enviados', 'contabilidade-pro' ), $n );
	if ( $recusados > 0 ) {
		$titulo .= ' · ' . sprintf(
			/* translators: 1: número de ficheiros recusados, 2: tamanho máximo */
			_n( '%1$d ficheiro não foi aceite (só PDF ou imagem, até %2$s)', '%1$d ficheiros não foram aceites (só PDF ou imagem, até %2$s)', (int) $recusados, 'contabilidade-pro' ),
			(int) $recusados,
			size_format( cp_ac_documento_max_bytes() )
		);
	}
	return $titulo;
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
		'documento_ja_recebido' => __( 'Este documento já foi lançado na contabilidade e não pode ser apagado.', 'contabilidade-pro' ),
		'documento_destino'     => __( 'Escolha a pasta, o ano e o mês do documento.', 'contabilidade-pro' ),
		'documento_demasiados'  => sprintf(
			/* translators: %d: número máximo de ficheiros */
			__( 'Pode enviar até %d ficheiros de cada vez.', 'contabilidade-pro' ),
			cp_ac_documento_max_ficheiros()
		),
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

// Destino do último envio, que vem no endereço depois de enviar: [ pasta_id,
// ano, mes ], ou null se não há ou não é válido nesta empresa (ex. mudou de
// empresa entretanto) — aí o formulário abre na primeira pasta e no mês atual.
function cp_ac_documento_destino_anterior( $empresa_id, $pedido ) {
	foreach ( [ 'cp_doc_pasta', 'cp_doc_ano', 'cp_doc_mes' ] as $campo ) {
		if ( ! isset( $pedido[ $campo ] ) || ! is_scalar( $pedido[ $campo ] ) ) return null;
	}
	$pasta_id = sanitize_text_field( wp_unslash( $pedido['cp_doc_pasta'] ) );
	$ano      = absint( $pedido['cp_doc_ano'] );
	$mes      = absint( $pedido['cp_doc_mes'] );
	if ( ! cp_ac_documento_destino_valido( $empresa_id, $pasta_id, $ano, $mes ) ) return null;
	return [ 'pasta_id' => $pasta_id, 'ano' => $ano, 'mes' => $mes ];
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

function cp_ac_documento_apagar_pedido( $anexo_id ) {
	return '' !== (string) get_post_meta( $anexo_id, '_cp_doc_apagar_pedido', true );
}

function cp_ac_documento_apagar_recusado( $anexo_id ) {
	return (string) get_post_meta( $anexo_id, '_cp_doc_apagar_recusado', true );
}

// Quem enviou o documento, ou qualquer elemento da equipa, pode apagá-lo
// (ou, depois de a Work o receber, pedir para o apagar). Não há botão
// enquanto há um pedido por tratar nem depois de a Work o recusar
// (documento já classificado na CentralGest).
function cp_ac_documento_pode_apagar( $anexo, $user_id ) {
	if ( cp_ac_documento_apagar_pedido( $anexo->ID ) || '' !== cp_ac_documento_apagar_recusado( $anexo->ID ) ) return false;
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) return true;
	return (int) $anexo->post_author === (int) $user_id;
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
	cp_ac_exigir_ferramenta( $empresa_id, 'documentos' );

	$pasta_id = isset( $_POST['pasta_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pasta_id'] ) ) : '';
	$ano      = isset( $_POST['ano'] ) ? absint( $_POST['ano'] ) : 0;
	$mes      = isset( $_POST['mes'] ) ? absint( $_POST['mes'] ) : 0;
	// O destino volta com a página (envio aceite ou recusado), para o
	// formulário continuar na pasta, mês e ano escolhidos — ver
	// cp_ac_documento_destino_anterior().
	$voltar = add_query_arg( [
		'cp_doc_pasta' => rawurlencode( $pasta_id ),
		'cp_doc_ano'   => $ano,
		'cp_doc_mes'   => $mes,
	], $voltar );

	$ficheiros = cp_ac_documento_ficheiros_do_pedido( $_FILES );
	if ( ! $ficheiros ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_em_falta', $voltar ) );
		exit;
	}
	if ( count( $ficheiros ) > cp_ac_documento_max_ficheiros() ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_demasiados', $voltar ) );
		exit;
	}
	if ( ! cp_ac_documento_destino_valido( $empresa_id, $pasta_id, $ano, $mes ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_destino', $voltar ) );
		exit;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	// Cada ficheiro passa a ser um anexo próprio, todos com o mesmo destino.
	// A Work recebe-os e envia-os para a CentralGest um a um. Um ficheiro
	// recusado não impede os outros.
	$aceites   = [];
	$primeiro_erro = '';
	foreach ( $ficheiros as $ficheiro ) {
		$erro = cp_ac_documento_validar_ficheiro( $ficheiro );
		if ( '' === $erro ) {
			$_FILES['cp_doc_ficheiro'] = $ficheiro;
			$anexo_id = media_handle_upload( 'cp_doc_ficheiro', $empresa_id, [], [ 'test_form' => false, 'mimes' => cp_ac_documento_mimes_permitidos() ] );
			unset( $_FILES['cp_doc_ficheiro'] );
			$erro = is_wp_error( $anexo_id ) ? 'documento_invalido' : '';
		}
		if ( '' !== $erro ) {
			if ( '' === $primeiro_erro ) $primeiro_erro = $erro;
			continue;
		}
		update_post_meta( $anexo_id, '_cp_doc_cliente', '1' );
		update_post_meta( $anexo_id, '_cp_doc_pasta', $pasta_id );
		update_post_meta( $anexo_id, '_cp_doc_ano', $ano );
		update_post_meta( $anexo_id, '_cp_doc_mes', $mes );
		$aceites[] = $anexo_id;
	}

	if ( ! $aceites ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', $primeiro_erro, $voltar ) );
		exit;
	}

	// Uma só notificação por envio, mesmo com vários ficheiros.
	cp_push_notificar_equipa(
		__( 'Novo documento de cliente', 'contabilidade-pro' ),
		1 === count( $aceites )
			? sprintf(
				/* translators: 1: nome da empresa, 2: nome do ficheiro */
				__( '%1$s enviou "%2$s".', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				get_the_title( $aceites[0] )
			)
			: sprintf(
				/* translators: 1: nome da empresa, 2: número de documentos */
				__( '%1$s enviou %2$d documentos.', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				count( $aceites )
			),
		home_url( '/backoffice/' ) . '?empresa_id=' . $empresa_id,
		$empresa_id,
		'documentos'
	);

	// A lista abre na tab do mês onde ficaram os documentos enviados.
	$args = [ 'cp_sucesso' => 'documento_enviado', 'cp_n' => count( $aceites ), 'mes' => sprintf( '%04d-%02d', $ano, $mes ) ];
	$recusados = count( $ficheiros ) - count( $aceites );
	if ( $recusados ) $args['cp_recusados'] = $recusados;
	wp_safe_redirect( add_query_arg( $args, $voltar ) );
	exit;
}

// Os ficheiros do campo "ficheiros[]" (vários de uma vez), um array por
// ficheiro no formato de $_FILES. Aceita também o campo antigo "ficheiro"
// (um só), para formulários que ainda estejam abertos noutro separador.
function cp_ac_documento_ficheiros_do_pedido( $files ) {
	$lista = [];
	if ( ! empty( $files['ficheiros'] ) && is_array( $files['ficheiros']['name'] ) ) {
		foreach ( array_keys( $files['ficheiros']['name'] ) as $i ) {
			$lista[] = [
				'name'     => $files['ficheiros']['name'][ $i ],
				'type'     => $files['ficheiros']['type'][ $i ],
				'tmp_name' => $files['ficheiros']['tmp_name'][ $i ],
				'error'    => (int) $files['ficheiros']['error'][ $i ],
				'size'     => (int) $files['ficheiros']['size'][ $i ],
			];
		}
	} elseif ( ! empty( $files['ficheiro'] ) && ! is_array( $files['ficheiro']['name'] ) ) {
		$lista[] = $files['ficheiro'];
	}
	return array_values( array_filter( $lista, function ( $f ) {
		return UPLOAD_ERR_NO_FILE !== (int) $f['error'];
	} ) );
}

// '' se o ficheiro pode seguir, senão o código de erro (ver
// cp_ac_documento_erro_texto).
function cp_ac_documento_validar_ficheiro( $ficheiro ) {
	if ( UPLOAD_ERR_INI_SIZE === (int) $ficheiro['error'] || UPLOAD_ERR_FORM_SIZE === (int) $ficheiro['error']
		|| (int) $ficheiro['size'] > cp_ac_documento_max_bytes() ) {
		return 'documento_grande';
	}
	if ( UPLOAD_ERR_OK !== (int) $ficheiro['error'] ) return 'documento_invalido';
	$tipo = wp_check_filetype_and_ext( $ficheiro['tmp_name'], $ficheiro['name'], cp_ac_documento_mimes_permitidos() );
	return empty( $tipo['type'] ) ? 'documento_tipo' : '';
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
	cp_ac_exigir_ferramenta( $empresa_id, 'documentos' );

	if ( ! $anexo || (int) $anexo->post_parent !== (int) $empresa_id || '1' !== get_post_meta( $anexo_id, '_cp_doc_cliente', true ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'documento_invalido', $voltar ) );
		exit;
	}
	if ( ! cp_ac_documento_pode_apagar( $anexo, get_current_user_id() ) ) {
		$codigo = '' !== cp_ac_documento_apagar_recusado( $anexo_id ) ? 'documento_ja_recebido' : 'documento_invalido';
		wp_safe_redirect( add_query_arg( 'cp_erro', $codigo, $voltar ) );
		exit;
	}

	// Já recebido pela Work: só ela sabe se ainda se pode apagar na
	// CentralGest. Fica o pedido; a Work trata-o na ronda seguinte.
	if ( cp_ac_documento_recebido_work( $anexo_id ) ) {
		update_post_meta( $anexo_id, '_cp_doc_apagar_pedido', current_time( 'mysql' ) );
		wp_safe_redirect( add_query_arg( 'cp_sucesso', 'documento_apagar_pedido', $voltar ) );
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
	register_rest_route( 'cp/v1', '/documentos/apagar-recusado', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_documentos_apagar_recusado',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id' => [ 'required' => true, 'type' => 'string' ],
				'itens'   => [ 'required' => true, 'type' => 'array' ],
			],
		],
	] );
	register_rest_route( 'cp/v1', '/documentos/apagar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_documentos_apagar',
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
	// Hora da última recolha pela Work — mostrada no topo do Backoffice.
	update_option( 'cp_work_ultima_recolha', current_time( 'mysql' ), false );
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$documentos     = [];
	$apagar_pedidos = [];
	foreach ( array_reverse( cp_ac_documentos( $empresa_id ) ) as $doc ) {
		if ( cp_ac_documento_recebido_work( $doc->ID ) ) {
			if ( cp_ac_documento_apagar_pedido( $doc->ID ) ) {
				$apagar_pedidos[] = [
					'id'        => (string) $doc->ID,
					'pedido_em' => (string) get_post_meta( $doc->ID, '_cp_doc_apagar_pedido', true ),
				];
			}
			continue;
		}
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
	// apagar_pedidos: documentos já recebidos que alguém pediu para apagar
	// no site (a Work decide com as regras da CentralGest).
	return rest_ensure_response( [ 'documentos' => $documentos, 'apagar_pedidos' => $apagar_pedidos ] );
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

// A equipa apagou o documento na Work (2026-11-23): apaga o anexo e o
// ficheiro. Só documentos de cliente desta empresa; um id que já cá não
// está (o cliente ou a equipa apagou-o no site) conta como apagado.
function cp_ac_rest_documentos_apagar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$validos  = array_map( 'intval', wp_list_pluck( cp_ac_documentos( $empresa_id ), 'ID' ) );
	$apagados = 0;
	$ausentes = 0;
	foreach ( (array) $request->get_param( 'ids' ) as $id ) {
		$id = absint( $id );
		if ( ! $id ) continue;
		if ( ! in_array( $id, $validos, true ) ) {
			$anexo = get_post( $id );
			// Existe mas é de outra empresa ou não é documento de cliente: recusa.
			if ( $anexo && 'attachment' === $anexo->post_type ) {
				return new WP_Error( 'cp_documento_invalido', 'O documento não pertence a esta empresa.', [ 'status' => 400 ] );
			}
			$ausentes++;
			continue;
		}
		if ( wp_delete_attachment( $id, true ) ) $apagados++;
	}
	return rest_ensure_response( [ 'ok' => true, 'apagados' => $apagados, 'ja_nao_existiam' => $ausentes ] );
}

// A Work recusou apagar (documento já classificado na CentralGest): tira o
// pedido e guarda o motivo, que o cliente vê no lugar do botão.
function cp_ac_rest_documentos_apagar_recusado( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$validos = array_map( 'intval', wp_list_pluck( cp_ac_documentos( $empresa_id ), 'ID' ) );
	$n       = 0;
	foreach ( (array) $request->get_param( 'itens' ) as $item ) {
		$id = is_array( $item ) ? absint( $item['id'] ?? 0 ) : 0;
		if ( ! $id || ! in_array( $id, $validos, true ) ) continue;
		$motivo = sanitize_text_field( (string) ( $item['motivo'] ?? '' ) );
		update_post_meta( $id, '_cp_doc_apagar_recusado', $motivo ?: __( 'Não pode ser apagado.', 'contabilidade-pro' ) );
		delete_post_meta( $id, '_cp_doc_apagar_pedido' );
		$n++;
	}
	return rest_ensure_response( [ 'ok' => true, 'recusados' => $n ] );
}

// ─── UI partilhada ──────────────────────────────────────────────────────────

// Só o formulário de envio (dropzone) — reutilizado pela vista Documentos
// e pelo botão flutuante do Dashboard.
function cp_ac_render_documento_form() {
	$empresa_id = cp_ac_empresa_ativa();
	$pastas     = cp_ac_documento_pastas( $empresa_id );
	if ( ! $pastas ) return;
	$ano_atual = (int) current_time( 'Y' );
	$mes_atual = (int) current_time( 'n' );
	// Depois de enviar, fica no mesmo destino (dá para continuar a enviar).
	$destino = cp_ac_documento_destino_anterior( $empresa_id, $_GET );
	if ( ! $destino ) $destino = [ 'pasta_id' => '', 'ano' => $ano_atual, 'mes' => $mes_atual ];
	$anos      = [];
	foreach ( $pastas as $pasta ) $anos = array_merge( $anos, array_map( 'intval', (array) $pasta['anos'] ) );
	$anos = array_unique( $anos );
	rsort( $anos );
	?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'cp_documento_upload', 'cp_documento_nonce' ); ?>
		<input type="hidden" name="action" value="cp_documento_upload">
		<?php // Arrastar e largar (2026-11-27): a zona inteira aceita ficheiros; o input nativo fica escondido dentro do botão "ou escolha os ficheiros" (sem id — o formulário pode aparecer duas vezes na página, aqui e na janela do "+"). ?>
		<div class="ac-dropzone" data-cp-doc-drop>
			<div class="ac-dropzone-icon">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
			</div>
			<div style="font-size:13.5px;font-weight:600" data-cp-doc-drop-titulo><?php esc_html_e( 'Arraste os documentos para aqui', 'contabilidade-pro' ); ?></div>
			<div style="font-size:12px;color:var(--ac-muted)"><?php esc_html_e( 'Faturas, despesas ou outros documentos — vários de uma vez', 'contabilidade-pro' ); ?></div>
			<label class="ac-btn ac-btn-ghost ac-dropzone-escolher">
				<input type="file" name="ficheiros[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="ac-dropzone-input" data-cp-doc-max="<?php echo esc_attr( cp_ac_documento_max_ficheiros() ); ?>">
				<?php esc_html_e( 'ou escolha os ficheiros', 'contabilidade-pro' ); ?>
			</label>
			<ul class="ac-dropzone-lista" data-cp-doc-escolhidos hidden></ul>
			<div class="ac-doc-destino">
				<label class="ac-doc-campo ac-doc-campo-pasta">
					<span><?php esc_html_e( 'Pasta', 'contabilidade-pro' ); ?></span>
					<select name="pasta_id" required data-cp-doc-pasta>
						<?php foreach ( $pastas as $pasta ) : ?>
							<option value="<?php echo esc_attr( $pasta['id'] ); ?>" data-anos="<?php echo esc_attr( implode( ',', array_map( 'intval', (array) $pasta['anos'] ) ) ); ?>" <?php selected( (string) $pasta['id'], $destino['pasta_id'] ); ?>><?php echo esc_html( $pasta['nome'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ac-doc-campo">
					<span><?php esc_html_e( 'Mês', 'contabilidade-pro' ); ?></span>
					<select name="mes" required data-cp-doc-mes>
						<?php foreach ( cp_ac_documento_nomes_meses() as $n => $nome_mes ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $n, $destino['mes'] ); ?>><?php echo esc_html( $nome_mes ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ac-doc-campo">
					<span><?php esc_html_e( 'Ano', 'contabilidade-pro' ); ?></span>
					<select name="ano" required data-cp-doc-ano>
						<?php foreach ( $anos as $a ) : if ( $a > $ano_atual ) continue; ?>
							<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $a, $destino['ano'] ); ?>><?php echo esc_html( $a ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<div style="font-size:11.5px;color:var(--ac-muted)"><?php printf( /* translators: 1: tamanho máximo, 2: número máximo de ficheiros */ esc_html__( 'PDF ou imagem, até %1$s cada, no máximo %2$d de cada vez. Todos vão para a mesma pasta, mês e ano.', 'contabilidade-pro' ), esc_html( size_format( cp_ac_documento_max_bytes() ) ), (int) cp_ac_documento_max_ficheiros() ); ?></div>
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
			ligarZona(form);
		}

		// Zona de envio: arrastar e largar ou escolher; lista dos escolhidos;
		// só PDF/imagem; máximo por envio. Os avisos aparecem no título da zona
		// (o input nativo está escondido, não pode mostrar o seu próprio aviso).
		var TXT = <?php echo wp_json_encode( [
			'titulo'     => __( 'Arraste os documentos para aqui', 'contabilidade-pro' ),
			'um'         => __( '1 ficheiro pronto a enviar', 'contabilidade-pro' ),
			'varios'     => __( '%d ficheiros prontos a enviar', 'contabilidade-pro' ),
			'demasiados' => cp_ac_documento_erro_texto( 'documento_demasiados' ),
			'nenhum'     => __( 'Escolha ou arraste pelo menos um documento.', 'contabilidade-pro' ),
			'fora_um'    => __( '1 ficheiro ficou de fora — só PDF ou imagem (JPG, PNG).', 'contabilidade-pro' ),
			'fora'       => __( '%d ficheiros ficaram de fora — só PDF ou imagem (JPG, PNG).', 'contabilidade-pro' ),
		] ); ?>;
		var ACEITES = /\.(pdf|jpe?g|png)$/i;
		function ligarZona(form) {
			var zona = form.querySelector('[data-cp-doc-drop]');
			if (!zona) return;
			var campo = zona.querySelector('[data-cp-doc-max]');
			var lista = zona.querySelector('[data-cp-doc-escolhidos]');
			var titulo = zona.querySelector('[data-cp-doc-drop-titulo]');
			var max = parseInt(campo.getAttribute('data-cp-doc-max'), 10) || 20;
			function avisar(texto, erro) {
				titulo.textContent = texto || TXT.titulo;
				titulo.classList.toggle('is-aviso', !!erro);
			}
			function tamanho(b) {
				return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
			}
			function mostrar() {
				lista.innerHTML = '';
				Array.prototype.forEach.call(campo.files, function (f) {
					var li = document.createElement('li');
					var nome = document.createElement('span');
					nome.textContent = f.name;
					var tam = document.createElement('span');
					tam.className = 'ac-dropzone-tam';
					tam.textContent = tamanho(f.size);
					li.appendChild(nome);
					li.appendChild(tam);
					lista.appendChild(li);
				});
				lista.hidden = !campo.files.length;
				var n = campo.files.length;
				if (n > max) avisar(TXT.demasiados, true);
				else if (n) avisar(n === 1 ? TXT.um : TXT.varios.replace('%d', n));
				else avisar('');
			}
			// Largar soma aos já escolhidos, sem repetir; o que não é PDF/imagem fica de fora.
			function juntar(novos) {
				var dt = new DataTransfer(), vistos = {}, fora = 0;
				Array.prototype.forEach.call(campo.files, function (f) { dt.items.add(f); vistos[f.name + f.size] = true; });
				Array.prototype.forEach.call(novos, function (f) {
					if (!ACEITES.test(f.name)) { fora++; return; }
					if (!vistos[f.name + f.size]) { dt.items.add(f); vistos[f.name + f.size] = true; }
				});
				campo.files = dt.files;
				mostrar();
				if (fora) avisar(fora === 1 ? TXT.fora_um : TXT.fora.replace('%d', fora), true);
			}
			campo.addEventListener('change', mostrar);
			['dragenter', 'dragover'].forEach(function (ev) {
				zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('is-arrastar'); });
			});
			zona.addEventListener('dragleave', function (e) { if (!zona.contains(e.relatedTarget)) zona.classList.remove('is-arrastar'); });
			zona.addEventListener('drop', function (e) {
				e.preventDefault();
				zona.classList.remove('is-arrastar');
				if (e.dataTransfer && e.dataTransfer.files.length) juntar(e.dataTransfer.files);
			});
			form.addEventListener('submit', function (e) {
				if (!campo.files.length) { e.preventDefault(); avisar(TXT.nenhum, true); }
				else if (campo.files.length > max) { e.preventDefault(); avisar(TXT.demasiados, true); }
			});
		}

		document.querySelectorAll('form').forEach(ligar);
		// Largar um ficheiro fora da zona não o abre no navegador.
		window.addEventListener('dragover', function (e) { e.preventDefault(); });
		window.addEventListener('drop', function (e) { if (!e.target.closest || !e.target.closest('[data-cp-doc-drop]')) e.preventDefault(); });
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

// Mês "AAAA-MM" do documento, para as tabs; '' nos enviados antes de haver
// pastas.
function cp_ac_documento_mes( $anexo_id ) {
	return cp_ac_calc_mes_valido( sprintf( '%04d-%02d', (int) get_post_meta( $anexo_id, '_cp_doc_ano', true ), (int) get_post_meta( $anexo_id, '_cp_doc_mes', true ) ) );
}

// Endereço das tabs por mês: leva o destino do último envio, para o
// formulário não mudar ao trocar de mês.
function cp_ac_documentos_tabs_url( $empresa_id ) {
	$url     = cp_ac_vista_url( 'documentos' );
	$destino = cp_ac_documento_destino_anterior( $empresa_id, $_GET );
	if ( ! $destino ) return $url;
	return add_query_arg( [
		'cp_doc_pasta' => rawurlencode( $destino['pasta_id'] ),
		'cp_doc_ano'   => $destino['ano'],
		'cp_doc_mes'   => $destino['mes'],
	], $url );
}

// Grupos da lista de documentos (só os de $documentos, os do mês aberto): um
// por pasta, pela ordem das pastas que a Work enviou, e no fim "Outros
// documentos" (enviados antes de haver pastas, ou de uma pasta que
// entretanto deixou de existir). Dentro de cada grupo, do ano/mês mais
// recente para o mais antigo.
function cp_ac_documentos_por_pasta( $empresa_id, array $documentos ) {
	$grupos = [];
	foreach ( cp_ac_documento_pastas( $empresa_id ) as $pasta ) {
		$grupos[ (string) $pasta['id'] ] = [ 'nome' => $pasta['nome'], 'docs' => [] ];
	}
	$outros = [ 'nome' => __( 'Outros documentos', 'contabilidade-pro' ), 'docs' => [] ];
	foreach ( $documentos as $doc ) {
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
	$itens     = array_map( function ( $doc ) {
		return [ 'mes' => cp_ac_documento_mes( $doc->ID ), 'doc' => $doc ];
	}, cp_ac_documentos( $empresa_id ) );
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

		<?php if ( empty( $itens ) ) : ?>
			<div style="margin-top:16px;color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não enviou nenhum documento.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<div class="ac-doc-tabs" id="lista-documentos"><?php $itens = cp_ac_render_tabs_meses( $itens, cp_ac_documentos_tabs_url( $empresa_id ), 'ac-badge-blue', null, '#lista-documentos' ); ?></div>
			<?php $grupos = cp_ac_documentos_por_pasta( $empresa_id, array_column( $itens, 'doc' ) ); ?>
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
						$removido = cp_ac_documento_ficheiro_removido( $doc->ID );
						$url     = $removido ? '' : wp_get_attachment_url( $doc->ID );
						$e_pdf   = 'application/pdf' === get_post_mime_type( $doc );
						$doc_mes = (int) get_post_meta( $doc->ID, '_cp_doc_mes', true );
						$doc_ano = (int) get_post_meta( $doc->ID, '_cp_doc_ano', true );
						?>
						<div class="ac-doc-linha">
							<div class="ac-doc-nome">
								<?php if ( $removido ) : ?>
									<span class="ac-doc-icone" title="<?php esc_attr_e( 'O ficheiro foi retirado do site depois de tratado. Peça-nos uma cópia se precisar.', 'contabilidade-pro' ); ?>" style="opacity:.45"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path></svg></span>
								<?php else : ?>
								<button type="button" class="ac-doc-icone ac-doc-ver" data-cp-fab-target="cp-doc-viewer"
									data-cp-doc-url="<?php echo esc_url( $url ); ?>" data-cp-doc-tipo="<?php echo $e_pdf ? 'pdf' : 'imagem'; ?>"
									data-cp-doc-nome="<?php echo esc_attr( $nome ); ?>"
									title="<?php esc_attr_e( 'Ver documento', 'contabilidade-pro' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nome do ficheiro */ __( 'Ver %s', 'contabilidade-pro' ), $nome ) ); ?>">
									<?php if ( $e_pdf ) : ?>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M9 13h6M9 17h4"></path></svg>
									<?php else : ?>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="1.6"></circle><path d="m21 16-5-5-9 9"></path></svg>
									<?php endif; ?>
								</button>
								<?php endif; ?>
								<span class="ac-doc-nome-texto" title="<?php echo esc_attr( $nome ); ?>"><?php echo esc_html( $nome ); ?><?php if ( $removido ) : ?> <small style="color:var(--ac-muted)"><?php esc_html_e( '· ficheiro retirado', 'contabilidade-pro' ); ?></small><?php endif; ?></span>
							</div>
							<?php $periodo = ( $doc_ano && isset( $nomes_mes[ $doc_mes ] ) ) ? $nomes_mes[ $doc_mes ] . ' ' . $doc_ano : ''; ?>
							<div class="ac-doc-dado ac-doc-dado-data" data-periodo="<?php echo esc_attr( $periodo ? ' · ' . $periodo : '' ); ?>"><?php echo esc_html( get_post_time( 'd/m/Y', false, $doc ) ); ?></div>
							<div class="ac-doc-dado ac-doc-dado-periodo"><?php echo $periodo ? esc_html( $periodo ) : '—'; ?></div>
							<div class="ac-doc-acoes">
								<?php if ( cp_ac_documento_pode_apagar( $doc, $user_id ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Apagar este documento?', 'contabilidade-pro' ) ); ?>');">
										<?php wp_nonce_field( 'cp_documento_apagar', 'cp_documento_nonce' ); ?>
										<input type="hidden" name="action" value="cp_documento_apagar">
										<input type="hidden" name="anexo_id" value="<?php echo esc_attr( $doc->ID ); ?>">
										<button type="submit" class="ac-todo-btn ac-todo-btn-del" title="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>" aria-label="<?php esc_attr_e( 'Apagar', 'contabilidade-pro' ); ?>">
											<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path></svg>
										</button>
									</form>
								<?php elseif ( cp_ac_documento_apagar_pedido( $doc->ID ) ) : ?>
									<span class="ac-doc-estado-ico" title="<?php esc_attr_e( 'A apagar — fica feito dentro de minutos, se ainda não tiver sido lançado na contabilidade.', 'contabilidade-pro' ); ?>" aria-label="<?php esc_attr_e( 'A apagar', 'contabilidade-pro' ); ?>">
										<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
									</span>
								<?php elseif ( '' !== ( $recusa = cp_ac_documento_apagar_recusado( $doc->ID ) ) ) : ?>
									<span class="ac-doc-estado-ico" title="<?php echo esc_attr( $recusa ); ?>" aria-label="<?php echo esc_attr( $recusa ); ?>">
										<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
									</span>
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
