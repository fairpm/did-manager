<?php
/**
 * DidCodec Tests
 *
 * @package FairDidManager\Tests\Crypto
 */

declare(strict_types=1);

namespace FairDidManager\Tests\Crypto;

use FairDidManager\Crypto\DidCodec;
use FairDidManager\Keys\EcKey;
use FairDidManager\Keys\EdDsaKey;
use FairDidManager\Keys\Key;
use FairDidManager\Plc\PlcOperation;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for DidCodec
 */
class DidCodecTest extends TestCase {

	/**
	 * Test multibase key encoding.
	 */
	public function test_to_multibase_key(): void {
		// Compressed secp256k1 key size.
		$public_key = random_bytes( 33 );
		$multibase  = DidCodec::to_multibase_key( $public_key );

		$this->assertStringStartsWith( 'z', $multibase );
	}

	/**
	 * Test multibase key round trip.
	 */
	public function test_multibase_key_round_trip(): void {
		$public_key = random_bytes( 33 );
		$multibase  = DidCodec::to_multibase_key( $public_key );
		$decoded    = DidCodec::from_multibase_key( $multibase );

		$this->assertSame( DidCodec::MULTICODEC_SECP256K1_PUB, $decoded['codec'] );
		$this->assertSame( $public_key, $decoded['key'] );
	}

	/**
	 * Test multibase key with Ed25519 codec.
	 */
	public function test_to_multibase_key_with_ed25519_codec(): void {
		// Ed25519 key size.
		$public_key = random_bytes( 32 );
		$multibase  = DidCodec::to_multibase_key( $public_key, DidCodec::MULTICODEC_ED25519_PUB );
		$decoded    = DidCodec::from_multibase_key( $multibase );

		$this->assertSame( DidCodec::MULTICODEC_ED25519_PUB, $decoded['codec'] );
		$this->assertSame( $public_key, $decoded['key'] );
	}

	/**
	 * Test canonical JSON simple object.
	 */
	public function test_canonical_json_simple_object(): void {
		$input    = [
			'z' => 1,
			'a' => 2,
			'm' => 3,
		];
		$expected = '{"a":2,"m":3,"z":1}';

		$this->assertSame( $expected, DidCodec::canonical_json( $input ) );
	}

	/**
	 * Test canonical JSON nested objects.
	 */
	public function test_canonical_json_nested_objects(): void {
		$input    = [
			'outer'   => [
				'z' => 'last',
				'a' => 'first',
			],
			'another' => 'value',
		];
		$expected = '{"another":"value","outer":{"a":"first","z":"last"}}';

		$this->assertSame( $expected, DidCodec::canonical_json( $input ) );
	}

	/**
	 * Test canonical JSON array order preserved.
	 */
	public function test_canonical_json_array_order_preserved(): void {
		$input    = [ 'items' => [ 3, 1, 2 ] ];
		$expected = '{"items":[3,1,2]}';

		$this->assertSame( $expected, DidCodec::canonical_json( $input ) );
	}

	/**
	 * Test canonical JSON deterministic output.
	 */
	public function test_canonical_json_deterministic(): void {
		$input   = [
			'z' => 1,
			'a' => 2,
			'm' => 3,
		];
		$result1 = DidCodec::canonical_json( $input );
		$result2 = DidCodec::canonical_json( $input );

		$this->assertSame( $result1, $result2 );
	}

	/**
	 * Test EC key pair generation.
	 */
	public function test_generate_key_pair(): void {
		$key = DidCodec::generate_key_pair();

		$this->assertInstanceOf( EcKey::class, $key );
		$this->assertTrue( $key->is_private() );

		// Public key should be multibase encoded.
		$public = $key->encode_public();
		$this->assertStringStartsWith( 'z', $public );
	}

	/**
	 * Test Ed25519 key pair generation.
	 */
	public function test_generate_ed25519_key_pair(): void {
		$key = DidCodec::generate_ed25519_key_pair();

		$this->assertInstanceOf( EdDsaKey::class, $key );
		$this->assertTrue( $key->is_private() );

		// Public key should be multibase encoded.
		$public = $key->encode_public();
		$this->assertStringStartsWith( 'z', $public );
	}

	/**
	 * Test generated keys are unique.
	 */
	public function test_generated_keys_are_unique(): void {
		$key1 = DidCodec::generate_key_pair();
		$key2 = DidCodec::generate_key_pair();

		$this->assertNotSame( $key1->encode_public(), $key2->encode_public() );
		$this->assertNotSame( $key1->encode_private(), $key2->encode_private() );
	}

	/**
	 * Test SHA-256 hashing.
	 */
	public function test_sha256(): void {
		$data = 'Hello World';
		$hash = DidCodec::sha256( $data );

		$this->assertSame( 32, strlen( $hash ) );
		$this->assertSame( hash( 'sha256', $data, true ), $hash );
	}

	/**
	 * Test EC key signing.
	 */
	public function test_ec_key_sign(): void {
		$key  = DidCodec::generate_key_pair();
		$data = hash( 'sha256', 'Test data to sign', false );

		$signature = $key->sign( $data );

		$this->assertNotEmpty( $signature );
		$this->assertIsString( $signature );
	}

	/**
	 * Test Ed25519 key signing.
	 */
	public function test_ed25519_key_sign(): void {
		$key  = DidCodec::generate_ed25519_key_pair();
		$data = hash( 'sha256', 'Test data to sign', false );

		$signature = $key->sign( $data );

		$this->assertNotEmpty( $signature );
		$this->assertIsString( $signature );
	}

