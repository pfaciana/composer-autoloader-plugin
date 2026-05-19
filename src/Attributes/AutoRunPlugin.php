<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

use Render\Autoloader\CallBuilder;
use Render\Autoloader\CodeAnalyzer;
use Render\Autoloader\BootstrapRenderer;
use Render\Autoloader\Entry;
use Render\Autoloader\Filesystem;
use Render\Autoloader\Import;

use Render\IncludeFile;

use Composer\IO\IOInterface;

class AutoRunPlugin
{
	private IOInterface $io;
	private string $vendorDir;

	/**
	 * Create plugin instance.
	 *
	 * @param IOInterface $io        Composer IO for console output
	 * @param string      $vendorDir Absolute path to vendor directory
	 */
	public function __construct ( IOInterface $io, string $vendorDir )
	{
		$this->io        = $io;
		$this->vendorDir = $vendorDir;
	}

	/**
	 * Scans file system for attributes, generates bootstrap, registers with autoloader.
	 *
	 * @param array $extra Composer extra config
	 *
	 * @return ?string Path to generated bootstrap file or null if no attributes found
	 */
	public function run ( array $extra ): ?string
	{
		if ( !isset( $extra['autoload-by-attr'] ) ) {
			return NULL;
		}

		$this->io->write( '<info>Render Autoloader:</info> Scanning for #[AutoRun] attributes...' );

		$result = self::build( $extra, $this->vendorDir );

		if ( empty( $result['path'] ) ) {
			$this->io->write( '<info>Render Autoloader:</info> No #[AutoRun] attributes found.' );

			return NULL;
		}

		$this->io->write( sprintf(
			'<info>Render Autoloader:</info> Generated %s with %d call(s).',
			$result['path'],
			$result['count'],
		) );

		return $result['path'];
	}

	/**
	 * Generate bootstrap directly from composer.json path.
	 *
	 * Standalone entry point without Composer runtime.
	 * Parses composer.json for config and vendor-dir.
	 *
	 * @throws \InvalidArgumentException If composer.json not found or invalid
	 *
	 * @param string $composerJson Absolute path to composer.json
	 *
	 * @return string|null Path to generated bootstrap or null if no entries
	 */
	public static function directRun ( string $composerJson ): ?string
	{
		if ( !is_file( $composerJson ) ) {
			throw new \InvalidArgumentException( "composer.json not found: {$composerJson}" );
		}

		try {
			$json = json_decode( file_get_contents( $composerJson ) ?: '', TRUE, flags: JSON_THROW_ON_ERROR );
		}
		catch ( \JsonException $e ) {
			throw new \InvalidArgumentException( "Invalid composer.json: {$composerJson}", previous: $e );
		}

		if ( !is_array( $json ) ) {
			throw new \InvalidArgumentException( "Invalid composer.json: {$composerJson}" );
		}

		$root = dirname( $composerJson );
		if ( !IncludeFile::is_absolute_path( $vendorDir = $json['config']['vendor-dir'] ?? 'vendor' ) ) {
			$vendorDir = IncludeFile::add_trailing_slash( $root ) . IncludeFile::strip_preceding_slash( $vendorDir );
		}

		return self::build( $json['extra'] ?? [], IncludeFile::normalize( $vendorDir ) )['path'];
	}

	/**
	 * Generate bootstrap at runtime with custom config.
	 *
	 * For dynamic bootstrap generation outside Composer events.
	 *
	 * @throws \InvalidArgumentException If vendorDir not absolute
	 *
	 * @param string       $vendorDir Absolute path to vendor directory
	 * @param string|array $config    config array
	 *
	 * @return string|null Path to generated bootstrap or null if no entries
	 */
	public static function runtime ( string $vendorDir, string|array $config ): ?string
	{
		if ( !IncludeFile::is_absolute_path( $vendorDir ) ) {
			throw new \InvalidArgumentException( "Invalid vendorDir, must be an absolute directory: {$vendorDir}" );
		}

		$config = array_merge( [
			'force'   => FALSE,
			'cleanup' => FALSE,
			'runtime' => TRUE,
		], is_array( $config ) ? $config : [ 'patterns' => $config ] );

		$extra = [ 'autoload-by-attr' => $config ];

		return self::build( $extra, IncludeFile::normalize( $vendorDir ) )['path'];
	}

