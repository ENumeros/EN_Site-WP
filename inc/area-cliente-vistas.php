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
						return 'sem_dados' !== $item['estado'] && ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
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
						return 'sem_dados' !== $item['estado'] && ! cp_ac_pendente_resposta_do_item( $empresa_id, $item['id'] );
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

function cp_ac_vista_documentos_em_falta() {
	$empresa_id = cp_ac_empresa_ativa();
	$documentos = cp_ac_pendentes_documentos( $empresa_id );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Documentos em Falta', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Documentos que a nossa equipa precisa especificamente de si.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div></div>
			<?php if ( ! empty( $documentos ) ) : ?><span class="ac-badge ac-badge-amber"><?php echo esc_html( count( $documentos ) ); ?> <?php esc_html_e( 'pendentes', 'contabilidade-pro' ); ?></span><?php endif; ?>
		</div>
		<?php if ( empty( $documentos ) ) : ?>
			<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Sem pedidos pendentes de momento.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<?php foreach ( $documentos as $doc ) :
				$resposta = cp_ac_pendente_resposta_do_item( $empresa_id, $doc['id'] );
				?>
				<div class="ac-req-row">
					<div>
						<div style="font-size:14px;font-weight:600"><?php echo esc_html( $doc['titulo'] ); ?></div>
						<?php if ( ! empty( $doc['pedido_em'] ) ) : ?><div style="font-size:12px;color:var(--ac-muted);margin-top:2px"><?php printf( /* translators: %s: data */ esc_html__( 'Pedido a %s', 'contabilidade-pro' ), esc_html( $doc['pedido_em'] ) ); ?></div><?php endif; ?>
					</div>
					<?php if ( $resposta ) : ?>
						<div style="display:flex;align-items:center;gap:10px">
							<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Enviado', 'contabilidade-pro' ); ?></span>
							<?php if ( ! empty( $resposta['anexo_url'] ) ) : ?><a class="ac-btn ac-btn-ghost" style="padding:7px 12px" href="<?php echo esc_url( $resposta['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></a><?php endif; ?>
						</div>
					<?php else : ?>
						<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;align-items:center;gap:10px">
							<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
							<input type="hidden" name="action" value="cp_pendente_responder">
							<input type="hidden" name="tipo" value="documento">
							<input type="hidden" name="item_id" value="<?php echo esc_attr( $doc['id'] ); ?>">
							<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'Pendente', 'contabilidade-pro' ); ?></span>
							<input type="file" name="anexo" required accept="<?php echo esc_attr( cp_ac_pendente_accept( 'documento' ) ); ?>" style="font-size:12.5px;max-width:200px">
							<button class="ac-btn ac-btn-primary" type="submit" style="padding:7px 12px"><?php esc_html_e( 'Carregar', 'contabilidade-pro' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Movimentos a Esclarecer ────────────────────────────────────────────────

function cp_ac_vista_movimentos_a_esclarecer() {
	$empresa_id = cp_ac_empresa_ativa();
	$movimentos = cp_ac_pendentes_movimentos( $empresa_id );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Movimentos a Esclarecer', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Linhas do extrato bancário — pode responder por texto, por anexo, ou ambos.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_render_banner_resposta_pendente(); ?>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div></div>
			<?php if ( ! empty( $movimentos ) ) : ?><span class="ac-badge ac-badge-amber"><?php echo esc_html( count( $movimentos ) ); ?> <?php esc_html_e( 'por responder', 'contabilidade-pro' ); ?></span><?php endif; ?>
		</div>
		<?php if ( empty( $movimentos ) ) : ?>
			<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Sem movimentos a esclarecer de momento.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<?php foreach ( $movimentos as $mov ) :
				$resposta  = cp_ac_pendente_resposta_do_item( $empresa_id, $mov['id'] );
				$valor_cls = $mov['valor'] < 0 ? 'ac-mv-neg' : 'ac-mv-pos';
				$valor_txt = ( $mov['valor'] < 0 ? '−' : '+' ) . number_format_i18n( abs( $mov['valor'] ), 2 );
				?>
				<div class="ac-mv-item">
					<div class="ac-mv-row">
						<div class="ac-mv-amount <?php echo esc_attr( $valor_cls ); ?>"><?php echo esc_html( $valor_txt ); ?>&nbsp;€</div>
						<div style="flex:1;min-width:220px">
							<div style="font-size:13.5px;font-weight:600"><?php echo esc_html( $mov['data'] ); ?></div>
							<?php if ( ! empty( $mov['descricao'] ) ) : ?><div class="ac-mv-line"><?php echo esc_html( $mov['descricao'] ); ?></div><?php endif; ?>
							<?php if ( ! empty( $mov['pergunta'] ) ) : ?><div class="ac-mv-q"><?php echo esc_html( $mov['pergunta'] ); ?></div><?php endif; ?>
							<?php if ( $resposta ) : ?>
								<div style="font-size:13px;color:var(--ac-text);margin-top:8px"><?php echo esc_html( $resposta['texto'] ); ?></div>
								<?php if ( ! empty( $resposta['anexo_url'] ) ) : ?>
									<a class="ac-attach-chip" style="margin-top:8px" href="<?php echo esc_url( $resposta['anexo_url'] ); ?>" target="_blank" rel="noopener">
										<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 12.5 15 6a3 3 0 0 1 4.2 4.2L11 18.5a4.2 4.2 0 0 1-6-6L13 5"></path></svg>
										<?php echo esc_html( $resposta['anexo_nome'] ? $resposta['anexo_nome'] : __( 'Anexo', 'contabilidade-pro' ) ); ?>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<span class="ac-badge <?php echo $resposta ? 'ac-badge-green' : 'ac-badge-amber'; ?>"><?php echo $resposta ? esc_html__( 'Respondido', 'contabilidade-pro' ) : esc_html__( 'Por responder', 'contabilidade-pro' ); ?></span>
					</div>
					<?php if ( ! $resposta ) : ?>
						<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ac-mv-reply">
							<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
							<input type="hidden" name="action" value="cp_pendente_responder">
							<input type="hidden" name="tipo" value="movimento">
							<input type="hidden" name="item_id" value="<?php echo esc_attr( $mov['id'] ); ?>">
							<label for="cp-reply-<?php echo esc_attr( $mov['id'] ); ?>" style="font-size:12px;font-weight:600;color:var(--ac-muted)"><?php esc_html_e( 'A sua resposta', 'contabilidade-pro' ); ?></label>
							<textarea id="cp-reply-<?php echo esc_attr( $mov['id'] ); ?>" name="texto" placeholder="<?php esc_attr_e( 'Ex.: é o pagamento da fatura do fornecedor ABC, em anexo.', 'contabilidade-pro' ); ?>"></textarea>
							<div class="ac-mv-reply-actions">
								<label class="ac-btn ac-btn-ghost" style="cursor:pointer">
									<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 12.5 15 6a3 3 0 0 1 4.2 4.2L11 18.5a4.2 4.2 0 0 1-6-6L13 5"></path></svg>
									<?php esc_html_e( 'Anexar ficheiro', 'contabilidade-pro' ); ?>
									<input type="file" name="anexo" accept="<?php echo esc_attr( cp_ac_pendente_accept( 'movimento' ) ); ?>" style="display:none">
								</label>
								<button class="ac-btn ac-btn-primary" type="submit"><?php esc_html_e( 'Enviar resposta', 'contabilidade-pro' ); ?></button>
							</div>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Extratos ───────────────────────────────────────────────────────────────
// Grelha mês × conta. Cada célula é um item de cp_ac_extratos() (ou está em
// branco, se essa conta ainda não tinha pedido nesse mês). "Sem movimento"
// pode já vir marcado pela Work (estado 'sem_dados') ou ser marcado aqui
// pelo cliente, como alternativa a carregar o ficheiro.

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
		// Agrupar por mês, mais recente primeiro; dentro de cada mês, uma
		// coluna por conta (na ordem em que as contas foram vistas).
		$por_mes = [];
		foreach ( $itens as $item ) {
			$por_mes[ $item['mes'] ][ $item['conta_id'] ] = $item;
		}
		krsort( $por_mes );
		?>
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
	<?php else : ?>
		<div class="ac-card" style="padding:22px">
			<?php foreach ( $itens as $item ) : ?>
				<div class="ac-req-row">
					<div style="font-size:14px;font-weight:600"><?php echo esc_html( $item['mes'] ); ?></div>
					<?php cp_ac_render_celula_checklist( $item, $empresa_id, 'saft' ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
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
	$estado   = $resposta ? $resposta['estado'] : $item['estado'];

	if ( 'sem_dados' === $estado ) : ?>
		<span class="ac-badge ac-badge-blue"><?php esc_html_e( 'Sem dados este mês', 'contabilidade-pro' ); ?></span>
	<?php elseif ( $resposta ) : ?>
		<div style="display:flex;align-items:center;gap:10px">
			<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Enviado', 'contabilidade-pro' ); ?></span>
			<?php if ( ! empty( $resposta['anexo_url'] ) ) : ?><a class="ac-btn ac-btn-ghost" style="padding:6px 12px" href="<?php echo esc_url( $resposta['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver', 'contabilidade-pro' ); ?></a><?php endif; ?>
		</div>
	<?php else : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
			<?php wp_nonce_field( 'cp_pendente_resposta', 'cp_pendente_nonce' ); ?>
			<input type="hidden" name="action" value="cp_pendente_responder">
			<input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo ); ?>">
			<input type="hidden" name="item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'Pendente', 'contabilidade-pro' ); ?></span>
			<input type="file" name="anexo" accept="<?php echo esc_attr( cp_ac_pendente_accept( $tipo ) ); ?>" style="font-size:12px;max-width:160px">
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
