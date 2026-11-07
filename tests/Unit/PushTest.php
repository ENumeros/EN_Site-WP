<?php

namespace ContabilidadePro\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * @covers cp_push_base64url_encode
 * @covers cp_push_base64url_decode
 * @covers cp_push_guardar_subscricao
 * @covers cp_push_remover_subscricao
 * @covers cp_push_der_para_raw_ecdsa
 * @covers cp_push_hkdf_expand
 *
 * A entrega real de uma notificação (cifra aes128gcm + pedido HTTP ao
 * serviço de push do browser) depende de criptografia e de um endpoint
 * real — não é testável aqui. Ver tests/README.md.
 */
final class PushTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		self::loadThemeFile( 'inc/area-cliente-push.php' );
	}

	public function test_base64url_faz_round_trip_em_vários_tamanhos(): void {
		foreach ( [ '', 'a', 'ab', 'abc', random_bytes( 65 ) ] as $original ) {
			$codificado = cp_push_base64url_encode( $original );
			self::assertStringNotContainsString( '+', $codificado );
			self::assertStringNotContainsString( '/', $codificado );
			self::assertStringNotContainsString( '=', $codificado );
			self::assertSame( $original, cp_push_base64url_decode( $codificado ) );
		}
	}

	public function test_guardar_subscricao_rejeita_dados_incompletos(): void {
		self::assertFalse( cp_push_guardar_subscricao( 1, [ 'endpoint' => 'https://exemplo.test/x' ] ) );
	}

	public function test_guardar_subscricao_substitui_a_anterior_com_o_mesmo_endpoint(): void {
		$existente = [ 'endpoint' => 'https://exemplo.test/mesmo', 'keys' => [ 'p256dh' => 'antigo', 'auth' => 'antigo' ], 'criada_em' => '2024-01-01' ];
		Functions\when( 'get_user_meta' )->justReturn( [ $existente ] );
		Functions\when( 'current_time' )->justReturn( '2024-02-02' );

		Functions\expect( 'update_user_meta' )
			->once()
			->withArgs( function ( $user_id, $chave, $subscricoes ) {
				return 1 === $user_id
					&& 'cp_push_subscricoes' === $chave
					&& 1 === count( $subscricoes )
					&& 'novo' === $subscricoes[0]['keys']['p256dh'];
			} );

		cp_push_guardar_subscricao( 1, [ 'endpoint' => 'https://exemplo.test/mesmo', 'keys' => [ 'p256dh' => 'novo', 'auth' => 'novo' ] ] );
	}

	public function test_remover_subscricao_tira_só_a_do_endpoint_indicado(): void {
		Functions\when( 'get_user_meta' )->justReturn( [
			[ 'endpoint' => 'https://exemplo.test/a', 'keys' => [ 'p256dh' => '1', 'auth' => '1' ] ],
			[ 'endpoint' => 'https://exemplo.test/b', 'keys' => [ 'p256dh' => '2', 'auth' => '2' ] ],
		] );

		Functions\expect( 'update_user_meta' )
			->once()
			->withArgs( function ( $user_id, $chave, $subscricoes ) {
				return 1 === count( $subscricoes ) && 'https://exemplo.test/b' === $subscricoes[0]['endpoint'];
			} );

		cp_push_remover_subscricao( 1, 'https://exemplo.test/a' );
	}

	public function test_der_para_raw_ecdsa_remove_padding_de_sinal_e_repoe_zeros_a_esquerda(): void {
		// r "real" tem só 16 bytes (os outros 16 são zero à esquerda, que o
		// DER omite por serem desnecessários) — a função tem de os repor.
		$r_real = '';
		for ( $i = 1; $i <= 16; $i++ ) $r_real .= chr( $i );
		$r_der = "\x02" . chr( strlen( $r_real ) ) . $r_real; // sem 0x00 — primeiro byte (0x01) já é < 0x80.

		// s "real" tem 32 bytes e começa com um byte ≥ 0x80 — o DER tem de
		// o prefixar com 0x00 para não ser lido como negativo; a função
		// tem de remover esse 0x00.
		$s_real = chr( 0x80 ) . str_repeat( chr( 0xAA ), 31 );
		$s_der  = "\x02" . chr( 1 + strlen( $s_real ) ) . "\x00" . $s_real;

		$conteudo = $r_der . $s_der;
		$der      = "\x30" . chr( strlen( $conteudo ) ) . $conteudo;

		$raw = cp_push_der_para_raw_ecdsa( $der, 32 );

		self::assertSame( 64, strlen( $raw ) );
		self::assertSame( str_repeat( "\x00", 16 ) . $r_real, substr( $raw, 0, 32 ) );
		self::assertSame( $s_real, substr( $raw, 32, 32 ) );
	}

	public function test_hkdf_expand_devolve_sempre_um_prefixo_do_mesmo_valor(): void {
		$prk  = random_bytes( 32 );
		$info = 'Content-Encoding: aes128gcm' . "\x00";

		$curto  = cp_push_hkdf_expand( $prk, $info, 12 );
		$longo  = cp_push_hkdf_expand( $prk, $info, 32 );

		self::assertSame( 12, strlen( $curto ) );
		self::assertSame( 32, strlen( $longo ) );
		self::assertSame( $curto, substr( $longo, 0, 12 ) );
	}

	public function test_hkdf_extract_usa_o_salt_como_chave_hmac(): void {
		$salt = random_bytes( 16 );
		$ikm  = random_bytes( 32 );

		self::assertSame( hash_hmac( 'sha256', $ikm, $salt, true ), cp_push_hkdf_extract( $salt, $ikm ) );
	}
}
