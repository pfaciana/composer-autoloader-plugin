<?php

declare( strict_types=1 );

use Render\Autoloader\Attributes\Attribute;

describe( 'unwrap', function () {

	it( 'strips attribute syntax', function ( string $input, string $expected ) {
		expect( Attribute::unwrap( $input ) )->toBe( $expected );
	} )->with( [
		'full syntax'          => [ '#[Autoload]', 'Autoload' ],
		'full with args'       => [ '#[Autoload(5)]', 'Autoload(5)' ],
		'partial (trailing ])' => [ 'Autoload]', 'Autoload' ],
		'bare name'            => [ 'Autoload', 'Autoload' ],
		'bare with args'       => [ 'Autoload(5)', 'Autoload(5)' ],
		'whitespace'           => [ '  #[Autoload]  ', 'Autoload' ],
		'only prefix'          => [ '#[Autoload', 'Autoload' ],
		'only suffix'          => [ 'Autoload]', 'Autoload' ],
		'nested brackets'      => [ '#[Autoload([])]', 'Autoload([])' ],
	] );

} );

describe( 'getNameWithArgs', function () {

	it( 'splits name and args', function ( string $input, ?array $expected ) {
		expect( Attribute::getNameWithArgs( $input ) )->toBe( $expected );
	} )->with( [
		'name only'            => [ 'Autoload', [ 'name' => 'Autoload', 'args' => '' ] ],
		'empty parens'         => [ 'Autoload()', [ 'name' => 'Autoload', 'args' => '' ] ],
		'namespaced name'      => [ 'App\\Attributes\\Autoload', [ 'name' => 'App\\Attributes\\Autoload', 'args' => '' ] ],
		'long namespaced name' => [ 'App\\Autoloader\\Attributes\\Autoload(5)', [ 'name' => 'App\\Autoloader\\Attributes\\Autoload', 'args' => '5' ] ],
		'fully qualified'      => [ '\\App\\Autoloader\\Attributes\\Autoload(5)', [ 'name' => '\\App\\Autoloader\\Attributes\\Autoload', 'args' => '5' ] ],
		'positional int'       => [ 'Autoload(5)', [ 'name' => 'Autoload', 'args' => '5' ] ],
		'positional negative'  => [ 'Autoload(-10)', [ 'name' => 'Autoload', 'args' => '-10' ] ],
		'positional float'     => [ 'Autoload(5.5)', [ 'name' => 'Autoload', 'args' => '5.5' ] ],
		'named priority'       => [ 'Autoload(priority: 15)', [ 'name' => 'Autoload', 'args' => 'priority: 15' ] ],
		'whitespace in args'   => [ 'Autoload( 5 )', [ 'name' => 'Autoload', 'args' => ' 5 ' ] ],
		'multiline args'       => [ "Autoload(\n  priority: 5\n)", [ 'name' => 'Autoload', 'args' => "\n  priority: 5\n" ] ],
		'empty string'         => [ '', NULL ],
		'invalid (no name)'    => [ '(5)', NULL ],
		'invalid (special)'    => [ '@Autoload', NULL ],
	] );

} );

