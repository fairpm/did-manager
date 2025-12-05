#!/usr/bin/env php
<?php
/**
 * FAIR CLI Tool
 *
 * PHP CLI tool for:
 * - DID Management using Bluesky PLC (did:plc: method)
 * - Metadata Generation for WordPress Plugins/Themes
 * - Integration with FAIR Protocol
 *
 * @package FairCli
 */

declare(strict_types=1);

// Ensure we're running from CLI
if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.');
}

// Autoload
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

// If no autoloader, load files manually
if (!$autoloaded) {
    $srcDir = __DIR__ . '/src/';
    $files = [
        'class-base58.php',
        'class-did-codec.php',
        'class-key-store.php',
        'class-plc-client.php',
        'class-fair-did-manager.php',
        'class-plugin-header-parser.php',
        'class-readme-parser.php',
        'class-metadata-generator.php',
    ];
    foreach ($files as $file) {
        if (file_exists($srcDir . $file)) {
            require_once $srcDir . $file;
        }
    }
}

use FairCli\FairDidManager;
use FairCli\MetadataGenerator;
use FairCli\PluginHeaderParser;
use FairCli\ReadmeParser;
use FairCli\KeyStore;
use FairCli\PlcClient;

/**
 * CLI Application
 */
class FairCli
{
    private array $argv;
    private int $argc;
    private bool $jsonOutput = false;
    private ?FairDidManager $didManager = null;
    private ?KeyStore $keyStore = null;
    private ?PlcClient $plcClient = null;

