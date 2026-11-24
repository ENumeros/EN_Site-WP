<?php
/**
 * Limpeza por mês pedida pela Work (2026-11-24): para libertar espaço no
 * site, a Work pede, para uma empresa e um mês, que se apaguem os ficheiros
 * que o cliente enviou e que ela já recolheu:
 *
 *  - documentos do separador Documentos (anexos com '_cp_doc_cliente',
 *    só os já recebidos pela Work — '_cp_doc_work_recebido');
 *  - anexos de respostas a pendentes, extratos e SAF-T (marcados no envio
 *    com '_cp_resposta_tipo' + '_cp_resposta_item', post_parent = empresa;
 *    os enviados antes desta marca não são encontrados).
 *
 * É a Work que decide o mês e manda a lista (ids dos documentos, e
 * tipo + item das respostas): o WordPress só confirma que cada ficheiro é
 * desta empresa e já foi recolhido, e apaga. Uma resposta ainda em
 * '_cp_pendentes_respostas' (a Work ainda não a recolheu) nunca é apagada.
 * Recibos de vencimento, guias e outros PDFs publicados pela Work ficam
 * fora (decisão de 2026-11-24: o cliente consulta-os).
 *
 * POST cp/v1/limpeza {work_id, simular?, documentos: [id], respostas: [{tipo, item_id}]}
 * → {ok, simulado, apagados, bytes, ignorados: [{id|item_id, motivo}]}
 */

function cp_ac_rest_routes_limpeza() {
	register_rest_route( 'cp/v1', '/limpeza', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_limpeza',
			'permission_callback' => 'cp_ac_rest_permissao',
			'args'                => [
				'work_id'    => [ 'required' => true, 'type' => 'string' ],
				'simular'    => [ 'required' => false, 'type' => 'boolean' ],
				'documentos' => [ 'required' => false, 'type' => 'array' ],
				'respostas'  => [ 'required' => false, 'type' => 'array' ],
			],
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_limpeza' );

/**
 * Pura (testada): o que se apaga.
 *
 * @param array $pedidos_docs      ids pedidos pela Work.
 * @param array $pedidos_respostas [{tipo, item_id}] pedidos pela Work.
 * @param array $docs              [id => recebido(bool)] documentos de cliente desta empresa.
 * @param array $anexos_resposta   [{id, tipo, item_id}] anexos de resposta desta empresa.
 * @param array $por_recolher      ["tipo|item_id"] respostas ainda à espera da Work.
 * @return array {apagar: [ids], ignorados: [{ref, motivo}]}
 */
function cp_ac_calc_limpeza( array $pedidos_docs, array $pedidos_respostas, array $docs, array $anexos_resposta, array $por_recolher ) {
	$apagar    = [];
	$ignorados = [];

	foreach ( $pedidos_docs as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) continue;
		if ( ! array_key_exists( $id, $docs ) ) {
			$ignorados[] = [ 'ref' => (string) $id, 'motivo' => 'nao_existe' ];
		} elseif ( ! $docs[ $id ] ) {
			$ignorados[] = [ 'ref' => (string) $id, 'motivo' => 'por_recolher' ];
		} else {
			$apagar[] = $id;
		}
	}

	foreach ( $pedidos_respostas as $r ) {
		if ( ! is_array( $r ) || empty( $r['tipo'] ) || ! isset( $r['item_id'] ) || '' === (string) $r['item_id'] ) continue;
		$chave = (string) $r['tipo'] . '|' . (string) $r['item_id'];
		if ( in_array( $chave, $por_recolher, true ) ) {
			$ignorados[] = [ 'ref' => $chave, 'motivo' => 'por_recolher' ];
			continue;
		}
		$encontrou = false;
		foreach ( $anexos_resposta as $a ) {
			if ( (string) $a['tipo'] . '|' . (string) $a['item_id'] === $chave ) {
				$apagar[]  = (int) $a['id'];
				$encontrou = true;
			}
		}
		if ( ! $encontrou ) $ignorados[] = [ 'ref' => $chave, 'motivo' => 'sem_ficheiro' ];
	}

	return [ 'apagar' => array_values( array_unique( $apagar ) ), 'ignorados' => $ignorados ];
}

function cp_ac_rest_limpeza( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$simular = (bool) $request->get_param( 'simular' );

	$docs = [];
	foreach ( cp_ac_documentos( $empresa_id ) as $doc ) {
		$docs[ (int) $doc->ID ] = cp_ac_documento_recebido_work( $doc->ID );
	}

	$anexos_resposta = [];
	foreach ( get_posts( [
		'post_type'      => 'attachment',
		'post_parent'    => (int) $empresa_id,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_cp_resposta_item',
		'fields'         => 'ids',
	] ) as $id ) {
		$anexos_resposta[] = [
			'id'      => (int) $id,
			'tipo'    => (string) get_post_meta( $id, '_cp_resposta_tipo', true ),
			'item_id' => (string) get_post_meta( $id, '_cp_resposta_item', true ),
		];
	}

	$por_recolher = [];
	foreach ( cp_ac_pendentes_respostas( $empresa_id ) as $r ) {
		$por_recolher[] = (string) $r['tipo'] . '|' . (string) $r['item_id'];
	}

	$plano = cp_ac_calc_limpeza(
		(array) $request->get_param( 'documentos' ),
		(array) $request->get_param( 'respostas' ),
		$docs,
		$anexos_resposta,
		$por_recolher
	);

	$bytes    = 0;
	$apagados = 0;
	foreach ( $plano['apagar'] as $id ) {
		$caminho = get_attached_file( $id );
		$tamanho = ( $caminho && file_exists( $caminho ) ) ? (int) filesize( $caminho ) : 0;
		if ( $simular ) {
			$bytes += $tamanho;
			$apagados++;
		} elseif ( wp_delete_attachment( $id, true ) ) {
			$bytes += $tamanho;
			$apagados++;
		}
	}

	return rest_ensure_response( [
		'ok'        => true,
		'simulado'  => $simular,
		'apagados'  => $apagados,
		'bytes'     => $bytes,
		'ignorados' => $plano['ignorados'],
	] );
}
