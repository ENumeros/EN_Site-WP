<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_mensagem_adicionar
 * @covers cp_ac_mensagem_resolver_empresa
 * @covers cp_ac_conversa_pertence
 * @covers cp_ac_mensagens_conversa
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
			[ 'id' => 'antiga', 'conversa_id' => 'conv-1', 'texto' => 'Já existente' ],
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
					&& 'conv-1' === $mensagens[1]['conversa_id']
					&& 'cliente' === $mensagens[1]['autor_tipo'];
			} );

		$criada = cp_ac_mensagem_adicionar( 5, 'conv-1', 'Olá, precisava do recibo de março', 3, false );

		self::assertSame( 'nova-uuid', $criada['id'] );
		self::assertSame( 'Maria', $criada['autor_nome'] );
	}

	public function test_adicionar_marca_autor_tipo_equipa(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'nova-uuid' );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'display_name' => 'Equipa EN' ] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$criada = cp_ac_mensagem_adicionar( 5, 'conv-1', 'Já tratámos do pedido', 1, true );

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

	// ─── cp_ac_conversa_pertence ────────────────────────────────────────────

	public function test_conversa_pertence_aceita_conversa_da_empresa(): void {
		Functions\when( 'get_post_meta' )->alias( function ( $post_id, $chave ) {
			if ( '_cp_conversas' === $chave ) {
				return [ [ 'id' => 'conv-1', 'assunto' => 'IVA' ] ];
			}
			return [];
		} );

		self::assertTrue( cp_ac_conversa_pertence( 'conv-1', 5 ) );
	}

	public function test_conversa_pertence_recusa_conversa_de_outra_empresa(): void {
		Functions\when( 'get_post_meta' )->alias( function ( $post_id, $chave ) {
			if ( '_cp_conversas' === $chave ) {
				return [ [ 'id' => 'conv-1', 'assunto' => 'IVA' ] ];
			}
			return [];
		} );

		self::assertFalse( cp_ac_conversa_pertence( 'conv-de-outra-empresa', 5 ) );
	}

	// ─── cp_ac_mensagens_conversa ───────────────────────────────────────────

	public function test_mensagens_conversa_filtra_só_pelo_conversa_id_pedido(): void {
		Functions\when( 'get_post_meta' )->alias( function ( $post_id, $chave ) {
			if ( '_cp_mensagens' === $chave ) {
				return [
					[ 'id' => 'm1', 'conversa_id' => 'conv-1', 'texto' => 'Sobre o IVA' ],
					[ 'id' => 'm2', 'conversa_id' => 'conv-2', 'texto' => 'Sobre a renda' ],
					[ 'id' => 'm3', 'conversa_id' => 'conv-1', 'texto' => 'Segunda sobre o IVA' ],
				];
			}
			return [];
		} );

		$mensagens = cp_ac_mensagens_conversa( 5, 'conv-1' );

		self::assertCount( 2, $mensagens );
		self::assertSame( 'm1', $mensagens[0]['id'] );
		self::assertSame( 'm3', $mensagens[1]['id'] );
	}
}
