<?php
/**
 * Área de Cliente — Pedir faturas (ferramenta `pedir_faturas`).
 *
 * O cliente pede à Entre Números que emita uma fatura em nome dele: a quem
 * faturar, o quê e por quanto. Nesta fase é só recolha de dados; quem emite
 * é a equipa, na CentralGest (mais tarde talvez pela API). O WordPress nunca
 * fala com a CentralGest: tudo passa pela Work.
 *
 * Fluxo:
 *   Work → POST /cp/v1/faturas/catalogo        artigos e clientes da empresa
 *                                              na CentralGest (substitui tudo)
 *   cliente → admin-post cp_fatura_pedir       grava o pedido (estado "enviado")
 *                                              e avisa a equipa (push)
 *   Work → GET  /cp/v1/faturas/pedidos/novos   pedidos ainda por receber
 *   Work → POST /cp/v1/faturas/pedidos/confirmar  passam a "recebido"
 *   Work → POST /cp/v1/faturas/pedidos/estado  "emitida" (número, data) ou
 *                                              "recusada" (motivo); avisa o cliente
 *   Work → POST /cp/v1/faturas/pedidos/documento  PDF da fatura (multipart)
 *
 * Exceção ao "o WordPress apaga a sua cópia": o pedido fica aqui depois de
 * confirmado, porque o cliente acompanha o estado e descarrega o PDF. A Work
 * continua a ser a fonte de verdade; isto é só o que o cliente vê.
 *
 * O cliente só cancela enquanto a Work ainda não recebeu o pedido.
 *
 * Postmeta no post da empresa:
 *   `_cp_fat_catalogo` = [ artigos => [ {codigo, nome, taxa_iva, preco} ],
 *                          clientes => [ {conta, nome, nif, morada, cod_postal,
 *                          localidade, pais, email} ], atualizado_em ]
 *   `_cp_fat_pedidos`  = [ {id, criado_em, criado_por, criado_por_nome,
 *                          destinatario, linhas, observacoes, totais, estado,
 *                          recebido_em, tratado_em, motivo, fatura} ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const CP_AC_FAT_MAX_LINHAS  = 30;
const CP_AC_FAT_MAX_PEDIDOS = 200;

// ─── Funções puras (testadas em tests/Unit/FaturasTest.php) ─────────────────

/** Taxas de IVA válidas em Portugal (Continente, Madeira e Açores). */
function cp_ac_fat_taxas_iva() {
	return [ 23.0, 22.0, 16.0, 13.0, 12.0, 9.0, 6.0, 5.0, 4.0, 0.0 ];
}

/** "1 234,50" / "1234.5" / 12 → 1234.5; vazio ou inválido → null. */
function cp_ac_fat_numero( $valor ) {
	if ( is_int( $valor ) || is_float( $valor ) ) return (float) $valor;
	$texto = str_replace( [ ' ', "\u{00A0}", '€' ], '', trim( (string) $valor ) );
	if ( '' === $texto ) return null;
	if ( false !== strpos( $texto, ',' ) ) {
		$texto = str_replace( '.', '', $texto ); // "1.234,50": o ponto é de milhares
		$texto = str_replace( ',', '.', $texto );
	}
	return is_numeric( $texto ) ? (float) $texto : null;
}

/** NIF português: 9 algarismos e dígito de controlo certo. */
function cp_ac_fat_nif_valido( $nif ) {
	$nif = preg_replace( '/\s+/', '', (string) $nif );
	if ( ! preg_match( '/^\d{9}$/', $nif ) ) return false;
	$soma = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$soma += (int) $nif[ $i ] * ( 9 - $i );
	}
	$resto    = $soma % 11;
	$controlo = $resto < 2 ? 0 : 11 - $resto;
	return (int) $nif[8] === $controlo;
}

function cp_ac_fat_euros( $valor ) {
	return number_format( (float) $valor, 2, ',', ' ' ) . ' €';
}

/** Totais: cada linha arredonda a base e o IVA a cêntimos, como numa fatura. */
function cp_ac_fat_totais( $linhas ) {
	$base = 0.0;
	$iva  = 0.0;
	foreach ( (array) $linhas as $l ) {
		$b     = round( (float) $l['quantidade'] * (float) $l['preco'], 2 );
		$base += $b;
		$iva  += round( $b * (float) $l['taxa_iva'] / 100, 2 );
	}
	return [ 'base' => round( $base, 2 ), 'iva' => round( $iva, 2 ), 'total' => round( $base + $iva, 2 ) ];
}

/** Artigos vindos da Work (ignora os sem código ou sem nome). */
function cp_ac_fat_normalizar_artigos( $artigos ) {
	$saida = [];
	foreach ( (array) $artigos as $a ) {
		if ( ! is_array( $a ) || empty( $a['codigo'] ) || empty( $a['nome'] ) ) continue;
		$taxa = cp_ac_fat_numero( $a['taxa_iva'] ?? null );
		$saida[] = [
			'codigo'   => sanitize_text_field( $a['codigo'] ),
			'nome'     => sanitize_text_field( $a['nome'] ),
			'taxa_iva' => ( null !== $taxa && in_array( $taxa, cp_ac_fat_taxas_iva(), true ) ) ? $taxa : 23.0,
			'preco'    => max( 0.0, (float) cp_ac_fat_numero( $a['preco'] ?? 0 ) ),
		];
	}
	return $saida;
}

/** Clientes da empresa na CentralGest vindos da Work (ignora os sem conta ou nome). */
function cp_ac_fat_normalizar_clientes( $clientes ) {
	$saida = [];
	foreach ( (array) $clientes as $c ) {
		if ( ! is_array( $c ) || empty( $c['conta'] ) || empty( $c['nome'] ) ) continue;
		$saida[] = cp_ac_fat_limpar_destinatario( $c ) + [ 'conta' => sanitize_text_field( $c['conta'] ) ];
	}
	return $saida;
}

