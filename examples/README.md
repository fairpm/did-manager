# FAIR CLI Examples

This directory contains example scripts demonstrating how to use the FAIR CLI library.

## Running Examples

All examples can be run from the command line:

```bash
cd did-manager
php examples/01-generate-keys.php
php examples/02-plc-operations.php
php examples/03-key-storage.php
php examples/04-parse-plugin-headers.php
php examples/05-generate-metadata.php
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

### 04-parse-plugin-headers.php
Demonstrates WordPress plugin/theme header parsing:
- Parse standard plugin headers
- Parse minimal headers
- Parse from files
- Handle plugins without Plugin ID
- Parse theme headers (style.css)

### 05-generate-metadata.php
Demonstrates FAIR metadata generation:
- Parse plugin headers and readme files
- Generate metadata.json structure
- Add DID to metadata
- Override slug
- Inspect full metadata structure
- Output as formatted JSON

## CLI Commands

The FAIR CLI also provides command-line tools:

```bash
# Generate a new DID
php did-manager.php did:create --handle my-plugin

# List local DIDs
php did-manager.php did:list

# Resolve a DID
php did-manager.php did:resolve did:plc:abc123

# Generate metadata for a plugin
php did-manager.php metadata:generate /path/to/plugin

# Rotate keys for a DID
php did-manager.php key:rotate did:plc:abc123
```

## Namespaces

The library uses the following namespaces:

| Namespace | Description |
|-----------|-------------|
| `FairDidManager\Keys` | Key interface and implementations (EcKey, EdDsaKey, KeyFactory) |
| `FairDidManager\Crypto` | Cryptographic utilities (DidCodec, CanonicalMapObject) |
| `FairDidManager\Plc` | PLC directory interaction (PlcOperation, PlcClient) |
| `FairDidManager\Storage` | Local storage (KeyStore) |
| `FairDidManager\Parsers` | WordPress metadata parsing (PluginHeaderParser, ReadmeParser, MetadataGenerator) |
| `FairDidManager\Did` | DID lifecycle management (FairDidManager) |

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use FairDidManager\Crypto\DidCodec;
use FairDidManager\Storage\KeyStore;
use FairDidManager\Plc\PlcClient;
use FairDidManager\Did\FairDidManager;

// Initialize components
$store = new KeyStore('/path/to/keys.json');
$client = new PlcClient();
$manager = new FairDidManager($store, $client);

// Create a new DID
$result = $manager->create_did('my-plugin-handle');
echo "Created DID: " . $result['did'];
```
