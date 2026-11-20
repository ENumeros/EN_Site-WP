<?php
/**
 * Backoffice › Contas correntes — a equipa consulta a conta corrente de cada
 * cliente sem entrar na Área de Cliente dele.
 *
 * Só leitura: os dados são os mesmos que o cliente vê (post meta
 * `_cp_conta_corrente`, enviado pela Work via POST cp/v1/conta-corrente).
 * Nada aqui escreve; para atualizar é na Work (ficha do cliente ou o pedido
 * automático diário "Conta corrente dos clientes").
 *
 * Ecrã: lista dos clientes à esquerda (número da Work, nome, saldo) e a conta
 * corrente do escolhido à direita — o mesmo padrão dos Recebimentos.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Uma linha da lista a partir dos dados guardados de uma empresa. Função
// pura (testada em tests/Unit/ContasCorrentesTest.php).
function cp_bocc_calc_item( $empresa_id, $rotulo, array $cc, $em = '' ) {
	$resumo = isset( $cc['resumo'] ) && is_array( $cc['resumo'] ) ? $cc['resumo'] : [];
	$linhas = isset( $cc['linhas'] ) && is_array( $cc['linhas'] ) ? $cc['linhas'] : [];
	$abertas = cp_ac_cc_faturas_em_aberto( $linhas );
	$em_divida = 0.0;
	foreach ( $abertas as $l ) $em_divida += (float) $l['por_pagar'];
	return [
		'id'         => (int) $empresa_id,
		'rotulo'     => (string) $rotulo,
		'tem_dados'  => ! empty( $resumo ) || ! empty( $linhas ),
		'saldo'      => isset( $resumo['saldo_atual'] ) ? round( (float) $resumo['saldo_atual'], 2 ) : null,
		'faturado'   => isset( $resumo['faturado_ano'] ) ? round( (float) $resumo['faturado_ano'], 2 ) : null,
		'pago'       => isset( $resumo['pago_ano'] ) ? round( (float) $resumo['pago_ano'], 2 ) : null,
		'em_divida'  => round( $em_divida, 2 ),
		'n_abertas'  => count( $abertas ),
		'linhas'     => $linhas,
		'em'         => (string) $em,
	];
}

function cp_bocc_euros( $v ) {
	return number_format( (float) $v, 2, ',', ' ' ) . ' €';
}

function cp_bocc_render_vista() {
	$itens = [];
	foreach ( cp_ac_ordenar_empresas( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'post_status' => 'publish' ] ) ) as $e ) {
		$itens[] = cp_bocc_calc_item( $e->ID, cp_ac_empresa_label( $e->ID ), cp_ac_conta_corrente( $e->ID ), get_post_meta( $e->ID, '_cp_conta_corrente_em', true ) );
	}
	$com_divida = count( array_filter( $itens, function ( $i ) { return $i['em_divida'] > 0.004; } ) );
	$total      = array_sum( array_column( $itens, 'em_divida' ) );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Contas correntes', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'A conta corrente de cada cliente na Entre Números, tal como o cliente a vê na Área de Cliente. Atualiza-se a partir da Work.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( ! $itens ) : ?>
		<?php cp_ac_vazio( __( 'Sem empresas', 'contabilidade-pro' ), __( 'As empresas chegam da Work.', 'contabilidade-pro' ) ); ?>
		<?php return; ?>
	<?php endif; ?>

	<div class="cp-bocc-filtros">
		<input type="search" id="cp-bocc-procura" placeholder="<?php esc_attr_e( 'Procurar por número ou nome…', 'contabilidade-pro' ); ?>" aria-label="<?php esc_attr_e( 'Procurar cliente', 'contabilidade-pro' ); ?>">
		<label><input type="checkbox" id="cp-bocc-divida"> <?php esc_html_e( 'Só com faturas em dívida', 'contabilidade-pro' ); ?></label>
		<span class="ac-sec-sub"><?php echo esc_html( sprintf( _n( '%1$d cliente com faturas em dívida · %2$s', '%1$d clientes com faturas em dívida · %2$s', $com_divida, 'contabilidade-pro' ), $com_divida, cp_bocc_euros( $total ) ) ); ?></span>
	</div>

	<div class="cp-bocc-trabalho">
		<div class="cp-bocc-lista" aria-label="<?php esc_attr_e( 'Clientes', 'contabilidade-pro' ); ?>">
			<?php foreach ( $itens as $i ) : ?>
				<button type="button" class="cp-bocc-item" data-cc="<?php echo esc_attr( $i['id'] ); ?>" data-divida="<?php echo $i['em_divida'] > 0.004 ? '1' : '0'; ?>" data-procura="<?php echo esc_attr( strtolower( remove_accents( $i['rotulo'] ) ) ); ?>">
					<span class="cp-bocc-item-nome"><?php echo esc_html( $i['rotulo'] ); ?></span>
					<span class="cp-bocc-item-l2">
						<?php if ( ! $i['tem_dados'] ) : ?>
							<span class="ac-sec-sub"><?php esc_html_e( 'Sem conta corrente', 'contabilidade-pro' ); ?></span>
						<?php elseif ( $i['em_divida'] > 0.004 ) : ?>
							<span class="ac-badge ac-badge-amber"><?php echo esc_html( sprintf( _n( '%d em dívida', '%d em dívida', $i['n_abertas'], 'contabilidade-pro' ), $i['n_abertas'] ) ); ?></span>
						<?php else : ?>
							<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Em dia', 'contabilidade-pro' ); ?></span>
						<?php endif; ?>
						<?php if ( null !== $i['saldo'] ) : ?><span class="cp-bocc-item-saldo"><?php echo esc_html( cp_bocc_euros( $i['saldo'] ) ); ?></span><?php endif; ?>
					</span>
				</button>
			<?php endforeach; ?>
			<p class="ac-sec-sub cp-bocc-nada" hidden><?php esc_html_e( 'Nenhum cliente com este filtro.', 'contabilidade-pro' ); ?></p>
		</div>

		<div class="cp-bocc-lado" aria-live="polite">
			<?php foreach ( $itens as $i ) : ?>
				<div class="ac-card cp-bocc-cc" id="cc-<?php echo esc_attr( $i['id'] ); ?>" data-cc="<?php echo esc_attr( $i['id'] ); ?>" hidden>
					<div class="cp-bocc-etq"><?php esc_html_e( 'Conta corrente', 'contabilidade-pro' ); ?><?php if ( $i['em'] ) : ?> · <?php echo esc_html( sprintf( __( 'atualizada %s', 'contabilidade-pro' ), mysql2date( 'd/m/Y H:i', $i['em'] ) ) ); ?><?php endif; ?></div>
					<h2 style="font-size:18px;margin:4px 0 0"><?php echo esc_html( $i['rotulo'] ); ?></h2>

					<?php if ( ! $i['tem_dados'] ) : ?>
						<p class="ac-sec-sub" style="margin-top:12px"><?php esc_html_e( 'Ainda não chegou a conta corrente deste cliente. Na Work, confirme que tem a conta na CentralGest (Ligações e acessos) e atualize a conta corrente na ficha.', 'contabilidade-pro' ); ?></p>
					<?php else : ?>
						<div class="cp-bocc-numeros">
							<div><div class="ac-stat-label"><?php esc_html_e( 'Saldo', 'contabilidade-pro' ); ?></div><div class="cp-bocc-num" style="color:var(--ac-gold)"><?php echo esc_html( null !== $i['saldo'] ? cp_bocc_euros( $i['saldo'] ) : '—' ); ?></div></div>
							<div><div class="ac-stat-label"><?php esc_html_e( 'Em dívida', 'contabilidade-pro' ); ?></div><div class="cp-bocc-num"><?php echo esc_html( cp_bocc_euros( $i['em_divida'] ) ); ?></div></div>
							<div><div class="ac-stat-label"><?php esc_html_e( 'Faturado no ano', 'contabilidade-pro' ); ?></div><div class="cp-bocc-num"><?php echo esc_html( null !== $i['faturado'] ? cp_bocc_euros( $i['faturado'] ) : '—' ); ?></div></div>
							<div><div class="ac-stat-label"><?php esc_html_e( 'Pago no ano', 'contabilidade-pro' ); ?></div><div class="cp-bocc-num"><?php echo esc_html( null !== $i['pago'] ? cp_bocc_euros( $i['pago'] ) : '—' ); ?></div></div>
						</div>
						<div class="ac-table-wrap" style="margin-top:14px">
							<table class="ac-table">
								<thead><tr>
									<th><?php esc_html_e( 'Data', 'contabilidade-pro' ); ?></th>
									<th><?php esc_html_e( 'Documento', 'contabilidade-pro' ); ?></th>
									<th style="text-align:right"><?php esc_html_e( 'Valor', 'contabilidade-pro' ); ?></th>
									<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
									<th></th>
								</tr></thead>
								<tbody>
									<?php foreach ( $i['linhas'] as $l ) :
										$estado = strtolower( (string) $l['estado'] );
										$cor    = in_array( $estado, [ 'pago', 'paga' ], true ) ? 'ac-badge-green' : ( '' === $estado ? '' : 'ac-badge-amber' );
										?>
										<tr>
											<td><?php echo esc_html( $l['data'] ); ?></td>
											<td><?php echo esc_html( $l['documento'] ); ?><?php if ( '' !== (string) $l['tipo'] ) : ?><div class="ac-sec-sub"><?php echo esc_html( $l['tipo'] ); ?></div><?php endif; ?></td>
											<td style="text-align:right"><?php echo esc_html( cp_bocc_euros( $l['valor'] ) ); ?><?php if ( ! empty( $l['por_pagar'] ) && (float) $l['por_pagar'] > 0.004 && (float) $l['por_pagar'] < (float) $l['valor'] - 0.004 ) : ?><div class="ac-sec-sub"><?php echo esc_html( sprintf( __( 'falta %s', 'contabilidade-pro' ), cp_bocc_euros( $l['por_pagar'] ) ) ); ?></div><?php endif; ?></td>
											<td><?php if ( $cor ) : ?><span class="ac-badge <?php echo esc_attr( $cor ); ?>"><?php echo esc_html( $l['estado'] ); ?></span><?php endif; ?></td>
											<td><?php if ( ! empty( $l['anexo_url'] ) ) : ?><a href="<?php echo esc_url( $l['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PDF', 'contabilidade-pro' ); ?></a><?php endif; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<style>
		.cp-bocc-filtros{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:16px}
		.cp-bocc-filtros input[type=search]{padding:8px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-surface);color:var(--ac-text);font-size:13.5px;flex:0 1 280px;min-width:0}
		.cp-bocc-filtros label{font-size:13px;display:flex;gap:6px;align-items:center}
		.cp-bocc-trabalho{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.4fr);gap:16px;align-items:start;margin-top:14px}
		.cp-bocc-lista{display:flex;flex-direction:column;gap:6px}
		.cp-bocc-item{display:flex;flex-direction:column;gap:4px;width:100%;text-align:left;padding:10px 14px;font:inherit;color:var(--ac-text);cursor:pointer;background:var(--ac-surface);border:1px solid var(--ac-line);border-radius:12px}
		.cp-bocc-item:hover{background:var(--ac-row-hover)}
		.cp-bocc-item.sel{border-color:var(--ac-gold);box-shadow:0 0 0 1px var(--ac-gold)}
		.cp-bocc-item[hidden]{display:none}
		.cp-bocc-item-nome{font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.cp-bocc-item-l2{display:flex;gap:10px;align-items:center}
		.cp-bocc-item-saldo{margin-left:auto;font-family:'Fraunces',serif;font-size:15px;white-space:nowrap}
		.cp-bocc-lado{position:sticky;top:16px}
		.cp-bocc-cc{padding:20px;max-height:calc(100vh - 32px);overflow:auto}
		.cp-bocc-cc[hidden]{display:none}
		.cp-bocc-etq{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--ac-muted);font-weight:600}
		.cp-bocc-numeros{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}
		.cp-bocc-num{font-family:'Fraunces',serif;font-size:20px;margin-top:2px;white-space:nowrap}
		.cp-bocc-cc .ac-table{min-width:0}
		.cp-bocc-cc .ac-table th,.cp-bocc-cc .ac-table td{padding:8px;font-size:13px}
		@media (max-width:1000px){.cp-bocc-trabalho{grid-template-columns:minmax(0,1fr)}.cp-bocc-lado{position:static}.cp-bocc-cc{max-height:none}}
		@media (max-width:640px){.cp-bocc-numeros{grid-template-columns:repeat(2,minmax(0,1fr))}}
	</style>
	<script>
	(function () {
		var itens = Array.prototype.slice.call(document.querySelectorAll('.cp-bocc-item'));
		var paineis = document.querySelectorAll('.cp-bocc-cc');
		var procura = document.getElementById('cp-bocc-procura');
		var divida = document.getElementById('cp-bocc-divida');
		var nada = document.querySelector('.cp-bocc-nada');
		function escolher(id, rolar) {
			itens.forEach(function (b) { var s = b.dataset.cc === id; b.classList.toggle('sel', s); b.setAttribute('aria-pressed', s); });
			paineis.forEach(function (p) { p.hidden = p.dataset.cc !== id; });
			history.replaceState(null, '', location.pathname + location.search + '#cc-' + encodeURIComponent(id));
			if (rolar && window.matchMedia('(max-width: 1000px)').matches) {
				document.querySelector('.cp-bocc-lado').scrollIntoView({behavior: 'smooth', block: 'start'});
			}
		}
		function filtrar() {
			var t = (procura.value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
			var visiveis = 0;
			itens.forEach(function (b) {
				var ok = (!t || b.dataset.procura.indexOf(t) !== -1) && (!divida.checked || b.dataset.divida === '1');
				b.hidden = !ok; if (ok) visiveis++;
			});
			nada.hidden = visiveis > 0;
		}
		itens.forEach(function (b) { b.addEventListener('click', function () { escolher(b.dataset.cc, true); }); });
		procura.addEventListener('input', filtrar);
		divida.addEventListener('change', filtrar);
		if (itens.length) {
			var pedido = decodeURIComponent((location.hash || '').replace(/^#cc-/, ''));
			var existe = itens.some(function (b) { return b.dataset.cc === pedido; });
			escolher(existe ? pedido : itens[0].dataset.cc, false);
		}
	})();
	</script>
	<?php
}
