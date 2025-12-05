<?php
/**
 * PLC Operation class for DID operations.
 *
 * @package FairDidManager\Plc
 */

declare(strict_types=1);

namespace FairDidManager\Plc;

use CBOR\ListObject;
use CBOR\MapItem;
use CBOR\MapObject;
use CBOR\OtherObject\NullObject;
use CBOR\TextStringObject;
use Exception;
use FairDidManager\Keys\Key;
use FairDidManager\Keys\KeyFactory;
use JsonSerializable;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * PLC Operation class.
 *
 * Represents a PLC operation for creating or updating DIDs.
 *
 * @package FairDidManager\Plc
 */
class PlcOperation implements JsonSerializable {

	/**
	 * Operation type.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Rotation keys.
	 *
	 * @var Key[]
	 */
	public array $rotation_keys;

	/**
	 * Verification methods.
	 *
	 * @var array<string, Key>
	 */
	public array $verification_methods;

	/**
	 * Also known as (handles/aliases).
	 *
	 * @var string[]
	 */
	public array $also_known_as;

	/**
	 * Services.
	 *
	 * @var array<string, array>
	 */
	public array $services;

	/**
	 * Previous operation CID.
	 *
	 * @var string|null
	 */
	public ?string $prev;

	/**
	 * Signature (only set on signed operations).
	 *
	 * @var string|null
	 */
	public ?string $sig = null;

	/**
	 * Constructor.
	 *
	 * @param string               $type                 Operation type (plc_operation or plc_tombstone).
	 * @param Key[]                $rotation_keys        Rotation keys.
	 * @param array<string, Key>   $verification_methods Verification keys.
	 * @param string[]             $also_known_as        Also known as (handles).
	 * @param array<string, array> $services             Services.
	 * @param string|null          $prev                 Previous operation CID.
	 */
	public function __construct(
		string $type,
		array $rotation_keys,
		array $verification_methods,
		array $also_known_as,
		array $services,
		?string $prev = null
	) {
		$this->type                 = $type;
		$this->rotation_keys        = $rotation_keys;
		$this->verification_methods = $verification_methods;
		$this->also_known_as        = $also_known_as;
		$this->services             = $services;
		$this->prev                 = $prev;
	}

	/**
	 * Validate the operation.
	 *
	 * @return bool True if valid.
	 * @throws Exception If the operation is invalid.
	 */
	public function validate(): bool {
		if ( empty( $this->type ) ) {
			throw new Exception( 'Operation type is empty' );
		}
		if ( ! in_array( $this->type, [ 'plc_operation', 'plc_tombstone' ], true ) ) {
			throw new Exception( 'Invalid operation type' );
		}

		if ( empty( $this->rotation_keys ) ) {
			throw new Exception( 'Rotation keys are empty' );
		}
		foreach ( $this->rotation_keys as $key ) {
			if ( ! $key instanceof Key ) {
				throw new Exception( 'Rotation key is not a Key object' );
			}
		}

		if ( empty( $this->verification_methods ) ) {
			throw new Exception( 'Verification methods are empty' );
		}
		foreach ( $this->verification_methods as $id => $key ) {
			if ( ! $key instanceof Key ) {
				throw new Exception( 'Verification method is not a Key object' );
			}
		}

		return true;
	}

	/**
	 * Sign the operation.
	 *
	 * @param Key $rotation_key Rotation key for signing.
	 * @return static The signed operation.
	 */
	public function sign( Key $rotation_key ): static {
		// Validate the operation.
		$this->validate();

		// Encode the operation into DAG-CBOR.
		$encoded = $this->encode_cbor();

		// Sign the hash of the data.
		$signature = $rotation_key->sign( hash( 'sha256', $encoded, false ) );

		// Convert to base64url.
		$sig_string = self::base64url_encode( hex2bin( $signature ) );

		// Clone and add signature.
		$signed      = clone $this;
		$signed->sig = $sig_string;

		return $signed;
	}

	/**
	 * Create a canonical map from items.
	 *
	 * @param MapItem[] $items Items to add.
	 * @return MapObject Sorted map object.
	 */
	private function create_sorted_map( array $items = [] ): MapObject {
		$sorted = [];
		foreach ( $items as $item ) {
			$key                   = $item->getKey();
			$key_string            = $key instanceof TextStringObject ? $key->getValue() : (string) $key;
			$sorted[ $key_string ] = $item;
		}
		ksort( $sorted, SORT_STRING );

		$map = MapObject::create();
		foreach ( $sorted as $item ) {
			$map->add( $item->getKey(), $item->getValue() );
		}
		return $map;
	}

