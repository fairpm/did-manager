<?php
/**
 * FairDidManager - DID lifecycle management
 *
 * @package FairDidManager\Did
 */

declare(strict_types=1);

namespace FAIR\DID;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\KeyFactory;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use FAIR\DID\Sorage\KeyStore;

/**
 * FairDidManager - DID lifecycle management.
 *
 * Manages the full lifecycle of DIDs including creation, resolution,
 * updates, key rotation, and deactivation.
 */
class DIDManager {

	/**
	 * Key storage instance.
	 *
	 * @var KeyStore
	 */
	private KeyStore $key_store;

	/**
	 * PLC client instance.
	 *
	 * @var PlcClient
	 */
	private PlcClient $plc_client;

	/**
	 * Constructor.
	 *
	 * @param KeyStore  $key_store Key storage instance.
	 * @param PlcClient $plc_client PLC client instance.
	 */
	public function __construct( KeyStore $key_store, PlcClient $plc_client ) {
		$this->key_store  = $key_store;
		$this->plc_client = $plc_client;
	}

	/**
	 * Create a new DID.
	 *
	 * @param string|null $handle           Optional handle/alias.
	 * @param string|null $service_endpoint Optional service endpoint URL.
	 * @param string|null $plugin_path      Optional plugin path for header injection.
	 * @param bool        $inject_id        Whether to inject Plugin ID header.
	 * @return array Created DID info.
	 * @throws \RuntimeException On failure.
	 */
	public function create_did(
		?string $handle = null,
		?string $service_endpoint = null,
		?string $plugin_path = null,
		bool $inject_id = false
	): array {
		// Generate rotation key pair (secp256k1).
		$rotation_key = DidCodec::generate_key_pair();

		// Generate verification key pair (Ed25519).
		$verification_key = DidCodec::generate_ed25519_key_pair();

		// Build the genesis operation.
		$operation = DidCodec::create_plc_operation(
			$rotation_key,
			$verification_key,
			$handle,
			$service_endpoint
		);

		// Sign the operation.
		$signed_operation = DidCodec::sign_plc_operation( $operation, $rotation_key );

		// Generate the DID from the signed genesis operation.
		$did = DidCodec::generate_plc_did( $signed_operation );

		// Submit to PLC directory.
		try {
			$this->plc_client->create_did( (array) $signed_operation->jsonSerialize() );
		} catch ( \RuntimeException $e ) {
			throw new \RuntimeException( 'Failed to create DID on PLC directory: ' . $e->getMessage() );
		}

		// Determine type from plugin path.
		$type = null;
		if ( null !== $plugin_path ) {
			$type = $this->detect_package_type( $plugin_path );
		}

		// Store DID and keys locally.
		$this->key_store->store_did(
			$did,
			$rotation_key->encode_private(),
			$rotation_key->encode_public(),
			$verification_key->encode_private(),
			$verification_key->encode_public(),
			$type,
			[
				'handle'          => $handle,
				'serviceEndpoint' => $service_endpoint,
				'pluginPath'      => $plugin_path,
			]
		);

		// Inject Plugin ID header if requested.
		if ( $inject_id && null !== $plugin_path ) {
			$this->inject_plugin_id( $plugin_path, $did );
		}

		return [
			'did'             => $did,
			'rotationKey'     => $rotation_key->encode_public(),
			'verificationKey' => $verification_key->encode_public(),
			'handle'          => $handle,
			'serviceEndpoint' => $service_endpoint,
		];
	}

	/**
	 * Resolve a DID to its document.
	 *
	 * @param string $did The DID to resolve.
	 * @return array The DID document.
	 * @throws \RuntimeException On failure.
	 */
	public function resolve_did( string $did ): array {
		return $this->plc_client->resolve_did( $did );
	}

