<?php

declare( strict_types=1 );

use Render\Autoloader\CodeAnalyzer;

describe( 'CodeAnalyzer', function () {

	describe( 'isSafe', function () {

		it( 'returns true for safe code', function ( string $code ) {
			expect( CodeAnalyzer::isSafe( php( $code ) ) )->toBeTrue();
		} )->with( [
			// Basic declarations
			'class only'            => [ 'class Foo {}' ],
			'function only'         => [ 'function foo() {}' ],
			'namespace + class'     => [ "namespace App;\n\nclass Foo {}" ],
			'interface'             => [ 'interface FooInterface {}' ],
			'trait'                 => [ 'trait FooTrait {}' ],
			'enum'                  => [ 'enum Status { case Active; }' ],
			'const'                 => [ 'const FOO = 1;' ],
			'use statement'         => [ "namespace App;\n\nuse Other\\Thing;\n\nclass Foo {}" ],
			'multiple classes'      => [ "class Foo {}\n\nclass Bar {}" ],
			'class with methods'    => [ 'class Foo { public function bar() {} }' ],

			// Attributes (function calls inside should not trigger unsafe)
			'attribute no args'     => [ "#[Autoload]\nfunction foo() {}" ],
			'attribute with args'   => [ "#[Autoload(5)]\nfunction foo() {}" ],
			'attribute named args'  => [ "#[Autoload(priority: 10)]\nfunction foo() {}" ],
			'attribute with array'  => [ "#[Attr([1, 2, 3])]\nfunction foo() {}" ],
			'attribute nested arr'  => [ "#[Attr([[1], [2]])]\nfunction foo() {}" ],
			'multiple attributes'   => [ "#[Attr1]\n#[Attr2(5)]\nfunction foo() {}" ],
			'attr on class method'  => [ "class Foo {\n    #[Autoload(1)]\n    public static function init() {}\n}" ],
			'attr with class const' => [ "#[Attr(Foo::BAR)]\nfunction test() {}" ],
			'attr with new (8.1+)'  => [ "#[Attr(new DateTime)]\nfunction test() {}" ],
			'attr with expression'  => [ "#[Attr(1 + 2)]\nfunction test() {}" ],

			// Arrow functions inside declarations should not trigger unsafe
			'arrow fn as param'     => [ 'function foo($fn = fn() => bar()) {}' ],

			// Content that looks like code but is not executable
			'comment with code'     => [ "// echo 'hello';\nclass Foo {}" ],
			'docblock with code'    => [ "/** echo 'hello'; */\nclass Foo {}" ],

			// Class internals (calls inside methods are safe)
			'method calls fn'       => [ "class Foo {\n    public function bar() { some_function(); }\n}" ],
			'static method calls'   => [ "class Foo {\n    public static function bar() { Other::init(); }\n}" ],
			'property default'      => [ 'class Foo { public array $x = []; }' ],
		] );

		it( 'returns false for unsafe code', function ( string $code ) {
			expect( CodeAnalyzer::isSafe( php( $code ) ) )->toBeFalse();
		} )->with( [
			// Direct statements
			'echo'                   => [ 'echo "hello";' ],
			'print'                  => [ 'print "hello";' ],
			'exit'                   => [ 'exit;' ],
			'die'                    => [ 'die();' ],
			'eval'                   => [ 'eval("code");' ],
			'include'                => [ 'include "file.php";' ],
			'include_once'           => [ 'include_once "file.php";' ],
			'require'                => [ 'require "file.php";' ],
			'require_once'           => [ 'require_once "file.php";' ],

			// Function calls at top level
			'function call'          => [ 'some_function();' ],
			'static call'            => [ 'SomeClass::init();' ],
			'add_action'             => [ "add_action('init', 'callback');" ],
			'chained call'           => [ 'Container::getInstance()->boot();' ],
			'call with arrow fn arg' => [ 'array_map(fn($x) => strtoupper($x), $arr);' ],

			// Object instantiation at top level
			'new with parens'        => [ 'new Bootstrap();' ],
			'new without parens'     => [ 'new Bootstrap;' ],
			'new with args'          => [ "new App('config');" ],
			'new assigned unused'    => [ '$x = new Foo();' ],
			'new assigned no parens' => [ '$x = new Foo;' ],

			// Edge cases for coverage (whitespace before paren)
			'call with space'        => [ 'some_function ();' ],
			'call with newline'      => [ "some_function\n();" ],

			// Top-level assignments and flow control run on require
			'global mutation'        => [ '$GLOBALS["booted"] = true;' ],
			'env mutation'           => [ '$_ENV["APP_MODE"] = "prod";' ],
			'static prop mutation'   => [ 'App\\Config::$loaded = true;' ],
			'conditional mutation'   => [ "if (\$enabled) {\n    \$GLOBALS['x'] = 1;\n}" ],
			'throw'                  => [ 'throw new RuntimeException("bad");' ],
			'return'                 => [ 'return;' ],
			'arrow fn assigned'      => [ '$fn = fn() => some_function();' ],
			'arrow fn with new'      => [ '$fn = fn() => new Foo();' ],
			'arrow fn nested'        => [ '$fn = fn($x) => fn($y) => add($x, $y);' ],
			'arrow fn complex'       => [ '$fn = fn($x, $y) => $x->method() + static_call();' ],
			'anon fn with call'      => [ '$fn = function() { some_function(); };' ],
			'anon fn with new'       => [ '$fn = function() { return new Foo(); };' ],
			'string assignment'      => [ '$x = "echo hello;";' ],
			'heredoc assignment'     => [ "\$x = <<<EOT\necho hello;\nEOT;" ],
		] );

	} );

	describe( 'extractNamespace', function () {

		it( 'extracts namespace', function ( string $code, ?string $expected ) {
			expect( CodeAnalyzer::extractNamespace( php( $code ) ) )->toBe( $expected );
		} )->with( [
			'simple' => [ "namespace App;\n\nclass Foo {}", 'App' ],
			'nested' => [ "namespace App\\Models;\n\nclass Foo {}", 'App\\Models' ],
			'none'   => [ 'class Foo {}', NULL ],
		] );

	} );

} );
