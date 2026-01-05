<?php

/**
 * Key Exporter class for exporting keys in various formats.
 *
 * @package FairDidManager\Keys
 */

declare(strict_types=1);

namespace FAIR\DID\Keys;

use Exception;

/**
 * Key Exporter class for exporting cryptographic keys.
 *
 * Provides methods to export keys in JSON, text, and environment variable formats.
 *
 * @package FairDidManager\Keys
 */
class KeyExporter
{
    /**
     * The key to export.
     *
     * @var Key
     */
    protected Key $key;

    /**
     * The key type name (e.g., 'EC', 'EdDSA').
     *
     * @var string
     */
    protected string $type_name;

    /**
     * The environment variable prefix (e.g., 'EC', 'EDDSA').
     *
     * @var string
     */
    protected string $env_prefix;

    /**
     * Constructor.
     *
     * @param Key    $key        The key to export.
     * @param string $type_name  The key type name for display.
     * @param string $env_prefix The environment variable prefix.
     */
    public function __construct(Key $key, string $type_name, string $env_prefix)
    {
        $this->key = $key;
        $this->type_name = $type_name;
        $this->env_prefix = $env_prefix;
    }

    /**
     * Create an exporter for an EC key.
     *
     * @param Key $key The EC key.
     * @return self The exporter instance.
     */
    public static function for_ec(Key $key): self
    {
        return new self($key, 'EC', 'EC');
    }

    /**
     * Create an exporter for an EdDSA key.
     *
     * @param Key $key The EdDSA key.
     * @return self The exporter instance.
     */
    public static function for_eddsa(Key $key): self
    {
        return new self($key, 'EdDSA', 'EDDSA');
    }

    /**
     * Convert key data to an array.
     *
     * @param bool $include_private Whether to include the private key.
     * @return array<string, mixed> The key data array.
     */
    public function to_array(bool $include_private = false): array
    {
        $data = [
            'type' => $this->type_name,
            'curve' => $this->key->get_curve(),
            'public_key' => $this->key->encode_public(),
            'did_key' => 'did:key:' . $this->key->encode_public(),
            'created_at' => gmdate('c'),
        ];

        if ($include_private && $this->key->is_private()) {
            $data['private_key'] = $this->key->encode_private();
        }

        return $data;
    }

    /**
     * Convert key data to a JSON string.
     *
     * @param bool $include_private Whether to include the private key.
     * @param int  $flags           JSON encoding flags.
     * @return string The JSON string.
     */
    public function to_json(bool $include_private = false, int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->to_array($include_private), $flags);
    }

    /**
     * Export key to output or file.
     *
     * @param string|null $file_path       Path to write to, or null for stdout.
     * @param bool        $include_private Whether to include the private key.
     * @param string      $format          Output format: 'json', 'text', or 'env'.
     * @return bool|string True if written to file, or the output string if no file path.
     * @throws Exception If format is invalid.
     */
    public function export(
        ?string $file_path = null,
        bool $include_private = false,
        string $format = 'json',
    ): bool|string {
        $output = $this->format_output($include_private, $format);

        if (null !== $file_path) {
            $dir = dirname($file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            if (file_put_contents($file_path, $output) === false) {
                return false;
            }
            chmod($file_path, 0600);
            return true;
        }

        return $output;
    }

    /**
     * Format output based on format type.
     *
     * @param bool   $include_private Whether to include the private key.
     * @param string $format          Output format: 'json', 'text', or 'env'.
     * @return string The formatted output.
     * @throws Exception If format is invalid.
     */
    protected function format_output(bool $include_private, string $format): string
    {
        return match ($format) {
            'json' => $this->to_json($include_private),
            'text' => $this->format_as_text($include_private),
            'env' => $this->format_as_env($include_private),
            default => throw new Exception("Invalid format: {$format}. Use 'json', 'text', or 'env'."),
        };
    }

    /**
     * Format key as human-readable text.
     *
     * @param bool $include_private Whether to include the private key.
     * @return string The text output.
     */
    protected function format_as_text(bool $include_private): string
    {
        $lines = [];
        $lines[] = '=== ' . $this->type_name . ' Key (' . $this->key->get_curve() . ') ===';
        $lines[] = 'Public Key:  ' . $this->key->encode_public();
        $lines[] = 'DID Key:     did:key:' . $this->key->encode_public();

        if ($include_private && $this->key->is_private()) {
            $lines[] = 'Private Key: ' . $this->key->encode_private();
        }

        $lines[] = 'Created:     ' . gmdate('c');
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * Format key as environment variables.
     *
     * @param bool $include_private Whether to include the private key.
     * @return string The env output.
     */
    protected function format_as_env(bool $include_private): string
    {
        $prefix = $this->env_prefix . '_' . strtoupper($this->key->get_curve()) . '_';
        $lines = [];
        $lines[] = $prefix . 'PUBLIC_KEY="' . $this->key->encode_public() . '"';
        $lines[] = $prefix . 'DID_KEY="did:key:' . $this->key->encode_public() . '"';

        if ($include_private && $this->key->is_private()) {
            $lines[] = $prefix . 'PRIVATE_KEY="' . $this->key->encode_private() . '"';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
