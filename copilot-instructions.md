Here’s an updated **`copilot-instruction.md`** that *allows* Composer for popular security libraries, while keeping everything else lean and simple.

You can replace the previous file with this version.

---

# copilot-instruction.md

**FAIR PHP CLI Tool — Development Guidelines, Tasks & Architecture**

This document instructs GitHub Copilot (and developers) on how to generate code, tests, and tasks for this repository.
The project implements a PHP CLI tool for:

1. **DID Management** using **Bluesky PLC** (`did:plc:` method)
2. **Metadata Generation** for **WordPress Plugins/Themes**
3. Optional: Header injection (Plugin ID / Theme ID)
4. Integration with **FAIR Protocol** (`metadata.json`, schemas)
5. Future integration with **fair-beacon** and **fair-plugin**

Copilot must follow the architecture, rules, and tasks described below.

> **Important:**
>
> * Composer is allowed, but should be used **primarily for well-known, battle-tested security/crypto libraries** (e.g. key generation, signing, encoding).
> * Avoid pulling in heavy frameworks unless absolutely necessary (no full Laravel/Symfony stacks).

---

# 1. Project Scope

This tool will:

### ✔ DID Lifecycle (Bluesky PLC)

* Generate EC secp256k1 (or equivalent) keys using:

  * **OpenSSL** and/or
  * **trusted Composer security libraries** (e.g. `paragonie/*`, `web-token/*`, `kornrunner/*` etc., as needed)
* Encode keys & signatures using **base58btc (multibase "z")**

  * Base58 may be implemented manually *or* via a well-known library
* Generate canonical JSON for DID operations
* Build PLC operations:

  * Create
  * Update
  * Rotate
  * Deactivate
* Submit operations to the PLC directory via HTTP

  * Prefer built-in cURL/streams; a lightweight HTTP client via Composer is acceptable if clearly justified
* Store rotation + verification keys locally (JSON or similar file store)

### ✔ WordPress Metadata Extraction

* Parse WordPress plugin **header comment block** (first 8KB, “Key: Value” syntax)
* Parse **readme.txt** in WordPress.org format:

  * Header metadata (Contributors, Tags, Requires at least…)
  * Short description (first paragraph)
  * Sections (== Description ==, == Installation ==, == Changelog ==…)
* Merge header + readme into a FAIR-compliant **metadata.json** following the schemas in `fair-protocol/schemas/`

### ✔ CLI Commands

```
php fair.php did:create
php fair.php did:resolve
php fair.php did:update
php fair.php did:rotate-keys
php fair.php did:deactivate
php fair.php did:list

php fair.php metadata:generate
php fair.php package:init
```

---

# 2. File Structure

Copilot should maintain this structure (more files can be added as needed):

```text
/fair.php                   # CLI entrypoint router
/MetadataGenerator.php
/PluginHeaderParser.php
/ReadmeParser.php
/FairDidManager.php
/PlcClient.php
/KeyStore.php
/Base58.php                 # If base58 not provided by a library
/DidCodec.php               # multibase, canonical JSON, signing helpers
/composer.json              # For security/crypto-related libraries
/vendor/                    # Composer deps (ignored in VCS if desired)
/tests/
  ├── test_base58.php
  ├── test_canonical_json.php
  ├── test_header_parser.php
  ├── test_readme_parser.php
  ├── test_metadata_generator.php
  ├── test_did_create.php
  ├── fixtures/
```

If a Composer package already provides reliable base58/multibase or secp256k1 primitives, Copilot may use it instead of re-implementing low-level algorithms, **as long as it’s a well-known security-focused library**.

---

# 3. DID Command Requirements

Below are the DID commands, required behavior, and how they map to the existing FAIR ecosystem. Copilot should use this mapping when generating code.

---

## 3.1 `did:create`

**Purpose:**
Create new `did:plc` via Bluesky PLC, generate rotation + verification keys, create and POST the PLC DID creation operation.

**Required Behaviors:**

* Generate cryptographic keys:

  * Prefer secure primitives via OpenSSL or a reputable Composer security library
