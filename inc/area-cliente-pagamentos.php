<?php
/**
 * Área de Cliente — Pagamentos ao Estado (WORK-022, fase 2).
 *
 * A Work lê o Mapa de pagamentos da CentralGest (AT e SSD), a equipa revê e
 * publica um mês por empresa. Aqui só se guarda e mostra; quem decide é a
 * Work. O email e o push saem DAQUI (decisão de 2026-11-17: um só canal, a
 * Área de Cliente; sem conta no site, o cliente não recebe nada).
 *
 * Fluxo (sempre por esta ordem, chamado pela Work):
 *   1. POST /cp/v1/pagamentos            dados do mês (substitui o mês;
 *                                        mantém o PDF das guias que ficam)
 *   2. POST /cp/v1/pagamentos/documento  PDF de cada guia (multipart)
 *   3. POST /cp/v1/pagamentos/notificar  email + push aos utilizadores da
 *                                        empresa (só com a ferramenta ligada)
 *   GET  /cp/v1/pagamentos/estado        publicado/avisado/visto por mês e
 *                                        as guias que o cliente indicou como
 *                                        pagas, para a Work mostrar à equipa
 *
 * O cliente vê a lista (sem estado de pagamento: isso fica do nosso lado),
 * abre cada guia num visor na própria página e pode indicar "Já paguei"
 * (admin-post cp_pagamento_indicar; avisa a equipa). Guias indicadas como
 * pagas saem do email de aviso.
 *
 * Postmeta `_cp_pagamentos` no post da empresa:
 *   [ 'meses' => [ 'AAAA-MM' => [ publicado_em, notificado_em, n_avisados,
 *     visto_em, guias => [ { id, imposto, nome, entidade, referencia, data_limite,
 *     montante, pago, anexo_id, anexo_url, cliente_pago_em,
 *     cliente_pago_por } ] ] ] ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Funções puras (testadas em tests/Unit/PagamentosTest.php) ──────────────

function cp_ac_pag_chave_mes( $ano, $mes ) {
	return sprintf( '%04d-%02d', (int) $ano, (int) $mes );
}

function cp_ac_pag_mes_valido( $ano, $mes ) {
	$ano = (int) $ano;
	$mes = (int) $mes;
	return $ano >= 2000 && $ano <= 2100 && $mes >= 1 && $mes <= 12;
}

/** Limpa a lista de guias vinda da Work (ignora as sem id). */
function cp_ac_pag_normalizar_guias( $guias_in ) {
	$guias = [];
	if ( ! is_array( $guias_in ) ) return $guias;
	foreach ( $guias_in as $g ) {
		if ( ! is_array( $g ) || empty( $g['id'] ) ) continue;
		$data = isset( $g['data_limite'] ) ? sanitize_text_field( $g['data_limite'] ) : '';
		$guias[] = [
			'id'          => sanitize_text_field( $g['id'] ),
			'imposto'     => isset( $g['imposto'] ) ? sanitize_text_field( $g['imposto'] ) : '',
			'nome'        => isset( $g['nome'] ) ? sanitize_text_field( $g['nome'] ) : '',
			'entidade'    => isset( $g['entidade'] ) ? sanitize_text_field( $g['entidade'] ) : '',
			'referencia'  => isset( $g['referencia'] ) ? sanitize_text_field( $g['referencia'] ) : '',
			'data_limite' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ? $data : '',
			'montante'    => isset( $g['montante'] ) ? (float) $g['montante'] : 0.0,
			'pago'        => ! empty( $g['pago'] ),
			'anexo_id'    => 0,
			'anexo_url'   => '',
			'cliente_pago_em'  => '',
			'cliente_pago_por' => 0,
		];
	}
	return $guias;
}

/** 'pago', 'atraso' (prazo já passou) ou 'por_pagar'. $hoje = 'AAAA-MM-DD'. */
function cp_ac_pag_estado_guia( $guia, $hoje ) {
	if ( ! empty( $guia['pago'] ) ) return 'pago';
	if ( ! empty( $guia['data_limite'] ) && $guia['data_limite'] < $hoje ) return 'atraso';
	return 'por_pagar';
}

function cp_ac_pag_euros( $valor ) {
	return number_format( (float) $valor, 2, ',', ' ' ) . ' €';
}

