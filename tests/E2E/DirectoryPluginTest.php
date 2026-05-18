<?php

declare( strict_types=1 );

use Composer\IO\NullIO;
use Render\Autoloader\Directories\AutoloadPlugin;

function dirFixtureBase (): string
{
	return dirname( __DIR__ ) . '/Fixtures/e2e/DirectoryPlugin/basic';
}

function dirProjectDir (): string
{
	return dirFixtureBase() . '/project-dir';
}

function dirVendorDir (): string
{
	return dirProjectDir() . '/vendor';
}

function dirCleanupGeneratedFiles (): void
{
	$composerDir = dirVendorDir() . '/composer';

	if ( !is_dir( $composerDir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $composerDir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST,
	);

	foreach ( $iterator as $item ) {
		$item->isDir()
			? rmdir( $item->getPathname() )
			: unlink( $item->getPathname() );
	}

	rmdir( $composerDir );
}

function dirRunPlugin ( array $config ): array
{
	$path = ( new AutoloadPlugin( new NullIO(), dirVendorDir() ) )->run( [
		'autoload-by-dir' => $config,
	] );

	return [
		'path'    => $path,
		'content' => $path ? file_get_contents( $path ) : NULL,
	];
}

function dirNormalizeBootstrap ( string $content ): string
{
	$content = str_replace( "\r\n", "\n", $content );
	$content = str_replace( str_replace( '\\', '/', dirProjectDir() ), '<project-dir>', $content );
	$content = preg_replace( '/Generated: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', 'Generated: <timestamp>', $content );

	return rtrim( $content ) . "\n";
}

function dirExpectedBootstrap ( string $file ): string
{
	$content = file_get_contents( dirFixtureBase() . '/expected/' . $file );

	return str_replace( "\r\n", "\n", $content );
}

beforeEach( function () {
	dirCleanupGeneratedFiles();
} );

afterEach( function () {
	dirCleanupGeneratedFiles();
} );

describe( 'DirectoryPlugin e2e', function () {

	it( 'generates the expected bootstrap file', function ( array $config, string $outputFile, string $expectedFile ) {
		$result = dirRunPlugin( $config );

		$result['path'] = str_replace( '\\', '/', $result['path'] );

		expect( $result['path'] )->toBe( str_replace( '\\', '/', dirVendorDir() . '/composer/' . $outputFile ) );
		expect( $result['path'] )->toBeFile();
		expect( $result['content'] )->toBeString();
		expect( dirNormalizeBootstrap( $result['content'] ) )->toBe( dirExpectedBootstrap( $expectedFile ) );
	} )->with( ( function () {
		$cases = [];

		foreach ( glob( dirFixtureBase() . '/expected/*.json' ) ?: [] as $jsonFile ) {
			$name         = basename( $jsonFile, '.json' );
			$phpFile      = $name . '.php';
			$json         = json_decode( file_get_contents( $jsonFile ), TRUE, flags: JSON_THROW_ON_ERROR );
			$config       = $json['config'] ?? $json;
			$output       = $config['output'] ?? 'autoload_directory_files.php';
			$cases[$name] = [ $config, $output, $phpFile ];
		}

		return $cases;
	} )() );

	it( 'returns null when no matching entries are found', function ( array $config ) {
		$result = dirRunPlugin( $config );

		expect( $result['path'] )->toBeNull();
		expect( $result['content'] )->toBeNull();
		expect( glob( dirVendorDir() . '/composer/e2e-*.php' ) ?: [] )->toBeEmpty();
	} )->with( [
		'missing directory'                     => [
			[
				'patterns' => [ 'missing-dir' ],
				'output'   => 'e2e-missing.php',
			],
		],
		'no php files'                          => [
			[
				'patterns' => [ 'no-extension' ],
				'output'   => 'e2e-no-php.php',
			],
		],
		'no match because of one level check'   => [
			[
				'patterns' => [ 'src/Deep/*' ],
				'output'   => 'e2e-deep-one-level.php',
			],
		],
		'no match because of one level check 1' => [
			[
				'patterns' => [ 'src/Deep/Nested/*' ],
				'output'   => 'e2e-nested-deep-one-level.php',
			],
		],
	] );

	it( 'removes stale output when no matching entries are found', function () {
		$output = dirVendorDir() . '/composer/e2e-stale.php';

		mkdir( dirname( $output ), recursive: TRUE );
		file_put_contents( $output, '<?php // stale' );

		$result = dirRunPlugin( [
			'patterns' => [ 'missing-dir' ],
			'output'   => 'e2e-stale.php',
		] );

		expect( $result['path'] )->toBeNull();
		expect( $output )->not->toBeFile();
	} );

	it( 'returns null when config key is missing', function () {
		$path = ( new AutoloadPlugin( new NullIO(), dirVendorDir() ) )->run( [] );

		expect( $path )->toBeNull();
	} );

} );
