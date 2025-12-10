<?php
/**
 * Key Factory for decoding multibase keys.
 *
 * @package FairDidManager\Keys
 */

declare(strict_types=1);

namespace FairDidManager\Keys;

use Exception;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * Key Factory class for decoding multibase keys.
 *
 * Provides factory methods to decode multibase-encoded keys into the appropriate Key implementation.
 *
 * @package FairDidManager\Keys
 */
class KeyFactory {

	/**
	 * Decode a multibase public key string to a Key object.
	 *
	 * @param string $key The multibase public key string (starts with z).
	 * @return Key The key object.
	 * @throws Exception If the curve is not supported.
	 */
	public static function decode_public_key( string $key ): Key {
		static $cache = [];
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$decoded = Multibase::decode( $key );
		$prefix  = substr( $decoded, 0, 2 );

		$keypair = match ( $prefix ) {
			Key::PREFIX_P256_PUB    => EcKey::from_public( $key ),
			Key::PREFIX_K256_PUB    => EcKey::from_public( $key ),
			Key::PREFIX_ED25519_PUB => EdDsaKey::from_public( $key ),
			default => throw new Exception( 'Unsupported curve: ' . bin2hex( $prefix ) ),
		};
		$cache[ $key ] = $keypair;
		return $keypair;
	}

	/**
	 * Decode a multibase private key string to a Key object.
	 *
	 * @param string $key The multibase private key string (starts with z).
	 * @return Key The key object.
	 * @throws Exception If the curve is not supported.
	 */
	public static function decode_private_key( string $key ): Key {
		static $cache = [];
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$decoded = Multibase::decode( $key );
		$prefix  = substr( $decoded, 0, 2 );

		$keypair = match ( $prefix ) {
			Key::PREFIX_P256_PRIV    => EcKey::from_private( $key ),
			Key::PREFIX_K256_PRIV    => EcKey::from_private( $key ),
			Key::PREFIX_ED25519_PRIV => EdDsaKey::from_private( $key ),
			// Legacy support: public key prefix used for private keys.
			Key::PREFIX_P256_PUB     => EcKey::from_private( $key ),
			Key::PREFIX_K256_PUB     => EcKey::from_private( $key ),
			Key::PREFIX_ED25519_PUB  => EdDsaKey::from_private( $key ),
			default => throw new Exception( 'Unsupported curve: ' . bin2hex( $prefix ) ),
		};
		$cache[ $key ] = $keypair;
		return $keypair;
	}

	/**
	 * Decode a did:key: string to a Key object.
	 *
	 * @param string $did The did:key: string.
	 * @return Key The key object.
	 * @throws Exception If the did:key: string is invalid.
	 */
	public static function decode_did_key( string $did ): Key {
		if ( ! str_starts_with( $did, 'did:key:' ) ) {
			throw new Exception( 'Invalid DID format' );
		}
		$key = substr( $did, 8 );
		if ( ! str_starts_with( $key, 'z' ) ) {
			throw new Exception( 'Invalid DID format' );
		}

		return self::decode_public_key( $key );
	}

	/**
	 * Encode a Key object to a did:key: string.
	 *
	 * @param Key $key The key object.
	 * @return string The did:key: string.
	 */
	public static function encode_did_key( Key $key ): string {
		return 'did:key:' . $key->encode_public();
	}
}