	/**
	 * Core build orchestration.
	 *
	 * Scans files, checks staleness, generates bootstrap if needed.
	 *
	 * @param array  $extra     Composer extra config (must contain autoload-by-attr)
	 * @param string $vendorDir Absolute path to vendor directory
	 *
	 * @return array{path: ?string, count: int} Output path (null if none) and entry count
	 */
	public static function build ( array $extra, string $vendorDir ): array
	{
		if ( !isset( $extra['autoload-by-attr'] ) ) {
			return [ 'path' => NULL, 'count' => 0 ];
		}

		$config   = self::getConfig( $extra, $vendorDir );
		$phpFiles = self::getFiles( $config['cwd'], $config );
		if ( $config['force'] || self::isStale( $config['output'], $phpFiles, $config['cwf'] ) ) {
			$entries = self::getEntries( $phpFiles, $config );
			self::makeFile( $config, $entries );
		}

		$outputFile = file_exists( $config['output'] ) ? $config['output'] : NULL;

		return [ 'path' => $outputFile, 'count' => count( $entries ?? [] ) ];
	}

	/**
	 * Get plugin configuration from composer.json extra.
	 *
	 * @param array $extra Composer extra config
	 *
	 * @return array{
	 *     attribute: Attribute,
	 *     patterns: string|string[],
	 *     output: string,
	 *     cwd: string,
	 *     cwf: ?string,
	 *     force: bool,
	 *     cleanup: bool,
	 *     runtime: bool,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * } Attribute config
	 */
	public static function getConfig ( array $extra, string $vendorDir ): array
	{
		if ( !isset( $extra['autoload-by-attr'] ) ) {
			$extra['autoload-by-attr'] = [];
		}

		if ( !is_array( $extra['autoload-by-attr'] ) ) {
			$extra['autoload-by-attr'] = [ 'patterns' => $extra['autoload-by-attr'] ];
		}

		$config = array_merge( [
			'attribute' => "AutoRun",
			'patterns'  => [ '*' ],
			'output'    => 'autoload_bootstrap.php',
			'cwd'       => dirname( $vendorDir ),
			'cwf'       => NULL,
			'force'     => TRUE,
			'cleanup'   => TRUE,
			'runtime'   => FALSE,
			'phpOnly'   => TRUE,
			'maxDepth'  => 25,
		], $extra['autoload-by-attr'] );

		if ( is_string( $config['patterns'] ) ) {
			$config['patterns'] = explode( "\n", $config['patterns'] );
		}

		$config['attribute'] = new Attribute( $config['attribute'] );

		if ( !IncludeFile::is_absolute_path( $config['output'] ) ) {
			$config['output'] = $vendorDir . '/composer/' . $config['output'];
		}
		$config['output'] = IncludeFile::normalize( $config['output'] );

		if ( !IncludeFile::is_absolute_path( $config['cwd'] ) ) {
			$config['cwd'] = IncludeFile::add_trailing_slash( dirname( $vendorDir ) ) . IncludeFile::strip_preceding_slash( $config['cwd'] );
		}
		$config['cwd'] = IncludeFile::normalize( $config['cwd'] );

		return $config;
	}

	/**
	 * Get PHP files matching configured patterns.
	 *
	 * @param string $baseDir Base directory to scan
	 * @param array  $config  Plugin config with patterns, phpOnly, maxDepth
	 *
	 * @return array<string, \SplFileInfo> File paths mapped to SplFileInfo
	 */
	public static function getFiles ( string $baseDir, array $config ): array
	{
		$includeFiles = new IncludeFile( $config['patterns'] );

		$baseDir = IncludeFile::strip_trailing_slash( IncludeFile::normalize( $baseDir ) );

		$phpFiles = IncludeFile::get_files( $baseDir, [
			'filter'   => self::getCallbackFilter( $baseDir, $config['phpOnly'], $includeFiles ),
			'maxDepth' => $config['maxDepth'],
		] );

		return iterator_to_array( $phpFiles );
	}

	/**
	 * Scan configured file system for attribute candidates.
	 *
	 * @param \SplFileInfo[] $phpFiles
	 * @param array{
	 *     attribute: Attribute,
	 *     patterns: string|string[],
	 *     output: string,
	 *     cwd: string,
	 *     cwf: ?string,
	 *     force: bool,
	 *     cleanup: bool,
	 *     runtime: bool,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * }                     $config Plugin config
	 *
	 * @return Entry[] All discovered entries
	 */
	public static function getEntries ( array $phpFiles, array $config ): array
	{
		$entries = [];

		$attributeName = $config['attribute']->getName();

		/** @var \SplFileInfo $fileInfo */
		foreach ( $phpFiles as $phpFile => $fileInfo ) {
			$entries = array_merge( $entries, self::scanFile( $phpFile, $attributeName, $config ) );
		}

		return $entries;
	}

