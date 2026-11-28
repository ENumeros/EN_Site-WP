<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_colab_normalizar_lista
 * @covers cp_ac_colab_validar
 * @covers cp_ac_colab_podar
 */
final class ColaboradoresTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( function ( $t ) { return trim( strip_tags( (string) $t ) ); } );
		Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Functions\when( 'sanitize_email' )->alias( function ( $e ) {
			return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '';
		} );
		self::loadThemeFile( 'inc/area-cliente-faturas.php' ); // cp_ac_fat_nif_valido
		self::loadThemeFile( 'inc/area-cliente-colaboradores.php' );
	}

	public function test_lista_ativos_primeiro_por_nome_e_sem_nome_fora(): void {
		$lista = cp_ac_colab_normalizar_lista( [
			[ 'numero' => '3', 'nome' => 'Zé', 'ativo' => true ],
			[ 'numero' => '1', 'nome' => 'Ana', 'ativo' => false ],
			[ 'numero' => '2', 'nome' => 'Bruno', 'ativo' => true, 'categoria' => 'Técnico', 'extra' => 'x' ],
			[ 'numero' => '9', 'nome' => '' ],
			'lixo',
		] );
		self::assertSame( [ 'Bruno', 'Zé', 'Ana' ], array_column( $lista, 'nome' ) );
		self::assertSame( 'Técnico', $lista[0]['categoria'] );
		self::assertArrayNotHasKey( 'extra', $lista[0] );
		self::assertFalse( $lista[2]['ativo'] );
	}

	public function test_validar_exige_nome_e_nif_e_email_validos(): void {
		list( , $erros ) = cp_ac_colab_validar( [ 'nome' => '  ', 'nif' => '123456788', 'email' => 'nao-e-email' ] );
		self::assertCount( 3, $erros );

		list( $dados, $erros ) = cp_ac_colab_validar( [ 'nome' => 'Maria Silva', 'nif' => '518 095 312', 'email' => 'm@x.pt',
			'categoria' => 'Administrativa', 'observacoes' => "Começa a 1\nde dezembro" ] );
		self::assertSame( [], $erros );
		self::assertSame( '518095312', $dados['nif'] );
		self::assertSame( "Começa a 1\nde dezembro", $dados['observacoes'] );
	}

	public function test_validar_aceita_nif_e_email_vazios(): void {
		list( , $erros ) = cp_ac_colab_validar( [ 'nome' => 'Só o nome' ] );
		self::assertSame( [], $erros );
	}

	public function test_podar_so_corta_tratados_dos_mais_antigos(): void {
		$p = [
			[ 'id' => 'd', 'estado' => 'enviado' ],
			[ 'id' => 'c', 'estado' => 'registado' ],
			[ 'id' => 'b', 'estado' => 'recebido' ],
			[ 'id' => 'a', 'estado' => 'recusado' ],
		];
		self::assertSame( [ 'd', 'c', 'b' ], array_column( cp_ac_colab_podar( $p, 3 ), 'id' ) );
		self::assertSame( [ 'd', 'b' ], array_column( cp_ac_colab_podar( $p, 2 ), 'id' ) );
	}
}
