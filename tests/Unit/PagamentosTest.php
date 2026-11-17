<?php

namespace ContabilidadePro\Tests\Unit;

/**
 * @covers cp_ac_pag_normalizar_guias
 * @covers cp_ac_pag_estado_guia
 * @covers cp_ac_pag_texto_email
 * @covers cp_ac_pag_por_pagar
 */
final class PagamentosTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-pagamentos.php' );
	}

	private function guia( $id, $nome, $data, $montante, $pago = false, $ref = '' ) {
		return [ 'id' => $id, 'imposto' => 'DMR SS', 'nome' => $nome, 'referencia' => $ref, 'data_limite' => $data,
			'montante' => $montante, 'pago' => $pago, 'anexo_id' => 0, 'anexo_url' => '' ];
	}

	public function test_normalizar_ignora_sem_id_e_datas_invalidas(): void {
		$g = cp_ac_pag_normalizar_guias( [
			[ 'id' => '8-1', 'nome' => 'DMR', 'data_limite' => '2026-11-25', 'montante' => '208.50', 'pago' => 0 ],
			[ 'nome' => 'sem id' ],
			[ 'id' => '3-2', 'data_limite' => '25/11/2026', 'pago' => 1 ],
		] );
		self::assertCount( 2, $g );
		self::assertSame( 208.5, $g[0]['montante'] );
		self::assertFalse( $g[0]['pago'] );
		self::assertSame( '', $g[1]['data_limite'] );
		self::assertTrue( $g[1]['pago'] );
	}

	public function test_estado_da_guia(): void {
		self::assertSame( 'pago', cp_ac_pag_estado_guia( $this->guia( 'a', 'x', '2026-01-01', 1, true ), '2026-11-17' ) );
		self::assertSame( 'atraso', cp_ac_pag_estado_guia( $this->guia( 'a', 'x', '2026-11-16', 1 ), '2026-11-17' ) );
		self::assertSame( 'por_pagar', cp_ac_pag_estado_guia( $this->guia( 'a', 'x', '2026-11-17', 1 ), '2026-11-17' ) );
		self::assertSame( 'por_pagar', cp_ac_pag_estado_guia( $this->guia( 'a', 'x', '', 1 ), '2026-11-17' ) );
	}

	public function test_email_so_com_guias_por_pagar_por_prazo(): void {
		$t = cp_ac_pag_texto_email( 'Troviscais', 2026, 11, [
			$this->guia( 'b', 'IMI', '2026-11-30', 123.45, false, '123 456 789' ),
			$this->guia( 'c', 'IUC', '2026-11-10', 50, true ),
			$this->guia( 'a', 'DMR SS', '2026-11-25', 1208.5 ),
		], 'https://exemplo/area-de-cliente/?vista=pagamentos' );
		self::assertSame( 'Pagamentos ao Estado de novembro de 2026 — Troviscais', $t['assunto'] );
		self::assertStringContainsString( "- DMR SS: 1 208,50 € até 25/11/2026\n\n- IMI: 123,45 € até 30/11/2026\n  Referência: 123 456 789", $t['corpo'] );
		self::assertStringContainsString( 'Total: 1 331,95 €', $t['corpo'] );
		self::assertStringNotContainsString( 'IUC', $t['corpo'] );
		self::assertStringContainsString( '?vista=pagamentos', $t['corpo'] );
	}

	public function test_indicadas_pelo_cliente_saem_do_aviso(): void {
		$indicada                    = $this->guia( 'a', 'DMR SS', '2026-11-25', 100 );
		$indicada['cliente_pago_em'] = '2026-11-18 10:00:00';
		$lista                       = cp_ac_pag_por_pagar( [ $indicada, $this->guia( 'b', 'IMI', '2026-11-30', 50 ) ] );
		self::assertSame( [ 'b' ], array_column( $lista, 'id' ) );
		self::assertNull( cp_ac_pag_texto_email( 'X', 2026, 11, [ $indicada ], 'u' ) );
	}

	public function test_email_com_entidade(): void {
		$g             = $this->guia( 'a', 'DMR SS', '2026-11-25', 100, false, '123 456 789' );
		$g['entidade'] = '20999';
		$t             = cp_ac_pag_texto_email( 'X', 2026, 11, [ $g ], 'u' );
		self::assertStringContainsString( "  Entidade: 20999\n  Referência: 123 456 789", $t['corpo'] );
		self::assertSame( '20999', cp_ac_pag_normalizar_guias( [ [ 'id' => 'a', 'entidade' => '20999' ] ] )[0]['entidade'] );
	}

	public function test_sem_nada_por_pagar_nao_ha_email(): void {
		self::assertNull( cp_ac_pag_texto_email( 'X', 2026, 11, [ $this->guia( 'c', 'IUC', '2026-11-10', 50, true ) ], 'u' ) );
		self::assertNull( cp_ac_pag_texto_email( 'X', 2026, 11, [], 'u' ) );
	}
}
