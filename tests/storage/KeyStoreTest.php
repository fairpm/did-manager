<?php
/**
 * KeyStore Tests
 *
 * @package FairDidManager\Tests\Storage
 */

declare(strict_types=1);

namespace FairDidManager\Tests\Storage;

use PHPUnit\Framework\TestCase;
use FairDidManager\Storage\KeyStore;

/**
 * Test cases for KeyStore
 */
class KeyStoreTest extends TestCase {

	/**
	 * Path to test store.
	 *
	 * @var string
	 */
	private string $test_store_path;

	protected function setUp(): void {
		$this->test_store_path = sys_get_temp_dir() . '/did-manager-test-' . uniqid() . '.json';
	}

	protected function tearDown(): void {
		if ( file_exists( $this->test_store_path ) ) {
			unlink( $this->test_store_path );
		}
	}

	/**
	 * Test store creates file if not exists
	 */
	public function testStoreCreatesFileIfNotExists(): void {
		$store = new KeyStore( $this->test_store_path );
		$this->assertFileDoesNotExist( $this->test_store_path );

		$store->store_did(
			'did:plc:test123',
			'rotation-private-key',
			'zRotationPublic',
			'verification-private-key',
			'zVerificationPublic'
		);

		$this->assertFileExists( $this->test_store_path );
	}

	/**
	 * Test store DID and retrieve
	 */
	public function testStoreDidAndRetrieve(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test123';

		$store->store_did(
			$did,
			'rotation-private-key',
			'zRotationPublic',
			'verification-private-key',
			'zVerificationPublic',
			'plugin',
			[ 'name' => 'Test Plugin' ]
		);

		$entry = $store->get_did( $did );

		$this->assertNotNull( $entry );
		$this->assertSame( $did, $entry['did'] );
		$this->assertSame( 'rotation-private-key', $entry['rotationKey']['private'] );
		$this->assertSame( 'zRotationPublic', $entry['rotationKey']['public'] );
		$this->assertSame( 'plugin', $entry['type'] );
		$this->assertTrue( $entry['active'] );
	}

	/**
	 * Test get non-existent DID returns null
	 */
	public function testGetNonExistentDidReturnsNull(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->assertNull( $store->get_did( 'did:plc:nonexistent' ) );
	}

	/**
	 * Test get rotation key
	 */
	public function testGetRotationKey(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test123';

		$store->store_did(
			$did,
			'rotation-private-key',
			'zRotationPublic',
			'verification-private-key',
			'zVerificationPublic'
		);

		$this->assertSame( 'rotation-private-key', $store->get_rotation_key( $did ) );
	}

	/**
	 * Test get verification key
	 */
	public function testget_verification_key(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test123';

		$store->store_did(
			$did,
			'rotation-private-key',
			'zRotationPublic',
			'verification-private-key',
			'zVerificationPublic'
		);

		$this->assertSame( 'verification-private-key', $store->get_verification_key( $did ) );
	}

	/**
	 * Test update keys
	 */
	public function testUpdateKeys(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test123';

		$store->store_did(
			$did,
			'old-rotation-key',
			'zOldRotationPublic',
			'old-verification-key',
			'zOldVerificationPublic'
		);

		$store->update_keys(
			$did,
			'new-rotation-key',
			'zNewRotationPublic',
			'new-verification-key',
			'zNewVerificationPublic'
		);

		$this->assertSame( 'new-rotation-key', $store->get_rotation_key( $did ) );
		$this->assertSame( 'new-verification-key', $store->get_verification_key( $did ) );
	}

	/**
	 * Test update keys throws for non-existent DID
	 */
	public function testUpdateKeysThrowsForNonExistentDid(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->expectException( \RuntimeException::class );
		$store->update_keys(
			'did:plc:nonexistent',
			'new-rotation',
			'zNew',
			'new-verification',
			'zNew'
		);
	}

	/**
	 * Test deactivate DID
	 */
	public function testDeactivateDid(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test123';

		$store->store_did(
			$did,
			'rotation-key',
			'zRotation',
			'verification-key',
			'zVerification'
		);

		$this->assertTrue( $store->is_active( $did ) );

		$store->deactivate( $did );

		$this->assertFalse( $store->is_active( $did ) );

		$entry = $store->get_did( $did );
		$this->assertArrayHasKey( 'deactivatedAt', $entry );
	}

