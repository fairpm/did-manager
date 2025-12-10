<?php
/**
 * MetadataGenerator - FAIR metadata.json generation
 *
 * Generates FAIR-compliant metadata.json for WordPress plugins/themes.
 *
 * @package FairDidManager\Parsers
 */

declare(strict_types=1);

namespace FAIR\DID\Parsers;

/**
 * MetadataGenerator - FAIR metadata.json generation.
 */
class MetadataGenerator {

	/**
	 * Parsed plugin/theme header data.
	 *
	 * @var array
	 */
	private array $header_data;

	/**
	 * Parsed readme.txt data.
	 *
	 * @var array
	 */
	private array $readme_data;

	/**
	 * Package slug.
	 *
	 * @var string|null
	 */
	private ?string $slug = null;

	/**
	 * DID identifier.
	 *
	 * @var string|null
	 */
	private ?string $did = null;

	/**
	 * Package type (plugin/theme).
	 *
	 * @var string|null
	 */
	private ?string $type = null;

	/**
	 * Constructor.
	 *
	 * @param array $header_data Parsed plugin/theme header.
	 * @param array $readme_data Parsed readme.txt.
	 */
	public function __construct( array $header_data = [], array $readme_data = [] ) {
		$this->header_data = $header_data;
		$this->readme_data = $readme_data;
	}

	/**
	 * Set the package slug.
	 *
	 * @param string $slug Package slug.
	 * @return self
	 */
	public function set_slug( string $slug ): self {
		$this->slug = $slug;
		return $this;
	}

	/**
	 * Set the DID.
	 *
	 * @param string $did DID identifier.
	 * @return self
	 */
	public function set_did( string $did ): self {
		$this->did = $did;
		return $this;
	}

	/**
	 * Set the package type.
	 *
	 * @param string $type Package type (plugin or theme).
	 * @return self
	 */
	public function set_type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Generate the metadata array.
	 *
	 * @return array FAIR-compliant metadata.
	 */
	public function generate(): array {
		$metadata = [
			'$schema'       => 'https://fair-protocol.org/schemas/wordpress-plugin.json',
			'schemaVersion' => '1.0',
			'type'          => $this->type ?? $this->detect_type(),
		];

		// Slug.
		$metadata['slug'] = $this->get_slug();

		// Name.
		$metadata['name'] = $this->get_name();

		// Version.
		$version = $this->get_version();
		if ( $version ) {
			$metadata['version'] = $version;
		}

		// Description.
		$description = $this->get_description();
		if ( $description ) {
			$metadata['description'] = $description;
		}

		// Homepage.
		$homepage = $this->get_homepage();
		if ( $homepage ) {
			$metadata['homepage'] = $homepage;
		}

		// Author.
		$author = $this->get_author();
		if ( $author ) {
			$metadata['author'] = $author;
		}

		// License.
		$license = $this->get_license();
		if ( $license ) {
			$metadata['license'] = $license;
		}

		// Requirements.
		$requires = $this->get_requirements();
		if ( $requires ) {
			$metadata['requires'] = $requires;
		}

		// Tags.
		$tags = $this->get_tags();
		if ( $tags ) {
			$metadata['tags'] = $tags;
		}

		// DID.
		if ( null !== $this->did ) {
			$metadata['did'] = $this->did;
		}

		// Readme data.
		if ( ! empty( $this->readme_data ) ) {
			$readme = [];

			if ( ! empty( $this->readme_data['header'] ) ) {
				$readme['header'] = $this->readme_data['header'];
			}

			if ( ! empty( $this->readme_data['sections'] ) ) {
				$readme['sections'] = $this->readme_data['sections'];
			}

			if ( ! empty( $readme ) ) {
				$metadata['readme'] = $readme;
			}
		}

		// Generated timestamp.
		$metadata['generatedAt'] = gmdate( 'c' );

		return $metadata;
	}