describe( 'parseValue', function () {

	describe( 'integers', function () {
		it( 'parses integer literals', function ( string $input, int $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'zero'      => [ '0', 0 ],
			'positive'  => [ '5', 5 ],
			'negative'  => [ '-5', -5 ],
			'large'     => [ '999999', 999999 ],
			'large neg' => [ '-999999', -999999 ],
		] );
	} );

	describe( 'floats', function () {
		it( 'parses float literals', function ( string $input, float $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'simple'   => [ '5.5', 5.5 ],
			'negative' => [ '-5.5', -5.5 ],
			'zero'     => [ '0.0', 0.0 ],
			'small'    => [ '0.001', 0.001 ],
			'large'    => [ '999.999', 999.999 ],
		] );
	} );

	describe( 'booleans', function () {
		it( 'parses boolean literals', function ( string $input, bool $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'true'  => [ 'true', TRUE ],
			'false' => [ 'false', FALSE ],
		] );
	} );

	describe( 'null', function () {
		it( 'parses null literal', function () {
			expect( Attribute::parseValue( 'null' ) )->toBeNull();
		} );
	} );

	describe( 'strings', function () {
		it( 'parses double-quoted strings', function ( string $input, string $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'simple'      => [ '"hello"', 'hello' ],
			'empty'       => [ '""', '' ],
			'with spaces' => [ '"hello world"', 'hello world' ],
			'with number' => [ '"test123"', 'test123' ],
		] );

		it( 'parses single-quoted strings', function ( string $input, string $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'simple'      => [ "'hello'", 'hello' ],
			'empty'       => [ "''", '' ],
			'with spaces' => [ "'hello world'", 'hello world' ],
		] );

		it( 'processes escape sequences in double-quoted strings', function ( string $input, string $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'escaped quote'     => [ '"Bob\'s \"House\""', "Bob's \"House\"" ],
			'escaped backslash' => [ '"path\\\\to\\\\file"', 'path\\to\\file' ],
			'newline'           => [ '"line1\\nline2"', "line1\nline2" ],
			'carriage return'   => [ '"line1\\rline2"', "line1\rline2" ],
			'tab'               => [ '"col1\\tcol2"', "col1\tcol2" ],
			'vertical tab'      => [ '"a\\vb"', "a\vb" ],
			'escape'            => [ '"a\\eb"', "a\eb" ],
			'form feed'         => [ '"a\\fb"', "a\fb" ],
			'dollar sign'       => [ '"price: \\$5"', 'price: $5' ],
			'hex escape'        => [ '"\\x41\\x42"', 'AB' ],
			'octal escape'      => [ '"\\101\\102"', 'AB' ],
			'mixed escapes'     => [ '"line1\\nline2\\ttab"', "line1\nline2\ttab" ],
			'all escapes'       => [ '"\\n\\r\\t\\v\\e\\f\\\\\\$\\""', "\n\r\t\v\e\f\\\$\"" ],
		] );

		it( 'processes escape sequences in single-quoted strings', function ( string $input, string $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'escaped quote'      => [ "'Bob\\'s Name'", "Bob's Name" ],
			'escaped backslash'  => [ "'path\\\\to'", 'path\\to' ],
			'literal n (no esc)' => [ "'line1\\nline2'", 'line1\\nline2' ],
			'literal t (no esc)' => [ "'col1\\tcol2'", 'col1\\tcol2' ],
			'both escapes'       => [ "'it\\'s a \\\\ test'", "it's a \\ test" ],
		] );
	} );

	describe( 'arrays', function () {
		it( 'parses array literals', function ( string $input, array $expected ) {
			expect( Attribute::parseValue( $input ) )->toBe( $expected );
		} )->with( [
			'empty'          => [ '[]', [] ],
			'single int'     => [ '[1]', [ 1 ] ],
			'multiple int'   => [ '[1, 2, 3]', [ 1, 2, 3 ] ],
			'mixed types'    => [ '[1, "two", true]', [ 1, 'two', TRUE ] ],
			'assoc simple'   => [ "['a' => 1]", [ 'a' => 1 ] ],
			'assoc multiple' => [ "['a' => 1, 'b' => 2]", [ 'a' => 1, 'b' => 2 ] ],
			'nested'         => [ '[[1, 2], [3, 4]]', [ [ 1, 2 ], [ 3, 4 ] ] ],
			'deeply nested'  => [ '[[[1]]]', [ [ [ 1 ] ] ] ],
			'mixed keys'     => [ "[1, 'key' => 2, 3]", [ 1, 'key' => 2, 3 ] ],
		] );
	} );

	describe( 'unparseable (returns as string)', function () {
		it( 'returns unparseable values as-is', function ( string $input ) {
			expect( Attribute::parseValue( $input ) )->toBe( $input );
		} )->with( [
			// Constants
			'global constant' => [ 'PHP_EOL' ],
			'class constant'  => [ 'PDO::ATTR_ERRMODE' ],
			'custom constant' => [ 'Foo::BAR' ],
			'self constant'   => [ 'self::PRIORITY' ],

			// Expressions
			'addition'        => [ '1 + 2' ],
			'subtraction'     => [ '5 - 3' ],
			'multiplication'  => [ '2 * 3' ],
			'division'        => [ '10 / 2' ],
			'string concat'   => [ '"a" . "b"' ],
			'ternary'         => [ 'true ? 1 : 2' ],
			'bitwise shift'   => [ '1 << 2' ],
			'negative expr'   => [ '-5 * 2' ],
			'comparison'      => [ '1 === 1' ],

			// Objects (PHP 8.1+)
			'new simple'      => [ 'new stdClass' ],
			'new with args'   => [ "new DateTime('2024-01-01')" ],

			// Function calls
			'function call'   => [ 'strlen("x")' ],
			'method call'     => [ '$obj->method()' ],
			'static call'     => [ 'Foo::bar()' ],

			// Variables
			'variable'        => [ '$x' ],
			'array access'    => [ '$arr[0]' ],

			// Closures
			'arrow fn'        => [ 'fn() => 1' ],
			'closure'         => [ 'function() { return 1; }' ],
		] );
	} );

} );