	/**
	 * Test deactivate throws for non-existent DID
	 */
	public function testDeactivateThrowsForNonExistentDid(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->expectException( \RuntimeException::class );
		$store->deactivate( 'did:plc:nonexistent' );
	}

	/**
	 * Test list DIDs
	 */
	public function testListDids(): void {
		$store = new KeyStore( $this->test_store_path );

		$store->store_did( 'did:plc:one', 'key1', 'pub1', 'ver1', 'vpub1', 'plugin' );
		$store->store_did( 'did:plc:two', 'key2', 'pub2', 'ver2', 'vpub2', 'theme' );

		$list = $store->list_dids();

		$this->assertCount( 2, $list );

		$dids = array_column( $list, 'did' );
		$this->assertContains( 'did:plc:one', $dids );
		$this->assertContains( 'did:plc:two', $dids );
	}

	/**
	 * Test list DIDs excludes private keys
	 */
	public function testListDidsExcludesPrivateKeys(): void {
		$store = new KeyStore( $this->test_store_path );

		$store->store_did( 'did:plc:test', 'private-key', 'pub', 'ver', 'vpub' );

		$list = $store->list_dids();

		$this->assertArrayNotHasKey( 'rotationKey', $list[0] );
		$this->assertArrayNotHasKey( 'verificationKey', $list[0] );
	}

	/**
	 * Test exists
	 */
	public function testExists(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->assertFalse( $store->exists( 'did:plc:test' ) );

		$store->store_did( 'did:plc:test', 'key', 'pub', 'ver', 'vpub' );

		$this->assertTrue( $store->exists( 'did:plc:test' ) );
	}

	/**
	 * Test is active
	 */
	public function testIsActive(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->assertFalse( $store->is_active( 'did:plc:nonexistent' ) );

		$store->store_did( 'did:plc:test', 'key', 'pub', 'ver', 'vpub' );
		$this->assertTrue( $store->is_active( 'did:plc:test' ) );
	}

	/**
	 * Test update metadata
	 */
	public function testUpdateMetadata(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test';

		$store->store_did( $did, 'key', 'pub', 'ver', 'vpub', null, [ 'name' => 'Test' ] );

		$store->update_metadata( $did, [ 'version' => '1.0.0' ] );

		$entry = $store->get_did( $did );
		$this->assertSame( 'Test', $entry['metadata']['name'] );
		$this->assertSame( '1.0.0', $entry['metadata']['version'] );
	}

	/**
	 * Test update metadata throws for non-existent DID
	 */
	public function testUpdateMetadataThrowsForNonExistentDid(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->expectException( \RuntimeException::class );
		$store->update_metadata( 'did:plc:nonexistent', [ 'key' => 'value' ] );
	}

	/**
	 * Test delete DID
	 */
	public function testDeleteDid(): void {
		$store = new KeyStore( $this->test_store_path );
		$did   = 'did:plc:test';

		$store->store_did( $did, 'key', 'pub', 'ver', 'vpub' );
		$this->assertTrue( $store->exists( $did ) );

		$result = $store->delete( $did );

		$this->assertTrue( $result );
		$this->assertFalse( $store->exists( $did ) );
	}

	/**
	 * Test delete non-existent DID returns false
	 */
	public function testDeleteNonExistentDidReturnsFalse(): void {
		$store = new KeyStore( $this->test_store_path );

		$this->assertFalse( $store->delete( 'did:plc:nonexistent' ) );
	}

	/**
	 * Test data persists across instances
	 */
	public function testDataPersistsAcrossInstances(): void {
		$store1 = new KeyStore( $this->test_store_path );
		$store1->store_did( 'did:plc:test', 'key', 'pub', 'ver', 'vpub', 'plugin' );

		$store2 = new KeyStore( $this->test_store_path );

		$this->assertTrue( $store2->exists( 'did:plc:test' ) );
		$entry = $store2->get_did( 'did:plc:test' );
		$this->assertSame( 'plugin', $entry['type'] );
	}
}
