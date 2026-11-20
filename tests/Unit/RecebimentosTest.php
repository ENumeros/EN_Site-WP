<?php

namespace ContabilidadePro\Tests\Unit;

/**
 * Conta corrente no formato da Work e Recebimentos do Backoffice.
 *
 * @covers cp_ac_cc_normalizar_linhas
 * @covers cp_ac_cc_faturas_em_aberto
 * @covers cp_rec_calc_fundir
 * @covers cp_rec_calc_alocacao
 * @covers cp_rec_calc_validar
 * @covers cp_rec_calc_abertas
 * @covers cp_rec_calc_contagem
 */
final class RecebimentosTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'DAY_IN_SECONDS' ) ) define( 'DAY_IN_SECONDS', 86400 );
		self::loadThemeFile( 'inc/area-cliente.php' );
		self::loadThemeFile( 'inc/area-cliente-recebimentos.php' );
	}

	// Linha tal como a Work a envia (api_cliente.filtrar_conta_corrente).
	private function linha_work( $ext, $doc, $tipo, $debito, $credito, $por_pagar, $data = '2026-10-01' ) {
		return [ 'data' => $data, 'numero_documento' => $doc, 'tipo' => $tipo, 'debito' => $debito, 'credito' => $credito,
			'saldo' => 0, 'pendente' => $por_pagar > 0, 'por_pagar' => $por_pagar, 'tem_documento' => true,
			'ext_poc_cab_id' => $ext, 'ano' => '2026' ];
	}

	public function test_conta_corrente_no_formato_da_work_ja_nao_e_descartada(): void {
		$l = cp_ac_cc_normalizar_linhas( [
			$this->linha_work( '{A}', 'FT 1.2026/145', 'fatura', 184.5, 0, 184.5 ),
			$this->linha_work( '{B}', 'RG 1/192', 'recibo', 0, 184.5, 0 ),
			$this->linha_work( '{B}', 'RG 1/192', 'fatura', 0, 0.5, 0.5 ),
			$this->linha_work( '{C}', 'FT 1.2026/113', 'fatura', 184.5, 0, 0 ),
			[ 'data' => '2026-01-01', 'numero_documento' => 'Saldo inicial 2026', 'tipo' => 'resumo', 'saldo' => 12.3, 'ano' => '2025' ],
		] );
		self::assertCount( 5, $l );
		self::assertSame( [ '{A}', '{B}', '{B}#2', '{C}', 'resumo-2025' ], array_column( $l, 'id' ) );
		self::assertSame( 'FT 1.2026/145', $l[0]['documento'] );
		self::assertSame( 'Fatura', $l[0]['tipo'] );
		self::assertSame( 184.5, $l[0]['valor'] );
		self::assertSame( 'Por pagar', $l[0]['estado'] );
		self::assertSame( 'Pago', $l[1]['estado'] );
		self::assertSame( 'Crédito', $l[2]['estado'] );
		self::assertSame( 'Paga', $l[3]['estado'] );
		self::assertSame( 12.3, $l[4]['valor'] );
	}

	public function test_formato_antigo_continua_a_funcionar(): void {
		$l = cp_ac_cc_normalizar_linhas( [ [ 'id' => 'x1', 'data' => '2026-01-02', 'documento' => 'FT 1', 'tipo' => 'Fatura', 'valor' => 10, 'estado' => 'Paga' ], [ 'sem' => 'id' ] ] );
		self::assertCount( 1, $l );
		self::assertSame( 'x1', $l[0]['id'] );
		self::assertSame( 'Paga', $l[0]['estado'] );
	}

	public function test_faturas_em_aberto_so_debitos_com_valor_mais_antigas_primeiro(): void {
		$l = cp_ac_cc_normalizar_linhas( [
			$this->linha_work( '{N}', 'FT 161', 'fatura', 184.5, 0, 184.5, '2026-11-01' ),
			$this->linha_work( '{S}', 'FT 130', 'fatura', 184.5, 0, 184.5, '2026-09-01' ),
			$this->linha_work( '{P}', 'FT 113', 'fatura', 184.5, 0, 0, '2026-08-01' ),
			$this->linha_work( '{C}', 'RG 192', 'fatura', 0, 0.5, 0.5, '2026-09-17' ),
		] );
		self::assertSame( [ '{S}', '{N}' ], array_column( cp_ac_cc_faturas_em_aberto( $l ), 'ext_poc_cab_id' ) );
	}

	private function recebido( $id, $valor = 184.5, $work_id = '7', $aloc = [ [ 'ext_poc_cab_id' => '{S}', 'documento' => 'FT 130', 'valor' => 184.5 ] ] ) {
		return [ 'id' => $id, 'conta_id' => 'bank', 'conta' => '1201', 'data' => '2026-11-18', 'valor' => $valor,
			'descricao' => 'Transferência De Leila', 'pagador' => 'Leila', 'work_id' => $work_id, 'como' => 'nome',
			'estado' => 'verde', 'aviso' => '', 'alocacao' => $aloc ];
	}

	public function test_fundir_novos_e_estados_que_ficam(): void {
		$agora = '2026-11-19 08:00:00';
		$r     = cp_rec_calc_fundir( [], [ $this->recebido( 'm1' ), $this->recebido( 'm2' ) ], [], [], $agora );
		self::assertSame( [ 'm1', 'm2' ], $r['novos'] );
		self::assertSame( 'novo', $r['itens']['m1']['estado'] );

		$itens                     = $r['itens'];
		$itens['m1']['estado']     = 'pedido';
		$itens['m1']['pedido']     = [ 'work_id' => '9' ];
		$itens['m2']['estado']     = 'ignorado';
		$r2 = cp_rec_calc_fundir( $itens, [ $this->recebido( 'm1', 184.5, '1' ), $this->recebido( 'm2' ), $this->recebido( 'm3' ) ], [], [], $agora );
		self::assertSame( [ 'm3' ], $r2['novos'] );
		self::assertSame( 'pedido', $r2['itens']['m1']['estado'] );
		self::assertSame( '7', $r2['itens']['m1']['work_id'], 'um pedido não é mexido pela sugestão nova' );
		self::assertSame( 'ignorado', $r2['itens']['m2']['estado'] );
	}

	public function test_fundir_o_que_deixou_de_vir(): void {
		$agora = '2026-11-19 08:00:00';
		$itens = cp_rec_calc_fundir( [], [ $this->recebido( 'a' ), $this->recebido( 'b' ), $this->recebido( 'c' ), $this->recebido( 'd' ) ], [], [], $agora )['itens'];
		$itens['b']['estado'] = 'pedido';
		$itens['d']['estado'] = 'emitido';
		$itens['d']['resultado'] = [ 'estado' => 'emitido', 'mensagem' => 'x', 'em' => '2026-09-01 10:00:00' ];
		$r = cp_rec_calc_fundir( $itens, [], [ [ 'id' => 'c', 'faturas' => 'FT 130', 'emitido_em' => '2026-11-18 12:00:00' ] ], [], $agora );
		self::assertArrayNotHasKey( 'a', $r['itens'], 'lançado por outra via: sai' );
		self::assertSame( 'pedido', $r['itens']['b']['estado'] );
		self::assertSame( 'emitido', $r['itens']['c']['estado'] );
		self::assertSame( 'FT 130', $r['itens']['c']['resultado']['mensagem'] );
		self::assertArrayNotHasKey( 'd', $r['itens'], 'emitido há mais de 30 dias: sai' );
	}

	public function test_fundir_ignorados_na_work_saem(): void {
		$r = cp_rec_calc_fundir( [], [ $this->recebido( 'a' ) ], [], [ 'a' ], '2026-11-19 08:00:00' );
		self::assertSame( [], $r['itens'] );
		self::assertSame( [], $r['novos'] );
	}

	public function test_validar(): void {
		$abertas = [ [ 'ext_poc_cab_id' => '{S}', 'documento' => 'FT 130', 'por_pagar' => 184.5 ],
			[ 'ext_poc_cab_id' => '{N}', 'documento' => 'FT 161', 'por_pagar' => 184.5 ] ];
		self::assertSame( [], cp_rec_calc_validar( 185.0, cp_rec_calc_alocacao( [
			[ 'ext_poc_cab_id' => '{S}', 'valor' => '184.5' ], [ 'ext_poc_cab_id' => '{N}', 'valor' => 0.5 ] ] ), $abertas ) );
		self::assertCount( 1, cp_rec_calc_validar( 185.0, cp_rec_calc_alocacao( [ [ 'ext_poc_cab_id' => '{S}', 'valor' => 184.5 ] ] ), $abertas ) );
		self::assertNotEmpty( cp_rec_calc_validar( 200.0, cp_rec_calc_alocacao( [ [ 'ext_poc_cab_id' => '{S}', 'valor' => 200 ] ] ), $abertas ) );
		self::assertNotEmpty( cp_rec_calc_validar( 10.0, cp_rec_calc_alocacao( [ [ 'ext_poc_cab_id' => '{X}', 'valor' => 10 ] ] ), $abertas ) );
		self::assertNotEmpty( cp_rec_calc_validar( 10.0, [], $abertas ) );
	}

	public function test_alocacao_descarta_zeros_e_sem_fatura(): void {
		$a = cp_rec_calc_alocacao( [ [ 'ext_poc_cab_id' => '{S}', 'valor' => '' ], [ 'valor' => 3 ], [ 'ext_poc_cab_id' => '{N}', 'valor' => '1.005' ] ] );
		self::assertSame( [ [ 'ext_poc_cab_id' => '{N}', 'documento' => '', 'valor' => 1.01 ] ], $a );
	}

	public function test_abertas_inclui_a_sugestao_da_work_so_para_a_mesma_empresa(): void {
		$linhas = cp_ac_cc_normalizar_linhas( [ $this->linha_work( '{N}', 'FT 161', 'fatura', 184.5, 0, 184.5, '2026-11-01' ) ] );
		$item   = cp_rec_calc_fundir( [], [ $this->recebido( 'a', 184.5, '7', [ [ 'ext_poc_cab_id' => '{VELHA}', 'documento' => 'FT 2025/9', 'valor' => 184.5 ] ] ) ], [], [], '2026-11-19 08:00:00' )['itens']['a'];
		self::assertSame( [ '{N}', '{VELHA}' ], array_column( cp_rec_calc_abertas( $linhas, $item, '7' ), 'ext_poc_cab_id' ) );
		self::assertSame( [ '{N}' ], array_column( cp_rec_calc_abertas( $linhas, $item, '8' ), 'ext_poc_cab_id' ) );
	}

	public function test_rotulo_da_empresa_usa_o_codigo_do_cliente_e_nunca_o_work_id(): void {
		\Brain\Monkey\Functions\when( 'get_the_title' )->justReturn( 'Entre Números, Lda' );
		\Brain\Monkey\Functions\when( 'get_post_meta' )->alias( function ( $id, $chave ) {
			return [ '_cp_work_id' => '33', '_cp_codigo' => 1 === $id ? 'EN001' : '' ][ $chave ];
		} );
		self::assertSame( 'EN001 - Entre Números, Lda', cp_ac_empresa_label( 1 ) );
		self::assertSame( 'Entre Números, Lda', cp_ac_empresa_label( 2 ), 'sem código: só o nome, nunca o 33' );
	}

	public function test_empresas_ordenadas_pelo_numero_de_cliente(): void {
		$cod = [ 1 => '033', 2 => '', 3 => '005', 4 => 'A01', 5 => '100' ];
		\Brain\Monkey\Functions\when( 'get_post_meta' )->alias( function ( $id ) use ( $cod ) { return $cod[ $id ]; } );
		$e = function ( $id, $nome ) { return (object) [ 'ID' => $id, 'post_title' => $nome ]; };
		$r = cp_ac_ordenar_empresas( [ $e( 1, 'Entre Números' ), $e( 2, 'Sem código' ), $e( 3, 'Zeta' ), $e( 4, 'Consultoria' ), $e( 5, 'Alfa' ) ] );
		self::assertSame( [ 3, 1, 5, 4, 2 ], array_column( $r, 'ID' ) );
	}

	public function test_contagem(): void {
		$n = cp_rec_calc_contagem( [ [ 'estado' => 'novo' ], [ 'estado' => 'erro' ], [ 'estado' => 'novo' ], [ 'estado' => 'emitido' ] ] );
		self::assertSame( 2, $n['novo'] );
		self::assertSame( 1, $n['erro'] );
		self::assertSame( 1, $n['emitido'] );
	}
}
