<?php

/**
 * KeyFactory Tests
 *
 * @package FairDidManager\Tests\Keys
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Keys;

use Exception;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\Keys\Key;
use FAIR\DID\Keys\KeyFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for KeyFactory
 */
class KeyFactoryTest extends TestCase
{
    /**
     * Test decode_public_key with secp256k1 key.
     */
    public function test_decode_public_key_secp256k1(): void
    {
        $original = EcKey::generate(Key::CURVE_K256);
        $encoded = $original->encode_public();

        $decoded = KeyFactory::decode_public_key($encoded);

        $this->assertInstanceOf(EcKey::class, $decoded);
        $this->assertFalse($decoded->is_private());
        $this->assertSame($encoded, $decoded->encode_public());
    }

    /**
     * Test decode_public_key with P-256 key.
     */
    public function test_decode_public_key_p256(): void
    {
        $original = EcKey::generate(Key::CURVE_P256);
        $encoded = $original->encode_public();

        $decoded = KeyFactory::decode_public_key($encoded);

        $this->assertInstanceOf(EcKey::class, $decoded);
        $this->assertSame(Key::CURVE_P256, $decoded->get_curve());
    }

    /**
     * Test decode_public_key with Ed25519 key.
     */
    public function test_decode_public_key_ed25519(): void
    {
        $original = EdDsaKey::generate();
        $encoded = $original->encode_public();

        $decoded = KeyFactory::decode_public_key($encoded);

        $this->assertInstanceOf(EdDsaKey::class, $decoded);
        $this->assertFalse($decoded->is_private());
        $this->assertSame($encoded, $decoded->encode_public());
    }

    /**
     * Test decode_private_key with secp256k1 key.
     */
    public function test_decode_private_key_secp256k1(): void
    {
        $original = EcKey::generate(Key::CURVE_K256);
        $encoded = $original->encode_private();

        $decoded = KeyFactory::decode_private_key($encoded);

        $this->assertInstanceOf(EcKey::class, $decoded);
        $this->assertTrue($decoded->is_private());
        $this->assertSame($original->encode_public(), $decoded->encode_public());
    }

    /**
     * Test decode_private_key with P-256 key.
     */
    public function test_decode_private_key_p256(): void
    {
        $original = EcKey::generate(Key::CURVE_P256);
        $encoded = $original->encode_private();

        $decoded = KeyFactory::decode_private_key($encoded);

        $this->assertInstanceOf(EcKey::class, $decoded);
        $this->assertSame(Key::CURVE_P256, $decoded->get_curve());
    }

    /**
     * Test decode_private_key with Ed25519 key.
     */
    public function test_decode_private_key_ed25519(): void
    {
        $original = EdDsaKey::generate();
        $encoded = $original->encode_private();

        $decoded = KeyFactory::decode_private_key($encoded);

        $this->assertInstanceOf(EdDsaKey::class, $decoded);
        $this->assertTrue($decoded->is_private());
        $this->assertSame($original->encode_public(), $decoded->encode_public());
    }

    /**
     * Test decode_did_key with EC key.
     */
    public function test_decode_did_key_ec(): void
    {
        $original = EcKey::generate();
        $did = 'did:key:' . $original->encode_public();

        $decoded = KeyFactory::decode_did_key($did);

        $this->assertInstanceOf(EcKey::class, $decoded);
        $this->assertSame($original->encode_public(), $decoded->encode_public());
    }

    /**
     * Test decode_did_key with EdDSA key.
     */
    public function test_decode_did_key_eddsa(): void
    {
        $original = EdDsaKey::generate();
        $did = 'did:key:' . $original->encode_public();

        $decoded = KeyFactory::decode_did_key($did);

        $this->assertInstanceOf(EdDsaKey::class, $decoded);
        $this->assertSame($original->encode_public(), $decoded->encode_public());
    }

    /**
     * Test decode_did_key throws for invalid format.
     */
    public function test_decode_did_key_throws_for_invalid_format(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid DID format');

        KeyFactory::decode_did_key('invalid:format');
    }

    /**
     * Test decode_did_key throws for non-multibase key.
     */
    public function test_decode_did_key_throws_for_non_multibase(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid DID format');

        KeyFactory::decode_did_key('did:key:notmultibase');
    }

    /**
     * Test encode_did_key with EC key.
     */
    public function test_encode_did_key_ec(): void
    {
        $key = EcKey::generate();
        $did = KeyFactory::encode_did_key($key);

        $this->assertStringStartsWith('did:key:z', $did);
        $this->assertSame('did:key:' . $key->encode_public(), $did);
    }

    /**
     * Test encode_did_key with EdDSA key.
     */
    public function test_encode_did_key_eddsa(): void
    {
        $key = EdDsaKey::generate();
        $did = KeyFactory::encode_did_key($key);

        $this->assertStringStartsWith('did:key:z', $did);
        $this->assertSame('did:key:' . $key->encode_public(), $did);
    }

    /**
     * Test decode_public_key throws for unsupported curve.
     */
    public function test_decode_public_key_throws_for_unsupported_curve(): void
    {
        $this->expectException(Exception::class);

        KeyFactory::decode_public_key('zinvalidkey');
    }

    /**
     * Test decode_private_key throws for unsupported curve.
     */
    public function test_decode_private_key_throws_for_unsupported_curve(): void
    {
        $this->expectException(Exception::class);

        KeyFactory::decode_private_key('zinvalidkey');
    }

    /**
     * Test public key caching.
     */
    public function test_public_key_caching(): void
    {
        $original = EcKey::generate();
        $encoded = $original->encode_public();

        $decoded1 = KeyFactory::decode_public_key($encoded);
        $decoded2 = KeyFactory::decode_public_key($encoded);

        $this->assertSame($decoded1, $decoded2);
    }

    /**
     * Test private key caching.
     */
    public function test_private_key_caching(): void
    {
        $original = EcKey::generate();
        $encoded = $original->encode_private();

        $decoded1 = KeyFactory::decode_private_key($encoded);
        $decoded2 = KeyFactory::decode_private_key($encoded);

        $this->assertSame($decoded1, $decoded2);
    }

    /**
     * Test round trip via factory.
     */
    public function test_round_trip_via_factory(): void
    {
        $original = EcKey::generate();
        $did = KeyFactory::encode_did_key($original);
        $decoded = KeyFactory::decode_did_key($did);

        $this->assertSame($original->encode_public(), $decoded->encode_public());
    }
}
