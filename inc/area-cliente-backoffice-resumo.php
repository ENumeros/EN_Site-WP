<?php
/**
 * Backoffice — Resumo da Área de Cliente (SITE-009).
 *
 * Página inicial do Backoffice para a equipa: o que os clientes fizeram no
 * site e o que está à nossa espera. Só lê dados que já vivem no WordPress
 * (documentos, conversas, pendentes, lembretes, feed de eventos, contas) —
 * as tarefas internas ficam na Work e nunca entram aqui.
 *
 * As funções `cp_bo_calc_*` recebem arrays e não tocam no WordPress, para
 * poderem ser testadas sem base de dados (tests/Unit/BackofficeResumoTest.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Só é "novidade" o que chegou depois da visita anterior; uma visita nova só
// conta depois de 30 min sem abrir o Resumo (recarregar não apaga o "Novo").
const CP_BO_JANELA_VISITA = 1800;

// Contas sem entrar há mais do que isto contam como inativas.
const CP_BO_DIAS_INATIVO = 30;

// ─── Cálculos puros ─────────────────────────────────────────────────────────

// Tipo de um evento do feed: o guardado (eventos novos) ou, nos antigos, o
// deduzido do título — os títulos são os de cp_push_notificar_equipa().
function cp_bo_calc_tipo_evento( array $evento ) {
	if ( ! empty( $evento['tipo'] ) ) return $evento['tipo'];
	$titulo = isset( $evento['titulo'] ) ? (string) $evento['titulo'] : '';
	if ( false !== stripos( $titulo, 'documento' ) ) return 'documentos';
	if ( false !== stripos( $titulo, 'mensagem' ) ) return 'mensagens';
	if ( false !== stripos( $titulo, 'lembrete' ) ) return 'lembretes';
	if ( false !== stripos( $titulo, 'resposta' ) || false !== stripos( $titulo, 'pendente' ) ) return 'pendentes';
	return 'outros';
}

// Conversas ativas cuja última mensagem é do cliente — estão à nossa espera.
// $conversas: as de cp_ac_conversas_raw(); $mensagens: as de cp_ac_mensagens().
function cp_bo_calc_conversas_por_responder( array $conversas, array $mensagens ) {
	$ultima = [];
	foreach ( $mensagens as $mensagem ) {
		if ( empty( $mensagem['conversa_id'] ) ) continue;
		$id = $mensagem['conversa_id'];
		if ( ! isset( $ultima[ $id ] ) || strcmp( $mensagem['enviado_em'], $ultima[ $id ]['enviado_em'] ) >= 0 ) {
			$ultima[ $id ] = $mensagem;
		}
	}
	$resultado = [];
	foreach ( $conversas as $conversa ) {
		if ( ! empty( $conversa['arquivada'] ) || ! isset( $ultima[ $conversa['id'] ] ) ) continue;
		if ( 'cliente' !== $ultima[ $conversa['id'] ]['autor_tipo'] ) continue;
		$resultado[] = [
			'id'      => $conversa['id'],
			'assunto' => $conversa['assunto'],
			'em'      => $ultima[ $conversa['id'] ]['enviado_em'],
		];
	}
	usort( $resultado, function ( $a, $b ) { return strcmp( $a['em'], $b['em'] ); } );
	return $resultado;
}

// O que pedimos ao cliente e ele ainda não respondeu: documentos em falta e
// movimentos a esclarecer sem resposta guardada. 'desde' = o pedido mais
// antigo com data conhecida (os movimentos não trazem data de pedido).
function cp_bo_calc_pedidos_por_responder( array $documentos, array $movimentos, array $respostas ) {
	$respondidos = array_flip( array_map( 'strval', wp_list_pluck( $respostas, 'item_id' ) ) );
	$docs = array_values( array_filter( $documentos, function ( $d ) use ( $respondidos ) {
		return ! isset( $respondidos[ (string) $d['id'] ] );
	} ) );
	$movs = array_values( array_filter( $movimentos, function ( $m ) use ( $respondidos ) {
		return ! isset( $respondidos[ (string) $m['id'] ] );
	} ) );
	$datas = array_filter( wp_list_pluck( $docs, 'pedido_em' ) );
	sort( $datas );
	return [
		'documentos' => count( $docs ),
		'movimentos' => count( $movs ),
		'desde'      => $datas ? reset( $datas ) : '',
	];
}

// Avança a visita do utilizador. $guardada: [ 'anterior' => ..., 'atual' => ... ]
// (timestamps). Devolve [ novo_valor_a_guardar, momento_desde_o_qual_e_novo ].
function cp_bo_calc_visita( $guardada, $agora ) {
	$guardada = is_array( $guardada ) ? $guardada : [];
	$anterior = isset( $guardada['anterior'] ) ? (int) $guardada['anterior'] : 0;
	$atual    = isset( $guardada['atual'] ) ? (int) $guardada['atual'] : 0;
	if ( ! $atual ) {
		return [ [ 'anterior' => $agora, 'atual' => $agora ], $agora ];
	}
	if ( $agora - $atual > CP_BO_JANELA_VISITA ) {
		return [ [ 'anterior' => $atual, 'atual' => $agora ], $atual ];
	}
	return [ [ 'anterior' => $anterior, 'atual' => $agora ], $anterior ?: $atual ];
}

// ─── Último acesso dos clientes ─────────────────────────────────────────────
// Começa a contar a partir desta versão: quem não entrou desde então aparece
// como "sem entrada registada".

function cp_bo_registar_ultimo_acesso( $user_login, $user ) {
	if ( $user instanceof WP_User ) {
		update_user_meta( $user->ID, '_cp_ultimo_acesso', current_time( 'mysql' ) );
	}
}
add_action( 'wp_login', 'cp_bo_registar_ultimo_acesso', 10, 2 );

// ─── Recolha dos dados ──────────────────────────────────────────────────────

function cp_bo_clientes_do_site() {
	$clientes = [];
	foreach ( get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] ) as $utilizador ) {
		if ( cp_backoffice_utilizador_tem_acesso( $utilizador->ID ) ) continue;
		$empresas = cp_ac_user_empresas( $utilizador->ID );
		if ( empty( $empresas ) ) continue;
		$clientes[] = [
			'id'       => $utilizador->ID,
			'empresas' => $empresas,
			'acesso'   => (string) get_user_meta( $utilizador->ID, '_cp_ultimo_acesso', true ),
		];
	}
	return $clientes;
}

function cp_bo_resumo() {
	$clientes = cp_bo_clientes_do_site();
	$acesso_por_empresa = [];
	foreach ( $clientes as $cliente ) {
		foreach ( $cliente['empresas'] as $empresa_id ) {
			if ( $cliente['acesso'] && ( ! isset( $acesso_por_empresa[ $empresa_id ] ) || $cliente['acesso'] > $acesso_por_empresa[ $empresa_id ] ) ) {
				$acesso_por_empresa[ $empresa_id ] = $cliente['acesso'];
			}
		}
	}

	$r = [
		'documentos' => 0, 'documentos_desde' => '', 'documentos_empresas' => 0,
		'mensagens'  => 0, 'mensagens_desde' => '',
		'respostas'  => 0, 'respostas_empresas' => 0,
		'lembretes'  => 0,
		'empresas'   => [], 'conversas' => [], 'lembretes_lista' => [], 'pedidos' => [],
	];

	$empresas = get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ] );
	foreach ( $empresas as $empresa ) {
		$id   = $empresa->ID;
		$nome = $empresa->post_title;

		$docs = array_values( array_filter( cp_ac_documentos( $id ), function ( $anexo ) {
			return ! cp_ac_documento_recebido_work( $anexo->ID );
		} ) );
		$docs_desde = $docs ? end( $docs )->post_date : '';

		$conversas = cp_bo_calc_conversas_por_responder( cp_ac_conversas_raw( $id ), cp_ac_mensagens( $id ) );

		$respostas = cp_ac_pendentes_respostas( $id );
		$pedidos   = cp_bo_calc_pedidos_por_responder(
			cp_ac_empresa_tem_ferramenta( $id, 'documentos_em_falta' ) ? cp_ac_pendentes_documentos( $id ) : [],
			cp_ac_empresa_tem_ferramenta( $id, 'movimentos_a_esclarecer' ) ? cp_ac_pendentes_movimentos( $id ) : [],
			$respostas
		);

		$lembretes = array_values( array_filter( cp_ac_tarefas( $id ), function ( $t ) { return empty( $t['concluido'] ); } ) );

		$r['documentos'] += count( $docs );
		if ( $docs ) {
			$r['documentos_empresas']++;
			if ( ! $r['documentos_desde'] || $docs_desde < $r['documentos_desde'] ) $r['documentos_desde'] = $docs_desde;
		}
		$r['mensagens'] += count( $conversas );
		foreach ( $conversas as $c ) {
			$c['empresa_id'] = $id;
			$c['empresa']    = $nome;
			$r['conversas'][] = $c;
			if ( ! $r['mensagens_desde'] || $c['em'] < $r['mensagens_desde'] ) $r['mensagens_desde'] = $c['em'];
		}
		$r['respostas'] += count( $respostas );
		if ( $respostas ) $r['respostas_empresas']++;
		$r['lembretes'] += count( $lembretes );
		foreach ( $lembretes as $l ) {
			$r['lembretes_lista'][] = [ 'titulo' => $l['titulo'], 'empresa_id' => $id, 'empresa' => $nome, 'por' => $l['criado_por'], 'em' => $l['criado_em'] ];
		}
		if ( $pedidos['documentos'] || $pedidos['movimentos'] ) {
			$r['pedidos'][] = array_merge( $pedidos, [ 'empresa_id' => $id, 'empresa' => $nome ] );
		}

		if ( $docs || $conversas || $respostas ) {
			$r['empresas'][] = [
				'id'         => $id,
				'nome'       => $nome,
				'documentos' => count( $docs ),
				'mensagens'  => count( $conversas ),
				'respostas'  => count( $respostas ),
				'acesso'     => isset( $acesso_por_empresa[ $id ] ) ? $acesso_por_empresa[ $id ] : '',
			];
		}
	}

	// Ordem pela ação: mensagens primeiro, depois quem tem mais à espera.
	usort( $r['empresas'], function ( $a, $b ) {
		return [ $b['mensagens'], $b['documentos'] + $b['respostas'] ] <=> [ $a['mensagens'], $a['documentos'] + $a['respostas'] ];
	} );
	usort( $r['conversas'], function ( $a, $b ) { return strcmp( $a['em'], $b['em'] ); } );
	usort( $r['lembretes_lista'], function ( $a, $b ) { return strcmp( $b['em'], $a['em'] ); } );
	usort( $r['pedidos'], function ( $a, $b ) {
		return strcmp( $a['desde'] ?: '9999', $b['desde'] ?: '9999' );
	} );

	$limite_inativo = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - CP_BO_DIAS_INATIVO * DAY_IN_SECONDS );
	$r['contas'] = [
		'total'        => count( $clientes ),
		'sem_registo'  => count( array_filter( $clientes, function ( $c ) { return '' === $c['acesso']; } ) ),
		'inativas'     => count( array_filter( $clientes, function ( $c ) use ( $limite_inativo ) { return '' !== $c['acesso'] && $c['acesso'] < $limite_inativo; } ) ),
	];
	$r['work_recolha'] = (string) get_option( 'cp_work_ultima_recolha', '' );

	return $r;
}

// ─── Apresentação ───────────────────────────────────────────────────────────

function cp_bo_ha( $mysql ) {
	if ( ! $mysql ) return '';
	/* translators: %s: há quanto tempo, ex. "3 horas" */
	return sprintf( __( 'há %s', 'contabilidade-pro' ), human_time_diff( strtotime( $mysql ), current_time( 'timestamp' ) ) );
}