* Extract raw public key and encode as multibase base58btc
* Build PLC **create** operation JSON
* Canonicalize operation JSON before signing
* Sign operation with rotation key using SHA-256 and appropriate curve

  * Encode signature in base58btc multibase
* POST to PLC `/create` endpoint
* Store DID + keys in local KeyStore (JSON file or similar)
* Optional: modify plugin header (`Plugin ID:`)
* Optional: generate metadata.json for a plugin

**Tasks:**

* Implement `DidManager::createDid()`
* Implement `PlcClient::createDid()`
* Implement `KeyStore` handling
* Implement CLI command `did:create`
* Tests:

  * Key generation
  * Signature correctness
  * Operation assembly
  * Local DID store format and behavior
  * Plugin ID header injection (if used)

---

## 3.2 `did:resolve`

**Purpose:**
Fetch DID document from PLC.

**Required Behaviors:**

* GET `https://plc.directory/<did>` (or configured PLC base URL)
* Parse DID document JSON
* Display:

  * Service endpoints
  * Verification methods / keys
* Optional: follow repository endpoint to fetch `metadata.json`

**Tasks:**

* Implement `PlcClient::resolveDid()`
* Implement `DidManager::resolveDid()`
* Implement CLI command `did:resolve`
* Tests:

  * Mock PLC responses
  * Validate parsing
  * Validate output formatting / `--json` mode if present

---

## 3.3 `did:update`

**Purpose:**
Modify DID document fields such as service endpoints or handle.

**Required Behaviors:**

* Fetch current DID document
* Build update operation with:

  * `prev` referencing last operation
  * updated services/handle
* Canonicalize JSON
* Sign with rotation key
* POST to PLC `/update`
* Optionally refresh linked metadata.json

**Tasks:**

* Implement `DidManager::updateDid(string $did, array $changes)`
* Implement CLI command `did:update`
* Tests:

  * Operation builder correctness
  * Mocked PLC update submission
  * Behavior when DID not found or rotation key missing

---

## 3.4 `did:rotate-keys`

**Purpose:**
Rotate keys for an existing DID.

**Required Behaviors:**

* Load existing DID + rotation key from `KeyStore`
* Generate new keypair(s)
* Build PLC rotation update operation
* Sign with current rotation key
* POST to PLC `/update`
* On success, update local KeyStore to only use new keys

**Tasks:**

* Implement `DidManager::rotateKeys(string $did, ?string $reason = null)`
* CLI command `did:rotate-keys`
* Tests:

  * New keys correctly generated, stored
  * Rotation op structure
  * Failure behavior when rotation key missing

---

## 3.5 `did:deactivate`

**Purpose:**
Deactivate a DID (if supported by PLC) or “soft deactivate” by removing services and keys.

**Required Behaviors:**

* Build appropriate deactivation operation (or equivalent update)
* Sign and submit to PLC
* Mark DID as deactivated in local store

**Tasks:**

* Implement `DidManager::deactivateDid(string $did): bool`
* CLI command `did:deactivate` (with confirmation or `--force`)
* Tests:

  * Deactivation op structure (mock)
  * Post-deactivation behavior (other commands should warn or refuse)

---

## 3.6 `did:list`

**Purpose:**
List locally stored DIDs and their basic status.

**Required Behaviors:**

* Read local KeyStore / DID store
* Print:

  * DID
  * Type (plugin/theme/repo/etc. if available)
  * Status (active/deactivated)
  * Key info (high level only)

**Tasks:**

* Implement `DidManager::listLocalDids(): array`
* CLI command `did:list`
* Tests:

  * Behavior with empty store
  * Behavior with multiple DIDs
  * Format and JSON output if `--json` flag supported

---

# 4. WordPress Metadata Tasks

Copilot must approximate WordPress.org’s behavior when parsing plugin metadata.

---

## 4.1 Plugin Header Parsing (`PluginHeaderParser.php`)

**Rules:**

