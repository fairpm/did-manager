<?php

/**
 * PlcOperation Tests
 *
 * @package FairDidManager\Tests\PLC
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\PLC;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\Keys\Key;
use FAIR\DID\PLC\PlcOperation;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for PlcOperation
 */
class PlcOperationTest extends TestCase
{
    private EcKey $rotation_key;
    private EdDsaKey $verification_key;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Generate test keys
        $this->rotation_key = EcKey::generate(Key::CURVE_K256);
        $this->verification_key = EdDsaKey::generate(Key::CURVE_ED25519);
    }

    /**
     * Test creating a basic PLC operation.
     */
    public function test_create_basic_operation(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $this->assertSame('plc_operation', $operation->type);
        $this->assertCount(1, $operation->rotation_keys);
        $this->assertCount(1, $operation->verification_methods);
        $this->assertCount(1, $operation->also_known_as);
        $this->assertEmpty($operation->services);
        $this->assertNull($operation->prev);
        $this->assertNull($operation->sig);
    }

    /**
     * Test creating operation with services.
     */
    public function test_create_operation_with_services(): void
    {
        $services = [
            'atproto_pds' => [
                'type' => 'AtprotoPersonalDataServer',
                'endpoint' => 'https://pds.example.com'
            ]
        ];

        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            $services,
            null
        );

        $this->assertCount(1, $operation->services);
        $this->assertArrayHasKey('atproto_pds', $operation->services);
        $this->assertSame('AtprotoPersonalDataServer', $operation->services['atproto_pds']['type']);
        $this->assertSame('https://pds.example.com', $operation->services['atproto_pds']['endpoint']);
    }

    /**
     * Test creating operation with previous CID.
     */
    public function test_create_operation_with_prev(): void
    {
        $prev_cid = 'bafyreibjo4xmgaevkgud7mbifn3dzp4v4lyyzrxdtpne7wsmmfxb3gkzmu';

        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            $prev_cid
        );

        $this->assertSame($prev_cid, $operation->prev);
    }

    /**
     * Test creating tombstone operation.
     */
    public function test_create_tombstone_operation(): void
    {
        $prev_cid = 'bafyreibjo4xmgaevkgud7mbifn3dzp4v4lyyzrxdtpne7wsmmfxb3gkzmu';

        $operation = new PlcOperation(
            'plc_tombstone',
            [],
            [],
            [],
            [],
            $prev_cid
        );

        $this->assertSame('plc_tombstone', $operation->type);
        $this->assertSame($prev_cid, $operation->prev);
    }

    /**
     * Test JSON serialization.
     */
    public function test_json_serialize(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $json = $operation->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('rotationKeys', $json);
        $this->assertArrayHasKey('verificationMethods', $json);
        $this->assertArrayHasKey('alsoKnownAs', $json);
        $this->assertArrayHasKey('services', $json);
        $this->assertArrayHasKey('prev', $json);

        $this->assertSame('plc_operation', $json['type']);
        $this->assertCount(1, $json['rotationKeys']);
        $this->assertStringStartsWith('did:key:', $json['rotationKeys'][0]);
        $this->assertArrayHasKey('atproto', $json['verificationMethods']);
        $this->assertStringStartsWith('did:key:', $json['verificationMethods']['atproto']);
        $this->assertNull($json['prev']);
    }

    /**
     * Test JSON serialization with signature.
     */
    public function test_json_serialize_with_signature(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        // Sign the operation
        $signed = $operation->sign($this->rotation_key);

        $json = $signed->jsonSerialize();

        $this->assertArrayHasKey('sig', $json);
        $this->assertIsString($json['sig']);
        $this->assertNotEmpty($json['sig']);
    }

    /**
     * Test CBOR encoding produces valid output.
     */
    public function test_encode_cbor(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $cbor = $operation->encode_cbor();

        $this->assertIsString($cbor);
        $this->assertNotEmpty($cbor);
        
        // CBOR map starts with major type 5 (0xA0-0xB7 for maps with 0-23 elements)
        $firstByte = ord($cbor[0]);
        $majorType = $firstByte >> 5;
        $this->assertSame(5, $majorType, 'CBOR should start with a map (major type 5)');
    }

    /**
     * Test DID generation from operation.
     */
    public function test_generate_did(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $did = $operation->generate_did();

        $this->assertStringStartsWith('did:plc:', $did);
        $this->assertSame(32, strlen($did)); // 'did:plc:' (8 chars) + 24 chars
        
        // Verify it's base32 lowercase
        $identifier = substr($did, 8);
        $this->assertMatchesRegularExpression('/^[a-z2-7]{24}$/', $identifier);
    }

    /**
     * Test that same operation produces same DID.
     */
    public function test_generate_did_deterministic(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $did1 = $operation->generate_did();
        $did2 = $operation->generate_did();

        $this->assertSame($did1, $did2);
    }

    /**
     * Test base64url encoding.
     */
    public function test_base64url_encode(): void
    {
        $data = 'Hello World!';
        $encoded = PlcOperation::base64url_encode($data);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    /**
     * Test base64url decoding.
     */
    public function test_base64url_decode(): void
    {
        $data = 'Hello World!';
        $encoded = PlcOperation::base64url_encode($data);
        $decoded = PlcOperation::base64url_decode($encoded);

        $this->assertSame($data, $decoded);
    }

    /**
     * Test base64url round trip with binary data.
     */
    public function test_base64url_round_trip_binary(): void
    {
        $data = random_bytes(64);
        $encoded = PlcOperation::base64url_encode($data);
        $decoded = PlcOperation::base64url_decode($encoded);

        $this->assertSame($data, $decoded);
    }

    /**
     * Test operation with multiple rotation keys.
     */
    public function test_multiple_rotation_keys(): void
    {
        $key1 = EcKey::generate(Key::CURVE_K256);
        $key2 = EcKey::generate(Key::CURVE_K256);

        $operation = new PlcOperation(
            'plc_operation',
            [$key1, $key2],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $this->assertCount(2, $operation->rotation_keys);
        
        $json = $operation->jsonSerialize();
        $this->assertCount(2, $json['rotationKeys']);
    }

    /**
     * Test operation with multiple verification methods.
     */
    public function test_multiple_verification_methods(): void
    {
        $key1 = EdDsaKey::generate(Key::CURVE_ED25519);
        $key2 = EdDsaKey::generate(Key::CURVE_ED25519);

        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            [
                'atproto' => $key1,
                'backup' => $key2
            ],
            ['at://test.example.com'],
            [],
            null
        );

        $this->assertCount(2, $operation->verification_methods);
        
        $json = $operation->jsonSerialize();
        $this->assertCount(2, $json['verificationMethods']);
        $this->assertArrayHasKey('atproto', $json['verificationMethods']);
        $this->assertArrayHasKey('backup', $json['verificationMethods']);
    }

    /**
     * Test operation with multiple handles.
     */
    public function test_multiple_handles(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            [
                'at://test.example.com',
                'at://test2.example.com'
            ],
            [],
            null
        );

        $this->assertCount(2, $operation->also_known_as);
        
        $json = $operation->jsonSerialize();
        $this->assertCount(2, $json['alsoKnownAs']);
    }

    /**
     * Test services are serialized as objects in JSON.
     */
    public function test_services_serialized_as_object(): void
    {
        $services = [
            'atproto_pds' => [
                'type' => 'AtprotoPersonalDataServer',
                'endpoint' => 'https://pds.example.com'
            ]
        ];

        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            $services,
            null
        );

        $json = $operation->jsonSerialize();
        
        $this->assertIsObject($json['services']);
    }

    /**
     * Test empty services are serialized as empty object.
     */
    public function test_empty_services_serialized_as_object(): void
    {
        $operation = new PlcOperation(
            'plc_operation',
            [$this->rotation_key],
            ['atproto' => $this->verification_key],
            ['at://test.example.com'],
            [],
            null
        );

        $json = $operation->jsonSerialize();
        
        $this->assertIsObject($json['services']);
        $this->assertEmpty((array) $json['services']);
    }
}