function cp_ac_fat_limpar_destinatario( $d ) {
	$pais = strtoupper( sanitize_text_field( $d['pais'] ?? 'PT' ) );
	return [
		'nome'       => sanitize_text_field( $d['nome'] ?? '' ),
		'nif'        => preg_replace( '/\s+/', '', sanitize_text_field( $d['nif'] ?? '' ) ),
		'morada'     => sanitize_text_field( $d['morada'] ?? '' ),
		'cod_postal' => sanitize_text_field( $d['cod_postal'] ?? '' ),
		'localidade' => sanitize_text_field( $d['localidade'] ?? '' ),
		'pais'       => preg_match( '/^[A-Z]{2}$/', $pais ) ? $pais : 'PT',
		'email'      => sanitize_email( $d['email'] ?? '' ),
	];
}

/**
 * Valida o que o cliente preencheu. $dados = [ destinatario_conta, destinatario
 * => {...}, linhas => [ {artigo, descricao, quantidade, preco, taxa_iva} ],
 * observacoes ]. Devolve [ 'pedido' => [...] | null, 'erros' => [...] ].
 * Um cliente ou artigo escolhido da lista vem sempre do catálogo (nunca do
 * que o browser manda).
 */
function cp_ac_fat_validar_pedido( $dados, $catalogo ) {
	$erros = [];

	$conta = sanitize_text_field( $dados['destinatario_conta'] ?? '' );
	if ( '' !== $conta ) {
		$dest = null;
		foreach ( (array) ( $catalogo['clientes'] ?? [] ) as $c ) {
			if ( (string) $c['conta'] === $conta ) { $dest = $c + [ 'novo' => false ]; break; }
		}
		if ( ! $dest ) $erros[] = __( 'O cliente escolhido já não está na lista. Escolha outra vez.', 'contabilidade-pro' );
	} else {
		$dest = cp_ac_fat_limpar_destinatario( (array) ( $dados['destinatario'] ?? [] ) ) + [ 'conta' => '', 'novo' => true ];
		if ( '' === $dest['nome'] ) $erros[] = __( 'Indique o nome do cliente a quem faturar.', 'contabilidade-pro' );
		if ( '' !== $dest['nif'] && 'PT' === $dest['pais'] && ! cp_ac_fat_nif_valido( $dest['nif'] ) ) {
			$erros[] = __( 'O NIF do cliente não é válido.', 'contabilidade-pro' );
		}
		if ( '' !== ( $dados['destinatario']['email'] ?? '' ) && '' === $dest['email'] ) {
			$erros[] = __( 'O email do cliente não é válido.', 'contabilidade-pro' );
		}
	}

	$artigos = [];
	foreach ( (array) ( $catalogo['artigos'] ?? [] ) as $a ) $artigos[ (string) $a['codigo'] ] = $a;

	$linhas = [];
	foreach ( array_values( (array) ( $dados['linhas'] ?? [] ) ) as $i => $l ) {
		$n         = $i + 1;
		$codigo    = sanitize_text_field( $l['artigo'] ?? '' );
		$descricao = sanitize_textarea_field( $l['descricao'] ?? '' );
		$qtd       = cp_ac_fat_numero( $l['quantidade'] ?? '' );
		$preco     = cp_ac_fat_numero( $l['preco'] ?? '' );
		$taxa      = cp_ac_fat_numero( $l['taxa_iva'] ?? '' );
		if ( '' === $codigo && '' === trim( $descricao ) && null === $preco ) continue; // linha em branco

		if ( '' !== $codigo ) {
			if ( ! isset( $artigos[ $codigo ] ) ) {
				$erros[] = sprintf( __( 'Linha %d: o artigo escolhido já não está na lista.', 'contabilidade-pro' ), $n );
				continue;
			}
			if ( '' === trim( $descricao ) ) $descricao = $artigos[ $codigo ]['nome'];
			if ( null === $taxa ) $taxa = $artigos[ $codigo ]['taxa_iva'];
		}
		if ( '' === trim( $descricao ) ) $erros[] = sprintf( __( 'Linha %d: descreva o que faturar.', 'contabilidade-pro' ), $n );
		if ( null === $qtd || $qtd <= 0 ) $erros[] = sprintf( __( 'Linha %d: a quantidade tem de ser maior que zero.', 'contabilidade-pro' ), $n );
		if ( null === $preco || $preco <= 0 ) $erros[] = sprintf( __( 'Linha %d: indique o preço (sem IVA).', 'contabilidade-pro' ), $n );
		if ( null === $taxa || ! in_array( $taxa, cp_ac_fat_taxas_iva(), true ) ) {
			$erros[] = sprintf( __( 'Linha %d: escolha a taxa de IVA.', 'contabilidade-pro' ), $n );
		}
		$linhas[] = [
			'artigo'     => $codigo,
			'descricao'  => trim( $descricao ),
			'quantidade' => (float) $qtd,
			'preco'      => (float) $preco,
			'taxa_iva'   => (float) $taxa,
		];
	}
	if ( ! $linhas ) $erros[] = __( 'Acrescente pelo menos uma linha com o que faturar.', 'contabilidade-pro' );
	if ( count( $linhas ) > CP_AC_FAT_MAX_LINHAS ) {
		$erros[] = sprintf( __( 'No máximo %d linhas por pedido.', 'contabilidade-pro' ), CP_AC_FAT_MAX_LINHAS );
	}

	$observacoes = sanitize_textarea_field( $dados['observacoes'] ?? '' );
	foreach ( $linhas as $l ) {
		if ( 0.0 === $l['taxa_iva'] && '' === trim( $observacoes ) ) {
			$erros[] = __( 'Há linhas sem IVA: indique nas observações o motivo da isenção.', 'contabilidade-pro' );
			break;
		}
	}

	if ( $erros ) return [ 'pedido' => null, 'erros' => array_values( array_unique( $erros ) ) ];
	return [
		'pedido' => [
			'destinatario' => $dest,
			'linhas'       => $linhas,
			'observacoes'  => $observacoes,
			'totais'       => cp_ac_fat_totais( $linhas ),
		],
		'erros'  => [],
	];
}

