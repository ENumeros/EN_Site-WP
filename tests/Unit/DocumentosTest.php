<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_documento_destino_anterior
 * @covers cp_ac_render_documento_form
 * @covers cp_ac_handle_documento_upload
 * @covers cp_ac_render_documentos
 */
final class DocumentosTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'MB_IN_BYTES' ) ) define( 'MB_IN_BYTES', 1048576 );
		foreach ( [ '', '-vistas', '-documentos', '-limpeza', '-backoffice' ] as $f ) self::loadThemeFile( "inc/area-cliente$f.php" );
		// Novembro de 2026; a empresa 5 tem duas pastas.
		Functions\when( 'current_time' )->alias( fn( $f ) => [ 'Y' => '2026', 'n' => '11' ][ $f ] );
		Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
		Functions\when( 'cp_ac_empresa_ativa' )->justReturn( 5 );
		Functions\when( 'get_post_meta' )->alias( fn( $id, $chave ) => ( 5 === $id && '_cp_doc_pastas' === $chave ) ? [
			[ 'id' => '7', 'nome' => 'Compras', 'anos' => [ 2026, 2025 ] ],
			[ 'id' => '8', 'nome' => 'Vendas', 'anos' => [ 2026, 2025 ] ],
		] : '' );
	}

	protected function tearDown(): void {
		$_GET = $_POST = $_FILES = [];
		parent::tearDown();
	}

	public function test_destino_anterior_so_se_for_valido_nesta_empresa(): void {
		$pedido = [ 'cp_doc_pasta' => '8', 'cp_doc_ano' => '2025', 'cp_doc_mes' => '3' ];
		self::assertSame( [ 'pasta_id' => '8', 'ano' => 2025, 'mes' => 3 ], cp_ac_documento_destino_anterior( 5, $pedido ) );
		self::assertNull( cp_ac_documento_destino_anterior( 6, $pedido ) );   // outra empresa
		self::assertNull( cp_ac_documento_destino_anterior( 5, [] ) );
		self::assertNull( cp_ac_documento_destino_anterior( 5, [ 'cp_doc_mes' => '12', 'cp_doc_ano' => '2026' ] + $pedido ) );   // mês futuro
		self::assertNull( cp_ac_documento_destino_anterior( 5, [ 'cp_doc_pasta' => [ '8' ] ] + $pedido ) );
	}

	public function test_formulario_abre_no_destino_do_ultimo_envio(): void {
		self::assertSame( [ '11', '2026' ], $this->selecionados() );   // sem envio: mês atual, primeira pasta
		$_GET = [ 'cp_doc_pasta' => '8', 'cp_doc_ano' => '2025', 'cp_doc_mes' => '3' ];
		self::assertSame( [ '8', '3', '2025' ], $this->selecionados() );
	}

	public function test_lista_em_tabs_por_mes_com_o_destino_do_ultimo_envio(): void {
		$metas = [
			10 => [ '_cp_doc_pasta' => '7', '_cp_doc_ano' => 2026, '_cp_doc_mes' => 10 ],
			11 => [ '_cp_doc_pasta' => '8', '_cp_doc_ano' => 2026, '_cp_doc_mes' => 9 ],
			12 => [],   // enviado antes de haver pastas
		];
		Functions\when( 'get_post_meta' )->alias( fn( $id, $chave ) => 5 === $id && '_cp_doc_pastas' === $chave
			? [ [ 'id' => '7', 'nome' => 'Compras', 'anos' => [ 2026 ] ], [ 'id' => '8', 'nome' => 'Vendas', 'anos' => [ 2026 ] ] ]
			: ( $metas[ $id ][ $chave ] ?? '' ) );
		Functions\when( 'get_posts' )->justReturn( array_map( fn( $id ) => (object) [ 'ID' => $id, 'post_author' => 1 ], array_keys( $metas ) ) );
		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		Functions\when( 'cp_backoffice_utilizador_tem_acesso' )->justReturn( false );
		Functions\when( 'get_attached_file' )->alias( fn( $id ) => "/x/doc$id.pdf" );
		Functions\when( 'wp_basename' )->alias( 'basename' );
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'u' );
		Functions\when( 'get_post_mime_type' )->justReturn( 'application/pdf' );
		Functions\when( 'get_post_time' )->justReturn( '20261125' );
		Functions\when( 'get_permalink' )->justReturn( 'https://site/area/' );
		Functions\when( 'add_query_arg' )->alias( [ self::class, 'add_query_arg' ] );

		$html = $this->lista();
		self::assertStringContainsString( 'doc10.pdf', $html );   // abre outubro
		self::assertStringNotContainsString( 'doc11.pdf', $html );
		self::assertStringContainsString( 'mes=2026-09', $html );
		self::assertStringContainsString( 'mes=sem', $html );

		$_GET = [ 'mes' => '2026-09', 'cp_doc_pasta' => '8', 'cp_doc_ano' => '2026', 'cp_doc_mes' => '9' ];
		$html = $this->lista();
		self::assertStringContainsString( 'doc11.pdf', $html );
		self::assertStringNotContainsString( 'doc10.pdf', $html );
		// Trocar de mês não muda o destino do formulário.
		self::assertStringContainsString( 'cp_doc_pasta=8&cp_doc_ano=2026&cp_doc_mes=9&mes=2026-10#lista-documentos', $html );

		$_GET = [ 'mes' => 'sem' ];
		self::assertStringContainsString( 'doc12.pdf', $this->lista() );
	}

	private function lista(): string {
		$this->stubs_de_ecra();
		ob_start();
		cp_ac_render_documentos( 5 );
		return ob_get_clean();
	}

	public function test_envio_recusado_tambem_volta_com_o_destino(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'wp_get_referer' )->justReturn( 'https://site/area/?vista=documentos&cp_doc_mes=9' );
		Functions\when( 'cp_ac_exigir_ferramenta' )->justReturn( null );
		Functions\when( 'add_query_arg' )->alias( [ self::class, 'add_query_arg' ] );
		Functions\when( 'wp_safe_redirect' )->alias( function ( $url ) { throw new \RuntimeException( $url ); } );
		$_POST = [ 'cp_documento_nonce' => 'x', 'pasta_id' => '8', 'ano' => '2025', 'mes' => '3' ];

		$this->expectExceptionMessage( 'https://site/area/?vista=documentos&cp_doc_mes=3&cp_doc_pasta=8&cp_doc_ano=2025&cp_erro=documento_em_falta' );
		cp_ac_handle_documento_upload();
	}

	public static function add_query_arg( ...$args ): string {
		[ $novos, $url ] = is_array( $args[0] ) ? [ $args[0], $args[1] ] : [ [ $args[0] => $args[1] ], $args[2] ];
		[ $base, $query ] = explode( '?', $url, 2 ) + [ 1 => '' ];
		parse_str( $query, $atuais );
		return $base . '?' . http_build_query( array_merge( $atuais, $novos ) );
	}

	/** Valores marcados no formulário, pela ordem pasta, mês, ano. */
	private function selecionados(): array {
		$this->stubs_de_ecra();
		ob_start();
		cp_ac_render_documento_form();
		preg_match_all( '/<option value="([^"]*)"[^>]* selected>/', ob_get_clean(), $m );
		return $m[1];
	}

	private function stubs_de_ecra(): void {
		foreach ( [ 'esc_url', 'esc_attr', 'esc_html', 'admin_url', 'size_format' ] as $fn ) Functions\when( $fn )->returnArg( 1 );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_max_upload_size' )->justReturn( 10485760 );
		Functions\when( 'selected' )->alias( function ( $a, $b ) { if ( (string) $a === (string) $b ) echo 'selected'; } );
	}
}
