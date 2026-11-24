<?php

namespace ContabilidadePro\Tests\Unit;

/**
 * @covers cp_ac_calc_limpeza
 */
final class LimpezaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-limpeza.php' );
	}

	public function test_so_apaga_documentos_ja_recebidos_e_desta_empresa(): void {
		$r = cp_ac_calc_limpeza( [ 10, 11, 99, 0, 'x' ], [], [ 10 => true, 11 => false ], [], [] );
		self::assertSame( [ 10 ], $r['apagar'] );
		self::assertSame( [
			[ 'ref' => '11', 'motivo' => 'por_recolher' ],
			[ 'ref' => '99', 'motivo' => 'nao_existe' ],
		], $r['ignorados'] );
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
}
