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

function dirEnsureGitHookFixture (): void
{
	$hook = dirProjectDir() . '/.git/hooks/pre-commit.php';

	if ( !is_dir( dirname( $hook ) ) ) {
		mkdir( dirname( $hook ), recursive: TRUE );
	}

	file_put_contents( $hook, '<?php // pre-commit.php' . PHP_EOL );
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

function dirDirectRunPlugin ( array $extra ): array
{
	$composerJson = dirProjectDir() . '/composer.json';
	$original     = file_get_contents( $composerJson );

	file_put_contents( $composerJson, json_encode( [
		'config' => [ 'vendor-dir' => 'vendor' ],
		'extra'  => $extra,
	], JSON_PRETTY_PRINT ) );

	try {
		$path = AutoloadPlugin::directRun( $composerJson );
	}
	finally {
		file_put_contents( $composerJson, $original );
	}

	return [
		'path'    => $path,
		'content' => $path ? file_get_contents( $path ) : NULL,
	];
}

function dirRuntimePlugin ( string|array $config ): array
{
	$path = AutoloadPlugin::runtime( dirVendorDir(), $config );

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
	dirEnsureGitHookFixture();
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

describe( 'AutoloadPlugin::directRun e2e', function () {

	it( 'generates the expected bootstrap file via directRun', function ( array $config, string $outputFile, string $expectedFile ) {
		$result = dirDirectRunPlugin( [ 'autoload-by-dir' => $config ] );

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

	it( 'returns null when no matching entries are found via directRun', function ( array $config ) {
		$result = dirDirectRunPlugin( [ 'autoload-by-dir' => $config ] );

		expect( $result['path'] )->toBeNull();
		expect( $result['content'] )->toBeNull();
		expect( glob( dirVendorDir() . '/composer/e2e-*.php' ) ?: [] )->toBeEmpty();
	} )->with( [
		'missing directory' => [
			[
				'patterns' => [ 'missing-dir' ],
				'output'   => 'e2e-missing.php',
			],
		],
		'no php files'      => [
			[
				'patterns' => [ 'no-extension' ],
				'output'   => 'e2e-no-php.php',
			],
		],
	] );

	it( 'returns null when extra key is missing via directRun', function () {
		$result = dirDirectRunPlugin( [] );

		expect( $result['path'] )->toBeNull();
	} );

	it( 'throws when composer.json is missing', function () {
		AutoloadPlugin::directRun( dirProjectDir() . '/missing-composer.json' );
	} )->throws( InvalidArgumentException::class, 'composer.json not found' );

	it( 'throws when composer.json is invalid JSON', function () {
		$composerJson = dirProjectDir() . '/composer.json';
		$original     = file_get_contents( $composerJson );
		file_put_contents( $composerJson, 'not json {' );

		try {
			AutoloadPlugin::directRun( $composerJson );
		}
		finally {
			file_put_contents( $composerJson, $original );
		}
	} )->throws( InvalidArgumentException::class, 'Invalid composer.json' );

} );

describe( 'AutoloadPlugin::runtime e2e', function () {

	it( 'generates the expected bootstrap file via runtime', function ( array $config, string $outputFile, string $expectedFile ) {
		$config['force'] = TRUE;
		$result          = dirRuntimePlugin( $config );

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

	it( 'skips regeneration when not stale', function () {
		$config = [
			'patterns' => [ 'src' ],
			'output'   => 'e2e-stale-check.php',
			'force'    => TRUE,
		];
		dirRuntimePlugin( $config );

		$output = dirVendorDir() . '/composer/e2e-stale-check.php';
		$now    = time();
		touch( $output, $now );
		touch( dirProjectDir() . '/src/bootstrap.php', $now - 100 );

		$config['force'] = FALSE;
		dirRuntimePlugin( $config );

		expect( filemtime( $output ) )->toBe( $now );
	} );

	it( 'regenerates when source file is newer', function () {
		$config = [
			'patterns' => [ 'src' ],
			'output'   => 'e2e-stale-regen.php',
			'force'    => TRUE,
		];
		dirRuntimePlugin( $config );

		$output = dirVendorDir() . '/composer/e2e-stale-regen.php';
		$now    = time();
		touch( $output, $now - 100 );
		touch( dirProjectDir() . '/src/bootstrap.php', $now );

		$config['force'] = FALSE;
		dirRuntimePlugin( $config );

		expect( filemtime( $output ) )->toBeGreaterThanOrEqual( $now );
	} );

	it( 'writes empty bootstrap when cleanup=FALSE and no entries', function () {
		$output = dirVendorDir() . '/composer/e2e-cleanup-false.php';
		mkdir( dirname( $output ), recursive: TRUE );
		file_put_contents( $output, '<?php // existing' );

		$result = dirRuntimePlugin( [
			'patterns' => [ 'missing-dir' ],
			'output'   => 'e2e-cleanup-false.php',
			'cleanup'  => FALSE,
			'force'    => TRUE,
		] );

		expect( $output )->toBeFile();
		expect( $result['content'] )->not->toContain( 'existing' );
		expect( $result['content'] )->toContain( '<?php' );
	} );

	it( 'deletes output when cleanup=TRUE and no entries', function () {
		$output = dirVendorDir() . '/composer/e2e-cleanup-true.php';
		mkdir( dirname( $output ), recursive: TRUE );
		file_put_contents( $output, '<?php // existing' );

		$result = dirRuntimePlugin( [
			'patterns' => [ 'missing-dir' ],
			'output'   => 'e2e-cleanup-true.php',
			'cleanup'  => TRUE,
			'force'    => TRUE,
		] );

		expect( $result['path'] )->toBeNull();
		expect( $output )->not->toBeFile();
	} );

	it( 'throws when vendorDir is relative', function () {
		AutoloadPlugin::runtime( 'relative/path', [ 'patterns' => 'src' ] );
	} )->throws( InvalidArgumentException::class );

} );
