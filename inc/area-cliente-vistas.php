<?php
/**
 * Área de Cliente — conteúdo de cada "vista" (ferramenta) do dashboard.
 *
 * Importante: estas ferramentas ainda não têm uma fonte de dados real por
 * trás (isso liga-se quando a empresa estiver sincronizada com a app
 * interna). Por isso cada vista mostra apenas a estrutura/UI e um estado
 * vazio honesto — nunca números, documentos ou movimentos inventados.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Util: cartão de estado vazio ──────────────────────────────────────────

function cp_ac_vazio( $titulo, $texto = '' ) {
	?>
	<div class="ac-card" style="padding:32px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:8px">
		<div style="width:40px;height:40px;border-radius:50%;background:var(--ac-bg);display:flex;align-items:center;justify-content:center;color:var(--ac-muted)">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9 12h6"></path></svg>
		</div>
		<div style="font-size:14.5px;font-weight:600"><?php echo esc_html( $titulo ); ?></div>
		<?php if ( $texto ) : ?>
			<div style="font-size:13px;color:var(--ac-muted);max-width:380px"><?php echo esc_html( $texto ); ?></div>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Dashboard ──────────────────────────────────────────────────────────────

function cp_ac_vista_dashboard( $nome_utilizador, $ferramentas ) {
	$empresa_id = cp_ac_empresa_ativa();

	$tem_visao_financeira  = cp_ac_tem_ferramenta( 'visao_financeira' );
	$tem_conta_corrente    = cp_ac_tem_ferramenta( 'conta_corrente' );
	$tem_calendario_fiscal = cp_ac_tem_ferramenta( 'calendario_fiscal' );
	$tem_mapas_gestao      = cp_ac_tem_ferramenta( 'mapas_gestao' );
	$tem_documentos        = cp_ac_tem_ferramenta( 'documentos' );
	$tem_documentos_falta  = cp_ac_tem_ferramenta( 'documentos_em_falta' );
	$tem_movimentos        = cp_ac_tem_ferramenta( 'movimentos_a_esclarecer' );
	$tem_extratos          = cp_ac_tem_ferramenta( 'extratos' );
	$tem_saft              = cp_ac_tem_ferramenta( 'saft' );
	$tem_tarefas           = cp_ac_tem_ferramenta( 'tarefas' );
	$tem_mensagens         = cp_ac_tem_ferramenta( 'mensagens' );

	?>
	<div>
		<h1 style="font-size:23px"><?php printf( /* translators: %s: nome do utilizador */ esc_html__( 'Olá, %s', 'contabilidade-pro' ), esc_html( $nome_utilizador ) ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php printf( /* translators: %s: nome da empresa */ esc_html__( 'Situação atual — %s', 'contabilidade-pro' ), esc_html( get_the_title( $empresa_id ) ) ); ?></div>
	</div>

	<?php if ( ! $tem_visao_financeira && ! $tem_conta_corrente && ! $tem_calendario_fiscal && ! $tem_mapas_gestao && ! $tem_documentos && ! $tem_documentos_falta && ! $tem_movimentos && ! $tem_extratos && ! $tem_saft && ! $tem_tarefas && ! $tem_mensagens ) : ?>
		<?php cp_ac_vazio( __( 'Ainda não há ferramentas ativadas para esta empresa.', 'contabilidade-pro' ), __( 'Contacte a Entre Números se estiver à espera de acesso a alguma funcionalidade.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<?php
	$lembretes_sucesso  = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
	$lembretes_erro     = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	$lembretes_titulos  = [
		'tarefa_criada'     => __( 'Lembrete adicionado', 'contabilidade-pro' ),
		'tarefa_atualizada' => __( 'Lembrete atualizado', 'contabilidade-pro' ),
		'tarefa_apagada'    => __( 'Lembrete apagado', 'contabilidade-pro' ),
	];
	$documentos_sucesso = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
	$documentos_erro    = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	$documentos_titulo  = cp_ac_documento_sucesso_titulo(
		$documentos_sucesso,
		isset( $_GET['cp_n'] ) ? absint( $_GET['cp_n'] ) : 1,
		isset( $_GET['cp_recusados'] ) ? absint( $_GET['cp_recusados'] ) : 0
	);
	?>

	<?php if ( $tem_tarefas && isset( $lembretes_titulos[ $lembretes_sucesso ] ) ) : ?>
		<div class="ac-status-banner green">
			<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
			<div><div class="ac-status-title"><?php echo esc_html( $lembretes_titulos[ $lembretes_sucesso ] ); ?></div></div>
		</div>
	<?php elseif ( $tem_tarefas && $lembretes_erro ) : ?>
		<div class="ac-status-banner amber">
			<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível concluir o pedido', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber"><?php esc_html_e( 'Escreva um texto para o lembrete e tente novamente.', 'contabilidade-pro' ); ?></div></div>
		</div>
	<?php endif; ?>

	<?php if ( $tem_documentos && '' !== $documentos_titulo ) : ?>
		<div class="ac-status-banner green">
			<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
			<div><div class="ac-status-title"><?php echo esc_html( $documentos_titulo ); ?></div></div>
		</div>
	<?php elseif ( $tem_documentos && '' !== cp_ac_documento_erro_texto( $documentos_erro ) ) : ?>
		<div class="ac-status-banner amber">
			<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível concluir o pedido', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber"><?php echo esc_html( cp_ac_documento_erro_texto( $documentos_erro ) ); ?></div></div>
		</div>
	<?php endif; ?>

	<div class="ac-dashboard-cols">

		<div class="ac-dashboard-main">
			<div class="ac-card ac-resumo-card">
				<div class="ac-resumo-head"><?php esc_html_e( 'A sua empresa', 'contabilidade-pro' ); ?></div>

				<?php if ( $tem_visao_financeira ) :
					$vf       = cp_ac_visao_financeira( $empresa_id );
					$ultimo_m = ! empty( $vf['meses'] ) ? end( $vf['meses'] ) : null;
					$valor    = $ultimo_m
						? esc_html( number_format_i18n( $ultimo_m['destaques']['vendas'], 0 ) ) . '&nbsp;€'
						: esc_html__( 'A preparar os seus números', 'contabilidade-pro' );
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'visao_financeira' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo $ultimo_m ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Visão financeira', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo $ultimo_m ? ' is-ok' : ''; ?>"><?php echo $valor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $tem_calendario_fiscal ) : ?>
					<div class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Próximo prazo', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value"><?php esc_html_e( 'Sem prazos agendados', 'contabilidade-pro' ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $tem_mapas_gestao ) :
					$mapas  = cp_ac_mapas_gestao( $empresa_id );
					$ultimo = ! empty( $mapas ) ? $mapas[0] : null;
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'mapas_gestao' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo $ultimo ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Mapa de Gestão', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo $ultimo ? ' is-ok' : ''; ?>"><?php echo $ultimo ? esc_html( $ultimo['titulo'] ) : esc_html__( 'Sem mapas publicados', 'contabilidade-pro' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $tem_documentos_falta ) :
					$pendentes_por_resolver = count( array_filter( cp_ac_pendentes_documentos( $empresa_id ), function ( $item ) use ( $empresa_id ) {
						return ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
					} ) );
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'documentos_em_falta' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo 0 === $pendentes_por_resolver ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Documentos em falta', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo 0 === $pendentes_por_resolver ? ' is-ok' : ''; ?>">
							<?php echo $pendentes_por_resolver > 0 ? esc_html( $pendentes_por_resolver ) : esc_html__( 'Tudo em dia', 'contabilidade-pro' ); ?>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( $tem_movimentos ) :
					$movimentos_por_resolver = count( array_filter( cp_ac_pendentes_movimentos( $empresa_id ), function ( $item ) use ( $empresa_id ) {
						return ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
					} ) );
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'movimentos_a_esclarecer' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo 0 === $movimentos_por_resolver ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Movimentos a esclarecer', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo 0 === $movimentos_por_resolver ? ' is-ok' : ''; ?>">
							<?php echo $movimentos_por_resolver > 0 ? esc_html( $movimentos_por_resolver ) : esc_html__( 'Tudo em dia', 'contabilidade-pro' ); ?>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( $tem_extratos ) :
					$extratos_por_resolver = count( array_filter( cp_ac_extratos( $empresa_id ), function ( $item ) use ( $empresa_id ) {
						return cp_ac_item_checklist_por_tratar( $empresa_id, $item );
					} ) );
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'extratos' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo 0 === $extratos_por_resolver ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'Extratos', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo 0 === $extratos_por_resolver ? ' is-ok' : ''; ?>">
							<?php echo $extratos_por_resolver > 0 ? esc_html( $extratos_por_resolver ) : esc_html__( 'Tudo em dia', 'contabilidade-pro' ); ?>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( $tem_saft ) :
					$saft_por_resolver = count( array_filter( cp_ac_saft( $empresa_id ), function ( $item ) use ( $empresa_id ) {
						return cp_ac_item_checklist_por_tratar( $empresa_id, $item );
					} ) );
					?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'saft' ) ); ?>" class="ac-resumo-row">
						<span class="ac-resumo-row-left">
							<span class="ac-resumo-dot<?php echo 0 === $saft_por_resolver ? ' is-ok' : ''; ?>"></span>
							<span class="ac-resumo-label"><?php esc_html_e( 'SAF-T', 'contabilidade-pro' ); ?></span>
						</span>
						<span class="ac-resumo-value<?php echo 0 === $saft_por_resolver ? ' is-ok' : ''; ?>">
							<?php echo $saft_por_resolver > 0 ? esc_html( $saft_por_resolver ) : esc_html__( 'Tudo em dia', 'contabilidade-pro' ); ?>
						</span>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="ac-dashboard-side">

			<?php if ( $tem_conta_corrente ) :
				$cc        = cp_ac_conta_corrente( $empresa_id );
				$resumo_cc = isset( $cc['resumo'] ) ? $cc['resumo'] : [];
				$linhas    = isset( $cc['linhas'] ) ? $cc['linhas'] : [];
				$por_pagar = count( array_filter( $linhas, function ( $linha ) {
					return ! in_array( strtolower( $linha['estado'] ), [ 'pago', 'paga' ], true );
				} ) );
				?>
				<div class="ac-card ac-cc-card">
					<div class="ac-stat-label"><?php esc_html_e( 'Conta corrente', 'contabilidade-pro' ); ?></div>
					<div class="ac-cc-amount"><?php echo esc_html( number_format_i18n( isset( $resumo_cc['saldo_atual'] ) ? $resumo_cc['saldo_atual'] : 0, 2 ) ); ?>&nbsp;€</div>
					<div class="ac-cc-sub"><?php printf( /* translators: %d: número de documentos */ esc_html( _n( '%d documento por liquidar', '%d documentos por liquidar', $por_pagar, 'contabilidade-pro' ) ), $por_pagar ); ?></div>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'conta_corrente' ) ); ?>" class="ac-cc-link"><?php esc_html_e( 'Ver conta corrente →', 'contabilidade-pro' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $tem_tarefas ) : ?>
				<div class="ac-resumo-head" style="padding-left:4px"><?php esc_html_e( 'Lembretes', 'contabilidade-pro' ); ?></div>
				<?php cp_ac_render_tarefas( $empresa_id ); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $tem_documentos ) : ?>
		<?php cp_ac_render_documento_modal(); ?>
	<?php endif; ?>
	<?php
	// Mensagens não tem painel no Dashboard — vive no dropdown do
	// utilizador (topbar) e no atalho do "+", ambos renderizados a nível
	// de página em page-area-de-cliente.php (válidos em qualquer vista,
	// não só aqui). Ver cp_ac_render_mensagens_dropdown() e
	// cp_ac_render_fab_acoes_rapidas().
}

