<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_tarefa_pode_concluir
 * @covers cp_ac_tarefa_adicionar
 * @covers cp_ac_tarefa_concluir
 */
final class TarefasTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-backoffice.php' );
		self::loadThemeFile( 'inc/area-cliente-tarefas.php' );
	}

	// ─── cp_ac_tarefa_pode_concluir ─────────────────────────────────────────

	public function test_criador_pode_concluir_a_propria_tarefa(): void {
		Functions\when( 'user_can' )->justReturn( false );

		$tarefa = [ 'criado_por_user_id' => 7 ];

		self::assertTrue( cp_ac_tarefa_pode_concluir( $tarefa, 7 ) );
	}

	public function test_outro_cliente_nao_pode_concluir_tarefa_de_outrem(): void {
		Functions\when( 'user_can' )->justReturn( false );

		$tarefa = [ 'criado_por_user_id' => 7 ];

		self::assertFalse( cp_ac_tarefa_pode_concluir( $tarefa, 8 ) );
	}

	public function test_equipa_pode_concluir_qualquer_tarefa(): void {
		Functions\when( 'user_can' )->alias( function ( $user_id, $capacidade ) {
			return 'cp_backoffice' === $capacidade;
		} );

		$tarefa = [ 'criado_por_user_id' => 7 ];

		self::assertTrue( cp_ac_tarefa_pode_concluir( $tarefa, 99 ) );
	}

	// ─── cp_ac_tarefa_adicionar ─────────────────────────────────────────────

	public function test_adicionar_acumula_sem_perder_as_anteriores(): void {
		Functions\when( 'get_post_meta' )->justReturn( [
			[ 'id' => 'antiga', 'titulo' => 'Já existente' ],
		] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'nova-uuid' );

		Functions\expect( 'update_post_meta' )
			->once()
			->withArgs( function ( $empresa_id, $chave, $tarefas ) {
				return 5 === $empresa_id
					&& '_cp_tarefas' === $chave
					&& 2 === count( $tarefas )
					&& 'Já existente' === $tarefas[0]['titulo']
					&& 'Novo contrato' === $tarefas[1]['titulo']
					&& 'cliente' === $tarefas[1]['criado_por'];
			} );

		$criada = cp_ac_tarefa_adicionar( 5, 'Novo contrato', 3, false );

		self::assertSame( 'nova-uuid', $criada['id'] );
		self::assertFalse( $criada['concluido'] );
	}

	public function test_adicionar_marca_criado_por_equipa(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'nova-uuid' );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$criada = cp_ac_tarefa_adicionar( 5, 'Enviar contrato da renda', 1, true );

		self::assertSame( 'equipa', $criada['criado_por'] );
	}

	// ─── cp_ac_tarefa_concluir ──────────────────────────────────────────────

	public function test_concluir_recusa_sem_permissao(): void {
		Functions\when( 'get_post_meta' )->justReturn( [
			[ 'id' => 'abc', 'criado_por_user_id' => 7, 'concluido' => false ],
		] );
		Functions\when( 'user_can' )->justReturn( false );

		Functions\expect( 'update_post_meta' )->never();

		self::assertFalse( cp_ac_tarefa_concluir( 5, 'abc', true, 8 ) );
	}

	public function test_concluir_atualiza_quando_permitido(): void {
		Functions\when( 'get_post_meta' )->justReturn( [
			[ 'id' => 'abc', 'criado_por_user_id' => 7, 'concluido' => false ],
		] );
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'current_time' )->justReturn( '2024-02-02 10:00:00' );

		Functions\expect( 'update_post_meta' )
			->once()
			->withArgs( function ( $empresa_id, $chave, $tarefas ) {
				return true === $tarefas[0]['concluido']
					&& '2024-02-02 10:00:00' === $tarefas[0]['concluido_em']
					&& 7 === $tarefas[0]['concluido_por_user_id'];
			} );

		self::assertTrue( cp_ac_tarefa_concluir( 5, 'abc', true, 7 ) );
	}

	public function test_concluir_devolve_falso_para_id_inexistente(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );

		self::assertFalse( cp_ac_tarefa_concluir( 5, 'nao-existe', true, 7 ) );
	}
}