function cp_ac_pag_data( $iso ) {
	return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m ) ? "{$m[3]}/{$m[2]}/{$m[1]}" : '';
}

function cp_ac_pag_nome_mes( $mes ) {
	$nomes = [ 1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ];
	return isset( $nomes[ (int) $mes ] ) ? $nomes[ (int) $mes ] : (string) $mes;
}

/** Guias ainda por pagar (nem pagas segundo a CG, nem indicadas pelo cliente), por prazo. */
function cp_ac_pag_por_pagar( $guias ) {
	$lista = array_values( array_filter( (array) $guias, function ( $g ) {
		return empty( $g['pago'] ) && empty( $g['cliente_pago_em'] );
	} ) );
	usort( $lista, function ( $a, $b ) { return strcmp( (string) $a['data_limite'], (string) $b['data_limite'] ); } );
	return $lista;
}

/**
 * Assunto e corpo (texto simples) do email. Só as guias por pagar; null se
 * não houver nenhuma (nesse caso não se envia nada).
 */
function cp_ac_pag_texto_email( $nome_empresa, $ano, $mes, $guias, $url ) {
	$por_pagar = cp_ac_pag_por_pagar( $guias );
	if ( ! $por_pagar ) return null;

	$total  = array_sum( array_map( function ( $g ) { return (float) $g['montante']; }, $por_pagar ) );
	$titulo = sprintf( 'Pagamentos ao Estado de %s de %d', cp_ac_pag_nome_mes( $mes ), (int) $ano );
	$linhas = [];
	foreach ( $por_pagar as $g ) {
		$linha = sprintf( '- %s: %s', $g['nome'] ?: $g['imposto'], cp_ac_pag_euros( $g['montante'] ) );
		if ( $g['data_limite'] ) $linha .= ' até ' . cp_ac_pag_data( $g['data_limite'] );
		if ( ! empty( $g['entidade'] ) ) $linha .= "\n  Entidade: " . $g['entidade'];
		if ( $g['referencia'] ) $linha .= "\n  Referência: " . $g['referencia'];
		$linhas[] = $linha;
	}

	$corpo  = "Olá,\n\n";
	$corpo .= sprintf( "Estes são os pagamentos ao Estado de %s em %s de %d:\n\n", $nome_empresa, cp_ac_pag_nome_mes( $mes ), (int) $ano );
	$corpo .= implode( "\n\n", $linhas ) . "\n\n";
	$corpo .= 'Total: ' . cp_ac_pag_euros( $total ) . "\n\n";
	$corpo .= "As guias de pagamento (PDF) estão na Área de Cliente:\n" . $url . "\n\n";
	$corpo .= "Se já fez algum destes pagamentos, pode ignorar esta mensagem.\n\nEntre Números";

	return [ 'assunto' => $titulo . ' — ' . $nome_empresa, 'corpo' => $corpo ];
}

// ─── Leitura / escrita ──────────────────────────────────────────────────────

function cp_ac_pagamentos( $empresa_id ) {
	$dados = get_post_meta( $empresa_id, '_cp_pagamentos', true );
	if ( ! is_array( $dados ) ) $dados = [];
	if ( empty( $dados['meses'] ) || ! is_array( $dados['meses'] ) ) $dados['meses'] = [];
	return $dados;
}

function cp_ac_pagamentos_gravar( $empresa_id, $dados ) {
	krsort( $dados['meses'] );
	update_post_meta( $empresa_id, '_cp_pagamentos', $dados );
}

/** Clientes (não equipa) com acesso à empresa, a "Pagamentos ao Estado" nela, e email. */
function cp_ac_pag_destinatarios( $empresa_id ) {
	$destinatarios = [];
	foreach ( get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] ) as $u ) {
		if ( ! in_array( (int) $empresa_id, cp_ac_user_empresas( $u->ID ), true ) ) continue;
		if ( ! cp_ac_utilizador_tem_ferramenta( $u->ID, (int) $empresa_id, 'pagamentos' ) ) continue;
		if ( cp_backoffice_utilizador_tem_acesso( $u->ID ) ) continue;
		if ( ! is_email( $u->user_email ) ) continue;
		$destinatarios[] = $u;
	}
	return $destinatarios;
}

// ─── REST ───────────────────────────────────────────────────────────────────