function cp_bo_url_empresa( $empresa_id, $ancora = 'cp-backoffice-tarefas', $extra = [] ) {
	return add_query_arg( array_merge( [ 'empresa_id' => (int) $empresa_id ], $extra ), get_permalink() ) . '#' . $ancora;
}

function cp_bo_plural( $n, $um, $varios ) {
	return sprintf( 1 === (int) $n ? $um : $varios, (int) $n );
}

function cp_bo_render_resumo( $r = null ) {
	$r = $r ?: cp_bo_resumo();

	list( $visita, $novo_desde_ts ) = cp_bo_calc_visita( get_user_meta( get_current_user_id(), '_cp_bo_visita', true ), current_time( 'timestamp' ) );
	update_user_meta( get_current_user_id(), '_cp_bo_visita', $visita );
	$novo_desde = gmdate( 'Y-m-d H:i:s', $novo_desde_ts );

	$tipos   = [ '' => __( 'Tudo', 'contabilidade-pro' ), 'documentos' => __( 'Documentos', 'contabilidade-pro' ), 'mensagens' => __( 'Mensagens', 'contabilidade-pro' ), 'pendentes' => __( 'Pendentes', 'contabilidade-pro' ), 'lembretes' => __( 'Lembretes', 'contabilidade-pro' ) ];
	$filtro  = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : '';
	$filtro  = isset( $tipos[ $filtro ] ) ? $filtro : '';
	$eventos = cp_backoffice_listar_eventos( 0 );
	$n_novos = count( array_filter( $eventos, function ( $e ) use ( $novo_desde ) { return $e['criado_em'] > $novo_desde; } ) );
	if ( $filtro ) {
		$eventos = array_values( array_filter( $eventos, function ( $e ) use ( $filtro ) { return cp_bo_calc_tipo_evento( $e ) === $filtro; } ) );
	}
	$eventos = array_slice( $eventos, 0, 8 );
	$base    = get_permalink();
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Resumo da Área de Cliente', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'O que os clientes fizeram no site e o que está à nossa espera.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-stats-grid ac-bo-kpis">
		<?php
		$cartoes = [
			[ __( 'Documentos por recolher', 'contabilidade-pro' ), $r['documentos'], '',
				$r['documentos'] ? sprintf( __( '%1$s · o mais antigo %2$s', 'contabilidade-pro' ), cp_bo_plural( $r['documentos_empresas'], __( '%d empresa', 'contabilidade-pro' ), __( '%d empresas', 'contabilidade-pro' ) ), cp_bo_ha( $r['documentos_desde'] ) ) : __( 'Nada à espera da Work', 'contabilidade-pro' ), '#cp-bo-empresas' ],
			[ __( 'Mensagens por responder', 'contabilidade-pro' ), $r['mensagens'], $r['mensagens'] ? 'var(--ac-badge-amber-fg)' : '',
				$r['mensagens'] ? sprintf( __( 'a mais antiga %s', 'contabilidade-pro' ), cp_bo_ha( $r['mensagens_desde'] ) ) : __( 'Tudo respondido', 'contabilidade-pro' ), '#cp-bo-conversas' ],
			[ __( 'Pendentes respondidos', 'contabilidade-pro' ), $r['respostas'], '',
				$r['respostas'] ? sprintf( __( '%s · por confirmar na Work', 'contabilidade-pro' ), cp_bo_plural( $r['respostas_empresas'], __( '%d empresa', 'contabilidade-pro' ), __( '%d empresas', 'contabilidade-pro' ) ) ) : __( 'Nenhuma resposta por tratar', 'contabilidade-pro' ), '#cp-bo-empresas' ],
			[ __( 'Lembretes em aberto', 'contabilidade-pro' ), $r['lembretes'], '',
				$r['lembretes'] ? __( 'entre todas as empresas', 'contabilidade-pro' ) : __( 'Nenhum lembrete em aberto', 'contabilidade-pro' ), '#cp-bo-lembretes' ],
		];
		foreach ( $cartoes as $c ) :
			?>
			<a class="ac-card ac-bo-kpi" href="<?php echo esc_attr( $c[4] ); ?>">
				<div class="ac-stat-label"><?php echo esc_html( $c[0] ); ?></div>
				<?php if ( $c[1] ) : ?>
					<div class="ac-bo-kpi-num"<?php echo $c[2] ? ' style="color:' . esc_attr( $c[2] ) . '"' : ''; ?>><?php echo esc_html( number_format_i18n( $c[1] ) ); ?></div>
					<div class="ac-sec-sub"><?php echo esc_html( $c[3] ); ?></div>
				<?php else : ?>
					<div class="ac-stat-vazio is-ok" style="margin-top:12px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5 9.5 17 19 7.5"></path></svg><span><?php echo esc_html( $c[3] ); ?></span></div>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="ac-dashboard-cols">
		<div class="ac-dashboard-main">
			<section class="ac-card" style="padding:22px" id="cp-bo-empresas">
				<div class="ac-sec-head" style="margin-bottom:4px">
					<h2 style="font-size:18px"><?php esc_html_e( 'Por empresa', 'contabilidade-pro' ); ?></h2>
					<span class="ac-sec-sub"><?php esc_html_e( 'só as que têm algo à nossa espera', 'contabilidade-pro' ); ?></span>
				</div>
				<?php if ( empty( $r['empresas'] ) ) : ?>
					<div class="ac-sec-sub" style="padding:10px 0"><?php esc_html_e( 'Nenhuma empresa tem nada à nossa espera.', 'contabilidade-pro' ); ?></div>
				<?php endif; ?>
				<?php foreach ( $r['empresas'] as $e ) :
					$partes = [];
					if ( $e['mensagens'] ) $partes[] = cp_bo_plural( $e['mensagens'], __( '%d mensagem', 'contabilidade-pro' ), __( '%d mensagens', 'contabilidade-pro' ) );
					if ( $e['documentos'] ) $partes[] = cp_bo_plural( $e['documentos'], __( '%d documento', 'contabilidade-pro' ), __( '%d documentos', 'contabilidade-pro' ) );
					if ( $e['respostas'] ) $partes[] = cp_bo_plural( $e['respostas'], __( '%d pendente respondido', 'contabilidade-pro' ), __( '%d pendentes respondidos', 'contabilidade-pro' ) );
					/* translators: %s: há quanto tempo */
					$partes[] = $e['acesso'] ? sprintf( __( 'entrou %s', 'contabilidade-pro' ), cp_bo_ha( $e['acesso'] ) ) : __( 'sem entrada registada', 'contabilidade-pro' );
					?>
					<div class="ac-req-row" style="align-items:center">
						<div>
							<div style="font-size:14px;font-weight:600"><?php echo esc_html( $e['nome'] ); ?></div>
							<div class="ac-sec-sub"><?php echo esc_html( implode( ' · ', $partes ) ); ?></div>
						</div>
						<a class="ac-btn ac-btn-ghost" style="padding:9px 14px" href="<?php echo esc_url( cp_bo_url_empresa( $e['id'], $e['mensagens'] ? 'cp-backoffice-mensagens' : 'cp-backoffice-tarefas' ) ); ?>"><?php esc_html_e( 'Abrir', 'contabilidade-pro' ); ?></a>
					</div>
				<?php endforeach; ?>
			</section>

			<section class="ac-card" style="padding:22px">
				<div class="ac-sec-head" style="margin-bottom:4px">
					<h2 style="font-size:18px"><?php esc_html_e( 'Pedidos ao cliente por responder', 'contabilidade-pro' ); ?></h2>
					<span class="ac-sec-sub"><?php esc_html_e( 'o que pedimos e ainda não chegou', 'contabilidade-pro' ); ?></span>
				</div>
				<?php if ( empty( $r['pedidos'] ) ) : ?>
					<div class="ac-sec-sub" style="padding:10px 0"><?php esc_html_e( 'Os clientes responderam a tudo o que pedimos.', 'contabilidade-pro' ); ?></div>
				<?php endif; ?>
				<?php foreach ( array_slice( $r['pedidos'], 0, 6 ) as $p ) :
					$partes = [];
					if ( $p['documentos'] ) $partes[] = cp_bo_plural( $p['documentos'], __( '%d documento em falta', 'contabilidade-pro' ), __( '%d documentos em falta', 'contabilidade-pro' ) );
					if ( $p['movimentos'] ) $partes[] = cp_bo_plural( $p['movimentos'], __( '%d movimento por esclarecer', 'contabilidade-pro' ), __( '%d movimentos por esclarecer', 'contabilidade-pro' ) );
					?>
					<div class="ac-req-row" style="align-items:center">
						<div>
							<div style="font-size:14px;font-weight:600"><?php echo esc_html( implode( ' · ', $partes ) ); ?></div>
							<div class="ac-sec-sub"><?php echo esc_html( $p['empresa'] ); ?></div>
						</div>
						<?php if ( $p['desde'] ) : ?>
							<span class="ac-badge ac-badge-amber"><?php echo esc_html( sprintf( __( 'pedido %s', 'contabilidade-pro' ), cp_bo_ha( $p['desde'] ) ) ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</section>

			<section class="ac-card" style="padding:22px">
				<div class="ac-sec-head">
					<div>
						<h2 style="font-size:18px"><?php esc_html_e( 'Atividade recente', 'contabilidade-pro' ); ?></h2>
						<div class="ac-sec-sub">
							<?php
							echo esc_html( $n_novos
								? cp_bo_plural( $n_novos, __( '%d novidade desde a sua última visita', 'contabilidade-pro' ), __( '%d novidades desde a sua última visita', 'contabilidade-pro' ) )
								: __( 'Nada de novo desde a sua última visita', 'contabilidade-pro' ) );
							?>
						</div>
					</div>
					<div class="ac-bo-filtros">
						<?php foreach ( $tipos as $chave => $rotulo ) : ?>
							<a class="ac-bo-chip<?php echo $filtro === $chave ? ' is-on' : ''; ?>" href="<?php echo esc_url( ( $chave ? add_query_arg( 'tipo', $chave, $base ) : $base ) . '#cp-bo-atividade' ); ?>"<?php echo $filtro === $chave ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $rotulo ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
				<div id="cp-bo-atividade">
					<?php if ( empty( $eventos ) ) : ?>
						<div class="ac-sec-sub" style="padding:10px 0"><?php esc_html_e( 'Sem atividade deste tipo.', 'contabilidade-pro' ); ?></div>
					<?php endif; ?>
					<?php foreach ( $eventos as $evento ) : ?>
						<div class="ac-req-row" style="align-items:center">
							<div>
								<div style="font-size:14px;font-weight:600">
									<?php echo esc_html( $evento['titulo'] ); ?>
									<?php if ( $evento['criado_em'] > $novo_desde ) : ?><span class="ac-badge ac-badge-amber" style="margin-left:8px"><?php esc_html_e( 'Novo', 'contabilidade-pro' ); ?></span><?php endif; ?>
								</div>
								<div class="ac-sec-sub"><?php echo esc_html( $evento['corpo'] ); ?> · <?php echo esc_html( cp_bo_ha( $evento['criado_em'] ) ); ?></div>
							</div>
							<?php if ( ! empty( $evento['empresa_id'] ) && 'cp_empresa' === get_post_type( $evento['empresa_id'] ) ) : ?>
								<a class="ac-btn ac-btn-ghost" style="padding:9px 14px" href="<?php echo esc_url( cp_bo_url_empresa( $evento['empresa_id'], 'mensagens' === cp_bo_calc_tipo_evento( $evento ) ? 'cp-backoffice-mensagens' : 'cp-backoffice-tarefas' ) ); ?>"><?php esc_html_e( 'Abrir', 'contabilidade-pro' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<a href="<?php echo esc_url( add_query_arg( 'vista', 'notificacoes', $base ) ); ?>" style="display:inline-flex;align-items:center;min-height:44px;font-size:13px;font-weight:600"><?php esc_html_e( 'Ver todas as notificações →', 'contabilidade-pro' ); ?></a>
				</div>
			</section>
		</div>

		<div class="ac-dashboard-side">
			<section class="ac-card" style="padding:22px" id="cp-bo-conversas">
				<h2 style="font-size:17px;margin-bottom:4px"><?php esc_html_e( 'Conversas à espera', 'contabilidade-pro' ); ?></h2>
				<?php if ( empty( $r['conversas'] ) ) : ?>
					<div class="ac-sec-sub" style="padding:8px 0"><?php esc_html_e( 'Nenhum cliente à espera de resposta.', 'contabilidade-pro' ); ?></div>
				<?php endif; ?>
				<?php foreach ( array_slice( $r['conversas'], 0, 5 ) as $c ) : ?>
					<a class="ac-req-row" style="color:inherit;align-items:center" href="<?php echo esc_url( cp_bo_url_empresa( $c['empresa_id'], 'cp-backoffice-mensagens', [ 'conversa_id' => $c['id'] ] ) ); ?>">
						<div>
							<div style="font-size:13.5px;font-weight:600"><?php echo esc_html( $c['assunto'] ); ?></div>
							<div class="ac-sec-sub"><?php echo esc_html( $c['empresa'] . ' · ' . cp_bo_ha( $c['em'] ) ); ?></div>
						</div>
						<span class="ac-badge ac-badge-amber"><?php esc_html_e( 'Responder', 'contabilidade-pro' ); ?></span>
					</a>
				<?php endforeach; ?>
			</section>

			<section class="ac-card" style="padding:22px" id="cp-bo-lembretes">
				<h2 style="font-size:17px;margin-bottom:4px"><?php esc_html_e( 'Lembretes em aberto', 'contabilidade-pro' ); ?></h2>
				<?php if ( empty( $r['lembretes_lista'] ) ) : ?>
					<div class="ac-sec-sub" style="padding:8px 0"><?php esc_html_e( 'Nenhum lembrete em aberto.', 'contabilidade-pro' ); ?></div>
				<?php endif; ?>
				<?php foreach ( array_slice( $r['lembretes_lista'], 0, 5 ) as $l ) : ?>
					<a class="ac-req-row" style="color:inherit" href="<?php echo esc_url( cp_bo_url_empresa( $l['empresa_id'] ) ); ?>">
						<div>
							<div style="font-size:13.5px;font-weight:600"><?php echo esc_html( $l['titulo'] ); ?></div>
							<div class="ac-sec-sub"><?php echo esc_html( $l['empresa'] . ' · ' . ( 'cliente' === $l['por'] ? __( 'criado pelo cliente', 'contabilidade-pro' ) : __( 'criado por nós', 'contabilidade-pro' ) ) ); ?></div>
						</div>
					</a>
				<?php endforeach; ?>
			</section>

			<section class="ac-card" style="padding:22px">
				<h2 style="font-size:17px;margin-bottom:4px"><?php esc_html_e( 'Contas no site', 'contabilidade-pro' ); ?></h2>
				<div class="ac-req-row"><span style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Clientes com acesso', 'contabilidade-pro' ); ?></span><span style="font-size:13.5px;font-weight:600"><?php echo esc_html( ( $r['contas']['total'] ? number_format_i18n( $r['contas']['total'] ) : __( 'Nenhum', 'contabilidade-pro' ) ) ); ?></span></div>
				<div class="ac-req-row"><div><div style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Sem entrada registada', 'contabilidade-pro' ); ?></div><div class="ac-sec-sub"><?php esc_html_e( 'conta-se a partir de 16/11/2026', 'contabilidade-pro' ); ?></div></div><span class="ac-badge ac-badge-blue"><?php echo esc_html( ( $r['contas']['sem_registo'] ? number_format_i18n( $r['contas']['sem_registo'] ) : __( 'Nenhum', 'contabilidade-pro' ) ) ); ?></span></div>
				<div class="ac-req-row"><span style="font-size:13.5px;font-weight:600"><?php echo esc_html( sprintf( __( 'Sem entrar há mais de %d dias', 'contabilidade-pro' ), CP_BO_DIAS_INATIVO ) ); ?></span><span class="ac-badge ac-badge-blue"><?php echo esc_html( ( $r['contas']['inativas'] ? number_format_i18n( $r['contas']['inativas'] ) : __( 'Nenhum', 'contabilidade-pro' ) ) ); ?></span></div>
			</section>
		</div>
	</div>
	<?php
}
