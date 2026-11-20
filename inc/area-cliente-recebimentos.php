<?php
/**
 * Backoffice — Recebimentos (recibos da Entre Números a partir do banco,
 * WORK-175 fase 2, decidido a 2026-11-19).
 *
 *   Work (diário)  ──POST /recebimentos──▶  WP avisa a equipa e mostra no Backoffice
 *   equipa altera e pede "Emitir recibo"  ──▶  pedido guardado aqui
 *   Work (cada ronda) ──GET /recebimentos/pedidos──▶ emite na CentralGest
 *                     ──POST /recebimentos/resultado──▶ "Emitido" / erro
 *
 * O WordPress nunca fala com a CentralGest nem emite nada: guarda o pedido
 * da equipa e mostra o resultado. A Work volta a confirmar tudo na CG antes
 * de emitir e atualiza a conta corrente do cliente na Área de Cliente.
 *
 * Exceção deliberada ao "Backoffice sem ações próprias" (CLAUDE.md):
 * decidida pelo utilizador a 2026-11-19 — a equipa pode mudar o cliente e
 * as faturas antes de pedir a emissão. As faturas de cada empresa vêm da
 * conta corrente que a Work já sincroniza (linhas com por_pagar).
 *
 * Opção `cp_recebimentos` = ['itens' => [id => item]]. Estados de um item:
 * novo → pedido → emitido | erro (pode voltar a pedir); ignorado.
 * Funções `cp_rec_calc_*` são puras (tests/Unit/RecebimentosTest.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const CP_REC_DIAS_EMITIDOS = 30;
const CP_REC_MAX_PUSH      = 3; // mais do que isto de uma vez → um só aviso

// ─── Cálculos puros ─────────────────────────────────────────────────────────

function cp_rec_calc_arred( $v ) {
	return round( (float) $v, 2 );
}

/**
 * Junta o que a Work enviou com o que já cá está. Devolve
 * ['itens' => [...], 'novos' => [ids]].
 * - Novo id → estado "novo".
 * - Id já conhecido: atualiza a sugestão só se ainda está "novo"/"erro";
 *   "pedido" e "ignorado" ficam como estão.
 * - Id que deixou de vir: emitido (se está em $emitidos), mantém-se se está
 *   "pedido" (a Work ainda o vai tratar) ou "emitido" (até 30 dias); os
 *   outros saem (foram lançados por outra via ou ignorados na Work).
 */
function cp_rec_calc_fundir( array $atuais, array $recebidos, array $emitidos, array $ignorados_work, $agora ) {
	$por_emitir = [];
	foreach ( $emitidos as $e ) {
		if ( ! empty( $e['id'] ) ) $por_emitir[ (string) $e['id'] ] = $e;
	}
	$ignorados = array_flip( array_map( 'strval', $ignorados_work ) );
	$itens     = [];
	$novos     = [];
	$vistos    = [];

	foreach ( $recebidos as $r ) {
		if ( empty( $r['id'] ) ) continue;
		$id = (string) $r['id'];
		if ( isset( $ignorados[ $id ] ) ) continue;
		$vistos[ $id ] = true;
		$dados = [
			'id'        => $id,
			'conta_id'  => isset( $r['conta_id'] ) ? (string) $r['conta_id'] : '',
			'conta'     => isset( $r['conta'] ) ? (string) $r['conta'] : '',
			'data'      => isset( $r['data'] ) ? (string) $r['data'] : '',
			'valor'     => cp_rec_calc_arred( isset( $r['valor'] ) ? $r['valor'] : 0 ),
			'descricao' => isset( $r['descricao'] ) ? (string) $r['descricao'] : '',
			'pagador'   => isset( $r['pagador'] ) ? (string) $r['pagador'] : '',
			'work_id'   => isset( $r['work_id'] ) ? (string) $r['work_id'] : '',
			'como'      => isset( $r['como'] ) ? (string) $r['como'] : '',
			'sugestao'  => isset( $r['estado'] ) ? (string) $r['estado'] : '',
			'aviso'     => isset( $r['aviso'] ) ? (string) $r['aviso'] : '',
			'alocacao'  => cp_rec_calc_alocacao( isset( $r['alocacao'] ) ? $r['alocacao'] : [] ),
		];
		if ( isset( $atuais[ $id ] ) ) {
			$item = $atuais[ $id ];
			if ( in_array( $item['estado'], [ 'novo', 'erro' ], true ) ) {
				$item = array_merge( $item, $dados );
			}
		} else {
			$item = $dados + [ 'estado' => 'novo', 'recebido_em' => $agora, 'pedido' => null, 'resultado' => null ];
			$novos[] = $id;
		}
		if ( isset( $por_emitir[ $id ] ) ) {
			$item['estado']    = 'emitido';
			$item['resultado'] = [ 'estado' => 'emitido', 'mensagem' => (string) ( $por_emitir[ $id ]['faturas'] ?? '' ), 'em' => (string) ( $por_emitir[ $id ]['emitido_em'] ?? $agora ) ];
		}
		$itens[ $id ] = $item;
	}

	$limite = gmdate( 'Y-m-d H:i:s', strtotime( $agora ) - CP_REC_DIAS_EMITIDOS * DAY_IN_SECONDS );
	foreach ( $atuais as $id => $item ) {
		$id = (string) $id;
		if ( isset( $vistos[ $id ] ) || isset( $ignorados[ $id ] ) ) continue;
		if ( isset( $por_emitir[ $id ] ) ) {
			$item['estado']    = 'emitido';
			$item['resultado'] = [ 'estado' => 'emitido', 'mensagem' => (string) ( $por_emitir[ $id ]['faturas'] ?? '' ), 'em' => (string) ( $por_emitir[ $id ]['emitido_em'] ?? $agora ) ];
			$itens[ $id ]      = $item;
		} elseif ( 'pedido' === $item['estado'] ) {
			$itens[ $id ] = $item;
		} elseif ( 'emitido' === $item['estado'] && ! empty( $item['resultado']['em'] ) && $item['resultado']['em'] >= $limite ) {
			$itens[ $id ] = $item;
		}
	}
	return [ 'itens' => $itens, 'novos' => $novos ];
}