function cp_ac_rest_routes_pagamentos() {
	$mes_args = [
		'work_id' => [ 'required' => true, 'type' => 'string' ],
		'ano'     => [ 'required' => true, 'type' => 'integer' ],
		'mes'     => [ 'required' => true, 'type' => 'integer' ],
	];
	register_rest_route( 'cp/v1', '/pagamentos', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_pagamentos_sincronizar',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => $mes_args + [ 'guias' => [ 'required' => false, 'type' => 'array' ] ],
		],
	] );
	register_rest_route( 'cp/v1', '/pagamentos/documento', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_pagamentos_documento',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => $mes_args + [ 'guia_id' => [ 'required' => true, 'type' => 'string' ] ],
		],
	] );
	register_rest_route( 'cp/v1', '/pagamentos/notificar', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_pagamentos_notificar',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => $mes_args,
		],
	] );
	register_rest_route( 'cp/v1', '/pagamentos/estado', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_pagamentos_estado',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [ 'work_id' => [ 'required' => true, 'type' => 'string' ] ],
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_pagamentos' );

function cp_ac_rest_pagamentos_mes_invalido() {
	return new WP_Error( 'cp_dados_invalidos', __( 'ano/mes inválidos.', 'contabilidade-pro' ), [ 'status' => 400 ] );
}

function cp_ac_rest_pagamentos_sincronizar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$ano = (int) $request->get_param( 'ano' );
	$mes = (int) $request->get_param( 'mes' );
	if ( ! cp_ac_pag_mes_valido( $ano, $mes ) ) return cp_ac_rest_pagamentos_mes_invalido();

	$dados    = cp_ac_pagamentos( $empresa_id );
	$chave    = cp_ac_pag_chave_mes( $ano, $mes );
	$anterior = isset( $dados['meses'][ $chave ] ) ? $dados['meses'][ $chave ] : [];
	$anexos   = [];
	$indicadas = [];
	foreach ( (array) ( $anterior['guias'] ?? [] ) as $g ) {
		if ( ! empty( $g['anexo_id'] ) ) $anexos[ $g['id'] ] = $g;
		if ( ! empty( $g['cliente_pago_em'] ) ) $indicadas[ $g['id'] ] = $g;
	}

	$guias = cp_ac_pag_normalizar_guias( $request->get_param( 'guias' ) );
	foreach ( $guias as &$g ) {
		if ( isset( $indicadas[ $g['id'] ] ) ) {
			$g['cliente_pago_em']  = $indicadas[ $g['id'] ]['cliente_pago_em'];
			$g['cliente_pago_por'] = $indicadas[ $g['id'] ]['cliente_pago_por'];
		}
		if ( isset( $anexos[ $g['id'] ] ) ) {
			$g['anexo_id']  = $anexos[ $g['id'] ]['anexo_id'];
			$g['anexo_url'] = $anexos[ $g['id'] ]['anexo_url'];
			unset( $anexos[ $g['id'] ] );
		}
	}
	unset( $g );
	foreach ( $anexos as $sobra ) { // guias que deixaram de existir: o PDF sai também
		wp_delete_attachment( $sobra['anexo_id'], true );
	}

	$dados['meses'][ $chave ] = [
		'publicado_em'  => current_time( 'mysql' ),
		'notificado_em' => $anterior['notificado_em'] ?? '',
		'n_avisados'    => $anterior['n_avisados'] ?? 0,
		'visto_em'      => $anterior['visto_em'] ?? '',
		'guias'         => $guias,
	];
	cp_ac_pagamentos_gravar( $empresa_id, $dados );

	return rest_ensure_response( [ 'ok' => true, 'guias' => count( $guias ) ] );
}

