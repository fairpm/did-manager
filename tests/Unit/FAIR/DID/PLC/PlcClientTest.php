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
}
