<?php
/**
 * Example 4: Parsing WordPress Plugin Headers
 *
 * This example demonstrates how to parse WordPress plugin headers
 * to extract metadata for FAIR protocol integration.
 *
 * @package FairDidManager\Examples
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FairDidManager\Parsers\PluginHeaderParser;

echo "=== FAIR CLI: Plugin Header Parsing Examples ===\n\n";

// -----------------------------------------------------------------------------
// 1. Parse a standard plugin header
// -----------------------------------------------------------------------------
echo "1. Parsing Standard Plugin Header\n";
echo str_repeat( '-', 50 ) . "\n";

$plugin_content = <<<'PHP'
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/my-awesome-plugin
 * Description: A powerful plugin that does amazing things.
 * Version: 2.1.0
 * Author: John Developer
 * Author URI: https://johndeveloper.com
 * Text Domain: my-awesome-plugin
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Tags: awesome, powerful, amazing
 * Plugin ID: did:plc:abc123xyz
 */

// Plugin code here...
PHP;

$parser = new PluginHeaderParser();
$result = $parser->parse_content( $plugin_content );

echo 'Plugin Name: ' . ( $result['plugin_name'] ?? 'N/A' ) . "\n";
echo 'Version: ' . ( $result['version'] ?? 'N/A' ) . "\n";
echo 'Author: ' . ( $result['author'] ?? 'N/A' ) . "\n";
echo 'Description: ' . ( $result['description'] ?? 'N/A' ) . "\n";
echo 'Text Domain: ' . ( $result['text_domain'] ?? 'N/A' ) . "\n";
echo 'Requires WP: ' . ( $result['requires_at_least'] ?? 'N/A' ) . "\n";
echo 'Requires PHP: ' . ( $result['requires_php'] ?? 'N/A' ) . "\n";
echo 'License: ' . ( $result['license'] ?? 'N/A' ) . "\n";
echo 'Plugin ID: ' . ( $result['plugin_id'] ?? 'N/A' ) . "\n";
echo 'Tags: ' . ( isset( $result['tags'] ) ? implode( ', ', $result['tags'] ) : 'N/A' ) . "\n\n";

// -----------------------------------------------------------------------------
// 2. Parse minimal header
// -----------------------------------------------------------------------------
echo "2. Parsing Minimal Plugin Header\n";
echo str_repeat( '-', 50 ) . "\n";

$minimal_content = <<<'PHP'
<?php
/*
Plugin Name: Simple Plugin
Version: 1.0
*/
PHP;

$minimal_result = $parser->parse_content( $minimal_content );

echo 'Plugin Name: ' . ( $minimal_result['plugin_name'] ?? 'N/A' ) . "\n";
echo 'Version: ' . ( $minimal_result['version'] ?? 'N/A' ) . "\n";
echo 'Has Author: ' . ( isset( $minimal_result['author'] ) ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 3. Parse from actual file (using fixture)
// -----------------------------------------------------------------------------
echo "3. Parsing From Fixture File\n";
echo str_repeat( '-', 50 ) . "\n";

$fixture_path = __DIR__ . '/../tests/fixtures/test-plugin.php';
if ( file_exists( $fixture_path ) ) {
	$file_result = $parser->parse_file( $fixture_path );
	echo 'From File - Plugin Name: ' . ( $file_result['plugin_name'] ?? 'N/A' ) . "\n";
	echo 'From File - Version: ' . ( $file_result['version'] ?? 'N/A' ) . "\n";
} else {
	echo "Fixture file not found at: {$fixture_path}\n";
}
echo "\n";

// -----------------------------------------------------------------------------
// 4. Handle header without Plugin ID (for new plugins)
// -----------------------------------------------------------------------------
echo "4. Plugin Without Plugin ID\n";
echo str_repeat( '-', 50 ) . "\n";

$no_id_content = <<<'PHP'
<?php
/**
 * Plugin Name: New Plugin
 * Version: 0.1.0
 * Author: New Developer
 */
PHP;

$no_id_result = $parser->parse_content( $no_id_content );

echo 'Plugin Name: ' . ( $no_id_result['plugin_name'] ?? 'N/A' ) . "\n";
echo 'Has Plugin ID: ' . ( isset( $no_id_result['plugin_id'] ) ? 'Yes' : 'No' ) . "\n";
echo "This plugin needs a DID to be registered!\n\n";

// -----------------------------------------------------------------------------
// 5. Parse theme header (style.css format)
// -----------------------------------------------------------------------------
echo "5. Parsing Theme Header (style.css)\n";
echo str_repeat( '-', 50 ) . "\n";

$theme_content = <<<'CSS'
/*
Theme Name: Beautiful Theme
Theme URI: https://example.com/beautiful-theme
Author: Theme Designer
Author URI: https://themedesigner.com
Description: A beautiful and responsive theme.
Version: 3.0.0
Requires at least: 6.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: beautiful-theme
Tags: responsive, modern, clean
*/
CSS;

$theme_result = $parser->parse_content( $theme_content );

echo 'Theme Name: ' . ( $theme_result['theme_name'] ?? 'N/A' ) . "\n";
echo 'Version: ' . ( $theme_result['version'] ?? 'N/A' ) . "\n";
echo 'Author: ' . ( $theme_result['author'] ?? 'N/A' ) . "\n";
echo 'Is Theme: ' . ( isset( $theme_result['theme_name'] ) ? 'Yes' : 'No' ) . "\n\n";

// -----------------------------------------------------------------------------
// 6. Show all parsed fields
// -----------------------------------------------------------------------------
echo "6. All Available Fields from Standard Header\n";
echo str_repeat( '-', 50 ) . "\n";

foreach ( $result as $key => $value ) {
	if ( is_array( $value ) ) {
		echo "{$key}: [" . implode( ', ', $value ) . "]\n";
	} else {
		echo "{$key}: {$value}\n";
	}
}
echo "\n";

echo "=== Example Complete ===\n";
