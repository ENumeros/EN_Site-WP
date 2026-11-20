<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_ferramentas
 * @covers cp_ac_empresa_tem_ferramenta
 * @covers cp_ac_set_empresa_ferramentas
 * @covers cp_ac_empresa_by_work_id
 */
final class AreaClienteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente.php' );
	}

	public function test_ferramentas_inclui_as_chaves_conhecidas(): void {
		$chaves = array_keys( cp_ac_ferramentas() );

		self::assertSame(
			[
				'tarefas',
				'mensagens',
				'visao_financeira',
				'conta_corrente',
				'pagamentos',
				'calendario_fiscal',
				'documentos',
				'documentos_em_falta',
				'movimentos_a_esclarecer',
				'extratos',
				'saft',
				'mapas_gestao',
				'recibos_vencimento',
			],
			$chaves
		);
	}

	public function test_empresa_sem_a_ferramenta_ativa_nao_tem_acesso(): void {
		Functions\when( 'get_post_meta' )->justReturn( [ 'tarefas' ] );

		self::assertFalse( cp_ac_empresa_tem_ferramenta( 123, 'conta_corrente' ) );
	}

	public function test_empresa_com_a_ferramenta_ativa_tem_acesso(): void {
		Functions\when( 'get_post_meta' )->justReturn( [ 'tarefas', 'conta_corrente' ] );

		self::assertTrue( cp_ac_empresa_tem_ferramenta( 123, 'conta_corrente' ) );
	}

	public function test_set_empresa_ferramentas_ignora_chaves_desconhecidas(): void {
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 123, '_cp_ferramentas_ativas', [ 'tarefas' ] )
			->andReturn( true );

		// 'ferramenta_inexistente' não está em cp_ac_ferramentas() — tem de
		// ser descartada antes de gravar, nunca guardada "porque veio no
		// pedido". Isto é o que impede a matriz de ferramentas por empresa
		// de aceitar lixo vindo de um formulário adulterado.
		cp_ac_set_empresa_ferramentas( 123, [ 'tarefas', 'ferramenta_inexistente' ] );
	}

	public function test_empresa_by_work_id_sem_work_id_nao_faz_query(): void {
		Functions\expect( 'get_posts' )->never();

		self::assertSame( 0, cp_ac_empresa_by_work_id( '' ) );
	}

	// ─── Acessos por utilizador ──────────────────────────────────────────

	private function empresa_e_acessos( array $da_empresa, $mapa ): void {
		Functions\when( 'get_post_meta' )->justReturn( $da_empresa );
		Functions\when( 'get_user_meta' )->justReturn( $mapa );
	}

	public function test_utilizador_sem_mapa_herda_tudo_o_que_a_empresa_tem(): void {
		$this->empresa_e_acessos( [ 'tarefas', 'pagamentos' ], '' );

		self::assertSame( [ 'tarefas', 'pagamentos' ], cp_ac_utilizador_ferramentas_ativas( 7, 123 ) );
	}

	public function test_utilizador_restrito_noutra_empresa_herda_nesta(): void {
		$this->empresa_e_acessos( [ 'tarefas', 'pagamentos' ], [ 999 => [ 'tarefas' ] ] );

		self::assertSame( [ 'tarefas', 'pagamentos' ], cp_ac_utilizador_ferramentas_ativas( 7, 123 ) );
	}

	public function test_utilizador_restrito_ve_so_a_intersecao_com_a_empresa(): void {
		// 'saft' está no utilizador mas a empresa não o tem: não aparece.
		$this->empresa_e_acessos( [ 'tarefas', 'pagamentos', 'documentos' ], [ 123 => [ 'documentos', 'saft' ] ] );

		self::assertSame( [ 'documentos' ], cp_ac_utilizador_ferramentas_ativas( 7, 123 ) );
		self::assertFalse( cp_ac_utilizador_tem_ferramenta( 7, 123, 'pagamentos' ) );
		self::assertTrue( cp_ac_utilizador_tem_ferramenta( 7, 123, 'documentos' ) );
	}

	public function test_utilizador_restrito_com_lista_vazia_so_ve_o_dashboard(): void {
		$this->empresa_e_acessos( [ 'tarefas' ], [ 123 => [] ] );

		self::assertSame( [], cp_ac_utilizador_ferramentas_ativas( 7, 123 ) );
	}

	public function test_set_user_acessos_descarta_chaves_desconhecidas(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 7, 'cp_ferramentas_por_empresa', [ 123 => [ 'tarefas' ], 456 => [] ] )
			->andReturn( true );

		cp_ac_set_user_acessos( 7, [ '123' => [ 'tarefas', 'inventada' ], 456 => [], 0 => [ 'tarefas' ], 789 => 'lixo' ] );
	}

	public function test_set_user_acessos_vazio_apaga_o_mapa(): void {
		Functions\expect( 'delete_user_meta' )->once()->with( 7, 'cp_ferramentas_por_empresa' )->andReturn( true );
		Functions\expect( 'update_user_meta' )->never();

		cp_ac_set_user_acessos( 7, [] );
	}

	public function test_rest_aplicar_acessos_omitido_nao_mexe(): void {
		Functions\expect( 'update_user_meta' )->never();
		Functions\expect( 'delete_user_meta' )->never();

		cp_ac_rest_aplicar_acessos( 7, null, [ '5' => 123 ], 'cliente' );
	}

	public function test_rest_aplicar_acessos_traduz_work_id_e_ignora_empresas_fora_do_pedido(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 7, 'cp_ferramentas_por_empresa', [ 123 => [ 'recibos_vencimento' ] ] )
			->andReturn( true );

		cp_ac_rest_aplicar_acessos(
			7,
			[ '5' => [ 'recibos_vencimento' ], '6' => null, '99' => [ 'tarefas' ] ],
			[ '5' => 123, '6' => 456 ],
			null
		);
	}

	public function test_rest_aplicar_acessos_na_equipa_apaga_sempre(): void {
		Functions\expect( 'delete_user_meta' )->once()->with( 7, 'cp_ferramentas_por_empresa' )->andReturn( true );

		cp_ac_rest_aplicar_acessos( 7, [ '5' => [ 'tarefas' ] ], [ '5' => 123 ], 'equipa' );
	}
}