function cp_ac_rest_pagamentos_documento( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$ano = (int) $request->get_param( 'ano' );
	$mes = (int) $request->get_param( 'mes' );
	if ( ! cp_ac_pag_mes_valido( $ano, $mes ) ) return cp_ac_rest_pagamentos_mes_invalido();

	$dados   = cp_ac_pagamentos( $empresa_id );
	$chave   = cp_ac_pag_chave_mes( $ano, $mes );
	$guia_id = sanitize_text_field( $request->get_param( 'guia_id' ) );
	$indice  = null;
	foreach ( (array) ( $dados['meses'][ $chave ]['guias'] ?? [] ) as $i => $g ) {
		if ( $g['id'] === $guia_id ) { $indice = $i; break; }
	}
	if ( null === $indice ) {
		return new WP_Error( 'cp_guia_nao_encontrada', __( 'Não existe esta guia neste mês. Envia primeiro POST /pagamentos.', 'contabilidade-pro' ), [ 'status' => 404 ] );
	}

	$ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
	if ( is_wp_error( $ficheiro ) ) return $ficheiro;

	$guia = &$dados['meses'][ $chave ]['guias'][ $indice ];
	if ( ! empty( $guia['anexo_id'] ) ) wp_delete_attachment( $guia['anexo_id'], true );
	$guia['anexo_id']  = $ficheiro['id'];
	$guia['anexo_url'] = $ficheiro['url'];
	unset( $guia );
	cp_ac_pagamentos_gravar( $empresa_id, $dados );

	return rest_ensure_response( [ 'ok' => true, 'anexo_id' => $ficheiro['id'] ] );
}

function cp_ac_rest_pagamentos_notificar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$ano = (int) $request->get_param( 'ano' );
	$mes = (int) $request->get_param( 'mes' );
	if ( ! cp_ac_pag_mes_valido( $ano, $mes ) ) return cp_ac_rest_pagamentos_mes_invalido();

	if ( ! cp_ac_empresa_tem_ferramenta( $empresa_id, 'pagamentos' ) ) {
		return new WP_Error( 'cp_ferramenta_desligada', __( 'A ferramenta "Pagamentos ao Estado" não está ligada nesta empresa.', 'contabilidade-pro' ), [ 'status' => 409 ] );
	}
	$dados = cp_ac_pagamentos( $empresa_id );
	$chave = cp_ac_pag_chave_mes( $ano, $mes );
	if ( empty( $dados['meses'][ $chave ] ) ) {
		return new WP_Error( 'cp_mes_nao_publicado', __( 'Este mês ainda não foi publicado.', 'contabilidade-pro' ), [ 'status' => 404 ] );
	}

	$url   = add_query_arg( 'vista', 'pagamentos', home_url( '/area-de-cliente/' ) );
	$texto = cp_ac_pag_texto_email( get_the_title( $empresa_id ), $ano, $mes, $dados['meses'][ $chave ]['guias'], $url );
	if ( null === $texto ) {
		return rest_ensure_response( [ 'ok' => true, 'enviados' => 0, 'motivo' => 'sem_pagamentos' ] );
	}

	$enviados = 0;
	foreach ( cp_ac_pag_destinatarios( $empresa_id ) as $u ) {
		if ( wp_mail( $u->user_email, $texto['assunto'], $texto['corpo'], [ 'Content-Type: text/plain; charset=UTF-8' ] ) ) {
			$enviados++;
		}
	}

	$por_pagar = cp_ac_pag_por_pagar( $dados['meses'][ $chave ]['guias'] );
	cp_push_notificar_empresa(
		$empresa_id,
		__( 'Pagamentos ao Estado', 'contabilidade-pro' ),
		sprintf(
			/* translators: 1: nº de pagamentos, 2: mês */
			__( '%1$d pagamento(s) de %2$s para fazer', 'contabilidade-pro' ),
			count( $por_pagar ),
			cp_ac_pag_nome_mes( $mes )
		),
		$url,
		'pagamentos'
	);

	$dados['meses'][ $chave ]['notificado_em'] = current_time( 'mysql' );
	$dados['meses'][ $chave ]['n_avisados']    = $enviados;
	cp_ac_pagamentos_gravar( $empresa_id, $dados );

	return rest_ensure_response( [ 'ok' => true, 'enviados' => $enviados ] );
}

function cp_ac_rest_pagamentos_estado( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$meses = [];
	foreach ( cp_ac_pagamentos( $empresa_id )['meses'] as $chave => $m ) {
		$meses[ $chave ] = [
			'publicado_em'  => $m['publicado_em'] ?? '',
			'notificado_em' => $m['notificado_em'] ?? '',
			'n_avisados'    => (int) ( $m['n_avisados'] ?? 0 ),
			'visto_em'      => $m['visto_em'] ?? '',
			'cliente_pagou' => (object) array_column(
				array_filter( (array) ( $m['guias'] ?? [] ), function ( $g ) { return ! empty( $g['cliente_pago_em'] ); } ),
				'cliente_pago_em', 'id'
			),
		];
	}
	return rest_ensure_response( [ 'meses' => $meses ] );
}

