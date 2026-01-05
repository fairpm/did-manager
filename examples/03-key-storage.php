<?php
/**
 * Example 3: Local Key Storage
 *
 * This example demonstrates how to use the KeyStore class to
 * store and manage DIDs and their associated keys locally.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Sorage\KeyStore;

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== FAIR CLI: Key Storage Examples ===\n\n";

// Use a temporary file for this example.
$store_path = sys_get_temp_dir() . '/did-manager-example-' . uniqid() . '.json';
echo "Using temporary store: {$store_path}\n\n";

// -----------------------------------------------------------------------------
// 1. Create a KeyStore instance
// -----------------------------------------------------------------------------
echo "1. Creating KeyStore Instance\n";
echo str_repeat( '-', 50 ) . "\n";

$store = new KeyStore( $store_path );
echo "KeyStore initialized.\n\n";

// -----------------------------------------------------------------------------
// 2. Store a DID with keys
// -----------------------------------------------------------------------------
echo "2. Storing a DID with Keys\n";
echo str_repeat( '-', 50 ) . "\n";

// Generate some keys.
$rotation_key     = DidCodec::generate_key_pair();
$verification_key = DidCodec::generate_ed25519_key_pair();

$did = 'did:plc:example123abc';

$store->store_did(
	$did,
	$rotation_key->encode_private(),
	$rotation_key->encode_public(),
	$verification_key->encode_private(),
	$verification_key->encode_public(),
	'plugin',
	[
		'name'    => 'My Awesome Plugin',
		'version' => '1.0.0',
		'handle'  => 'my-awesome-plugin',
	]
);

echo "DID stored: {$did}\n";
echo "Type: plugin\n\n";

// -----------------------------------------------------------------------------
// 3. Retrieve a DID entry
// -----------------------------------------------------------------------------
echo "3. Retrieving DID Entry\n";
echo str_repeat( '-', 50 ) . "\n";

$entry = $store->get_did( $did );

echo 'DID: ' . $entry['did'] . "\n";
echo 'Type: ' . $entry['type'] . "\n";
echo 'Active: ' . ( $entry['active'] ? 'Yes' : 'No' ) . "\n";
echo 'Created At: ' . $entry['createdAt'] . "\n";
echo 'Metadata Name: ' . $entry['metadata']['name'] . "\n";
echo 'Has Private Key: ' . ( isset( $entry['rotationKey']['private'] ) ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 4. Store another DID (theme)
// -----------------------------------------------------------------------------
echo "4. Storing Another DID (Theme)\n";
echo str_repeat( '-', 50 ) . "\n";

$theme_rotation     = DidCodec::generate_key_pair();
$theme_verification = DidCodec::generate_ed25519_key_pair();

$theme_did = 'did:plc:theme456xyz';

$store->store_did(
	$theme_did,
	$theme_rotation->encode_private(),
	$theme_rotation->encode_public(),
	$theme_verification->encode_private(),
	$theme_verification->encode_public(),
	'theme',
	[ 'name' => 'My Beautiful Theme' ]
);

echo "Theme DID stored: {$theme_did}\n\n";

// -----------------------------------------------------------------------------
// 5. List all stored DIDs
// -----------------------------------------------------------------------------
echo "5. Listing All Stored DIDs\n";
echo str_repeat( '-', 50 ) . "\n";

$all_dids = $store->list_dids();

echo 'Total DIDs: ' . count( $all_dids ) . "\n";
foreach ( $all_dids as $item ) {
	$item_type = $item['type'] ?? 'unknown';
	echo "  - {$item['did']} ({$item_type})\n";
}
echo "\n";

// -----------------------------------------------------------------------------
// 6. Check if DID exists
// -----------------------------------------------------------------------------
echo "6. Checking DID Existence\n";
echo str_repeat( '-', 50 ) . "\n";

echo "Exists '{$did}': " . ( $store->exists( $did ) ? 'Yes' : 'No' ) . "\n";
echo "Exists 'did:plc:notreal': " . ( $store->exists( 'did:plc:notreal' ) ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 7. Get specific keys
// -----------------------------------------------------------------------------
echo "7. Retrieving Specific Keys\n";
echo str_repeat( '-', 50 ) . "\n";

$rotation_private = $store->get_rotation_key( $did );
$verify_private   = $store->get_verification_key( $did );

echo 'Rotation Key (first 30 chars): ' . substr( $rotation_private, 0, 30 ) . "...\n";
echo 'Verification Key (first 30 chars): ' . substr( $verify_private, 0, 30 ) . "...\n\n";

// -----------------------------------------------------------------------------
// 8. Update metadata
// -----------------------------------------------------------------------------
echo "8. Updating Metadata\n";
echo str_repeat( '-', 50 ) . "\n";

$store->update_metadata(
	$did,
	[
		'version'     => '2.0.0',
		'lastUpdated' => gmdate( 'c' ),
	]
);

$updated_entry = $store->get_did( $did );
echo 'Updated Version: ' . $updated_entry['metadata']['version'] . "\n";
echo 'Last Updated: ' . $updated_entry['metadata']['lastUpdated'] . "\n\n";

// -----------------------------------------------------------------------------
// 9. Update keys (key rotation)
// -----------------------------------------------------------------------------
echo "9. Updating Keys (Simulating Rotation)\n";
echo str_repeat( '-', 50 ) . "\n";

$new_rotation     = DidCodec::generate_key_pair();
$new_verification = DidCodec::generate_ed25519_key_pair();

$old_public = $store->get_did( $did )['rotationKey']['public'];

$store->update_keys(
	$did,
	$new_rotation->encode_private(),
	$new_rotation->encode_public(),
	$new_verification->encode_private(),
	$new_verification->encode_public()
);

$new_public = $store->get_did( $did )['rotationKey']['public'];

echo 'Old Public Key: ' . substr( $old_public, 0, 30 ) . "...\n";
echo 'New Public Key: ' . substr( $new_public, 0, 30 ) . "...\n";
echo 'Keys Changed: ' . ( $old_public !== $new_public ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 10. Deactivate a DID
// -----------------------------------------------------------------------------
echo "10. Deactivating a DID\n";
echo str_repeat( '-', 50 ) . "\n";

echo 'Is Active Before: ' . ( $store->is_active( $theme_did ) ? 'Yes' : 'No' ) . "\n";

$store->deactivate( $theme_did );

echo 'Is Active After: ' . ( $store->is_active( $theme_did ) ? 'Yes' : 'No' ) . "\n";

$deactivated_entry = $store->get_did( $theme_did );
echo 'Deactivated At: ' . $deactivated_entry['deactivatedAt'] . "\n\n";

// -----------------------------------------------------------------------------
// 11. Delete a DID
// -----------------------------------------------------------------------------
echo "11. Deleting a DID\n";
echo str_repeat( '-', 50 ) . "\n";

echo 'DIDs Before Delete: ' . count( $store->list_dids() ) . "\n";

$deleted = $store->delete( $theme_did );

echo 'Deletion Success: ' . ( $deleted ? 'Yes' : 'No' ) . "\n";
echo 'DIDs After Delete: ' . count( $store->list_dids() ) . "\n\n";

// Clean up.
unlink( $store_path );
echo "Temporary store cleaned up.\n\n";

echo "=== Example Complete ===\n";
