<?php
/**
 * EC Key class for secp256k1 and P-256 curves.
 *
 * @package FairDidManager\Keys
 */

declare(strict_types=1);

namespace FAIR\DID\Keys;

use Elliptic\EC;
use Elliptic\EC\KeyPair;
use Elliptic\EC\Signature;
use Elliptic\Utils;
use Exception;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * EC Key class for elliptic curve cryptography.
 *
 * Supports secp256k1 (K-256) and P-256 curves using the simplito/elliptic-php library.
 *
 * @package FairDidManager\Keys
 */
class EcKey implements Key {

	/**
	 * Constructor.
	 *
	 * @param KeyPair $keypair The keypair.
	 * @param string  $curve   The curve.
	 */
	public function __construct(
		protected KeyPair $keypair,
		protected string $curve
	) {
	}

	/**
	 * Does this key represent a private key?
	 *
	 * @return bool True if the key is a private keypair, false if it is a public key.
	 */
	public function is_private(): bool {
		return null !== $this->keypair->getPrivate();
	}

	/**
	 * Convert a keypair object to a multibase public key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase public key string (starts with z).
	 * @throws Exception If the curve is not supported.
	 */
	public function encode_public(): string {
		$pub    = $this->keypair->getPublic( true, 'hex' );
		$prefix = match ( $this->curve ) {
			Key::CURVE_K256 => bin2hex( Key::PREFIX_K256_PUB ),
			Key::CURVE_P256 => bin2hex( Key::PREFIX_P256_PUB ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		return Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $pub ) );
	}

	/**
	 * Convert a keypair object to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase private key string (starts with z).
	 * @throws Exception If the key is public.
	 * @throws Exception If the curve is unsupported.
	 */
	public function encode_private(): string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot encode private key for a public key' );
		}

		$priv   = $this->keypair->getPrivate( 'hex' );
		$prefix = match ( $this->curve ) {
			Key::CURVE_K256 => bin2hex( Key::PREFIX_K256_PRIV ),
			Key::CURVE_P256 => bin2hex( Key::PREFIX_P256_PRIV ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		return Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $priv ) );
	}

	/**
	 * Get the curve name.
	 *
	 * @return string The curve name.
	 */
	public function get_curve(): string {
		return $this->curve;
	}

	/**
	 * Convert a signature to compact (IEEE-P1363) representation.
	 *
	 * (Equivalent to secp256k1_ecdsa_sign_compact().)
	 *
	 * @param EC        $ec        The elliptic curve object.
	 * @param Signature $signature The signature object.
	 * @return string The compact signature.
	 */
	protected function signature_to_compact( EC $ec, Signature $signature ): string {
		$byte_length = (int) ceil( $ec->curve->n->bitLength() / 8 );
		$compact     = Utils::toHex( $signature->r->toArray( 'be', $byte_length ) )
			. Utils::toHex( $signature->s->toArray( 'be', $byte_length ) );
		return $compact;
	}

	/**
	 * Sign data using the private key.
	 *
	 * @param string $data The data to sign, as a hex-encoded string.
	 * @return string The signature encoded as a hex-encoded string.
	 * @throws Exception If the key is public.
	 */
	public function sign( string $data ): string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot sign with a public key' );
		}

		/**
		 * Hash with SHA-256, then sign, using canonical (low-S) form.
		 *
		 * @var Signature $signature
		 */
		$signature = $this->keypair->sign(
			$data,
			'hex',
			[
				'canonical' => true,
			]
		);

		// Convert to compact (IEEE-P1363) form for secp256k1.
		if ( Key::CURVE_K256 === $this->curve ) {
			return $this->signature_to_compact( $this->keypair->ec, $signature );
		}
		return $signature->toDER( 'hex' );
	}

	/**
	 * Generate a new keypair.
	 *
	 * We use NIST K-256 as the default to match ATProto.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @param string $curve The curve.
	 * @return static The generated keypair object.
	 * @throws Exception If the curve is not supported.
	 */
	public static function generate( string $curve = Key::CURVE_K256 ): static {
		$ec = new EC( $curve );
		return new static( $ec->genKeyPair(), $curve );
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
	public static function from_public( string $key ): static {
		$decoded = Multibase::decode( $key );

		$curve = match ( substr( $decoded, 0, 2 ) ) {
			Key::PREFIX_P256_PUB => Key::CURVE_P256,
			Key::PREFIX_K256_PUB => Key::CURVE_K256,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$ec = new EC( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair  = $ec->keyFromPublic( $stripped, 'hex' );
		return new static( $keypair, $curve );
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
	public static function from_private( string $key ): static {
		$decoded = Multibase::decode( $key );

		$curve = match ( substr( $decoded, 0, 2 ) ) {
			Key::PREFIX_P256_PRIV => Key::CURVE_P256,
			Key::PREFIX_K256_PRIV => Key::CURVE_K256,
			// Legacy support: public key prefix used for private keys.
			Key::PREFIX_P256_PUB => Key::CURVE_P256,
			Key::PREFIX_K256_PUB => Key::CURVE_K256,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$ec = new EC( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair  = $ec->keyFromPrivate( $stripped, 'hex' );
		return new static( $keypair, $curve );
	}

	/**
	 * Convert key data to an array.
	 *
	 * @param bool $include_private Whether to include the private key.
	 * @return array<string, mixed> The key data array.
	 */
	public function to_array( bool $include_private = false ): array {
		return $this->get_exporter()->to_array( $include_private );
	}

	/**
	 * Convert key data to a JSON string.
	 *
	 * @param bool $include_private Whether to include the private key.
	 * @param int  $flags           JSON encoding flags.
	 * @return string The JSON string.
	 */
	public function to_json( bool $include_private = false, int $flags = JSON_PRETTY_PRINT ): string {
		return $this->get_exporter()->to_json( $include_private, $flags );
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
		string $format = 'json'
	): bool|string {
		return $this->get_exporter()->export( $file_path, $include_private, $format );
	}

	/**
	 * Get a KeyExporter instance for this key.
	 *
	 * @return KeyExporter The exporter instance.
	 */
	public function get_exporter(): KeyExporter {
		return KeyExporter::for_ec( $this );
	}
}
