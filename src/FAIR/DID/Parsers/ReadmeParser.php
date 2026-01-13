<?php

/**
 * ReadmeParser - WordPress readme.txt parsing
 *
 * Wraps the WordPress.org Plugin Directory readme parser from afragen/wordpress-plugin-readme-parser.
 *
 * @package FairDidManager\Parsers
 */

declare(strict_types=1);

namespace FAIR\DID\Parsers;

// Include WordPress function stubs for standalone usage
require_once __DIR__ . '/wordpress-stubs.php';

use WordPressdotorg\Plugin_Directory\Readme\Parser;

/**
 * ReadmeParser - WordPress readme.txt parsing.
 *
 * This class wraps the official WordPress.org Plugin Directory readme parser
 * and provides a consistent interface for parsing WordPress readme.txt files.
 */
class ReadmeParser
{
    /**
     * The underlying WordPress.org parser instance.
     *
     * @var Parser|null
     */
    private ?Parser $parser = null;

    /**
     * Parse readme.txt from a directory.
     *
     * @param string $path Path to plugin/theme directory.
     * @return array Parsed readme data.
     */
    public function parse(string $path): array
    {
        $readme_path = $this->find_readme($path);
        if (null === $readme_path) {
            return [];
        }

        return $this->parse_file($readme_path);
    }

