<?php

declare( strict_types=1 );

use Render\Autoloader\Entry;
use Render\Autoloader\CodeAnalyzer;
use Render\Autoloader\Attributes\TokenScanner;

describe( 'Analysis Pipeline (CodeAnalyzer + TokenScanner)', function () {

	it( 'returns combined analysis', function ( string $code, array $expected ) {
		$fullCode   = php( $code );
		$safe       = CodeAnalyzer::isSafe( $fullCode );
		$namespace  = CodeAnalyzer::extractNamespace( $fullCode );
		$candidates = TokenScanner::scan( $fullCode, '/test.php' );

		expect( $safe )->toBe( $expected['safe'] );
		expect( $namespace )->toBe( $expected['namespace'] );
		expect( $candidates )->toHaveCount( count( $expected['candidates'] ) );

		foreach ( $expected['candidates'] as $i => $exp ) {
			expect( $candidates[$i] )->toBeInstanceOf( Entry::class );
			foreach ( $exp as $key => $value ) {
				expect( $candidates[$i]->$key )->toBe( $value, "Candidate {$i}: {$key} mismatch" );
			}
		}
	} )->with( [
		'safe + namespace + candidate'        => [
			"namespace App;\n\n#[Autoload]\nfunction bootstrap() {}",
			[
				'safe'       => TRUE,
				'namespace'  => 'App',
				'candidates' => [
					[ 'type' => 'function', 'name' => 'bootstrap' ],
				],
			],
		],
		'safe + no namespace + candidate'     => [
			"#[Autoload(5)]\nfunction init() {}",
			[
				'safe'       => TRUE,
				'namespace'  => NULL,
				'candidates' => [
					[ 'name' => 'init', 'priority' => 5.0 ],
				],
			],
		],
		'safe + namespace + no candidates'    => [
			"namespace App;\n\nclass Service {}",
			[
				'safe'       => TRUE,
				'namespace'  => 'App',
				'candidates' => [],
			],
		],
		'safe + no namespace + no candidates' => [
			'class Foo {}',
			[
				'safe'       => TRUE,
				'namespace'  => NULL,
				'candidates' => [],
			],
		],
		'unsafe + namespace + candidate'      => [
			"namespace App;\n\necho 'hi';\n\n#[Autoload]\nfunction bootstrap() {}",
			[
				'safe'       => FALSE,
				'namespace'  => 'App',
				'candidates' => [
					[ 'name' => 'bootstrap' ],
				],
			],
		],
		'unsafe + no namespace'               => [
			"some_function();\n\n#[Autoload]\nfunction init() {}",
			[
				'safe'       => FALSE,
				'namespace'  => NULL,
				'candidates' => [
					[ 'name' => 'init' ],
				],
			],
		],
		'multiple candidates mixed types'     => [
			"namespace App;\n\n#[Autoload(1)]\nfunction first() {}\n\nclass Service {\n    #[Autoload(2)]\n    public static function init() {}\n}",
			[
				'safe'       => TRUE,
				'namespace'  => 'App',
				'candidates' => [
					[ 'type' => 'function', 'name' => 'first', 'priority' => 1.0 ],
					[ 'type' => 'method', 'name' => 'init', 'class' => 'App\\Service', 'isStatic' => TRUE, 'priority' => 2.0 ],
				],
			],
		],
		'file path passed through'            => [
			"#[Autoload]\nfunction test() {}",
			[
				'safe'       => TRUE,
				'namespace'  => NULL,
				'candidates' => [
					[ 'file' => '/test.php' ],
				],
			],
		],
	] );

} );