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

		$root      = dirname( $composerJson );
		$vendorDir = $json['config']['vendor-dir'] ?? 'vendor';
		$vendorDir = IncludeFile::is_absolute_path( $vendorDir )
			? $vendorDir
			: IncludeFile::add_trailing_slash( $root ) . IncludeFile::strip_preceding_slash( $vendorDir );

		return self::build( $json['extra'] ?? [], IncludeFile::normalize( $vendorDir ) )['path'];
	}

	public static function build ( array $extra, string $vendorDir ): array
	{
		if ( !isset( $extra['autoload-by-dir'] ) ) {
			return [ 'path' => NULL, 'count' => 0 ];
		}

		$config  = self::getConfig( $extra, $vendorDir );
		$entries = self::getEntries( $config['cwd'], $config );

		if ( empty( $entries ) ) {
			if ( file_exists( $config['output'] ) ) {
				@unlink( $config['output'] );
			}

			return [ 'path' => NULL, 'count' => 0 ];
		}

		Filesystem::writeFile( $config['output'], BootstrapRenderer::render( $entries ) );

		return [ 'path' => $config['output'], 'count' => count( $entries ) ];
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
	 *     cwd: string,
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
			'patterns' => [ '*' ],
			'import'   => 'require_once',
			'output'   => 'autoload_directory_files.php',
			'cwd'      => dirname( $vendorDir ),
			'phpOnly'  => TRUE,
			'maxDepth' => 25,
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
	 *     cwd: string,
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
			'filter'   => self::getCallbackFilter( $baseDir, $config['phpOnly'], $includeFiles ),
			'maxDepth' => $config['maxDepth'],
		] );

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
			);
		}

		usort( $entries, fn( $a, $b ) => strcmp( $a->file, $b->file ) );

		return $entries;
	}

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
}
