# FAIR DID Manager

A PHP library for DID (Decentralized Identifier) management and WordPress plugin/theme metadata generation, implementing the FAIR (Federated Asset Integrity Registry) protocol.

## Features

- **DID Management**: Create, update, rotate keys, and deactivate DIDs using the PLC directory
- **Key Generation**: Generate secp256k1 (rotation) and Ed25519 (verification) key pairs
- **Key Export**: Export keys in JSON, text, or environment variable formats
- **Local Key Storage**: Securely store and manage DIDs and their associated keys
- **WordPress Integration**: Parse plugin/theme headers and readme.txt files
- **Metadata Generation**: Generate FAIR-compliant metadata.json files

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

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use FairDidManager\Crypto\DidCodec;
use FairDidManager\Storage\KeyStore;
use FairDidManager\Plc\PlcClient;
use FairDidManager\Did\FairDidManager;

// Initialize components
$store = new KeyStore('keys.json');
$client = new PlcClient();
$manager = new FairDidManager($store, $client);

// Create a new DID
$result = $manager->create_did('my-plugin-handle');
echo "Created DID: " . $result['did'];
```

## Project Structure

```
did-manager/
├── src/
│   ├── crypto/          # Cryptographic utilities
│   │   ├── class-did-codec.php
│   │   └── class-canonical-map-object.php
│   ├── did/             # DID lifecycle management
│   │   └── class-fair-did-manager.php
│   ├── keys/            # Key interfaces and implementations
│   │   ├── class-key.php
│   │   ├── class-ec-key.php
│   │   ├── class-ed-dsa-key.php
│   │   ├── class-key-exporter.php
│   │   └── class-key-factory.php
│   ├── parsers/         # WordPress metadata parsing
│   │   ├── class-plugin-header-parser.php
│   │   ├── class-readme-parser.php
│   │   └── class-metadata-generator.php
│   ├── plc/             # PLC directory interaction
│   │   ├── class-plc-client.php
│   │   └── class-plc-operation.php
│   └── storage/         # Local key storage
│       └── class-key-store.php
├── tests/               # PHPUnit tests (200+ tests)
├── examples/            # Usage examples
├── composer.json
├── phpunit.xml
└── fair-did.json        # Package DID configuration
```

## Namespaces

| Namespace | Description |
|-----------|-------------|
| `FairDidManager\Keys` | Key interface and implementations (EcKey, EdDsaKey, KeyFactory, KeyExporter) |
| `FairDidManager\Crypto` | Cryptographic utilities (DidCodec, CanonicalMapObject) |
| `FairDidManager\Plc` | PLC directory interaction (PlcOperation, PlcClient) |
| `FairDidManager\Storage` | Local storage (KeyStore) |
| `FairDidManager\Parsers` | WordPress metadata parsing (PluginHeaderParser, ReadmeParser, MetadataGenerator) |
| `FairDidManager\Did` | DID lifecycle management (FairDidManager) |

## Examples

See the [`examples/`](examples/) directory for detailed usage examples:

- `01-generate-keys.php` - Key generation and signing
- `02-plc-operations.php` - PLC operations and DID generation
- `03-key-storage.php` - Local key storage management
- `04-parse-plugin-headers.php` - WordPress header parsing
- `05-generate-metadata.php` - FAIR metadata generation
- `06-export-keys.php` - Export keys in various formats

Run an example:

```bash
php examples/01-generate-keys.php
```

## API Overview

### Key Generation

```php
use FairDidManager\Crypto\DidCodec;

// Generate secp256k1 key pair (for rotation keys)
$rotationKey = DidCodec::generate_key_pair();

// Generate Ed25519 key pair (for verification keys)
$verificationKey = DidCodec::generate_ed25519_key_pair();

// Sign data
$signature = $rotationKey->sign(hash('sha256', $data, false));
```

### Key Export

```php
use FairDidManager\Keys\EcKey;

$key = EcKey::generate();

// Export as JSON (default)
echo $key->export();

// Export with private key included
echo $key->export(null, true);

// Export as human-readable text
echo $key->export(null, true, 'text');

// Export as environment variables
echo $key->export(null, true, 'env');

// Save to file
$key->export('/path/to/key.json', true, 'json');

// Get as array for programmatic use
$data = $key->to_array(true);
```

### DID Creation

```php
use FairDidManager\Crypto\DidCodec;

// Create a PLC operation
$operation = DidCodec::create_plc_operation(
    $rotationKey,
    $verificationKey,
    'my-handle',
    'https://api.example.com'
);

// Sign the operation
$signedOperation = DidCodec::sign_plc_operation($operation, $rotationKey);

// Generate the DID
$did = DidCodec::generate_plc_did($signedOperation);
```

### Key Storage

```php
use FairDidManager\Storage\KeyStore;

$store = new KeyStore('keys.json');

// Store a DID with keys
$store->store_did(
    $did,
    $rotationKey->encode_private(),
    $rotationKey->encode_public(),
    $verificationKey->encode_private(),
    $verificationKey->encode_public(),
    'plugin',
    ['name' => 'My Plugin']
);

// Retrieve keys
$rotationPrivate = $store->get_rotation_key($did);
```

### WordPress Metadata Parsing

```php
use FairDidManager\Parsers\PluginHeaderParser;
use FairDidManager\Parsers\ReadmeParser;
use FairDidManager\Parsers\MetadataGenerator;

// Parse plugin header
$headerParser = new PluginHeaderParser();
$headerData = $headerParser->parse_file('/path/to/plugin.php');

// Parse readme.txt
$readmeParser = new ReadmeParser();
$readmeData = $readmeParser->parse_file('/path/to/readme.txt');

// Generate FAIR metadata
$generator = new MetadataGenerator($headerData, $readmeData);
$generator->set_did($did);
$metadata = $generator->generate();
```

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test -- --coverage-html coverage
```

Lint the code:

```bash
composer lint
```

Fix coding standards:

```bash
composer lint:fix
```

## Dependencies

- [simplito/elliptic-php](https://github.com/nickolasburr/php-elliptic) - Elliptic curve cryptography
- [spomky-labs/cbor-php](https://github.com/Spomky-Labs/cbor-php) - CBOR encoding for DAG-CBOR
- [yocto/yoclib-multibase](https://packagist.org/packages/yocto/yoclib-multibase) - Multibase encoding

## Security

⚠️ **Important**: Never commit private keys to version control!

- Private keys are stored locally in JSON files (e.g., `keys.json`)
- The `.gitignore` file excludes common key storage patterns
- Always back up your keys securely

## License

MIT License - see [LICENSE](LICENSE) for details.

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Related Projects

- [FAIR Protocol Specification](https://fair-protocol.org)
- [AT Protocol](https://atproto.com)
- [DID:PLC Method](https://github.com/did-method-plc/did-method-plc)
