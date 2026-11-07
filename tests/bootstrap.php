<?php
/**
 * Bootstrap dos testes unitários (PHPUnit + Brain Monkey).
 *
 * Carrega só o autoload do Composer — nada de WordPress real é incluído
 * aqui. Cada teste é responsável por invocar Brain\Monkey\setUp()/tearDown()
 * e por incluir só os ficheiros do tema que precisa, depois de ter os stubs
 * das funções WordPress que esses ficheiros chamam no topo (add_action,
 * register_post_type, etc.) prontos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
