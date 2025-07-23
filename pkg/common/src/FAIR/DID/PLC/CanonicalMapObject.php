<?php

declare(strict_types=1);

namespace FAIR\DID\PLC;

use ArrayAccess;
use ArrayIterator;
use CBOR\AbstractCBORObject;
use CBOR\CBORObject;
use CBOR\LengthCalculator;
use CBOR\MapItem;
use CBOR\Normalizable;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;

/**
 * MapObject, implementing the canonicalization algorithm.
 *
 * This implements section 3.9 canonicalization, i.e. sort keys by length, then by lower (byte) value.
 * This canonicalization is done only when serialized, i.e. in __toString().
 * If you need the keys put in canonical order before then, call the ->canonicalize() method manually.
 *
 * @internal Unfortunately, MapObject is Final, so we need to duplicate the code.
 *
 * @phpstan-implements ArrayAccess<int, CBORObject>
 * @phpstan-implements IteratorAggregate<int, MapItem>
 */
class CanonicalMapObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    private const int MAJOR_TYPE = self::MAJOR_TYPE_MAP;

    /** @var array<string, MapItem> */
    private array $data;

    private ?string $length = null;

    /** @param array<string, MapItem> $data */
    public function __construct(array $data = [])
    {
        foreach ($data as $item) {
            if (! $item instanceof MapItem) {
                throw new InvalidArgumentException('The list must contain only MapItem objects.');
            }
        }

        [$additionalInformation, $length] = LengthCalculator::getLengthOfArray($data);
        parent::__construct(self::MAJOR_TYPE, $additionalInformation);

        $this->data = $data;
        $this->length = $length;
    }

    // This whole class exists to add this one method and call it in __toString().  `final` sucks.
    public function canonicalize(): void {
        uksort($this->data, fn($a, $b) => strlen($a) === strlen($b) ? strcmp($a, $b) : strlen($a) <=> strlen($b));

        // I bet this is faster, but it's not tested against the spec or reference above, so it's not enabled yet
        // ksort($this->data, SORT_STRING);
        // uksort($this->data, fn($a, $b) => strlen($a) <=> strlen($b));
    }

    public function __toString(): string
    {
        $this->canonicalize();

        $result = parent::__toString();

        if ($this->length !== null) {
            $result .= $this->length;
        }

        foreach ($this->data as $object) {
            $result .= $object->getKey()->__toString();
            $result .= $object->getValue()->__toString();
        }

        return $result;
    }

    /** @param array<string, MapItem> $data */
    public static function create(array $data = []): self
    {
        return new self($data);
    }

    public function add(CBORObject $key, CBORObject $value): self
    {
        if (! $key instanceof Normalizable) {
            throw new InvalidArgumentException('Invalid key. Shall be normalizable');
        }
        $this->data[$key->normalize()] = MapItem::create($key, $value);
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);

        return $this;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(int|string $index): self
    {
        if (! $this->has($index)) {
            return $this;
        }
        unset($this->data[$index]);
        $this->data = array_values($this->data);
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);

        return $this;
    }

    public function get(int|string $index): CBORObject
    {
        if (! $this->has($index)) {
            throw new InvalidArgumentException('Index not found.');
        }

        return $this->data[$index]->getValue();
    }

    public function set(MapItem $object): self
    {
        $key = $object->getKey();
        if (! $key instanceof Normalizable) {
            throw new InvalidArgumentException('Invalid key. Shall be normalizable');
        }

        $this->data[$key->normalize()] = $object;
        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);

        return $this;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /** @return Iterator<int, MapItem> */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->data);
    }

    /** @return array<array-key, mixed> */
    public function normalize(): array
    {
        return array_reduce($this->data, static function (array $carry, MapItem $item): array {
            $key = $item->getKey();
            if (! $key instanceof Normalizable) {
                throw new InvalidArgumentException('Invalid key. Shall be normalizable');
            }
            $valueObject = $item->getValue();
            $carry[$key->normalize()] = $valueObject instanceof Normalizable ? $valueObject->normalize() : $valueObject;

            return $carry;
        }, []);
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): CBORObject
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        if (! $offset instanceof CBORObject) {
            throw new InvalidArgumentException('Invalid key');
        }
        if (! $value instanceof CBORObject) {
            throw new InvalidArgumentException('Invalid value');
        }

        $this->set(MapItem::create($offset, $value));
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

}
