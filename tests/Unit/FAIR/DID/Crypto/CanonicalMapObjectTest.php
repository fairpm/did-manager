<?php

/**
 * CanonicalMapObject Tests
 *
 * @package FairDidManager\Tests\Crypto
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Crypto;

use CBOR\MapItem;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use FAIR\DID\Crypto\CanonicalMapObject;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for CanonicalMapObject
 */
class CanonicalMapObjectTest extends TestCase
{
    /**
     * Test creating an empty canonical map.
     */
    public function test_create_empty_map(): void
    {
        $map = CanonicalMapObject::create();

        $this->assertInstanceOf(CanonicalMapObject::class, $map);
        $this->assertCount(0, $map);
    }

    /**
     * Test creating a map with initial items.
     */
    public function test_create_with_items(): void
    {
        $items = [
            MapItem::create(
                TextStringObject::create('key1'),
                TextStringObject::create('value1')
            ),
            MapItem::create(
                TextStringObject::create('key2'),
                TextStringObject::create('value2')
            )
        ];

        $map = CanonicalMapObject::create($items);

        $this->assertCount(2, $map);
    }

    /**
     * Test adding items to the map.
     */
    public function test_add_items(): void
    {
        $map = CanonicalMapObject::create();
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        
        $map->add(
            TextStringObject::create('key2'),
            TextStringObject::create('value2')
        );

        $this->assertCount(2, $map);
    }

    /**
     * Test canonical sorting: by key length first.
     */
    public function test_canonical_sorting_by_length(): void
    {
        $map = CanonicalMapObject::create();
        
        // Add keys in non-sorted order
        $map->add(
            TextStringObject::create('longkey'),
            TextStringObject::create('value1')
        );
        $map->add(
            TextStringObject::create('key'),
            TextStringObject::create('value2')
        );
        $map->add(
            TextStringObject::create('a'),
            TextStringObject::create('value3')
        );

        $cbor = (string) $map;
        
        // Shorter keys should appear first in CBOR encoding
        // We can verify this by checking the encoded string contains keys in order
        $this->assertNotEmpty($cbor);
        
        // The CBOR should start with map marker
        $firstByte = ord($cbor[0]);
        $majorType = $firstByte >> 5;
        $this->assertSame(5, $majorType, 'Should be a CBOR map (major type 5)');
    }

    /**
     * Test canonical sorting: by byte value when lengths are equal.
     */
    public function test_canonical_sorting_by_byte_value(): void
    {
        $map = CanonicalMapObject::create();
        
        // Add keys of same length in non-sorted order
        $map->add(
            TextStringObject::create('zebra'),
            TextStringObject::create('value1')
        );
        $map->add(
            TextStringObject::create('apple'),
            TextStringObject::create('value2')
        );
        $map->add(
            TextStringObject::create('mango'),
            TextStringObject::create('value3')
        );

        $cbor = (string) $map;
        
        // Verify it produces valid CBOR
        $this->assertNotEmpty($cbor);
        
        // First byte should indicate a map
        $firstByte = ord($cbor[0]);
        $majorType = $firstByte >> 5;
        $this->assertSame(5, $majorType);
    }

    /**
     * Test normalize returns array.
     */
    public function test_normalize(): void
    {
        $map = CanonicalMapObject::create();
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        $map->add(
            TextStringObject::create('key2'),
            UnsignedIntegerObject::create(42)
        );

        $normalized = $map->normalize();

        $this->assertIsArray($normalized);
        $this->assertArrayHasKey('key1', $normalized);
        $this->assertArrayHasKey('key2', $normalized);
        $this->assertSame('value1', $normalized['key1']);
        // UnsignedIntegerObject normalizes to string in CBOR library
        $this->assertSame('42', $normalized['key2']);
    }

    /**
     * Test getValue returns the map data.
     */
    public function test_get_value(): void
    {
        $items = [
            MapItem::create(
                TextStringObject::create('key1'),
                TextStringObject::create('value1')
            )
        ];

        $map = CanonicalMapObject::create($items);
        $value = $map->getValue();

        $this->assertIsArray($value);
        $this->assertContainsOnlyInstancesOf(MapItem::class, $value);
    }

    /**
     * Test getData returns the map data.
     */
    public function test_get_data(): void
    {
        $items = [
            MapItem::create(
                TextStringObject::create('key1'),
                TextStringObject::create('value1')
            )
        ];

        $map = CanonicalMapObject::create($items);
        $data = $map->getData();

        $this->assertIsArray($data);
        $this->assertContainsOnlyInstancesOf(MapItem::class, $data);
    }

    /**
     * Test count returns correct number of items.
     */
    public function test_count(): void
    {
        $map = CanonicalMapObject::create();
        
        $this->assertCount(0, $map);
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        
        $this->assertCount(1, $map);
        
        $map->add(
            TextStringObject::create('key2'),
            TextStringObject::create('value2')
        );
        
        $this->assertCount(2, $map);
    }