    /**
     * Available commands with descriptions
     */
    private array $commands = [
        'did:create'      => 'Create a new DID via Bluesky PLC',
        'did:resolve'     => 'Resolve and display a DID document',
        'did:update'      => 'Update an existing DID document',
        'did:rotate-keys' => 'Rotate keys for an existing DID',
        'did:deactivate'  => 'Deactivate a DID',
        'did:list'        => 'List locally stored DIDs',
        'metadata:generate' => 'Generate metadata.json for a WordPress plugin/theme',
        'package:init'    => 'Initialize a package with DID and metadata',
        'help'            => 'Show help information',
    ];

    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->argc = count($argv);
        $this->parseGlobalFlags();
        $this->initializeServices();
    }

    /**
     * Parse global flags like --json and --help
     */
    private function parseGlobalFlags(): void
    {
        foreach ($this->argv as $key => $arg) {
            if ($arg === '--json') {
                $this->jsonOutput = true;
                unset($this->argv[$key]);
            }
        }
        $this->argv = array_values($this->argv);
        $this->argc = count($this->argv);
    }

    /**
     * Initialize services
     */
    private function initializeServices(): void
    {
        $storePath = getenv('FAIR_KEYSTORE_PATH') ?: (__DIR__ . '/data/keystore.json');
        $this->keyStore = new KeyStore($storePath);
        
        $plcUrl = getenv('FAIR_PLC_URL') ?: 'https://plc.directory';
        $this->plcClient = new PlcClient($plcUrl);
        
        $this->didManager = new FairDidManager($this->keyStore, $this->plcClient);
    }

    /**
     * Run the CLI application
     */
    public function run(): int
    {
        if ($this->argc < 2) {
            $this->showHelp();
            return 0;
        }

        $command = $this->argv[1];
        $args = array_slice($this->argv, 2);

        // Check for --help on specific command
        if (in_array('--help', $args, true)) {
            $this->showCommandHelp($command);
            return 0;
        }

        try {
            return match ($command) {
                'did:create'        => $this->didCreate($args),
                'did:resolve'       => $this->didResolve($args),
                'did:update'        => $this->didUpdate($args),
                'did:rotate-keys'   => $this->didRotateKeys($args),
                'did:deactivate'    => $this->didDeactivate($args),
                'did:list'          => $this->didList($args),
                'metadata:generate' => $this->metadataGenerate($args),
                'package:init'      => $this->packageInit($args),
                'help', '--help', '-h' => $this->showHelp(),
                default             => $this->unknownCommand($command),
            };
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Create a new DID
     */
    private function didCreate(array $args): int
    {
        $options = $this->parseArgs($args, [
            'handle'      => null,
            'service'     => null,
            'plugin-path' => null,
            'inject-id'   => false,
        ]);

        $this->info('Creating new DID...');

        $result = $this->didManager->createDid(
            handle: $options['handle'],
            serviceEndpoint: $options['service'],
            pluginPath: $options['plugin-path'],
            injectId: $options['inject-id']
        );

        if ($this->jsonOutput) {
            $this->output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->success('DID created successfully!');
            $this->output("DID: {$result['did']}");
            if (isset($result['rotationKey'])) {
                $this->output("Rotation Key: {$result['rotationKey']}");
            }
            if (isset($result['verificationKey'])) {
                $this->output("Verification Key: {$result['verificationKey']}");
            }
        }

        return 0;
    }

    /**
     * Resolve a DID
     */
    private function didResolve(array $args): int
    {
        if (empty($args[0])) {
            $this->error('DID is required. Usage: did:resolve <did>');
            return 1;
        }

        $did = $args[0];
        $this->info("Resolving DID: {$did}");

        $document = $this->didManager->resolveDid($did);

        if ($this->jsonOutput) {
            $this->output(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->success('DID Document:');
            $this->output("ID: {$document['id']}");
            
            if (!empty($document['service'])) {
                $this->output("\nServices:");
                foreach ($document['service'] as $service) {
                    $this->output("  - {$service['id']}: {$service['serviceEndpoint']}");
                }
            }
            
            if (!empty($document['verificationMethod'])) {
                $this->output("\nVerification Methods:");
                foreach ($document['verificationMethod'] as $method) {
                    $this->output("  - {$method['id']} ({$method['type']})");
                }
            }
        }

        return 0;
    }

    /**
     * Update a DID
     */
    private function didUpdate(array $args): int
    {
        $options = $this->parseArgs($args, [
            'did'     => null,
            'handle'  => null,
            'service' => null,
        ]);

        if (empty($options['did'])) {
            $this->error('DID is required. Usage: did:update --did=<did> [--handle=<handle>] [--service=<url>]');
            return 1;
        }

        $this->info("Updating DID: {$options['did']}");

        $changes = [];
        if ($options['handle']) {
            $changes['handle'] = $options['handle'];
        }
        if ($options['service']) {
            $changes['service'] = $options['service'];
        }

        if (empty($changes)) {
            $this->error('No changes specified. Use --handle or --service to specify updates.');
            return 1;
        }

        $result = $this->didManager->updateDid($options['did'], $changes);

        if ($this->jsonOutput) {
            $this->output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->success('DID updated successfully!');
        }

        return 0;
    }

    /**
     * Rotate keys for a DID
     */
    private function didRotateKeys(array $args): int
    {
        $options = $this->parseArgs($args, [
            'did'    => null,
            'reason' => null,
        ]);

        if (empty($options['did'])) {
            $this->error('DID is required. Usage: did:rotate-keys --did=<did> [--reason=<reason>]');
            return 1;
        }

        $this->info("Rotating keys for DID: {$options['did']}");

        $result = $this->didManager->rotateKeys($options['did'], $options['reason']);

        if ($this->jsonOutput) {
            $this->output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->success('Keys rotated successfully!');
            $this->output("New Rotation Key: {$result['rotationKey']}");
            $this->output("New Verification Key: {$result['verificationKey']}");
        }

        return 0;
    }

    /**
     * Deactivate a DID
     */
    private function didDeactivate(array $args): int
    {
        $options = $this->parseArgs($args, [
            'did'   => null,
            'force' => false,
        ]);

        if (empty($options['did'])) {
            $this->error('DID is required. Usage: did:deactivate --did=<did> [--force]');
            return 1;
        }

        if (!$options['force']) {
            $this->output("Are you sure you want to deactivate DID: {$options['did']}?");
            $this->output("This action cannot be undone. Use --force to confirm.");
            return 1;
        }

        $this->info("Deactivating DID: {$options['did']}");

        $result = $this->didManager->deactivateDid($options['did']);

        if ($this->jsonOutput) {
            $this->output(json_encode(['success' => $result, 'did' => $options['did']], JSON_PRETTY_PRINT));
        } else {
            if ($result) {
                $this->success('DID deactivated successfully.');
            } else {
                $this->error('Failed to deactivate DID.');
                return 1;
            }
        }

        return 0;
    }

    /**
     * List local DIDs
     */
    private function didList(array $args): int
    {
        $dids = $this->didManager->listLocalDids();

        if ($this->jsonOutput) {
            $this->output(json_encode($dids, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            if (empty($dids)) {
                $this->info('No DIDs stored locally.');
            } else {
                $this->success('Locally stored DIDs:');
                foreach ($dids as $entry) {
                    $status = $entry['active'] ? 'active' : 'deactivated';
                    $type = $entry['type'] ?? 'unknown';
                    $this->output("  {$entry['did']} [{$type}] ({$status})");
                }
            }
        }

        return 0;
    }

    /**
     * Generate metadata.json
     */
    private function metadataGenerate(array $args): int
    {
        $options = $this->parseArgs($args, [
            'path'   => null,
            'slug'   => null,
            'did'    => null,
            'output' => null,
        ]);

        $path = $options['path'] ?? ($args[0] ?? null);

        if (empty($path)) {
            $this->error('Plugin/theme path is required. Usage: metadata:generate <path> [--slug=<slug>] [--did=<did>]');
            return 1;
        }

        if (!is_dir($path)) {
            $this->error("Path does not exist or is not a directory: {$path}");
            return 1;
        }

        $this->info("Generating metadata for: {$path}");

        // Parse plugin header
        $headerParser = new PluginHeaderParser();
        $headerData = $headerParser->parse($path);

        // Parse readme.txt
        $readmeParser = new ReadmeParser();
        $readmeData = $readmeParser->parse($path);

        // Generate metadata
        $generator = new MetadataGenerator($headerData, $readmeData);
        
        if ($options['slug']) {
            $generator->setSlug($options['slug']);
        }
        if ($options['did']) {
            $generator->setDid($options['did']);
        }

        $metadata = $generator->generate();

        if ($this->jsonOutput || !$options['output']) {
            $this->output(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($options['output']) {
            $outputPath = $options['output'];
            if ($outputPath === 'auto') {
                $outputPath = rtrim($path, '/\\') . '/metadata.json';
            }
            $generator->writeToFile($outputPath);
            $this->success("Metadata written to: {$outputPath}");
        }

        return 0;
    }

    /**
     * Initialize package with DID and metadata
     */
    private function packageInit(array $args): int
    {
        $options = $this->parseArgs($args, [
            'path'    => null,
            'slug'    => null,
            'handle'  => null,
            'service' => null,
        ]);

        $path = $options['path'] ?? ($args[0] ?? null);

        if (empty($path)) {
            $this->error('Plugin/theme path is required. Usage: package:init <path> [options]');
            return 1;
        }

        if (!is_dir($path)) {
            $this->error("Path does not exist or is not a directory: {$path}");
            return 1;
        }

        $this->info("Initializing package: {$path}");

        // Create DID
        $this->info('Creating DID...');
        $didResult = $this->didManager->createDid(
            handle: $options['handle'],
            serviceEndpoint: $options['service'],
            pluginPath: $path,
            injectId: true
        );

        // Generate metadata with DID
        $this->info('Generating metadata...');
        $headerParser = new PluginHeaderParser();
        $headerData = $headerParser->parse($path);

        $readmeParser = new ReadmeParser();
        $readmeData = $readmeParser->parse($path);

        $generator = new MetadataGenerator($headerData, $readmeData);
        if ($options['slug']) {
            $generator->setSlug($options['slug']);
        }
        $generator->setDid($didResult['did']);

        $metadata = $generator->generate();
        $metadataPath = rtrim($path, '/\\') . '/metadata.json';
        $generator->writeToFile($metadataPath);

        if ($this->jsonOutput) {
            $this->output(json_encode([
                'did' => $didResult,
                'metadata' => $metadata,
                'metadataPath' => $metadataPath,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->success('Package initialized successfully!');
            $this->output("DID: {$didResult['did']}");
            $this->output("Metadata: {$metadataPath}");
        }

        return 0;
    }

    /**
     * Show general help
     */
    private function showHelp(): int
    {
        $this->output("FAIR CLI Tool - DID Management and Metadata Generation\n");
        $this->output("Usage: php fair.php <command> [options]\n");
        $this->output("Commands:");
        
        foreach ($this->commands as $cmd => $desc) {
            $this->output(sprintf("  %-20s %s", $cmd, $desc));
        }

        $this->output("\nGlobal Options:");
        $this->output("  --json              Output results as JSON");
        $this->output("  --help              Show help for a specific command");
        $this->output("\nExamples:");
        $this->output("  php fair.php did:create --handle=my-plugin");
        $this->output("  php fair.php did:resolve did:plc:abc123");
        $this->output("  php fair.php metadata:generate ./my-plugin --output=auto");
        $this->output("  php fair.php package:init ./my-plugin --handle=my-plugin");

        return 0;
    }

    /**
     * Show help for a specific command
     */
    private function showCommandHelp(string $command): void
    {
        $help = match ($command) {
            'did:create' => <<<HELP
did:create - Create a new DID via Bluesky PLC

Usage: php fair.php did:create [options]

Options:
  --handle=<handle>       Optional handle/alias for the DID
  --service=<url>         Service endpoint URL
  --plugin-path=<path>    Path to plugin to inject Plugin ID header
  --inject-id             Inject Plugin ID into plugin header

Examples:
  php fair.php did:create
  php fair.php did:create --handle=my-plugin --service=https://example.com
  php fair.php did:create --plugin-path=./my-plugin --inject-id
HELP,
            'did:resolve' => <<<HELP
did:resolve - Resolve and display a DID document

Usage: php fair.php did:resolve <did>

Arguments:
  <did>                   The DID to resolve (e.g., did:plc:abc123)

Examples:
  php fair.php did:resolve did:plc:abc123
  php fair.php did:resolve did:plc:abc123 --json
HELP,
            'did:update' => <<<HELP
did:update - Update an existing DID document

Usage: php fair.php did:update --did=<did> [options]

Options:
  --did=<did>             The DID to update (required)
  --handle=<handle>       New handle/alias
  --service=<url>         New service endpoint URL

Examples:
  php fair.php did:update --did=did:plc:abc123 --handle=new-handle
  php fair.php did:update --did=did:plc:abc123 --service=https://new-url.com
HELP,
            'did:rotate-keys' => <<<HELP
did:rotate-keys - Rotate keys for an existing DID

Usage: php fair.php did:rotate-keys --did=<did> [options]

Options:
  --did=<did>             The DID to rotate keys for (required)
  --reason=<reason>       Optional reason for key rotation

Examples:
  php fair.php did:rotate-keys --did=did:plc:abc123
  php fair.php did:rotate-keys --did=did:plc:abc123 --reason="Scheduled rotation"
HELP,
            'did:deactivate' => <<<HELP
did:deactivate - Deactivate a DID

Usage: php fair.php did:deactivate --did=<did> [--force]

Options:
  --did=<did>             The DID to deactivate (required)
  --force                 Confirm deactivation (required)

Examples:
  php fair.php did:deactivate --did=did:plc:abc123 --force
HELP,
            'did:list' => <<<HELP
did:list - List locally stored DIDs

Usage: php fair.php did:list

Examples:
  php fair.php did:list
  php fair.php did:list --json
HELP,
            'metadata:generate' => <<<HELP
metadata:generate - Generate metadata.json for a WordPress plugin/theme

Usage: php fair.php metadata:generate <path> [options]

Arguments:
  <path>                  Path to the plugin/theme directory

Options:
  --slug=<slug>           Override the package slug
  --did=<did>             DID to include in metadata
  --output=<path>         Output file path (use 'auto' for <path>/metadata.json)

Examples:
  php fair.php metadata:generate ./my-plugin
  php fair.php metadata:generate ./my-plugin --slug=my-custom-slug
  php fair.php metadata:generate ./my-plugin --did=did:plc:abc123 --output=auto
HELP,
            'package:init' => <<<HELP
package:init - Initialize a package with DID and metadata

Usage: php fair.php package:init <path> [options]

Arguments:
  <path>                  Path to the plugin/theme directory

Options:
  --slug=<slug>           Override the package slug
  --handle=<handle>       Handle/alias for the DID
  --service=<url>         Service endpoint URL

Examples:
  php fair.php package:init ./my-plugin
  php fair.php package:init ./my-plugin --handle=my-plugin --service=https://example.com
HELP,
            default => "No detailed help available for: {$command}",
        };

        $this->output($help);
    }

    /**
     * Handle unknown command
     */
    private function unknownCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->output("Run 'php fair.php help' for available commands.");
        return 1;
    }

    /**
     * Parse command arguments into options array
     */
    private function parseArgs(array $args, array $defaults): array
    {
        $options = $defaults;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $key = str_replace('-', '_', $key);
                    if (array_key_exists(str_replace('_', '-', $key), $defaults) || array_key_exists($key, $defaults)) {
                        $options[str_replace('_', '-', $key)] = $value;
                    }
                } else {
                    $key = str_replace('-', '_', $arg);
                    if (array_key_exists(str_replace('_', '-', $key), $defaults) || array_key_exists($key, $defaults)) {
                        $options[str_replace('_', '-', $key)] = true;
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Output a message
     */
    private function output(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Output an info message
     */
    private function info(string $message): void
    {
        if (!$this->jsonOutput) {
            echo "\033[36m→ {$message}\033[0m" . PHP_EOL;
        }
    }

    /**
     * Output a success message
     */
    private function success(string $message): void
    {
        if (!$this->jsonOutput) {
            echo "\033[32m✓ {$message}\033[0m" . PHP_EOL;
        }
    }

    /**
     * Output an error message
     */
    private function error(string $message): void
    {
        if ($this->jsonOutput) {
            echo json_encode(['error' => $message]) . PHP_EOL;
        } else {
            fwrite(STDERR, "\033[31m✗ {$message}\033[0m" . PHP_EOL);
        }
    }
}

// Run the application
$cli = new FairCli($argv);
exit($cli->run());
