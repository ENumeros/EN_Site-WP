<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base para testes unitários do tema: isola as funções do WordPress com
 * Brain Monkey, sem precisar de base de dados nem de um WordPress real.
 *
 * Cada subclasse testa um ficheiro de `inc/` de cada vez. O ficheiro só é
 * `require`-ido uma vez por processo (ver loadThemeFile()) — nos testes
 * seguintes já existe, por isso os stubs definidos aqui só precisam de
 * cobrir o que o ficheiro corre no topo (add_action, register_post_type,
 * register_rest_route, ...) na primeira vez que for carregado.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Chamadas de topo de ficheiro mais comuns em inc/*.php — no-op.
		foreach ( [ 'add_action', 'add_filter', 'register_post_type', 'register_rest_route' ] as $fn ) {
			Functions\when( $fn )->justReturn( true );
		}

		// Tradução: devolve o próprio texto, sem domínio nem escaping.
		foreach ( [ '__', 'esc_html__', 'esc_attr__' ] as $fn ) {
			Functions\when( $fn )->returnArg( 1 );
		}

		// Sanitização: devolve o valor tal como recebido (os testes que
		// precisam de verificar sanitização a sério estubam-la à parte).
		foreach ( [ 'sanitize_text_field', 'sanitize_key', 'wp_unslash' ] as $fn ) {
			Functions\when( $fn )->returnArg( 1 );
		}
		foreach ( [ '_e', 'esc_html_e', 'esc_attr_e' ] as $fn ) {
			Functions\when( $fn )->echoArg( 1 );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Inclui um ficheiro do tema uma única vez por processo de testes.
	 * Chamar DEPOIS de definir, no teste, quaisquer stubs extra que esse
	 * ficheiro precise no topo (para além dos genéricos já cobertos acima).
	 */
	protected static function loadThemeFile( string $relativePath ): void {
		static $carregados = [];
		$caminho = dirname( __DIR__, 2 ) . '/' . ltrim( $relativePath, '/' );
		if ( isset( $carregados[ $caminho ] ) ) {
			return;
		}
		require $caminho;
		$carregados[ $caminho ] = true;
	}
}
