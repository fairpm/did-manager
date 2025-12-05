<?php
/**
 * KeyStore - Local key and DID storage
 *
 * @package FairDidManager\Storage
 */

declare(strict_types=1);

namespace FairDidManager\Storage;

/**
 * KeyStore - Local key and DID storage.
 */
class KeyStore {

	/**
	 * Path to the keystore JSON file.
	 *
	 * @var string
	 */
	private string $store_path;

	/**
	 * Keystore data.
	 *
	 * @var array
	 */
	private array $data = [];

	/**
	 * Constructor.
	 *
	 * @param string $store_path Path to the keystore JSON file.
	 */
	public function __construct( string $store_path ) {
		$this->store_path = $store_path;
		$this->load();
	}

	/**
	 * Load data from the store file.
	 */
	private function load(): void {
		if ( file_exists( $this->store_path ) ) {
			$content    = file_get_contents( $this->store_path );
			$this->data = json_decode( $content, true ) ?? [];
		} else {
			$this->data = [ 'dids' => [] ];
		}
	}

	/**
	 * Save data to the store file.
	 *
	 * @throws \RuntimeException If unable to write.
	 */
	private function save(): void {
		$dir = dirname( $this->store_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir, 0600, true ) ) {
				throw new \RuntimeException( "Unable to create directory: {$dir}" );
			}
		}

		$json = json_encode( $this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === file_put_contents( $this->store_path, $json ) ) {
			throw new \RuntimeException( "Unable to write to keystore: {$this->store_path}" );
		}
	}

	/**
	 * Store a DID with its keys.
	 *
	 * @param string      $did The DID identifier.
	 * @param string      $rotation_key_pem PEM-encoded rotation private key.
	 * @param string      $rotation_key_multibase Multibase public key.
	 * @param string      $verification_key_pem PEM-encoded verification private key.
	 * @param string      $verification_key_multibase Multibase public key.
	 * @param string|null $type Optional type (plugin, theme, repo).
	 * @param array       $metadata Optional additional metadata.
	 */
	public function store_did(
		string $did,
		string $rotation_key_pem,
		string $rotation_key_multibase,
		string $verification_key_pem,
		string $verification_key_multibase,
		?string $type = null,
		array $metadata = []
	): void {
		$this->data['dids'][ $did ] = [
			'did'             => $did,
			'rotationKey'     => [
				'private' => $rotation_key_pem,
				'public'  => $rotation_key_multibase,
			],
			'verificationKey' => [
				'private' => $verification_key_pem,
				'public'  => $verification_key_multibase,
			],
			'type'            => $type,
			'active'          => true,
			'createdAt'       => gmdate( 'c' ),
			'updatedAt'       => gmdate( 'c' ),
			'metadata'        => $metadata,
		];

		$this->save();
	}

	/**
	 * Get a DID entry.
	 *
	 * @param string $did The DID to retrieve.
	 * @return array|null The DID entry or null.
	 */
	public function get_did( string $did ): ?array {
		return $this->data['dids'][ $did ] ?? null;
	}

	/**
	 * Get the rotation private key for a DID.
	 *
	 * @param string $did The DID.
	 * @return string|null The PEM-encoded private key.
	 */
	public function get_rotation_key( string $did ): ?string {
		$entry = $this->get_did( $did );
		return $entry['rotationKey']['private'] ?? null;
	}

	/**
	 * Get the verification private key for a DID.
	 *
	 * @param string $did The DID.
	 * @return string|null The PEM-encoded private key.
	 */
	public function get_verification_key( string $did ): ?string {
		$entry = $this->get_did( $did );
		return $entry['verificationKey']['private'] ?? null;
	}

	/**
	 * Update keys for a DID (used during rotation).
	 *
	 * @param string $did The DID.
	 * @param string $rotation_key_pem New rotation private key.
	 * @param string $rotation_key_multibase New rotation public key.
	 * @param string $verification_key_pem New verification private key.
	 * @param string $verification_key_multibase New verification public key.
	 * @throws \RuntimeException If DID not found.
	 */
	public function update_keys(
		string $did,
		string $rotation_key_pem,
		string $rotation_key_multibase,
		string $verification_key_pem,
		string $verification_key_multibase
	): void {
		if ( ! isset( $this->data['dids'][ $did ] ) ) {
			throw new \RuntimeException( "DID not found in keystore: {$did}" );
		}

		$this->data['dids'][ $did ]['rotationKey']     = [
			'private' => $rotation_key_pem,
			'public'  => $rotation_key_multibase,
		];
		$this->data['dids'][ $did ]['verificationKey'] = [
			'private' => $verification_key_pem,
			'public'  => $verification_key_multibase,
		];
		$this->data['dids'][ $did ]['updatedAt']       = gmdate( 'c' );

		$this->save();
	}

	/**
	 * Mark a DID as deactivated.
	 *
	 * @param string $did The DID to deactivate.
	 * @throws \RuntimeException If DID not found.
	 */
	public function deactivate( string $did ): void {
		if ( ! isset( $this->data['dids'][ $did ] ) ) {
			throw new \RuntimeException( "DID not found in keystore: {$did}" );
		}

		$this->data['dids'][ $did ]['active']        = false;
		$this->data['dids'][ $did ]['deactivatedAt'] = gmdate( 'c' );
		$this->data['dids'][ $did ]['updatedAt']     = gmdate( 'c' );

		$this->save();
	}

	/**
	 * List all DIDs.
	 *
	 * @return array List of DID entries (without private keys).
	 */
	public function list_dids(): array {
		$list = [];
		foreach ( $this->data['dids'] as $did => $entry ) {
			$list[] = [
				'did'       => $did,
				'type'      => $entry['type'] ?? null,
				'active'    => $entry['active'] ?? true,
				'createdAt' => $entry['createdAt'] ?? null,
				'updatedAt' => $entry['updatedAt'] ?? null,
			];
		}
		return $list;
	}

	/**
	 * Check if a DID exists.
	 *
	 * @param string $did The DID to check.
	 * @return bool True if exists.
	 */
	public function exists( string $did ): bool {
		return isset( $this->data['dids'][ $did ] );
	}

	/**
	 * Check if a DID is active.
	 *
	 * @param string $did The DID to check.
	 * @return bool True if active.
	 */
	public function is_active( string $did ): bool {
		$entry = $this->get_did( $did );
		return null !== $entry && ( $entry['active'] ?? true );
	}

	/**
	 * Update metadata for a DID.
	 *
	 * @param string $did The DID.
	 * @param array  $metadata Metadata to merge.
	 * @throws \RuntimeException If DID not found.
	 */
	public function update_metadata( string $did, array $metadata ): void {
		if ( ! isset( $this->data['dids'][ $did ] ) ) {
			throw new \RuntimeException( "DID not found in keystore: {$did}" );
		}

		$this->data['dids'][ $did ]['metadata']  = array_merge(
			$this->data['dids'][ $did ]['metadata'] ?? [],
			$metadata
		);
		$this->data['dids'][ $did ]['updatedAt'] = gmdate( 'c' );

		$this->save();
	}

	/**
	 * Delete a DID from the store.
	 *
	 * @param string $did The DID to delete.
	 * @return bool True if deleted.
	 */
	public function delete( string $did ): bool {
		if ( ! isset( $this->data['dids'][ $did ] ) ) {
			return false;
		}

		unset( $this->data['dids'][ $did ] );
		$this->save();
		return true;
	}
}
