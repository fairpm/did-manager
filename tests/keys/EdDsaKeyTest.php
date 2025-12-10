<?php
/**
 * EdDsaKey Tests
 *
 * @package FairDidManager\Tests\Keys
 */

declare(strict_types=1);

namespace FairDidManager\Tests\Keys;

use Exception;
use FairDidManager\Keys\EdDsaKey;
use FairDidManager\Keys\Key;
use FairDidManager\Keys\KeyExporter;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for EdDsaKey
 */
class EdDsaKeyTest extends TestCase {

	/**
	 * Test generating an Ed25519 key.
	 */
	public function test_generate_ed25519_key(): void {
		$key = EdDsaKey::generate();

		$this->assertInstanceOf( EdDsaKey::class, $key );
		$this->assertTrue( $key->is_private() );
		$this->assertSame( Key::CURVE_ED25519, $key->get_curve() );
	}

	/**
	 * Test default curve is Ed25519.
	 */
	public function test_default_curve_is_ed25519(): void {
		$key = EdDsaKey::generate();

		$this->assertSame( Key::CURVE_ED25519, $key->get_curve() );
	}

	/**
	 * Test encode public key returns multibase string.
	 */
	public function test_encode_public_returns_multibase(): void {
		$key    = EdDsaKey::generate();
		$public = $key->encode_public();

		$this->assertStringStartsWith( 'z', $public );
		$this->assertGreaterThan( 40, strlen( $public ) );
	}

	/**
	 * Test encode private key returns multibase string.
	 */
	public function test_encode_private_returns_multibase(): void {
		$key     = EdDsaKey::generate();
		$private = $key->encode_private();

		$this->assertStringStartsWith( 'z', $private );
		$this->assertGreaterThan( 40, strlen( $private ) );
	}

	/**
	 * Test from_public creates public-only key.
	 */
	public function test_from_public_creates_public_key(): void {
		$original   = EdDsaKey::generate();
		$public_str = $original->encode_public();

		$public_key = EdDsaKey::from_public( $public_str );

		$this->assertFalse( $public_key->is_private() );
		$this->assertSame( $public_str, $public_key->encode_public() );
	}

	/**
	 * Test from_private creates private key.
	 */
	public function test_from_private_creates_private_key(): void {
		$original    = EdDsaKey::generate();
		$private_str = $original->encode_private();
		$public_str  = $original->encode_public();

		$restored = EdDsaKey::from_private( $private_str );

		$this->assertTrue( $restored->is_private() );
		$this->assertSame( $public_str, $restored->encode_public() );
	}

	/**
	 * Test encode_private throws for public key.
	 */
	public function test_encode_private_throws_for_public_key(): void {
		$original   = EdDsaKey::generate();
		$public_key = EdDsaKey::from_public( $original->encode_public() );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot encode private key for a public key' );
		$public_key->encode_private();
	}

	/**
	 * Test signing data.
	 */
	public function test_sign_data(): void {
		$key       = EdDsaKey::generate();
		$data      = hash( 'sha256', 'test data', false );
		$signature = $key->sign( $data );

		$this->assertNotEmpty( $signature );
		$this->assertIsString( $signature );
		// Ed25519 signatures are 64 bytes = 128 hex chars.
		$this->assertSame( 128, strlen( $signature ) );
	}

	/**
	 * Test sign throws for public key.
	 */
	public function test_sign_throws_for_public_key(): void {
		$original   = EdDsaKey::generate();
		$public_key = EdDsaKey::from_public( $original->encode_public() );
		$data       = hash( 'sha256', 'test data', false );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Cannot sign with a public key' );
		$public_key->sign( $data );
	}

	/**
	 * Test signatures are unique per message.
	 */
	public function test_signatures_unique_per_message(): void {
		$key  = EdDsaKey::generate();
		$sig1 = $key->sign( hash( 'sha256', 'message 1', false ) );
		$sig2 = $key->sign( hash( 'sha256', 'message 2', false ) );

		$this->assertNotSame( $sig1, $sig2 );
	}

	/**
	 * Test signatures are deterministic for same message.
	 */
	public function test_signatures_deterministic(): void {
		$key     = EdDsaKey::generate();
		$message = hash( 'sha256', 'same message', false );
		$sig1    = $key->sign( $message );
		$sig2    = $key->sign( $message );

		// Ed25519 signatures are deterministic.
		$this->assertSame( $sig1, $sig2 );
	}