	/**
	 * Encode to DAG-CBOR format.
	 *
	 * @return string The CBOR-encoded operation.
	 */
	public function encode_cbor(): string {
		// Build verification methods map.
		$verification_items = [];
		foreach ( $this->verification_methods as $id => $value ) {
			$verification_items[] = MapItem::create(
				TextStringObject::create( $id ),
				TextStringObject::create( KeyFactory::encode_did_key( $value ) )
			);
		}
		$verification_map = $this->create_sorted_map( $verification_items );

		// Build services map.
		$services_items = [];
		foreach ( $this->services as $key => $service ) {
			$service_map      = $this->create_sorted_map(
				[
					MapItem::create(
						TextStringObject::create( 'endpoint' ),
						TextStringObject::create( $service['endpoint'] )
					),
					MapItem::create(
						TextStringObject::create( 'type' ),
						TextStringObject::create( $service['type'] )
					),
				]
			);
			$services_items[] = MapItem::create(
				TextStringObject::create( $key ),
				$service_map
			);
		}
		$services_map = $this->create_sorted_map( $services_items );

		// Build the main operation map.
		$operation_items = [
			MapItem::create(
				TextStringObject::create( 'alsoKnownAs' ),
				ListObject::create(
					array_map(
						fn( $key ) => TextStringObject::create( $key ),
						$this->also_known_as
					)
				)
			),
			MapItem::create(
				TextStringObject::create( 'prev' ),
				! empty( $this->prev ) ? TextStringObject::create( $this->prev ) : NullObject::create()
			),
			MapItem::create(
				TextStringObject::create( 'rotationKeys' ),
				ListObject::create(
					array_map(
						fn( Key $key ) => TextStringObject::create( KeyFactory::encode_did_key( $key ) ),
						$this->rotation_keys
					)
				)
			),
			MapItem::create(
				TextStringObject::create( 'services' ),
				$services_map
			),
			MapItem::create(
				TextStringObject::create( 'type' ),
				TextStringObject::create( $this->type )
			),
			MapItem::create(
				TextStringObject::create( 'verificationMethods' ),
				$verification_map
			),
		];

		if ( null !== $this->sig ) {
			$operation_items[] = MapItem::create(
				TextStringObject::create( 'sig' ),
				TextStringObject::create( $this->sig )
			);
		}

		$operation = $this->create_sorted_map( $operation_items );

		return (string) $operation;
	}

	/**
	 * Generate a CID for this signed operation.
	 *
	 * CIDs are used for referencing prior operations, which are always signed.
	 *
	 * Per the PLC spec, we encode with the following parameters:
	 * - CIDv1 (code: 0x01)
	 * - dag-cbor multibase type (code: 0x71)
	 * - sha-256 multihash (code: 0x12)
	 *
	 * @see https://web.plc.directory/spec/v0.1/did-plc
	 * @see https://cid.ipfs.tech/
	 * @return string The CID for the operation.
	 */
	public function get_cid(): string {
		$cbor = $this->encode_cbor();
		$hash = hash( 'sha256', $cbor, true );

		// The bit layout for CIDs is:
		// Version (CIDv1 = 0x01).
		$cid = "\x01";

		// Type (dag-cbor = 0x71).
		$cid .= "\x71";

		// Multihash type (sha-256 = 0x12).
		$cid .= "\x12";

		// Multihash length.
		$cid .= pack( 'C', strlen( $hash ) );

		// Hash digest.
		$cid .= $hash;

		// Then, encode to base32 multibase.
		return Multibase::encode( Multibase::BASE32, $cid );
	}

	/**
	 * Generate the PLC DID from a genesis operation.
	 *
	 * @return string The DID (did:plc:...).
	 */
	public function generate_did(): string {
		$encoded   = $this->encode_cbor();
		$hash      = hash( 'sha256', $encoded, true );
		$encoded32 = self::base32_encode( $hash );
		return 'did:plc:' . substr( $encoded32, 0, 24 );
	}

	/**
	 * Return data that should be serialized to JSON.
	 *
	 * @return array The JSON-serializable data.
	 */
	public function jsonSerialize(): array {
		$methods = [];
		foreach ( $this->verification_methods as $id => $keypair ) {
			$methods[ $id ] = KeyFactory::encode_did_key( $keypair );
		}

		$data = [
			'type'                => $this->type,
			'rotationKeys'        => array_map(
				fn( Key $key ) => KeyFactory::encode_did_key( $key ),
				$this->rotation_keys
			),
			'verificationMethods' => $methods,
			'alsoKnownAs'         => $this->also_known_as,
			'services'            => (object) $this->services,
			'prev'                => $this->prev,
		];

		if ( null !== $this->sig ) {
			$data['sig'] = $this->sig;
		}

		return $data;
	}

	/**
	 * Encode a binary string into a base64url string.
	 *
	 * @param string $data The binary string to encode.
	 * @return string The base64url encoded string.
	 */
	public static function base64url_encode( string $data ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode a base64url string into a binary string.
	 *
	 * @param string $data The base64url string to decode.
	 * @return string The decoded binary string.
	 */
	public static function base64url_decode( string $data ): string {
		$translated = strtr( $data, '-_', '+/' );
		$padded     = str_pad( $translated, strlen( $data ) % 4, '=', STR_PAD_RIGHT );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $padded );
	}

	/**
	 * Encode a binary string into a base32 string.
	 *
	 * @param string $data The data to encode.
	 * @return string The encoded string.
	 */
	public static function base32_encode( string $data ): string {
		$chars          = 'abcdefghijklmnopqrstuvwxyz234567';
		$data_size      = strlen( $data );
		$res            = '';
		$remainder      = 0;
		$remainder_size = 0;

		for ( $i = 0; $i < $data_size; $i++ ) {
			$b               = ord( $data[ $i ] );
			$remainder       = ( $remainder << 8 ) | $b;
			$remainder_size += 8;
			while ( $remainder_size > 4 ) {
				$remainder_size -= 5;
				$c               = $remainder & ( 31 << $remainder_size );
				$c             >>= $remainder_size;
				$res            .= $chars[ $c ];
			}
		}
		if ( $remainder_size > 0 ) {
			$remainder <<= ( 5 - $remainder_size );
			$c           = $remainder & 31;
			$res        .= $chars[ $c ];
		}
		return $res;
	}
}
