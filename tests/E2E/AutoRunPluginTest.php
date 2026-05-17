<?php

declare( strict_types=1 );

use Composer\IO\NullIO;
use Render\Autoloader\Attributes\AutoRunPlugin;

function fileFixtureRoot (): string
{
	return dirname( __DIR__ ) . '/Fixtures/e2e/AutoloadPlugin/basic-project';
}

function fileVendorDir (): string
{
	return fileFixtureRoot() . '/vendor';
}

function fileCleanupGeneratedFiles (): void
{
	$vendorDir = fileVendorDir();

	if ( !is_dir( $vendorDir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $vendorDir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST,
	);

	foreach ( $iterator as $item ) {
		$item->isDir()
			? rmdir( $item->getPathname() )
			: unlink( $item->getPathname() );
	}

	rmdir( $vendorDir );
}

function runAutoRunPlugin ( array $config ): array
{
	$path = ( new AutoRunPlugin( new NullIO(), fileVendorDir() ) )->run( [
		'autoload-by-attr' => $config,
	] );

	return [
		'path'    => $path,
		'content' => $path ? file_get_contents( $path ) : NULL,
	];
}

function fileNormalizeBootstrap ( string $content ): string
{
	$content = str_replace( "\r\n", "\n", $content );
	$content = str_replace( str_replace( '\\', '/', fileFixtureRoot() ), '<fixture-root>', $content );
	$content = preg_replace( '/Generated: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', 'Generated: <timestamp>', $content );

	return rtrim( $content ) . "\n";
}

function fileExpectedBootstrap ( string $file ): string
{
	$content = file_get_contents( fileFixtureRoot() . '/expected/' . $file );

	return str_replace( "\r\n", "\n", $content );
}

beforeEach( function () {
	fileCleanupGeneratedFiles();
} );

afterEach( function () {
	fileCleanupGeneratedFiles();
} );

describe( 'AutoRunPlugin e2e', function () {

	it( 'generates the expected bootstrap file', function ( array $config, string $outputFile, string $expectedFile ) {
		$result = runAutoRunPlugin( $config );

		$result['path'] = str_replace( '\\', '/', $result['path'] );

		expect( $result['path'] )->toBe( str_replace( '\\', '/', fileVendorDir() . '/composer/' . $outputFile ) );
		expect( $result['path'] )->toBeFile();
		expect( $result['content'] )->toBeString();
		expect( fileNormalizeBootstrap( $result['content'] ) )->toBe( fileExpectedBootstrap( $expectedFile ) );
	} )->with( ( function () {
		$cases = [];

		foreach ( glob( fileFixtureRoot() . '/expected/*.json' ) ?: [] as $jsonFile ) {
			$name         = basename( $jsonFile, '.json' );
			$phpFile      = $name . '.php';
			$json         = json_decode( file_get_contents( $jsonFile ), TRUE, flags: JSON_THROW_ON_ERROR );
			$config       = $json['config'] ?? $json;
			$output       = $config['output'] ?? 'autoload_bootstrap.php';
			$cases[$name] = [ $config, $output, $phpFile ];
		}

		return $cases;
	} )() );

	it( 'returns null when no matching entries are found', function ( array $config ) {
		$result = runAutoRunPlugin( $config );

		expect( $result['path'] )->toBeNull();
		expect( $result['content'] )->toBeNull();
		expect( glob( fileVendorDir() . '/composer/e2e-*.php' ) ?: [] )->toBeEmpty();
	} )->with( [
		'missing directory' => [
			[
				'attribute' => 'AutoRun',
				'patterns'  => [ 'missing' ],
				'output'    => 'e2e-missing.php',
			],
		],
		'wrong attribute'   => [
			[
				'attribute' => 'OtherAttribute',
				'patterns'  => [ 'src' ],
				'output'    => 'e2e-wrong-attribute.php',
			],
		],
	] );

} );
