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
 *
 * Também, quando a equipa o indica (`mes` + `persistentes`), os PDFs que a
 * Work publicou e que o cliente consulta durante um tempo: documentos da
 * conta corrente e recibos de vencimento desse mês. Aqui é o próprio site
 * que os encontra pelo mês (a data da linha / o ano e mês do recibo).
 *
 * Nada é apagado sozinho: é sempre a equipa que pede (decisão de
 * 2026-11-24). Depois, o cliente continua a ver a linha, sem o ficheiro:
 * nos documentos fica o anexo (só se apagam os ficheiros do disco, meta
 * '_cp_ficheiro_removido'); na conta corrente e nos recibos a entrada fica
 * com 'ficheiro_removido' e sem anexo_url. Os anexos de respostas não têm
 * linha própria (o cliente vê o item como recebido/tratado): apagam-se.
 * Guias de pagamento ficam sempre fora.
 *
 * POST cp/v1/limpeza {work_id, simular?, documentos: [id], respostas: [{tipo, item_id}],
 *                     mes?: "AAAA-MM", persistentes?: ["conta_corrente", "recibos_vencimento"]}
 * → {ok, simulado, apagados, bytes, ignorados: [{ref, motivo}]}
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
				'documentos'   => [ 'required' => false, 'type' => 'array' ],
				'respostas'    => [ 'required' => false, 'type' => 'array' ],
				'mes'          => [ 'required' => false, 'type' => 'string' ],
				'persistentes' => [ 'required' => false, 'type' => 'array' ],
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
 * @param array $docs              [id => 'recebido'|'por_recolher'|'removido'] documentos de cliente desta empresa.
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
		} elseif ( 'removido' === $docs[ $id ] ) {
			$ignorados[] = [ 'ref' => (string) $id, 'motivo' => 'ja_removido' ];
		} elseif ( 'recebido' !== $docs[ $id ] ) {
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

// Pura (testada): "AAAA-MM" de uma data da conta corrente — aceita
// AAAA-MM-DD (com ou sem hora) e DD/MM/AAAA ou DD-MM-AAAA. '' se não der.
function cp_ac_calc_mes_da_data( $data ) {
	$data = trim( (string) $data );
	if ( preg_match( '/^(\d{4})-(\d{2})-\d{2}/', $data, $m ) ) return $m[1] . '-' . $m[2];
	if ( preg_match( '/^\d{2}[\/-](\d{2})[\/-](\d{4})/', $data, $m ) ) return $m[2] . '-' . $m[1];
	return '';
}

/**
 * Pura (testada): documentos da conta corrente e recibos de vencimento de
 * um mês. Devolve os anexos a apagar e as listas já sem esses ficheiros
 * (a entrada fica, com 'ficheiro_removido' = $agora e sem anexo).
 *
 * @return array {anexos: [ids], linhas: [...], recibos: [...], n_cc, n_recibos}
 */
function cp_ac_calc_limpeza_persistentes( array $linhas, array $recibos, $mes, array $pecas, $agora ) {
	$anexos = [];
	$n_cc   = 0;
	$n_rec  = 0;
	if ( in_array( 'conta_corrente', $pecas, true ) ) {
		foreach ( $linhas as &$l ) {
			if ( empty( $l['anexo_id'] ) || cp_ac_calc_mes_da_data( $l['data'] ?? '' ) !== $mes ) continue;
			if ( ! in_array( (int) $l['anexo_id'], $anexos, true ) ) {
				$anexos[] = (int) $l['anexo_id'];
				$n_cc++;
			}
			$l['anexo_id']          = '';
			$l['anexo_url']         = '';
			$l['ficheiro_removido'] = $agora;
		}
		unset( $l );
	}
	if ( in_array( 'recibos_vencimento', $pecas, true ) ) {
		foreach ( $recibos as &$r ) {
			if ( empty( $r['anexo_id'] ) || sprintf( '%04d-%02d', (int) $r['ano'], (int) $r['mes'] ) !== $mes ) continue;
			$anexos[]               = (int) $r['anexo_id'];
			$n_rec++;
			$r['anexo_id']          = '';
			$r['anexo_url']         = '';
			$r['ficheiro_removido'] = $agora;
		}
		unset( $r );
	}
	return [ 'anexos' => $anexos, 'linhas' => $linhas, 'recibos' => $recibos, 'n_cc' => $n_cc, 'n_recibos' => $n_rec ];
}

// Apaga do disco os ficheiros de um anexo (original e tamanhos) mas mantém
// o anexo, marcado com '_cp_ficheiro_removido' — para o cliente continuar a
// ver a linha do documento, sem o poder abrir.
function cp_ac_limpeza_remover_ficheiros( $anexo_id ) {
	$ficheiro = get_attached_file( $anexo_id );
	$ok       = wp_delete_attachment_files(
		$anexo_id,
		wp_get_attachment_metadata( $anexo_id ),
		get_post_meta( $anexo_id, '_wp_attachment_backup_sizes', true ),
		$ficheiro
	);
	if ( $ok ) update_post_meta( $anexo_id, '_cp_ficheiro_removido', current_time( 'mysql' ) );
	return $ok;
}

function cp_ac_documento_ficheiro_removido( $anexo_id ) {
	return '' !== (string) get_post_meta( $anexo_id, '_cp_ficheiro_removido', true );
}

function cp_ac_limpeza_tamanho( $anexo_id ) {
	$caminho = get_attached_file( $anexo_id );
	return ( $caminho && file_exists( $caminho ) ) ? (int) filesize( $caminho ) : 0;
}

function cp_ac_rest_limpeza( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$simular = (bool) $request->get_param( 'simular' );

	$docs = [];
	foreach ( cp_ac_documentos( $empresa_id ) as $doc ) {
		$docs[ (int) $doc->ID ] = cp_ac_documento_ficheiro_removido( $doc->ID ) ? 'removido'
			: ( cp_ac_documento_recebido_work( $doc->ID ) ? 'recebido' : 'por_recolher' );
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
		$tamanho = cp_ac_limpeza_tamanho( $id );
		// Documentos: fica a linha (anexo sem ficheiro). Respostas: apaga o anexo.
		$ok = $simular || ( array_key_exists( $id, $docs ) ? cp_ac_limpeza_remover_ficheiros( $id ) : (bool) wp_delete_attachment( $id, true ) );
		if ( $ok ) {
			$bytes += $tamanho;
			$apagados++;
		}
	}

	// Persistentes (conta corrente, recibos): só com mês válido.
	$mes          = (string) $request->get_param( 'mes' );
	$persistentes = array_values( array_intersect( (array) $request->get_param( 'persistentes' ), [ 'conta_corrente', 'recibos_vencimento' ] ) );
	if ( $persistentes && preg_match( '/^\d{4}-\d{2}$/', $mes ) ) {
		$cc     = cp_ac_conta_corrente( $empresa_id );
		$p      = cp_ac_calc_limpeza_persistentes( $cc['linhas'], cp_ac_recibos_vencimento( $empresa_id ), $mes, $persistentes, current_time( 'mysql' ) );
		foreach ( $p['anexos'] as $id ) {
			$tamanho = cp_ac_limpeza_tamanho( $id );
			if ( $simular || wp_delete_attachment( $id, true ) ) {
				$bytes += $tamanho;
				$apagados++;
			}
		}
		if ( ! $simular && $p['anexos'] ) {
			if ( $p['n_cc'] ) update_post_meta( $empresa_id, '_cp_conta_corrente', [ 'resumo' => $cc['resumo'], 'linhas' => $p['linhas'] ] );
			if ( $p['n_recibos'] ) update_post_meta( $empresa_id, '_cp_recibos_vencimento', $p['recibos'] );
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