describe( 'parseArgs', function () {

	it( 'parses args into typed associative array', function ( string $input, array $expected ) {
		expect( Attribute::parseArgs( $input ) )->toBe( $expected );
	} )->with( [
		// Empty
		'empty'                  => [ '', [] ],

		// Single values
		'single int'             => [ '5', [ 5 ] ],
		'single negative int'    => [ '-10', [ -10 ] ],
		'single float'           => [ '5.5', [ 5.5 ] ],
		'single string'          => [ '"hello"', [ 'hello' ] ],
		'single bool true'       => [ 'true', [ TRUE ] ],
		'single bool false'      => [ 'false', [ FALSE ] ],
		'single null'            => [ 'null', [ NULL ] ],
		'single array'           => [ '[1, 2]', [ [ 1, 2 ] ] ],

		// Named args
		'named int'              => [ 'priority: 15', [ 'priority' => 15 ] ],
		'named string'           => [ 'name: "foo"', [ 'name' => 'foo' ] ],
		'named bool'             => [ 'enabled: true', [ 'enabled' => TRUE ] ],
		'named array'            => [ 'items: [1, 2]', [ 'items' => [ 1, 2 ] ] ],
		'named with spaces'      => [ 'priority :  15', [ 'priority' => 15 ] ],

		// Multiple positional
		'two positional'         => [ '5, 10', [ 5, 10 ] ],
		'three positional'       => [ '5, 10, 15', [ 5, 10, 15 ] ],
		'mixed types positional' => [ '5, "str", true', [ 5, 'str', TRUE ] ],

		// Multiple named
		'two named'              => [ 'a: 1, b: 2', [ 'a' => 1, 'b' => 2 ] ],
		'three named'            => [ 'a: 1, b: 2, c: 3', [ 'a' => 1, 'b' => 2, 'c' => 3 ] ],

		// Mixed positional and named
		'pos then named'         => [ '5, name: "foo"', [ 5, 'name' => 'foo' ] ],
		'named then pos'         => [ 'name: "foo", 10', [ 'name' => 'foo', 10 ] ],
		'complex mixed'          => [ '5, name: "foo", 10, enabled: true', [ 5, 'name' => 'foo', 10, 'enabled' => TRUE ] ],

		// Nested structures
		'nested array'           => [ '[1, 2, 3]', [ [ 1, 2, 3 ] ] ],
		'nested assoc'           => [ "['a' => 1, 'b' => 2]", [ [ 'a' => 1, 'b' => 2 ] ] ],
		'deeply nested'          => [ '[[1, 2], [3, 4]]', [ [ [ 1, 2 ], [ 3, 4 ] ] ] ],
		'array as named arg'     => [ 'items: [1, 2], active: true', [ 'items' => [ 1, 2 ], 'active' => TRUE ] ],

		// Whitespace handling
		'whitespace trimmed'     => [ '  5  ,  name: "x"  ', [ 5, 'name' => 'x' ] ],
		'whitespace in string'   => [ '  5  ,  name: " x "  ', [ 5, 'name' => ' x ' ] ],
		'no spaces'              => [ '5,10,15', [ 5, 10, 15 ] ],
		'extra spaces'           => [ '5  ,   10  ,   15', [ 5, 10, 15 ] ],
		'trailing comma'         => [ '1, 2,', [ 1, 2 ] ],
		'leading comma'          => [ ', 1, 2', [ 1, 2 ] ],

		// Arrow syntax with bareword/int keys (inner array content)
		'bareword arrow key'     => [ 'foo => 1', [ 'foo' => 1 ] ],
		'int arrow key'          => [ '0 => "a", 1 => "b"', [ 'a', 'b' ] ],
		'mixed bareword/string'  => [ "foo => 1, 'bar' => 2", [ 'foo' => 1, 'bar' => 2 ] ],

		// Unparseable values (returned as string)
		'constant as arg'        => [ 'PHP_EOL', [ 'PHP_EOL' ] ],
		'expression as arg'      => [ '1 + 2', [ '1 + 2' ] ],
		'mixed with constant'    => [ '5, PHP_EOL, true', [ 5, 'PHP_EOL', TRUE ] ],
	] );

} );

