<?php
/**
 * Área de Cliente — Visão financeira (WORK-045).
 *
 * O Work calcula tudo e envia um payload por empresa (substituído por
 * inteiro a cada sincronização). O site não faz contas de contabilidade:
 * só guarda o JSON em post meta e desenha. Tudo o que muda com o mês é
 * desenhado em JS (assets/js/ac-visao-financeira.js) a partir do JSON
 * completo passado inline na página — trocar de mês nunca recarrega nem
 * pede dados ao servidor.
 *
 * Contrato: D:\EN_Work-APP\docs\visao-financeira\BRIEF-WP.md (ticket WORK-045).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Leitura ────────────────────────────────────────────────────────────────

function cp_ac_visao_financeira( $empresa_id ) {
	$dados = get_post_meta( $empresa_id, '_cp_visao_financeira', true );
	if ( ! is_array( $dados ) ) $dados = [];
	$dados += [ 'work_id' => '', 'atualizado_em' => '', 'meses' => [], 'series' => [ 'vendas' => [], 'dinheiro' => [] ] ];
	if ( ! is_array( $dados['meses'] ) ) $dados['meses'] = [];
	return $dados;
}

// ─── REST: POST /cp/v1/visao-financeira ────────────────────────────────────
// Substitui por inteiro o que existir para a empresa (como /conta-corrente e
// /pendentes). `meses: []` limpa tudo.

function cp_ac_rest_routes_visao_financeira() {
	register_rest_route( 'cp/v1', '/visao-financeira', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_sincronizar_visao_financeira',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id' => [ 'required' => true, 'type' => 'string' ],
			],
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_visao_financeira' );

function cp_ac_rest_sincronizar_visao_financeira( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) ) {
		return new WP_Error(
			'cp_payload_invalido',
			__( 'Corpo do pedido tem de ser JSON.', 'contabilidade-pro' ),
			[ 'status' => 400 ]
		);
	}

	$meses_in = isset( $payload['meses'] ) && is_array( $payload['meses'] ) ? $payload['meses'] : [];
	foreach ( $meses_in as $mes ) {
		if ( empty( $mes['mes'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $mes['mes'] ) ) {
			return new WP_Error(
				'cp_mes_invalido',
				__( 'Cada mês tem de ter o campo "mes" no formato AAAA-MM.', 'contabilidade-pro' ),
				[ 'status' => 400 ]
			);
		}
	}

	// Meses novos (para a notificação push) — comparados antes de substituir.
	$anterior       = cp_ac_visao_financeira( $empresa_id );
	$meses_antes    = wp_list_pluck( $anterior['meses'], 'mes' );
	$meses_novos    = array_diff( wp_list_pluck( $meses_in, 'mes' ), $meses_antes );

	$payload += [ 'work_id' => $request->get_param( 'work_id' ), 'atualizado_em' => current_time( 'mysql' ), 'series' => [ 'vendas' => [], 'dinheiro' => [] ] ];
	$payload['meses'] = $meses_in;

	update_post_meta( $empresa_id, '_cp_visao_financeira', $payload );

	if ( $meses_novos && cp_ac_empresa_tem_ferramenta( $empresa_id, 'visao_financeira' ) ) {
		$ultimo_novo = end( $meses_novos );
		cp_push_notificar_empresa(
			$empresa_id,
			__( 'Novas contas disponíveis', 'contabilidade-pro' ),
			sprintf( /* translators: %s: mês no formato AAAA-MM */ __( 'Contas de %s disponíveis', 'contabilidade-pro' ), $ultimo_novo ),
			add_query_arg( 'vista', 'visao_financeira', home_url( '/area-de-cliente/' ) )
		);
	}

	return rest_ensure_response( [ 'ok' => true, 'meses' => count( $meses_in ) ] );
}

