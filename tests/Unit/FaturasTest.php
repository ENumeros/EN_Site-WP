<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_fat_numero
 * @covers cp_ac_fat_nif_valido
 * @covers cp_ac_fat_totais
 * @covers cp_ac_fat_normalizar_artigos
 * @covers cp_ac_fat_normalizar_clientes
 * @covers cp_ac_fat_validar_pedido
 * @covers cp_ac_fat_podar
 */
final class FaturasTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Functions\when( 'sanitize_email' )->alias( function ( $e ) {
			return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '';
		} );
		self::loadThemeFile( 'inc/area-cliente-faturas.php' );
	}

	private function catalogo() {
		return [
			'artigos'  => [ [ 'codigo' => '0001', 'nome' => 'Consultoria', 'taxa_iva' => 23.0, 'preco' => 120.0 ],
				[ 'codigo' => 'LIV', 'nome' => 'Livro', 'taxa_iva' => 6.0, 'preco' => 0.0 ] ],
			'clientes' => [ [ 'conta' => '211110003', 'nome' => 'Cliente CG', 'nif' => '518095312', 'morada' => 'Rua A',
				'cod_postal' => '3030-250', 'localidade' => 'Coimbra', 'pais' => 'PT', 'email' => '' ] ],
		];
	}

	public function test_numero_aceita_formatos_portugueses(): void {
		self::assertSame( 1234.5, cp_ac_fat_numero( '1 234,50' ) );
		self::assertSame( 1234.5, cp_ac_fat_numero( '1.234,50' ) );
		self::assertSame( 12.5, cp_ac_fat_numero( '12.5' ) );
		self::assertSame( 3.0, cp_ac_fat_numero( 3 ) );
		self::assertNull( cp_ac_fat_numero( '' ) );
		self::assertNull( cp_ac_fat_numero( 'abc' ) );
	}

	public function test_nif(): void {
		self::assertTrue( cp_ac_fat_nif_valido( '518095312' ) );
		self::assertTrue( cp_ac_fat_nif_valido( '501 442 600' ) );
		self::assertFalse( cp_ac_fat_nif_valido( '518095313' ) );
		self::assertFalse( cp_ac_fat_nif_valido( '12345' ) );
	}

	public function test_totais_arredondam_por_linha(): void {
		$t = cp_ac_fat_totais( [
			[ 'quantidade' => 3, 'preco' => 0.333 ],   // 0,999 → 1,00
			[ 'quantidade' => 1, 'preco' => 100 ],
		] );
		self::assertSame( [ 'total' => 101.0 ], $t ); // sem IVA: o cliente não escolhe a taxa
	}

	public function test_normalizar_catalogo_ignora_incompletos_e_taxas_invalidas(): void {
		$a = cp_ac_fat_normalizar_artigos( [ [ 'codigo' => 'X', 'nome' => 'Y', 'taxa_iva' => 17, 'preco' => '10' ], [ 'nome' => 'sem código' ] ] );
		self::assertCount( 1, $a );
		self::assertSame( 23.0, $a[0]['taxa_iva'] );
		self::assertSame( 10.0, $a[0]['preco'] );
		$c = cp_ac_fat_normalizar_clientes( [ [ 'conta' => '21', 'nome' => 'A', 'pais' => 'zz9' ], [ 'nome' => 'sem conta' ] ] );
		self::assertCount( 1, $c );
		self::assertSame( 'PT', $c[0]['pais'] );
	}

	public function test_pedido_com_cliente_e_artigo_do_catalogo_usa_os_dados_do_catalogo(): void {
		$r = cp_ac_fat_validar_pedido( [
			'destinatario_conta' => '211110003',
			'destinatario'       => [ 'nome' => 'Ignorado' ],
			'linhas'             => [
				[ 'artigo' => '0001', 'descricao' => '', 'quantidade' => '2', 'preco' => '150' ],
				[ 'artigo' => '', 'descricao' => '', 'quantidade' => '1', 'preco' => '' ], // em branco
			],
			'observacoes'        => '',
		], $this->catalogo() );
		self::assertSame( [], $r['erros'] );
		self::assertSame( 'Cliente CG', $r['pedido']['destinatario']['nome'] );
		self::assertFalse( $r['pedido']['destinatario']['novo'] );
		self::assertCount( 1, $r['pedido']['linhas'] );
		self::assertSame( 'Consultoria', $r['pedido']['linhas'][0]['descricao'] );
		self::assertArrayNotHasKey( 'taxa_iva', $r['pedido']['linhas'][0] );
		self::assertSame( 300.0, $r['pedido']['totais']['total'] );
	}

	public function test_cliente_novo_e_texto_livre(): void {
		$r = cp_ac_fat_validar_pedido( [
			'destinatario_conta' => 'novo',
			'destinatario'       => [ 'nome' => 'Novo Lda', 'nif' => '501442600', 'pais' => 'pt' ],
			'linhas'             => [ [ 'artigo' => '', 'descricao' => 'Serviço novo', 'quantidade' => '1', 'preco' => '1.000,00' ] ],
		], $this->catalogo() );
		self::assertSame( [], $r['erros'] );
		self::assertTrue( $r['pedido']['destinatario']['novo'] );
		self::assertSame( 'PT', $r['pedido']['destinatario']['pais'] );
		self::assertSame( 1000.0, $r['pedido']['totais']['total'] );
	}

	public function test_erros(): void {
		$r = cp_ac_fat_validar_pedido( [
			'destinatario_conta' => 'novo',
			'destinatario'       => [ 'nome' => '', 'nif' => '123456780', 'email' => 'nao-e-email' ],
			'linhas'             => [ [ 'artigo' => 'NAO', 'descricao' => 'x', 'quantidade' => '1', 'preco' => '1' ],
				[ 'artigo' => '', 'descricao' => 'y', 'quantidade' => '0', 'preco' => '-1' ] ],
		], $this->catalogo() );
		self::assertNull( $r['pedido'] );
		$texto = implode( ' | ', $r['erros'] );
		foreach ( [ 'nome do cliente', 'NIF', 'email', 'Linha 1: o artigo', 'Linha 2: a quantidade', 'Linha 2: indique o preço' ] as $esperado ) {
			self::assertStringContainsString( $esperado, $texto );
		}
	}

	public function test_cliente_por_escolher(): void {
		$r = cp_ac_fat_validar_pedido( [ 'destinatario_conta' => '',
			'linhas' => [ [ 'artigo' => '', 'descricao' => 'x', 'quantidade' => '1', 'preco' => '50' ] ] ], $this->catalogo() );
		self::assertSame( [ 'Escolha o cliente.' ], $r['erros'] );
	}

	public function test_sem_linhas(): void {
		$r = cp_ac_fat_validar_pedido( [ 'destinatario_conta' => '211110003', 'linhas' => [] ], $this->catalogo() );
		self::assertStringContainsString( 'pelo menos uma linha', implode( ' ', $r['erros'] ) );
	}

	public function test_podar_so_corta_tratados_mais_antigos(): void {
		$pedidos = [
			[ 'id' => 'd', 'estado' => 'enviado' ],
			[ 'id' => 'c', 'estado' => 'emitida' ],
			[ 'id' => 'b', 'estado' => 'recebido' ],
			[ 'id' => 'a', 'estado' => 'emitida' ], // o mais antigo
		];
		self::assertSame( [ 'd', 'c', 'b' ], array_column( cp_ac_fat_podar( $pedidos, 3 ), 'id' ) );
		self::assertSame( [ 'd', 'b' ], array_column( cp_ac_fat_podar( $pedidos, 2 ), 'id' ) );
		self::assertCount( 4, cp_ac_fat_podar( $pedidos, 10 ) );
	}
}
