# FAIR DID Manager

`fairpm/did-manager` is the core FAIR DID library. It contains generic DID lifecycle management, PLC operations, key generation/export, and local key storage.

## Features

- Create, resolve, update, rotate, and deactivate `did:plc` identifiers
- Generate secp256k1 rotation keys and Ed25519 verification keys
- Encode/sign PLC operations with CBOR and multibase helpers
- Store DIDs, keys, and generic metadata locally
- Export keys in JSON, text, and environment-variable formats

## Requirements

- PHP 8.3 or higher
- Composer
- Extensions: `curl`, `json`

## Installation

```bash
git clone https://github.com/fairpm/did-manager.git
cd did-manager
composer install
```

For WordPress package metadata parsing, install `fairpm/did-manager-wordpress` alongside this package.

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use FAIR\DID\DIDManager;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\Storage\KeyStore;

$store = new KeyStore(__DIR__ . '/keys.json');
$client = new PlcClient();
$manager = new DIDManager($store, $client);

$result = $manager->create_did(
	handle: 'example-package',
	service_endpoint: 'https://example.com/did-endpoint',
	type: 'package',
	metadata: ['owner' => 'Example Org'],
);

echo $result['did'] . PHP_EOL;
```

## Namespaces

- `FAIR\DID\Crypto` for encoding, canonicalization, and DID helpers
- `FAIR\DID\Keys` for key generation, decoding, and export
- `FAIR\DID\PLC` for PLC client and operation objects
- `FAIR\DID\Storage` for local key/DID persistence
- `FAIR\DID` for high-level DID lifecycle orchestration

## Examples

Core examples remain in [examples](examples):

- `01-generate-keys.php`
- `02-plc-operations.php`
- `03-key-storage.php`
- `06-export-keys.php`
- `07-generate-and-submit-did.php`

WordPress examples were moved to the `did-manager-wordpress` package.

## Testing

```bash
composer test
composer lint
composer analyze
```

## Related Packages

- `fairpm/did-manager-wordpress` for WordPress header parsing, readme parsing, and FAIR metadata generation

## Security

Never commit private keys or generated keystore files to version control.

## License

GPL-3.0-or-later. See `LICENSE.md` for details.
