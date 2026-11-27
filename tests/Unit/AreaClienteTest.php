<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_calc_texto_convite
 * @covers cp_ac_calc_destino_login
 * @covers cp_ac_calc_filtrar_destinatarios
 * @covers cp_ac_calc_conta_bloqueada
 * @covers cp_ac_calc_documentos_tratados
 * @covers cp_ac_calc_campos_documento
 * @covers cp_ac_calc_filtro_documentos
 * @covers cp_ac_calc_filtro_movimentos
 * @covers cp_ac_calc_avisos_pendentes
 * @covers cp_ac_calc_mes_valido
 * @covers cp_ac_calc_tabs_meses
 * @covers cp_ac_vista_documentos_em_falta
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
				'pedir_faturas',
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

	public function test_pendentes_novos_so_avisam_o_que_a_work_pede(): void {
		// Importação do e-fatura (ou Work antiga, sem o campo): nunca avisa.
		self::assertSame( [], cp_ac_calc_avisos_pendentes( null, true, true ) );
		self::assertSame( [], cp_ac_calc_avisos_pendentes( [], true, true ) );
		// Ações da equipa: só do tipo pedido, e só se houver novidade.
		self::assertSame( [ 'documentos' ], cp_ac_calc_avisos_pendentes( [ 'documentos' ], true, true ) );
		self::assertSame( [ 'movimentos' ], cp_ac_calc_avisos_pendentes( [ 'movimentos' ], true, true ) );
		self::assertSame( [], cp_ac_calc_avisos_pendentes( [ 'documentos' ], false, true ) );
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

	public function test_conta_bloqueada_so_com_marca_e_nunca_admin(): void {
		self::assertTrue( cp_ac_calc_conta_bloqueada( '2026-11-20 10:00:00', false ) );
		self::assertFalse( cp_ac_calc_conta_bloqueada( '', false ) );
		self::assertFalse( cp_ac_calc_conta_bloqueada( '2026-11-20 10:00:00', true ) );
	}

	public function test_item_checklist_aceita_recebido_e_sem_dados(): void {
		$r = cp_ac_normalizar_item_checklist( [ 'id' => 'saft-2026-03', 'mes' => '2026-03', 'estado' => 'recebido' ] );
		self::assertSame( 'recebido', $r['estado'] );
		$r = cp_ac_normalizar_item_checklist( [ 'id' => 'saft-2026-03', 'mes' => '2026-03', 'estado' => 'sem_dados' ] );
		self::assertSame( 'sem_dados', $r['estado'] );
		$r = cp_ac_normalizar_item_checklist( [ 'id' => 'saft-2026-03', 'mes' => '2026-03', 'estado' => 'outro' ] );
		self::assertSame( 'pendente', $r['estado'] );
		self::assertNull( cp_ac_normalizar_item_checklist( [ 'id' => 'x', 'mes' => '2026-3' ] ) );
	}

	public function test_tabs_anos_corrente_sempre_e_por_omissao(): void {
		$itens = [
			[ 'mes' => '2026-10', 'estado' => 'pendente' ],
			[ 'mes' => '2026-09', 'estado' => 'recebido' ],
			[ 'mes' => '2025-12', 'estado' => 'pendente' ],
		];
		$pendente = function ( $item ) { return 'pendente' === $item['estado']; };

		$t = cp_ac_calc_tabs_anos( $itens, 2027, null, $pendente );
		self::assertSame( [ 2027, 2026, 2025 ], $t['anos'] );
		self::assertSame( 2027, $t['ativo'] );
		self::assertSame( [ 2027 => 0, 2026 => 1, 2025 => 1 ], $t['por_tratar'] );

		self::assertSame( 2025, cp_ac_calc_tabs_anos( $itens, 2026, '2025' )['ativo'] );
		self::assertSame( 2026, cp_ac_calc_tabs_anos( $itens, 2026, '1999' )['ativo'] );
	}

	public function test_documentos_tratados_limpa_estado_e_mes(): void {
		$r = cp_ac_calc_documentos_tratados( [
			[ 'id' => '11', 'titulo' => 'Fatura FT 1', 'mes' => '2026-10', 'estado' => 'recebido' ],
			[ 'id' => '12', 'titulo' => 'Fatura FT 2', 'mes' => '2026-1', 'estado' => 'outro' ],
			[ 'titulo' => 'sem id' ],
			'lixo',
		] );
		self::assertCount( 2, $r );
		self::assertSame( [ 'id' => '11', 'titulo' => 'Fatura FT 1', 'pedido_em' => '', 'mes' => '2026-10', 'estado' => 'recebido',
			'data' => '', 'nif' => '', 'nome' => '', 'tipo' => '', 'numero' => '', 'valor' => null ], $r[0] );
		self::assertSame( 'tratado', $r[1]['estado'] );
		self::assertSame( '', $r[1]['mes'] );
		self::assertSame( [], cp_ac_calc_documentos_tratados( null ) );
	}

	public function test_campos_documento_limpa_data_e_valor(): void {
		self::assertSame(
			[ 'data' => '2026-10-03', 'nif' => '500000000', 'nome' => 'BANCO BPI SA', 'tipo' => 'Fatura', 'numero' => 'FT 004/1', 'valor' => 10.4 ],
			cp_ac_calc_campos_documento( [ 'data' => '2026-10-03', 'nif' => '500000000', 'nome' => 'BANCO BPI SA', 'tipo' => 'Fatura', 'numero' => 'FT 004/1', 'valor' => '10.40' ] )
		);
		$r = cp_ac_calc_campos_documento( [ 'data' => '03/10/2026', 'valor' => 'abc', 'nome' => [ 'x' ] ] );
		self::assertSame( '', $r['data'] );
		self::assertNull( $r['valor'] );
		self::assertSame( '', $r['nome'] );
	}

	public function test_tabs_meses_mais_recente_com_algo_por_tratar(): void {
		$docs = [
			[ 'id' => '1', 'mes' => '2026-09' ],
			[ 'id' => '2', 'mes' => '2026-10' ],
			[ 'id' => '3', 'mes' => '2025-12' ],
			[ 'id' => '4' ],                       // Work antiga, sem mês
			[ 'id' => '5', 'mes' => '2026-13' ],   // inválido
		];
		$t = cp_ac_calc_tabs_meses( $docs );
		self::assertSame( [ '2026-10', '2026-09', '2025-12', '' ], $t['meses'] );
		self::assertSame( '2026-10', $t['ativo'] );
		self::assertSame( [ '2026-10' => 1, '2026-09' => 1, '2025-12' => 1, '' => 2 ], $t['contagem'] );

		// O de outubro já foi respondido: abre setembro; outubro continua lá.
		$t = cp_ac_calc_tabs_meses( $docs, null, function ( $d ) { return '2' !== $d['id']; } );
		self::assertSame( '2026-09', $t['ativo'] );
		self::assertSame( 0, $t['contagem']['2026-10'] );

		self::assertSame( '2025-12', cp_ac_calc_tabs_meses( $docs, '2025-12' )['ativo'] );
		self::assertSame( '', cp_ac_calc_tabs_meses( $docs, 'sem' )['ativo'] );
		self::assertSame( '2026-10', cp_ac_calc_tabs_meses( $docs, '2024-01' )['ativo'] );
		self::assertSame( [ 'meses' => [], 'ativo' => '', 'contagem' => [] ], cp_ac_calc_tabs_meses( [] ) );
	}

	public function test_vista_documentos_em_falta_em_tabs_por_mes(): void {
		self::loadThemeFile( 'inc/area-cliente-documentos.php' );
		self::loadThemeFile( 'inc/area-cliente-vistas.php' );
		Functions\when( 'cp_ac_empresa_ativa' )->justReturn( 5 );
		Functions\when( 'get_post_meta' )->alias( fn( $id, $chave ) => [
			'_cp_pendentes_documentos'          => [
				[ 'id' => '1', 'titulo' => 'Fatura setembro', 'pedido_em' => '2026-11-25', 'mes' => '2026-09' ],
				[ 'id' => '2', 'titulo' => 'Fatura outubro', 'pedido_em' => '2026-11-25', 'mes' => '2026-10' ],
			],
			'_cp_pendentes_documentos_tratados' => [
				[ 'id' => '3', 'titulo' => 'Tratada agosto', 'pedido_em' => '', 'mes' => '2026-08', 'estado' => 'tratado' ],
			],
		][ $chave ] ?? [] );
		foreach ( [ 'esc_url', 'esc_attr', 'esc_html', 'admin_url' ] as $fn ) Functions\when( $fn )->returnArg( 1 );
		Functions\when( 'cp_ac_vista_url' )->justReturn( '/area/?vista=documentos_em_falta' );
		Functions\when( 'add_query_arg' )->alias( fn( $k, $v, $u ) => "$u&$k=$v" );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'current_time' )->justReturn( '2026' );

		$_GET = [];
		$html = $this->vista();
		self::assertStringContainsString( 'class="ac-tab is-on"', $html );
		self::assertStringNotContainsString( 'Pedido a', $html );
		self::assertStringContainsString( 'Outubro 2026', $html );
		self::assertStringContainsString( 'Setembro 2026', $html );
		self::assertStringContainsString( 'Fatura outubro', $html );
		self::assertStringNotContainsString( 'Fatura setembro', $html );

		$_GET = [ 'mes' => '2026-09' ];
		$html = $this->vista();
		self::assertStringContainsString( 'Fatura setembro', $html );
		self::assertStringNotContainsString( 'Fatura outubro', $html );

		$_GET = [ 'filtro' => 'tratados' ];
		$html = $this->vista();
		self::assertStringContainsString( '&filtro=tratados&mes=2026-08', $html );
		self::assertStringContainsString( 'Tratada agosto', $html );
		$_GET = [];
	}

	private function vista(): string {
		ob_start();
		cp_ac_vista_documentos_em_falta();
		return ob_get_clean();
	}

	public function test_filtro_documentos_pendentes_por_omissao(): void {
		self::assertSame( 'pendentes', cp_ac_calc_filtro_documentos( '' ) );
		self::assertSame( 'pendentes', cp_ac_calc_filtro_documentos( 'outro' ) );
		self::assertSame( 'tratados', cp_ac_calc_filtro_documentos( 'tratados' ) );
		self::assertSame( 'todos', cp_ac_calc_filtro_documentos( 'todos' ) );
	}

	public function test_filtro_movimentos_pendentes_por_omissao(): void {
		self::assertSame( 'pendentes', cp_ac_calc_filtro_movimentos( '' ) );
		self::assertSame( 'pendentes', cp_ac_calc_filtro_movimentos( 'tratados' ) );
		self::assertSame( 'respondidos', cp_ac_calc_filtro_movimentos( 'respondidos' ) );
		self::assertSame( 'todos', cp_ac_calc_filtro_movimentos( 'todos' ) );
	}
}
