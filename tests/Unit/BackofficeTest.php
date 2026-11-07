<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_backoffice_utilizador_tem_acesso
 * @covers cp_backoffice_registar_evento
 * @covers cp_backoffice_listar_eventos
 */
final class BackofficeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-backoffice.php' );
	}

	public function test_administrador_tem_acesso_sem_precisar_do_papel_novo(): void {
		Functions\when( 'user_can' )->alias( function ( $user_id, $capacidade ) {
			return 'manage_options' === $capacidade;
		} );

		self::assertTrue( cp_backoffice_utilizador_tem_acesso( 1 ) );
	}

	public function test_utilizador_com_papel_equipa_tem_acesso(): void {
		Functions\when( 'user_can' )->alias( function ( $user_id, $capacidade ) {
			return 'cp_backoffice' === $capacidade;
		} );

		self::assertTrue( cp_backoffice_utilizador_tem_acesso( 2 ) );
	}

	public function test_cliente_normal_nao_tem_acesso(): void {
		Functions\when( 'user_can' )->justReturn( false );

		self::assertFalse( cp_backoffice_utilizador_tem_acesso( 3 ) );
	}

	public function test_registar_evento_poe_o_mais_recente_primeiro(): void {
		Functions\when( 'get_option' )->justReturn( [
			[ 'titulo' => 'Antigo', 'corpo' => '', 'url' => '', 'empresa_id' => 0, 'criado_em' => '2024-01-01' ],
		] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02' );

		Functions\expect( 'update_option' )
			->once()
			->withArgs( function ( $chave, $eventos ) {
				return 'cp_eventos_staff' === $chave
					&& 'Novo' === $eventos[0]['titulo']
					&& 'Antigo' === $eventos[1]['titulo'];
			} );

		cp_backoffice_registar_evento( 'Novo', 'corpo', 'https://exemplo.test', 5 );
	}

	public function test_registar_evento_corta_a_100(): void {
		$existentes = [];
		for ( $i = 0; $i < 100; $i++ ) {
			$existentes[] = [ 'titulo' => "Evento $i", 'corpo' => '', 'url' => '', 'empresa_id' => 0, 'criado_em' => '2024-01-01' ];
		}
		Functions\when( 'get_option' )->justReturn( $existentes );
		Functions\when( 'current_time' )->justReturn( '2024-02-02' );

		Functions\expect( 'update_option' )
			->once()
			->withArgs( function ( $chave, $eventos ) {
				return 100 === count( $eventos );
			} );

		cp_backoffice_registar_evento( 'Novo', 'corpo', 'https://exemplo.test' );
	}

	public function test_listar_eventos_respeita_o_limite(): void {
		Functions\when( 'get_option' )->justReturn( [
			[ 'titulo' => '1' ], [ 'titulo' => '2' ], [ 'titulo' => '3' ],
		] );

		self::assertCount( 2, cp_backoffice_listar_eventos( 2 ) );
		self::assertCount( 3, cp_backoffice_listar_eventos( 0 ) );
	}
}
