<?php

/**
 * EdDSA Key class for Ed25519 curve.
 *
 * @package FairDidManager\Keys
 */

declare(strict_types=1);

namespace FAIR\DID\Keys;

use Elliptic\EdDSA;
use Elliptic\EdDSA\KeyPair;
use Exception;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * EdDSA Key class for Ed25519 curve.
 *
 * Uses the simplito/elliptic-php library for Ed25519 operations.
 *
 * @package FairDidManager\Keys
 */
class EdDsaKey implements Key
{
    /**
     * Constructor.
     *
     * @param KeyPair $keypair The keypair.
     * @param string  $curve   The curve.
     */
    public function __construct(
        protected KeyPair $keypair,
        protected string $curve,
    ) {}

    /**
     * Does this key represent a private key?
     *
     * @return bool True if the key is a private keypair, false if it is a public key.
     */
    public function is_private(): bool
    {
        return null !== $this->keypair->secret();
    }

    /**
     * Sign data using the private key.
     *
     * @param string $data The data to sign, as a hex-encoded string.
     * @return string The signature encoded as a hex-encoded string.
     * @throws Exception If the key is public.
     */
    public function sign(string $data): string
    {
        if (!$this->is_private()) {
            throw new Exception('Cannot sign with a public key');
        }

        return $this->keypair->sign($data)->toHex();
    }

    /**
     * Convert a key to a multibase public key string.
     *
     * @see https://atproto.com/specs/cryptography
     *
     * @return string The multibase public key string (starts with z).
     * @throws Exception If the curve is not supported.
     */
    public function encode_public(): string
    {
        $pub = $this->keypair->getPublic('hex');
        $prefix = match ($this->curve) {
            Key::CURVE_ED25519 => bin2hex(Key::PREFIX_ED25519_PUB),
            default => throw new Exception('Unsupported curve'),
        };
        return Multibase::encode(Multibase::BASE58BTC, hex2bin($prefix . $pub));
    }

    /**
     * Convert a key to a multibase private key string.
     *
     * @see https://atproto.com/specs/cryptography
     *
     * @return string The multibase private key string (starts with z).
     * @throws Exception If the curve is not supported.
     */
    public function encode_private(): string
    {
        if (!$this->is_private()) {
            throw new Exception('Cannot encode private key for a public key');
        }

        $priv = $this->keypair->getSecret('hex');
        $prefix = match ($this->curve) {
            Key::CURVE_ED25519 => bin2hex(Key::PREFIX_ED25519_PRIV),
            default => throw new Exception('Unsupported curve'),
        };
        return Multibase::encode(Multibase::BASE58BTC, hex2bin($prefix . $priv));
    }

    /**
     * Get the curve name.
     *
     * @return string The curve name.
     */
    public function get_curve(): string
    {
        return $this->curve;
    }

    /**
     * Generate a new key.
     *
     * @param string $curve The curve (defaults to Ed25519).
     * @return static A new instance of the key.
     */
    public static function generate(string $curve = Key::CURVE_ED25519): static
    {
        // Generate 32 random bytes for the secret key.
        $secret = random_bytes(32);

        // Convert to KeyPair object.
        $ed = new EdDSA($curve);
        $keypair = $ed->keyFromSecret(bin2hex($secret));
        return new static($keypair, $curve);
    }

    /**
     * Convert a multibase public key string to a keypair object.
     *
     * @see https://atproto.com/specs/cryptography
     *
     * @param string $key The multibase public key string (starts with z).
     * @return static The key object.
     * @throws Exception If the curve is not supported.
     */
    public static function from_public(string $key): static
    {
        $decoded = Multibase::decode($key);

        $curve = match (substr($decoded, 0, 2)) {
            Key::PREFIX_ED25519_PUB => Key::CURVE_ED25519,
            default => throw new Exception('Unsupported curve'),
        };

        $eddsa = new EdDSA($curve);

        $stripped = bin2hex(substr($decoded, 2));
        $keypair = $eddsa->keyFromPublic($stripped, 'hex');
        return new static($keypair, $curve);
    }

    /**
     * Convert a multibase private key string to a keypair object.
     *
     * @see https://atproto.com/specs/cryptography
     *
     * @param string $key The multibase private key string (starts with z).
     * @return static The key object.
     * @throws Exception If the curve is not supported.
     */
    public static function from_private(string $key): static
    {
        $decoded = Multibase::decode($key);

        $curve = match (substr($decoded, 0, 2)) {
            Key::PREFIX_ED25519_PRIV => Key::CURVE_ED25519,
            // Legacy support: public key prefix used for private keys.
            Key::PREFIX_ED25519_PUB => Key::CURVE_ED25519,
            default => throw new Exception('Unsupported curve'),
        };

        $eddsa = new EdDSA($curve);

        $stripped = bin2hex(substr($decoded, 2));
        $keypair = $eddsa->keyFromSecret($stripped, 'hex');
        return new static($keypair, $curve);
    }

    /**
     * Convert key data to an array.
     *
     * @param bool $include_private Whether to include the private key.
     * @return array<string, mixed> The key data array.
     */
    public function to_array(bool $include_private = false): array
    {
        return $this->get_exporter()->to_array($include_private);
    }

    /**
     * Convert key data to a JSON string.
     *
     * @param bool $include_private Whether to include the private key.
     * @param int  $flags           JSON encoding flags.
     * @return string The JSON string.
     */
    public function to_json(bool $include_private = false, int $flags = JSON_PRETTY_PRINT): string
    {
        return $this->get_exporter()->to_json($include_private, $flags);
    }

    /**
     * Export key to output or file.
     *
     * @param string|null $file_path       Path to write to, or null for stdout.
     * @param bool        $include_private Whether to include the private key.
     * @param string      $format          Output format: 'json', 'text', or 'env'.
     * @return bool|string True if written to file, or the output string if no file path.
     * @throws Exception If format is invalid.
     */
    public function export(
        ?string $file_path = null,
        bool $include_private = false,
        string $format = 'json',
    ): bool|string {
        return $this->get_exporter()->export($file_path, $include_private, $format);
    }

    /**
     * Get a KeyExporter instance for this key.
     *
     * @return KeyExporter The exporter instance.
     */
    public function get_exporter(): KeyExporter
    {
        return KeyExporter::for_eddsa($this);
    }
}
