<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_bo_calc_tipo_evento
 * @covers cp_bo_calc_conversas_por_responder
 * @covers cp_bo_calc_pedidos_por_responder
 * @covers cp_bo_calc_visita
 */
final class BackofficeResumoTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_list_pluck' )->alias( function ( $lista, $campo ) {
			return array_map( function ( $item ) use ( $campo ) {
				return isset( $item[ $campo ] ) ? $item[ $campo ] : null;
			}, $lista );
		} );
		self::loadThemeFile( 'inc/area-cliente-backoffice-resumo.php' );
	}

	public function test_tipo_guardado_tem_prioridade(): void {
		self::assertSame( 'mensagens', cp_bo_calc_tipo_evento( [ 'tipo' => 'mensagens', 'titulo' => 'Novo documento' ] ) );
	}

	public function test_tipo_dos_eventos_antigos_vem_do_titulo(): void {
		self::assertSame( 'documentos', cp_bo_calc_tipo_evento( [ 'titulo' => 'Novo documento de cliente' ] ) );
		self::assertSame( 'mensagens', cp_bo_calc_tipo_evento( [ 'titulo' => 'Nova mensagem de cliente — IVA' ] ) );
		self::assertSame( 'lembretes', cp_bo_calc_tipo_evento( [ 'titulo' => 'Lembrete concluído pelo cliente' ] ) );
		self::assertSame( 'pendentes', cp_bo_calc_tipo_evento( [ 'titulo' => 'Nova resposta de cliente' ] ) );
		self::assertSame( 'outros', cp_bo_calc_tipo_evento( [ 'titulo' => 'Outra coisa' ] ) );
	}

	public function test_conversa_so_espera_se_a_ultima_mensagem_e_do_cliente(): void {
		$conversas = [
			[ 'id' => 'a', 'assunto' => 'IVA' ],
			[ 'id' => 'b', 'assunto' => 'Salários' ],
			[ 'id' => 'c', 'assunto' => 'Arquivada', 'arquivada' => true ],
			[ 'id' => 'd', 'assunto' => 'Vazia' ],
		];
		$mensagens = [
			[ 'conversa_id' => 'a', 'autor_tipo' => 'equipa', 'enviado_em' => '2026-11-10 10:00:00' ],
			[ 'conversa_id' => 'a', 'autor_tipo' => 'cliente', 'enviado_em' => '2026-11-10 11:00:00' ],
			[ 'conversa_id' => 'b', 'autor_tipo' => 'cliente', 'enviado_em' => '2026-11-09 09:00:00' ],
			[ 'conversa_id' => 'b', 'autor_tipo' => 'equipa', 'enviado_em' => '2026-11-09 12:00:00' ],
			[ 'conversa_id' => 'c', 'autor_tipo' => 'cliente', 'enviado_em' => '2026-11-08 09:00:00' ],
		];

		$espera = cp_bo_calc_conversas_por_responder( $conversas, $mensagens );

		self::assertSame( [ 'a' ], array_column( $espera, 'id' ) );
		self::assertSame( '2026-11-10 11:00:00', $espera[0]['em'] );
	}

	public function test_pedidos_por_responder_ignoram_os_ja_respondidos(): void {
		$documentos = [
			[ 'id' => 'd1', 'pedido_em' => '2026-11-05' ],
			[ 'id' => 'd2', 'pedido_em' => '2026-11-01' ],
			[ 'id' => 'd3', 'pedido_em' => '' ],
		];
		$movimentos = [ [ 'id' => 'm1' ], [ 'id' => 'm2' ] ];
		$respostas  = [ [ 'item_id' => 'd2' ], [ 'item_id' => 'm1' ] ];

		$r = cp_bo_calc_pedidos_por_responder( $documentos, $movimentos, $respostas );

		self::assertSame( 2, $r['documentos'] );
		self::assertSame( 1, $r['movimentos'] );
		self::assertSame( '2026-11-05', $r['desde'] );
	}

	public function test_primeira_visita_nao_marca_nada_como_novo(): void {
		list( $guardar, $desde ) = cp_bo_calc_visita( '', 1000 );
		self::assertSame( 1000, $desde );
		self::assertSame( [ 'anterior' => 1000, 'atual' => 1000 ], $guardar );
	}

	public function test_recarregar_dentro_da_janela_mantem_o_novo(): void {
		list( $guardar, $desde ) = cp_bo_calc_visita( [ 'anterior' => 500, 'atual' => 1000 ], 1000 + 60 );
		self::assertSame( 500, $desde );
		self::assertSame( 500, $guardar['anterior'] );
	}

	public function test_voltar_depois_da_janela_avanca_a_visita(): void {
		list( $guardar, $desde ) = cp_bo_calc_visita( [ 'anterior' => 500, 'atual' => 1000 ], 1000 + CP_BO_JANELA_VISITA + 1 );
		self::assertSame( 1000, $desde );
		self::assertSame( [ 'anterior' => 1000, 'atual' => 1000 + CP_BO_JANELA_VISITA + 1 ], $guardar );
	}
}