	/**
	 * Update a DID document.
	 *
	 * @param string $did     The DID to update.
	 * @param array  $changes Changes to apply (handle, service, etc.).
	 * @return array Updated DID info.
	 * @throws \RuntimeException On failure.
	 */
	public function update_did( string $did, array $changes ): array {
		// Get the rotation key.
		$rotation_key_encoded = $this->key_store->get_rotation_key( $did );
		if ( null === $rotation_key_encoded ) {
			throw new \RuntimeException( "Rotation key not found for DID: {$did}" );
		}
		$rotation_key = KeyFactory::decode_private_key( $rotation_key_encoded );

		// Get the current DID document.
		$current_doc = $this->plc_client->resolve_did( $did );

		// Get the last operation.
		$last_op = $this->plc_client->get_last_operation( $did );
		if ( null === $last_op ) {
			throw new \RuntimeException( "Could not retrieve last operation for DID: {$did}" );
		}

		// Get current keys.
		$did_entry = $this->key_store->get_did( $did );

		// Decode existing rotation keys.
		$rotation_keys_array = [];
		$rotation_key_data   = $current_doc['rotationKeys'] ?? [ $did_entry['rotationKey']['public'] ];
		foreach ( $rotation_key_data as $key_str ) {
			$rotation_keys_array[] = KeyFactory::decode_did_key( $key_str );
		}

		// Decode existing verification methods.
		$verification_methods = [];
		$methods_data         = $current_doc['verificationMethods'] ?? [];
		foreach ( $methods_data as $id => $key_str ) {
			$verification_methods[ $id ] = KeyFactory::decode_did_key( $key_str );
		}

		// Build also known as.
		$also_known_as = $current_doc['alsoKnownAs'] ?? [];

		// Build services.
		$services = $current_doc['services'] ?? [];

		// Apply changes.
		if ( isset( $changes['handle'] ) ) {
			$also_known_as = [ 'at://' . $changes['handle'] ];
		}

		if ( isset( $changes['service'] ) ) {
			$services['atproto_pds'] = [
				'type'     => 'AtprotoPersonalDataServer',
				'endpoint' => $changes['service'],
			];
		}

		// Build update operation.
		$operation = new PlcOperation(
			type: 'plc_operation',
			rotation_keys: $rotation_keys_array,
			verification_methods: $verification_methods,
			also_known_as: $also_known_as,
			services: $services,
			prev: $last_op['cid'] ?? null,
		);

		// Sign and submit.
		$signed_operation = $operation->sign( $rotation_key );
		$this->plc_client->update_did( $did, (array) $signed_operation->jsonSerialize() );

		// Update local metadata.
		$this->key_store->update_metadata( $did, $changes );

		return array_merge( [ 'did' => $did ], $changes );
	}

	/**
	 * Rotate keys for a DID.
	 *
	 * @param string      $did    The DID to rotate keys for.
	 * @param string|null $reason Optional reason for rotation.
	 * @return array New key info.
	 * @throws \RuntimeException On failure.
	 */
	public function rotate_keys( string $did, ?string $reason = null ): array {
		// Get current rotation key.
		$current_rotation_key_encoded = $this->key_store->get_rotation_key( $did );
		if ( null === $current_rotation_key_encoded ) {
			throw new \RuntimeException( "Rotation key not found for DID: {$did}" );
		}
		$current_rotation_key = KeyFactory::decode_private_key( $current_rotation_key_encoded );

		// Get current document.
		$current_doc = $this->plc_client->resolve_did( $did );

		// Get last operation.
		$last_op = $this->plc_client->get_last_operation( $did );

		// Generate new key pairs.
		$new_rotation_key     = DidCodec::generate_key_pair();
		$new_verification_key = DidCodec::generate_ed25519_key_pair();

		// Generate a unique ID for the verification key.
		$key_id = substr( hash( 'sha256', $new_verification_key->encode_public() ), 0, 6 );

		// Build rotation operation.
		$operation = new PlcOperation(
			type: 'plc_operation',
			rotation_keys: [ $new_rotation_key ],
			verification_methods: [ 'fair_' . $key_id => $new_verification_key ],
			also_known_as: $current_doc['alsoKnownAs'] ?? [],
			services: $current_doc['services'] ?? [],
			prev: $last_op['cid'] ?? null,
		);

		// Sign with CURRENT rotation key.
		$signed_operation = $operation->sign( $current_rotation_key );

		// Submit to PLC.
		$this->plc_client->update_did( $did, (array) $signed_operation->jsonSerialize() );

		// Update local store with new keys.
		$this->key_store->update_keys(
			$did,
			$new_rotation_key->encode_private(),
			$new_rotation_key->encode_public(),
			$new_verification_key->encode_private(),
			$new_verification_key->encode_public()
		);

		// Update metadata with rotation info.
		$this->key_store->update_metadata(
			$did,
			[
				'lastRotation'   => gmdate( 'c' ),
				'rotationReason' => $reason,
			]
		);

		return [
			'did'             => $did,
			'rotationKey'     => $new_rotation_key->encode_public(),
			'verificationKey' => $new_verification_key->encode_public(),
			'rotatedAt'       => gmdate( 'c' ),
			'reason'          => $reason,
		];
	}