// [{ext_poc_cab_id, documento?, valor}] limpo; descarta valores <= 0.
function cp_rec_calc_alocacao( $entrada ) {
	$saida = [];
	if ( ! is_array( $entrada ) ) return $saida;
	foreach ( $entrada as $a ) {
		if ( ! is_array( $a ) || empty( $a['ext_poc_cab_id'] ) ) continue;
		$v = cp_rec_calc_arred( isset( $a['valor'] ) ? $a['valor'] : 0 );
		if ( $v <= 0 ) continue;
		$saida[] = [
			'ext_poc_cab_id' => (string) $a['ext_poc_cab_id'],
			'documento'      => isset( $a['documento'] ) ? (string) $a['documento'] : '',
			'valor'          => $v,
		];
	}
	return $saida;
}

/**
 * Valida um pedido de emissão. $abertas = faturas da empresa que se podem
 * escolher ([{ext_poc_cab_id, documento, por_pagar}]). Devolve [] se está
 * bem, senão a lista de erros (texto).
 */
function cp_rec_calc_validar( $valor, array $alocacao, array $abertas ) {
	$erros   = [];
	$por_ext = [];
	foreach ( $abertas as $f ) {
		$por_ext[ (string) $f['ext_poc_cab_id'] ] = $f;
	}
	if ( empty( $alocacao ) ) {
		$erros[] = __( 'Escolha pelo menos uma fatura.', 'contabilidade-pro' );
	}
	$soma = 0.0;
	foreach ( $alocacao as $a ) {
		$ext = (string) $a['ext_poc_cab_id'];
		if ( ! isset( $por_ext[ $ext ] ) ) {
			$erros[] = __( 'Uma das faturas já não está em aberto nesta empresa.', 'contabilidade-pro' );
			continue;
		}
		if ( $a['valor'] > cp_rec_calc_arred( $por_ext[ $ext ]['por_pagar'] ) + 0.005 ) {
			$erros[] = sprintf(
				/* translators: %s: documento */
				__( 'O valor para %s é maior do que o que está em aberto.', 'contabilidade-pro' ),
				$por_ext[ $ext ]['documento']
			);
		}
		$soma += $a['valor'];
	}
	if ( $alocacao && abs( cp_rec_calc_arred( $soma ) - cp_rec_calc_arred( $valor ) ) >= 0.005 ) {
		$erros[] = sprintf(
			/* translators: 1: soma das faturas, 2: valor do movimento */
			__( 'As faturas somam %1$s € e o movimento é de %2$s €.', 'contabilidade-pro' ),
			number_format( cp_rec_calc_arred( $soma ), 2, ',', ' ' ),
			number_format( cp_rec_calc_arred( $valor ), 2, ',', ' ' )
		);
	}
	return $erros;
}

/**
 * Faturas que se podem escolher para uma empresa num recebimento: as da
 * conta corrente com valor por receber, mais as da sugestão da Work quando
 * é a mesma empresa (podem ser de um ano que a conta corrente já não lista).
 */
function cp_rec_calc_abertas( array $linhas_conta_corrente, array $item, $work_id ) {
	$abertas = [];
	foreach ( cp_ac_cc_faturas_em_aberto( $linhas_conta_corrente ) as $l ) {
		$abertas[ $l['ext_poc_cab_id'] ] = [
			'ext_poc_cab_id' => $l['ext_poc_cab_id'], 'documento' => $l['documento'], 'data' => $l['data'],
			'por_pagar'      => cp_rec_calc_arred( $l['por_pagar'] ),
		];
	}
	if ( (string) $work_id === (string) $item['work_id'] ) {
		foreach ( $item['alocacao'] as $a ) {
			if ( ! isset( $abertas[ $a['ext_poc_cab_id'] ] ) ) {
				$abertas[ $a['ext_poc_cab_id'] ] = [
					'ext_poc_cab_id' => $a['ext_poc_cab_id'], 'documento' => $a['documento'], 'data' => '',
					'por_pagar'      => $a['valor'],
				];
			}
		}
	}
	return array_values( $abertas );
}

function cp_rec_calc_contagem( array $itens ) {
	$n = [ 'novo' => 0, 'pedido' => 0, 'emitido' => 0, 'erro' => 0, 'ignorado' => 0 ];
	foreach ( $itens as $i ) {
		if ( isset( $n[ $i['estado'] ] ) ) $n[ $i['estado'] ]++;
	}
	return $n;
}

// ─── Guardar ────────────────────────────────────────────────────────────────

function cp_rec_itens() {
	$dados = get_option( 'cp_recebimentos', [] );
	return ( is_array( $dados ) && isset( $dados['itens'] ) && is_array( $dados['itens'] ) ) ? $dados['itens'] : [];
}

function cp_rec_guardar( array $itens ) {
	update_option( 'cp_recebimentos', [ 'itens' => $itens ], false );
}

function cp_rec_euros( $v ) {
	return number_format( cp_rec_calc_arred( $v ), 2, ',', ' ' ) . ' €';
}

function cp_rec_url( $args = [] ) {
	return add_query_arg( array_merge( [ 'vista' => 'recebimentos' ], $args ), function_exists( 'cp_backoffice_url' ) ? cp_backoffice_url() : home_url( '/backoffice/' ) );
}

// ─── REST (Work ↔ WP) ───────────────────────────────────────────────────────