// Botão "+" flutuante (visível em qualquer vista da Área de Cliente, não
// só no Dashboard — chamado a partir de page-area-de-cliente.php): abre
// uma lista de ações rápidas (enviar documento, enviar mensagem). Feito
// para crescer — cada nova ação acrescenta uma chave a $ativas, o seu
// próprio item (cp_ac_render_..._fab_item()) e a sua própria modal, sem
// tocar neste botão.
function cp_ac_render_fab_acoes_rapidas( $ativas ) {
	if ( ! array_filter( $ativas ) ) return;
	?>
	<div class="ac-fab-wrap">
		<div id="cp-fab-menu" class="ac-fab-menu" hidden role="menu">
			<?php if ( ! empty( $ativas['documento'] ) ) : ?>
				<?php cp_ac_render_documento_fab_item(); ?>
			<?php endif; ?>
			<?php if ( ! empty( $ativas['mensagem'] ) ) : ?>
				<?php cp_ac_render_mensagem_fab_item(); ?>
			<?php endif; ?>
		</div>
		<button type="button" id="cp-fab-btn" class="ac-fab" aria-haspopup="menu" aria-controls="cp-fab-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ações rápidas', 'contabilidade-pro' ); ?>">
			<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
		</button>
	</div>
	<?php
}

// ─── Conta Corrente ─────────────────────────────────────────────────────────