/** Rótulo e cor de cada estado, para o cliente. */
function cp_ac_fat_estado_info( $estado ) {
	$mapa = [
		'enviado'  => [ __( 'Enviado', 'contabilidade-pro' ), 'amber' ],
		'recebido' => [ __( 'Em preparação', 'contabilidade-pro' ), 'blue' ],
		'emitida'  => [ __( 'Emitida', 'contabilidade-pro' ), 'green' ],
		'recusada' => [ __( 'Não emitida', 'contabilidade-pro' ), 'red' ],
	];
	return $mapa[ $estado ] ?? [ $estado, 'blue' ];
}

/** O que a Work recebe de cada pedido em /faturas/pedidos/novos. */
function cp_ac_fat_pedido_para_work( $p ) {
	return [
		'id'              => $p['id'],
		'criado_em'       => $p['criado_em'],
		'criado_por_nome' => $p['criado_por_nome'] ?? '',
		'destinatario'    => $p['destinatario'],
		'linhas'          => $p['linhas'],
		'observacoes'     => $p['observacoes'],
		'totais'          => $p['totais'],
	];
}

/** Mantém os mais recentes; só corta pedidos já tratados. */
function cp_ac_fat_podar( $pedidos, $max = CP_AC_FAT_MAX_PEDIDOS ) {
	$pedidos = array_values( (array) $pedidos );
	$excesso = count( $pedidos ) - $max;
	if ( $excesso <= 0 ) return $pedidos;
	$saida = [];
	foreach ( array_reverse( $pedidos ) as $p ) { // do mais antigo para o mais recente
		if ( $excesso > 0 && in_array( $p['estado'], [ 'emitida', 'recusada' ], true ) ) { $excesso--; continue; }
		$saida[] = $p;
	}
	return array_reverse( $saida );
}

// ─── Leitura / escrita ──────────────────────────────────────────────────────

function cp_ac_fat_catalogo( $empresa_id ) {
	$c = get_post_meta( $empresa_id, '_cp_fat_catalogo', true );
	if ( ! is_array( $c ) ) $c = [];
	return $c + [ 'artigos' => [], 'clientes' => [], 'atualizado_em' => '' ];
}

/** Pedidos da empresa, mais recente primeiro. */
function cp_ac_fat_pedidos( $empresa_id ) {
	$p = get_post_meta( $empresa_id, '_cp_fat_pedidos', true );
	return is_array( $p ) ? array_values( $p ) : [];
}

function cp_ac_fat_pedidos_gravar( $empresa_id, $pedidos ) {
	usort( $pedidos, function ( $a, $b ) { return strcmp( (string) $b['criado_em'], (string) $a['criado_em'] ); } );
	update_post_meta( $empresa_id, '_cp_fat_pedidos', cp_ac_fat_podar( $pedidos ) );
}

function cp_ac_fat_url() {
	return add_query_arg( 'vista', 'pedir_faturas', home_url( '/area-de-cliente/' ) );
}

// ─── REST (Work) ────────────────────────────────────────────────────────────

function cp_ac_rest_routes_faturas() {
	$empresa = [ 'work_id' => [ 'required' => true, 'type' => 'string' ] ];
	$pedido  = $empresa + [ 'id' => [ 'required' => true, 'type' => 'string' ] ];
	register_rest_route( 'cp/v1', '/faturas/catalogo', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_faturas_catalogo',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa + [
			'artigos'  => [ 'required' => false, 'type' => 'array' ],
			'clientes' => [ 'required' => false, 'type' => 'array' ],
		],
	] );
	register_rest_route( 'cp/v1', '/faturas/pedidos/novos', [
		'methods'             => 'GET',
		'callback'            => 'cp_ac_rest_faturas_novos',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa,
	] );
	register_rest_route( 'cp/v1', '/faturas/pedidos/confirmar', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_faturas_confirmar',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa + [ 'ids' => [ 'required' => true, 'type' => 'array' ] ],
	] );
	register_rest_route( 'cp/v1', '/faturas/pedidos/estado', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_faturas_estado',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $pedido + [ 'estado' => [ 'required' => true, 'type' => 'string' ] ],
	] );
	register_rest_route( 'cp/v1', '/faturas/pedidos/documento', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_faturas_documento',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $pedido,
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_faturas' );

function cp_ac_rest_faturas_catalogo( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$catalogo = [
		'artigos'       => cp_ac_fat_normalizar_artigos( $request->get_param( 'artigos' ) ),
		'clientes'      => cp_ac_fat_normalizar_clientes( $request->get_param( 'clientes' ) ),
		'atualizado_em' => current_time( 'mysql' ),
	];
	update_post_meta( $empresa_id, '_cp_fat_catalogo', $catalogo );
	return rest_ensure_response( [ 'ok' => true, 'artigos' => count( $catalogo['artigos'] ), 'clientes' => count( $catalogo['clientes'] ) ] );
}

function cp_ac_rest_faturas_novos( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$novos = array_values( array_filter( cp_ac_fat_pedidos( $empresa_id ), function ( $p ) { return 'enviado' === $p['estado']; } ) );
	return rest_ensure_response( [ 'pedidos' => array_map( 'cp_ac_fat_pedido_para_work', array_reverse( $novos ) ) ] );
}

function cp_ac_rest_faturas_confirmar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$ids     = array_map( 'sanitize_text_field', (array) $request->get_param( 'ids' ) );
	$pedidos = cp_ac_fat_pedidos( $empresa_id );
	$n       = 0;
	foreach ( $pedidos as &$p ) {
		if ( 'enviado' === $p['estado'] && in_array( $p['id'], $ids, true ) ) {
			$p['estado']      = 'recebido';
			$p['recebido_em'] = current_time( 'mysql' );
			$n++;
		}
	}
	unset( $p );
	if ( $n ) cp_ac_fat_pedidos_gravar( $empresa_id, $pedidos );
	return rest_ensure_response( [ 'ok' => true, 'confirmados' => $n ] );
}