	/**
	 * Test generated keys are unique.
	 */
	public function test_generated_keys_are_unique(): void {
		$key1 = EdDsaKey::generate();
		$key2 = EdDsaKey::generate();

		$this->assertNotSame( $key1->encode_public(), $key2->encode_public() );
		$this->assertNotSame( $key1->encode_private(), $key2->encode_private() );
	}

	/**
	 * Test round trip Ed25519 key.
	 */
	public function test_round_trip_ed25519(): void {
		$original    = EdDsaKey::generate();
		$private_str = $original->encode_private();
		$public_str  = $original->encode_public();

		$restored = EdDsaKey::from_private( $private_str );

		$this->assertSame( Key::CURVE_ED25519, $restored->get_curve() );
		$this->assertSame( $public_str, $restored->encode_public() );
	}

	/**
	 * Test to_array returns expected structure.
	 */
	public function test_to_array_structure(): void {
		$key  = EdDsaKey::generate();
		$data = $key->to_array();

		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'curve', $data );
		$this->assertArrayHasKey( 'public_key', $data );
		$this->assertArrayHasKey( 'did_key', $data );
		$this->assertArrayHasKey( 'created_at', $data );
		$this->assertArrayNotHasKey( 'private_key', $data );

		$this->assertSame( 'EdDSA', $data['type'] );
		$this->assertSame( Key::CURVE_ED25519, $data['curve'] );
	}

	/**
	 * Test to_array includes private key when requested.
	 */
	public function test_to_array_includes_private_key(): void {
		$key  = EdDsaKey::generate();
		$data = $key->to_array( true );

		$this->assertArrayHasKey( 'private_key', $data );
		$this->assertSame( $key->encode_private(), $data['private_key'] );
	}

	/**
	 * Test to_json returns valid JSON.
	 */
	public function test_to_json_returns_valid_json(): void {
		$key  = EdDsaKey::generate();
		$json = $key->to_json();

		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'EdDSA', $decoded['type'] );
	}

	/**
	 * Test export to string.
	 */
	public function test_export_to_string(): void {
		$key    = EdDsaKey::generate();
		$output = $key->export();

		$this->assertIsString( $output );
		$this->assertJson( $output );
	}

	/**
	 * Test export to file.
	 */
	public function test_export_to_file(): void {
		$key       = EdDsaKey::generate();
		$file_path = sys_get_temp_dir() . '/test-eddsa-key-' . uniqid() . '.json';

		$result = $key->export( $file_path, true );

		$this->assertTrue( $result );
		$this->assertFileExists( $file_path );

		$content = file_get_contents( $file_path );
		$decoded = json_decode( $content, true );
		$this->assertSame( 'EdDSA', $decoded['type'] );
		$this->assertArrayHasKey( 'private_key', $decoded );

		unlink( $file_path );
	}

	/**
	 * Test export as text format.
	 */
	public function test_export_as_text(): void {
		$key    = EdDsaKey::generate();
		$output = $key->export( null, false, 'text' );

		$this->assertStringContainsString( '=== EdDSA Key (ed25519) ===', $output );
		$this->assertStringContainsString( 'Public Key:', $output );
		$this->assertStringContainsString( 'DID Key:', $output );
	}

	/**
	 * Test export as env format.
	 */
	public function test_export_as_env(): void {
		$key    = EdDsaKey::generate();
		$output = $key->export( null, true, 'env' );

		$this->assertStringContainsString( 'EDDSA_ED25519_PUBLIC_KEY=', $output );
		$this->assertStringContainsString( 'EDDSA_ED25519_DID_KEY=', $output );
		$this->assertStringContainsString( 'EDDSA_ED25519_PRIVATE_KEY=', $output );
	}

	/**
	 * Test get_exporter returns KeyExporter.
	 */
	public function test_get_exporter(): void {
		$key      = EdDsaKey::generate();
		$exporter = $key->get_exporter();

		$this->assertInstanceOf( KeyExporter::class, $exporter );
	}

	/**
	 * Test from_public throws for invalid curve.
	 */
	public function test_from_public_throws_for_invalid_curve(): void {
		$this->expectException( Exception::class );
		EdDsaKey::from_public( 'zinvalidkey' );
	}
}
