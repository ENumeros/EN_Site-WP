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
				'conta_corrente',
				'calendario_fiscal',
				'documentos',
				'pendentes',
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
}
