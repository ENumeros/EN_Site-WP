<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_ac_documento_destino_anterior
 * @covers cp_ac_render_documento_form
 * @covers cp_ac_handle_documento_upload
 */
final class DocumentosTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'MB_IN_BYTES' ) ) define( 'MB_IN_BYTES', 1048576 );
		self::loadThemeFile( 'inc/area-cliente-documentos.php' );
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
		foreach ( [ 'esc_url', 'esc_attr', 'esc_html', 'admin_url', 'size_format' ] as $fn ) Functions\when( $fn )->returnArg( 1 );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_max_upload_size' )->justReturn( 10485760 );
		Functions\when( 'selected' )->alias( function ( $a, $b ) { if ( (string) $a === (string) $b ) echo 'selected'; } );
		ob_start();
		cp_ac_render_documento_form();
		preg_match_all( '/<option value="([^"]*)"[^>]* selected>/', ob_get_clean(), $m );
		return $m[1];
	}
}
