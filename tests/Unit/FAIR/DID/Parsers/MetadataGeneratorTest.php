<?php

/**
 * Metadata Generator Tests
 *
 * @package FairDidManager\Tests\Parsers
 */

declare(strict_types=1);

namespace Tests\Unit\FAIR\DID\Parsers;

use FAIR\DID\Parsers\MetadataGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Metadata Generator
 */
class MetadataGeneratorTest extends TestCase
{
    /**
     * Get standard header data for testing
     */
    private function getHeaderData(): array
    {
        return [
            'plugin_name' => 'My Test Plugin',
            'plugin_uri' => 'https://example.com/my-plugin',
            'description' => 'Header description',
            'version' => '1.2.3',
            'author' => 'Test Author',
            'author_uri' => 'https://example.com',
            'text_domain' => 'my-test-plugin',
            'requires_at_least' => '5.8',
            'requires_php' => '7.4',
            'license' => 'GPL-2.0+',
            'license_uri' => 'https://www.gnu.org/licenses/gpl-2.0.html',
            'tags' => ['header-tag1', 'header-tag2'],
        ];
    }

    /**
     * Get standard readme data for testing
     */
    private function getReadmeData(): array
    {
        return [
            'name' => 'My Test Plugin',
            'short_description' => 'Readme short description',
            'header' => [
                'contributors' => ['author1', 'author2'],
                'tags' => ['readme-tag1', 'readme-tag2'],
                'tested_up_to' => '6.4',
                'stable_tag' => '1.2.3',
                'license' => 'GPLv2 or later',
                'license_uri' => 'https://www.gnu.org/licenses/gpl-2.0.html',
            ],
            'sections' => [
                'description' => 'Full description here.',
                'installation' => "1. Install\n2. Activate",
                'changelog' => "= 1.2.3 =\n* First release",
            ],
        ];
    }



    /**
     * Test type is plugin
     */
    public function testTypeIsPlugin(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('plugin', $metadata['type']);
    }

    /**
     * Test slug is text domain
     */
    public function testSlugIsTextDomain(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('my-test-plugin', $metadata['slug']);
    }

    /**
     * Test name from header
     */
    public function testNameFromHeader(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('My Test Plugin', $metadata['name']);
    }

    /**
     * Test version from header
     */
    public function testVersionFromHeader(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('1.2.3', $metadata['version']);
    }

    /**
     * Test DID is set
     */
    public function testDidIsSet(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $generator->set_did('did:plc:abc123');
        $metadata = $generator->generate();
        $this->assertSame('did:plc:abc123', $metadata['id']);
    }

    /**
     * Test description uses readme short description
     */
    public function testDescriptionUsesReadmeShortDescription(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('Readme short description', $metadata['description']);
    }

    /**
     * Test authors array from header
     */
    public function testAuthorsArrayFromHeader(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertIsArray($metadata['authors']);
        $this->assertSame('Test Author', $metadata['authors'][0]['name']);
        $this->assertSame('https://example.com', $metadata['authors'][0]['url']);
    }

    /**
     * Test contributors from readme included in authors
     */
    public function testContributorsFromReadmeInAuthors(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertGreaterThanOrEqual(3, count($metadata['authors']));
        $this->assertSame('author1', $metadata['authors'][1]['name']);
        $this->assertSame('author2', $metadata['authors'][2]['name']);
    }

    /**
     * Test WordPress requirement from header
     */
    public function testWordPressRequirementFromHeader(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('5.8', $metadata['requires']['wordpress']);
    }

    /**
     * Test tested up to from readme
     */
    public function testTestedUpToFromReadme(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('6.4', $metadata['requires']['tested']);
    }

    /**
     * Test tags merged from header and readme
     */
    public function testTagsMerged(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $tags = $metadata['tags'];
        $this->assertContains('header-tag1', $tags);
        $this->assertContains('readme-tag1', $tags);
    }

    /**
     * Test sections included at root level
     */
    public function testSectionsIncludedAtRoot(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertArrayHasKey('sections', $metadata);
    }

    /**
     * Test slug override works
     */
    public function testSlugOverride(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $generator->set_slug('custom-slug');
        $metadata = $generator->generate();
        $this->assertSame('custom-slug', $metadata['slug']);
    }

    /**
     * Test falls back to header description
     */
    public function testFallsBackToHeaderDescription(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), []);
        $metadata = $generator->generate();
        $this->assertSame('Header description', $metadata['description']);
    }

    /**
     * Test name from readme when no header
     */
    public function testNameFromReadmeWhenNoHeader(): void
    {
        $generator = new MetadataGenerator([], $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertSame('My Test Plugin', $metadata['name']);
    }

    /**
     * Test valid timestamp generated
     */
    public function testValidTimestampGenerated(): void
    {
        $generator = new MetadataGenerator($this->getHeaderData(), $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertArrayHasKey('generatedAt', $metadata);
        $time = strtotime($metadata['generatedAt']);
        $this->assertNotFalse($time);
        $this->assertGreaterThan(0, $time);
    }

    /**
     * Test theme type detected
     */
    public function testThemeTypeDetected(): void
    {
        $theme_header = ['theme_name' => 'My Theme'];
        $generator = new MetadataGenerator($theme_header, []);
        $metadata = $generator->generate();
        $this->assertSame('theme', $metadata['type']);
    }

    /**
     * Test security field from header
     */
    public function testSecurityFieldFromHeader(): void
    {
        $header_with_security = array_merge(
            $this->getHeaderData(),
            ['security' => 'security@example.com']
        );
        $generator = new MetadataGenerator($header_with_security, $this->getReadmeData());
        $metadata = $generator->generate();
        $this->assertArrayHasKey('security', $metadata);
        $this->assertIsArray($metadata['security']);
        $this->assertSame('security@example.com', $metadata['security'][0]['email']);
    }
}