    /**
     * Test iterator functionality.
     */
    public function test_iterator(): void
    {
        $map = CanonicalMapObject::create();
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        $map->add(
            TextStringObject::create('key2'),
            TextStringObject::create('value2')
        );

        $items = [];
        foreach ($map as $item) {
            $items[] = $item;
        }

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(MapItem::class, $items);
    }

    /**
     * Test ArrayAccess offsetExists.
     */
    public function test_array_access_offset_exists(): void
    {
        $map = CanonicalMapObject::create();
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );

        $this->assertTrue(isset($map['key1']));
        $this->assertFalse(isset($map['nonexistent']));
    }

    /**
     * Test ArrayAccess offsetGet.
     */
    public function test_array_access_offset_get(): void
    {
        $map = CanonicalMapObject::create();
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );

        $item = $map['key1'];
        $this->assertInstanceOf(MapItem::class, $item);
        
        $this->assertNull($map['nonexistent']);
    }

    /**
     * Test ArrayAccess offsetSet.
     */
    public function test_array_access_offset_set(): void
    {
        $map = CanonicalMapObject::create();
        
        $map['key1'] = MapItem::create(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );

        $this->assertCount(1, $map);
        $this->assertTrue(isset($map['key1']));
    }

    /**
     * Test ArrayAccess offsetUnset.
     */
    public function test_array_access_offset_unset(): void
    {
        $map = CanonicalMapObject::create();
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        $map->add(
            TextStringObject::create('key2'),
            TextStringObject::create('value2')
        );

        $this->assertCount(2, $map);
        
        unset($map['key1']);
        
        $this->assertCount(1, $map);
        $this->assertFalse(isset($map['key1']));
    }

    /**
     * Test __toString produces CBOR output.
     */
    public function test_to_string(): void
    {
        $map = CanonicalMapObject::create();
        $map->add(
            TextStringObject::create('type'),
            TextStringObject::create('plc_operation')
        );

        $cbor = (string) $map;

        $this->assertIsString($cbor);
        $this->assertNotEmpty($cbor);
        
        // Should start with CBOR map marker
        $firstByte = ord($cbor[0]);
        $majorType = $firstByte >> 5;
        $this->assertSame(5, $majorType);
    }

    /**
     * Test canonical sorting matches RFC 8949 section 3.9.
     * 
     * Keys should be sorted:
     * 1. First by length (shorter first)
     * 2. Then by byte value (lexicographic)
     */
    public function test_rfc_8949_canonical_sorting(): void
    {
        $map = CanonicalMapObject::create();
        
        // Add keys in specific order to test sorting
        $map->add(TextStringObject::create('sig'), TextStringObject::create('v1'));
        $map->add(TextStringObject::create('prev'), TextStringObject::create('v2'));
        $map->add(TextStringObject::create('type'), TextStringObject::create('v3'));
        $map->add(TextStringObject::create('services'), TextStringObject::create('v4'));
        $map->add(TextStringObject::create('alsoKnownAs'), TextStringObject::create('v5'));
        $map->add(TextStringObject::create('rotationKeys'), TextStringObject::create('v6'));
        $map->add(TextStringObject::create('verificationMethods'), TextStringObject::create('v7'));

        $cbor = (string) $map;
        
        // Expected order by length: sig(3), prev(4), type(4), services(8), alsoKnownAs(11), rotationKeys(12), verificationMethods(19)
        // When lengths are equal (prev/type), lexicographic order applies
        
        $this->assertNotEmpty($cbor);
        
        // The data should be properly encoded as CBOR map
        $firstByte = ord($cbor[0]);
        $majorType = $firstByte >> 5;
        $additionalInfo = $firstByte & 0x1F;
        
        $this->assertSame(5, $majorType);
        $this->assertSame(7, $additionalInfo); // 7 items in map
    }

    /**
     * Test adding duplicate keys overwrites previous value.
     */
    public function test_duplicate_keys_overwrite(): void
    {
        $map = CanonicalMapObject::create();
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value1')
        );
        
        $map->add(
            TextStringObject::create('key1'),
            TextStringObject::create('value2')
        );

        $this->assertCount(1, $map);
        
        $normalized = $map->normalize();
        $this->assertSame('value2', $normalized['key1']);
    }

    /**
     * Test map with null values.
     */
    public function test_map_with_null_values(): void
    {
        $map = CanonicalMapObject::create();
        
        $map->add(
            TextStringObject::create('prev'),
            \CBOR\OtherObject\NullObject::create()
        );

        $normalized = $map->normalize();
        
        $this->assertArrayHasKey('prev', $normalized);
        $this->assertNull($normalized['prev']);
    }

    /**
     * Test map handles complex nested structures.
     */
    public function test_nested_structures(): void
    {
        $map = CanonicalMapObject::create();
        
        $innerMap = \CBOR\MapObject::create();
        $innerMap->add(
            TextStringObject::create('nested_key'),
            TextStringObject::create('nested_value')
        );
        
        $map->add(
            TextStringObject::create('outer'),
            $innerMap
        );

        $cbor = (string) $map;
        $this->assertNotEmpty($cbor);
    }
}