	/**
	 * Deactivate a DID.
	 *
	 * @param string $did The DID to deactivate.
	 * @return bool True if successful.
	 * @throws \RuntimeException On failure.
	 */
	public function deactivate_did( string $did ): bool {
		// Get rotation key.
		$rotation_key_encoded = $this->key_store->get_rotation_key( $did );
		if ( null === $rotation_key_encoded ) {
			throw new \RuntimeException( "Rotation key not found for DID: {$did}" );
		}
		$rotation_key = KeyFactory::decode_private_key( $rotation_key_encoded );

		// Get last operation.
		$last_op = $this->plc_client->get_last_operation( $did );

		// Build deactivation operation as a tombstone.
		// Note: Tombstone operations are not full PlcOperations, so we handle them specially.
		$tombstone_data = [
			'type' => 'plc_tombstone',
			'prev' => $last_op['cid'] ?? null,
		];

		// Sign the tombstone.
		$canonical  = json_encode( $tombstone_data, JSON_UNESCAPED_SLASHES );
		$signature  = $rotation_key->sign( hash( 'sha256', $canonical, false ) );
		$sig_string = PlcOperation::base64url_encode( hex2bin( $signature ) );

		$tombstone_data['sig'] = $sig_string;

		try {
			$this->plc_client->update_did( $did, $tombstone_data );
		} catch ( \RuntimeException $e ) {
			// If tombstone not supported, try soft deactivation with empty keys.
			// This creates an operation with no rotation keys, effectively deactivating.
			$soft_operation = new PlcOperation(
				type: 'plc_operation',
				rotation_keys: [],
				verification_methods: [],
				also_known_as: [],
				services: [],
				prev: $last_op['cid'] ?? null,
			);

			$signed_soft_op = $soft_operation->sign( $rotation_key );
			$this->plc_client->update_did( $did, (array) $signed_soft_op->jsonSerialize() );
		}

		// Mark as deactivated locally.
		$this->key_store->deactivate( $did );

		return true;
	}

	/**
	 * List locally stored DIDs.
	 *
	 * @return array List of DID entries.
	 */
	public function list_local_dids(): array {
		return $this->key_store->list_dids();
	}

	/**
	 * Detect package type from path.
	 *
	 * @param string $path Path to package.
	 * @return string|null Type (plugin, theme, or null).
	 */
	private function detect_package_type( string $path ): ?string {
		// Check for theme.
		if ( file_exists( $path . '/style.css' ) ) {
			$content = file_get_contents( $path . '/style.css', false, null, 0, 8192 );
			if ( preg_match( '/Theme Name:/i', $content ) ) {
				return 'theme';
			}
		}

		// Check for plugin.
		$files = glob( $path . '/*.php' );
		foreach ( $files as $file ) {
			$content = file_get_contents( $file, false, null, 0, 8192 );
			if ( preg_match( '/Plugin Name:/i', $content ) ) {
				return 'plugin';
			}
		}

		return null;
	}

	/**
	 * Inject Plugin ID into plugin header.
	 *
	 * @param string $path Path to plugin.
	 * @param string $did The DID to inject.
	 * @throws \RuntimeException On failure.
	 */
	private function inject_plugin_id( string $path, string $did ): void {
		// Find the main plugin file.
		$files     = glob( $path . '/*.php' );
		$main_file = null;

		foreach ( $files as $file ) {
			$content = file_get_contents( $file, false, null, 0, 8192 );
			if ( preg_match( '/Plugin Name:/i', $content ) ) {
				$main_file = $file;
				break;
			}
		}

		if ( null === $main_file ) {
			throw new \RuntimeException( "Could not find main plugin file in: {$path}" );
		}

		$content = file_get_contents( $main_file );

		// Check if Plugin ID already exists.
		if ( preg_match( '/Plugin ID:/i', $content ) ) {
			// Update existing.
			$content = preg_replace(
				'/(\*\s*Plugin ID:\s*).*/i',
				'$1' . $did,
				$content
			);
		} else {
			// Add after Plugin Name.
			$content = preg_replace(
				'/(\*\s*Plugin Name:\s*[^\n]+)/i',
				"$1\n * Plugin ID: {$did}",
				$content
			);
		}

		if ( false === file_put_contents( $main_file, $content ) ) {
			throw new \RuntimeException( "Failed to write to: {$main_file}" );
		}
	}
}
