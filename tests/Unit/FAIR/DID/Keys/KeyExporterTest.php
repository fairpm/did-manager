<?php

/**
 * KeyExporter Tests
 *
 * @package FairDidManager\Tests\Keys
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Keys;

use Exception;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\Keys\Key;
use FAIR\DID\Keys\KeyExporter;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for KeyExporter
 */
class KeyExporterTest extends TestCase
{
    /**
     * Test constructor.
     */
    public function test_constructor(): void
    {
        $key = EcKey::generate();
        $exporter = new KeyExporter($key, 'TestType', 'TEST');

        $data = $exporter->to_array();
        $this->assertSame('TestType', $data['type']);
    }

    /**
     * Test for_ec factory method.
     */
    public function test_for_ec(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);

        $data = $exporter->to_array();
        $this->assertSame('EC', $data['type']);
    }

    /**
     * Test for_eddsa factory method.
     */
    public function test_for_eddsa(): void
    {
        $key = EdDsaKey::generate();
        $exporter = KeyExporter::for_eddsa($key);

        $data = $exporter->to_array();
        $this->assertSame('EdDSA', $data['type']);
    }

    /**
     * Test to_array basic structure.
     */
    public function test_to_array_structure(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $data = $exporter->to_array();

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('curve', $data);
        $this->assertArrayHasKey('public_key', $data);
        $this->assertArrayHasKey('did_key', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    /**
     * Test to_array without private key.
     */
    public function test_to_array_without_private_key(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $data = $exporter->to_array(false);

        $this->assertArrayNotHasKey('private_key', $data);
    }

    /**
     * Test to_array with private key.
     */
    public function test_to_array_with_private_key(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $data = $exporter->to_array(true);

        $this->assertArrayHasKey('private_key', $data);
        $this->assertSame($key->encode_private(), $data['private_key']);
    }

    /**
     * Test to_array excludes private for public-only key.
     */
    public function test_to_array_excludes_private_for_public_key(): void
    {
        $original = EcKey::generate();
        $public_key = EcKey::from_public($original->encode_public());
        $exporter = KeyExporter::for_ec($public_key);
        $data = $exporter->to_array(true);

        $this->assertArrayNotHasKey('private_key', $data);
    }

    /**
     * Test to_array did_key format.
     */
    public function test_to_array_did_key_format(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $data = $exporter->to_array();

        $this->assertStringStartsWith('did:key:', $data['did_key']);
        $this->assertSame('did:key:' . $key->encode_public(), $data['did_key']);
    }

    /**
     * Test to_array created_at is valid timestamp.
     */
    public function test_to_array_created_at_valid(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $data = $exporter->to_array();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $data['created_at'],
        );
    }

    /**
     * Test to_json returns valid JSON.
     */
    public function test_to_json_valid(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $json = $exporter->to_json();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('EC', $decoded['type']);
    }

    /**
     * Test to_json with custom flags.
     */
    public function test_to_json_custom_flags(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);

        $pretty = $exporter->to_json(false, JSON_PRETTY_PRINT);
        $compact = $exporter->to_json(false, 0);

        $this->assertStringContainsString("\n", $pretty);
        $this->assertStringNotContainsString("\n", $compact);
    }

    /**
     * Test to_json includes private key.
     */
    public function test_to_json_includes_private_key(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $json = $exporter->to_json(true);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('private_key', $decoded);
    }

    /**
     * Test export returns string when no file path.
     */
    public function test_export_returns_string(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export();

        $this->assertIsString($output);
        $this->assertJson($output);
    }

    /**
     * Test export writes to file.
     */
    public function test_export_writes_to_file(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $file_path = sys_get_temp_dir() . '/test-exporter-' . uniqid() . '.json';

        $result = $exporter->export($file_path);

        $this->assertTrue($result);
        $this->assertFileExists($file_path);

        $content = file_get_contents($file_path);
        $this->assertJson($content);

        unlink($file_path);
    }

    /**
     * Test export creates directory if not exists.
     */
    public function test_export_creates_directory(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $dir = sys_get_temp_dir() . '/test-exporter-dir-' . uniqid();
        $file_path = $dir . '/key.json';

        $result = $exporter->export($file_path);

        $this->assertTrue($result);
        $this->assertFileExists($file_path);

        unlink($file_path);
        rmdir($dir);
    }

    /**
     * Test export with text format.
     */
    public function test_export_text_format(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export(null, false, 'text');

        $this->assertStringContainsString('=== EC Key (secp256k1) ===', $output);
        $this->assertStringContainsString('Public Key:', $output);
        $this->assertStringContainsString('DID Key:', $output);
        $this->assertStringContainsString('Created:', $output);
    }

    /**
     * Test export text format with private key.
     */
    public function test_export_text_format_with_private(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export(null, true, 'text');

        $this->assertStringContainsString('Private Key:', $output);
    }

    /**
     * Test export with env format.
     */
    public function test_export_env_format(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export(null, false, 'env');

        $this->assertStringContainsString('EC_SECP256K1_PUBLIC_KEY="', $output);
        $this->assertStringContainsString('EC_SECP256K1_DID_KEY="', $output);
    }

    /**
     * Test export env format with private key.
     */
    public function test_export_env_format_with_private(): void
    {
        $key = EcKey::generate(Key::CURVE_K256);
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export(null, true, 'env');

        $this->assertStringContainsString('EC_SECP256K1_PRIVATE_KEY="', $output);
    }

    /**
     * Test export env format EdDSA prefix.
     */
    public function test_export_env_format_eddsa(): void
    {
        $key = EdDsaKey::generate();
        $exporter = KeyExporter::for_eddsa($key);
        $output = $exporter->export(null, false, 'env');

        $this->assertStringContainsString('EDDSA_ED25519_PUBLIC_KEY="', $output);
    }

    /**
     * Test export throws for invalid format.
     */
    public function test_export_throws_for_invalid_format(): void
    {
        $key = EcKey::generate();
        $exporter = KeyExporter::for_ec($key);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid format: invalid. Use 'json', 'text', or 'env'.");

        $exporter->export(null, false, 'invalid');
    }

    /**
     * Test export P-256 env format.
     */
    public function test_export_p256_env_format(): void
    {
        $key = EcKey::generate(Key::CURVE_P256);
        $exporter = KeyExporter::for_ec($key);
        $output = $exporter->export(null, false, 'env');

        $this->assertStringContainsString('EC_P256_PUBLIC_KEY="', $output);
    }

    /**
     * Test text format EdDSA.
     */
    public function test_text_format_eddsa(): void
    {
        $key = EdDsaKey::generate();
        $exporter = KeyExporter::for_eddsa($key);
        $output = $exporter->export(null, false, 'text');

        $this->assertStringContainsString('=== EdDSA Key (ed25519) ===', $output);
    }
}
