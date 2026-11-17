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
 *   GET  /cp/v1/pagamentos/estado        publicado/avisado/visto por mês,
 *                                        para a Work mostrar à equipa
 *
 * Postmeta `_cp_pagamentos` no post da empresa:
 *   [ 'meses' => [ 'AAAA-MM' => [ publicado_em, notificado_em, n_avisados,
 *     visto_em, guias => [ { id, imposto, nome, referencia, data_limite,
 *     montante, pago, anexo_id, anexo_url } ] ] ] ]
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
			'referencia'  => isset( $g['referencia'] ) ? sanitize_text_field( $g['referencia'] ) : '',
			'data_limite' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ? $data : '',
			'montante'    => isset( $g['montante'] ) ? (float) $g['montante'] : 0.0,
			'pago'        => ! empty( $g['pago'] ),
			'anexo_id'    => 0,
			'anexo_url'   => '',
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

/** Guias ainda por pagar, por prazo. */
function cp_ac_pag_por_pagar( $guias ) {
	$lista = array_values( array_filter( (array) $guias, function ( $g ) { return empty( $g['pago'] ); } ) );
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

/** Clientes (não equipa) com acesso à empresa e email. */
function cp_ac_pag_destinatarios( $empresa_id ) {
	$destinatarios = [];
	foreach ( get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] ) as $u ) {
		if ( ! in_array( (int) $empresa_id, cp_ac_user_empresas( $u->ID ), true ) ) continue;
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
	foreach ( (array) ( $anterior['guias'] ?? [] ) as $g ) {
		if ( ! empty( $g['anexo_id'] ) ) $anexos[ $g['id'] ] = $g;
	}

	$guias = cp_ac_pag_normalizar_guias( $request->get_param( 'guias' ) );
	foreach ( $guias as &$g ) {
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
		$url
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
		];
	}
	return rest_ensure_response( [ 'meses' => $meses ] );
}

// ─── Vista ──────────────────────────────────────────────────────────────────

function cp_ac_vista_pagamentos() {
	$empresa_id = cp_ac_empresa_ativa();
	$dados      = cp_ac_pagamentos( $empresa_id );
	$hoje       = current_time( 'Y-m-d' );

	// O primeiro cliente (não equipa) a abrir marca os meses como vistos.
	if ( $dados['meses'] && ! cp_backoffice_utilizador_tem_acesso( get_current_user_id() ) ) {
		$mudou = false;
		foreach ( $dados['meses'] as &$m ) {
			if ( empty( $m['visto_em'] ) ) { $m['visto_em'] = current_time( 'mysql' ); $mudou = true; }
		}
		unset( $m );
		if ( $mudou ) cp_ac_pagamentos_gravar( $empresa_id, $dados );
	}

	$rotulos = [
		'pago'      => [ __( 'Pago', 'contabilidade-pro' ), 'ac-badge-green' ],
		'atraso'    => [ __( 'Prazo ultrapassado', 'contabilidade-pro' ), 'ac-badge-red' ],
		'por_pagar' => [ __( 'Por pagar', 'contabilidade-pro' ), 'ac-badge-amber' ],
	];
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Pagamentos ao Estado', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Impostos e Segurança Social a pagar em cada mês, com a referência e a guia de pagamento.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( empty( $dados['meses'] ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem pagamentos publicados', 'contabilidade-pro' ), __( 'Quando houver pagamentos ao Estado a fazer, aparecem aqui com a referência e a guia.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<?php foreach ( $dados['meses'] as $chave => $m ) :
		list( $ano, $mes ) = array_map( 'intval', explode( '-', $chave ) );
		$guias     = (array) ( $m['guias'] ?? [] );
		$por_pagar = cp_ac_pag_por_pagar( $guias );
		$total     = array_sum( array_map( function ( $g ) { return (float) $g['montante']; }, $por_pagar ) );
		?>
		<div class="ac-card" style="padding:22px">
			<div class="ac-sec-head">
				<div>
					<h3 style="font-size:16px"><?php echo esc_html( ucfirst( cp_ac_pag_nome_mes( $mes ) ) . ' ' . $ano ); ?></h3>
				</div>
				<?php if ( $por_pagar ) : ?>
					<span class="ac-badge ac-badge-amber"><?php echo esc_html( sprintf( __( 'Por pagar: %s', 'contabilidade-pro' ), cp_ac_pag_euros( $total ) ) ); ?></span>
				<?php elseif ( $guias ) : ?>
					<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Tudo pago', 'contabilidade-pro' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( ! $guias ) : ?>
				<div style="color:var(--ac-muted);font-size:14px"><?php esc_html_e( 'Não há pagamentos ao Estado neste mês.', 'contabilidade-pro' ); ?></div>
			<?php else : ?>
			<div class="ac-table-wrap">
				<table class="ac-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Pagamento', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Referência', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Prazo', 'contabilidade-pro' ); ?></th>
							<th style="text-align:right"><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $guias as $g ) : $estado = cp_ac_pag_estado_guia( $g, $hoje ); ?>
							<tr>
								<td><?php echo esc_html( $g['nome'] ?: $g['imposto'] ); ?></td>
								<td style="font-variant-numeric:tabular-nums"><?php echo esc_html( $g['referencia'] ); ?></td>
								<td><?php echo esc_html( cp_ac_pag_data( $g['data_limite'] ) ); ?></td>
								<td style="text-align:right;font-variant-numeric:tabular-nums"><?php echo esc_html( cp_ac_pag_euros( $g['montante'] ) ); ?></td>
								<td><span class="ac-badge <?php echo esc_attr( $rotulos[ $estado ][1] ); ?>"><?php echo esc_html( $rotulos[ $estado ][0] ); ?></span></td>
								<td>
									<?php if ( ! empty( $g['anexo_url'] ) ) : ?>
										<a class="ac-icon-btn-sm" href="<?php echo esc_url( $g['anexo_url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Descarregar a guia', 'contabilidade-pro' ); ?>">
											<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10M8 10.5 12 14l4-3.5"></path><path d="M5 17v1.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V17"></path></svg>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<?php
}