    /**
     * Parse a readme file.
     *
     * @param string $file_path Path to readme.txt.
     * @return array Parsed data.
     */
    public function parse_file(string $file_path): array
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return [];
        }

        // The WordPress.org parser accepts a file path directly
        $this->parser = new Parser($file_path);

        return $this->normalize_parser_output();
    }

    /**
     * Parse readme content.
     *
     * @param string $content Readme content.
     * @return array Parsed data.
     */
    public function parse_content(string $content): array
    {
        if (empty(trim($content))) {
            return [
                'name' => null,
                'header' => [],
                'short_description' => null,
                'sections' => [],
            ];
        }

        // The WordPress.org parser accepts content directly if it contains newlines
        $this->parser = new Parser($content);

        return $this->normalize_parser_output();
    }

    /**
     * Normalize the WordPress.org parser output to our expected format.
     *
     * @return array Normalized data.
     */
    private function normalize_parser_output(): array
    {
        if (null === $this->parser) {
            return [
                'name' => null,
                'header' => [],
                'short_description' => null,
                'sections' => [],
            ];
        }

        $result = [
            'name' => $this->parser->name ?: null,
            'header' => [],
            'short_description' => $this->parser->short_description ?: null,
            'sections' => [],
        ];

        // Map header fields
        if (!empty($this->parser->contributors)) {
            $result['header']['contributors'] = $this->parser->contributors;
        }

        if (!empty($this->parser->tags)) {
            $result['header']['tags'] = $this->parser->tags;
        }

        if (!empty($this->parser->requires)) {
            $result['header']['requires_at_least'] = $this->parser->requires;
        }

        if (!empty($this->parser->tested)) {
            $result['header']['tested_up_to'] = $this->parser->tested;
        }

        if (!empty($this->parser->requires_php)) {
            $result['header']['requires_php'] = $this->parser->requires_php;
        }

        if (!empty($this->parser->stable_tag)) {
            $result['header']['stable_tag'] = $this->parser->stable_tag;
        }

        if (!empty($this->parser->license)) {
            $result['header']['license'] = $this->parser->license;
        }

        if (!empty($this->parser->license_uri)) {
            $result['header']['license_uri'] = $this->parser->license_uri;
        }

        if (!empty($this->parser->donate_link)) {
            $result['header']['donate_link'] = $this->parser->donate_link;
        }

        // Map sections - convert keys to lowercase with underscores
        if (!empty($this->parser->sections)) {
            foreach ($this->parser->sections as $name => $content) {
                $normalized_name = $this->normalize_key($name);
                // Strip HTML tags from section content for consistency
                $result['sections'][$normalized_name] = strip_tags($content);
            }
        }

        return $result;
    }

    /**
     * Find readme.txt in a directory.
     *
     * @param string $path Directory path.
     * @return string|null Path to readme or null.
     */
    public function find_readme(string $path): ?string
    {
        if (!is_dir($path)) {
            // Maybe it's a file path.
            if (is_file($path) && $this->is_readme_file(basename($path))) {
                return $path;
            }
            return null;
        }

        $path = rtrim($path, '/\\');

        // Check for common readme filenames.
        $readme_names = [
            'readme.txt',
            'README.txt',
            'Readme.txt',
            'README.md',
            'readme.md',
        ];

        foreach ($readme_names as $name) {
            $full_path = $path . DIRECTORY_SEPARATOR . $name;
            if (file_exists($full_path)) {
                return $full_path;
            }
        }

        return null;
    }

    /**
     * Check if filename is a readme file.
     *
     * @param string $filename Filename to check.
     * @return bool True if readme file.
     */
    private function is_readme_file(string $filename): bool
    {
        $lower = strtolower($filename);
        return in_array($lower, ['readme.txt', 'readme.md'], true);
    }

    /**
     * Normalize a key to snake_case.
     *
     * @param string $key Original key.
     * @return string Normalized key.
     */
    private function normalize_key(string $key): string
    {
        $normalized = strtolower($key);
        $normalized = str_replace([' ', '-'], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);

        // Handle special cases.
        $aliases = [
            'frequently_asked_questions' => 'faq',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * Parse changelog section into structured entries.
     *
     * @param string $changelog Raw changelog content.
     * @return array Structured changelog entries.
     */
    public function parse_changelog(string $changelog): array
    {
        $entries = [];
        $current_version = null;
        $current_changes = [];

        $lines = explode("\n", $changelog);
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Match version header: = 1.0.0 = or = 1.0.0 - 2024-01-01 = or just version numbers
            if (preg_match('/^=?\s*([\d.]+(?:\s*-\s*[\d-]+)?)\s*=?/', $trimmed, $matches)) {
                // Save previous version.
                if (null !== $current_version) {
                    $entries[$current_version] = $current_changes;
                }
                $current_version = trim($matches[1]);
                $current_changes = [];
            } elseif (null !== $current_version && '' !== $trimmed) {
                // Parse bullet point.
                if (preg_match('/^[\*\-]\s*(.+)$/', $trimmed, $matches)) {
                    $current_changes[] = $matches[1];
                } elseif (!preg_match('/^=/', $trimmed)) {
                    // Continuation of previous item.
                    if (!empty($current_changes)) {
                        $last_key = count($current_changes) - 1;
                        $current_changes[$last_key] .= ' ' . $trimmed;
                    }
                }
            }
        }

        // Save last version.
        if (null !== $current_version) {
            $entries[$current_version] = $current_changes;
        }

        return $entries;
    }

    /**
     * Parse FAQ section into Q&A pairs.
     *
     * @param string $faq Raw FAQ content.
     * @return array Array of ['question' => ..., 'answer' => ...].
     */
    public function parse_faq(string $faq): array
    {
        $entries = [];
        $current_question = null;
        $current_answer = [];

        $lines = explode("\n", $faq);
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Match question: = Question here = or just text followed by content
            if (preg_match('/^=\s*(.+?)\s*=\s*$/', $trimmed, $matches)) {
                // Save previous Q&A.
                if (null !== $current_question) {
                    $entries[] = [
                        'question' => $current_question,
                        'answer' => trim(implode("\n", $current_answer)),
                    ];
                }
                $current_question = $matches[1];
                $current_answer = [];
            } elseif (null !== $current_question) {
                $current_answer[] = $line;
            }
        }

        // Save last Q&A.
        if (null !== $current_question) {
            $entries[] = [
                'question' => $current_question,
                'answer' => trim(implode("\n", $current_answer)),
            ];
        }

        return $entries;
    }

    /**
     * Check if directory has a valid readme.
     *
     * @param string $path Directory path.
     * @return bool True if valid readme exists.
     */
    public function has_valid_readme(string $path): bool
    {
        $readme = $this->find_readme($path);
        if (null === $readme) {
            return false;
        }

        $parsed = $this->parse_file($readme);
        return !empty($parsed['name']) || !empty($parsed['header']);
    }

    /**
     * Get the underlying parser instance.
     *
     * Useful for accessing additional properties not exposed through the normalized interface.
     *
     * @return Parser|null The parser instance or null if not yet parsed.
     */
    public function get_parser(): ?Parser
    {
        return $this->parser;
    }

    /**
     * Get parser warnings.
     *
     * @return array Array of warning flags from the parser.
     */
    public function get_warnings(): array
    {
        if (null === $this->parser) {
            return [];
        }

        return $this->parser->warnings ?? [];
    }

    /**
     * Get screenshots data.
     *
     * @return array Array of screenshot descriptions.
     */
    public function get_screenshots(): array
    {
        if (null === $this->parser) {
            return [];
        }

        return $this->parser->screenshots ?? [];
    }

    /**
     * Get upgrade notices.
     *
     * @return array Array of version => upgrade notice.
     */
    public function get_upgrade_notices(): array
    {
        if (null === $this->parser) {
            return [];
        }

        return $this->parser->upgrade_notice ?? [];
    }

    /**
     * Get parsed FAQ data.
     *
     * @return array Array of question => answer pairs.
     */
    public function get_faq(): array
    {
        if (null === $this->parser) {
            return [];
        }

        return $this->parser->faq ?? [];
    }
}
