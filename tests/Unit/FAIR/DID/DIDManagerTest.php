<?php
/**
 * DIDManager Tests
 *
 * @package DIDManager\Tests\Did
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID;

use FAIR\DID\DIDManager;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\Sorage\KeyStore;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for DIDManager
 */
class DIDManagerTest extends TestCase {

	/**
	 * Path to test store.
	 *
	 * @var string
	 */
	private string $test_store_path;

	/**
	 * KeyStore instance.
	 *
	 * @var KeyStore
	 */
	private KeyStore $key_store;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		$this->test_store_path = sys_get_temp_dir() . '/did-manager-test-' . uniqid() . '.json';
		$this->key_store       = new KeyStore( $this->test_store_path );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->test_store_path ) ) {
			unlink( $this->test_store_path );
		}
	}

	/**
	 * Test constructor.
	 */
	public function testConstructor(): void {
		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->assertInstanceOf( DIDManager::class, $manager );
	}

	/**
	 * Test list local DIDs when empty.
	 */
	public function testListLocalDidsWhenEmpty(): void {
		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$list = $manager->list_local_dids();

		$this->assertIsArray( $list );
		$this->assertEmpty( $list );
	}

	/**
	 * Test list local DIDs returns stored DIDs.
	 */
	public function testListLocalDidsReturnsStoredDids(): void {
		// Pre-populate the key store.
		$this->key_store->store_did(
			'did:plc:test123',
			'rotation-key',
			'zRotation',
			'verification-key',
			'zVerification',
			'plugin'
		);

		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$list = $manager->list_local_dids();

		$this->assertCount( 1, $list );
		$this->assertSame( 'did:plc:test123', $list[0]['did'] );
	}

	/**
	 * Test resolve DID throws on network error.
	 */
	public function testResolveDidThrowsOnNetworkError(): void {
		$plc_client = new PlcClient( 'http://invalid.local.test', 1 );
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->expectException( \RuntimeException::class );
		$manager->resolve_did( 'did:plc:test123' );
	}

	/**
	 * Test create DID throws on network error.
	 */
	public function testCreateDidThrowsOnNetworkError(): void {
		$plc_client = new PlcClient( 'http://invalid.local.test', 1 );
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->expectException( \RuntimeException::class );
		$manager->create_did( 'test-handle' );
	}

	/**
	 * Test update DID throws when rotation key not found.
	 */
	public function testUpdateDidThrowsWhenRotationKeyNotFound(): void {
		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Rotation key not found' );
		$manager->update_did( 'did:plc:nonexistent', [ 'handle' => 'new-handle' ] );
	}

	/**
	 * Test rotate keys throws when rotation key not found.
	 */
	public function testRotateKeysThrowsWhenRotationKeyNotFound(): void {
		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Rotation key not found' );
		$manager->rotate_keys( 'did:plc:nonexistent' );
	}

	/**
	 * Test deactivate DID throws when rotation key not found.
	 */
	public function testDeactivateDidThrowsWhenRotationKeyNotFound(): void {
		$plc_client = new PlcClient();
		$manager    = new DIDManager( $this->key_store, $plc_client );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Rotation key not found' );
		$manager->deactivate_did( 'did:plc:nonexistent' );
	}
}