// ─── Vista ──────────────────────────────────────────────────────────────────
// Esqueleto estático (textos/labels fixos, vindos da maqueta). Tudo o que
// depende do mês escolhido (números, cascata, gráficos) tem id/data-* e é
// preenchido por JS a partir de window.cpVisaoFinanceira (ver
// cp_enqueue_assets() em functions.php).

function cp_ac_vista_visao_financeira() {
	$empresa_id = cp_ac_empresa_ativa();
	$dados      = cp_ac_visao_financeira( $empresa_id );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Como está a sua empresa', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Números da contabilidade, mês a mês.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( empty( $dados['meses'] ) ) : ?>
		<?php cp_ac_vazio( __( 'Ainda estamos a preparar os números da sua empresa.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<div id="cp-vf-root" data-pendentes="<?php echo esc_attr( count( cp_ac_pendentes_documentos( $empresa_id ) ) + count( cp_ac_pendentes_movimentos( $empresa_id ) ) ); ?>">

		<!-- Navegador de mês -->
		<div class="ac-card ac-vf-nav" style="padding:22px">
			<div class="ac-vf-nav-head">
				<button type="button" class="ac-icon-btn-sm" id="cp-vf-mes-prev" aria-label="<?php esc_attr_e( 'Mês anterior', 'contabilidade-pro' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"></path></svg>
				</button>
				<div style="text-align:center">
					<h2 id="cp-vf-mes-titulo" style="font-size:26px"></h2>
					<div id="cp-vf-mes-sub" style="font-size:12.5px;color:var(--ac-muted);margin-top:4px"></div>
				</div>
				<button type="button" class="ac-icon-btn-sm" id="cp-vf-mes-next" aria-label="<?php esc_attr_e( 'Mês seguinte', 'contabilidade-pro' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"></path></svg>
				</button>
			</div>
			<div style="font-size:12px;color:var(--ac-muted);margin:14px 0 6px"><?php esc_html_e( 'Arraste o marcador ou toque num mês', 'contabilidade-pro' ); ?></div>
			<input type="range" id="cp-vf-mes-slider" class="ac-vf-slider" min="0" value="0" step="1">
			<div id="cp-vf-mes-botoes" class="ac-vf-meses-row"></div>
		</div>

		<!-- Resumo do mês -->
		<div class="ac-card" style="padding:22px">
			<div id="cp-vf-resumo-label" class="ac-stat-label"></div>
			<div id="cp-vf-resumo-texto" style="font-size:16.5px;font-weight:600;line-height:1.5;margin-top:8px"></div>
			<div id="cp-vf-resumo-assinatura" style="font-size:12.5px;color:var(--ac-muted);margin-top:10px"></div>
		</div>

		<!-- 4 destaques -->
		<div class="ac-stats-grid" style="max-width:none">
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
				<div class="ac-stat-label" id="cp-vf-d-vendas-label"></div>
				<div class="ac-vf-destaque-val" id="cp-vf-d-vendas-val"></div>
				<div id="cp-vf-d-vendas-trend" class="ac-trend"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:6px"><?php esc_html_e( 'Tudo o que faturou no mês, sem IVA, comparado com o mesmo mês do ano passado.', 'contabilidade-pro' ); ?></div>
			</div>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
				<div class="ac-stat-label"><?php esc_html_e( 'Lucro acumulado', 'contabilidade-pro' ); ?></div>
				<div class="ac-vf-destaque-val" id="cp-vf-d-lucro-val"></div>
				<div id="cp-vf-d-lucro-trend" class="ac-trend"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:6px"><?php esc_html_e( 'O que sobra das vendas depois de pagar todos os custos do ano. Antes do imposto sobre o lucro.', 'contabilidade-pro' ); ?></div>
			</div>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
				<div class="ac-stat-label" id="cp-vf-d-dinheiro-label"></div>
				<div class="ac-vf-destaque-val" id="cp-vf-d-dinheiro-val"></div>
				<div id="cp-vf-d-dinheiro-trend" class="ac-trend"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:6px"><?php esc_html_e( 'O que a empresa tinha em caixa e nas contas bancárias no último dia do mês.', 'contabilidade-pro' ); ?></div>
			</div>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
					<div class="ac-stat-label"><?php esc_html_e( 'O que precisamos de si', 'contabilidade-pro' ); ?></div>
					<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'A tratar', 'contabilidade-pro' ); ?></span>
				</div>
				<div class="ac-vf-destaque-val" id="cp-vf-d-pendentes-val"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:6px"><?php esc_html_e( 'Faturas e movimentos bancários que ainda não conseguimos justificar. Não depende do mês escolhido.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>

		<!-- As contas do mês -->
		<div>
			<h2 style="font-size:18px"><?php esc_html_e( 'As contas do mês', 'contabilidade-pro' ); ?></h2>
			<div style="font-size:13px;color:var(--ac-muted);margin-top:4px"><?php esc_html_e( 'A Demonstração de Resultados do mês, em imagem. Comece nas vendas, à esquerda: cada barra laranja é um custo que sai e as barras escuras mostram o que fica.', 'contabilidade-pro' ); ?></div>
		</div>
		<div class="ac-card" style="padding:22px">
			<div id="cp-vf-cascata" class="ac-vf-cascata"></div>
			<div id="cp-vf-cascata-legenda" class="ac-vf-cascata-legenda"></div>
		</div>
		<div class="ac-vf-duas-col">
			<div class="ac-card" id="cp-vf-detalhe" style="padding:22px"></div>
			<div class="ac-card" id="cp-vf-ebitda" style="padding:22px"></div>
		</div>

		<!-- Vendas -->
		<div><h2 style="font-size:18px"><?php esc_html_e( 'Vendas — o que entra', 'contabilidade-pro' ); ?></h2></div>
		<div class="ac-vf-duas-col ac-vf-duas-col-vendas">
			<div class="ac-card" style="padding:22px">
				<div class="ac-stat-label"><?php esc_html_e( 'Vendas por mês', 'contabilidade-pro' ); ?></div>
				<div style="display:flex;align-items:baseline;gap:10px;margin-top:4px">
					<div class="ac-vf-destaque-val" id="cp-vf-vendas-total" style="font-size:28px"></div>
					<div id="cp-vf-vendas-trend" class="ac-trend"></div>
				</div>
				<div style="font-size:12px;color:var(--ac-muted)"><?php esc_html_e( 'Total de janeiro ao mês escolhido', 'contabilidade-pro' ); ?></div>
				<div id="cp-vf-vendas-grafico" class="ac-vf-vendas-grafico" style="margin-top:14px"></div>
				<div style="font-size:12px;color:var(--ac-muted);margin-top:10px"><?php esc_html_e( 'Cada par de barras é um mês: a escura é este ano, a clara é o ano passado. Toque numa barra para ver esse mês.', 'contabilidade-pro' ); ?></div>
			</div>
			<div class="ac-card" id="cp-vf-clientes" style="padding:22px" hidden></div>
		</div>

		<!-- Dinheiro -->
		<div><h2 style="font-size:18px"><?php esc_html_e( 'Dinheiro — tesouraria', 'contabilidade-pro' ); ?></h2></div>
		<div class="ac-vf-duas-col">
			<div class="ac-card" style="padding:22px">
				<div class="ac-stat-label"><?php esc_html_e( 'Dinheiro em caixa e bancos', 'contabilidade-pro' ); ?></div>
				<div style="display:flex;align-items:baseline;gap:10px;margin-top:4px">
					<div class="ac-vf-destaque-val" id="cp-vf-tesouraria-dinheiro" style="font-size:28px"></div>
					<div id="cp-vf-tesouraria-trend" class="ac-trend"></div>
				</div>
				<div style="font-size:12px;color:var(--ac-muted)"><?php esc_html_e( 'Saldo no fim de cada mês', 'contabilidade-pro' ); ?></div>
				<div id="cp-vf-tesouraria-linha" class="ac-vf-linha-grafico" style="margin-top:14px"></div>
			</div>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:14px">
				<div>
					<div style="display:flex;align-items:center;justify-content:space-between">
						<div class="ac-stat-label"><?php esc_html_e( 'Fôlego', 'contabilidade-pro' ); ?></div>
						<span id="cp-vf-tesouraria-folego-badge" class="ac-badge ac-badge-green"></span>
					</div>
					<div class="ac-vf-destaque-val" id="cp-vf-tesouraria-folego"></div>
					<div style="font-size:12.5px;color:var(--ac-muted)"><?php esc_html_e( 'Quantos meses consegue pagar os custos fixos — salários, rendas, serviços — só com o dinheiro que tem.', 'contabilidade-pro' ); ?></div>
				</div>
				<div class="ac-vf-grid3">
					<div><div class="ac-stat-label"><?php esc_html_e( 'Os seus clientes devem-lhe', 'contabilidade-pro' ); ?></div><div class="ac-vf-destaque-val" id="cp-vf-a-receber" style="font-size:20px"></div><div id="cp-vf-pmr" style="font-size:12px;color:var(--ac-muted)"></div></div>
					<div><div class="ac-stat-label"><?php esc_html_e( 'Deve aos seus fornecedores', 'contabilidade-pro' ); ?></div><div class="ac-vf-destaque-val" id="cp-vf-a-pagar" style="font-size:20px"></div><div id="cp-vf-pmp" style="font-size:12px;color:var(--ac-muted)"></div></div>
					<div><div class="ac-stat-label"><?php esc_html_e( 'Empréstimos bancários', 'contabilidade-pro' ); ?></div><div class="ac-vf-destaque-val" id="cp-vf-divida" style="font-size:20px"></div><div id="cp-vf-juros" style="font-size:12px;color:var(--ac-muted)"></div></div>
				</div>
			</div>
		</div>

		<!-- Estado, equipa e solidez -->
		<div><h2 style="font-size:18px"><?php esc_html_e( 'Estado, equipa e solidez', 'contabilidade-pro' ); ?></h2></div>
		<div class="ac-vf-grid3-card">
			<div class="ac-card" style="padding:22px">
				<div style="display:flex;align-items:center;justify-content:space-between">
					<div class="ac-stat-label"><?php esc_html_e( 'Devido ao Estado', 'contabilidade-pro' ); ?></div>
					<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'A pagar em breve', 'contabilidade-pro' ); ?></span>
				</div>
				<div class="ac-vf-destaque-val" id="cp-vf-estado-total"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:4px"><?php esc_html_e( 'Valores já devidos no fim do mês e ainda não pagos: IVA, IRS retido nos salários e Segurança Social.', 'contabilidade-pro' ); ?></div>
				<div style="font-size:11px;font-weight:600;color:var(--ac-muted);text-transform:uppercase;letter-spacing:.04em;margin-top:16px"><?php esc_html_e( 'Prazos do mês seguinte', 'contabilidade-pro' ); ?></div>
				<div id="cp-vf-prazos" style="margin-top:8px"></div>
			</div>
			<div class="ac-card" style="padding:22px">
				<div class="ac-stat-label"><?php esc_html_e( 'A sua equipa', 'contabilidade-pro' ); ?></div>
				<div class="ac-vf-destaque-val" id="cp-vf-equipa-pessoas"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:4px" id="cp-vf-equipa-custo"></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:10px"><?php esc_html_e( 'Colaboradores com contrato no mês e o custo total para a empresa: salários, subsídios, Segurança Social paga pela empresa e seguro de acidentes de trabalho.', 'contabilidade-pro' ); ?></div>
			</div>
			<div class="ac-card" style="padding:22px">
				<div style="display:flex;align-items:center;justify-content:space-between">
					<div class="ac-stat-label"><?php esc_html_e( 'Quanto da empresa é dos sócios', 'contabilidade-pro' ); ?></div>
					<span id="cp-vf-autonomia-badge" class="ac-badge ac-badge-green"></span>
				</div>
				<div class="ac-vf-destaque-val" id="cp-vf-autonomia-val"></div>
				<div class="ac-vf-autonomia-barra"><div class="ac-vf-autonomia-marca"></div><div class="ac-vf-autonomia-fill" id="cp-vf-autonomia-fill"></div></div>
				<div style="font-size:12.5px;color:var(--ac-muted);margin-top:4px"><?php esc_html_e( 'De tudo o que a empresa tem, que parte é financiada pelos sócios — o resto é dívida a bancos, fornecedores e Estado. Quanto maior, menos dependente está de terceiros; acima de 30% é geralmente visto como saudável pelos bancos.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>

		<div style="font-size:12px;color:var(--ac-muted);text-align:center;padding:8px 0"><?php esc_html_e( 'Os valores desta página são calculados a partir da contabilidade mensal. Em caso de dúvida, fale connosco.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php
}

// ═════════════════════════════════════════════════════════════════════════
// DEBUG — só para testar a Visão financeira sem a app interna (Work).
// Semeia/limpa o post meta `_cp_visao_financeira` de uma empresa à escolha,
// direto no wp-admin, sem passar pelo endpoint REST (e portanto sem
// Application Password). Aparece na mesma página da matriz de ferramentas,
// só a quem já tem `manage_options`. Pode ser removido sem afetar o resto
// desta ferramenta — não é usado pelo site em produção, só por quem está
// com sessão aberta no wp-admin a testar.
// ═════════════════════════════════════════════════════════════════════════

function cp_ac_vf_payload_exemplo( $cenario ) {
	$base_mes = [
		'mes'       => '2026-10',
		'fechado_em' => '2026-11-07',
		'resumo'    => [
			'tipo'       => 'equipa',
			'texto'      => 'Outubro foi o melhor mês do ano: vendeu 48.300 €, mais 8,1% do que em outubro de 2025, e o EBITDA do mês chegou a 7.568 €. Atenção ao IVA de setembro, a pagar até 25 de novembro (cerca de 3.215 €) — o saldo em banco chega com folga.',
			'assinatura' => 'Equipa Entre Números · 7 de novembro de 2026',
		],
		'destaques' => [
			'vendas' => 48300.0, 'vendas_ano_anterior' => 44700.0,
			'lucro_acumulado' => 30245.0, 'lucro_acumulado_ano_anterior' => 26700.0,
			'dinheiro' => 46800.0, 'dinheiro_mes_anterior' => 43600.0,
		],
		'contas' => [
			[ 'id' => 'vendas', 'tipo' => 'vendas', 'nome' => 'Vendas', 'titulo' => 'Vendas', 'explicacao' => 'Tudo o que faturou no mês, sem IVA.', 'valor' => 48300.0, 'media_12m' => 41900.0, 'acumulado' => 404500.0, 'acumulado_ano_anterior' => 379200.0, 'composicao' => [ [ 'nome' => 'Venda de mercadorias', 'valor' => 44436.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Prestação de serviços', 'valor' => 3864.0, 'nota' => null, 'destaque' => false ] ], 'formula' => null ],
			[ 'id' => 'mercadoria', 'tipo' => 'custo', 'nome' => 'Mercadoria', 'titulo' => 'Mercadoria', 'explicacao' => 'Custo da mercadoria vendida no mês.', 'valor' => 29946.0, 'media_12m' => 25800.0, 'acumulado' => 250790.0, 'acumulado_ano_anterior' => 235400.0, 'composicao' => [ [ 'nome' => 'Compras de mercadoria', 'valor' => 29946.0, 'nota' => null, 'destaque' => false ] ], 'formula' => null ],
			[ 'id' => 'margem_bruta', 'tipo' => 'subtotal', 'nome' => 'Margem bruta', 'titulo' => 'Margem bruta', 'explicacao' => 'Vendas menos o custo da mercadoria vendida.', 'valor' => 18354.0, 'media_12m' => 16100.0, 'acumulado' => 153710.0, 'acumulado_ano_anterior' => 143800.0, 'composicao' => null, 'formula' => null ],
			[ 'id' => 'servicos', 'tipo' => 'custo', 'nome' => 'Serviços', 'titulo' => 'Serviços', 'explicacao' => 'Rendas, energia, combustível, reparações e outros serviços comprados a terceiros.', 'valor' => 3244.0, 'media_12m' => 3218.0, 'acumulado' => 32578.0, 'acumulado_ano_anterior' => 30920.0, 'composicao' => [ [ 'nome' => 'Rendas', 'valor' => 1200.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Combustíveis', 'valor' => 657.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Eletricidade e água', 'valor' => 320.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Honorários (contabilidade e outros)', 'valor' => 300.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Conservação e reparação', 'valor' => 280.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Seguros', 'valor' => 140.0, 'nota' => null, 'destaque' => false ], [ 'nome' => 'Telecomunicações', 'valor' => 347.0, 'nota' => null, 'destaque' => false ] ], 'formula' => null ],
			[ 'id' => 'pessoal', 'tipo' => 'custo', 'nome' => 'Pessoal', 'titulo' => 'Pessoal', 'explicacao' => 'Salários, subsídios, Segurança Social da empresa e seguro de acidentes de trabalho.', 'valor' => 7232.0, 'media_12m' => 7100.0, 'acumulado' => 72320.0, 'acumulado_ano_anterior' => 68900.0, 'composicao' => [ [ 'nome' => 'Salários', 'valor' => 7232.0, 'nota' => null, 'destaque' => false ] ], 'formula' => null ],
			[ 'id' => 'outros_gastos', 'tipo' => 'custo', 'nome' => 'Outros gastos', 'titulo' => 'Outros gastos', 'explicacao' => 'Outros gastos operacionais não incluídos nas rubricas anteriores.', 'valor' => 310.0, 'media_12m' => 300.0, 'acumulado' => 3100.0, 'acumulado_ano_anterior' => 2900.0, 'composicao' => null, 'formula' => null ],
			[ 'id' => 'ebitda', 'tipo' => 'subtotal', 'nome' => 'EBITDA', 'titulo' => 'EBITDA', 'explicacao' => 'O que a atividade gera antes de juros, impostos e desgaste dos equipamentos.', 'valor' => 7568.0, 'media_12m' => 6400.0, 'acumulado' => 38275.0, 'acumulado_ano_anterior' => 34800.0, 'composicao' => null, 'formula' => [ [ 'nome' => 'Margem bruta', 'valor' => 18354.0 ], [ 'nome' => 'Serviços', 'valor' => -3244.0 ], [ 'nome' => 'Pessoal', 'valor' => -7232.0 ], [ 'nome' => 'Outros gastos', 'valor' => -310.0 ] ] ],
			[ 'id' => 'desgaste', 'tipo' => 'custo', 'nome' => 'Desgaste', 'titulo' => 'Desgaste', 'explicacao' => 'Desgaste (depreciação) dos equipamentos.', 'valor' => 560.0, 'media_12m' => 560.0, 'acumulado' => 5600.0, 'acumulado_ano_anterior' => 5600.0, 'composicao' => null, 'formula' => null ],
			[ 'id' => 'juros', 'tipo' => 'custo', 'nome' => 'Juros', 'titulo' => 'Juros', 'explicacao' => 'Juros pagos pelos empréstimos bancários.', 'valor' => 243.0, 'media_12m' => 243.0, 'acumulado' => 2430.0, 'acumulado_ano_anterior' => 2500.0, 'composicao' => null, 'formula' => null ],
			[ 'id' => 'lucro', 'tipo' => 'subtotal', 'nome' => 'Lucro', 'titulo' => 'Lucro', 'explicacao' => 'O que sobra das vendas depois de pagar todos os custos do ano. Antes do imposto sobre o lucro.', 'valor' => 6765.0, 'media_12m' => 5600.0, 'acumulado' => 30245.0, 'acumulado_ano_anterior' => 26700.0, 'composicao' => null, 'formula' => null ],
		],
		'ebitda' => [
			'valor' => 7568.0, 'acumulado' => 38275.0, 'depreciacoes_acumulado' => 5600.0, 'juros_acumulado' => 2430.0,
			'lucro_acumulado' => 30245.0, 'divida_bancaria' => 32000.0, 'divida_em_anos_de_ebitda' => 0.4,
			'por_cada_100_ebitda' => 15.1, 'por_cada_100_lucro' => 13.1,
		],
		'tesouraria' => [
			'folego_meses' => 4.0, 'a_receber' => 58518.0, 'pmr_dias' => 36, 'a_pagar' => 41254.0, 'pmp_dias' => 41,
			'divida_bancaria' => 32000.0, 'juros_acumulado' => 2430.0,
		],
		'estado' => [
			'total' => 9636.0,
			'prazos' => [
				[ 'data' => '2026-11-10', 'titulo' => 'Declaração de remunerações (Segurança Social e Finanças)', 'valor' => null, 'nota' => 'Entrega — tratamos nós' ],
				[ 'data' => '2026-11-20', 'titulo' => 'Pagamento da Segurança Social e do IRS retido', 'valor' => 2722.0, 'nota' => null ],
				[ 'data' => '2026-11-25', 'titulo' => 'Pagamento do IVA de setembro', 'valor' => 3215.0, 'nota' => null ],
			],
		],
		'equipa'        => [ 'pessoas' => 5, 'custo_mes' => 7232.0 ],
		'autonomia_pct' => 41.0,
		'clientes_top'  => null,
	];

	$series = [
		'vendas'   => array_map( null, [ '2025-01', '2025-02', '2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10' ], [ 34100, 36800, 41200, 43500, 45900, 44000, 39700, 28400, 42600, 44700, 34100, 36800, 41200, 43500, 45900, 44000, 39700, 28400, 42600, 48300 ] ),
		'dinheiro' => array_map( null, [ '2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10' ], [ 38200, 39500, 35000, 36100, 37800, 38900, 40200, 41000, 40500, 39800, 43600, 46800 ] ),
	];
	foreach ( $series as $chave => $pares ) {
		$series[ $chave ] = array_map( function ( $par ) { return [ 'mes' => $par[0], 'valor' => (float) $par[1] ]; }, $pares );
	}

	if ( 'vazio' === $cenario ) {
		$meses = [];
	} elseif ( 'negativo' === $cenario ) {
		// Mesma estrutura, mas um mês em que os custos superam as vendas —
		// testa a cascata abaixo da linha de zero (subtotal "Lucro" negativo).
		$mes = $base_mes;
		$mes['resumo']['tipo']  = 'automatico';
		$mes['resumo']['texto'] = 'Novembro fechou com um lucro negativo de −2.180 €: as vendas caíram e os custos de Pessoal e Serviços mantiveram-se. O EBITDA do mês ficou em −1.420 €.';
		$mes['mes'] = '2026-11';
		$mes['destaques']['vendas'] = 22100.0;
		foreach ( $mes['contas'] as &$conta ) {
			if ( 'vendas' === $conta['id'] ) $conta['valor'] = 22100.0;
			if ( 'margem_bruta' === $conta['id'] ) $conta['valor'] = 8400.0;
			if ( 'ebitda' === $conta['id'] ) { $conta['valor'] = -1420.0; $conta['formula'] = [ [ 'nome' => 'Margem bruta', 'valor' => 8400.0 ], [ 'nome' => 'Serviços', 'valor' => -3244.0 ], [ 'nome' => 'Pessoal', 'valor' => -7232.0 ], [ 'nome' => 'Outros gastos', 'valor' => -310.0 ] ]; }
			if ( 'lucro' === $conta['id'] ) $conta['valor'] = -2180.0;
		}
		unset( $conta );
		$mes['ebitda']['valor'] = -1420.0;
		$meses = [ $mes ];
	} elseif ( 'servicos' === $cenario ) {
		// Empresa de serviços: sem barra "Mercadoria" nem "Margem bruta" —
		// a cascata começa logo em "Serviços".
		$mes = $base_mes;
		$mes['contas'] = array_values( array_filter( $mes['contas'], function ( $c ) { return ! in_array( $c['id'], [ 'mercadoria', 'margem_bruta' ], true ); } ) );
		$meses = [ $mes ];
	} else {
		$meses = [ $base_mes ];
	}

	return [ 'atualizado_em' => current_time( 'mysql' ), 'meses' => $meses, 'series' => $series ];
}

function cp_ac_vf_debug_seed_ui( $ctx ) {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$empresas = isset( $ctx->empresas ) ? $ctx->empresas : [];
	if ( empty( $empresas ) ) return;

	if ( isset( $_POST['cp_ac_vf_seed_nonce'] ) && wp_verify_nonce( $_POST['cp_ac_vf_seed_nonce'], 'cp_ac_vf_seed' ) ) {
		$empresa_id = absint( $_POST['cp_vf_empresa'] );
		$cenario    = sanitize_key( $_POST['cp_vf_cenario'] );
		if ( $empresa_id && get_post_type( $empresa_id ) === 'cp_empresa' ) {
			update_post_meta( $empresa_id, '_cp_visao_financeira', cp_ac_vf_payload_exemplo( $cenario ) );
			$ativas = cp_ac_empresa_ferramentas_ativas( $empresa_id );
			if ( ! in_array( 'visao_financeira', $ativas, true ) ) {
				cp_ac_set_empresa_ferramentas( $empresa_id, array_merge( $ativas, [ 'visao_financeira' ] ) );
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Dados de teste da Visão financeira gravados (e ferramenta ligada) para esta empresa.', 'contabilidade-pro' ) . '</p></div>';
		}
	}
	?>
	<hr style="margin:32px 0 20px">
	<h2><?php esc_html_e( 'Visão financeira — dados de teste (debug)', 'contabilidade-pro' ); ?></h2>
	<p style="max-width:640px"><?php esc_html_e( 'Semeia o post meta diretamente, sem passar pelo endpoint REST — só para testar o ecrã sem esperar pela app interna (Work). Não afeta mais nenhuma ferramenta.', 'contabilidade-pro' ); ?></p>
	<form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;max-width:720px">
		<?php wp_nonce_field( 'cp_ac_vf_seed', 'cp_ac_vf_seed_nonce' ); ?>
		<label>
			<?php esc_html_e( 'Empresa', 'contabilidade-pro' ); ?><br>
			<select name="cp_vf_empresa">
				<?php foreach ( $empresas as $empresa ) : ?>
					<option value="<?php echo esc_attr( $empresa->ID ); ?>"><?php echo esc_html( $empresa->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Cenário', 'contabilidade-pro' ); ?><br>
			<select name="cp_vf_cenario">
				<option value="exemplo"><?php esc_html_e( 'Normal (outubro 2026, lucro positivo)', 'contabilidade-pro' ); ?></option>
				<option value="negativo"><?php esc_html_e( 'Lucro negativo (novembro 2026)', 'contabilidade-pro' ); ?></option>
				<option value="servicos"><?php esc_html_e( 'Empresa de serviços (sem Mercadoria)', 'contabilidade-pro' ); ?></option>
				<option value="vazio"><?php esc_html_e( 'Vazio (meses: [])', 'contabilidade-pro' ); ?></option>
			</select>
		</label>
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Semear dados de teste', 'contabilidade-pro' ); ?></button>
	</form>
	<?php
}
add_action( 'cp_ac_matriz_extra', 'cp_ac_vf_debug_seed_ui' );