function cp_rec_rest_routes() {
	register_rest_route( 'cp/v1', '/recebimentos', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_rec_rest_sincronizar',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'recebimentos' => [ 'required' => true, 'type' => 'array' ],
				'emitidos'     => [ 'required' => false, 'type' => 'array' ],
				'ignorados'    => [ 'required' => false, 'type' => 'array' ],
			],
		],
	] );
	register_rest_route( 'cp/v1', '/recebimentos/pedidos', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_rec_rest_pedidos',
			'permission_callback' => 'cp_ac_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/recebimentos/resultado', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_rec_rest_resultado',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [ 'resultados' => [ 'required' => true, 'type' => 'array' ] ],
		],
	] );
}
add_action( 'rest_api_init', 'cp_rec_rest_routes' );

function cp_rec_limpar_texto( $v ) {
	return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
}

function cp_rec_rest_sincronizar( $request ) {
	$recebidos = [];
	foreach ( (array) $request->get_param( 'recebimentos' ) as $r ) {
		if ( ! is_array( $r ) ) continue;
		$limpo = [];
		foreach ( [ 'id', 'conta_id', 'conta', 'data', 'descricao', 'pagador', 'work_id', 'como', 'estado', 'aviso' ] as $c ) {
			$limpo[ $c ] = isset( $r[ $c ] ) ? cp_rec_limpar_texto( $r[ $c ] ) : '';
		}
		$limpo['valor']    = isset( $r['valor'] ) ? (float) $r['valor'] : 0;
		$limpo['alocacao'] = [];
		foreach ( (array) ( $r['alocacao'] ?? [] ) as $a ) {
			if ( ! is_array( $a ) ) continue;
			$limpo['alocacao'][] = [
				'ext_poc_cab_id' => cp_rec_limpar_texto( $a['ext_poc_cab_id'] ?? '' ),
				'documento'      => cp_rec_limpar_texto( $a['documento'] ?? '' ),
				'valor'          => (float) ( $a['valor'] ?? 0 ),
			];
		}
		$recebidos[] = $limpo;
	}
	$emitidos = [];
	foreach ( (array) $request->get_param( 'emitidos' ) as $e ) {
		if ( ! is_array( $e ) ) continue;
		$emitidos[] = [ 'id' => cp_rec_limpar_texto( $e['id'] ?? '' ), 'faturas' => cp_rec_limpar_texto( $e['faturas'] ?? '' ),
			'emitido_em' => cp_rec_limpar_texto( $e['emitido_em'] ?? '' ) ];
	}
	$ignorados = array_map( 'cp_rec_limpar_texto', (array) $request->get_param( 'ignorados' ) );

	$fundido = cp_rec_calc_fundir( cp_rec_itens(), $recebidos, $emitidos, $ignorados, current_time( 'mysql' ) );
	cp_rec_guardar( $fundido['itens'] );
	cp_rec_avisar_novos( $fundido['itens'], $fundido['novos'] );

	return rest_ensure_response( [ 'ok' => true, 'novos' => count( $fundido['novos'] ), 'total' => count( $fundido['itens'] ) ] );
}

function cp_rec_avisar_novos( array $itens, array $novos ) {
	if ( ! $novos || ! function_exists( 'cp_push_notificar_equipa' ) ) return;
	if ( count( $novos ) > CP_REC_MAX_PUSH ) {
		$total = 0;
		foreach ( $novos as $id ) $total += $itens[ $id ]['valor'];
		cp_push_notificar_equipa(
			sprintf( /* translators: %d: número de recebimentos */ __( '%d recebimentos novos no banco', 'contabilidade-pro' ), count( $novos ) ),
			sprintf( /* translators: %s: total */ __( 'Total %s — reveja e emita os recibos.', 'contabilidade-pro' ), cp_rec_euros( $total ) ),
			cp_rec_url(), 0, 'recebimentos'
		);
		return;
	}
	foreach ( $novos as $id ) {
		$item       = $itens[ $id ];
		$empresa_id = $item['work_id'] ? cp_ac_empresa_by_work_id( $item['work_id'] ) : 0;
		$faturas    = implode( ', ', wp_list_pluck( $item['alocacao'], 'documento' ) );
		cp_push_notificar_equipa(
			sprintf( /* translators: %s: valor */ __( 'Recebido %s', 'contabilidade-pro' ), cp_rec_euros( $item['valor'] ) ),
			$faturas
				? sprintf( /* translators: 1: quem pagou, 2: faturas */ __( '%1$s — recibo sugerido: %2$s', 'contabilidade-pro' ), $item['pagador'], $faturas )
				: sprintf( /* translators: %s: quem pagou */ __( '%s — escolha o cliente e as faturas.', 'contabilidade-pro' ), $item['pagador'] ),
			cp_rec_url( [ 'rec' => $id ] ), $empresa_id, 'recebimentos'
		);
	}
}

function cp_rec_rest_pedidos( $request ) {
	$pedidos = [];
	foreach ( cp_rec_itens() as $item ) {
		if ( 'pedido' !== $item['estado'] || empty( $item['pedido'] ) ) continue;
		$pedidos[] = [
			'id'         => $item['id'],
			'conta_id'   => $item['conta_id'],
			'data'       => $item['data'],
			'valor'      => $item['valor'],
			'work_id'    => $item['pedido']['work_id'],
			'alocacao'   => $item['pedido']['alocacao'],
			'pedido_por' => $item['pedido']['por'],
			'pedido_em'  => $item['pedido']['em'],
		];
	}
	return rest_ensure_response( [ 'pedidos' => $pedidos ] );
}

