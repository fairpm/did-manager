<?php

/**
 * Readme Parser Tests
 *
 * @package FairDidManager\Tests\Parsers
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Parsers;

use FAIR\DID\Parsers\ReadmeParser;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Readme Parser
 */
class ReadmeParserTest extends TestCase
{
    /**
     * Parser instance.
     *
     * @var ReadmeParser
     */
    private ReadmeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ReadmeParser();
    }

    /**
     * Get a full readme for testing
     */
    private function get_full_readme(): string
    {
        return <<<'README'
        === My Test Plugin ===
        Contributors: author1, author2
        Tags: testing, example, demo
        Requires at least: 5.8
        Tested up to: 6.4
        Requires PHP: 7.4
        Stable tag: 1.2.3
        License: GPLv2 or later
        License URI: https://www.gnu.org/licenses/gpl-2.0.html

        This is the short description of the plugin.

        == Description ==

        This is the full description of the plugin.
        It can span multiple lines.

        == Installation ==

        1. Upload the plugin files.
        2. Activate the plugin.
        3. Configure settings.

        == FAQ ==

        = How do I use this? =

        Just activate and go!

        = Is it free? =

        Yes, it's free.

        == Changelog ==

        = 1.2.3 =
        * Fixed a bug
        * Added a feature

        = 1.2.2 =
        * Initial release
        README;
    }

    /**
     * Test parsing plugin name
     */
    public function testParsePluginName(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('My Test Plugin', $result['name']);
    }

    /**
     * Test parsing short description
     */
    public function testParseshort_description(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertStringContainsString('short description', $result['short_description']);
    }

    /**
     * Test parsing header contributors
     */
    public function testParseHeaderContributors(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame(['author1', 'author2'], $result['header']['contributors']);
    }

    /**
     * Test parsing header tags
     */
    public function testParseHeaderTags(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame(['testing', 'example', 'demo'], $result['header']['tags']);
    }

    /**
     * Test parsing requires at least
     */
    public function testParseRequiresAtLeast(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('5.8', $result['header']['requires_at_least']);
    }

    /**
     * Test parsing tested up to
     */
    public function testParseTestedUpTo(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('6.4', $result['header']['tested_up_to']);
    }

    /**
     * Test parsing requires PHP
     */
    public function testParseRequiresPhp(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('7.4', $result['header']['requires_php']);
    }

    /**
     * Test parsing stable tag
     */
    public function testParseStableTag(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('1.2.3', $result['header']['stable_tag']);
    }

    /**
     * Test parsing license
     */
    public function testParseLicense(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertSame('GPLv2 or later', $result['header']['license']);
    }

    /**
     * Test description section exists
     */
    public function testDescriptionSectionExists(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertArrayHasKey('description', $result['sections']);
    }

    /**
     * Test installation section exists
     */
    public function testInstallationSectionExists(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertArrayHasKey('installation', $result['sections']);
    }

    /**
     * Test FAQ section exists
     */
    public function testFaqSectionExists(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertArrayHasKey('faq', $result['sections']);
    }

    /**
     * Test changelog section exists
     */
    public function testchangelog_sectionExists(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $this->assertArrayHasKey('changelog', $result['sections']);
    }

    /**
     * Test minimal readme parsing
     */
    public function testParseminimal_readme(): void
    {
        $minimal_readme = <<<'README'
        === Simple Plugin ===

        Just a simple plugin.
        README;
        $result = $this->parser->parse_content($minimal_readme);
        $this->assertSame('Simple Plugin', $result['name']);
    }

    /**
     * Test changelog version 1.2.3 found
     */
    public function testChangelogVersion123Found(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $changelog = $result['sections']['changelog'] ?? '';
        $parsed_changelog = $this->parser->parse_changelog($changelog);
        $this->assertArrayHasKey('1.2.3', $parsed_changelog);
    }

    /**
     * Test changelog version 1.2.2 found
     */
    public function testChangelogVersion122Found(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $changelog = $result['sections']['changelog'] ?? '';
        $parsed_changelog = $this->parser->parse_changelog($changelog);
        $this->assertArrayHasKey('1.2.2', $parsed_changelog);
    }

    /**
     * Test two FAQ entries found
     */
    public function testTwoFaqEntriesFound(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $faq = $result['sections']['faq'] ?? '';
        $parsed_faq = $this->parser->parse_faq($faq);
        $this->assertCount(2, $parsed_faq);
    }

    /**
     * Test FAQ question parsed
     */
    public function testFaqQuestionParsed(): void
    {
        $result = $this->parser->parse_content($this->get_full_readme());
        $faq = $result['sections']['faq'] ?? '';
        $parsed_faq = $this->parser->parse_faq($faq);
        $this->assertStringContainsString('How do I use', $parsed_faq[0]['question']);
    }

    /**
     * Test empty content handled
     */
    public function testEmptyContentHandled(): void
    {
        $result = $this->parser->parse_content('');
        $this->assertNull($result['name'] ?? null);
        $this->assertEmpty($result['sections'] ?? []);
    }

    /**
     * Test long description truncated
     */
    public function testlong_descriptionTruncated(): void
    {
        $long_desc = str_repeat('This is a very long description. ', 20);
        $readme = "=== Long Plugin ===\n\n{$long_desc}";
        $result = $this->parser->parse_content($readme);
        $short_desc = $result['short_description'] ?? '';
        $this->assertLessThanOrEqual(150, strlen($short_desc));
    }
}
