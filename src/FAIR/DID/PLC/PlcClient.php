<?php
/**
 * PlcClient - Bluesky PLC Directory API Client
 *
 * @package FairDidManager\Plc
 */

declare(strict_types=1);

namespace FAIR\DID\PLC;

/**
 * PlcClient - Bluesky PLC Directory API Client.
 */
class PlcClient {

	/**
	 * PLC directory base URL.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * HTTP timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Constructor.
	 *
	 * @param string $base_url PLC directory base URL.
	 * @param int    $timeout HTTP timeout in seconds.
	 */
	public function __construct( string $base_url = 'https://plc.directory', int $timeout = 30 ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->timeout  = $timeout;
	}

	/**
	 * Create a new DID.
	 *
	 * @param array $signed_operation The signed PLC operation.
	 * @return array Response from PLC.
	 * @throws \RuntimeException On network or API error.
	 */
	public function create_did( array $signed_operation ): array {
		return $this->post( '/', $signed_operation );
	}

	/**
	 * Update an existing DID.
	 *
	 * @param string $did The DID to update.
	 * @param array  $signed_operation The signed update operation.
	 * @return array Response from PLC.
	 * @throws \RuntimeException On network or API error.
	 */
	public function update_did( string $did, array $signed_operation ): array {
		return $this->post( "/{$did}", $signed_operation );
	}

	/**
	 * Resolve a DID to its document.
	 *
	 * @param string $did The DID to resolve.
	 * @return array The DID document.
	 * @throws \RuntimeException On network or API error.
	 */
	public function resolve_did( string $did ): array {
		return $this->get( "/{$did}" );
	}

	/**
	 * Get the operation log for a DID.
	 *
	 * @param string $did The DID.
	 * @return array List of operations.
	 * @throws \RuntimeException On network or API error.
	 */
	public function get_operation_log( string $did ): array {
		return $this->get( "/{$did}/log" );
	}

	/**
	 * Get the audit log for a DID.
	 *
	 * @param string $did The DID.
	 * @return array Audit log entries.
	 * @throws \RuntimeException On network or API error.
	 */
	public function get_audit_log( string $did ): array {
		return $this->get( "/{$did}/log/audit" );
	}

	/**
	 * Get the last operation for a DID.
	 *
	 * @param string $did The DID.
	 * @return array|null The last operation or null.
	 * @throws \RuntimeException On network or API error.
	 */
	public function get_last_operation( string $did ): ?array {
		return $this->get( "/{$did}/log/last" );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $endpoint API endpoint.
	 * @return array Decoded JSON response.
	 * @throws \RuntimeException On error.
	 */
	private function get( string $endpoint ): array {
		$url = $this->base_url . $endpoint;

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			[
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => $this->timeout,
				CURLOPT_HTTPHEADER     => [
					'Accept: application/json',
				],
				CURLOPT_FOLLOWLOCATION => true,
			]
		);

		$response  = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		curl_close( $ch );

		if ( false === $response ) {
			throw new \RuntimeException( "HTTP request failed: {$error}" );
		}

		if ( $http_code >= 400 ) {
			$this->handle_error( $http_code, $response );
		}

		$decoded = json_decode( $response, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			throw new \RuntimeException( 'Invalid JSON response: ' . json_last_error_msg() );
		}

		return $decoded;
	}

	/**
	 * Perform a POST request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Data to send.
	 * @return array Decoded JSON response.
	 * @throws \RuntimeException On error.
	 */
	private function post( string $endpoint, array $data ): array {
		$url       = $this->base_url . $endpoint;
		$json_body = json_encode( $data, JSON_UNESCAPED_SLASHES );

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			[
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => $this->timeout,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json_body,
				CURLOPT_HTTPHEADER     => [
					'Content-Type: application/json',
					'Accept: application/json',
				],
				CURLOPT_FOLLOWLOCATION => true,
			]
		);

		$response  = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		curl_close( $ch );

		if ( false === $response ) {
			throw new \RuntimeException( "HTTP request failed: {$error}" );
		}

		if ( $http_code >= 400 ) {
			$this->handle_error( $http_code, $response );
		}

		// Some endpoints return empty 200/201 responses.
		if ( empty( $response ) ) {
			return [
				'success'   => true,
				'http_code' => $http_code,
			];
		}

		$decoded = json_decode( $response, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			throw new \RuntimeException( 'Invalid JSON response: ' . json_last_error_msg() );
		}

		return $decoded;
	}

	/**
	 * Handle API errors.
	 *
	 * @param int    $http_code HTTP status code.
	 * @param string $response Response body.
	 * @throws \RuntimeException Always throws.
	 */
	private function handle_error( int $http_code, string $response ): void {
		$decoded = json_decode( $response, true );
		$message = $decoded['error'] ?? $decoded['message'] ?? $response;

		$error_messages = [
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			409 => 'Conflict',
			422 => 'Unprocessable Entity',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
		];

		$status_message = $error_messages[ $http_code ] ?? 'Unknown Error';

		throw new \RuntimeException( "PLC API Error ({$http_code} {$status_message}): {$message}" );
	}

	/**
	 * Check if PLC directory is reachable.
	 *
	 * @return bool True if reachable.
	 */
	public function is_reachable(): bool {
		try {
			$ch = curl_init();
			curl_setopt_array(
				$ch,
				[
					CURLOPT_URL            => $this->base_url . '/',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 5,
					CURLOPT_NOBODY         => true,
				]
			);
			curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			return $http_code < 500;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get the base URL.
	 *
	 * @return string Base URL.
	 */
	public function get_base_url(): string {
		return $this->base_url;
	}
}
