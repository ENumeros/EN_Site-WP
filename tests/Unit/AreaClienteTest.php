<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_calc_texto_convite
 * @covers cp_ac_calc_destino_login
 * @covers cp_ac_calc_filtrar_destinatarios
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

	public function test_convite_nomeia_as_empresas_e_tem_o_link(): void {
		$t = cp_ac_calc_texto_convite( 'Ana Silva', [ 'Alfa Lda', 'Beta SA', 'Gama' ], 'ana@x.pt', 'https://s/rp', 'https://s/area-de-cliente/' );
		self::assertStringContainsString( 'Convite', $t['assunto'] );
		self::assertStringStartsWith( 'Olá Ana Silva,', $t['corpo'] );
		self::assertStringContainsString( 'para Alfa Lda, Beta SA e Gama.', $t['corpo'] );
		self::assertStringContainsString( "https://s/rp
", $t['corpo'] );
		self::assertStringContainsString( 'ana@x.pt', $t['corpo'] );
	}

	public function test_convite_sem_nome_nem_empresas(): void {
		$t = cp_ac_calc_texto_convite( '', [], 'a@x.pt', 'u', 'v' );
		self::assertStringStartsWith( "Olá,
", $t['corpo'] );
		self::assertStringContainsString( 'Área de Cliente da Entre Números. ', $t['corpo'] );
	}

	public function test_destino_login_cliente_vai_para_area_de_cliente(): void {
		$area = 'https://s/area-de-cliente/';
		self::assertSame( $area, cp_ac_calc_destino_login( true, '', 'https://s/wp-admin/', $area ) );
		self::assertSame( $area, cp_ac_calc_destino_login( true, 'https://s/wp-admin/profile.php', 'https://s/wp-admin/profile.php', $area ) );
		self::assertSame( $area, cp_ac_calc_destino_login( true, 'https://s/wp-login.php?checkemail=confirm', 'x', $area ) );
	}

	public function test_destino_login_respeita_pagina_pedida_e_equipa(): void {
		$area = 'https://s/area-de-cliente/';
		self::assertSame( 'https://s/area-de-cliente/?empresa=3', cp_ac_calc_destino_login( true, 'https://s/area-de-cliente/?empresa=3', 'https://s/area-de-cliente/?empresa=3', $area ) );
		self::assertSame( 'https://s/wp-admin/', cp_ac_calc_destino_login( false, '', 'https://s/wp-admin/', $area ) );
	}

	public function test_filtrar_destinatarios_sem_pedido_envia_a_todos(): void {
		$r = cp_ac_calc_filtrar_destinatarios( [ 'a@x.pt', 'b@x.pt' ], null );
		self::assertSame( [ 'a@x.pt', 'b@x.pt' ], $r['enviar'] );
		self::assertSame( [], $r['ignorados'] );
	}

	public function test_filtrar_destinatarios_so_os_pedidos_validos(): void {
		$r = cp_ac_calc_filtrar_destinatarios( [ 'Ana@X.pt', 'b@x.pt' ], [ 'ana@x.pt', 'intruso@y.pt', 'ANA@x.pt' ] );
		self::assertSame( [ 'Ana@X.pt' ], $r['enviar'] );
		self::assertSame( [ 'intruso@y.pt' ], $r['ignorados'] );
	}
}
