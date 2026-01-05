<?php

/**
 * DID Codec - Multibase, Canonical JSON, and Signing Helpers
 *
 * @package FairDidManager\Crypto
 */

declare(strict_types=1);

namespace FAIR\DID\Crypto;

use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\Keys\Key;
use FAIR\DID\PLC\PlcOperation;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * DID Codec class for multibase, canonical JSON, and signing helpers.
 *
 * @package FairDidManager\Crypto
 */
class DidCodec
{
    /**
     * Multicodec prefix for secp256k1 public key (0xe7)
     */
    public const MULTICODEC_SECP256K1_PUB = "\xe7\x01";

    /**
     * Multicodec prefix for Ed25519 public key (0xed)
     */
    public const MULTICODEC_ED25519_PUB = "\xed\x01";

    /**
     * Convert a public key to multibase base58btc format
     *
     * @param string $public_key_binary Raw public key bytes.
     * @param string $codec             Multicodec prefix.
     * @return string Multibase encoded key.
     */
    public static function to_multibase_key(
        string $public_key_binary,
        string $codec = self::MULTICODEC_SECP256K1_PUB,
    ): string {
        $prefixed = $codec . $public_key_binary;
        return Multibase::encode(Multibase::BASE58BTC, $prefixed);
    }

    /**
     * Decode a multibase key to raw bytes
     *
     * @param string $multibase_key Multibase encoded key.
     * @return array{codec: string, key: string} Decoded codec and key bytes.
     */
    public static function from_multibase_key(string $multibase_key): array
    {
        $decoded = Multibase::decode($multibase_key);

        // Extract codec (first 2 bytes for variable-length codecs we use).
        $codec = substr($decoded, 0, 2);
        $key = substr($decoded, 2);

        return [
            'codec' => $codec,
            'key' => $key,
        ];
    }

    /**
     * Create canonical JSON (deterministic JSON serialization)
     *
     * Keys are sorted lexicographically at all object levels.
     * No extra whitespace.
     *
     * @param mixed $data Data to serialize.
     * @return string Canonical JSON string.
     */
    public static function canonical_json(mixed $data): string
    {
        $sorted = self::recursive_key_sort($data);
        return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recursively sort array keys
     *
     * @param mixed $data Data to sort.
     * @return mixed Sorted data.
     */
    private static function recursive_key_sort(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        // Check if it's an associative array (object in JSON).
        if (self::is_associative($data)) {
            ksort($data, SORT_STRING);
        }

        // Recursively sort nested arrays.
        foreach ($data as $key => $value) {
            $data[$key] = self::recursive_key_sort($value);
        }

        return $data;
    }

    /**
     * Check if array is associative (object-like)
     *
     * @param array $input Array to check.
     * @return bool True if associative.
     */
    private static function is_associative(array $input): bool
    {
        if (empty($input)) {
            return false;
        }
        return array_keys($input) !== range(0, count($input) - 1);
    }

    /**
     * Generate a new secp256k1 key pair using elliptic-php
     *
     * @param string $curve The curve to use (default: secp256k1).
     * @return EcKey The generated key.
     */
    public static function generate_key_pair(string $curve = Key::CURVE_K256): EcKey
    {
        return EcKey::generate($curve);
    }

    /**
     * Generate a new Ed25519 key pair
     *
     * @return EdDsaKey The generated key.
     */
    public static function generate_ed25519_key_pair(): EdDsaKey
    {
        return EdDsaKey::generate();
    }

    /**
     * Hash data using SHA-256
     *
     * @param string $data Data to hash.
     * @return string Hash bytes.
     */
    public static function sha256(string $data): string
    {
        return hash('sha256', $data, true);
    }

    /**
     * Generate a DID from a signed genesis operation
     *
     * @param PlcOperation $operation The signed genesis operation.
     * @return string The DID (did:plc:...).
     */
    public static function generate_plc_did(PlcOperation $operation): string
    {
        return $operation->generate_did();
    }

    /**
     * Create a PLC genesis operation
     *
     * @param Key         $rotation_key     Rotation key.
     * @param Key         $verification_key Verification key.
     * @param string|null $handle           Optional handle/alias.
     * @param string|null $service_endpoint Optional service endpoint.
     * @return PlcOperation The operation.
     */
    public static function create_plc_operation(
        Key $rotation_key,
        Key $verification_key,
        ?string $handle = null,
        ?string $service_endpoint = null,
    ): PlcOperation {
        $also_known_as = [];
        $services = [];

        if (null !== $handle) {
            $also_known_as[] = 'at://' . $handle;
        }

        if (null !== $service_endpoint) {
            $services['atproto_pds'] = [
                'type' => 'AtprotoPersonalDataServer',
                'endpoint' => $service_endpoint,
            ];
        }

        // Generate a unique ID for the verification key.
        $key_id = substr(hash('sha256', $verification_key->encode_public()), 0, 6);

        return new PlcOperation(
            type: 'plc_operation',
            rotation_keys: [$rotation_key],
            verification_methods: ['fair_' . $key_id => $verification_key],
            also_known_as: $also_known_as,
            services: $services,
        );
    }

    /**
     * Sign a PLC operation
     *
     * @param PlcOperation $operation The operation to sign.
     * @param Key          $key       The signing key.
     * @return PlcOperation The signed operation.
     */
    public static function sign_plc_operation(PlcOperation $operation, Key $key): PlcOperation
    {
        return $operation->sign($key);
    }
}
