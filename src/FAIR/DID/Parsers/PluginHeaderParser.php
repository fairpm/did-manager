<?php

/**
 * PluginHeaderParser - WordPress plugin header parsing
 *
 * Parses the plugin header comment block from WordPress plugin files.
 * Follows WordPress.org specifications for plugin headers.
 *
 * @package FairDidManager\Parsers
 */

declare(strict_types=1);

namespace FAIR\DID\Parsers;

/**
 * PluginHeaderParser - WordPress plugin header parsing.
 */
class PluginHeaderParser
{
    /**
     * Standard WordPress plugin header fields
     */
    private const STANDARD_FIELDS = [
        'Plugin Name',
        'Plugin URI',
        'Description',
        'Version',
        'Author',
        'Author URI',
        'Text Domain',
        'Domain Path',
        'Network',
        'Requires at least',
        'Requires PHP',
        'License',
        'License URI',
        'Update URI',
        'Tags',
        'Security',
    ];

    /**
     * FAIR-specific header fields
     */
    private const FAIR_FIELDS = [
        'Plugin ID', // DID.
        'Theme ID', // DID for themes.
    ];

    /**
     * Maximum bytes to read from file
     */
    private const MAX_HEADER_SIZE = 8192;

    /**
     * Parse plugin headers from a directory.
     *
     * @param string $path Path to plugin directory.
     * @return array Parsed header data.
     * @throws \RuntimeException If no plugin file found.
     */
    public function parse(string $path): array
    {
        $main_file = $this->find_main_file($path);
        if (null === $main_file) {
            return [];
        }

        return $this->parse_file($main_file);
    }

    /**
     * Parse headers from a specific file.
     *
     * @param string $file_path Path to PHP file.
     * @return array Parsed header data.
     */
    public function parse_file(string $file_path): array
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return [];
        }

        $content = file_get_contents($file_path, false, null, 0, self::MAX_HEADER_SIZE);
        if (false === $content) {
            return [];
        }

        return $this->parse_content($content);
    }

    /**
     * Parse headers from content string.
     *
     * @param string $content File content.
     * @return array Parsed header data.
     */
    public function parse_content(string $content): array
    {
        // Find the plugin header comment block.
        if (!preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            // Try alternate comment style.
            if (!preg_match('/\/\*(.*?)\*\//s', $content, $matches)) {
                return [];
            }
        }

        $header_block = $matches[1];
        $headers = [];
        $all_fields = array_merge(self::STANDARD_FIELDS, self::FAIR_FIELDS);

        // Parse each line for "Key: Value" patterns.
        $lines = explode("\n", $header_block);
        foreach ($lines as $line) {
            // Remove comment markers and trim.
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Match "Key: Value" pattern.
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);

                // Check if this is a recognized field.
                foreach ($all_fields as $field) {
                    if (0 === strcasecmp($key, $field)) {
                        $normalized_key = $this->normalize_key($field);
                        $headers[$normalized_key] = $this->parse_value($field, $value);
                        break;
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * Find the main plugin file in a directory.
     *
     * @param string $path Plugin directory path.
     * @return string|null Path to main file or null.
     */
    public function find_main_file(string $path): ?string
    {
        if (!is_dir($path)) {
            // Maybe it's a file path.
            if (is_file($path) && str_ends_with($path, '.php')) {
                return $path;
            }
            return null;
        }

        $path = rtrim($path, '/\\');

        // First, check for a file with same name as directory.
        $dir_name = basename($path);
        $expected_file = $path . DIRECTORY_SEPARATOR . $dir_name . '.php';
        if (file_exists($expected_file)) {
            $content = file_get_contents($expected_file, false, null, 0, self::MAX_HEADER_SIZE);
            if ($content && preg_match('/Plugin Name:/i', $content)) {
                return $expected_file;
            }
        }

        // Search all PHP files in the directory root.
        $php_files = glob($path . DIRECTORY_SEPARATOR . '*.php');
        if (false === $php_files) {
            return null;
        }

        foreach ($php_files as $file) {
            $content = file_get_contents($file, false, null, 0, self::MAX_HEADER_SIZE);
            if ($content && preg_match('/Plugin Name:/i', $content)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Normalize a header key to snake_case.
     *
     * @param string $key Original key.
     * @return string Normalized key.
     */
    private function normalize_key(string $key): string
    {
        // Convert to lowercase and replace spaces with underscores.
        $normalized = strtolower($key);
        $normalized = str_replace(' ', '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);

        return $normalized;
    }

    /**
     * Parse a value based on field type.
     *
     * @param string $field Field name.
     * @param string $value Raw value.
     * @return mixed Parsed value.
     */
    private function parse_value(string $field, string $value): mixed
    {
        // Handle Tags (comma-separated list).
        if (0 === strcasecmp($field, 'Tags')) {
            return $this->parse_tags($value);
        }

        // Handle Network (boolean).
        if (0 === strcasecmp($field, 'Network')) {
            return 'true' === strtolower($value) || '1' === $value;
        }

        // Handle Requires at least (version string).
        if (0 === strcasecmp($field, 'Requires at least') || 0 === strcasecmp($field, 'Requires PHP')) {
            return $this->normalize_version($value);
        }

        return $value;
    }

    /**
     * Parse comma-separated tags.
     *
     * @param string $value Tags string.
     * @return array Array of tags.
     */
    private function parse_tags(string $value): array
    {
        $tags = explode(',', $value);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($tag) => '' !== $tag);
        return array_values($tags);
    }

    /**
     * Normalize a version string.
     *
     * @param string $version Raw version.
     * @return string Normalized version.
     */
    private function normalize_version(string $version): string
    {
        // Remove leading 'v' or 'V'.
        $version = ltrim($version, 'vV');

        // Trim whitespace.
        return trim($version);
    }

    /**
     * Check if a file/directory contains a valid plugin.
     *
     * @param string $path Path to check.
     * @return bool True if valid plugin.
     */
    public function is_valid_plugin(string $path): bool
    {
        $main_file = $this->find_main_file($path);
        if (null === $main_file) {
            return false;
        }

        $headers = $this->parse_file($main_file);
        return isset($headers['plugin_name']) && !empty($headers['plugin_name']);
    }

    /**
     * Get the plugin slug from path or headers.
     *
     * @param string     $path Plugin path.
     * @param array|null $headers Optional pre-parsed headers.
     * @return string|null Plugin slug.
     */
    public function get_slug(string $path, ?array $headers = null): ?string
    {
        // Try to get from Text Domain.
        if (null === $headers) {
            $headers = $this->parse($path);
        }

        if (isset($headers['text_domain']) && !empty($headers['text_domain'])) {
            return $headers['text_domain'];
        }

        // Fall back to directory name.
        if (is_dir($path)) {
            return basename(rtrim($path, '/\\'));
        }

        // Fall back to filename without extension.
        if (is_file($path)) {
            return pathinfo($path, PATHINFO_FILENAME);
        }

        return null;
    }
}