* Read first ~8 KB of a plugin’s main PHP file
* Parse the header comment block for lines in `Key: Value` format
* Normalize keys to lower snake_case (e.g., `Plugin Name` → `plugin_name`)
* Recognize at minimum:

  * `Plugin Name`
  * `Plugin URI`
  * `Description`
  * `Version`
  * `Author`
  * `Author URI`
  * `Text Domain`
  * `Requires at least`
  * `Requires PHP`
  * `License`
  * `License URI`
  * `Tags` (comma-separated)
  * FAIR-specific `Plugin ID` (for DID)

**Tasks:**

* Implement header discovery (find main plugin file by looking for `Plugin Name:`)
* Parse fields as described
* Tests:

  * Standard header with all fields
  * Minimal header
  * Header with malformed lines
  * Tags parsing
  * Plugin ID handling

---

## 4.2 Readme Parsing (`ReadmeParser.php`)

**Rules (WordPress.org readme.txt format):**

* Optionally first line: `=== Plugin Name ===`
* Header block (until first `== Section ==`) contains:

  * `Contributors`, `Tags`, `Requires at least`, `Tested up to`, `Requires PHP`, `Stable tag`, `License`, `License URI`, etc.
* Short description:

  * First non-empty paragraph after header, before first section
* Sections:

  * Marked by `== Section Name ==`
  * Examples: `Description`, `Installation`, `FAQ`, `Changelog`, `Screenshots`

**Tasks:**

* Parse header key/value lines into an associative array
* Extract `short_description`
* Parse sections into a `sections` map (normalized keys like `description`, `installation`, `faq`, `changelog`)
* Tests:

  * Realistic readme fixture
  * Readme with missing sections
  * Readme with only description
  * Edge cases (extra whitespace, inconsistent casing)

---

## 4.3 Metadata Generation (`MetadataGenerator.php`)

**Input:**

* Plugin header array
* Readme parsed array
* Optional DID
* Optional slug override

**Output:**

* `metadata.json` that matches FAIR protocol WordPress plugin schemas.

**Merging Rules (high level):**

* `slug`: CLI `--slug` override > plugin folder name
* `name`: plugin header `Plugin Name` > readme plugin name > slug
* `version`: plugin header `Version` (readme stable tag is secondary or informational)
* `description`: readme short description > header `Description`
* `homepage`: header `Plugin URI`
* `author.name`: header `Author`
* `author.url`: header `Author URI`
* `license.name`: readme `License` > header `License`
* `license.url`: readme `License URI` > header `License URI`
* `requires.wordpress`: header `Requires at least` > readme `Requires at least`
* `requires.php`: header `Requires PHP` > readme `Requires PHP`
* `tags`: union of header `Tags` and readme `Tags` (unique, trimmed)
* `readme.header`: raw header metadata from readme
* `readme.sections`: parsed sections map
* `did`: injected if provided

**Tasks:**

* Implement `MetadataGenerator::generate(): array` combining the above
* Implement `MetadataGenerator::writeToFile($filename)` as a convenience
* CLI command: `metadata:generate` (`php fair.php metadata:generate <plugin-path>`)
* Combined command: `package:init` to create DID + metadata in one go

**Tests:**

* Plugin with header + readme
* Header-only plugin
* Readme-only plugin
* DID injection
* Slug override behavior

---

# 5. Cryptography & Security

Copilot may use Composer **for security-focused libraries** if needed, but should keep dependencies minimal and well-known.

### Allowed use cases for Composer:

* EC key generation and signing (e.g., `kornrunner/secp256k1` or similar, if OpenSSL support is insufficient or awkward)
* Base58/multibase encoding (if using a trusted, small library instead of hand-rolling)
* JOSE/JWT or signature helper libraries (if strictly necessary for DID operations)
* Sodium compatibility/polyfills (`paragonie/sodium_compat`), if used

### Preferred approach:

* For simple tasks, use built-in extensions:

  * `openssl_pkey_new`, `openssl_sign`, `openssl_verify`
* For complex, error-prone primitives (e.g., low-level secp256k1 math), prefer a well-known library via Composer over custom crypto code.