	/**
	 * Write metadata to a file.
	 *
	 * @param string $file_path Output file path.
	 * @throws \RuntimeException On write failure.
	 */
	public function write_to_file( string $file_path ): void {
		$metadata = $this->generate();
		$json     = json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$dir = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir, 0755, true ) ) {
				throw new \RuntimeException( "Failed to create directory: {$dir}" );
			}
		}

		if ( false === file_put_contents( $file_path, $json ) ) {
			throw new \RuntimeException( "Failed to write metadata to: {$file_path}" );
		}
	}

	/**
	 * Detect package type from headers.
	 *
	 * @return string Package type.
	 */
	private function detect_type(): string {
		if ( isset( $this->header_data['theme_name'] ) ) {
			return 'theme';
		}
		return 'plugin';
	}

	/**
	 * Get the package slug.
	 *
	 * @return string Slug.
	 */
	private function get_slug(): string {
		if ( null !== $this->slug ) {
			return $this->slug;
		}

		// Try text domain from header.
		if ( ! empty( $this->header_data['text_domain'] ) ) {
			return $this->header_data['text_domain'];
		}

		// Generate from name.
		$name = $this->get_name();
		return strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $name ) );
	}

	/**
	 * Get the package name.
	 *
	 * @return string Name.
	 */
	private function get_name(): string {
		// Header plugin name takes priority.
		if ( ! empty( $this->header_data['plugin_name'] ) ) {
			return $this->header_data['plugin_name'];
		}

		// Theme name.
		if ( ! empty( $this->header_data['theme_name'] ) ) {
			return $this->header_data['theme_name'];
		}

		// Readme name.
		if ( ! empty( $this->readme_data['name'] ) ) {
			return $this->readme_data['name'];
		}

		// Fallback to slug.
		return $this->slug ?? 'Unknown';
	}

	/**
	 * Get the version.
	 *
	 * @return string|null Version.
	 */
	private function get_version(): ?string {
		// Header version takes priority.
		if ( ! empty( $this->header_data['version'] ) ) {
			return $this->header_data['version'];
		}

		// Readme stable tag.
		if ( ! empty( $this->readme_data['header']['stable_tag'] ) ) {
			return $this->readme_data['header']['stable_tag'];
		}

		return null;
	}

	/**
	 * Get the description.
	 *
	 * @return string|null Description.
	 */
	private function get_description(): ?string {
		// Readme short description takes priority.
		if ( ! empty( $this->readme_data['short_description'] ) ) {
			return $this->readme_data['short_description'];
		}

		// Header description.
		if ( ! empty( $this->header_data['description'] ) ) {
			return $this->header_data['description'];
		}

		return null;
	}

	/**
	 * Get the homepage URL.
	 *
	 * @return string|null Homepage URL.
	 */
	private function get_homepage(): ?string {
		if ( ! empty( $this->header_data['plugin_uri'] ) ) {
			return $this->header_data['plugin_uri'];
		}

		if ( ! empty( $this->header_data['theme_uri'] ) ) {
			return $this->header_data['theme_uri'];
		}

		return null;
	}

	/**
	 * Get author information.
	 *
	 * @return array|null Author info.
	 */
	private function get_author(): ?array {
		$author = [];

		if ( ! empty( $this->header_data['author'] ) ) {
			$author['name'] = $this->header_data['author'];
		}

		if ( ! empty( $this->header_data['author_uri'] ) ) {
			$author['url'] = $this->header_data['author_uri'];
		}

		// Contributors from readme.
		if ( ! empty( $this->readme_data['header']['contributors'] ) ) {
			$author['contributors'] = $this->readme_data['header']['contributors'];
		}

		return ! empty( $author ) ? $author : null;
	}

	/**
	 * Get license information.
	 *
	 * @return array|null License info.
	 */
	private function get_license(): ?array {
		$license = [];

		// Readme license takes priority.
		if ( ! empty( $this->readme_data['header']['license'] ) ) {
			$license['name'] = $this->readme_data['header']['license'];
		} elseif ( ! empty( $this->header_data['license'] ) ) {
			$license['name'] = $this->header_data['license'];
		}

		// License URI.
		if ( ! empty( $this->readme_data['header']['license_uri'] ) ) {
			$license['url'] = $this->readme_data['header']['license_uri'];
		} elseif ( ! empty( $this->header_data['license_uri'] ) ) {
			$license['url'] = $this->header_data['license_uri'];
		}

		return ! empty( $license ) ? $license : null;
	}

	/**
	 * Get requirements.
	 *
	 * @return array|null Requirements.
	 */
	private function get_requirements(): ?array {
		$requires = [];

		// WordPress version.
		$wp_version = $this->header_data['requires_at_least']
			?? $this->readme_data['header']['requires_at_least']
			?? null;
		if ( $wp_version ) {
			$requires['wordpress'] = $wp_version;
		}

		// PHP version.
		$php_version = $this->header_data['requires_php']
			?? $this->readme_data['header']['requires_php']
			?? null;
		if ( $php_version ) {
			$requires['php'] = $php_version;
		}

		// Tested up to.
		if ( ! empty( $this->readme_data['header']['tested_up_to'] ) ) {
			$requires['tested'] = $this->readme_data['header']['tested_up_to'];
		}

		return ! empty( $requires ) ? $requires : null;
	}

	/**
	 * Get tags.
	 *
	 * @return array|null Tags.
	 */
	private function get_tags(): ?array {
		$tags = [];

		// Header tags.
		if ( ! empty( $this->header_data['tags'] ) && is_array( $this->header_data['tags'] ) ) {
			$tags = array_merge( $tags, $this->header_data['tags'] );
		}

		// Readme tags.
		if ( ! empty( $this->readme_data['header']['tags'] ) && is_array( $this->readme_data['header']['tags'] ) ) {
			$tags = array_merge( $tags, $this->readme_data['header']['tags'] );
		}

		// Remove duplicates and empty values.
		$tags = array_unique( array_filter( $tags ) );
		$tags = array_values( $tags );

		return ! empty( $tags ) ? $tags : null;
	}

	/**
	 * Create from path (convenience method).
	 *
	 * @param string $path Plugin/theme path.
	 * @return self
	 */
	public static function from_path( string $path ): self {
		$header_parser = new PluginHeaderParser();
		$header_data   = $header_parser->parse( $path );

		$readme_parser = new ReadmeParser();
		$readme_data   = $readme_parser->parse( $path );

		$generator = new self( $header_data, $readme_data );

		// Try to set slug from path.
		if ( is_dir( $path ) ) {
			$generator->set_slug( basename( rtrim( $path, '/\\' ) ) );
		}

		return $generator;
	}
}