describe( 'constructor + getName + getArguments (ReflectionAttribute interface)', function () {

	describe( 'parseable attributes', function () {

		it( 'parses attribute and provides typed interface', function ( string $input, string $expectedName, array $expectedArgs ) {
			$parser = new Attribute( $input );
			expect( $parser->getName() )->toBe( $expectedName );
			expect( $parser->getArguments() )->toBe( $expectedArgs );
		} )->with( [
			// No args
			'no args'            => [ '#[Autoload]', 'Autoload', [] ],
			'namespaced name'    => [ '#[App\\Attributes\\Autoload]', 'App\\Attributes\\Autoload', [] ],
			'fully qualified'    => [ '#[\\App\\Attributes\\Autoload(5)]', '\\App\\Attributes\\Autoload', [ 5 ] ],
			'empty parens'       => [ '#[Autoload()]', 'Autoload', [] ],

			// Integer args
			'int arg'            => [ '#[Autoload(5)]', 'Autoload', [ 5 ] ],
			'negative int'       => [ '#[Autoload(-5)]', 'Autoload', [ -5 ] ],
			'named int'          => [ '#[Autoload(priority: 15)]', 'Autoload', [ 'priority' => 15 ] ],

			// Float args
			'float arg'          => [ '#[Attr(5.5)]', 'Attr', [ 5.5 ] ],
			'negative float'     => [ '#[Attr(-3.14)]', 'Attr', [ -3.14 ] ],

			// Bool args
			'bool true'          => [ '#[Cache(true)]', 'Cache', [ TRUE ] ],
			'bool false'         => [ '#[Cache(false)]', 'Cache', [ FALSE ] ],
			'named bool'         => [ '#[Cache(enabled: true)]', 'Cache', [ 'enabled' => TRUE ] ],

			// Null arg
			'null arg'           => [ '#[Attr(null)]', 'Attr', [ NULL ] ],
			'named null'         => [ '#[Attr(value: null)]', 'Attr', [ 'value' => NULL ] ],

			// String args
			'double quoted'      => [ '#[Route("/api")]', 'Route', [ '/api' ] ],
			'single quoted'      => [ "#[Route('/api')]", 'Route', [ '/api' ] ],
			'named string'       => [ '#[Route(path: "/api")]', 'Route', [ 'path' => '/api' ] ],

			// Array args
			'array arg'          => [ '#[Attr([1, 2])]', 'Attr', [ [ 1, 2 ] ] ],
			'assoc array'        => [ "#[Attr(['a' => 1])]", 'Attr', [ [ 'a' => 1 ] ] ],
			'named array'        => [ '#[Attr(items: [1, 2])]', 'Attr', [ 'items' => [ 1, 2 ] ] ],
			'nested array'       => [ '#[Attr([[1], [2]])]', 'Attr', [ [ [ 1 ], [ 2 ] ] ] ],

			// Multiple args
			'two positional'     => [ '#[Attr(1, 2)]', 'Attr', [ 1, 2 ] ],
			'pos and named'      => [ '#[Route("/api", method: "GET")]', 'Route', [ '/api', 'method' => 'GET' ] ],
			'multiple named'     => [ '#[Route(path: "/api", method: "POST")]', 'Route', [ 'path' => '/api', 'method' => 'POST' ] ],
			'complex mixed'      => [ '#[Attr(1, name: "x", 2, flag: true)]', 'Attr', [ 1, 'name' => 'x', 2, 'flag' => TRUE ] ],

			// Without #[ prefix (fallback parsing)
			'bare name'          => [ 'Autoload', 'Autoload', [] ],
			'bare with args'     => [ 'Autoload(5)', 'Autoload', [ 5 ] ],
			'partial trailing ]' => [ 'Autoload]', 'Autoload', [] ],
		] );

	} );

	describe( 'unparseable args (returned as string)', function () {

		it( 'returns constants and expressions as strings', function ( string $input, string $expectedName, array $expectedArgs ) {
			$parser = new Attribute( $input );
			expect( $parser->getName() )->toBe( $expectedName );
			expect( $parser->getArguments() )->toBe( $expectedArgs );
		} )->with( [
			'global constant' => [ '#[Attr(PHP_EOL)]', 'Attr', [ 'PHP_EOL' ] ],
			'class constant'  => [ '#[Attr(PDO::ATTR_ERRMODE)]', 'Attr', [ 'PDO::ATTR_ERRMODE' ] ],
			'expression'      => [ '#[Attr(1 + 2)]', 'Attr', [ '1 + 2' ] ],
			'string concat'   => [ '#[Attr("a" . "b")]', 'Attr', [ '"a" . "b"' ] ],
			'ternary'         => [ '#[Attr(true ? 1 : 2)]', 'Attr', [ 'true ? 1 : 2' ] ],
			'new object'      => [ '#[Attr(new stdClass)]', 'Attr', [ 'new stdClass' ] ],
			'mixed parseable' => [ '#[Attr(5, PHP_EOL, true)]', 'Attr', [ 5, 'PHP_EOL', TRUE ] ],
		] );

	} );

	describe( 'invalid input', function () {

		it( 'returns empty name for invalid input', function ( string $input ) {
			$parser = new Attribute( $input );
			expect( $parser->getName() )->toBe( '' );
			expect( $parser->getArguments() )->toBe( [] );
		} )->with( [
			'empty string' => [ '' ],
			'at symbol'    => [ '@Invalid' ],
			'just parens'  => [ '()' ],
			'number only'  => [ '123' ],
		] );

	} );

} );

