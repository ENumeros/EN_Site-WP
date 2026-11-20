<?php

namespace ContabilidadePro\Tests\Unit;

/**
 * Backoffice › Contas correntes.
 *
 * @covers cp_bocc_calc_item
 */
final class ContasCorrentesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'DAY_IN_SECONDS' ) ) define( 'DAY_IN_SECONDS', 86400 );
		self::loadThemeFile( 'inc/area-cliente.php' );
		self::loadThemeFile( 'inc/area-cliente-backoffice-contas.php' );
	}

	private function linha( $ext, $doc, $tipo, $debito, $credito, $por_pagar, $data ) {
		return [ 'data' => $data, 'numero_documento' => $doc, 'tipo' => $tipo, 'debito' => $debito, 'credito' => $credito,
			'saldo' => 0, 'pendente' => $por_pagar > 0, 'por_pagar' => $por_pagar, 'tem_documento' => true,
			'ext_poc_cab_id' => $ext, 'ano' => '2026' ];
	}

	public function test_em_divida_soma_so_as_faturas_por_pagar(): void {
		$linhas = cp_ac_cc_normalizar_linhas( [
			$this->linha( '{A}', 'FT 161', 'fatura', 184.5, 0, 184.5, '2026-11-01' ),
			$this->linha( '{B}', 'FT 145', 'fatura', 184.5, 0, 0.5, '2026-10-01' ),
			$this->linha( '{C}', 'FT 130', 'fatura', 184.5, 0, 0, '2026-09-01' ),
			$this->linha( '{D}', 'RG 192', 'fatura', 0, 0.5, 0.5, '2026-09-17' ),
		] );
		$i = cp_bocc_calc_item( 7, '002 - Entre Números', [ 'resumo' => [ 'saldo_atual' => 184.5, 'faturado_ano' => 553.5, 'pago_ano' => 369 ], 'linhas' => $linhas ], '2026-11-20 08:00:00' );
		self::assertTrue( $i['tem_dados'] );
		self::assertSame( 185.0, $i['em_divida'], 'o crédito não entra, a fatura paga em parte conta só o que falta' );
		self::assertSame( 2, $i['n_abertas'] );
		self::assertSame( 184.5, $i['saldo'] );
		self::assertCount( 4, $i['linhas'] );
	}

	public function test_sem_conta_corrente(): void {
		$i = cp_bocc_calc_item( 8, '006 - NTH', [ 'resumo' => [], 'linhas' => [] ] );
		self::assertFalse( $i['tem_dados'] );
		self::assertNull( $i['saldo'] );
		self::assertSame( 0.0, $i['em_divida'] );
	}
}
