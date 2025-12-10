<?php
/**
 * Canonical Map Object for CBOR encoding.
 *
 * @package FairDidManager\Crypto
 */

declare(strict_types=1);

namespace FAIR\DID\Crypto;

use CBOR\CBORObject;
use CBOR\MapItem;
use CBOR\MapObject;
use CBOR\TextStringObject;
use Stringable;

/**
 * Canonical Map Object class.
 *
 * Wraps MapObject to provide canonical (sorted) key ordering for DAG-CBOR.
 *
 * @package FairDidManager\Crypto
 */
class CanonicalMapObject implements Stringable {

	/**
	 * Internal items storage.
	 *
	 * @var array<string, MapItem>
	 */
	private array $items = [];

	/**
	 * Create a new CanonicalMapObject.
	 *
	 * @param MapItem[] $items Initial items.
	 * @return static The new object.
	 */
	public static function create( array $items = [] ): static {
		$object = new static();
		foreach ( $items as $item ) {
			$object->add( $item->getKey(), $item->getValue() );
		}
		return $object;
	}

	/**
	 * Add an item to the map.
	 *
	 * @param CBORObject $key   The key.
	 * @param CBORObject $value The value.
	 * @return static This object for chaining.
	 */
	public function add( CBORObject $key, CBORObject $value ): static {
		$key_string                 = $key instanceof TextStringObject ? $key->getValue() : (string) $key;
		$this->items[ $key_string ] = MapItem::create( $key, $value );
		return $this;
	}

	/**
	 * Normalize to a sorted MapObject.
	 *
	 * @return MapObject The normalized map.
	 */
	public function normalize(): MapObject {
		// Sort items by key (lexicographically).
		$sorted = $this->items;
		ksort( $sorted, SORT_STRING );

		$map = MapObject::create();
		foreach ( $sorted as $item ) {
			$map->add( $item->getKey(), $item->getValue() );
		}
		return $map;
	}

	/**
	 * Convert to string (CBOR bytes).
	 *
	 * @return string The CBOR-encoded bytes.
	 */
	public function __toString(): string {
		return (string) $this->normalize();
	}
}