	/**
	 * Check if bootstrap needs regeneration.
	 *
	 * Stale if: output missing but files exist, or any source newer than output.
	 *
	 * @param string         $outputFile Bootstrap file path
	 * @param \SplFileInfo[] $phpFiles   Source files to check
	 * @param string|null    $cwf        Optional caller file to include in mtime check
	 *
	 * @return bool True if regeneration needed
	 */
	public static function isStale ( string $outputFile, array $phpFiles, ?string $cwf = NULL )
	{
		if ( !file_exists( $outputFile ) !== empty( $phpFiles ) ) {
			return TRUE;
		}

		if ( empty( $phpFiles ) ) {
			return FALSE;
		}

		$mtime    = filemtime( $outputFile );
		$mTimeMax = max( array_map( fn( \SplFileInfo $f ) => $f->getMTime(), $phpFiles ) );
		if ( !empty( $cwf ) ) {
			$mTimeMax = max( $mTimeMax, filemtime( $cwf ) );
		}

		return $mtime <= $mTimeMax;
	}

	/**
	 * Write bootstrap file, or clean up if empty.
	 *
	 * Applies defaults from config attribute, sorts entries, renders content.
	 * Deletes output if entries empty and cleanup enabled.
	 *
	 * @param array   $config  Plugin config with output path and attribute defaults
	 * @param Entry[] $entries Discovered entries to render
	 *
	 * @return int|bool|null Bytes written, true if deleted, null if no action
	 */
	public static function makeFile ( $config, $entries )
	{
		if ( empty( $entries ) ) {
			if ( file_exists( $config['output'] ) ) {
				if ( $config['cleanup'] ) {
					return @unlink( $config['output'] );
				}
			}
			else {
				return NULL;
			}
		}

		$defaults = array_filter( [
			'priority' => $config['attribute']->getArgument( 'priority', 'float', 0 ),
			'import'   => Import::parse( $config['attribute']->getArgument( 'import', 'string' ) ),
			'check'    => $config['attribute']->getArgument( 'check', 'bool' ),
		], fn( $v ) => $v !== NULL );

		$entries = CallBuilder::build( $entries, $defaults ?: NULL );
		$content = BootstrapRenderer::render( $entries );

		return Filesystem::writeFile( $config['output'], $content );
	}

	/**
	 * Build file filter callback for directory scanning.
	 *
	 * When phpOnly=true, filters to .php files matching patterns.
	 * Optimizes directory traversal via terminating directory patterns.
	 *
	 * @param string      $baseDir      Base directory for relative path calculation
	 * @param bool        $phpOnly      Filter to .php files only
	 * @param IncludeFile $includeFiles Pattern matcher instance
	 *
	 * @return callable|false Filter callback or false for no filtering
	 */
	public static function getCallbackFilter ( string $baseDir, bool $phpOnly, IncludeFile $includeFiles ): callable|false
	{
		if ( !$phpOnly ) {
			return $includeFiles->getDefaultCallbackFilter( $baseDir );
		}

		$includeDirs = IncludeFile::get_terminating_directory_instance( $includeFiles->patterns );

		$baseDir = IncludeFile::add_trailing_slash( IncludeFile::normalize( $baseDir ) );

		return function ( $fileInfo, $absPath ) use ( $baseDir, $includeFiles, $includeDirs ): bool {
			$relPath = IncludeFile::strip_base( $absPath, $baseDir );

			if ( is_dir( $absPath ) ) {
				return $includeDirs ? $includeDirs->includes( $relPath ) : TRUE;
			}

			if ( !str_ends_with( strtolower( $relPath ), '.php' ) ) {
				return FALSE;
			}

			return $includeFiles->includes( $relPath );
		};
	}

	/**
	 * Scan single PHP file for matching attributes.
	 *
	 * Uses reflection if file is safe, falls back to token parsing otherwise.
	 *
	 * @param string $filePath      Absolute file path
	 * @param string $attributeName Attribute to match
	 *
	 * @return Entry[] Discovered entries from file
	 */
	public static function scanFile ( string $filePath, string $attributeName, array $config ): array
	{
		if ( ( $code = @file_get_contents( $filePath ) ) === FALSE ) {
			return [];
		}

		$useReflection = !$config['runtime'] && CodeAnalyzer::isSafe( $code );
		$candidates    = TokenScanner::scan( $code, $filePath );

		if ( empty( $candidates ) ) {
			return [];
		}

		$relevantCandidates = array_filter(
			$candidates,
			fn( Entry $e ) => Entry::matches( $e->attribute, $attributeName ) && $e->isCallable(),
		);

		if ( empty( $relevantCandidates ) ) {
			return [];
		}

		if ( $useReflection ) {
			require_once $filePath;

			return ReflectionScanner::scan( $relevantCandidates, $attributeName );
		}

		return $relevantCandidates;
	}
}
