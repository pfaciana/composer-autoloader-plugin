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
		if ( !isset( $extra['autoload-by-attr'] ) ) {
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

		$defaults = array_filter( [
			'priority' => $config['attribute']->getArgument( 'priority', 'float', 0 ),
			'import'   => Import::parse( $config['attribute']->getArgument( 'import', 'string' ) ),
			'check'    => $config['attribute']->getArgument( 'check', 'bool' ),
		], fn( $v ) => $v !== NULL );

		$entries = CallBuilder::build( $entries, $defaults ?: NULL );
		$content = BootstrapRenderer::render( $entries );

		Filesystem::writeFile( $config['output'], $content );

		return [ 'path' => $config['output'], 'count' => count( $entries ) ];
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
	 * Scan configured file system for attribute candidates.
	 *
	 * @param string $baseDir Base directory to scan
	 * @param array{
	 *     attribute: Attribute,
	 *     patterns: string|string[],
	 *     output: string,
	 *     cwd: string,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * }             $config  Plugin config
	 *
	 * @return Entry[] All discovered entries
	 */
	public static function getEntries ( string $baseDir, array $config ): array
	{
		$entries = [];

		$attributeName = $config['attribute']->getName();

		$includeFiles = new IncludeFile( $config['patterns'] );

		$baseDir = IncludeFile::strip_trailing_slash( IncludeFile::normalize( $baseDir ) );

		$phpFiles = IncludeFile::get_files( $baseDir, [
			'filter'   => self::getCallbackFilter( $baseDir, $config['phpOnly'], $includeFiles ),
			'maxDepth' => $config['maxDepth'],
		] );

		foreach ( $phpFiles as $phpFile => $fileInfo ) {
			$entries = array_merge( $entries, self::scanFile( $phpFile, $attributeName ) );
		}

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
	public static function scanFile ( string $filePath, string $attributeName ): array
	{
		if ( ( $code = @file_get_contents( $filePath ) ) === FALSE ) {
			return [];
		}

		$isSafe     = CodeAnalyzer::isSafe( $code );
		$candidates = TokenScanner::scan( $code, $filePath );

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

		if ( $isSafe ) {
			require_once $filePath;

			return ReflectionScanner::scan( $relevantCandidates, $attributeName );
		}

		return $relevantCandidates;
	}
}
