<?php
/**
 * Example 1: Generating Cryptographic Keys
 *
 * This example demonstrates how to generate different types of key pairs
 * used for DID operations.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FairDidManager\Crypto\DidCodec;
use FairDidManager\Keys\EcKey;
use FairDidManager\Keys\EdDsaKey;

echo "=== FAIR CLI: Key Generation Examples ===\n\n";

// -----------------------------------------------------------------------------
// 1. Generate a secp256k1 key pair (used for rotation keys)
// -----------------------------------------------------------------------------
echo "1. Generating secp256k1 Key Pair (Rotation Key)\n";
echo str_repeat( '-', 50 ) . "\n";

$rotation_key = DidCodec::generate_key_pair();

echo 'Public Key (multibase):  ' . $rotation_key->encode_public() . "\n";
echo 'Private Key (multibase): ' . substr( $rotation_key->encode_private(), 0, 20 ) . "...\n";
echo 'Curve: ' . $rotation_key->get_curve() . "\n";
echo 'Is Private Key: ' . ( $rotation_key->is_private() ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 2. Generate an Ed25519 key pair (used for verification/signing)
// -----------------------------------------------------------------------------
echo "2. Generating Ed25519 Key Pair (Verification Key)\n";
echo str_repeat( '-', 50 ) . "\n";

$verification_key = DidCodec::generate_ed25519_key_pair();

echo 'Public Key (multibase):  ' . $verification_key->encode_public() . "\n";
echo 'Private Key (multibase): ' . substr( $verification_key->encode_private(), 0, 20 ) . "...\n";
echo 'Curve: ' . $verification_key->get_curve() . "\n\n";

// -----------------------------------------------------------------------------
// 3. Reconstruct keys from encoded strings
// -----------------------------------------------------------------------------
echo "3. Reconstructing Keys from Encoded Strings\n";
echo str_repeat( '-', 50 ) . "\n";

$encoded_private = $rotation_key->encode_private();
$reconstructed   = EcKey::from_private( $encoded_private );

echo 'Original Public:      ' . $rotation_key->encode_public() . "\n";
echo 'Reconstructed Public: ' . $reconstructed->encode_public() . "\n";
echo 'Keys Match: ' . ( $rotation_key->encode_public() === $reconstructed->encode_public() ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 4. Sign data with keys
// -----------------------------------------------------------------------------
echo "4. Signing Data\n";
echo str_repeat( '-', 50 ) . "\n";

$message      = 'Hello, FAIR Protocol!';
$message_hash = hash( 'sha256', $message, false );

$ec_signature    = $rotation_key->sign( $message_hash );
$eddsa_signature = $verification_key->sign( $message_hash );

echo "Message: {$message}\n";
echo 'EC Signature (hex):    ' . substr( $ec_signature, 0, 40 ) . "...\n";
echo 'EdDSA Signature (hex): ' . substr( $eddsa_signature, 0, 40 ) . "...\n\n";

// -----------------------------------------------------------------------------
// 5. Convert to did:key format
// -----------------------------------------------------------------------------
echo "5. Converting to did:key Format\n";
echo str_repeat( '-', 50 ) . "\n";

use FairDidManager\Keys\KeyFactory;

$did_key_rotation     = KeyFactory::encode_did_key( $rotation_key );
$did_key_verification = KeyFactory::encode_did_key( $verification_key );

echo 'Rotation Key as did:key:     ' . $did_key_rotation . "\n";
echo 'Verification Key as did:key: ' . $did_key_verification . "\n\n";

echo "=== Example Complete ===\n";
