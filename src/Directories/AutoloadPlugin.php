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

		$config  = $this->getConfig( $extra );
		$entries = $this->getEntries( $config['cwd'], $config );

		if ( empty( $entries ) ) {
			if ( file_exists( $config['output'] ) ) {
				@unlink( $config['output'] );
			}

			$this->io->write( '<info>Render Autoloader:</info> No directory autoload files found.' );

			return NULL;
		}

		$content = BootstrapRenderer::render( $entries );

		Filesystem::writeFile( $config['output'], $content );

		$this->io->write( sprintf(
			'<info>Render Autoloader:</info> Generated %s with %d file(s).',
			$config['output'],
			count( $entries ),
		) );

		return $config['output'];
	}

	/**
	 * Get plugin configuration from composer.json extra.
	 *
	 * @param array $extra Composer extra config
	 *
	 * @return array{
	 *     patterns: string[],
	 *     import: Import,
	 *     output: string,
	 *     cwd: string,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * } Directory autoload config
	 */
	private function getConfig ( array $extra ): array
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
			'cwd'      => dirname( $this->vendorDir ),
			'phpOnly'  => TRUE,
			'maxDepth' => 25,
		], $extra['autoload-by-dir'] );

		if ( is_string( $config['patterns'] ) ) {
			$config['patterns'] = explode( "\n", $config['patterns'] );
		}

		$config['import'] = Import::parse( $config['import'] ) ?? Import::RequireOnce;

		if ( !IncludeFile::is_absolute_path( $config['output'] ) ) {
			$config['output'] = $this->vendorDir . '/composer/' . $config['output'];
		}
		$config['output'] = IncludeFile::normalize( $config['output'] );

		if ( !IncludeFile::is_absolute_path( $config['cwd'] ) ) {
			$config['cwd'] = IncludeFile::add_trailing_slash( dirname( $this->vendorDir ) ) . IncludeFile::strip_preceding_slash( $config['cwd'] );
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
	private function getEntries ( string $baseDir, array $config ): array
	{
		$entries = [];

		$includeFiles = new IncludeFile( $config['patterns'] );

		$baseDir = IncludeFile::strip_trailing_slash( IncludeFile::normalize( $baseDir ) );

		$phpFiles = IncludeFile::get_files( $baseDir, [
			'filter'   => $this->getCallbackFilter( $baseDir, $config['phpOnly'], $includeFiles ),
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

	public function getCallbackFilter ( string $baseDir, bool $phpOnly, IncludeFile $includeFiles ): callable|false
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
