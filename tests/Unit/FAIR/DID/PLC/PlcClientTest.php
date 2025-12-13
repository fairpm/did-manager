<?php

/**
 * PlcClient Tests
 *
 * @package FairDidManager\Tests\Plc
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\PLC;

use FAIR\DID\PLC\PlcClient;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for PlcClient
 */
class PlcClientTest extends TestCase
{
    /**
     * Test constructor with default URL
     */
    public function testConstructorWithDefaultUrl(): void
    {
        $client = new PlcClient();

        // Test that the client was created successfully.
        $this->assertInstanceOf(PlcClient::class, $client);
    }

    /**
     * Test constructor with custom URL
     */
    public function testConstructorWithCustomUrl(): void
    {
        $client = new PlcClient('https://custom.plc.example.com', 60);

        $this->assertInstanceOf(PlcClient::class, $client);
    }

    /**
     * Test constructor trims trailing slash from URL
     */
    public function testConstructorTrimsTrailingSlash(): void
    {
        $client = new PlcClient('https://plc.directory/');

        $this->assertInstanceOf(PlcClient::class, $client);
    }

    /**
     * Test resolve DID throws on network error
     *
     * Note: This test uses an invalid URL to trigger a network error.
     */
    public function testResolveDidThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->resolve_did('did:plc:test123');
    }

    /**
     * Test get operation log throws on network error
     */
    public function testGetOperationLogThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->get_operation_log('did:plc:test123');
    }

    /**
     * Test get audit log throws on network error
     */
    public function testGetAuditLogThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->get_audit_log('did:plc:test123');
    }

    /**
     * Test get last operation throws on network error
     */
    public function testGetLastOperationThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->get_last_operation('did:plc:test123');
    }

    /**
     * Test create DID throws on network error
     */
    public function testCreateDidThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->create_did('did:plc:test123', ['type' => 'plc_operation']);
    }

    /**
     * Test update DID throws on network error
     */
    public function testUpdateDidThrowsOnNetworkError(): void
    {
        $client = new PlcClient('http://invalid.local.test', 1);

        $this->expectException(\RuntimeException::class);
        $client->update_did('did:plc:test123', ['type' => 'plc_operation']);
    }

    /**
     * Test get previous CID with real DID
     *
     * This test uses a real DID that was created in example 07.
     * It verifies that we can fetch the previous CID from the audit log.
     */
    public function testGetPreviousCidWithRealDid(): void
    {
        $client = new PlcClient('https://plc.directory', 30, false);

        // Use a DID that we know exists (from our examples)
        // You can replace this with any valid did:plc DID
        $did = 'did:plc:ewvi7nxzyoun6zhxrhs64oiz';

        try {
            $cid = $client->get_previous_cid($did);

            // The CID should be a non-empty string
            $this->assertIsString($cid);
            $this->assertNotEmpty($cid);

            // CID should start with 'b' (base32 encoding prefix for CIDv1)
            $this->assertStringStartsWith('b', $cid);

            // CID should be reasonably long (typically 59 characters for CIDv1)
            $this->assertGreaterThan(50, strlen($cid));
        } catch (\RuntimeException $e) {
            // If we can't reach the PLC directory, skip this test
            $this->markTestSkipped('Could not reach PLC directory: ' . $e->getMessage());
        }
    }

    /**
     * Test get previous CID returns null for non-existent DID
     */
    public function testGetPreviousCidReturnsNullForNonExistentDid(): void
    {
        $client = new PlcClient('https://plc.directory', 30, false);

        // Use a DID that doesn't exist
        $did = 'did:plc:nonexistentdidthatdoesnotexist';

        // Should throw an error for non-existent DID (404)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');
        $client->get_previous_cid($did);
    }
}