// ─── "Já paguei" (cliente) ──────────────────────────────────────────────────
// Formulário clássico (admin-post + reload), como os Lembretes. A empresa é
// resolvida como em cp_ac_tarefa_resolver_empresa(): o cliente só mexe na
// sua empresa ativa, nunca na que vem no pedido.

/** Marca/desmarca. Devolve a guia (para o aviso) ou null se não existir. */
function cp_ac_pag_indicar_pago( $empresa_id, $chave, $guia_id, $pago, $user_id ) {
	$dados = cp_ac_pagamentos( $empresa_id );
	if ( empty( $dados['meses'][ $chave ]['guias'] ) ) return null;
	foreach ( $dados['meses'][ $chave ]['guias'] as &$g ) {
		if ( $g['id'] !== $guia_id ) continue;
		$g['cliente_pago_em']  = $pago ? current_time( 'mysql' ) : '';
		$g['cliente_pago_por'] = $pago ? (int) $user_id : 0;
		$guia = $g;
		unset( $g );
		cp_ac_pagamentos_gravar( $empresa_id, $dados );
		return $guia;
	}
	unset( $g );
	return null;
}

function cp_ac_handle_pagamento_indicar() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_pagamento_nonce'] ) || ! wp_verify_nonce( $_POST['cp_pagamento_nonce'], 'cp_pagamento_indicar' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	$chave      = isset( $_POST['mes'] ) ? sanitize_text_field( wp_unslash( $_POST['mes'] ) ) : '';
	$guia_id    = isset( $_POST['guia_id'] ) ? sanitize_text_field( wp_unslash( $_POST['guia_id'] ) ) : '';
	$pago       = ! empty( $_POST['pago'] );
	$voltar     = wp_get_referer() ? wp_get_referer() : add_query_arg( 'vista', 'pagamentos', home_url( '/area-de-cliente/' ) );

	$guia = ( $empresa_id && cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, 'pagamentos' ) && preg_match( '/^\d{4}-\d{2}$/', $chave ) )
		? cp_ac_pag_indicar_pago( $empresa_id, $chave, $guia_id, $pago, $user_id )
		: null;
	if ( ! $guia ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'pagamento_invalido', $voltar ) );
		exit;
	}

	if ( $pago && ! cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		cp_push_notificar_equipa(
			__( 'Pagamento indicado pelo cliente', 'contabilidade-pro' ),
			sprintf(
				/* translators: 1: empresa, 2: pagamento, 3: valor */
				__( '%1$s: %2$s, %3$s', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				$guia['nome'] ?: $guia['imposto'],
				cp_ac_pag_euros( $guia['montante'] )
			),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id,
			'pagamentos'
		);
	}

	wp_safe_redirect( add_query_arg( 'guia', rawurlencode( $chave . '|' . $guia_id ), remove_query_arg( [ 'cp_erro', 'guia' ], $voltar ) ) );
	exit;
}
add_action( 'admin_post_cp_pagamento_indicar', 'cp_ac_handle_pagamento_indicar' );

// ─── Vista ──────────────────────────────────────────────────────────────────
// Lista à esquerda, visor à direita (por baixo no telemóvel). Tocar numa
// linha carrega a guia no visor; no Android (sem PDF embutido) abre noutro
// separador, como o visor dos Documentos. Sem coluna de estado: se está pago
// ou não fica do nosso lado; o cliente só indica "Já paguei".