function cp_ac_vista_conta_corrente() {
	$empresa_id = cp_ac_empresa_ativa();
	$dados      = cp_ac_conta_corrente( $empresa_id );
	$resumo     = $dados['resumo'];
	$linhas     = $dados['linhas'];
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Conta Corrente', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Consulte as faturas e recibos emitidos pela Entre Números.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( ! empty( $resumo ) ) : ?>
	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
		<?php if ( isset( $resumo['saldo_atual'] ) ) : ?>
		<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
			<div class="ac-stat-label"><?php esc_html_e( 'Saldo atual', 'contabilidade-pro' ); ?></div>
			<div style="font-family:'Fraunces',serif;font-size:37px;color:var(--ac-gold)"><?php echo esc_html( number_format_i18n( $resumo['saldo_atual'], 2 ) ); ?>&nbsp;€</div>
			<?php if ( ! empty( $resumo['saldo_nota'] ) ) : ?><div style="font-size:12.5px;color:var(--ac-muted)"><?php echo esc_html( $resumo['saldo_nota'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
		<?php if ( isset( $resumo['faturado_ano'] ) ) : ?>
		<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
			<div class="ac-stat-label"><?php esc_html_e( 'Faturado este ano', 'contabilidade-pro' ); ?></div>
			<div style="font-family:'Fraunces',serif;font-size:37px"><?php echo esc_html( number_format_i18n( $resumo['faturado_ano'], 2 ) ); ?>&nbsp;€</div>
			<?php if ( ! empty( $resumo['faturado_nota'] ) ) : ?><div style="font-size:12.5px;color:var(--ac-muted)"><?php echo esc_html( $resumo['faturado_nota'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
		<?php if ( isset( $resumo['pago_ano'] ) ) : ?>
		<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
			<div class="ac-stat-label"><?php esc_html_e( 'Pago este ano', 'contabilidade-pro' ); ?></div>
			<div style="font-family:'Fraunces',serif;font-size:37px"><?php echo esc_html( number_format_i18n( $resumo['pago_ano'], 2 ) ); ?>&nbsp;€</div>
			<?php if ( ! empty( $resumo['pago_nota'] ) ) : ?><div style="font-size:12.5px;color:var(--ac-muted)"><?php echo esc_html( $resumo['pago_nota'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $linhas ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem documentos a mostrar', 'contabilidade-pro' ), __( 'As faturas e recibos emitidos pela Entre Números vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
	<div class="ac-table-wrap">
		<table class="ac-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Documento', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Tipo', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $linhas as $linha ) :
					$estado      = strtolower( $linha['estado'] );
					$badge_class = in_array( $estado, [ 'pago', 'paga' ], true ) ? 'ac-badge-green' : 'ac-badge-amber';
					?>
					<tr>
						<td><?php echo esc_html( $linha['data'] ); ?></td>
						<td><?php echo esc_html( $linha['documento'] ); ?></td>
						<td><?php echo esc_html( $linha['tipo'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $linha['valor'], 2 ) ); ?>&nbsp;€</td>
						<td><span class="ac-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $linha['estado'] ); ?></span></td>
						<td>
							<?php if ( ! empty( $linha['anexo_url'] ) ) : ?>
								<a class="ac-icon-btn-sm" href="<?php echo esc_url( $linha['anexo_url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Descarregar', 'contabilidade-pro' ); ?>">
									<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10M8 10.5 12 14l4-3.5"></path><path d="M5 17v1.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V17"></path></svg>
								</a>
							<?php elseif ( ! empty( $linha['ficheiro_removido'] ) ) : ?>
								<span style="font-size:12px;color:var(--ac-muted)" title="<?php esc_attr_e( 'O PDF deste documento foi retirado do site. Peça-nos uma cópia se precisar.', 'contabilidade-pro' ); ?>"><?php esc_html_e( 'PDF retirado', 'contabilidade-pro' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
	<?php
}

// ─── Recibos de Vencimento ──────────────────────────────────────────────────

function cp_ac_vista_recibos_vencimento() {
	$empresa_id = cp_ac_empresa_ativa();
	$recibos    = cp_ac_recibos_vencimento( $empresa_id );

	// Agrupa por colaborador (cod_emp); dentro de cada grupo, mais recente primeiro.
	$grupos = [];
	foreach ( $recibos as $recibo ) {
		$chave = $recibo['cod_emp'];
		if ( ! isset( $grupos[ $chave ] ) ) {
			$grupos[ $chave ] = [ 'nome_emp' => $recibo['nome_emp'], 'itens' => [] ];
		}
		$grupos[ $chave ]['itens'][] = $recibo;
	}
	foreach ( $grupos as &$grupo ) {
		usort( $grupo['itens'], function ( $a, $b ) {
			return ( (int) $b['ano'] <=> (int) $a['ano'] ) ?: ( (int) $b['mes'] <=> (int) $a['mes'] );
		} );
	}
	unset( $grupo );
	uasort( $grupos, function ( $a, $b ) { return strcasecmp( $a['nome_emp'], $b['nome_emp'] ); } );

	$meses = [
		1 => __( 'Janeiro', 'contabilidade-pro' ), 2 => __( 'Fevereiro', 'contabilidade-pro' ), 3 => __( 'Março', 'contabilidade-pro' ),
		4 => __( 'Abril', 'contabilidade-pro' ), 5 => __( 'Maio', 'contabilidade-pro' ), 6 => __( 'Junho', 'contabilidade-pro' ),
		7 => __( 'Julho', 'contabilidade-pro' ), 8 => __( 'Agosto', 'contabilidade-pro' ), 9 => __( 'Setembro', 'contabilidade-pro' ),
		10 => __( 'Outubro', 'contabilidade-pro' ), 11 => __( 'Novembro', 'contabilidade-pro' ), 12 => __( 'Dezembro', 'contabilidade-pro' ),
	];
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Recibos de Vencimento', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Consulte e descarregue os recibos de vencimento mensais dos colaboradores.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( empty( $grupos ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem recibos disponíveis', 'contabilidade-pro' ), __( 'Os recibos de vencimento processados aparecem aqui, mês a mês.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
		<?php foreach ( $grupos as $grupo ) : ?>
		<div class="ac-card" style="padding:22px">
			<div class="ac-sec-head">
				<div>
					<h3 style="font-size:16px"><?php echo esc_html( $grupo['nome_emp'] ); ?></h3>
				</div>
				<span class="ac-badge ac-badge-amber"><?php echo esc_html( count( $grupo['itens'] ) ); ?> <?php esc_html_e( 'recibos', 'contabilidade-pro' ); ?></span>
			</div>
			<div class="ac-table-wrap">
				<table class="ac-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Mês', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Ano', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Tipo de Processamento', 'contabilidade-pro' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $grupo['itens'] as $item ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $meses[ (int) $item['mes'] ] ) ? $meses[ (int) $item['mes'] ] : $item['mes'] ); ?></td>
								<td><?php echo esc_html( $item['ano'] ); ?></td>
								<td><?php echo esc_html( $item['tipo_processamento'] ); ?></td>
								<td>
									<?php if ( ! empty( $item['anexo_url'] ) ) : ?>
										<a class="ac-icon-btn-sm" href="<?php echo esc_url( $item['anexo_url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Descarregar', 'contabilidade-pro' ); ?>">
											<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10M8 10.5 12 14l4-3.5"></path><path d="M5 17v1.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V17"></path></svg>
										</a>
									<?php elseif ( ! empty( $item['ficheiro_removido'] ) ) : ?>
										<span style="font-size:12px;color:var(--ac-muted)" title="<?php esc_attr_e( 'O PDF deste recibo foi retirado do site. Peça-nos uma cópia se precisar.', 'contabilidade-pro' ); ?>"><?php esc_html_e( 'PDF retirado', 'contabilidade-pro' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php
}

// ─── Documentos ─────────────────────────────────────────────────────────────

function cp_ac_vista_documentos() {
	$empresa_id = cp_ac_empresa_ativa();
	$sucesso    = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
	$erro       = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Documentos', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Envie e acompanhe os documentos correntes da sua contabilidade.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php
	$titulo_sucesso = cp_ac_documento_sucesso_titulo(
		$sucesso,
		isset( $_GET['cp_n'] ) ? absint( $_GET['cp_n'] ) : 1,
		isset( $_GET['cp_recusados'] ) ? absint( $_GET['cp_recusados'] ) : 0
	);
	if ( '' !== $titulo_sucesso ) :
		?>
		<div class="ac-status-banner green">
			<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
			<div><div class="ac-status-title"><?php echo esc_html( $titulo_sucesso ); ?></div></div>
		</div>
	<?php elseif ( '' !== cp_ac_documento_erro_texto( $erro ) ) : ?>
		<div class="ac-status-banner amber">
			<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível concluir o pedido', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber"><?php echo esc_html( cp_ac_documento_erro_texto( $erro ) ); ?></div></div>
		</div>
	<?php endif; ?>

	<?php cp_ac_render_documentos( $empresa_id ); ?>
	<?php
	// Extratos bancários e SAF-T têm vista própria — ver cp_ac_vista_extratos()
	// e cp_ac_vista_saft() mais abaixo.
}

// ─── Util: banner de sucesso/erro partilhado pelas vistas de resposta ──────
// (Documentos em Falta, Movimentos a Esclarecer, Extratos, SAF-T — todas
// submetem pelo mesmo admin-post `cp_pendente_responder`.)

function cp_ac_render_banner_resposta_pendente() {
	$sucesso = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
	$erro    = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	if ( 'resposta_enviada' === $sucesso ) :
		?>
		<div class="ac-status-banner green">
			<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Resposta enviada', 'contabilidade-pro' ); ?></div><div class="ac-status-sub green"><?php esc_html_e( 'A nossa equipa foi notificada.', 'contabilidade-pro' ); ?></div></div>
		</div>
		<?php
	elseif ( $erro ) :
		$tipo_recusado = 0 === strpos( $erro, 'anexo_tipo_' ) ? substr( $erro, strlen( 'anexo_tipo_' ) ) : '';
		?>
		<div class="ac-status-banner amber">
			<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível enviar', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber">
				<?php if ( $tipo_recusado ) : ?>
					<?php printf( /* translators: %s: tipos aceites, ex. "PDF ou Excel" */ esc_html__( 'Este tipo de ficheiro não é aceite aqui. Envie %s.', 'contabilidade-pro' ), esc_html( cp_ac_pendente_tipos_texto( $tipo_recusado ) ) ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Escreva uma resposta, anexe um ficheiro, ou marque "sem dados", e tente novamente.', 'contabilidade-pro' ); ?>
				<?php endif; ?>
			</div></div>
		</div>
		<?php
	endif;
}

// ─── Documentos em Falta ────────────────────────────────────────────────────

// Tabs por mês (links ?mes= a partir de $base, sem JS), em Documentos em
// Falta e em Documentos. $por_tratar diz que documentos contam no número da
// tab (null = todos); $badge é a classe da cor desse número; $ancora ("#id")
// é onde a página fica ao trocar de mês. Devolve só os
// documentos do mês aberto. Sem meses (Work antiga) não há tabs.
function cp_ac_render_tabs_meses( array $documentos, $base, $badge, $por_tratar = null, $ancora = '' ) {
	$pedido = isset( $_GET['mes'] ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : null;
	$tabs   = cp_ac_calc_tabs_meses( $documentos, $pedido, $por_tratar );
	if ( ! array_filter( $tabs['meses'] ) ) return $documentos;

	$nomes     = cp_ac_documento_nomes_meses();
	$ano_atual = current_time( 'Y' );
	?>
	<div class="ac-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Mês', 'contabilidade-pro' ); ?>" data-cp-tabs>
		<?php foreach ( $tabs['meses'] as $mes ) :
			$ativo  = $mes === $tabs['ativo'];
			$n      = $tabs['contagem'][ $mes ];
			$ano    = substr( $mes, 0, 4 );
			$nome   = '' === $mes ? __( 'Sem mês', 'contabilidade-pro' ) : $nomes[ (int) substr( $mes, 5 ) ] . ' ' . $ano;
			// Nome curto, para o ano inteiro caber na barra ("Out", "Dez 2025").
			$rotulo = '' === $mes ? $nome : mb_substr( $nome, 0, 3 ) . ( $ano === (string) $ano_atual ? '' : ' ' . $ano );
			?>
			<a class="ac-tab<?php echo $ativo ? ' is-on' : ''; ?>" role="tab" title="<?php echo esc_attr( $nome ); ?>" aria-selected="<?php echo $ativo ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'mes', '' === $mes ? 'sem' : $mes, $base ) . $ancora ); ?>">
				<?php echo esc_html( $rotulo ); ?>
				<?php if ( $n ) : ?><span class="ac-badge <?php echo esc_attr( $badge ); ?>" style="margin-left:6px;padding:0"><?php echo esc_html( $n ); ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
	<script>
	(function () {
		// Em ecrãs estreitos a barra desliza: a tab aberta fica sempre à vista.
		var barra = document.querySelector('[data-cp-tabs]'), tab = barra && barra.querySelector('.is-on');
		if (tab) barra.scrollLeft = tab.offsetLeft - barra.offsetLeft - 16;
	})();
	</script>
	<?php
	return array_values( array_filter( $documentos, function ( $doc ) use ( $tabs ) {
		return cp_ac_calc_mes_valido( isset( $doc['mes'] ) ? $doc['mes'] : '' ) === $tabs['ativo'];
	} ) );
}

// Tabela de Documentos em Falta (2026-11-27): Data | NIF | Nome | Tipo
// documento | Nº documento | Valor, mais uma coluna com o estado/ação que
// `$celula` desenha (recebe o documento). Por data, mais antigo primeiro.
// Um documento sem estes campos (Work antiga, ou pedido escrito à mão)
// mostra o título na coluna Nome.
function cp_ac_render_tabela_documentos_falta( array $documentos, callable $celula ) {
	usort( $documentos, function ( $a, $b ) {
		// Sem data (pedido escrito à mão) vai para o fim.
		return strcmp( ! empty( $a['data'] ) ? $a['data'] : '9999', ! empty( $b['data'] ) ? $b['data'] : '9999' );
	} );
	$traco = '<span style="color:var(--ac-muted)">—</span>';
	?>
	<div style="overflow-x:auto">
		<table class="ac-table ac-df-tabela">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'NIF', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Tipo documento', 'contabilidade-pro' ); ?></th>
					<th><?php esc_html_e( 'Nº documento', 'contabilidade-pro' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $documentos as $doc ) :
					$data  = ! empty( $doc['data'] ) ? date_i18n( 'd/m/Y', strtotime( $doc['data'] ) ) : '';
					$nome  = ! empty( $doc['nome'] ) ? $doc['nome'] : ( isset( $doc['titulo'] ) ? $doc['titulo'] : '' );
					$valor = isset( $doc['valor'] ) && null !== $doc['valor'] ? number_format_i18n( (float) $doc['valor'], 2 ) . ' €' : '';
					?>
					<tr>
						<td style="white-space:nowrap"><?php echo '' !== $data ? esc_html( $data ) : $traco; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td style="white-space:nowrap"><?php echo ! empty( $doc['nif'] ) ? esc_html( $doc['nif'] ) : $traco; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td style="font-weight:600"><?php echo esc_html( $nome ); ?></td>
						<td><?php echo ! empty( $doc['tipo'] ) ? esc_html( $doc['tipo'] ) : $traco; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td style="white-space:nowrap"><?php echo ! empty( $doc['numero'] ) ? esc_html( $doc['numero'] ) : $traco; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td style="text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums"><?php echo '' !== $valor ? esc_html( $valor ) : $traco; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td style="text-align:right;white-space:nowrap"><?php $celula( $doc ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<style>
		.ac-df-tabela th,.ac-df-tabela td{padding:11px 10px}
		.ac-df-tabela th:first-child,.ac-df-tabela td:first-child{padding-left:0}
		.ac-df-tabela th:last-child,.ac-df-tabela td:last-child{padding-right:0}
		.ac-df-tabela .ac-req-form{flex-wrap:nowrap;justify-content:flex-end}
	</style>
	<?php
}

// Três filtros (?filtro=, sem JS), como em Movimentos a Esclarecer
// (2026-11-27): "Pendentes" (omissão: só o que falta enviar, com o
// formulário), "Tratados" (o que o cliente já enviou e ainda está nos
// pedidos, mais os que a Work mandou como enviados/recebidos/tratados) e
// "Todos". Dentro de cada um, uma tab por mês só com os meses do filtro.
function cp_ac_vista_documentos_em_falta() {
	$empresa_id = cp_ac_empresa_ativa();
	$filtro     = cp_ac_calc_filtro_documentos( isset( $_GET['filtro'] ) ? sanitize_key( wp_unslash( $_GET['filtro'] ) ) : '' );
	$pendentes  = [];
	$tratados   = [];
	foreach ( cp_ac_pendentes_documentos( $empresa_id ) as $doc ) {
		$resposta = cp_ac_pendente_resposta_do_item( $empresa_id, $doc['id'] );
		if ( $resposta ) {
			// Enviado pelo cliente; a Work ainda não o tirou dos pedidos.
			$tratados[ $doc['id'] ] = [ 'estado' => 'enviado', 'anexo_url' => isset( $resposta['anexo_url'] ) ? $resposta['anexo_url'] : '' ] + $doc;
		} else {
			$pendentes[] = [ 'estado' => 'pendente' ] + $doc;
		}
	}
	foreach ( cp_ac_pendentes_documentos_tratados( $empresa_id ) as $doc ) {
		if ( ! isset( $tratados[ $doc['id'] ] ) ) $tratados[ $doc['id'] ] = $doc;
	}
	$tratados   = array_values( $tratados );
	$filtros    = [
		'pendentes' => [ __( 'Pendentes', 'contabilidade-pro' ), $pendentes, 'ac-badge-amber', __( 'Sem pedidos pendentes de momento.', 'contabilidade-pro' ) ],
		'tratados'  => [ __( 'Tratados', 'contabilidade-pro' ), $tratados, 'ac-badge-green', __( 'Ainda não há documentos tratados.', 'contabilidade-pro' ) ],
		'todos'     => [ __( 'Todos', 'contabilidade-pro' ), array_merge( $pendentes, $tratados ), 'ac-badge-blue', __( 'Sem documentos em falta de momento.', 'contabilidade-pro' ) ],
	];
	$url_filtro = function ( $chave ) {
		return 'pendentes' === $chave ? cp_ac_vista_url( 'documentos_em_falta' ) : add_query_arg( 'filtro', $chave, cp_ac_vista_url( 'documentos_em_falta' ) );
	};
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Documentos em Falta', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Documentos que a nossa equipa precisa especificamente de si.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<div class="ac-bo-filtros" role="tablist" style="margin-top:18px">
		<?php foreach ( $filtros as $chave => list( $rotulo, $lista, $cor ) ) :
			$ativo = $chave === $filtro;
			$n     = count( $lista );
			?>
			<a class="ac-bo-chip<?php echo $ativo ? ' is-on' : ''; ?>" role="tab" aria-selected="<?php echo $ativo ? 'true' : 'false'; ?>" href="<?php echo esc_url( $url_filtro( $chave ) ); ?>">
				<?php echo esc_html( $rotulo ); ?>
				<?php if ( $n ) : ?><span class="ac-badge <?php echo esc_attr( $cor ); ?>" style="margin-left:8px;padding:1px 7px"><?php echo esc_html( $n ); ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="ac-card" style="padding:22px">
		<?php $documentos = cp_ac_render_tabs_meses( $filtros[ $filtro ][1], $url_filtro( $filtro ), $filtros[ $filtro ][2] ); ?>
		<?php if ( empty( $documentos ) ) : ?>
			<div style="color:var(--ac-muted);font-size:13px"><?php echo esc_html( $filtros[ $filtro ][3] ); ?></div>
		<?php else : ?>
			<?php cp_ac_render_tabela_documentos_falta( $documentos, 'cp_ac_render_celula_documento_falta' ); ?>
		<?php endif; ?>
	</div>
	<?php
}

// Última coluna da tabela: formulário para carregar (pendente) ou o estado.
function cp_ac_render_celula_documento_falta( $doc ) {
	if ( 'pendente' === $doc['estado'] ) : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ac-req-form" data-cp-drop>
			<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
			<input type="hidden" name="action" value="cp_pendente_responder">
			<input type="hidden" name="tipo" value="documento">
			<input type="hidden" name="item_id" value="<?php echo esc_attr( $doc['id'] ); ?>">
			<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'Pendente', 'contabilidade-pro' ); ?></span>
			<?php cp_ac_render_anexo_pendente( 'documento', true ); ?>
			<button class="ac-btn ac-btn-primary" type="submit" style="padding:7px 12px"><?php esc_html_e( 'Carregar', 'contabilidade-pro' ); ?></button>
		</form>
		<?php
		return;
	endif;
	$rotulo_estado = [
		'enviado'  => __( 'Enviado', 'contabilidade-pro' ),
		'recebido' => __( 'Recebido', 'contabilidade-pro' ),
		'tratado'  => __( 'Tratado', 'contabilidade-pro' ),
	][ $doc['estado'] ];
	$dica_estado = [
		'enviado'  => __( 'Enviou este documento — a nossa equipa vai tratá-lo.', 'contabilidade-pro' ),
		'recebido' => __( 'A contabilidade já tem este documento.', 'contabilidade-pro' ),
		'tratado'  => __( 'Documento já tratado na contabilidade.', 'contabilidade-pro' ),
	][ $doc['estado'] ];
	?>
	<div style="display:inline-flex;align-items:center;gap:10px">
		<span class="ac-badge <?php echo 'enviado' === $doc['estado'] ? 'ac-badge-blue' : 'ac-badge-green'; ?>" title="<?php echo esc_attr( $dica_estado ); ?>"><?php echo esc_html( $rotulo_estado ); ?></span>
		<?php if ( ! empty( $doc['anexo_url'] ) ) : ?><a class="ac-btn ac-btn-ghost" style="padding:7px 12px" href="<?php echo esc_url( $doc['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></a><?php endif; ?>
	</div>
	<?php
}

// ─── Movimentos a Esclarecer ────────────────────────────────────────────────

function cp_ac_vista_movimentos_a_esclarecer() {
	$empresa_id = cp_ac_empresa_ativa();
	// Tabs por mês (2026-11-27): o mês sai da data do movimento.
	$movimentos = array_map( function ( $mov ) {
		$mov['mes'] = substr( (string) $mov['data'], 0, 7 );
		return $mov;
	}, cp_ac_pendentes_movimentos( $empresa_id ) );
	$e_por_tratar = function ( $item ) use ( $empresa_id ) {
		return ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
	};
	// Filtro Pendentes (omissão) / Respondidos / Todos (2026-11-27); as tabs
	// por mês só mostram os meses com movimentos do filtro escolhido.
	$filtro       = cp_ac_calc_filtro_movimentos( isset( $_GET['filtro'] ) ? sanitize_key( wp_unslash( $_GET['filtro'] ) ) : '' );
	$pendentes    = array_values( array_filter( $movimentos, $e_por_tratar ) );
	$respondidos  = array_values( array_filter( $movimentos, function ( $m ) use ( $e_por_tratar ) { return ! $e_por_tratar( $m ); } ) );
	$base         = 'pendentes' === $filtro ? cp_ac_vista_url( 'movimentos_a_esclarecer' ) : add_query_arg( 'filtro', $filtro, cp_ac_vista_url( 'movimentos_a_esclarecer' ) );
	$filtros      = [
		'pendentes'   => [ __( 'Pendentes', 'contabilidade-pro' ), count( $pendentes ), 'ac-badge-amber' ],
		'respondidos' => [ __( 'Respondidos', 'contabilidade-pro' ), count( $respondidos ), 'ac-badge-green' ],
		'todos'       => [ __( 'Todos', 'contabilidade-pro' ), count( $movimentos ), 'ac-badge-blue' ],
	];
	$vazio        = [
		'pendentes'   => __( 'Não há movimentos por responder.', 'contabilidade-pro' ),
		'respondidos' => __( 'Ainda não respondeu a nenhum movimento.', 'contabilidade-pro' ),
		'todos'       => __( 'Sem movimentos a esclarecer de momento.', 'contabilidade-pro' ),
	];
	$movimentos   = 'pendentes' === $filtro ? $pendentes : ( 'respondidos' === $filtro ? $respondidos : $movimentos );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Movimentos a Esclarecer', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Linhas do extrato bancário — pode responder por texto, por anexo, ou ambos.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<div class="ac-bo-filtros" role="tablist" style="margin-top:18px">
		<?php foreach ( $filtros as $chave => list( $rotulo, $n, $cor ) ) :
			$ativo = $chave === $filtro;
			$url   = 'pendentes' === $chave ? cp_ac_vista_url( 'movimentos_a_esclarecer' ) : add_query_arg( 'filtro', $chave, cp_ac_vista_url( 'movimentos_a_esclarecer' ) );
			?>
			<a class="ac-bo-chip<?php echo $ativo ? ' is-on' : ''; ?>" role="tab" aria-selected="<?php echo $ativo ? 'true' : 'false'; ?>" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $rotulo ); ?>
				<?php if ( $n ) : ?><span class="ac-badge <?php echo esc_attr( $cor ); ?>" style="margin-left:8px;padding:1px 7px"><?php echo esc_html( $n ); ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="ac-card" style="padding:22px">
		<?php $movimentos = cp_ac_render_tabs_meses( $movimentos, $base, 'pendentes' === $filtro ? 'ac-badge-amber' : $filtros[ $filtro ][2] ); ?>
		<?php if ( empty( $movimentos ) ) : ?>
			<div style="color:var(--ac-muted);font-size:13px"><?php echo esc_html( $vazio[ $filtro ] ); ?></div>
		<?php else :
			// Por data, mais antigo primeiro (como Documentos em Falta).
			usort( $movimentos, function ( $a, $b ) { return strcmp( (string) $a['data'], (string) $b['data'] ); } );
			?>
			<div style="overflow-x:auto">
				<table class="ac-table ac-mv-tabela">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Descrição', 'contabilidade-pro' ); ?></th>
							<th style="text-align:right"><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
							<th style="width:50%"><?php esc_html_e( 'Justificação', 'contabilidade-pro' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $movimentos as $mov ) :
							$resposta  = cp_ac_pendente_resposta_do_item( $empresa_id, $mov['id'] );
							$valor_cls = $mov['valor'] < 0 ? 'ac-mv-neg' : 'ac-mv-pos';
							$valor_txt = ( $mov['valor'] < 0 ? '−' : '+' ) . number_format_i18n( abs( $mov['valor'] ), 2 ) . ' €';
							$data      = preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $mov['data'] ) ? date_i18n( 'd/m/Y', strtotime( substr( $mov['data'], 0, 10 ) ) ) : $mov['data'];
							// A pergunta genérica da Work não se mostra; uma pergunta
							// escrita pela equipa fica como texto de ajuda da caixa.
							$pergunta  = trim( (string) ( $mov['pergunta'] ?? '' ) );
							$ajuda     = ( '' === $pergunta || 'Podes esclarecer este movimento?' === $pergunta ) ? __( 'Escreva a justificação deste movimento…', 'contabilidade-pro' ) : $pergunta;
							?>
							<tr>
								<td style="white-space:nowrap"><?php echo esc_html( $data ); ?></td>
								<td class="ac-mv-line" style="margin:0"><?php echo esc_html( $mov['descricao'] ); ?></td>
								<td class="<?php echo esc_attr( $valor_cls ); ?>" style="text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;font-weight:600"><?php echo esc_html( $valor_txt ); ?></td>
								<?php if ( $resposta ) : ?>
									<td style="font-size:13px">
										<?php echo esc_html( $resposta['texto'] ); ?>
										<?php if ( ! empty( $resposta['anexo_url'] ) ) : ?>
											<a class="ac-attach-chip" style="margin-left:8px" href="<?php echo esc_url( $resposta['anexo_url'] ); ?>" target="_blank" rel="noopener">
												<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 12.5 15 6a3 3 0 0 1 4.2 4.2L11 18.5a4.2 4.2 0 0 1-6-6L13 5"></path></svg>
												<?php echo esc_html( $resposta['anexo_nome'] ? $resposta['anexo_nome'] : __( 'Anexo', 'contabilidade-pro' ) ); ?>
											</a>
										<?php endif; ?>
									</td>
									<td style="text-align:right;white-space:nowrap"><span class="ac-badge ac-badge-green"><?php esc_html_e( 'Respondido', 'contabilidade-pro' ); ?></span></td>
								<?php else : ?>
									<td colspan="2">
										<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ac-mv-linha-form" data-cp-drop>
											<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
											<input type="hidden" name="action" value="cp_pendente_responder">
											<input type="hidden" name="tipo" value="movimento">
											<input type="hidden" name="item_id" value="<?php echo esc_attr( $mov['id'] ); ?>">
											<textarea name="texto" rows="1" aria-label="<?php esc_attr_e( 'Justificação', 'contabilidade-pro' ); ?>" placeholder="<?php echo esc_attr( $ajuda ); ?>"></textarea>
											<div class="ac-anexo-grupo"><?php cp_ac_render_anexo_pendente( 'movimento' ); ?></div>
											<button class="ac-btn ac-btn-primary" type="submit"><?php esc_html_e( 'Enviar', 'contabilidade-pro' ); ?></button>
										</form>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<style>
				.ac-mv-tabela th,.ac-mv-tabela td{padding:7px 10px;vertical-align:middle}
				.ac-mv-tabela th:first-child,.ac-mv-tabela td:first-child{padding-left:0}
				.ac-mv-tabela th:last-child,.ac-mv-tabela td:last-child{padding-right:0}
				.ac-mv-tabela td.ac-mv-line{font-size:12.5px;min-width:170px}
				.ac-mv-linha-form{display:flex;align-items:center;gap:6px;margin:0}
				.ac-mv-linha-form textarea{flex:1 1 200px;min-width:150px;max-width:420px;height:30px;min-height:30px;max-height:96px;padding:5px 10px;box-sizing:border-box;font-family:'Public Sans',sans-serif;font-size:12.5px;line-height:1.45;color:var(--ac-text);background:var(--ac-bg);border:1px solid var(--ac-line);border-radius:8px;resize:none;overflow:hidden;transition:border-color .15s,box-shadow .15s,background-color .15s}
				.ac-mv-linha-form textarea::placeholder{color:var(--ac-muted);opacity:.8}
				.ac-mv-linha-form textarea:hover{border-color:var(--ac-blue)}
				.ac-mv-linha-form textarea:focus{outline:none;background:var(--ac-surface);border-color:var(--ac-blue);box-shadow:0 0 0 3px rgba(126,144,181,.22)}
				:root[data-theme="dark"] .ac-mv-linha-form textarea:focus{box-shadow:0 0 0 3px rgba(155,174,218,.2)}
				.ac-mv-linha-form .ac-anexo-grupo{flex-wrap:nowrap;gap:6px;margin-left:auto}
				.ac-mv-linha-form .ac-anexo-btn{padding:5px 10px;font-size:12px;font-weight:500;gap:6px;color:var(--ac-muted);border-radius:8px}
				.ac-mv-linha-form .ac-anexo-btn:hover{color:var(--ac-text)}
				.ac-mv-linha-form .ac-anexo-nome{max-width:130px;font-size:12px}
				.ac-mv-linha-form .ac-btn-primary{padding:6px 12px;font-size:12.5px;border-radius:8px;white-space:nowrap}
				@media (max-width:760px){.ac-mv-linha-form{flex-wrap:wrap}.ac-mv-linha-form textarea{flex-basis:100%;max-width:none}}
			</style>
			<script>
			(function () {
				// A caixa cresce com o texto (até 4 linhas) e volta a uma linha quando vazia.
				document.querySelectorAll('.ac-mv-linha-form textarea').forEach(function (t) {
					function ajustar() { t.style.height = '30px'; if (t.scrollHeight > 30) t.style.height = Math.min(t.scrollHeight + 2, 96) + 'px'; t.style.overflowY = t.scrollHeight > 96 ? 'auto' : 'hidden'; }
					t.addEventListener('input', ajustar);
				});
			})();
			</script>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Extratos ───────────────────────────────────────────────────────────────
// Grelha mês × conta, numa tab por ano (o corrente é a principal). Cada
// célula é um item de cp_ac_extratos() (ou está em branco, se essa conta
// ainda não tinha pedido nesse mês). "Sem movimento" pode já vir marcado
// pela Work (estado 'sem_dados') ou ser marcado aqui pelo cliente, como
// alternativa a carregar o ficheiro; 'recebido' = a Work já o tem.

// Item de Extratos/SAF-T à espera do cliente: pedido pela Work e ainda sem
// resposta dele.
function cp_ac_item_checklist_por_tratar( $empresa_id, $item ) {
	return 'pendente' === $item['estado'] && ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
}

// Tabs por ano (links ?ano=, sem JavaScript). Devolve o ano ativo.
function cp_ac_render_tabs_anos( $empresa_id, array $itens, $vista ) {
	$tabs = cp_ac_calc_tabs_anos(
		$itens,
		(int) current_time( 'Y' ),
		isset( $_GET['ano'] ) ? absint( $_GET['ano'] ) : null,
		function ( $item ) use ( $empresa_id ) { return cp_ac_item_checklist_por_tratar( $empresa_id, $item ); }
	);
	?>
	<div class="ac-bo-filtros" role="tablist" style="margin-top:18px">
		<?php foreach ( $tabs['anos'] as $ano ) :
			$ativo = $ano === $tabs['ativo'];
			$n     = $tabs['por_tratar'][ $ano ];
			?>
			<a class="ac-bo-chip<?php echo $ativo ? ' is-on' : ''; ?>" role="tab" aria-selected="<?php echo $ativo ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'ano', $ano, cp_ac_vista_url( $vista ) ) ); ?>">
				<?php echo esc_html( $ano ); ?>
				<?php if ( $n ) : ?><span class="ac-badge ac-badge-amber" style="margin-left:8px;padding:1px 7px" title="<?php esc_attr_e( 'Por enviar', 'contabilidade-pro' ); ?>"><?php echo esc_html( $n ); ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return $tabs['ativo'];
}

function cp_ac_vista_extratos() {
	$empresa_id = cp_ac_empresa_ativa();
	$itens      = cp_ac_extratos( $empresa_id );
	$contas     = cp_ac_contas_bancarias( $empresa_id );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Extratos', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Um extrato por mês, por conta bancária.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<?php if ( empty( $itens ) || empty( $contas ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem extratos pedidos de momento', 'contabilidade-pro' ), __( 'Os extratos que a Entre Números precisar vão aparecer aqui, mês a mês.', 'contabilidade-pro' ) ); ?>
	<?php else :
		$ano_ativo = cp_ac_render_tabs_anos( $empresa_id, $itens, 'extratos' );
		// Agrupar por mês, mais recente primeiro; dentro de cada mês, uma
		// coluna por conta (na ordem em que as contas foram vistas).
		$por_mes = [];
		foreach ( $itens as $item ) {
			if ( 0 !== strpos( $item['mes'], $ano_ativo . '-' ) ) continue;
			$por_mes[ $item['mes'] ][ $item['conta_id'] ] = $item;
		}
		krsort( $por_mes );
		?>
		<?php if ( empty( $por_mes ) ) : ?>
			<?php /* translators: %d: ano */ cp_ac_vazio( sprintf( __( 'Nada em %d', 'contabilidade-pro' ), $ano_ativo ), __( 'Ainda não há extratos deste ano. Os meses vão aparecer aqui quando a Entre Números os pedir.', 'contabilidade-pro' ) ); ?>
		<?php else : ?>
		<div class="ac-table-wrap">
			<table class="ac-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Mês', 'contabilidade-pro' ); ?></th>
						<?php foreach ( $contas as $conta_nome ) : ?>
							<th><?php echo esc_html( $conta_nome ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $por_mes as $mes => $itens_do_mes ) : ?>
						<tr>
							<td style="font-weight:600;white-space:nowrap"><?php echo esc_html( $mes ); ?></td>
							<?php foreach ( $contas as $conta_id => $conta_nome ) : ?>
								<td><?php cp_ac_render_celula_checklist( empty( $itens_do_mes[ $conta_id ] ) ? null : $itens_do_mes[ $conta_id ], $empresa_id, 'extrato' ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php
}

// ─── SAF-T ──────────────────────────────────────────────────────────────────
// Mesma lógica de Extratos, mas um item por mês (sem conta).

function cp_ac_vista_saft() {
	$empresa_id = cp_ac_empresa_ativa();
	$itens      = cp_ac_saft( $empresa_id );
	usort( $itens, function ( $a, $b ) { return strcmp( $b['mes'], $a['mes'] ); } );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'SAF-T', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'O ficheiro SAF-T da sua contabilidade, mês a mês.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<?php if ( empty( $itens ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem SAF-T pedido de momento', 'contabilidade-pro' ), __( 'Os pedidos de SAF-T da Entre Números vão aparecer aqui, mês a mês.', 'contabilidade-pro' ) ); ?>
	<?php else :
		$ano_ativo = cp_ac_render_tabs_anos( $empresa_id, $itens, 'saft' );
		$do_ano    = array_filter( $itens, function ( $item ) use ( $ano_ativo ) {
			return 0 === strpos( $item['mes'], $ano_ativo . '-' );
		} );
		?>
		<?php if ( empty( $do_ano ) ) : ?>
			<?php /* translators: %d: ano */ cp_ac_vazio( sprintf( __( 'Nada em %d', 'contabilidade-pro' ), $ano_ativo ), __( 'Ainda não há SAF-T deste ano. Os meses vão aparecer aqui quando a Entre Números os pedir.', 'contabilidade-pro' ) ); ?>
		<?php else : ?>
		<div class="ac-card" style="padding:22px">
			<?php foreach ( $do_ano as $item ) : ?>
				<div class="ac-req-row">
					<div style="font-size:14px;font-weight:600"><?php echo esc_html( $item['mes'] ); ?></div>
					<?php cp_ac_render_celula_checklist( $item, $empresa_id, 'saft' ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php
}

// ─── Util: anexo de um pendente com arrastar e largar (2026-11-27) ────────
// Botão "Escolher ou arrastar" com o input nativo escondido lá dentro e o
// nome do ficheiro escolhido ao lado. O formulário inteiro (data-cp-drop)
// aceita um ficheiro largado; o que não cabe no `accept` fica de fora com
// aviso. Usado nos Documentos em falta, nos Movimentos a esclarecer e na
// grelha de Extratos/SAF-T. O script imprime-se uma vez por página.

function cp_ac_render_anexo_pendente( $tipo, $obrigatorio = false ) {
	?>
	<label class="ac-btn ac-btn-ghost ac-anexo-btn" title="<?php esc_attr_e( 'Também pode arrastar o ficheiro para aqui', 'contabilidade-pro' ); ?>">
		<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 12.5 15 6a3 3 0 0 1 4.2 4.2L11 18.5a4.2 4.2 0 0 1-6-6L13 5"></path></svg>
		<span data-cp-drop-rotulo><?php esc_html_e( 'Escolher ou arrastar', 'contabilidade-pro' ); ?></span>
		<input type="file" name="anexo" class="ac-anexo-input" accept="<?php echo esc_attr( cp_ac_pendente_accept( $tipo ) ); ?>" <?php echo $obrigatorio ? 'data-cp-drop-obrigatorio' : ''; ?>>
	</label>
	<span class="ac-anexo-nome" data-cp-drop-nome hidden></span>
	<?php
	cp_ac_render_anexo_pendente_script();
}

function cp_ac_render_anexo_pendente_script() {
	static $impresso = false;
	if ( $impresso ) return;
	$impresso = true;
	$txt = [
		'rotulo'   => __( 'Escolher ou arrastar', 'contabilidade-pro' ),
		'trocar'   => __( 'Trocar', 'contabilidade-pro' ),
		'formato'  => __( 'Este tipo de ficheiro não é aceite aqui.', 'contabilidade-pro' ),
		'um'       => __( 'Um ficheiro de cada vez — ficou o primeiro.', 'contabilidade-pro' ),
		'nenhum'   => __( 'Escolha ou arraste o ficheiro primeiro.', 'contabilidade-pro' ),
	];
	?>
	<script>
	(function () {
		var TXT = <?php echo wp_json_encode( $txt ); ?>;
		function aceite(input, f) {
			var lista = (input.getAttribute('accept') || '').toLowerCase().split(',').map(function (s) { return s.trim(); }).filter(Boolean);
			if (!lista.length) return true;
			var nome = f.name.toLowerCase();
			return lista.some(function (e) { return e.charAt(0) === '.' ? nome.slice(-e.length) === e : f.type === e; });
		}
		// O script é impresso dentro do primeiro formulário: só liga quando a página inteira já existe.
		function iniciar() { document.querySelectorAll('form[data-cp-drop]').forEach(ligar); }
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
		function ligar(form) {
			var input = form.querySelector('.ac-anexo-input');
			var nome = form.querySelector('[data-cp-drop-nome]');
			var rotulo = form.querySelector('[data-cp-drop-rotulo]');
			if (!input || !nome) return;
			function mostrar(aviso) {
				var f = input.files[0];
				nome.hidden = !f && !aviso;
				nome.classList.toggle('is-aviso', !!aviso);
				nome.textContent = aviso || (f ? f.name : '');
				rotulo.textContent = f ? TXT.trocar : TXT.rotulo;
				nome.title = nome.textContent;
			}
			input.addEventListener('change', function () {
				if (input.files[0] && !aceite(input, input.files[0])) { input.value = ''; mostrar(TXT.formato); return; }
				mostrar();
			});
			['dragenter', 'dragover'].forEach(function (ev) {
				form.addEventListener(ev, function (e) { e.preventDefault(); form.classList.add('is-arrastar'); });
			});
			form.addEventListener('dragleave', function (e) { if (!form.contains(e.relatedTarget)) form.classList.remove('is-arrastar'); });
			form.addEventListener('drop', function (e) {
				e.preventDefault();
				form.classList.remove('is-arrastar');
				var fs = e.dataTransfer && e.dataTransfer.files;
				if (!fs || !fs.length) return;
				if (!aceite(input, fs[0])) { mostrar(TXT.formato); return; }
				var dt = new DataTransfer();
				dt.items.add(fs[0]);
				input.files = dt.files;
				mostrar();
				if (fs.length > 1) nome.textContent = nome.title = fs[0].name + ' · ' + TXT.um;
			});
			form.addEventListener('submit', function (e) {
				// "Sem dados este mês" / "Não houve movimento" não precisam de ficheiro.
				if (e.submitter && e.submitter.name === 'sem_dados') return;
				if (input.hasAttribute('data-cp-drop-obrigatorio') && !input.files.length) { e.preventDefault(); mostrar(TXT.nenhum); }
			});
		}
		// Largar um ficheiro fora de uma zona não o abre no navegador.
		window.addEventListener('dragover', function (e) { e.preventDefault(); });
		window.addEventListener('drop', function (e) { if (!e.target.closest || !e.target.closest('form[data-cp-drop], [data-cp-doc-drop]')) e.preventDefault(); });
	})();
	</script>
	<?php
}

// ─── Util: célula/linha de estado partilhada por Extratos e SAF-T ─────────
// Três estados possíveis: 'sem_dados' (já veio assim da Work, ou o cliente
// marcou), 'enviado' (o cliente já respondeu com ficheiro) e, por omissão,
// pendente (mostra o formulário de resposta).

function cp_ac_render_celula_checklist( $item, $empresa_id, $tipo ) {
	if ( ! $item ) {
		echo '<span style="color:var(--ac-muted);font-size:12.5px">—</span>';
		return;
	}
	$resposta = cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
	$estado   = $resposta && 'recebido' !== $item['estado'] ? $resposta['estado'] : $item['estado'];

	if ( 'recebido' === $estado ) : ?>
		<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Recebido', 'contabilidade-pro' ); ?></span>
	<?php elseif ( 'sem_dados' === $estado ) : ?>
		<span class="ac-badge ac-badge-blue"><?php esc_html_e( 'Sem dados este mês', 'contabilidade-pro' ); ?></span>
	<?php elseif ( $resposta ) : ?>
		<div style="display:flex;align-items:center;gap:10px">
			<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Enviado', 'contabilidade-pro' ); ?></span>
			<?php if ( ! empty( $resposta['anexo_url'] ) ) : ?><a class="ac-btn ac-btn-ghost" style="padding:6px 12px" href="<?php echo esc_url( $resposta['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></a><?php endif; ?>
		</div>
	<?php else : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap" data-cp-drop>
			<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
			<input type="hidden" name="action" value="cp_pendente_responder">
			<input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo ); ?>">
			<input type="hidden" name="item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'Pendente', 'contabilidade-pro' ); ?></span>
			<?php cp_ac_render_anexo_pendente( $tipo ); ?>
			<button class="ac-btn ac-btn-primary" type="submit" style="padding:6px 12px"><?php esc_html_e( 'Carregar', 'contabilidade-pro' ); ?></button>
			<button class="ac-btn ac-btn-ghost" type="submit" name="sem_dados" value="1" style="padding:6px 12px"><?php echo 'extrato' === $tipo ? esc_html__( 'Não houve movimento', 'contabilidade-pro' ) : esc_html__( 'Sem SAF-T este mês', 'contabilidade-pro' ); ?></button>
		</form>
	<?php endif;
}

// ─── Mapas de Gestão ────────────────────────────────────────────────────────

function cp_ac_vista_mapas_gestao() {
	$empresa_id = cp_ac_empresa_ativa();
	$mapas      = cp_ac_mapas_gestao( $empresa_id );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Mapas de Gestão', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Acompanhamento financeiro construído à medida do seu negócio.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( empty( $mapas ) ) : ?>
		<?php cp_ac_vazio( __( 'Ainda não há mapas publicados', 'contabilidade-pro' ), __( 'Os mapas de gestão preparados pela Entre Números vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
	<div class="ac-card" style="padding:22px">
		<?php foreach ( $mapas as $mapa ) :
			$publicado = $mapa['publicado_em'] ? mysql2date( get_option( 'date_format' ), $mapa['publicado_em'] ) : '';
			?>
			<div class="ac-report-row">
				<div>
					<div style="font-size:14px;font-weight:600"><?php echo esc_html( $mapa['titulo'] ); ?></div>
					<?php if ( $publicado ) : ?><div style="font-size:12px;color:var(--ac-muted);margin-top:2px"><?php printf( /* translators: %s: data */ esc_html__( 'Publicado a %s', 'contabilidade-pro' ), esc_html( $publicado ) ); ?></div><?php endif; ?>
				</div>
				<?php if ( ! empty( $mapa['anexo_url'] ) ) : ?>
					<a class="ac-btn ac-btn-ghost" href="<?php echo esc_url( $mapa['anexo_url'] ); ?>" target="_blank" rel="noopener">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10M8 10.5 12 14l4-3.5"></path><path d="M5 17v1.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V17"></path></svg>
						<?php esc_html_e( 'Descarregar', 'contabilidade-pro' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php
}

// ─── Calendário Fiscal ──────────────────────────────────────────────────────

function cp_ac_vista_calendario_fiscal() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Calendário', 'contabilidade-pro' ); ?></h1>
	</div>

	<?php cp_ac_vazio( __( 'Sem prazos registados', 'contabilidade-pro' ), __( 'Os próximos prazos e obrigações fiscais da sua empresa vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Mensagens ──────────────────────────────────────────────────────────────
// Sem entrada no menu lateral, de propósito (como Lembretes) — só
// acessível pelo link no dropdown do utilizador ou pelo atalho "Nova
// conversa" do "+" no Dashboard. Ver cp_ac_render_mensagens_pagina() em
// inc/area-cliente-mensagens.php.

function cp_ac_vista_mensagens() {
	$empresa_id = cp_ac_empresa_ativa();
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Mensagens', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Converse com a Entre Números — uma conversa por assunto.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_mensagens_pagina( $empresa_id ); ?>
	<?php
}

// ─── Util: URL de uma vista ─────────────────────────────────────────────────

function cp_ac_vista_url( $chave ) {
	return add_query_arg( 'vista', $chave, get_permalink() );
}
