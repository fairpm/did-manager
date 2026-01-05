<?php
/**
 * Example 6: Export Keys to Output or File.
 *
 * This example demonstrates how to export keys in different formats
 * and save them to files.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\Keys\Key;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

echo "=== FAIR DID Manager: Key Export Examples ===\n\n";

// Generate keys for demonstration.
$rotation_key     = EcKey::generate( Key::CURVE_K256 );
$verification_key = EdDsaKey::generate();

// 1. Export to JSON (default format) - output to console.
echo "1. Export EC Key as JSON (public only)\n";
echo str_repeat( '-', 50 ) . "\n";
echo $rotation_key->export( null, false, 'json' );
echo "\n\n";

// 2. Export with private key included.
echo "2. Export EC Key as JSON (with private key)\n";
echo str_repeat( '-', 50 ) . "\n";
echo $rotation_key->export( null, true, 'json' );
echo "\n\n";

// 3. Export as human-readable text.
echo "3. Export EdDSA Key as Text\n";
echo str_repeat( '-', 50 ) . "\n";
echo $verification_key->export( null, true, 'text' );
echo "\n";

// 4. Export as environment variables.
echo "4. Export Keys as Environment Variables\n";
echo str_repeat( '-', 50 ) . "\n";
echo "# EC Key (secp256k1)\n";
echo $rotation_key->export( null, true, 'env' );
echo "\n# EdDSA Key (ed25519)\n";
echo $verification_key->export( null, true, 'env' );
echo "\n";

// 5. Save to files.
echo "5. Saving Keys to Files\n";
echo str_repeat( '-', 50 ) . "\n";

$temp_dir = sys_get_temp_dir() . '/did-manager-keys-' . uniqid();

// Save JSON format.
$json_path = $temp_dir . '/rotation-key.json';
if ( $rotation_key->export( $json_path, true, 'json' ) ) {
	echo "Saved JSON to: {$json_path}\n";
	echo "Contents:\n" . file_get_contents( $json_path ) . "\n";
}

// Save text format.
$text_path = $temp_dir . '/verification-key.txt';
if ( $verification_key->export( $text_path, false, 'text' ) ) {
	echo "\nSaved Text to: {$text_path}\n";
	echo "Contents:\n" . file_get_contents( $text_path );
}

// Save env format.
$env_path     = $temp_dir . '/keys.env';
$env_content  = '# DID Manager Keys - Generated ' . gmdate( 'c' ) . "\n\n";
$env_content .= "# Rotation Key (secp256k1)\n";
$env_content .= $rotation_key->export( null, true, 'env' );
$env_content .= "\n# Verification Key (Ed25519)\n";
$env_content .= $verification_key->export( null, true, 'env' );
file_put_contents( $env_path, $env_content );
echo "\nSaved ENV to: {$env_path}\n";
echo "Contents:\n" . file_get_contents( $env_path );

// 6. Using to_array() and to_json() directly.
echo "\n6. Direct Method Access\n";
echo str_repeat( '-', 50 ) . "\n";

$key_data = $rotation_key->to_array( true );
echo "to_array() returns:\n";
print_r( $key_data );

echo "\nAccess specific fields:\n";
echo '  Type:       ' . $key_data['type'] . "\n";
echo '  Curve:      ' . $key_data['curve'] . "\n";
echo '  Public Key: ' . substr( $key_data['public_key'], 0, 30 ) . "...\n";
echo '  DID Key:    ' . substr( $key_data['did_key'], 0, 40 ) . "...\n";

// Cleanup.
echo "\n7. Cleanup\n";
echo str_repeat( '-', 50 ) . "\n";
unlink( $json_path );
unlink( $text_path );
unlink( $env_path );
rmdir( $temp_dir );
echo "Temporary files removed.\n";

echo "\n=== Example Complete ===\n";
