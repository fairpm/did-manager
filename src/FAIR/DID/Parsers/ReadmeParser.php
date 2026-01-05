<?php

/**
 * ReadmeParser - WordPress readme.txt parsing
 *
 * Parses WordPress.org style readme.txt files.
 *
 * @package FairDidManager\Parsers
 */

declare(strict_types=1);

namespace FAIR\DID\Parsers;

/**
 * ReadmeParser - WordPress readme.txt parsing.
 */
class ReadmeParser
{
    /**
     * Standard readme header fields
     */
    private const HEADER_FIELDS = [
        'Contributors',
        'Tags',
        'Requires at least',
        'Tested up to',
        'Requires PHP',
        'Stable tag',
        'License',
        'License URI',
        'Donate link',
    ];

    /**
     * Standard section names
     */
    private const SECTIONS = [
        'Description',
        'Installation',
        'FAQ',
        'Frequently Asked Questions',
        'Screenshots',
        'Changelog',
        'Upgrade Notice',
        'Other Notes',
        'Arbitrary section',
    ];

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

        $content = file_get_contents($file_path);
        if (false === $content) {
            return [];
        }

        return $this->parse_content($content);
    }

    /**
     * Parse readme content.
     *
     * @param string $content Readme content.
     * @return array Parsed data.
     */
    public function parse_content(string $content): array
    {
        // Normalize line endings.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $result = [
            'name' => null,
            'header' => [],
            'short_description' => null,
            'sections' => [],
        ];

        $lines = explode("\n", $content);
        $current_section = null;
        $section_content = [];
        $in_header = true;
        $found_first_section = false;
        $short_desc_lines = [];

        foreach ($lines as $line_num => $line) {
            $trimmed_line = trim($line);

            // Parse plugin/theme name from first line === Name ===.
            if (null === $result['name'] && preg_match('/^===\s*(.+?)\s*===\s*$/', $trimmed_line, $matches)) {
                $result['name'] = $matches[1];
                continue;
            }

            // Check for section header == Section Name ==.
            if (preg_match('/^==\s*(.+?)\s*==\s*$/', $trimmed_line, $matches)) {
                // Save previous section if exists.
                if (null !== $current_section) {
                    $result['sections'][$current_section] = $this->process_section($section_content);
                }

                $current_section = $this->normalize_key($matches[1]);
                $section_content = [];
                $in_header = false;
                $found_first_section = true;
                continue;
            }

            // Parse header fields (before first section).
            if ($in_header && !$found_first_section) {
                if (preg_match('/^([^:]+):\s*(.*)$/', $trimmed_line, $matches)) {
                    $key = trim($matches[1]);
                    $value = trim($matches[2]);

                    foreach (self::HEADER_FIELDS as $field) {
                        if (0 === strcasecmp($key, $field)) {
                            $normalized_key = $this->normalize_key($field);
                            $result['header'][$normalized_key] = $this->parse_header_value($field, $value);
                            break;
                        }
                    }
                } elseif (null !== $result['name'] && '' !== $trimmed_line && !$found_first_section) {
                    // Lines after header but before first section are short description.
                    $short_desc_lines[] = $trimmed_line;
                }
            } elseif (null !== $current_section) {
                // Accumulate section content.
                $section_content[] = $line;
            }
        }

        // Save last section.
        if (null !== $current_section) {
            $result['sections'][$current_section] = $this->process_section($section_content);
        }

        // Set short description.
        if (!empty($short_desc_lines)) {
            $result['short_description'] = implode(' ', $short_desc_lines);
            // Limit to first ~150 characters as per WordPress.org spec.
            $desc_length = strlen($result['short_description']);
            if ($desc_length > 150) {
                $result['short_description'] = substr($result['short_description'], 0, 150);
                // Try to cut at last word boundary.
                $last_space = strrpos($result['short_description'], ' ');
                if (false !== $last_space && $last_space > 100) {
                    $result['short_description'] = substr($result['short_description'], 0, $last_space);
                }
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
     * Parse a header value.
     *
     * @param string $field Field name.
     * @param string $value Raw value.
     * @return mixed Parsed value.
     */
    private function parse_header_value(string $field, string $value): mixed
    {
        $lower = strtolower($field);

        // Handle comma-separated fields.
        if (in_array($lower, ['contributors', 'tags'], true)) {
            $items = explode(',', $value);
            return array_values(array_filter(array_map('trim', $items)));
        }

        // Handle version fields.
        if (in_array($lower, ['requires at least', 'tested up to', 'requires php', 'stable tag'], true)) {
            return ltrim(trim($value), 'vV');
        }

        return $value;
    }

    /**
     * Process section content.
     *
     * @param array $lines Section lines.
     * @return string Processed content.
     */
    private function process_section(array $lines): string
    {
        // Remove leading/trailing empty lines.
        while (!empty($lines) && '' === trim($lines[0])) {
            array_shift($lines);
        }
        while (!empty($lines) && '' === trim(end($lines))) {
            array_pop($lines);
        }

        return implode("\n", $lines);
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

            // Match version header: = 1.0.0 = or = 1.0.0 - 2024-01-01 =.
            if (preg_match('/^=\s*([\d.]+(?:\s*-\s*[\d-]+)?)\s*=/', $trimmed, $matches)) {
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

            // Match question: = Question here =.
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
}
