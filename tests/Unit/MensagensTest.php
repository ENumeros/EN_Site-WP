<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_mensagem_adicionar
 * @covers cp_ac_mensagem_resolver_empresa
 */
final class MensagensTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-backoffice.php' );
		self::loadThemeFile( 'inc/area-cliente-mensagens.php' );
	}

	// ─── cp_ac_mensagem_adicionar ───────────────────────────────────────────

	public function test_adicionar_acumula_sem_perder_as_anteriores(): void {
		Functions\when( 'get_post_meta' )->justReturn( [
			[ 'id' => 'antiga', 'texto' => 'Já existente' ],
		] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'nova-uuid' );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'display_name' => 'Maria' ] );

		Functions\expect( 'update_post_meta' )
			->once()
			->withArgs( function ( $empresa_id, $chave, $mensagens ) {
				return 5 === $empresa_id
					&& '_cp_mensagens' === $chave
					&& 2 === count( $mensagens )
					&& 'Já existente' === $mensagens[0]['texto']
					&& 'Olá, precisava do recibo de março' === $mensagens[1]['texto']
					&& 'cliente' === $mensagens[1]['autor_tipo'];
			} );

		$criada = cp_ac_mensagem_adicionar( 5, 'Olá, precisava do recibo de março', 3, false );

		self::assertSame( 'nova-uuid', $criada['id'] );
		self::assertSame( 'Maria', $criada['autor_nome'] );
	}

	public function test_adicionar_marca_autor_tipo_equipa(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'nova-uuid' );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'display_name' => 'Equipa EN' ] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$criada = cp_ac_mensagem_adicionar( 5, 'Já tratámos do pedido', 1, true );

		self::assertSame( 'equipa', $criada['autor_tipo'] );
	}

	// ─── cp_ac_mensagem_resolver_empresa ────────────────────────────────────

	public function test_equipa_pode_escolher_qualquer_empresa(): void {
		Functions\when( 'user_can' )->alias( function ( $user_id, $capacidade ) {
			return 'cp_backoffice' === $capacidade;
		} );

		self::assertSame( 42, cp_ac_mensagem_resolver_empresa( 42, 99 ) );
	}

	public function test_cliente_nunca_impoe_empresa_arbitraria(): void {
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'cp_ac_empresa_ativa' )->justReturn( 5 );

		// Mesmo pedindo a empresa 999, um cliente (sem acesso de equipa)
		// fica sempre preso à sua própria empresa ativa — nunca à do pedido.
		self::assertSame( 5, cp_ac_mensagem_resolver_empresa( 999, 3 ) );
	}
}