function cp_rec_rest_resultado( $request ) {
	$itens = cp_rec_itens();
	$n     = 0;
	foreach ( (array) $request->get_param( 'resultados' ) as $r ) {
		if ( ! is_array( $r ) || empty( $r['id'] ) ) continue;
		$id = cp_rec_limpar_texto( $r['id'] );
		if ( ! isset( $itens[ $id ] ) || 'pedido' !== $itens[ $id ]['estado'] ) continue;
		$estado   = ( isset( $r['estado'] ) && 'emitido' === $r['estado'] ) ? 'emitido' : 'erro';
		$mensagem = cp_rec_limpar_texto( $r['mensagem'] ?? '' );
		$itens[ $id ]['estado']    = $estado;
		$itens[ $id ]['resultado'] = [ 'estado' => $estado, 'mensagem' => $mensagem, 'em' => current_time( 'mysql' ) ];
		$n++;
		if ( 'erro' === $estado && function_exists( 'cp_push_notificar_equipa' ) ) {
			cp_push_notificar_equipa(
				sprintf( /* translators: %s: valor */ __( 'Recibo não emitido (%s)', 'contabilidade-pro' ), cp_rec_euros( $itens[ $id ]['valor'] ) ),
				$mensagem, cp_rec_url( [ 'rec' => $id ] ), 0, 'recebimentos'
			);
		}
	}
	cp_rec_guardar( $itens );
	return rest_ensure_response( [ 'ok' => true, 'atualizados' => $n ] );
}

// ─── Ações da equipa (admin-post) ───────────────────────────────────────────

function cp_rec_handle_acao() {
	if ( ! is_user_logged_in() || ! cp_backoffice_utilizador_tem_acesso() ) {
		wp_die( esc_html__( 'Sem acesso.', 'contabilidade-pro' ) );
	}
	if ( ! isset( $_POST['cp_rec_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_rec_nonce'] ) ), 'cp_recebimento' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volte à página e tente novamente.', 'contabilidade-pro' ) );
	}
	$id    = isset( $_POST['rec_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rec_id'] ) ) : '';
	$acao  = isset( $_POST['acao'] ) ? sanitize_key( wp_unslash( $_POST['acao'] ) ) : '';
	$itens = cp_rec_itens();
	if ( ! isset( $itens[ $id ] ) ) {
		wp_safe_redirect( cp_rec_url( [ 'cp_msg' => 'nao_existe' ] ) );
		exit;
	}
	$item = $itens[ $id ];
	$user = wp_get_current_user();
	$msg  = '';

	if ( 'pedir' === $acao && in_array( $item['estado'], [ 'novo', 'erro' ], true ) ) {
		$work_id    = isset( $_POST['work_id'] ) ? sanitize_text_field( wp_unslash( $_POST['work_id'] ) ) : '';
		$empresa_id = $work_id ? cp_ac_empresa_by_work_id( $work_id ) : 0;
		$alocacao   = [];
		foreach ( (array) ( $_POST['aloc'] ?? [] ) as $ext => $v ) {
			$alocacao[] = [ 'ext_poc_cab_id' => sanitize_text_field( wp_unslash( $ext ) ), 'valor' => str_replace( ',', '.', sanitize_text_field( wp_unslash( $v ) ) ) ];
		}
		$alocacao = cp_rec_calc_alocacao( $alocacao );
		$abertas  = $empresa_id ? cp_rec_calc_abertas( cp_ac_conta_corrente( $empresa_id )['linhas'], $item, $work_id ) : [];
		$erros    = $empresa_id ? cp_rec_calc_validar( $item['valor'], $alocacao, $abertas ) : [ __( 'Escolha o cliente.', 'contabilidade-pro' ) ];
		if ( $erros ) {
			set_transient( 'cp_rec_erro_' . get_current_user_id(), [ 'id' => $id, 'erros' => $erros ], 120 );
			wp_safe_redirect( cp_rec_url( [ 'rec' => $id ] ) . '#rec-' . rawurlencode( $id ) );
			exit;
		}
		$docs = [];
		foreach ( $abertas as $f ) $docs[ $f['ext_poc_cab_id'] ] = $f['documento'];
		foreach ( $alocacao as &$a ) $a['documento'] = isset( $docs[ $a['ext_poc_cab_id'] ] ) ? $docs[ $a['ext_poc_cab_id'] ] : '';
		unset( $a );
		$item['estado']    = 'pedido';
		$item['resultado'] = null;
		$item['pedido']    = [ 'work_id' => $work_id, 'alocacao' => $alocacao, 'por' => $user->display_name ?: $user->user_login, 'em' => current_time( 'mysql' ) ];
		$msg               = 'pedido';
	} elseif ( 'cancelar' === $acao && 'pedido' === $item['estado'] ) {
		$item['estado'] = 'novo';
		$item['pedido'] = null;
		$msg            = 'cancelado';
	} elseif ( 'ignorar' === $acao && in_array( $item['estado'], [ 'novo', 'erro' ], true ) ) {
		$item['estado'] = 'ignorado';
		$item['motivo'] = isset( $_POST['motivo'] ) ? sanitize_text_field( wp_unslash( $_POST['motivo'] ) ) : '';
		$msg            = 'ignorado';
	} elseif ( 'repor' === $acao && 'ignorado' === $item['estado'] ) {
		$item['estado'] = 'novo';
		$msg            = 'reposto';
	}
	$itens[ $id ] = $item;
	cp_rec_guardar( $itens );
	wp_safe_redirect( cp_rec_url( [ 'cp_msg' => $msg ?: 'sem_efeito' ] ) );
	exit;
}
add_action( 'admin_post_cp_recebimento', 'cp_rec_handle_acao' );

// ─── Vista no Backoffice ────────────────────────────────────────────────────

function cp_rec_contagem_por_tratar() {
	$n = cp_rec_calc_contagem( cp_rec_itens() );
	return $n['novo'] + $n['erro'];
}

