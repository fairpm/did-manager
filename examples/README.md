# FAIR DID Manager Examples

This directory contains example scripts for the generic `fairpm/did-manager` package.

## Running Examples

All examples can be run from the command line:

```bash
cd did-manager
php examples/01-generate-keys.php
php examples/02-plc-operations.php
php examples/03-key-storage.php
php examples/06-export-keys.php
php examples/07-generate-and-submit-did.php
```

## Example Overview

### 01-generate-keys.php
Demonstrates cryptographic key generation:
- Generate secp256k1 key pairs (rotation keys)
- Generate Ed25519 key pairs (verification keys)
- Reconstruct keys from encoded strings
- Sign data with keys
- Convert to did:key format

### 02-plc-operations.php
Demonstrates PLC (DID:PLC) operations:
- Create basic PLC operations
- Add handles and service endpoints
- Sign operations
- Generate DIDs from operations
- Get Content Identifiers (CIDs)
- Create update operations

### 03-key-storage.php
Demonstrates local key storage:
- Create and initialize KeyStore
- Store DIDs with their keys
- Retrieve DID entries
- List all stored DIDs
- Update metadata and keys
- Deactivate and delete DIDs

WordPress-specific examples were moved to the `did-manager-wordpress` package.

## Namespaces

The library uses the following namespaces:

| Namespace | Description |
|-----------|-------------|
| `FAIR\DID\Keys` | Key interface and implementations (EcKey, EdDsaKey, KeyFactory) |
| `FAIR\DID\Crypto` | Cryptographic utilities (DidCodec, CanonicalMapObject) |
| `FAIR\DID\PLC` | PLC directory interaction (PlcOperation, PlcClient) |
| `FAIR\DID\Storage` | Local storage (KeyStore) |
| `FAIR\DID` | DID lifecycle management (DIDManager) |

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use FAIR\DID\DIDManager;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\Storage\KeyStore;

// Initialize components
$store = new KeyStore('/path/to/keys.json');
$client = new PlcClient();
$manager = new DIDManager($store, $client);

// Create a new DID
$result = $manager->create_did('example-package');
echo "Created DID: " . $result['did'];
```