describe( 'processDoubleQuotedEscapes', function () {

	it( 'processes escape sequences', function ( string $input, string $expected ) {
		expect( Attribute::processDoubleQuotedEscapes( $input ) )->toBe( $expected );
	} )->with( [
		'no escapes'           => [ 'hello world', 'hello world' ],
		'escaped double quote' => [ 'say \\"hello\\"', 'say "hello"' ],
		'escaped backslash'    => [ 'path\\\\to\\\\file', 'path\\to\\file' ],
		'newline'              => [ 'line1\\nline2', "line1\nline2" ],
		'carriage return'      => [ 'before\\rafter', "before\rafter" ],
		'tab'                  => [ 'col1\\tcol2', "col1\tcol2" ],
		'vertical tab'         => [ 'a\\vb', "a\vb" ],
		'escape char'          => [ 'a\\eb', "a\eb" ],
		'form feed'            => [ 'page1\\fpage2', "page1\fpage2" ],
		'dollar sign'          => [ 'cost: \\$100', 'cost: $100' ],
		'hex 2-digit'          => [ '\\x41\\x42\\x43', 'ABC' ],
		'hex 1-digit'          => [ '\\x0', "\x00" ],
		'hex lowercase'        => [ '\\x4a', 'J' ],
		'octal 3-digit'        => [ '\\101\\102\\103', 'ABC' ],
		'octal 2-digit'        => [ '\\10', "\x08" ],
		'octal 1-digit'        => [ '\\0', "\x00" ],
		'mixed all'            => [ 'a\\nb\\tc\\\\d\\"e', "a\nb\tc\\d\"e" ],
		'empty string'         => [ '', '' ],
	] );

} );