function cp_ac_rest_faturas_estado( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$estado = sanitize_key( $request->get_param( 'estado' ) );
	if ( ! in_array( $estado, [ 'recebido', 'emitida', 'recusada' ], true ) ) {
		return new WP_Error( 'cp_dados_invalidos', __( 'Estado inválido (recebido, emitida ou recusada).', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	$id      = sanitize_text_field( $request->get_param( 'id' ) );
	$pedidos = cp_ac_fat_pedidos( $empresa_id );
	$indice  = array_search( $id, array_column( $pedidos, 'id' ), true );
	if ( false === $indice ) {
		return new WP_Error( 'cp_pedido_nao_encontrado', __( 'Pedido de fatura não encontrado.', 'contabilidade-pro' ), [ 'status' => 404 ] );
	}

	$p        = &$pedidos[ $indice ];
	$anterior = $p['estado'];
	$p['estado']     = $estado;
	$p['tratado_em'] = in_array( $estado, [ 'emitida', 'recusada' ], true ) ? current_time( 'mysql' ) : '';
	$p['motivo']     = 'recusada' === $estado ? sanitize_textarea_field( (string) $request->get_param( 'motivo' ) ) : '';
	if ( 'emitida' === $estado ) {
		$data         = sanitize_text_field( (string) $request->get_param( 'data' ) );
		$p['fatura']  = [
			'numero'    => sanitize_text_field( (string) $request->get_param( 'numero' ) ),
			'data'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ? $data : '',
			'anexo_id'  => (int) ( $p['fatura']['anexo_id'] ?? 0 ),
			'anexo_url' => $p['fatura']['anexo_url'] ?? '',
		];
	} elseif ( ! empty( $p['fatura']['anexo_id'] ) ) {
		wp_delete_attachment( $p['fatura']['anexo_id'], true );
		$p['fatura'] = [];
	}
	$pedido = $p;
	unset( $p );
	cp_ac_fat_pedidos_gravar( $empresa_id, $pedidos );

	if ( $estado !== $anterior && in_array( $estado, [ 'emitida', 'recusada' ], true ) ) {
		$a_quem = $pedido['destinatario']['nome'];
		cp_push_notificar_empresa(
			$empresa_id,
			__( 'Pedir faturas', 'contabilidade-pro' ),
			'emitida' === $estado
				/* translators: 1: número da fatura, 2: cliente */
				? sprintf( __( 'Fatura %1$s emitida para %2$s', 'contabilidade-pro' ), $pedido['fatura']['numero'], $a_quem )
				/* translators: %s: cliente */
				: sprintf( __( 'O pedido de fatura para %s não foi emitido', 'contabilidade-pro' ), $a_quem ),
			cp_ac_fat_url(),
			'pedir_faturas'
		);
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

function cp_ac_rest_faturas_documento( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$id      = sanitize_text_field( $request->get_param( 'id' ) );
	$pedidos = cp_ac_fat_pedidos( $empresa_id );
	$indice  = array_search( $id, array_column( $pedidos, 'id' ), true );
	if ( false === $indice || 'emitida' !== $pedidos[ $indice ]['estado'] ) {
		return new WP_Error( 'cp_pedido_nao_encontrado', __( 'Não há pedido emitido com este id. Envia primeiro POST /faturas/pedidos/estado.', 'contabilidade-pro' ), [ 'status' => 404 ] );
	}
	$ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
	if ( is_wp_error( $ficheiro ) ) return $ficheiro;

	$fatura = &$pedidos[ $indice ]['fatura'];
	if ( ! empty( $fatura['anexo_id'] ) ) wp_delete_attachment( $fatura['anexo_id'], true );
	$fatura['anexo_id']  = $ficheiro['id'];
	$fatura['anexo_url'] = $ficheiro['url'];
	unset( $fatura );
	cp_ac_fat_pedidos_gravar( $empresa_id, $pedidos );
	return rest_ensure_response( [ 'ok' => true, 'anexo_id' => $ficheiro['id'] ] );
}

// ─── Ações do cliente (admin-post + reload, como os Pagamentos) ─────────────

function cp_ac_fat_pedido_do_post() {
	$lista = function ( $campo ) {
		return isset( $_POST[ $campo ] ) && is_array( $_POST[ $campo ] ) ? array_values( wp_unslash( $_POST[ $campo ] ) ) : [];
	};
	$artigos = $lista( 'linha_artigo' );
	$descr   = $lista( 'linha_descricao' );
	$qtds    = $lista( 'linha_quantidade' );
	$precos  = $lista( 'linha_preco' );
	$taxas   = $lista( 'linha_iva' );
	$linhas  = [];
	$n       = max( count( $artigos ), count( $descr ), count( $qtds ), count( $precos ) );
	for ( $i = 0; $i < min( $n, CP_AC_FAT_MAX_LINHAS + 1 ); $i++ ) {
		$linhas[] = [
			'artigo'     => (string) ( $artigos[ $i ] ?? '' ),
			'descricao'  => (string) ( $descr[ $i ] ?? '' ),
			'quantidade' => (string) ( $qtds[ $i ] ?? '' ),
			'preco'      => (string) ( $precos[ $i ] ?? '' ),
			'taxa_iva'   => (string) ( $taxas[ $i ] ?? '' ),
		];
	}
	$campo = function ( $nome ) { return isset( $_POST[ $nome ] ) ? (string) wp_unslash( $_POST[ $nome ] ) : ''; };
	return [
		'destinatario_conta' => $campo( 'destinatario_conta' ),
		'destinatario'       => [
			'nome'       => $campo( 'dest_nome' ),
			'nif'        => $campo( 'dest_nif' ),
			'morada'     => $campo( 'dest_morada' ),
			'cod_postal' => $campo( 'dest_cod_postal' ),
			'localidade' => $campo( 'dest_localidade' ),
			'pais'       => $campo( 'dest_pais' ),
			'email'      => $campo( 'dest_email' ),
		],
		'linhas'             => $linhas,
		'observacoes'        => $campo( 'observacoes' ),
	];
}

function cp_ac_handle_fatura_pedir() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_fatura_nonce'] ) || ! wp_verify_nonce( $_POST['cp_fatura_nonce'], 'cp_fatura_pedir' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	if ( ! $empresa_id || ! cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, 'pedir_faturas' ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'fatura_sem_acesso', cp_ac_fat_url() ) );
		exit;
	}

	$dados     = cp_ac_fat_pedido_do_post();
	$resultado = cp_ac_fat_validar_pedido( $dados, cp_ac_fat_catalogo( $empresa_id ) );
	if ( $resultado['erros'] ) {
		// O formulário volta preenchido: os dados ficam um minuto, só para este utilizador.
		set_transient( 'cp_fat_form_' . $user_id, [ 'erros' => $resultado['erros'], 'dados' => $dados ], 120 );
		wp_safe_redirect( add_query_arg( 'cp_erro', 'fatura_invalida', cp_ac_fat_url() ) . '#cp-fat-form' );
		exit;
	}

	$utilizador = wp_get_current_user();
	$pedido     = $resultado['pedido'] + [
		'id'              => wp_generate_uuid4(),
		'criado_em'       => current_time( 'mysql' ),
		'criado_por'      => $user_id,
		'criado_por_nome' => $utilizador->display_name,
		'estado'          => 'enviado',
		'recebido_em'     => '',
		'tratado_em'      => '',
		'motivo'          => '',
		'fatura'          => [],
	];
	$pedidos = cp_ac_fat_pedidos( $empresa_id );
	$pedidos[] = $pedido;
	cp_ac_fat_pedidos_gravar( $empresa_id, $pedidos );

	if ( ! cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		cp_push_notificar_equipa(
			__( 'Pedido de fatura', 'contabilidade-pro' ),
			sprintf(
				/* translators: 1: empresa, 2: cliente a faturar, 3: total */
				__( '%1$s pede uma fatura para %2$s (%3$s)', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				$pedido['destinatario']['nome'],
				cp_ac_fat_euros( $pedido['totais']['total'] )
			),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id,
			'pedir_faturas'
		);
	}
	wp_safe_redirect( add_query_arg( 'cp_ok', 'fatura_pedida', cp_ac_fat_url() ) );
	exit;
}
add_action( 'admin_post_cp_fatura_pedir', 'cp_ac_handle_fatura_pedir' );

function cp_ac_handle_fatura_cancelar() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_fatura_nonce'] ) || ! wp_verify_nonce( $_POST['cp_fatura_nonce'], 'cp_fatura_cancelar' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	$id         = isset( $_POST['pedido_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pedido_id'] ) ) : '';
	$cancelado  = false;
	if ( $empresa_id && cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, 'pedir_faturas' ) ) {
		$pedidos = cp_ac_fat_pedidos( $empresa_id );
		$resto   = array_values( array_filter( $pedidos, function ( $p ) use ( $id ) {
			return ! ( $p['id'] === $id && 'enviado' === $p['estado'] ); // só antes de a Work o receber
		} ) );
		if ( count( $resto ) < count( $pedidos ) ) {
			cp_ac_fat_pedidos_gravar( $empresa_id, $resto );
			$cancelado = true;
		}
	}
	wp_safe_redirect( add_query_arg( $cancelado ? 'cp_ok' : 'cp_erro', $cancelado ? 'fatura_cancelada' : 'fatura_nao_cancelada', cp_ac_fat_url() ) );
	exit;
}
add_action( 'admin_post_cp_fatura_cancelar', 'cp_ac_handle_fatura_cancelar' );

// ─── Vista ──────────────────────────────────────────────────────────────────

function cp_ac_vista_pedir_faturas() {
	$empresa_id = cp_ac_empresa_ativa();
	$catalogo   = cp_ac_fat_catalogo( $empresa_id );
	$pedidos    = cp_ac_fat_pedidos( $empresa_id );
	$user_id    = get_current_user_id();
	$form       = get_transient( 'cp_fat_form_' . $user_id );
	if ( $form ) delete_transient( 'cp_fat_form_' . $user_id );
	$erros      = ( isset( $_GET['cp_erro'] ) && 'fatura_invalida' === $_GET['cp_erro'] && $form ) ? (array) $form['erros'] : [];
	$anterior   = $erros ? (array) $form['dados'] : [];
	$ok         = isset( $_GET['cp_ok'] ) ? sanitize_key( wp_unslash( $_GET['cp_ok'] ) ) : '';
	$erro       = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	$taxas      = cp_ac_fat_taxas_iva();
	$fmt_taxa   = function ( $t ) { return rtrim( rtrim( number_format( (float) $t, 1, ',', '' ), '0' ), ',' ) . '%'; };
	$dest_ant   = (array) ( $anterior['destinatario'] ?? [] );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Pedir faturas', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Diga-nos a quem faturar, o quê e por quanto. Nós emitimos a fatura e ela aparece aqui.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php if ( 'fatura_pedida' === $ok ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-green-fg)"><?php esc_html_e( 'Pedido enviado. Avisamos quando a fatura estiver emitida.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'fatura_cancelada' === $ok ) : ?>
		<div class="ac-card" style="padding:14px 18px"><?php esc_html_e( 'Pedido cancelado.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'fatura_nao_cancelada' === $erro ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-red-fg)"><?php esc_html_e( 'Este pedido já está a ser tratado e não pode ser cancelado aqui. Envie-nos uma mensagem.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'fatura_sem_acesso' === $erro ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-red-fg)"><?php esc_html_e( 'Não tem acesso a pedir faturas nesta empresa.', 'contabilidade-pro' ); ?></div>
	<?php endif; ?>

	<div class="ac-card" style="padding:22px" id="cp-fat-form">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Novo pedido', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Os preços são sem IVA.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>

		<?php if ( $erros ) : ?>
			<div style="color:var(--ac-badge-red-fg);font-size:13.5px;margin-bottom:14px">
				<?php foreach ( $erros as $e ) : ?><div><?php echo esc_html( $e ); ?></div><?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ac-fat-form">
			<?php wp_nonce_field( 'cp_fatura_pedir', 'cp_fatura_nonce' ); ?>
			<input type="hidden" name="action" value="cp_fatura_pedir">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">

			<div class="ac-fat-bloco">
				<div class="ac-fat-bloco-titulo"><?php esc_html_e( 'A quem faturar', 'contabilidade-pro' ); ?></div>
				<?php $conta_ant = (string) ( $anterior['destinatario_conta'] ?? '' ); ?>
				<div class="ac-field ac-fat-largo">
					<label for="cp-fat-cliente"><?php esc_html_e( 'Cliente', 'contabilidade-pro' ); ?></label>
					<?php if ( count( $catalogo['clientes'] ) > 8 ) : ?>
						<input type="search" id="cp-fat-cliente-procurar" placeholder="<?php esc_attr_e( 'Procurar por nome ou NIF…', 'contabilidade-pro' ); ?>" style="width:100%;margin-bottom:6px" autocomplete="off">
					<?php endif; ?>
					<select name="destinatario_conta" id="cp-fat-cliente" style="width:100%">
						<option value=""><?php esc_html_e( '+ Novo cliente (preencher os dados)', 'contabilidade-pro' ); ?></option>
						<?php foreach ( $catalogo['clientes'] as $c ) : ?>
							<option value="<?php echo esc_attr( $c['conta'] ); ?>" <?php selected( $conta_ant, (string) $c['conta'] ); ?>
								data-resumo="<?php echo esc_attr( trim( implode( ' · ', array_filter( [ $c['nif'] ? 'NIF ' . $c['nif'] : '', trim( $c['morada'] . ' ' . $c['cod_postal'] . ' ' . $c['localidade'] ) ] ) ) ) ); ?>">
								<?php echo esc_html( $c['nome'] . ( $c['nif'] ? ' — ' . $c['nif'] : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="ac-sec-sub" id="cp-fat-cliente-resumo"></div>
				</div>

				<div class="ac-fat-novo" id="cp-fat-novo">
					<div class="ac-field ac-fat-largo"><label for="cp-fat-nome"><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-nome" name="dest_nome" value="<?php echo esc_attr( $dest_ant['nome'] ?? '' ); ?>" maxlength="150"></div>
					<div class="ac-field"><label for="cp-fat-nif"><?php esc_html_e( 'NIF', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-nif" name="dest_nif" value="<?php echo esc_attr( $dest_ant['nif'] ?? '' ); ?>" inputmode="numeric" maxlength="20" placeholder="<?php esc_attr_e( 'Vazio = consumidor final', 'contabilidade-pro' ); ?>"></div>
					<div class="ac-field"><label for="cp-fat-email"><?php esc_html_e( 'Email (opcional)', 'contabilidade-pro' ); ?></label>
						<input type="email" id="cp-fat-email" name="dest_email" value="<?php echo esc_attr( $dest_ant['email'] ?? '' ); ?>"></div>
					<div class="ac-field ac-fat-largo"><label for="cp-fat-morada"><?php esc_html_e( 'Morada', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-morada" name="dest_morada" value="<?php echo esc_attr( $dest_ant['morada'] ?? '' ); ?>" maxlength="200"></div>
					<div class="ac-field"><label for="cp-fat-cp"><?php esc_html_e( 'Código postal', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-cp" name="dest_cod_postal" value="<?php echo esc_attr( $dest_ant['cod_postal'] ?? '' ); ?>" maxlength="20" placeholder="0000-000"></div>
					<div class="ac-field"><label for="cp-fat-localidade"><?php esc_html_e( 'Localidade', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-localidade" name="dest_localidade" value="<?php echo esc_attr( $dest_ant['localidade'] ?? '' ); ?>" maxlength="80"></div>
					<div class="ac-field"><label for="cp-fat-pais"><?php esc_html_e( 'País', 'contabilidade-pro' ); ?></label>
						<input type="text" id="cp-fat-pais" name="dest_pais" value="<?php echo esc_attr( $dest_ant['pais'] ?? 'PT' ); ?>" maxlength="2" style="text-transform:uppercase" title="<?php esc_attr_e( 'Código do país (PT, ES, FR…)', 'contabilidade-pro' ); ?>"></div>
				</div>
			</div>

			<div class="ac-fat-bloco">
				<div class="ac-fat-bloco-titulo"><?php esc_html_e( 'O que faturar', 'contabilidade-pro' ); ?></div>
				<div class="ac-fat-linhas-cab" aria-hidden="true">
					<span><?php esc_html_e( 'Artigo', 'contabilidade-pro' ); ?></span>
					<span><?php esc_html_e( 'Descrição', 'contabilidade-pro' ); ?></span>
					<span><?php esc_html_e( 'Qtd.', 'contabilidade-pro' ); ?></span>
					<span><?php esc_html_e( 'Preço s/ IVA', 'contabilidade-pro' ); ?></span>
					<span><?php esc_html_e( 'IVA', 'contabilidade-pro' ); ?></span>
					<span style="text-align:right"><?php esc_html_e( 'Total', 'contabilidade-pro' ); ?></span>
					<span></span>
				</div>
				<div id="cp-fat-linhas">
					<?php
					$linhas_ant = $anterior['linhas'] ?? [];
					if ( ! $linhas_ant ) $linhas_ant = [ [] ];
					foreach ( $linhas_ant as $l ) :
						$art_ant  = (string) ( $l['artigo'] ?? '' );
						$taxa_ant = cp_ac_fat_numero( $l['taxa_iva'] ?? '' );
						?>
						<div class="ac-fat-linha">
							<div class="ac-field"><label class="ac-fat-rotulo-movel"><?php esc_html_e( 'Artigo', 'contabilidade-pro' ); ?></label>
								<select name="linha_artigo[]" class="cp-fat-artigo">
									<option value=""><?php esc_html_e( 'Outro (escrever)', 'contabilidade-pro' ); ?></option>
									<?php foreach ( $catalogo['artigos'] as $a ) : ?>
										<option value="<?php echo esc_attr( $a['codigo'] ); ?>" <?php selected( $art_ant, (string) $a['codigo'] ); ?>><?php echo esc_html( $a['nome'] ); ?></option>
									<?php endforeach; ?>
								</select></div>
							<div class="ac-field"><label class="ac-fat-rotulo-movel"><?php esc_html_e( 'Descrição', 'contabilidade-pro' ); ?></label>
								<input type="text" name="linha_descricao[]" class="cp-fat-descricao" value="<?php echo esc_attr( $l['descricao'] ?? '' ); ?>" maxlength="300"></div>
							<div class="ac-field"><label class="ac-fat-rotulo-movel"><?php esc_html_e( 'Qtd.', 'contabilidade-pro' ); ?></label>
								<input type="text" name="linha_quantidade[]" class="cp-fat-qtd" value="<?php echo esc_attr( $l['quantidade'] ?? '1' ); ?>" inputmode="decimal"></div>
							<div class="ac-field"><label class="ac-fat-rotulo-movel"><?php esc_html_e( 'Preço s/ IVA', 'contabilidade-pro' ); ?></label>
								<input type="text" name="linha_preco[]" class="cp-fat-preco" value="<?php echo esc_attr( $l['preco'] ?? '' ); ?>" inputmode="decimal" placeholder="0,00"></div>
							<div class="ac-field"><label class="ac-fat-rotulo-movel"><?php esc_html_e( 'IVA', 'contabilidade-pro' ); ?></label>
								<select name="linha_iva[]" class="cp-fat-iva">
									<?php foreach ( $taxas as $t ) : ?>
										<option value="<?php echo esc_attr( $t ); ?>" <?php selected( null === $taxa_ant ? 23.0 : $taxa_ant, $t ); ?>><?php echo esc_html( $fmt_taxa( $t ) ); ?></option>
									<?php endforeach; ?>
								</select></div>
							<div class="ac-fat-total-linha cp-fat-total-linha">—</div>
							<button type="button" class="ac-icon-btn-sm cp-fat-remover" title="<?php esc_attr_e( 'Remover linha', 'contabilidade-pro' ); ?>" aria-label="<?php esc_attr_e( 'Remover linha', 'contabilidade-pro' ); ?>">
								<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"></path></svg>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ac-btn ac-btn-ghost" id="cp-fat-adicionar" style="padding:8px 14px;font-size:13px;margin-top:4px">+ <?php esc_html_e( 'Adicionar linha', 'contabilidade-pro' ); ?></button>

				<div class="ac-fat-totais">
					<div><span><?php esc_html_e( 'Total sem IVA', 'contabilidade-pro' ); ?></span><strong id="cp-fat-base">0,00 €</strong></div>
					<div><span><?php esc_html_e( 'IVA', 'contabilidade-pro' ); ?></span><strong id="cp-fat-iva">0,00 €</strong></div>
					<div class="ac-fat-total"><span><?php esc_html_e( 'Total', 'contabilidade-pro' ); ?></span><strong id="cp-fat-total">0,00 €</strong></div>
				</div>
			</div>

			<div class="ac-field" style="margin-bottom:16px">
				<label for="cp-fat-obs"><?php esc_html_e( 'Observações (opcional)', 'contabilidade-pro' ); ?></label>
				<textarea id="cp-fat-obs" name="observacoes" rows="3" style="width:100%" placeholder="<?php esc_attr_e( 'Data a usar, referência de encomenda, motivo de isenção de IVA…', 'contabilidade-pro' ); ?>"><?php echo esc_textarea( $anterior['observacoes'] ?? '' ); ?></textarea>
			</div>

			<button type="submit" class="ac-btn ac-btn-primary"><?php esc_html_e( 'Enviar pedido', 'contabilidade-pro' ); ?></button>
		</form>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head"><h3 style="font-size:16px"><?php esc_html_e( 'Os seus pedidos', 'contabilidade-pro' ); ?></h3></div>
		<?php if ( ! $pedidos ) : ?>
			<div style="color:var(--ac-muted);font-size:14px"><?php esc_html_e( 'Ainda não pediu nenhuma fatura.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<div class="ac-table-wrap">
				<table class="ac-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Pedido em', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'A quem', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'O quê', 'contabilidade-pro' ); ?></th>
							<th style="text-align:right"><?php esc_html_e( 'Total', 'contabilidade-pro' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $pedidos as $p ) :
						list( $rotulo, $cor ) = cp_ac_fat_estado_info( $p['estado'] );
						$o_que = implode( ', ', array_map( function ( $l ) { return $l['descricao']; }, (array) $p['linhas'] ) );
						?>
						<tr>
							<td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y', $p['criado_em'] ) ); ?></td>
							<td><?php echo esc_html( $p['destinatario']['nome'] ); ?></td>
							<td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr( $o_que ); ?>"><?php echo esc_html( $o_que ); ?></td>
							<td style="text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap"><?php echo esc_html( cp_ac_fat_euros( $p['totais']['total'] ) ); ?></td>
							<td>
								<span class="ac-badge ac-badge-<?php echo esc_attr( $cor ); ?>"><?php echo esc_html( $rotulo ); ?></span>
								<?php if ( 'emitida' === $p['estado'] && ! empty( $p['fatura']['numero'] ) ) : ?>
									<div class="ac-sec-sub"><?php echo esc_html( $p['fatura']['numero'] ); ?></div>
								<?php elseif ( 'recusada' === $p['estado'] && '' !== $p['motivo'] ) : ?>
									<div class="ac-sec-sub"><?php echo esc_html( $p['motivo'] ); ?></div>
								<?php endif; ?>
							</td>
							<td style="white-space:nowrap">
								<?php if ( ! empty( $p['fatura']['anexo_url'] ) ) : ?>
									<a class="ac-btn ac-btn-ghost" style="padding:7px 12px;font-size:12.5px;text-decoration:none" href="<?php echo esc_url( $p['fatura']['anexo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver fatura', 'contabilidade-pro' ); ?></a>
								<?php elseif ( 'enviado' === $p['estado'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Cancelar este pedido?', 'contabilidade-pro' ) ); ?>')">
										<?php wp_nonce_field( 'cp_fatura_cancelar', 'cp_fatura_nonce' ); ?>
										<input type="hidden" name="action" value="cp_fatura_cancelar">
										<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
										<input type="hidden" name="pedido_id" value="<?php echo esc_attr( $p['id'] ); ?>">
										<button type="submit" class="ac-pag-desfazer"><?php esc_html_e( 'Cancelar', 'contabilidade-pro' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<script type="application/json" id="cp-fat-artigos"><?php echo wp_json_encode( array_column( $catalogo['artigos'], null, 'codigo' ) ?: new stdClass() ); ?></script>
	<script>
	(function () {
		var artigos = JSON.parse(document.getElementById('cp-fat-artigos').textContent || '{}');
		var linhas = document.getElementById('cp-fat-linhas');
		var cliente = document.getElementById('cp-fat-cliente');
		var novo = document.getElementById('cp-fat-novo');
		var resumo = document.getElementById('cp-fat-cliente-resumo');
		var procurar = document.getElementById('cp-fat-cliente-procurar');

		function num(v) {
			v = String(v || '').replace(/[\s €]/g, '');
			if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
			var n = parseFloat(v);
			return isNaN(n) ? 0 : n;
		}
		function euros(v) {
			return v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' €';
		}
		function r2(v) { return Math.round(v * 100) / 100; }
		function recalcular() {
			var base = 0, iva = 0;
			linhas.querySelectorAll('.ac-fat-linha').forEach(function (l) {
				var b = r2(num(l.querySelector('.cp-fat-qtd').value) * num(l.querySelector('.cp-fat-preco').value));
				base += b;
				iva += r2(b * num(l.querySelector('.cp-fat-iva').value) / 100);
				l.querySelector('.cp-fat-total-linha').textContent = b ? euros(b) : '—';
			});
			document.getElementById('cp-fat-base').textContent = euros(r2(base));
			document.getElementById('cp-fat-iva').textContent = euros(r2(iva));
			document.getElementById('cp-fat-total').textContent = euros(r2(base + iva));
		}
		function escolherArtigo(l) {
			var a = artigos[l.querySelector('.cp-fat-artigo').value];
			if (!a) return;
			var d = l.querySelector('.cp-fat-descricao'), p = l.querySelector('.cp-fat-preco');
			d.value = a.nome;
			if (a.preco > 0 && !num(p.value)) p.value = a.preco.toFixed(2).replace('.', ',');
			l.querySelector('.cp-fat-iva').value = String(a.taxa_iva);
			recalcular();
		}
		function ligar(l) {
			l.querySelector('.cp-fat-artigo').addEventListener('change', function () { escolherArtigo(l); });
			l.querySelectorAll('input,select').forEach(function (c) { c.addEventListener('input', recalcular); });
			l.querySelector('.cp-fat-remover').addEventListener('click', function () {
				if (linhas.children.length > 1) { l.remove(); } else {
					l.querySelectorAll('input').forEach(function (c) { c.value = c.classList.contains('cp-fat-qtd') ? '1' : ''; });
					l.querySelector('.cp-fat-artigo').value = '';
				}
				recalcular();
			});
		}
		linhas.querySelectorAll('.ac-fat-linha').forEach(ligar);
		document.getElementById('cp-fat-adicionar').addEventListener('click', function () {
			if (linhas.children.length >= <?php echo (int) CP_AC_FAT_MAX_LINHAS; ?>) return;
			var n = linhas.firstElementChild.cloneNode(true);
			n.querySelectorAll('input').forEach(function (c) { c.value = c.classList.contains('cp-fat-qtd') ? '1' : ''; });
			n.querySelector('.cp-fat-artigo').value = '';
			n.querySelector('.cp-fat-iva').value = '23';
			linhas.appendChild(n);
			ligar(n);
			recalcular();
			n.querySelector('.cp-fat-artigo').focus();
		});

		function mudarCliente() {
			var op = cliente.options[cliente.selectedIndex];
			var e_novo = !cliente.value;
			novo.hidden = !e_novo;
			resumo.textContent = e_novo ? '' : (op.getAttribute('data-resumo') || '');
		}
		cliente.addEventListener('change', mudarCliente);
		if (procurar) {
			procurar.addEventListener('input', function () {
				var q = procurar.value.trim().toLowerCase(), primeiro = null;
				Array.prototype.forEach.call(cliente.options, function (o) {
					var ver = !o.value || !q || o.textContent.toLowerCase().indexOf(q) >= 0;
					o.hidden = !ver;
					if (ver && o.value && !primeiro) primeiro = o;
				});
				if (q && primeiro) { cliente.value = primeiro.value; mudarCliente(); }
			});
		}
		mudarCliente();
		recalcular();
	})();
	</script>
	<?php
}
