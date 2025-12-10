<?php
/**
 * Key interface.
 *
 * @package FairDidManager\Keys
 */

declare(strict_types=1);

namespace FAIR\DID\Keys;

use Exception;

/**
 * Key interface for cryptographic key operations.
 *
 * @package FairDidManager\Keys
 */
interface Key {

	/**
	 * Curve constant for secp256k1.
	 */
	public const CURVE_K256 = 'secp256k1';

	/**
	 * Curve constant for P-256.
	 */
	public const CURVE_P256 = 'p256';

	/**
	 * Curve constant for Ed25519.
	 */
	public const CURVE_ED25519 = 'ed25519';

	/**
	 * Multicodec prefix for secp256k1 public key.
	 */
	public const PREFIX_K256_PUB = "\xe7\x01";

	/**
	 * Multicodec prefix for secp256k1 private key.
	 */
	public const PREFIX_K256_PRIV = "\x81\x26";

	/**
	 * Multicodec prefix for P-256 public key.
	 */
	public const PREFIX_P256_PUB = "\x80\x24";

	/**
	 * Multicodec prefix for P-256 private key.
	 */
	public const PREFIX_P256_PRIV = "\x06\x26";

	/**
	 * Multicodec prefix for Ed25519 public key.
	 */
	public const PREFIX_ED25519_PUB = "\xed\x01";

	/**
	 * Multicodec prefix for Ed25519 private key.
	 */
	public const PREFIX_ED25519_PRIV = "\x80\x26";

	/**
	 * Does this key represent a private key?
	 *
	 * @return bool True if the key is a private keypair, false if it is a public key.
	 */
	public function is_private(): bool;

	/**
	 * Sign data using the private key.
	 *
	 * @param string $data The data to sign, as a hex-encoded string.
	 * @return string The signature encoded as a hex-encoded string.
	 * @throws Exception If the key is public.
	 */
	public function sign( string $data ): string;

	/**
	 * Convert a key to a multibase public key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase public key string (starts with z).
	 * @throws Exception If the curve is not supported.
	 */
	public function encode_public(): string;

	/**
	 * Convert a key to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase private key string (starts with z).
	 * @throws Exception If the curve is not supported or key is public.
	 */
	public function encode_private(): string;

	/**
	 * Convert a multibase public key string to a key.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @param string $key The multibase public key string (starts with z).
	 * @return static The key object.
	 * @throws Exception If the curve is not supported.
	 */
	public static function from_public( string $key ): static;

	/**
	 * Convert a multibase private key string to a key.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @param string $key The multibase private key string (starts with z).
	 * @return static The key object.
	 * @throws Exception If the curve is not supported.
	 */
	public static function from_private( string $key ): static;

	/**
	 * Get the curve name.
	 *
	 * @return string The curve name.
	 */
	public function get_curve(): string;

	/**
	 * Convert key data to an array.
	 *
	 * @param bool $include_private Whether to include the private key.
	 * @return array<string, mixed> The key data array.
	 */
	public function to_array( bool $include_private = false ): array;

	/**
	 * Convert key data to a JSON string.
	 *
	 * @param bool $include_private Whether to include the private key.
	 * @param int  $flags           JSON encoding flags.
	 * @return string The JSON string.
	 */
	public function to_json( bool $include_private = false, int $flags = JSON_PRETTY_PRINT ): string;

	/**
	 * Export key to output or file.
	 *
	 * @param string|null $file_path       Path to write to, or null for stdout.
	 * @param bool        $include_private Whether to include the private key.
	 * @param string      $format          Output format: 'json', 'text', or 'env'.
	 * @return bool|string True if written to file, or the output string if no file path.
	 */
	public function export(
		?string $file_path = null,
		bool $include_private = false,
		string $format = 'json'
	): bool|string;
}
