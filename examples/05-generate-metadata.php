<?php
/**
 * Example 5: Generating FAIR Metadata
 *
 * This example demonstrates how to generate FAIR metadata.json
 * by combining plugin header and readme.txt data.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

use FAIR\DID\Parsers\MetadataGenerator;
use FAIR\DID\Parsers\PluginHeaderParser;
use FAIR\DID\Parsers\ReadmeParser;

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== FAIR CLI: Metadata Generation Examples ===\n\n";

// -----------------------------------------------------------------------------
// Sample data
// -----------------------------------------------------------------------------

$plugin_header = <<<'PHP'
<?php
/**
 * Plugin Name: Contact Form Pro
 * Plugin URI: https://example.com/contact-form-pro
 * Description: Advanced contact forms with spam protection.
 * Version: 3.2.1
 * Author: FormBuilder Inc.
 * Author URI: https://formbuilder.io
 * Text Domain: contact-form-pro
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL-2.0+
 * Tags: forms, contact, email
 */
PHP;

$readme_content = <<<'README'
=== Contact Form Pro ===
Contributors: formbuilder, developer1
Tags: contact form, email, forms, spam protection, recaptcha
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 3.2.1
License: GPLv2 or later

Create beautiful, spam-free contact forms in minutes.

== Description ==

Contact Form Pro is the most powerful contact form plugin for WordPress.

Features:
* Drag and drop form builder
* Spam protection with reCAPTCHA
* Email notifications
* Form analytics

== Installation ==

1. Upload the plugin to your /wp-content/plugins/ directory
2. Activate the plugin through the WordPress admin
3. Go to Contact Forms > Add New to create your first form

== FAQ ==

= How do I create a form? =

Navigate to Contact Forms > Add New and use our drag-and-drop builder.

= Does it work with page builders? =

Yes! It works with Gutenberg, Elementor, and Divi.

== Changelog ==

= 3.2.1 =
* Fixed: Email delivery issues
* Improved: Form loading speed

= 3.2.0 =
* Added: New field types
* Added: Conditional logic
README;

// -----------------------------------------------------------------------------
// 1. Parse header and readme
// -----------------------------------------------------------------------------
echo "1. Parsing Plugin Header and Readme\n";
echo str_repeat( '-', 50 ) . "\n";

$header_parser = new PluginHeaderParser();
$readme_parser = new ReadmeParser();

$header_data = $header_parser->parse_content( $plugin_header );
$readme_data = $readme_parser->parse_content( $readme_content );

echo 'Header - Plugin Name: ' . ( $header_data['plugin_name'] ?? 'N/A' ) . "\n";
echo 'Header - Version: ' . ( $header_data['version'] ?? 'N/A' ) . "\n";
echo 'Readme - Name: ' . ( $readme_data['name'] ?? 'N/A' ) . "\n";
echo 'Readme - Tested Up To: ' . ( $readme_data['header']['tested_up_to'] ?? 'N/A' ) . "\n\n";

// -----------------------------------------------------------------------------
// 2. Generate basic metadata
// -----------------------------------------------------------------------------
echo "2. Generating Basic Metadata\n";
echo str_repeat( '-', 50 ) . "\n";

$generator = new MetadataGenerator( $header_data, $readme_data );
$metadata  = $generator->generate();

echo 'Schema Version: ' . $metadata['schemaVersion'] . "\n";
echo 'Type: ' . $metadata['type'] . "\n";
echo 'Slug: ' . $metadata['slug'] . "\n";
echo 'Name: ' . $metadata['name'] . "\n";
echo 'Version: ' . $metadata['version'] . "\n\n";

// -----------------------------------------------------------------------------
// 3. Add DID to metadata
// -----------------------------------------------------------------------------
echo "3. Adding DID to Metadata\n";
echo str_repeat( '-', 50 ) . "\n";

$generator->set_did( 'did:plc:contactformpro123' );
$metadata_with_did = $generator->generate();

