<?php

declare( strict_types=1 );

namespace Render\Autoloader\Directories;

use Render\Autoloader\BootstrapRenderer;
use Render\Autoloader\Entry;
use Render\Autoloader\Filesystem;
use Render\Autoloader\Import;

use Render\IncludeFile;

use Composer\IO\IOInterface;

class AutoloadPlugin
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
	 * Scans file system for configured PHP files, generates bootstrap, registers with autoloader.
	 *
	 * @param array $extra Composer extra config
	 *
	 * @return ?string Path to generated bootstrap file or null if no files found
	 */
	public function run ( array $extra ): ?string
	{
		if ( !isset( $extra['autoload-by-dir'] ) ) {
			return NULL;
		}

		$this->io->write( '<info>Render Autoloader:</info> Scanning for directory autoload files...' );

		$result = self::build( $extra, $this->vendorDir );

		if ( empty( $result['path'] ) ) {
			$this->io->write( '<info>Render Autoloader:</info> No directory autoload files found.' );

			return NULL;
		}

		$this->io->write( sprintf(
			'<info>Render Autoloader:</info> Generated %s with %d file(s).',
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
	 * @return string|null Path to generated bootstrap or null if no files
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
	 * @return string|null Path to generated bootstrap or null if no files
	 */
	public static function runtime ( string $vendorDir, string|array $config ): ?string
	{
		if ( !IncludeFile::is_absolute_path( $vendorDir ) ) {
			throw new \InvalidArgumentException( "Invalid vendorDir, must be an absolute directory: {$vendorDir}" );
		}

		$config = array_merge( [
			'force'   => FALSE,
			'cleanup' => FALSE,
		], is_array( $config ) ? $config : [ 'patterns' => $config ] );

		$extra = [ 'autoload-by-dir' => $config ];

		return self::build( $extra, IncludeFile::normalize( $vendorDir ) )['path'];
	}

	/**
	 * Core build orchestration.
	 *
	 * Scans files, checks staleness, generates bootstrap if needed.
	 *
	 * @param array  $extra     Composer extra config (must contain autoload-by-dir)
	 * @param string $vendorDir Absolute path to vendor directory
	 *
	 * @return array{path: ?string, count: int} Output path (null if none) and entry count
	 */
	public static function build ( array $extra, string $vendorDir ): array
	{
		if ( !isset( $extra['autoload-by-dir'] ) ) {
			return [ 'path' => NULL, 'count' => 0 ];
		}

		$config  = self::getConfig( $extra, $vendorDir );
		$entries = self::getEntries( $config['cwd'], $config );
		if ( $config['force'] || self::isStale( $config['output'], $entries, $config['cwf'] ) ) {
			self::makeFile( $config, $entries );
		}

		$outputFile = file_exists( $config['output'] ) ? $config['output'] : NULL;

		return [ 'path' => $outputFile, 'count' => count( $entries ) ];
	}

	/**
	 * Get plugin configuration from composer.json extra.
	 *
	 * @param array $extra Composer extra config
	 *
	 * @return array{
	 *     patterns: string|string[],
	 *     import: Import,
	 *     output: string,
	 *     header: string|string[],
	 *     footer: string|string[],
	 *     generatedAt: ?string,
	 *     cwd: string,
	 *     cwf: string|int|null,
	 *     force: bool,
	 *     cleanup: bool,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * } Directory autoload config
	 */
	public static function getConfig ( array $extra, string $vendorDir ): array
	{
		if ( !isset( $extra['autoload-by-dir'] ) ) {
			$extra['autoload-by-dir'] = [];
		}

		if ( !is_array( $extra['autoload-by-dir'] ) ) {
			$extra['autoload-by-dir'] = [ 'patterns' => $extra['autoload-by-dir'] ];
		}

		$config = array_merge( [
			'patterns'    => [ '*' ],
			'import'      => 'require_once',
			'output'      => 'autoload_directory_files.php',
			'header'      => '',
			'footer'      => '',
			'generatedAt' => NULL,
			'cwd'         => dirname( $vendorDir ),
			'cwf'         => NULL,
			'force'       => TRUE,
			'cleanup'     => TRUE,
			'phpOnly'     => TRUE,
			'maxDepth'    => 25,
		], $extra['autoload-by-dir'] );

		if ( is_string( $config['patterns'] ) ) {
			$config['patterns'] = explode( "\n", $config['patterns'] );
		}

		$config['import'] = Import::parse( $config['import'] ) ?? Import::RequireOnce;

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
	 * Scan configured file system for PHP files matching configured patterns.
	 *
	 * @param string $baseDir Base directory to scan
	 * @param array{
	 *     patterns: string|string[],
	 *     import: Import,
	 *     output: string,
	 *     header: string|string[],
	 *     footer: string|string[],
	 *     generatedAt: ?string,
	 *     cwd: string,
	 *     cwf: string|int|null,
	 *     force: bool,
	 *     cleanup: bool,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * }             $config  Plugin config
	 *
	 * @return Entry[] All discovered file import entries
	 */
	public static function getEntries ( string $baseDir, array $config ): array
	{
		$entries = [];

		$includeFiles = new IncludeFile( $config['patterns'] );

		$baseDir = IncludeFile::strip_trailing_slash( IncludeFile::normalize( $baseDir ) );

		$phpFiles = IncludeFile::get_files( $baseDir, [
			'filter'   => $includeFiles->makeFilter( $baseDir, [
				'file' => $config['phpOnly'] ? ( fn( $fileInfo ): ?bool => strtolower( $fileInfo->getExtension() ) === 'php' ? NULL : FALSE ) : NULL,
			] ),
			'maxDepth' => $config['maxDepth'],
		] );

		/** @var \SplFileInfo $fileInfo */
		foreach ( $phpFiles as $phpFile => $fileInfo ) {
			$entries[] = new Entry(
				type: 'file',
				name: $phpFile,
				class: NULL,
				isStatic: FALSE,
				priority: NULL,
				file: $phpFile,
				line: 1,
				attribute: NULL,
				import: $config['import'],
				check: FALSE,
				mtime: $fileInfo->getMTime(),
			);
		}

		usort( $entries, fn( $a, $b ) => strcmp( $a->file, $b->file ) );

		return $entries;
	}

	/**
	 * Check if bootstrap needs regeneration.
	 *
	 * Stale if: output missing but entries exist, or any source newer than output.
	 *
	 * @param string          $outputFile Bootstrap file path
	 * @param Entry[]         $entries    Entries with mtime to check
	 * @param string|int|null $cwf        Optional caller file or mtime to include in mtime check
	 *
	 * @return bool True if regeneration needed
	 */
	public static function isStale ( string $outputFile, array $entries, string|int|null $cwf = NULL )
	{
		if ( !file_exists( $outputFile ) !== empty( $entries ) ) {
			return TRUE;
		}

		if ( empty( $entries ) ) {
			return FALSE;
		}

		$mtime    = filemtime( $outputFile );
		$mTimeMax = max( array_map( fn( Entry $e ) => $e->mtime, $entries ) );
		if ( !empty( $cwf ) ) {
			$mTimeMax = max( $mTimeMax, is_string( $cwf ) ? filemtime( $cwf ) : $cwf );
		}

		return $mtime <= $mTimeMax;
	}

	/**
	 * Write bootstrap file or clean up if empty.
	 *
	 * Deletes output if entries empty and cleanup enabled.
	 *
	 * @param array   $config  Plugin config with output path and cleanup flag
	 * @param Entry[] $entries Discovered file entries to render
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

		return Filesystem::writeFile( $config['output'], BootstrapRenderer::render( $entries, $config ) );
	}

}
