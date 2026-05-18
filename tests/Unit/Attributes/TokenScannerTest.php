<?php

declare( strict_types=1 );

use Render\Autoloader\Entry;
use Render\Autoloader\Attributes\TokenScanner;

describe( 'TokenScanner', function () {

	describe( 'scan', function () {

		it( 'detects attribute targets', function ( string $code, array $expected ) {
			$candidates = TokenScanner::scan( php( $code ), '/test.php' );

			expect( $candidates )->toHaveCount( count( $expected ) );

			foreach ( $expected as $i => $exp ) {
				expect( $candidates[$i] )->toBeInstanceOf( Entry::class );
				foreach ( $exp as $key => $value ) {
					expect( $candidates[$i]->$key )->toBe( $value, "Mismatch on {$key}" );
				}
			}
		} )->with( [
			'function with attribute'    => [
				"#[Autoload]\nfunction bootstrap() {}",
				[ [ 'type' => 'function', 'name' => 'bootstrap', 'attribute' => 'Autoload' ] ],
			],
			'static method'              => [
				"class Service {\n    #[Autoload]\n    public static function init() {}\n}",
				[ [ 'type' => 'method', 'name' => 'init', 'class' => 'Service', 'isStatic' => TRUE ] ],
			],
			'non-static method'          => [
				"class Service {\n    #[Autoload]\n    public function init() {}\n}",
				[ [ 'type' => 'method', 'name' => 'init', 'isStatic' => FALSE ] ],
			],
			'positional priority'        => [
				"#[Autoload(5)]\nfunction bootstrap() {}",
				[ [ 'priority' => 5.0 ] ],
			],
			'named priority'             => [
				"#[Autoload(priority: 15)]\nfunction bootstrap() {}",
				[ [ 'priority' => 15.0 ] ],
			],
			'namespaced attribute'       => [
				"#[App\\Attributes\\Autoload]\nfunction bootstrap() {}",
				[ [ 'attribute' => 'App\\Attributes\\Autoload' ] ],
			],
			'fully qualified attr'       => [
				"#[\\App\\Attributes\\Autoload]\nfunction bootstrap() {}",
				[ [ 'attribute' => '\\App\\Attributes\\Autoload' ] ],
			],
			'no priority (null)'         => [
				"#[Autoload]\nfunction bootstrap() {}",
				[ [ 'priority' => NULL ] ],
			],
			'namespaced class FQCN'      => [
				"namespace App\\Services;\n\nclass Logger {\n    #[Autoload]\n    public static function register() {}\n}",
				[ [ 'class' => 'App\\Services\\Logger' ] ],
			],
			'no attribute (empty)'       => [
				"function foo() {}",
				[],
			],
			'multiple candidates'        => [
				"namespace App;\n\n#[Autoload(1)]\nfunction first() {}\n\nclass Service {\n    #[Autoload(2)]\n    public static function init() {}\n}\n\n#[Autoload(3)]\nfunction last() {}",
				[
					[ 'priority' => 1.0 ],
					[ 'priority' => 2.0 ],
					[ 'priority' => 3.0 ],
				],
			],

			// Edge cases: attributes should NOT leak or be misapplied
			'attr on class no leak'      => [
				"#[ClassAttr]\nclass Foo {\n    public function bar() {}\n}",
				[],
			],
			'attr on class + method'     => [
				"#[ClassAttr]\nclass Foo {\n    #[MethodAttr]\n    public static function bar() {}\n}",
				[ [ 'name' => 'bar', 'attribute' => 'MethodAttr' ] ],
			],
			'attr on property skip'      => [
				"class Foo {\n    #[PropAttr]\n    public int \$x;\n    #[Autoload]\n    public static function init() {}\n}",
				[ [ 'name' => 'init', 'attribute' => 'Autoload' ] ],
			],

			// Access modifiers and signatures
			'private static method'      => [
				"class Foo {\n    #[Autoload]\n    private static function init() {}\n}",
				[ [ 'name' => 'init', 'isStatic' => TRUE ] ],
			],
			'protected static method'    => [
				"class Foo {\n    #[Autoload]\n    protected static function init() {}\n}",
				[ [ 'name' => 'init', 'isStatic' => TRUE ] ],
			],
			'method with return type'    => [
				"class Foo {\n    #[Autoload]\n    public static function init(): void {}\n}",
				[ [ 'name' => 'init' ] ],
			],
			'method with params'         => [
				"class Foo {\n    #[Autoload]\n    public static function init(\$a, int \$b = 5): void {}\n}",
				[ [ 'name' => 'init' ] ],
			],
			'final static method'        => [
				"class Foo {\n    #[Autoload]\n    final public static function init() {}\n}",
				[ [ 'name' => 'init', 'isStatic' => TRUE ] ],
			],

			// Trait and interface
			'trait method'               => [
				"trait MyTrait {\n    #[Autoload]\n    public static function init() {}\n}",
				[ [ 'name' => 'init', 'class' => 'MyTrait' ] ],
			],
			'interface method'           => [
				"interface MyInterface {\n    #[Autoload]\n    public function init();\n}",
				[ [ 'name' => 'init', 'class' => 'MyInterface', 'isStatic' => FALSE ] ],
			],

			// Multiple attribute groups should all be captured
			'multi attr groups'          => [
				"#[First]\n#[Second(5)]\nfunction foo() {}",
				[
					[ 'attribute' => 'First', 'priority' => NULL ],
					[ 'attribute' => 'Second', 'priority' => 5.0 ],
				],
			],
			'autoload before other attr' => [
				"#[Autoload(10)]\n#[Other]\nfunction foo() {}",
				[
					[ 'attribute' => 'Autoload', 'priority' => 10.0 ],
					[ 'attribute' => 'Other', 'priority' => NULL ],
				],
			],

			// Edge cases for coverage
			'attr with nested array'     => [
				"#[Attr([[1, 2], [3, 4]])]\nfunction foo() {}",
				[ [ 'name' => 'foo', 'attribute' => 'Attr' ] ],
			],
			'attr with constant prio'    => [
				"#[Autoload(PHP_EOL)]\nfunction foo() {}",
				[ [ 'name' => 'foo', 'priority' => NULL ] ],
			],
			'anonymous function'         => [
				'$fn = #[Attr] function() {};',
				[],
			],
		] );

	} );

} );