function cp_rec_render_vista() {
	$itens = cp_rec_itens();
	uasort( $itens, function ( $a, $b ) { return strcmp( (string) $b['data'], (string) $a['data'] ); } );
	$n          = cp_rec_calc_contagem( $itens );
	$erro_form  = get_transient( 'cp_rec_erro_' . get_current_user_id() );
	if ( $erro_form ) delete_transient( 'cp_rec_erro_' . get_current_user_id() );
	$msg        = isset( $_GET['cp_msg'] ) ? sanitize_key( wp_unslash( $_GET['cp_msg'] ) ) : '';
	$mensagens  = [
		'pedido'    => __( 'Pedido registado. A Work emite o recibo na próxima ronda (até 15 minutos) e o resultado aparece aqui.', 'contabilidade-pro' ),
		'cancelado' => __( 'Pedido cancelado.', 'contabilidade-pro' ),
		'ignorado'  => __( 'Recebimento ignorado.', 'contabilidade-pro' ),
		'reposto'   => __( 'Recebimento reposto.', 'contabilidade-pro' ),
		'nao_existe' => __( 'Esse recebimento já não existe.', 'contabilidade-pro' ),
		'sem_efeito' => __( 'Nada mudou: o recebimento já estava noutro estado.', 'contabilidade-pro' ),
	];

	// Empresas e faturas em aberto de cada uma (da conta corrente sincronizada).
	$empresas = [];
	$abertas  = [];
	foreach ( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $e ) {
		$wid = (string) get_post_meta( $e->ID, '_cp_work_id', true );
		if ( '' === $wid ) continue;
		$empresas[ $wid ] = cp_ac_empresa_label( $e->ID );
		$abertas[ $wid ]  = cp_ac_cc_faturas_em_aberto( cp_ac_conta_corrente( $e->ID )['linhas'] );
	}
	$json_abertas = [];
	foreach ( $abertas as $wid => $lista ) {
		$json_abertas[ $wid ] = array_map( function ( $l ) {
			return [ 'ext' => $l['ext_poc_cab_id'], 'doc' => $l['documento'], 'data' => $l['data'], 'por_pagar' => cp_rec_calc_arred( $l['por_pagar'] ) ];
		}, $lista );
	}
	$cores  = [ 'verde' => 'ac-badge-green', 'amarelo' => 'ac-badge-amber', 'vermelho' => 'ac-badge-red' ];
	$rotulo = [ 'verde' => __( 'Bate certo', 'contabilidade-pro' ), 'amarelo' => __( 'Rever', 'contabilidade-pro' ), 'vermelho' => __( 'Sem cliente', 'contabilidade-pro' ) ];
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Recebimentos', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Entradas no banco da Entre Números ainda sem recibo. Confirme ou altere o cliente e as faturas e peça a emissão: a Work emite na CentralGest e atualiza a conta corrente do cliente.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php if ( $msg && isset( $mensagens[ $msg ] ) ) : ?>
		<div class="ac-card" style="padding:14px 18px;margin-top:16px;font-size:13.5px"><?php echo esc_html( $mensagens[ $msg ] ); ?></div>
	<?php endif; ?>

	<?php
	$por_tratar = array_filter( $itens, function ( $i ) { return in_array( $i['estado'], [ 'novo', 'erro' ], true ); } );
	$pedidos    = array_filter( $itens, function ( $i ) { return 'pedido' === $i['estado']; } );
	$emitidos   = array_filter( $itens, function ( $i ) { return 'emitido' === $i['estado']; } );
	$ignorados  = array_filter( $itens, function ( $i ) { return 'ignorado' === $i['estado']; } );
	?>

	<?php if ( ! $itens ) : ?>
		<?php cp_ac_vazio( __( 'Sem recebimentos', 'contabilidade-pro' ), __( 'A Work envia as entradas no banco uma vez por dia (ou pelo botão "Sincronizar com o Backoffice" em Mais › Recibos).', 'contabilidade-pro' ) ); ?>
	<?php endif; ?>

	<?php if ( $por_tratar ) : ?>
	<?php /* À esquerda a lista; ao escolher um recebimento, o painel à direita
	   mostra o recibo a pedir (como em Mais › Recibos na Work e na Validação
	   do IVA). Os painéis vêm todos daqui; o JS só mostra o escolhido. */ ?>
	<div class="cp-rec-trabalho">
		<div class="cp-rec-lista" aria-label="<?php esc_attr_e( 'Recebimentos por tratar', 'contabilidade-pro' ); ?>">
			<?php foreach ( $por_tratar as $item ) :
				$wid_l = $item['work_id']; ?>
				<button type="button" class="cp-rec-item" data-rec="<?php echo esc_attr( $item['id'] ); ?>">
					<span class="cp-rec-item-l1">
						<?php if ( 'erro' === $item['estado'] ) : ?>
							<span class="ac-badge ac-badge-red"><?php esc_html_e( 'Não emitido', 'contabilidade-pro' ); ?></span>
						<?php elseif ( isset( $cores[ $item['sugestao'] ] ) ) : ?>
							<span class="ac-badge <?php echo esc_attr( $cores[ $item['sugestao'] ] ); ?>"><?php echo esc_html( $rotulo[ $item['sugestao'] ] ); ?></span>
						<?php endif; ?>
						<span class="cp-rec-item-data"><?php echo esc_html( $item['data'] ? date_i18n( 'd/m/Y', strtotime( $item['data'] ) ) : '' ); ?></span>
						<span class="cp-rec-item-valor"><?php echo esc_html( cp_rec_euros( $item['valor'] ) ); ?></span>
					</span>
					<span class="cp-rec-item-quem"><?php echo esc_html( $item['pagador'] ? $item['pagador'] : $item['descricao'] ); ?></span>
					<span class="cp-rec-item-cliente"><?php echo esc_html( isset( $empresas[ $wid_l ] ) ? $empresas[ $wid_l ] : __( '— sem cliente —', 'contabilidade-pro' ) ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<div class="cp-rec-lado" aria-live="polite">
		<?php foreach ( $por_tratar as $item ) :
		$wid     = $item['work_id'];
		$aloc    = [];
		foreach ( $item['alocacao'] as $a ) $aloc[ $a['ext_poc_cab_id'] ] = $a['valor'];
		$linhas  = $wid && isset( $abertas[ $wid ] ) ? cp_rec_calc_abertas( cp_ac_conta_corrente( cp_ac_empresa_by_work_id( $wid ) )['linhas'], $item, $wid ) : [];
		$erros   = ( $erro_form && $erro_form['id'] === $item['id'] ) ? $erro_form['erros'] : [];
		?>
		<div class="ac-card cp-rec" id="rec-<?php echo esc_attr( $item['id'] ); ?>" data-rec="<?php echo esc_attr( $item['id'] ); ?>" data-valor="<?php echo esc_attr( $item['valor'] ); ?>" data-sugerido="<?php echo esc_attr( $wid ); ?>" data-aloc="<?php echo esc_attr( wp_json_encode( $aloc ) ); ?>" hidden>
				<div class="cp-rec-etq"><?php echo esc_html( sprintf( __( 'Recibo a pedir · %1$s · conta %2$s', 'contabilidade-pro' ), $item['data'] ? date_i18n( 'd/m/Y', strtotime( $item['data'] ) ) : '', $item['conta'] ) ); ?></div>
			<div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:4px">
				<span style="font-family:'Fraunces',serif;font-size:24px"><?php echo esc_html( cp_rec_euros( $item['valor'] ) ); ?></span>
				<?php if ( 'erro' === $item['estado'] ) : ?>
					<span class="ac-badge ac-badge-red"><?php esc_html_e( 'Não emitido', 'contabilidade-pro' ); ?></span>
				<?php elseif ( isset( $cores[ $item['sugestao'] ] ) ) : ?>
					<span class="ac-badge <?php echo esc_attr( $cores[ $item['sugestao'] ] ); ?>"><?php echo esc_html( $rotulo[ $item['sugestao'] ] ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ac-sec-sub" style="margin-top:4px;overflow-wrap:anywhere"><?php echo esc_html( $item['descricao'] ); ?></div>
			<?php if ( 'erro' === $item['estado'] && ! empty( $item['resultado']['mensagem'] ) ) : ?>
				<div class="ac-sec-sub" style="color:var(--ac-badge-red-fg);margin-top:8px"><?php echo esc_html( $item['resultado']['mensagem'] ); ?></div>
			<?php endif; ?>
			<?php foreach ( $erros as $e ) : ?>
				<div class="ac-sec-sub" style="color:var(--ac-badge-red-fg);margin-top:8px"><?php echo esc_html( $e ); ?></div>
			<?php endforeach; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-rec-form" style="margin-top:12px">
				<input type="hidden" name="action" value="cp_recebimento">
				<input type="hidden" name="acao" value="pedir">
				<input type="hidden" name="rec_id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php wp_nonce_field( 'cp_recebimento', 'cp_rec_nonce' ); ?>
				<label style="font-size:12.5px;font-weight:600;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<?php esc_html_e( 'Cliente', 'contabilidade-pro' ); ?>
					<select name="work_id" class="cp-rec-empresa" style="padding:8px 10px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px;max-width:100%">
						<option value=""><?php esc_html_e( 'Escolha o cliente…', 'contabilidade-pro' ); ?></option>
						<?php foreach ( $empresas as $e_wid => $label ) : ?>
							<option value="<?php echo esc_attr( $e_wid ); ?>" <?php selected( (string) $wid, (string) $e_wid ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( $item['como'] ) : ?><span class="ac-sec-sub" style="font-weight:400"><?php echo esc_html( sprintf( __( 'sugerido pelo %s', 'contabilidade-pro' ), $item['como'] ) ); ?></span><?php endif; ?>
				</label>
				<?php if ( $item['aviso'] ) : ?><div class="ac-sec-sub" style="margin-top:6px"><?php echo esc_html( $item['aviso'] ); ?></div><?php endif; ?>
				<div class="ac-table-wrap" style="margin-top:10px">
					<table class="ac-table">
						<thead><tr><th><?php esc_html_e( 'Fatura', 'contabilidade-pro' ); ?></th><th><?php esc_html_e( 'Data', 'contabilidade-pro' ); ?></th><th style="text-align:right"><?php esc_html_e( 'Em aberto', 'contabilidade-pro' ); ?></th><th style="text-align:right"><?php esc_html_e( 'A receber', 'contabilidade-pro' ); ?></th></tr></thead>
						<tbody class="cp-rec-faturas">
							<?php if ( ! $linhas ) : ?>
								<tr><td colspan="4" class="ac-sec-sub"><?php echo esc_html( $wid ? __( 'Sem faturas em aberto na conta corrente deste cliente.', 'contabilidade-pro' ) : __( 'Escolha o cliente para ver as faturas em aberto.', 'contabilidade-pro' ) ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $linhas as $f ) : ?>
								<tr>
									<td><?php echo esc_html( $f['documento'] ); ?></td>
									<td><?php echo esc_html( $f['data'] ); ?></td>
									<td style="text-align:right"><?php echo esc_html( cp_rec_euros( $f['por_pagar'] ) ); ?></td>
									<td style="text-align:right"><input type="number" step="0.01" min="0" max="<?php echo esc_attr( $f['por_pagar'] ); ?>" class="cp-rec-aloc" name="aloc[<?php echo esc_attr( $f['ext_poc_cab_id'] ); ?>]" value="<?php echo esc_attr( isset( $aloc[ $f['ext_poc_cab_id'] ] ) ? $aloc[ $f['ext_poc_cab_id'] ] : '' ); ?>" style="width:100px;text-align:right;padding:6px 8px;border-radius:6px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text)"></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap">
					<span class="ac-sec-sub cp-rec-soma"></span>
					<button type="submit" class="ac-btn ac-btn-primary cp-rec-emitir"><?php esc_html_e( 'Emitir recibo', 'contabilidade-pro' ); ?></button>
				</div>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;flex-wrap:wrap">
				<input type="hidden" name="action" value="cp_recebimento">
				<input type="hidden" name="acao" value="ignorar">
				<input type="hidden" name="rec_id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php wp_nonce_field( 'cp_recebimento', 'cp_rec_nonce' ); ?>
				<input type="text" name="motivo" placeholder="<?php esc_attr_e( 'Motivo (opcional)', 'contabilidade-pro' ); ?>" style="padding:7px 10px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13px;min-width:0;flex:0 1 240px">
				<button type="submit" class="ac-btn ac-btn-ghost" style="padding:7px 12px"><?php esc_html_e( 'Ignorar', 'contabilidade-pro' ); ?></button>
			</form>
		</div>
	<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $pedidos ) : ?>
		<h2 style="font-size:17px;margin-top:30px"><?php esc_html_e( 'À espera da Work', 'contabilidade-pro' ); ?></h2>
		<div class="ac-card" style="padding:18px;margin-top:10px">
			<?php foreach ( $pedidos as $item ) : ?>
				<div class="ac-req-row">
					<div>
						<div style="font-size:14px;font-weight:600"><?php echo esc_html( cp_rec_euros( $item['valor'] ) . ' · ' . ( isset( $empresas[ $item['pedido']['work_id'] ] ) ? $empresas[ $item['pedido']['work_id'] ] : $item['pedido']['work_id'] ) ); ?></div>
						<div class="ac-sec-sub"><?php echo esc_html( implode( ', ', wp_list_pluck( $item['pedido']['alocacao'], 'documento' ) ) . ' — ' . sprintf( __( 'pedido por %1$s em %2$s', 'contabilidade-pro' ), $item['pedido']['por'], $item['pedido']['em'] ) ); ?></div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cp_recebimento"><input type="hidden" name="acao" value="cancelar">
						<input type="hidden" name="rec_id" value="<?php echo esc_attr( $item['id'] ); ?>">
						<?php wp_nonce_field( 'cp_recebimento', 'cp_rec_nonce' ); ?>
						<button type="submit" class="ac-btn ac-btn-ghost" style="padding:7px 12px"><?php esc_html_e( 'Cancelar pedido', 'contabilidade-pro' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $emitidos ) : ?>
		<h2 style="font-size:17px;margin-top:30px"><?php esc_html_e( 'Emitidos (últimos 30 dias)', 'contabilidade-pro' ); ?></h2>
		<div class="ac-card" style="padding:18px;margin-top:10px">
			<?php foreach ( $emitidos as $item ) : $wid = ! empty( $item['pedido']['work_id'] ) ? $item['pedido']['work_id'] : $item['work_id']; ?>
				<div class="ac-req-row">
					<div>
						<div style="font-size:14px;font-weight:600"><?php echo esc_html( cp_rec_euros( $item['valor'] ) . ' · ' . ( isset( $empresas[ $wid ] ) ? $empresas[ $wid ] : $item['pagador'] ) ); ?></div>
						<div class="ac-sec-sub"><?php echo esc_html( ( $item['resultado']['mensagem'] ?? '' ) ); ?></div>
					</div>
					<span class="ac-badge ac-badge-green"><?php esc_html_e( 'Emitido', 'contabilidade-pro' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $ignorados ) : ?>
		<h2 style="font-size:17px;margin-top:30px"><?php esc_html_e( 'Ignorados', 'contabilidade-pro' ); ?></h2>
		<div class="ac-card" style="padding:18px;margin-top:10px">
			<?php foreach ( $ignorados as $item ) : ?>
				<div class="ac-req-row">
					<div>
						<div style="font-size:14px;font-weight:600"><?php echo esc_html( cp_rec_euros( $item['valor'] ) . ' · ' . $item['pagador'] ); ?></div>
						<div class="ac-sec-sub"><?php echo esc_html( $item['data'] . ( ! empty( $item['motivo'] ) ? ' — ' . $item['motivo'] : '' ) ); ?></div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cp_recebimento"><input type="hidden" name="acao" value="repor">
						<input type="hidden" name="rec_id" value="<?php echo esc_attr( $item['id'] ); ?>">
						<?php wp_nonce_field( 'cp_recebimento', 'cp_rec_nonce' ); ?>
						<button type="submit" class="ac-btn ac-btn-ghost" style="padding:7px 12px"><?php esc_html_e( 'Repor', 'contabilidade-pro' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<style>
		.cp-rec-trabalho{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:16px;align-items:start;margin-top:16px}
		.cp-rec-lista{display:flex;flex-direction:column;gap:8px}
		.cp-rec-item{display:flex;flex-direction:column;gap:3px;width:100%;text-align:left;padding:12px 14px;font:inherit;color:var(--ac-text);cursor:pointer;background:var(--ac-surface);border:1px solid var(--ac-line);border-radius:12px}
		.cp-rec-item:hover{background:var(--ac-row-hover)}
		.cp-rec-item.sel{border-color:var(--ac-gold);box-shadow:0 0 0 1px var(--ac-gold)}
		.cp-rec-item-l1{display:flex;gap:10px;align-items:center}
		.cp-rec-item-data{font-size:13px;font-weight:600}
		.cp-rec-item-valor{margin-left:auto;font-family:'Fraunces',serif;font-size:17px;white-space:nowrap}
		.cp-rec-item-quem{font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.cp-rec-item-cliente{font-size:12.5px;color:var(--ac-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.cp-rec-lado{position:sticky;top:16px}
		.cp-rec-lado .cp-rec{padding:20px;max-height:calc(100vh - 32px);overflow:auto}
		.cp-rec-lado .cp-rec[hidden]{display:none}
		.cp-rec-etq{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--ac-muted);font-weight:600}
		.cp-rec .ac-table th,.cp-rec .ac-table td{padding:8px 8px;font-size:13px}
		.cp-rec .ac-table{min-width:0}
		.cp-rec .ac-table td{white-space:nowrap}
		.cp-rec .cp-rec-aloc{width:84px !important}
		@media (max-width:1000px){.cp-rec-trabalho{grid-template-columns:minmax(0,1fr)}.cp-rec-lado{position:static}.cp-rec-lado .cp-rec{max-height:none}}
	</style>
	<script type="application/json" id="cp-rec-abertas"><?php echo wp_json_encode( $json_abertas ); ?></script>
	<script>
	(function () {
		var abertas = JSON.parse(document.getElementById('cp-rec-abertas').textContent || '{}');
		var fmt = function (v) { return (Math.round(v * 100) / 100).toLocaleString('pt-PT', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
		function recalcular(caixa) {
			var valor = parseFloat(caixa.dataset.valor), soma = 0;
			caixa.querySelectorAll('.cp-rec-aloc').forEach(function (i) { soma += parseFloat(i.value || '0') || 0; });
			soma = Math.round(soma * 100) / 100;
			var ok = Math.abs(soma - valor) < 0.005;
			var el = caixa.querySelector('.cp-rec-soma');
			el.textContent = 'Selecionado ' + fmt(soma) + ' € de ' + fmt(valor) + ' €' + (ok ? '' : ' — tem de bater certo');
			el.style.color = ok ? '' : 'var(--ac-badge-red-fg)';
			caixa.querySelector('.cp-rec-emitir').disabled = !ok || !caixa.querySelector('.cp-rec-empresa').value;
		}
		document.querySelectorAll('.cp-rec').forEach(function (caixa) {
			var original = caixa.querySelector('.cp-rec-faturas').innerHTML;
			caixa.addEventListener('input', function (e) { if (e.target.classList.contains('cp-rec-aloc')) recalcular(caixa); });
			caixa.querySelector('.cp-rec-empresa').addEventListener('change', function (e) {
				var wid = e.target.value, tbody = caixa.querySelector('.cp-rec-faturas');
				if (wid === caixa.dataset.sugerido) { tbody.innerHTML = original; recalcular(caixa); return; }
				var lista = abertas[wid] || [];
				tbody.innerHTML = '';
				if (!lista.length) {
					var tr = tbody.insertRow(), td = tr.insertCell(); td.colSpan = 4; td.className = 'ac-sec-sub';
					td.textContent = wid ? 'Sem faturas em aberto na conta corrente deste cliente.' : 'Escolha o cliente para ver as faturas em aberto.';
				}
				var resto = parseFloat(caixa.dataset.valor);
				lista.forEach(function (f) {
					var tr = tbody.insertRow();
					tr.insertCell().textContent = f.doc;
					tr.insertCell().textContent = f.data;
					var c = tr.insertCell(); c.style.textAlign = 'right'; c.textContent = fmt(f.por_pagar) + ' €';
					var c2 = tr.insertCell(); c2.style.textAlign = 'right';
					var inp = document.createElement('input');
					inp.type = 'number'; inp.step = '0.01'; inp.min = '0'; inp.max = f.por_pagar; inp.className = 'cp-rec-aloc';
					inp.name = 'aloc[' + f.ext + ']'; inp.style.cssText = 'width:100px;text-align:right;padding:6px 8px;border-radius:6px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text)';
					// Mais antigas primeiro, como na Work.
					var parte = Math.min(resto, f.por_pagar);
					if (parte > 0.004) { inp.value = (Math.round(parte * 100) / 100).toFixed(2); resto = Math.round((resto - parte) * 100) / 100; }
					c2.appendChild(inp);
				});
				recalcular(caixa);
			});
			recalcular(caixa);
		});

		// Lista à esquerda → painel à direita. O escolhido fica no endereço
		// (#rec-<id>), que é também para onde o servidor volta quando um pedido
		// tem erros; se já não existir, abre o primeiro.
		var itens = Array.prototype.slice.call(document.querySelectorAll('.cp-rec-item'));
		var paineis = document.querySelectorAll('.cp-rec');
		function escolher(id, rolar) {
			itens.forEach(function (b) { var s = b.dataset.rec === id; b.classList.toggle('sel', s); b.setAttribute('aria-pressed', s); });
			paineis.forEach(function (p) { p.hidden = p.dataset.rec !== id; });
			history.replaceState(null, '', location.pathname + location.search + '#rec-' + encodeURIComponent(id));
			if (rolar && window.matchMedia('(max-width: 1000px)').matches) {
				document.querySelector('.cp-rec-lado').scrollIntoView({behavior: 'smooth', block: 'start'});
			}
		}
		itens.forEach(function (b) { b.addEventListener('click', function () { escolher(b.dataset.rec, true); }); });
		if (itens.length) {
			var pedido = decodeURIComponent((location.hash || '').replace(/^#rec-/, ''));
			var existe = itens.some(function (b) { return b.dataset.rec === pedido; });
			escolher(existe ? pedido : itens[0].dataset.rec, false);
		}
	})();
	</script>
	<?php
}
