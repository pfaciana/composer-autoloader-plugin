<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

use Render\Autoloader\Import;
use Render\Autoloader\CallBuilder;
use Render\Autoloader\CodeAnalyzer;
use Render\Autoloader\BootstrapRenderer;
use Render\Autoloader\Filesystem;
use Render\Autoloader\Entry;

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

		$config  = $this->getConfig( $extra );
		$entries = $this->getEntries( $config['cwd'], $config );

		if ( empty( $entries ) ) {
			$this->io->write( '<info>Render Autoloader:</info> No #[AutoRun] attributes found.' );

			return NULL;
		}

		$defaults = array_filter( [
			'priority' => $config['attribute']->getArgument( 'priority', 'float', 0 ),
			'import'   => Import::parse( $config['attribute']->getArgument( 'import', 'string' ) ),
			'check'    => $config['attribute']->getArgument( 'check', 'bool' ),
		], fn( $v ) => $v !== NULL );

		$entries = CallBuilder::build( $entries, $defaults ?: NULL );
		$content = BootstrapRenderer::render( $entries );

		Filesystem::writeFile( $config['output'], $content );

		$this->io->write( sprintf(
			'<info>Render Autoloader:</info> Generated %s with %d call(s).',
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
	 *     attribute: Attribute,
	 *     patterns: string[],
	 *     output: string,
	 *     cwd: string,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * } Attribute config
	 */
	private function getConfig ( array $extra ): array
	{
		$config = array_merge( [
			'attribute' => "AutoRun",
			'patterns'  => [ 'src' ],
			'output'    => 'autoload_bootstrap.php',
			'cwd'       => dirname( $this->vendorDir ),
			'phpOnly'   => TRUE,
			'maxDepth'  => 25,
		], $extra['autoload-by-attr'] ?? [] );

		if ( is_string( $config['patterns'] ) ) {
			$config['patterns'] = explode( "\n", $config['patterns'] );
		}

		$config['attribute'] = new Attribute( $config['attribute'] );

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
	 * Scan configured file system for attribute candidates.
	 *
	 * @param string $baseDir Base directory to scan
	 * @param array{
	 *     attribute: Attribute,
	 *     patterns: string[],
	 *     output: string,
	 *     cwd: string,
	 *     phpOnly: bool,
	 *     maxDepth: int,
	 * }             $config  Plugin config
	 *
	 * @return Entry[] All discovered entries
	 */
	private function getEntries ( string $baseDir, array $config ): array
	{
		$entries = [];

		$attributeName = $config['attribute']->getName();

		$includeFiles = new IncludeFile( $config['patterns'] );

		$baseDir = IncludeFile::strip_trailing_slash( IncludeFile::normalize( $baseDir ) );

		$phpFiles = IncludeFile::get_files( $baseDir, [
			'filter'   => $this->getCallbackFilter( $baseDir, $config['phpOnly'], $includeFiles ),
			'maxDepth' => $config['maxDepth'],
		] );

		foreach ( $phpFiles as $phpFile => $fileInfo ) {
			$entries = array_merge( $entries, $this->scanFile( $phpFile, $attributeName ) );
		}

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
	private function scanFile ( string $filePath, string $attributeName ): array
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

		$this->io->write( "<comment>Render Autoloader:</comment> Using token fallback for {$filePath}" );

		return $relevantCandidates;
	}
}
