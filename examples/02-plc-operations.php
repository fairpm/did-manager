<?php
/**
 * Example 2: Creating PLC Operations
 *
 * This example demonstrates how to create and sign PLC operations
 * for DID creation and updates.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\PLC\PlcOperation;

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== FAIR CLI: PLC Operation Examples ===\n\n";

// -----------------------------------------------------------------------------
// 1. Create a basic PLC operation (genesis operation)
// -----------------------------------------------------------------------------
echo "1. Creating Basic PLC Operation\n";
echo str_repeat( '-', 50 ) . "\n";

// Generate keys.
$rotation_key     = DidCodec::generate_key_pair();
$verification_key = DidCodec::generate_ed25519_key_pair();

// Create the operation.
$operation = DidCodec::create_plc_operation( $rotation_key, $verification_key );

echo 'Operation Type: ' . $operation->type . "\n";
echo 'Rotation Keys Count: ' . count( $operation->rotation_keys ) . "\n";
echo 'Verification Methods Count: ' . count( $operation->verification_methods ) . "\n";
$aka = empty( $operation->also_known_as ) ? '(none)' : implode( ', ', $operation->also_known_as );
echo 'Also Known As: ' . $aka . "\n";
$svcs = empty( $operation->services ) ? '(none)' : implode( ', ', array_keys( $operation->services ) );
echo 'Services: ' . $svcs . "\n\n";

// -----------------------------------------------------------------------------
// 2. Create operation with handle and service
// -----------------------------------------------------------------------------
echo "2. Creating PLC Operation with Handle and Service\n";
echo str_repeat( '-', 50 ) . "\n";

$handle           = 'my-awesome-plugin';
$service_endpoint = 'https://api.example.com/fair';

$operation_with_handle = DidCodec::create_plc_operation(
	$rotation_key,
	$verification_key,
	$handle,
	$service_endpoint
);

echo 'Handle (alsoKnownAs): ' . implode( ', ', $operation_with_handle->also_known_as ) . "\n";
echo 'Service Endpoint: ' . $operation_with_handle->services['atproto_pds']['endpoint'] . "\n\n";

// -----------------------------------------------------------------------------
// 3. Sign the operation
// -----------------------------------------------------------------------------
echo "3. Signing the Operation\n";
echo str_repeat( '-', 50 ) . "\n";

$signed_operation = DidCodec::sign_plc_operation( $operation_with_handle, $rotation_key );

echo 'Signature: ' . substr( $signed_operation->sig, 0, 40 ) . "...\n";
echo 'Signature Length: ' . strlen( $signed_operation->sig ) . " chars\n\n";

// -----------------------------------------------------------------------------
// 4. Generate DID from signed operation
// -----------------------------------------------------------------------------
echo "4. Generating DID from Signed Operation\n";
echo str_repeat( '-', 50 ) . "\n";

$did = DidCodec::generate_plc_did( $signed_operation );

echo 'Generated DID: ' . $did . "\n\n";

// -----------------------------------------------------------------------------
// 5. Get CID (Content Identifier) of the operation
// -----------------------------------------------------------------------------
echo "5. Getting Operation CID\n";
echo str_repeat( '-', 50 ) . "\n";

$cid = $signed_operation->get_cid();

echo 'Operation CID: ' . $cid . "\n\n";

// -----------------------------------------------------------------------------
// 6. JSON serialization of operation
// -----------------------------------------------------------------------------
echo "6. JSON Serialization\n";
echo str_repeat( '-', 50 ) . "\n";

$json_data = $signed_operation->jsonSerialize();

echo 'JSON Keys: ' . implode( ', ', array_keys( $json_data ) ) . "\n";
echo 'Rotation Keys Format: ' . $json_data['rotationKeys'][0] . "\n";
echo 'Verification Methods: ' . json_encode( array_keys( $json_data['verificationMethods'] ) ) . "\n\n";

// -----------------------------------------------------------------------------
// 7. Creating an update operation (with prev reference)
// -----------------------------------------------------------------------------
echo "7. Creating Update Operation\n";
echo str_repeat( '-', 50 ) . "\n";

// Generate new verification key for rotation.
$new_verification_key = DidCodec::generate_ed25519_key_pair();
$key_id               = 'fair_' . substr( hash( 'sha256', $new_verification_key->encode_public() ), 0, 6 );

$update_operation = new PlcOperation(
	type: 'plc_operation',
	rotation_keys: [ $rotation_key ],
	verification_methods: [ $key_id => $new_verification_key ],
	also_known_as: [ 'at://updated-plugin-handle' ],
	services: [
		'atproto_pds' => [
			'type'     => 'AtprotoPersonalDataServer',
			'endpoint' => 'https://new-api.example.com/fair',
		],
	],
	prev: $cid // Reference to previous operation.
);

$signed_update = $update_operation->sign( $rotation_key );

echo 'Update Operation Prev: ' . $update_operation->prev . "\n";
echo 'New Handle: ' . implode( ', ', $update_operation->also_known_as ) . "\n";
echo 'Update Signature: ' . substr( $signed_update->sig, 0, 40 ) . "...\n\n";

echo "=== Example Complete ===\n";