### Base58btc (`Base58.php` or library)

* Implement encode/decode manually **or**
* Use a reputable library if available via Composer

### Multibase (`DidCodec.php`)

* Prefix base58btc strings with `"z"` for multibase
* Provide helpers for:

  * `toMultibaseBase58(string $binary): string`
  * `fromMultibaseBase58(string $multibase): string`

### Canonical JSON

* Implement deterministic JSON serialization:

  * Sort keys lexicographically at all object levels
  * No extra whitespace beyond what `json_encode` produces (or write a custom encoder)
* This can be implemented manually; avoid heavy JSON libraries.

### Signing

* Use OpenSSL or a well-established Composer library to:

  * Sign canonical JSON using SHA-256 over the message
  * Support appropriate curves per PLC spec (e.g., secp256k1)

### Tests

* Base58 encode/decode against known vectors
* Canonical JSON determinism tests
* Signature round-trip tests (sign + verify)
* Key generation sanity checks

---

# 6. PLC Client Tasks (`PlcClient.php`)

Implement a client for the Bluesky PLC (did:plc) API.

### Required Functions

* `createDid(array $operation): array`
* `updateDid(array $operation): array`
* `resolveDid(string $did): array`

### Behaviors:

* Use either cURL or a lightweight HTTP client (Composer is allowed but not required here)
* Handle network errors gracefully
* Parse JSON responses into PHP arrays
* Support configurable PLC base URL (default `https://plc.directory`)

### Tests:

* PLC client tests should mock HTTP responses (e.g., via test doubles rather than external services)
* Verify request body structure and headers
* Verify error-handling logic

---

# 7. CLI Wrapper (`fair.php`)

Copilot must:

* Use manual `$argv` parsing (or minimal custom parser)
* Map commands to methods:

  * `metadata:generate`
  * `package:init`
  * `did:*` commands
* Print human-readable output by default
* Optionally support `--json` to output structured JSON for scripts
* Provide `--help` per command

### Tests:

* CLI argument parsing tests (simulate `$argv`)
* Command behavior using mocks/stubs (`DidManager`, `MetadataGenerator`)
* Error messages for invalid/missing arguments

---

# 8. Test Case Requirements

Tests live under `/tests`. They can be:

* Simple PHP scripts using `assert()`
* Or a minimal PHPUnit setup (Composer allowed for PHPUnit if desired)

### Required test suites:

1. Base58 / multibase tests
2. Canonical JSON tests
3. Key generation + signing tests
4. Plugin header parser tests
5. Readme parser tests
6. Metadata generator tests
7. DID create/resolve/update tests (with mocked PLC)
8. CLI command tests

Each test file should be runnable via:

```bash
php tests/test_xxx.php
```

or, if PHPUnit is used:

```bash
vendor/bin/phpunit
```

---

# 9. Development Rules for Copilot

### ✔ Rule 1 — Composer is allowed selectively

* It may be used for:

  * Security/cryptography primitives
  * Testing frameworks
  * Small, focused utility libraries (like base58)
* Avoid heavy frameworks (no full Laravel/Symfony apps)
* Keep dependency count low and focused.

### ✔ Rule 2 — Obey the file structure & responsibilities

* New code should fit into the described classes or clearly-related files.

### ✔ Rule 3 — Always consider tests

* Whenever adding a new major function or command, also add/extend a corresponding test.

### ✔ Rule 4 — Favor clarity & maintainability

* Use comments and docblocks
* Don’t over-engineer abstractions

### ✔ Rule 5 — Align with FAIR Protocol

* `metadata.json` must be compatible with FAIR protocol schemas and examples
* Field naming and structure should follow the protocol docs

---

# 10. Summary

This tool will unify:

* **Bluesky PLC DID creation & lifecycle**
* **FAIR-compliant metadata.json generation** for WordPress plugins
* A **PHP CLI interface** that is portable, secure, and testable
* Integration paths for **fair-beacon** and **fair-plugin**

Copilot should use this document as the global guideline for all generated code, tests, and architectural choices in this repository.