function cp_ac_vista_pagamentos() {
	$empresa_id = cp_ac_empresa_ativa();
	$dados      = cp_ac_pagamentos( $empresa_id );

	// O primeiro cliente (não equipa) a abrir marca os meses como vistos.
	if ( $dados['meses'] && ! cp_backoffice_utilizador_tem_acesso( get_current_user_id() ) ) {
		$mudou = false;
		foreach ( $dados['meses'] as &$m ) {
			if ( empty( $m['visto_em'] ) ) { $m['visto_em'] = current_time( 'mysql' ); $mudou = true; }
		}
		unset( $m );
		if ( $mudou ) cp_ac_pagamentos_gravar( $empresa_id, $dados );
	}

	// Guia a mostrar logo: a pedida (?guia=AAAA-MM|id, ex. depois de "Já
	// paguei") ou a primeira com PDF do mês mais recente.
	$pedida  = isset( $_GET['guia'] ) ? sanitize_text_field( wp_unslash( $_GET['guia'] ) ) : '';
	$inicial = null;
	foreach ( $dados['meses'] as $chave => $m ) {
		foreach ( (array) ( $m['guias'] ?? [] ) as $g ) {
			if ( empty( $g['anexo_url'] ) ) continue;
			if ( $pedida === $chave . '|' . $g['id'] ) { $inicial = [ $chave, $g ]; break 2; }
			if ( ! $inicial ) $inicial = [ $chave, $g ];
		}
	}
	$titulo_guia = function ( $chave, $g ) {
		list( $ano, $mes ) = array_map( 'intval', explode( '-', $chave ) );
		return ( $g['nome'] ?: $g['imposto'] ) . ' — ' . cp_ac_pag_nome_mes( $mes ) . ' ' . $ano;
	};
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Pagamentos ao Estado', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Impostos e Segurança Social a pagar em cada mês. Toque num pagamento para ver a guia.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( isset( $_GET['cp_erro'] ) && 'pagamento_invalido' === $_GET['cp_erro'] ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-red-fg)"><?php esc_html_e( 'Não foi possível registar. Atualize a página e tente outra vez.', 'contabilidade-pro' ); ?></div>
	<?php endif; ?>

	<?php if ( empty( $dados['meses'] ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem pagamentos publicados', 'contabilidade-pro' ), __( 'Quando houver pagamentos ao Estado a fazer, aparecem aqui com a referência e a guia.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<div class="ac-pag-layout">
		<div class="ac-pag-lista">
		<?php foreach ( $dados['meses'] as $chave => $m ) :
			list( $ano, $mes ) = array_map( 'intval', explode( '-', $chave ) );
			$guias = (array) ( $m['guias'] ?? [] );
			$total = array_sum( array_map( function ( $g ) { return (float) $g['montante']; }, $guias ) );
			?>
			<div class="ac-card" style="padding:22px">
				<div class="ac-sec-head">
					<h3 style="font-size:16px"><?php echo esc_html( ucfirst( cp_ac_pag_nome_mes( $mes ) ) . ' ' . $ano ); ?></h3>
					<?php if ( $guias ) : ?>
						<span class="ac-badge ac-badge-blue"><?php echo esc_html( sprintf( __( 'Total: %s', 'contabilidade-pro' ), cp_ac_pag_euros( $total ) ) ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! $guias ) : ?>
					<div style="color:var(--ac-muted);font-size:14px"><?php esc_html_e( 'Não há pagamentos ao Estado neste mês.', 'contabilidade-pro' ); ?></div>
				<?php else : ?>
				<div class="ac-table-wrap">
					<table class="ac-table ac-pag-tabela">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Pagamento', 'contabilidade-pro' ); ?></th>
								<th><?php esc_html_e( 'Entidade / Referência', 'contabilidade-pro' ); ?></th>
								<th><?php esc_html_e( 'Prazo', 'contabilidade-pro' ); ?></th>
								<th style="text-align:right"><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $guias as $g ) :
								$tem_pdf = ! empty( $g['anexo_url'] );
								$ativa   = $inicial && $inicial[0] === $chave && $inicial[1]['id'] === $g['id'];
								?>
								<tr class="ac-pag-linha<?php echo $tem_pdf ? ' ac-pag-com-pdf' : ''; ?><?php echo $ativa ? ' ativa' : ''; ?>"
									<?php if ( $tem_pdf ) : ?>tabindex="0" data-pdf="<?php echo esc_url( $g['anexo_url'] ); ?>" data-titulo="<?php echo esc_attr( $titulo_guia( $chave, $g ) ); ?>"<?php endif; ?>>
									<td><?php echo esc_html( $g['nome'] ?: $g['imposto'] ); ?></td>
									<td style="font-variant-numeric:tabular-nums">
										<?php if ( ! empty( $g['entidade'] ) ) : ?><div><?php echo esc_html( sprintf( __( 'Entidade %s', 'contabilidade-pro' ), $g['entidade'] ) ); ?></div><?php endif; ?>
										<div><?php echo esc_html( $g['referencia'] ); ?></div>
									</td>
									<td><?php echo esc_html( cp_ac_pag_data( $g['data_limite'] ) ); ?></td>
									<td style="text-align:right;font-variant-numeric:tabular-nums"><?php echo esc_html( cp_ac_pag_euros( $g['montante'] ) ); ?></td>
									<td class="ac-pag-acao">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'cp_pagamento_indicar', 'cp_pagamento_nonce' ); ?>
											<input type="hidden" name="action" value="cp_pagamento_indicar">
											<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
											<input type="hidden" name="mes" value="<?php echo esc_attr( $chave ); ?>">
											<input type="hidden" name="guia_id" value="<?php echo esc_attr( $g['id'] ); ?>">
											<?php if ( empty( $g['cliente_pago_em'] ) ) : ?>
												<input type="hidden" name="pago" value="1">
												<button type="submit" class="ac-btn ac-btn-ghost ac-pag-btn"><?php esc_html_e( 'Já paguei', 'contabilidade-pro' ); ?></button>
											<?php else : ?>
												<span class="ac-pag-indicado"><?php echo esc_html( sprintf( __( 'Pago em %s', 'contabilidade-pro' ), mysql2date( 'd/m', $g['cliente_pago_em'] ) ) ); ?></span>
												<button type="submit" class="ac-pag-desfazer" title="<?php esc_attr_e( 'Desfazer', 'contabilidade-pro' ); ?>"><?php esc_html_e( 'Desfazer', 'contabilidade-pro' ); ?></button>
											<?php endif; ?>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		</div>

		<div class="ac-card ac-pag-visor" id="cp-pag-visor">
			<div class="ac-pag-visor-cab">
				<h3 class="ac-pag-visor-titulo" style="font-size:15px"><?php echo $inicial ? esc_html( $titulo_guia( $inicial[0], $inicial[1] ) ) : esc_html__( 'Guia de pagamento', 'contabilidade-pro' ); ?></h3>
				<a class="ac-btn ac-btn-ghost ac-pag-visor-abrir" href="<?php echo $inicial ? esc_url( $inicial[1]['anexo_url'] ) : '#'; ?>" target="_blank" rel="noopener"<?php echo $inicial ? '' : ' hidden'; ?>><?php esc_html_e( 'Abrir noutro separador', 'contabilidade-pro' ); ?></a>
			</div>
			<div class="ac-pag-visor-corpo">
				<?php if ( $inicial ) : ?>
					<iframe src="<?php echo esc_url( $inicial[1]['anexo_url'] ); ?>" title="<?php echo esc_attr( $titulo_guia( $inicial[0], $inicial[1] ) ); ?>"></iframe>
				<?php else : ?>
					<div style="color:var(--ac-muted);font-size:14px;padding:24px;text-align:center"><?php esc_html_e( 'As guias ainda não estão disponíveis.', 'contabilidade-pro' ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var visor = document.getElementById('cp-pag-visor');
		if (!visor) return;
		var corpo = visor.querySelector('.ac-pag-visor-corpo'), titulo = visor.querySelector('.ac-pag-visor-titulo'), abrir = visor.querySelector('.ac-pag-visor-abrir');
		var android = /Android/i.test(navigator.userAgent);
		function mostrar(linha) {
			var url = linha.getAttribute('data-pdf');
			if (android) { window.open(url, '_blank', 'noopener'); return; }
			document.querySelectorAll('.ac-pag-linha.ativa').forEach(function (l) { l.classList.remove('ativa'); });
			linha.classList.add('ativa');
			titulo.textContent = linha.getAttribute('data-titulo');
			abrir.href = url; abrir.hidden = false;
			corpo.innerHTML = '';
			var el = document.createElement('iframe');
			el.title = linha.getAttribute('data-titulo');
			el.src = url;
			corpo.appendChild(el);
			if (window.matchMedia('(max-width: 980px)').matches) visor.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		document.querySelectorAll('.ac-pag-com-pdf').forEach(function (linha) {
			linha.addEventListener('click', function (e) { if (!e.target.closest('form')) mostrar(linha); });
			linha.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); mostrar(linha); } });
		});
	})();
	</script>
	<?php
}