	/**
	 * Test create PLC operation basic.
	 */
	public function test_create_plc_operation_basic(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation = DidCodec::create_plc_operation( $rotation_key, $verification_key );

		$this->assertInstanceOf( PlcOperation::class, $operation );
		$this->assertSame( 'plc_operation', $operation->type );
		$this->assertCount( 1, $operation->rotation_keys );
		$this->assertCount( 1, $operation->verification_methods );
		$this->assertEmpty( $operation->also_known_as );
		$this->assertEmpty( $operation->services );
	}

	/**
	 * Test create PLC operation with handle.
	 */
	public function test_create_plc_operation_with_handle(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();
		$handle           = 'my-plugin';

		$operation = DidCodec::create_plc_operation( $rotation_key, $verification_key, $handle );

		$this->assertContains( 'at://my-plugin', $operation->also_known_as );
	}

	/**
	 * Test create PLC operation with service endpoint.
	 */
	public function test_create_plc_operation_with_service_endpoint(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();
		$service_endpoint = 'https://example.com/service';

		$operation = DidCodec::create_plc_operation( $rotation_key, $verification_key, null, $service_endpoint );

		$this->assertArrayHasKey( 'atproto_pds', $operation->services );
		$this->assertSame( 'AtprotoPersonalDataServer', $operation->services['atproto_pds']['type'] );
		$this->assertSame( $service_endpoint, $operation->services['atproto_pds']['endpoint'] );
	}

	/**
	 * Test sign PLC operation.
	 */
	public function test_sign_plc_operation(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation        = DidCodec::create_plc_operation( $rotation_key, $verification_key );
		$signed_operation = DidCodec::sign_plc_operation( $operation, $rotation_key );

		$this->assertNotNull( $signed_operation->sig );
		$this->assertIsString( $signed_operation->sig );
	}

	/**
	 * Test PLC DID generation.
	 */
	public function test_generate_plc_did(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation        = DidCodec::create_plc_operation( $rotation_key, $verification_key );
		$signed_operation = DidCodec::sign_plc_operation( $operation, $rotation_key );
		$did              = DidCodec::generate_plc_did( $signed_operation );

		$this->assertStringStartsWith( 'did:plc:', $did );
		$this->assertMatchesRegularExpression( '/^did:plc:[a-z0-9]+$/', $did );
	}

	/**
	 * Test PLC DID is deterministic.
	 */
	public function test_generate_plc_did_deterministic(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation        = DidCodec::create_plc_operation( $rotation_key, $verification_key );
		$signed_operation = DidCodec::sign_plc_operation( $operation, $rotation_key );

		$did1 = DidCodec::generate_plc_did( $signed_operation );
		$did2 = DidCodec::generate_plc_did( $signed_operation );

		$this->assertSame( $did1, $did2 );
	}

	/**
	 * Test PlcOperation JSON serialization.
	 */
	public function test_plc_operation_json_serialize(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation = DidCodec::create_plc_operation( $rotation_key, $verification_key );
		$json_data = $operation->jsonSerialize();

		$this->assertArrayHasKey( 'type', $json_data );
		$this->assertArrayHasKey( 'rotationKeys', $json_data );
		$this->assertArrayHasKey( 'verificationMethods', $json_data );
		$this->assertArrayHasKey( 'alsoKnownAs', $json_data );
		$this->assertArrayHasKey( 'services', $json_data );

		// Rotation keys should be did:key: formatted.
		$this->assertStringStartsWith( 'did:key:z', $json_data['rotationKeys'][0] );
	}

	/**
	 * Test PlcOperation CID generation.
	 */
	public function test_plc_operation_get_cid(): void {
		$rotation_key     = DidCodec::generate_key_pair();
		$verification_key = DidCodec::generate_ed25519_key_pair();

		$operation        = DidCodec::create_plc_operation( $rotation_key, $verification_key );
		$signed_operation = DidCodec::sign_plc_operation( $operation, $rotation_key );

		$cid = $signed_operation->get_cid();

		// CID should start with base32 multibase prefix.
		$this->assertStringStartsWith( 'b', $cid );
	}

	/**
	 * Test key encoding and decoding round trip.
	 */
	public function test_key_encode_decode_round_trip(): void {
		$key = DidCodec::generate_key_pair();

		$encoded_public  = $key->encode_public();
		$encoded_private = $key->encode_private();

		// Both should be multibase encoded (start with z).
		$this->assertStringStartsWith( 'z', $encoded_public );
		$this->assertStringStartsWith( 'z', $encoded_private );

		// Decode and verify.
		$decoded_key = EcKey::from_private( $encoded_private );
		$this->assertSame( $encoded_public, $decoded_key->encode_public() );
	}

	/**
	 * Test Ed25519 key encoding and decoding round trip.
	 */
	public function test_ed25519_key_encode_decode_round_trip(): void {
		$key = DidCodec::generate_ed25519_key_pair();

		$encoded_public  = $key->encode_public();
		$encoded_private = $key->encode_private();

		// Both should be multibase encoded (start with z).
		$this->assertStringStartsWith( 'z', $encoded_public );
		$this->assertStringStartsWith( 'z', $encoded_private );

		// Decode and verify.
		$decoded_key = EdDsaKey::from_private( $encoded_private );
		$this->assertSame( $encoded_public, $decoded_key->encode_public() );
	}
}
