<?php

/**
 * EcKey Tests
 *
 * @package FairDidManager\Tests\Keys
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Keys;

use Exception;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\Key;
use FAIR\DID\Keys\KeyExporter;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for EcKey
 */
class EcKeyTest extends TestCase
{
    /**
     * Test generating a secp256k1 key.
     */
    public function test_generate_secp256k1_key(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);

        $this->assertInstanceOf(EcKey::class, $key);
        $this->assertTrue($key->is_private());
        $this->assertSame(Key::CURVE_K256, $key->get_curve());
    }

    /**
     * Test generating a P-256 key.
     */
    public function test_generate_p256_key(): void
    {
        $key = EcKey::generate(Key::CURVE_P256);

        $this->assertInstanceOf(EcKey::class, $key);
        $this->assertTrue($key->is_private());
        $this->assertSame(Key::CURVE_P256, $key->get_curve());
    }

    /**
     * Test default curve is secp256k1.
     */
    public function test_default_curve_is_secp256k1(): void
    {
        $key = EcKey::generate();

        $this->assertSame(Key::CURVE_K256, $key->get_curve());
    }

    /**
     * Test encode public key returns multibase string.
     */
    public function test_encode_public_returns_multibase(): void
    {
        $key = EcKey::generate();
        $public = $key->encode_public();

        $this->assertStringStartsWith('z', $public);
        $this->assertGreaterThan(40, strlen($public));
    }

    /**
     * Test encode private key returns multibase string.
     */
    public function test_encode_private_returns_multibase(): void
    {
        $key = EcKey::generate();
        $private = $key->encode_private();

        $this->assertStringStartsWith('z', $private);
        $this->assertGreaterThan(40, strlen($private));
    }

    /**
     * Test from_public creates public-only key.
     */
    public function test_from_public_creates_public_key(): void
    {
        $original = EcKey::generate();
        $public_str = $original->encode_public();

        $public_key = EcKey::from_public($public_str);

        $this->assertFalse($public_key->is_private());
        $this->assertSame($public_str, $public_key->encode_public());
    }

    /**
     * Test from_private creates private key.
     */
    public function test_from_private_creates_private_key(): void
    {
        $original = EcKey::generate();
        $private_str = $original->encode_private();
        $public_str = $original->encode_public();

        $restored = EcKey::from_private($private_str);

        $this->assertTrue($restored->is_private());
        $this->assertSame($public_str, $restored->encode_public());
    }

    /**
     * Test encode_private throws for public key.
     */
    public function test_encode_private_throws_for_public_key(): void
    {
        $original = EcKey::generate();
        $public_key = EcKey::from_public($original->encode_public());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot encode private key for a public key');
        $public_key->encode_private();
    }

    /**
     * Test signing data.
     */
    public function test_sign_data(): void
    {
        $key = EcKey::generate();
        $data = hash('sha256', 'test data', false);
        $signature = $key->sign($data);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    /**
     * Test sign throws for public key.
     */
    public function test_sign_throws_for_public_key(): void
    {
        $original = EcKey::generate();
        $public_key = EcKey::from_public($original->encode_public());
        $data = hash('sha256', 'test data', false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot sign with a public key');
        $public_key->sign($data);
    }

    /**
     * Test signatures are unique per message.
     */
    public function test_signatures_unique_per_message(): void
    {
        $key = EcKey::generate();
        $sig1 = $key->sign(hash('sha256', 'message 1', false));
        $sig2 = $key->sign(hash('sha256', 'message 2', false));

        $this->assertNotSame($sig1, $sig2);
    }

    /**
     * Test generated keys are unique.
     */
    public function test_generated_keys_are_unique(): void
    {
        $key1 = EcKey::generate();
        $key2 = EcKey::generate();

        $this->assertNotSame($key1->encode_public(), $key2->encode_public());
        $this->assertNotSame($key1->encode_private(), $key2->encode_private());
    }

    /**
     * Test round trip secp256k1 key.
     */
    public function test_round_trip_secp256k1(): void
    {
        $original = EcKey::generate(Key::CURVE_K256);
        $private_str = $original->encode_private();
        $public_str = $original->encode_public();

        $restored = EcKey::from_private($private_str);

        $this->assertSame(Key::CURVE_K256, $restored->get_curve());
        $this->assertSame($public_str, $restored->encode_public());
        $this->assertSame($private_str, $restored->encode_private());
    }

    /**
     * Test round trip P-256 key.
     */
    public function test_round_trip_p256(): void
    {
        $original = EcKey::generate(Key::CURVE_P256);
        $private_str = $original->encode_private();
        $public_str = $original->encode_public();

        $restored = EcKey::from_private($private_str);

        $this->assertSame(Key::CURVE_P256, $restored->get_curve());
        $this->assertSame($public_str, $restored->encode_public());
    }

    /**
     * Test to_array returns expected structure.
     */
    public function test_to_array_structure(): void
    {
        $key = EcKey::generate();
        $data = $key->to_array();

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('curve', $data);
        $this->assertArrayHasKey('public_key', $data);
        $this->assertArrayHasKey('did_key', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayNotHasKey('private_key', $data);

        $this->assertSame('EC', $data['type']);
        $this->assertSame(Key::CURVE_K256, $data['curve']);
    }

    /**
     * Test to_array includes private key when requested.
     */
    public function test_to_array_includes_private_key(): void
    {
        $key = EcKey::generate();
        $data = $key->to_array(true);

        $this->assertArrayHasKey('private_key', $data);
        $this->assertSame($key->encode_private(), $data['private_key']);
    }

    /**
     * Test to_json returns valid JSON.
     */
    public function test_to_json_returns_valid_json(): void
    {
        $key = EcKey::generate();
        $json = $key->to_json();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('EC', $decoded['type']);
    }

    /**
     * Test export to string.
     */
    public function test_export_to_string(): void
    {
        $key = EcKey::generate();
        $output = $key->export();

        $this->assertIsString($output);
        $this->assertJson($output);
    }

    /**
     * Test export to file.
     */
    public function test_export_to_file(): void
    {
        $key = EcKey::generate();
        $file_path = sys_get_temp_dir() . '/test-ec-key-' . uniqid() . '.json';

        $result = $key->export($file_path, true);

        $this->assertTrue($result);
        $this->assertFileExists($file_path);

        $content = file_get_contents($file_path);
        $decoded = json_decode($content, true);
        $this->assertSame('EC', $decoded['type']);
        $this->assertArrayHasKey('private_key', $decoded);

        unlink($file_path);
    }

    /**
     * Test export as text format.
     */
    public function test_export_as_text(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);
        $output = $key->export(null, false, 'text');

        $this->assertStringContainsString('=== EC Key (secp256k1) ===', $output);
        $this->assertStringContainsString('Public Key:', $output);
        $this->assertStringContainsString('DID Key:', $output);
    }

    /**
     * Test export as env format.
     */
    public function test_export_as_env(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);
        $output = $key->export(null, true, 'env');

        $this->assertStringContainsString('EC_SECP256K1_PUBLIC_KEY=', $output);
        $this->assertStringContainsString('EC_SECP256K1_DID_KEY=', $output);
        $this->assertStringContainsString('EC_SECP256K1_PRIVATE_KEY=', $output);
    }

    /**
     * Test get_exporter returns KeyExporter.
     */
    public function test_get_exporter(): void
    {
        $key = EcKey::generate();
        $exporter = $key->get_exporter();

        $this->assertInstanceOf(KeyExporter::class, $exporter);
    }

    /**
     * Test from_public throws for invalid curve.
     */
    public function test_from_public_throws_for_invalid_curve(): void
    {
        $this->expectException(Exception::class);
        EcKey::from_public('zinvalidkey');
    }
}
