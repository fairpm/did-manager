<?php

/**
 * Plugin Header Parser Tests
 *
 * @package FairDidManager\Tests\Parsers
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Parsers;

use FAIR\DID\Parsers\PluginHeaderParser;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Plugin Header Parser
 */
class PluginHeaderParserTest extends TestCase
{
    /**
     * Parser instance.
     *
     * @var PluginHeaderParser
     */
    private PluginHeaderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PluginHeaderParser();
    }

    /**
     * Get a standard plugin header for testing
     */
    private function getStandardHeader(): string
    {
        return <<<'PHP'
        <?php
        /**
         * Plugin Name: My Test Plugin
         * Plugin URI: https://example.com/my-plugin
         * Description: A test plugin for unit testing.
         * Version: 1.2.3
         * Author: Test Author
         * Author URI: https://example.com
         * Text Domain: my-test-plugin
         * Requires at least: 5.8
         * Requires PHP: 7.4
         * License: GPL-2.0+
         * License URI: https://www.gnu.org/licenses/gpl-2.0.html
         * Tags: testing, example, demo
         * Plugin ID: did:plc:abc123
         */
        PHP;
    }

    /**
     * Test parsing plugin name
     */
    public function testParsePluginName(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('My Test Plugin', $result['plugin_name']);
    }

    /**
     * Test parsing plugin URI
     */
    public function testParsePluginUri(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('https://example.com/my-plugin', $result['plugin_uri']);
    }

    /**
     * Test parsing description
     */
    public function testParseDescription(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('A test plugin for unit testing.', $result['description']);
    }

    /**
     * Test parsing version
     */
    public function testParseVersion(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('1.2.3', $result['version']);
    }

    /**
     * Test parsing author
     */
    public function testParseAuthor(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('Test Author', $result['author']);
    }

    /**
     * Test parsing author URI
     */
    public function testParseAuthorUri(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('https://example.com', $result['author_uri']);
    }

    /**
     * Test parsing text domain
     */
    public function testParseTextDomain(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('my-test-plugin', $result['text_domain']);
    }

    /**
     * Test parsing requires at least
     */
    public function testParseRequiresAtLeast(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('5.8', $result['requires_at_least']);
    }

    /**
     * Test parsing requires PHP
     */
    public function testParseRequiresPhp(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('7.4', $result['requires_php']);
    }

    /**
     * Test parsing license
     */
    public function testParseLicense(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('GPL-2.0+', $result['license']);
    }

    /**
     * Test parsing plugin ID
     */
    public function testParsePluginId(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame('did:plc:abc123', $result['plugin_id']);
    }

    /**
     * Test parsing tags
     */
    public function testParseTags(): void
    {
        $result = $this->parser->parse_content($this->getStandardHeader());
        $this->assertSame(['testing', 'example', 'demo'], $result['tags']);
    }

    /**
     * Test parsing minimal header - plugin name
     */
    public function testParseminimal_headerPluginName(): void
    {
        $minimal_header = <<<'PHP'
        <?php
        /*
        Plugin Name: Minimal Plugin
        Version: 0.1
        */
        PHP;
        $result = $this->parser->parse_content($minimal_header);
        $this->assertSame('Minimal Plugin', $result['plugin_name']);
    }

    /**
     * Test parsing minimal header - version
     */
    public function testParseminimal_headerVersion(): void
    {
        $minimal_header = <<<'PHP'
        <?php
        /*
        Plugin Name: Minimal Plugin
        Version: 0.1
        */
        PHP;
        $result = $this->parser->parse_content($minimal_header);
        $this->assertSame('0.1', $result['version']);
    }

    /**
     * Test malformed header produces no false positives
     */
    public function testmalformed_headerNoFalsePositives(): void
    {
        $malformed_header = <<<'PHP'
        <?php
        /**
         * This is not a proper header
         * It has no Plugin Name field
         * Just some random : colons : here
         */
        PHP;
        $result = $this->parser->parse_content($malformed_header);
        $this->assertArrayNotHasKey('plugin_name', $result);
    }

    /**
     * Test version normalization strips v prefix
     */
    public function testVersionNormalizationStripsVPrefix(): void
    {
        $version_header = <<<'PHP'
        <?php
        /**
         * Plugin Name: Version Test
         * Requires at least: v5.0
         * Requires PHP: V8.0
         */
        PHP;
        $result = $this->parser->parse_content($version_header);
        $this->assertSame('5.0', $result['requires_at_least']);
    }

    /**
     * Test empty content returns empty array
     */
    public function testEmptyContentReturnsEmptyArray(): void
    {
        $result = $this->parser->parse_content('');
        $this->assertEmpty($result);
    }

    /**
     * Test no comment block returns empty array
     */
    public function testno_commentBlockReturnsEmptyArray(): void
    {
        $no_comment = "<?php\necho 'Hello World';";
        $result = $this->parser->parse_content($no_comment);
        $this->assertEmpty($result);
    }
}