describe( 'processSingleQuotedEscapes', function () {

	it( 'processes only \\\\ and \\\'', function ( string $input, string $expected ) {
		expect( Attribute::processSingleQuotedEscapes( $input ) )->toBe( $expected );
	} )->with( [
		'no escapes'            => [ 'hello world', 'hello world' ],
		'escaped single quote'  => [ "it\\'s", "it's" ],
		'escaped backslash'     => [ 'path\\\\to', 'path\\to' ],
		'literal n (preserved)' => [ 'line1\\nline2', 'line1\\nline2' ],
		'literal t (preserved)' => [ 'col1\\tcol2', 'col1\\tcol2' ],
		'literal r (preserved)' => [ 'a\\rb', 'a\\rb' ],
		'literal x (preserved)' => [ '\\x41', '\\x41' ],
		'both valid escapes'    => [ "it\\'s a \\\\ test", "it's a \\ test" ],
		'multiple quotes'       => [ "don\\'t won\\'t", "don't won't" ],
		'empty string'          => [ '', '' ],
	] );

} );

describe( 'getArgument', function () {

	it( 'extracts typed argument by name', function ( string $attr, string $name, string $type, mixed $expected ) {
		$parser = new Attribute( $attr );
		expect( $parser->getArgument( $name, $type ) )->toBe( $expected );
	} )->with( [
		'float by name'  => [ '#[Attr(priority: 5)]', 'priority', 'float', 5.0 ],
		'string by name' => [ "#[Attr(import: 'require_once')]", 'import', 'string', 'require_once' ],
		'bool by name'   => [ '#[Attr(check: true)]', 'check', 'bool', TRUE ],
		'missing name'   => [ '#[Attr(other: 5)]', 'priority', 'float', NULL ],
		'wrong type'     => [ "#[Attr(priority: 'text')]", 'priority', 'float', NULL ],
	] );

	it( 'uses fallback key for positional args', function ( string $attr, string $name, string $type, int $fallback, mixed $expected ) {
		$parser = new Attribute( $attr );
		expect( $parser->getArgument( $name, $type, $fallback ) )->toBe( $expected );
	} )->with( [
		'positional float'      => [ '#[Attr(5)]', 'priority', 'float', 0, 5.0 ],
		'named over positional' => [ '#[Attr(10, priority: 5)]', 'priority', 'float', 0, 5.0 ],
		'fallback when missing' => [ '#[Attr(5)]', 'other', 'float', 0, 5.0 ],
		'no fallback match'     => [ '#[Attr(5)]', 'other', 'float', 1, NULL ],
	] );

} );
