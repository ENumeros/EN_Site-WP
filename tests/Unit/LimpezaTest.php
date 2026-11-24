<?php

namespace ContabilidadePro\Tests\Unit;

/**
 * @covers cp_ac_calc_limpeza
 * @covers cp_ac_calc_limpeza_persistentes
 * @covers cp_ac_calc_mes_da_data
 * @covers cp_ac_calc_orfaos
 * @covers cp_ac_calc_anexos_referidos
 */
final class LimpezaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-limpeza.php' );
	}

	public function test_so_apaga_documentos_ja_recebidos_e_desta_empresa(): void {
		$r = cp_ac_calc_limpeza( [ 10, 11, 12, 99, 0, 'x' ], [], [ 10 => 'recebido', 11 => 'por_recolher', 12 => 'removido' ], [], [] );
		self::assertSame( [ 10 ], $r['apagar'] );
		self::assertSame( [
			[ 'ref' => '11', 'motivo' => 'por_recolher' ],
			[ 'ref' => '12', 'motivo' => 'ja_removido' ],
			[ 'ref' => '99', 'motivo' => 'nao_existe' ],
		], $r['ignorados'] );
	}

	public function test_mes_da_data_aceita_os_formatos_da_conta_corrente(): void {
		self::assertSame( '2026-10', cp_ac_calc_mes_da_data( '2026-10-05' ) );
		self::assertSame( '2026-10', cp_ac_calc_mes_da_data( '2026-10-05T00:00:00' ) );
		self::assertSame( '2026-10', cp_ac_calc_mes_da_data( '05/10/2026' ) );
		self::assertSame( '2026-10', cp_ac_calc_mes_da_data( '05-10-2026' ) );
		self::assertSame( '', cp_ac_calc_mes_da_data( 'Saldo inicial' ) );
	}

	public function test_persistentes_do_mes_ficam_com_a_linha_sem_ficheiro(): void {
		$linhas = [
			[ 'id' => 'a', 'data' => '2026-10-05', 'anexo_id' => 70, 'anexo_url' => 'u70' ],
			[ 'id' => 'a#2', 'data' => '2026-10-05', 'anexo_id' => 70, 'anexo_url' => 'u70' ],   // mesmo documento
			[ 'id' => 'b', 'data' => '2026-11-02', 'anexo_id' => 71, 'anexo_url' => 'u71' ],
			[ 'id' => 'c', 'data' => '2026-10-09', 'anexo_id' => '', 'anexo_url' => '' ],
		];
		$recibos = [
			[ 'cod_emp' => '1', 'ano' => 2026, 'mes' => 10, 'anexo_id' => 80, 'anexo_url' => 'u80' ],
			[ 'cod_emp' => '1', 'ano' => 2026, 'mes' => 9, 'anexo_id' => 81, 'anexo_url' => 'u81' ],
		];
		$r = cp_ac_calc_limpeza_persistentes( $linhas, $recibos, '2026-10', [ 'conta_corrente', 'recibos_vencimento' ], 'agora' );
		self::assertSame( [ 70, 80 ], $r['anexos'] );
		self::assertSame( [ 1, 1 ], [ $r['n_cc'], $r['n_recibos'] ] );
		self::assertSame( [ '', '', 'agora' ], [ $r['linhas'][1]['anexo_id'], $r['linhas'][1]['anexo_url'], $r['linhas'][1]['ficheiro_removido'] ] );
		self::assertSame( 71, $r['linhas'][2]['anexo_id'] );
		self::assertArrayNotHasKey( 'ficheiro_removido', $r['linhas'][3] );
		self::assertSame( 'agora', $r['recibos'][0]['ficheiro_removido'] );
		self::assertSame( 81, $r['recibos'][1]['anexo_id'] );

		$so_recibos = cp_ac_calc_limpeza_persistentes( $linhas, $recibos, '2026-10', [ 'recibos_vencimento' ], 'agora' );
		self::assertSame( [ 80 ], $so_recibos['anexos'] );
		self::assertSame( 70, $so_recibos['linhas'][0]['anexo_id'] );
	}

	public function test_respostas_todas_as_versoes_mas_nunca_por_recolher(): void {
		$anexos = [
			[ 'id' => 50, 'tipo' => 'documento', 'item_id' => '7' ],
			[ 'id' => 51, 'tipo' => 'documento', 'item_id' => '7' ],   // resposta substituída
			[ 'id' => 52, 'tipo' => 'extrato', 'item_id' => 'extrato-2026-10-1201' ],
			[ 'id' => 53, 'tipo' => 'movimento', 'item_id' => '7' ],
		];
		$r = cp_ac_calc_limpeza( [], [
			[ 'tipo' => 'documento', 'item_id' => '7' ],
			[ 'tipo' => 'extrato', 'item_id' => 'extrato-2026-10-1201' ],
			[ 'tipo' => 'saft', 'item_id' => 'saft-2026-10' ],
			[ 'tipo' => 'movimento', 'item_id' => '7' ],
			[ 'tipo' => '', 'item_id' => '1' ],
		], [], $anexos, [ 'movimento|7' ] );
		self::assertSame( [ 50, 51, 52 ], $r['apagar'] );
		self::assertSame( [
			[ 'ref' => 'saft|saft-2026-10', 'motivo' => 'sem_ficheiro' ],
			[ 'ref' => 'movimento|7', 'motivo' => 'por_recolher' ],
		], $r['ignorados'] );
	}

	public function test_anexos_referidos_em_qualquer_profundidade(): void {
		$ids = [];
		cp_ac_calc_anexos_referidos( [ 'linhas' => [ [ 'anexo_id' => 70 ], [ 'anexo_id' => '' ] ], 'fatura' => [ 'anexo_id' => '71' ] ], $ids );
		cp_ac_calc_anexos_referidos( 'texto', $ids );
		cp_ac_calc_anexos_referidos( [ [ 'anexo_id' => 0 ], [ 'outro_id' => 5 ] ], $ids );
		self::assertSame( [ 70 => true, 71 => true ], $ids );
	}

	public function test_orfaos_so_da_area_de_cliente_e_sem_uso(): void {
		$base   = [ 'parent' => 0, 'ac' => false, 'doc' => false, 'resposta' => false, 'imagem' => false, 'citado' => false ];
		$anexos = [
			[ 'id' => 1, 'ac' => true ] + $base,                             // PDF da Work já sem linha
			[ 'id' => 2, 'ac' => true ] + $base,                             // PDF ainda usado
			[ 'id' => 3, 'doc' => true, 'parent' => 50 ] + $base,            // documento de empresa viva
			[ 'id' => 4, 'doc' => true ] + $base,                            // empresa apagada (parent 0)
			[ 'id' => 5, 'resposta' => true, 'parent' => 99 ] + $base,       // empresa que já não existe
			[ 'id' => 6 ] + $base,                                           // resposta antiga sem marca
			[ 'id' => 7, 'imagem' => true ] + $base,                         // imagem do site
			[ 'id' => 8, 'citado' => true ] + $base,                         // PDF citado numa página
			[ 'id' => 9, 'parent' => 12 ] + $base,                           // media ligado a um post
		];
		self::assertSame(
			[ 1 => 'sem_uso', 4 => 'empresa_apagada', 5 => 'empresa_apagada', 6 => 'antigo' ],
			cp_ac_calc_orfaos( $anexos, [ 2 => true ], [ 50 => true ] )
		);
	}
}
