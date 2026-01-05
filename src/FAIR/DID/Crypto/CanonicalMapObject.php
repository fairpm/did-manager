<?php

/**
 * Canonical map object for CBOR encoding.
 *
 * @package FairDidManager\Crypto
 */

declare(strict_types=1);

namespace FAIR\DID\Crypto;

use ArrayAccess;
use ArrayIterator;
use CBOR\AbstractCBORObject;
use CBOR\CBORObject;
use CBOR\Countable;
use CBOR\LengthCalculator;
use CBOR\MapItem;
use CBOR\Normalizable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * MapObject implementing the canonicalization algorithm.
 *
 * This implements section 3.9 canonicalization of RFC 8949:
 * Sort keys by length first, then by byte value.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8949#section-4.2
 */
class CanonicalMapObject extends AbstractCBORObject implements \Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    private const MAJOR_TYPE = self::MAJOR_TYPE_MAP;

    /**
     * The map data.
     *
     * @var MapItem[]
     */
    private array $data;

    /**
     * The data's length.
     *
     * @var string|null
     */
    private ?string $length = null;

    /**
     * Constructor.
     *
     * @param MapItem[] $data The data for the map.
     */
    public function __construct(array $data = [])
    {
        [$additional_information, $length] = LengthCalculator::getLengthOfArray($data);
        array_map(static function ($item): void {
            if (!$item instanceof MapItem) {
                throw new InvalidArgumentException('The list must contain only MapItem objects.');
            }
        }, $data);

        parent::__construct(self::MAJOR_TYPE, $additional_information);
        $this->data = $data;
        $this->length = $length;
    }

    /**
     * Return a string representation of the object.
     *
     * Implements canonical sorting: by length first, then by byte value.
     *
     * @return string
     */
    public function __toString(): string
    {
        // Get numeric array of items for sorting
        $items = array_values($this->data);
        
        usort($items, function ($a, $b) {
            // Get the CBOR-encoded representation of the keys for comparison.
            $a_key = (string) $a->getKey();
            $b_key = (string) $b->getKey();
            
            // Sort by length first.
            $length_compare = strlen($a_key) <=> strlen($b_key);
            if ($length_compare !== 0) {
                return $length_compare;
            }
            
            // Then by byte value.
            return strcmp($a_key, $b_key);
        });

        $result = parent::__toString();
        if ($this->length !== null) {
            $result .= $this->length;
        }
        foreach ($items as $object) {
            $result .= $object->getKey()->__toString();
            $result .= $object->getValue()->__toString();
        }

        return $result;
    }

    /**
     * Create a new canonical map.
     *
     * @param MapItem[] $data Initial data.
     * @return static
     */
    public static function create(array $data = []): static
    {
        return new static($data);
    }

    /**
     * Add an item to the map.
     *
     * @param CBORObject $key The key.
     * @param CBORObject $value The value.
     * @return static
     */
    public function add(CBORObject $key, CBORObject $value): static
    {
        if (!$key instanceof Normalizable) {
            throw new InvalidArgumentException('Invalid key. Shall be normalizable');
        }
        
        // Store by normalized key to allow overwriting
        $normalizedKey = $key->normalize();
        $this->data[$normalizedKey] = MapItem::create($key, $value);
        
        // Recalculate length - need to get numeric array for count
        $items = array_values($this->data);
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($items);

        return $this;
    }

    /**
     * Get the data.
     *
     * @return MapItem[]
     */
    public function getData(): array
    {
        return array_values($this->data);
    }

    /**
     * Get the map value.
     *
     * @return array
     */
    public function getValue(): array
    {
        return array_values($this->data);
    }

    /**
     * Normalize to native PHP values.
     *
     * @return array
     */
    public function normalize(): array
    {
        $result = [];
        foreach ($this->data as $item) {
            if (!$item instanceof MapItem) {
                continue;
            }
            
            $key = $item->getKey();
            $value = $item->getValue();
            
            if ($key instanceof Normalizable) {
                $key = $key->normalize();
            }
            if ($value instanceof Normalizable) {
                $value = $value->normalize();
            }
            
            $result[$key] = $value;
        }
        
        return $result;
    }

    // ArrayAccess implementation.
    
    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof MapItem) {
            throw new InvalidArgumentException('Value must be a MapItem');
        }
        
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
        
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
        $this->data = array_values($this->data);
        
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);
    }

    // Countable implementation.
    
    public function count(): int
    {
        return count($this->data);
    }

    // IteratorAggregate implementation.
    
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->data);
    }
}