echo 'DID: ' . $metadata_with_did['did'] . "\n\n";

// -----------------------------------------------------------------------------
// 4. Override slug
// -----------------------------------------------------------------------------
echo "4. Overriding Slug\n";
echo str_repeat( '-', 50 ) . "\n";

$generator->set_slug( 'custom-contact-form' );
$metadata_custom_slug = $generator->generate();

echo 'Custom Slug: ' . $metadata_custom_slug['slug'] . "\n\n";

// -----------------------------------------------------------------------------
// 5. Inspect full metadata structure
// -----------------------------------------------------------------------------
echo "5. Full Metadata Structure\n";
echo str_repeat( '-', 50 ) . "\n";

// Reset for clean output.
$clean_generator = new MetadataGenerator( $header_data, $readme_data );
$clean_generator->set_did( 'did:plc:contactformpro123' );
$full_metadata = $clean_generator->generate();

echo "Top-level keys:\n";
foreach ( array_keys( $full_metadata ) as $key ) {
	$value = $full_metadata[ $key ];
	if ( is_array( $value ) ) {
		echo "  - {$key}: (array with " . count( $value ) . " items)\n";
	} elseif ( is_string( $value ) ) {
		$display = strlen( $value ) > 40 ? substr( $value, 0, 40 ) . '...' : $value;
		echo "  - {$key}: {$display}\n";
	} else {
		echo "  - {$key}: " . gettype( $value ) . "\n";
	}
}
echo "\n";

// -----------------------------------------------------------------------------
// 6. Author information
// -----------------------------------------------------------------------------
echo "6. Author Information\n";
echo str_repeat( '-', 50 ) . "\n";

$author = $full_metadata['author'];
echo 'Author Name: ' . ( $author['name'] ?? 'N/A' ) . "\n";
echo 'Author URI: ' . ( $author['uri'] ?? 'N/A' ) . "\n";
if ( isset( $author['contributors'] ) ) {
	echo 'Contributors: ' . implode( ', ', $author['contributors'] ) . "\n";
}
echo "\n";

// -----------------------------------------------------------------------------
// 7. Requirements
// -----------------------------------------------------------------------------
echo "7. Requirements\n";
echo str_repeat( '-', 50 ) . "\n";

$requires = $full_metadata['requires'];
echo 'WordPress: ' . ( $requires['wordpress'] ?? 'N/A' ) . "\n";
echo 'PHP: ' . ( $requires['php'] ?? 'N/A' ) . "\n";
echo 'Tested Up To: ' . ( $requires['tested'] ?? 'N/A' ) . "\n\n";

// -----------------------------------------------------------------------------
// 8. Tags (merged from header and readme)
// -----------------------------------------------------------------------------
echo "8. Merged Tags\n";
echo str_repeat( '-', 50 ) . "\n";

echo 'Tags: ' . implode( ', ', $full_metadata['tags'] ) . "\n\n";

// -----------------------------------------------------------------------------
// 9. Readme sections
// -----------------------------------------------------------------------------
echo "9. Readme Sections\n";
echo str_repeat( '-', 50 ) . "\n";

if ( isset( $full_metadata['readme']['sections'] ) ) {
	foreach ( array_keys( $full_metadata['readme']['sections'] ) as $section ) {
		echo "  - {$section}\n";
	}
}
echo "\n";

// -----------------------------------------------------------------------------
// 10. Output as JSON
// -----------------------------------------------------------------------------
echo "10. JSON Output (formatted)\n";
echo str_repeat( '-', 50 ) . "\n";

$json_output = json_encode( $full_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
// Show first 1000 chars.
echo substr( $json_output, 0, 1000 );
if ( strlen( $json_output ) > 1000 ) {
	echo "\n... (truncated, full output is " . strlen( $json_output ) . " chars)\n";
}
echo "\n\n";

echo "=== Example Complete ===\n";
