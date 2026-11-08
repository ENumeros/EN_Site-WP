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

	$tem_conta_corrente    = cp_ac_tem_ferramenta( 'conta_corrente' );
	$tem_calendario_fiscal = cp_ac_tem_ferramenta( 'calendario_fiscal' );
	$tem_mapas_gestao      = cp_ac_tem_ferramenta( 'mapas_gestao' );
	$tem_documentos        = cp_ac_tem_ferramenta( 'documentos' );
	$tem_pendentes         = cp_ac_tem_ferramenta( 'pendentes' );
	$tem_tarefas           = cp_ac_tem_ferramenta( 'tarefas' );

	?>
	<div>
		<h1 style="font-size:23px"><?php printf( /* translators: %s: nome do utilizador */ esc_html__( 'Olá, %s', 'contabilidade-pro' ), esc_html( $nome_utilizador ) ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php printf( /* translators: %s: nome da empresa */ esc_html__( 'Situação atual — %s', 'contabilidade-pro' ), esc_html( get_the_title( $empresa_id ) ) ); ?></div>
	</div>

	<?php if ( ! $tem_conta_corrente && ! $tem_calendario_fiscal && ! $tem_mapas_gestao && ! $tem_documentos && ! $tem_pendentes && ! $tem_tarefas ) : ?>
		<?php cp_ac_vazio( __( 'Ainda não há ferramentas ativadas para esta empresa.', 'contabilidade-pro' ), __( 'Contacte a Entre Números se estiver à espera de acesso a alguma funcionalidade.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">

		<?php if ( $tem_conta_corrente ) :
			$cc        = cp_ac_conta_corrente( $empresa_id );
			$resumo    = isset( $cc['resumo'] ) ? $cc['resumo'] : [];
			$linhas    = isset( $cc['linhas'] ) ? $cc['linhas'] : [];
			$por_pagar = count( array_filter( $linhas, function ( $linha ) {
				return ! in_array( strtolower( $linha['estado'] ), [ 'pago', 'paga' ], true );
			} ) );
			?>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Saldo em conta corrente', 'contabilidade-pro' ); ?></div>
				<?php if ( isset( $resumo['saldo_atual'] ) ) : ?>
					<div style="font-family:'Fraunces',serif;font-size:26px;color:var(--ac-gold)"><?php echo esc_html( number_format_i18n( $resumo['saldo_atual'], 2 ) ); ?>&nbsp;€</div>
				<?php else : ?>
					<div style="font-family:'Fraunces',serif;font-size:26px">0,00&nbsp;€</div>
				<?php endif; ?>
				<div style="font-size:12.5px;color:var(--ac-muted)"><?php printf( /* translators: %d: número de documentos */ esc_html( _n( '%d documento por liquidar', '%d documentos por liquidar', $por_pagar, 'contabilidade-pro' ) ), $por_pagar ); ?></div>
				<a href="<?php echo esc_url( cp_ac_vista_url( 'conta_corrente' ) ); ?>" style="font-size:12.5px;font-weight:600;margin-top:4px"><?php esc_html_e( 'Ver conta corrente →', 'contabilidade-pro' ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $tem_calendario_fiscal ) : ?>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Próximo prazo', 'contabilidade-pro' ); ?></div>
				<div style="font-family:'Fraunces',serif;font-size:26px">—</div>
				<div style="font-size:12.5px;color:var(--ac-muted)"><?php esc_html_e( 'Sem prazos agendados', 'contabilidade-pro' ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( $tem_mapas_gestao ) :
			$mapas  = cp_ac_mapas_gestao( $empresa_id );
			$ultimo = ! empty( $mapas ) ? $mapas[0] : null;
			?>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Mapa de Gestão', 'contabilidade-pro' ); ?></div>
				<?php if ( $ultimo ) : ?>
					<div style="font-family:'Fraunces',serif;font-size:19px;line-height:1.3"><?php echo esc_html( $ultimo['titulo'] ); ?></div>
					<?php if ( ! empty( $ultimo['publicado_em'] ) ) : ?>
						<div style="font-size:12.5px;color:var(--ac-muted)"><?php printf( /* translators: %s: data */ esc_html__( 'Publicado a %s', 'contabilidade-pro' ), esc_html( mysql2date( get_option( 'date_format' ), $ultimo['publicado_em'] ) ) ); ?></div>
					<?php endif; ?>
					<a href="<?php echo esc_url( cp_ac_vista_url( 'mapas_gestao' ) ); ?>" style="font-size:12.5px;font-weight:600;margin-top:4px"><?php esc_html_e( 'Descarregar →', 'contabilidade-pro' ); ?></a>
				<?php else : ?>
					<div style="font-family:'Fraunces',serif;font-size:19px">—</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $tem_documentos ) : ?>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Extratos & SAF-T (mês corrente)', 'contabilidade-pro' ); ?></div>
				<div style="font-family:'Fraunces',serif;font-size:19px">—</div>
			</div>
		<?php endif; ?>

		<?php if ( $tem_pendentes ) :
			$doc_pendentes = count( cp_ac_pendentes_documentos( $empresa_id ) );
			$mov_pendentes = count( cp_ac_pendentes_movimentos( $empresa_id ) );
			?>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Documentos pendentes', 'contabilidade-pro' ); ?></div>
				<div style="font-family:'Fraunces',serif;font-size:26px"><?php echo esc_html( $doc_pendentes ); ?></div>
				<a href="<?php echo esc_url( cp_ac_vista_url( 'pendentes' ) ); ?>" style="font-size:12.5px;font-weight:600;margin-top:4px"><?php esc_html_e( 'Ver pendentes →', 'contabilidade-pro' ); ?></a>
			</div>
			<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:8px">
				<div class="ac-stat-label"><?php esc_html_e( 'Movimentos a esclarecer', 'contabilidade-pro' ); ?></div>
				<div style="font-family:'Fraunces',serif;font-size:26px"><?php echo esc_html( $mov_pendentes ); ?></div>
				<div style="font-size:12.5px;color:var(--ac-muted)"><?php esc_html_e( 'Linhas do extrato bancário', 'contabilidade-pro' ); ?></div>
				<a href="<?php echo esc_url( cp_ac_vista_url( 'pendentes' ) ); ?>" style="font-size:12.5px;font-weight:600;margin-top:4px"><?php esc_html_e( 'Ver pendentes →', 'contabilidade-pro' ); ?></a>
			</div>
		<?php endif; ?>

	</div>

	<?php if ( $tem_tarefas ) :
		$lembretes_sucesso = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
		$lembretes_erro    = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
		?>
		<div>
			<h2 style="font-size:18px"><?php esc_html_e( 'Lembretes', 'contabilidade-pro' ); ?></h2>
		</div>

		<?php if ( in_array( $lembretes_sucesso, [ 'tarefa_criada', 'tarefa_atualizada', 'tarefa_apagada' ], true ) ) :
			$lembretes_titulos = [
				'tarefa_criada'     => __( 'Lembrete adicionado', 'contabilidade-pro' ),
				'tarefa_atualizada' => __( 'Lembrete atualizado', 'contabilidade-pro' ),
				'tarefa_apagada'    => __( 'Lembrete apagado', 'contabilidade-pro' ),
			];
			?>
			<div class="ac-status-banner green">
				<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
				<div><div class="ac-status-title"><?php echo esc_html( $lembretes_titulos[ $lembretes_sucesso ] ); ?></div></div>
			</div>
		<?php elseif ( $lembretes_erro ) : ?>
			<div class="ac-status-banner amber">
				<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
				<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível concluir o pedido', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber"><?php esc_html_e( 'Escreva um texto para o lembrete e tente novamente.', 'contabilidade-pro' ); ?></div></div>
			</div>
		<?php endif; ?>

		<?php cp_ac_render_tarefas( $empresa_id ); ?>
	<?php endif; ?>
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
			<div style="font-family:'Fraunces',serif;font-size:25px;color:var(--ac-gold)"><?php echo esc_html( number_format_i18n( $resumo['saldo_atual'], 2 ) ); ?>&nbsp;€</div>
			<?php if ( ! empty( $resumo['saldo_nota'] ) ) : ?><div style="font-size:12.5px;color:var(--ac-muted)"><?php echo esc_html( $resumo['saldo_nota'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
		<?php if ( isset( $resumo['faturado_ano'] ) ) : ?>
		<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
			<div class="ac-stat-label"><?php esc_html_e( 'Faturado este ano', 'contabilidade-pro' ); ?></div>
			<div style="font-family:'Fraunces',serif;font-size:25px"><?php echo esc_html( number_format_i18n( $resumo['faturado_ano'], 2 ) ); ?>&nbsp;€</div>
			<?php if ( ! empty( $resumo['faturado_nota'] ) ) : ?><div style="font-size:12.5px;color:var(--ac-muted)"><?php echo esc_html( $resumo['faturado_nota'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
		<?php if ( isset( $resumo['pago_ano'] ) ) : ?>
		<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:6px">
			<div class="ac-stat-label"><?php esc_html_e( 'Pago este ano', 'contabilidade-pro' ); ?></div>
			<div style="font-family:'Fraunces',serif;font-size:25px"><?php echo esc_html( number_format_i18n( $resumo['pago_ano'], 2 ) ); ?>&nbsp;€</div>
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
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Documentos', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Envie e acompanhe os documentos correntes da sua contabilidade.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos da Contabilidade', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Envie aqui, a qualquer momento, faturas, despesas e outros documentos correntes.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div class="ac-dropzone">
			<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
			<div style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Arraste ficheiros para aqui ou carregue manualmente', 'contabilidade-pro' ); ?></div>
			<button class="ac-btn ac-btn-primary" type="button"><?php esc_html_e( 'Carregar Documento', 'contabilidade-pro' ); ?></button>
		</div>
		<div style="margin-top:16px;color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não enviou nenhum documento.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Extratos Bancários & SAF-T', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Todos os meses, carregue o extrato bancário e o ficheiro SAF-T da sua contabilidade.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não há meses registados.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php
}

// ─── Pendentes ──────────────────────────────────────────────────────────────

function cp_ac_vista_pendentes() {
	$empresa_id = cp_ac_empresa_ativa();
	$documentos = cp_ac_pendentes_documentos( $empresa_id );
	$movimentos = cp_ac_pendentes_movimentos( $empresa_id );
	$sucesso    = isset( $_GET['cp_sucesso'] ) ? sanitize_key( wp_unslash( $_GET['cp_sucesso'] ) ) : '';
	$erro       = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Pendentes', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'O que a Entre Números precisa de si, em dois grupos: documentos e movimentos bancários.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( 'resposta_enviada' === $sucesso ) : ?>
		<div class="ac-status-banner green">
			<div class="ac-status-icon green"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Resposta enviada', 'contabilidade-pro' ); ?></div><div class="ac-status-sub green"><?php esc_html_e( 'A nossa equipa foi notificada.', 'contabilidade-pro' ); ?></div></div>
		</div>
	<?php elseif ( $erro ) : ?>
		<div class="ac-status-banner amber">
			<div class="ac-status-icon amber"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg></div>
			<div><div class="ac-status-title"><?php esc_html_e( 'Não foi possível enviar', 'contabilidade-pro' ); ?></div><div class="ac-status-sub amber"><?php esc_html_e( 'Escreva uma resposta ou anexe um ficheiro, e tente novamente.', 'contabilidade-pro' ); ?></div></div>
		</div>
	<?php endif; ?>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos Pendentes', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Documentos que a nossa equipa precisa especificamente de si.', 'contabilidade-pro' ); ?></div>
			</div>
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
							<input type="file" name="anexo" required style="font-size:12.5px;max-width:200px">
							<button class="ac-btn ac-btn-primary" type="submit" style="padding:7px 12px"><?php esc_html_e( 'Carregar', 'contabilidade-pro' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Movimentos a Esclarecer', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Linhas do extrato bancário — pode responder por texto, por anexo, ou ambos.', 'contabilidade-pro' ); ?></div>
			</div>
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
									<input type="file" name="anexo" style="display:none">
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
		<h1 style="font-size:23px"><?php esc_html_e( 'Calendário Fiscal', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Prazos e obrigações fiscais acompanhados pela Entre Números.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_vazio( __( 'Sem prazos registados', 'contabilidade-pro' ), __( 'Os próximos prazos e obrigações fiscais da sua empresa vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Util: URL de uma vista ─────────────────────────────────────────────────

function cp_ac_vista_url( $chave ) {
	return add_query_arg( 'vista', $chave, get_permalink() );
}
