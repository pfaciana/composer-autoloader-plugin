<?php

declare( strict_types=1 );

use Render\Autoloader\Entry;
use Render\Autoloader\Import;

describe( 'Entry', function () {

	describe( 'isCallable', function () {

		it( 'returns expected result', function ( array $props, bool $expected ) {
			$entry = makeEntry( $props );
			expect( $entry->isCallable() )->toBe( $expected );
		} )->with( [
			'function'          => [ [ 'type' => 'function' ], TRUE ],
			'static method'     => [ [ 'type' => 'method', 'class' => 'App\\Service', 'isStatic' => TRUE ], TRUE ],
			'non-static method' => [ [ 'type' => 'method', 'class' => 'App\\Service', 'isStatic' => FALSE ], FALSE ],
		] );

	} );

	describe( 'getCallable', function () {

		it( 'returns correct format', function ( array $props, string $expected ) {
			$entry = makeEntry( $props );
			expect( $entry->getCallable() )->toBe( $expected );
		} )->with( [
			'method'              => [
				[ 'type' => 'method', 'name' => 'register', 'class' => 'App\\Service', 'isStatic' => TRUE ],
				'\\App\\Service::register',
			],
			'namespaced function' => [
				[ 'type' => 'function', 'name' => 'bootstrap', 'class' => 'App\\Helpers' ],
				'\\App\\Helpers\\bootstrap',
			],
			'global function'     => [
				[ 'type' => 'function', 'name' => 'my_global_func', 'class' => NULL ],
				'\\my_global_func',
			],
		] );

	} );

	describe( 'needsRequire', function () {

		it( 'returns expected result', function ( string $type, bool $expected ) {
			$entry = makeEntry( [ 'type' => $type, 'class' => 'App\\Service', 'isStatic' => TRUE ] );
			expect( $entry->needsRequire() )->toBe( $expected );
		} )->with( [
			'function' => [ 'function', TRUE ],
			'method'   => [ 'method', FALSE ],
		] );

	} );

	describe( 'with', function () {

		it( 'returns new instance with updated values', function () {
			$original = makeEntry( [ 'priority' => NULL, 'import' => NULL ] );
			$updated  = $original->with( [ 'priority' => 5.0, 'import' => Import::None ] );

			expect( $original->priority )->toBeNull();
			expect( $original->import )->toBeNull();
			expect( $updated->priority )->toBe( 5.0 );
			expect( $updated->import )->toBe( Import::None );
			expect( $updated->name )->toBe( 'test' );
		} );

		it( 'preserves values not in overrides', function () {
			$original = makeEntry( [ 'priority' => 10.0, 'import' => Import::RequireOnce ] );
			$updated  = $original->with( [ 'priority' => 5.0 ] );

			expect( $updated->priority )->toBe( 5.0 );
			expect( $updated->import )->toBe( Import::RequireOnce );
		} );

	} );

	describe( 'withDefaults', function () {

		it( 'applies Entry::DEFAULTS to null props', function () {
			$entry  = makeEntry( [ 'priority' => NULL, 'import' => NULL, 'check' => NULL ] );
			$result = $entry->withDefaults();

			expect( $result->priority )->toBe( Entry::DEFAULTS['priority'] );
			expect( $result->import )->toBe( Entry::DEFAULTS['import'] );
			expect( $result->check )->toBe( Entry::DEFAULTS['check'] );
		} );

		it( 'preserves explicit values', function () {
			$entry  = makeEntry( [ 'priority' => 5.0, 'import' => Import::None, 'check' => FALSE ] );
			$result = $entry->withDefaults();

			expect( $result->priority )->toBe( 5.0 );
			expect( $result->import )->toBe( Import::None );
			expect( $result->check )->toBe( FALSE );
		} );

		it( 'returns same instance when no nulls', function () {
			$entry  = makeEntry( [ 'priority' => 5.0, 'import' => Import::None, 'check' => TRUE ] );
			$result = $entry->withDefaults();

			expect( $result )->toBe( $entry );
		} );

		it( 'uses custom defaults over DEFAULTS', function () {
			$entry    = makeEntry( [ 'priority' => NULL, 'import' => NULL, 'check' => NULL ] );
			$defaults = [ 'priority' => 25.0, 'import' => Import::Include, 'check' => FALSE ];
			$result   = $entry->withDefaults( $defaults );

			expect( $result->priority )->toBe( 25.0 );
			expect( $result->import )->toBe( Import::Include );
			expect( $result->check )->toBe( FALSE );
		} );

		it( 'merges custom defaults with DEFAULTS', function () {
			$entry    = makeEntry( [ 'priority' => NULL, 'import' => NULL, 'check' => NULL ] );
			$defaults = [ 'priority' => 25.0 ]; // only override priority
			$result   = $entry->withDefaults( $defaults );

			expect( $result->priority )->toBe( 25.0 );
			expect( $result->import )->toBe( Entry::DEFAULTS['import'] );
			expect( $result->check )->toBe( Entry::DEFAULTS['check'] );
		} );

	} );

	describe( 'matches', function () {

		it( 'compares attribute names', function ( string $found, string $expected, bool $shouldMatch ) {
			expect( Entry::matches( $found, $expected ) )->toBe( $shouldMatch );
		} )->with( [
			'identical'             => [ 'Autoload', 'Autoload', TRUE ],
			'short to FQCN'         => [ 'App\\Autoload', 'Autoload', TRUE ],
			'FQCN to short'         => [ 'Autoload', 'App\\Autoload', TRUE ],
			'leading slash FQCN'    => [ '\\App\\Autoload', 'App\\Autoload', TRUE ],
			'different names'       => [ 'Autoload', 'Bootstrap', FALSE ],
			'different short names' => [ 'App\\Autoload', 'App\\Bootstrap', FALSE ],
			'empty strings'         => [ '', '', TRUE ],
		] );

	} );

	describe( 'filterByAttribute (via matches)', function () {

		it( 'filters by attribute name', function ( array $attributes, string $filter, int $expectedCount ) {
			$entries  = array_map( fn( $attr ) => makeEntry( [ 'attribute' => $attr ] ), $attributes );
			$filtered = array_filter( $entries, fn( Entry $e ) => Entry::matches( $e->attribute ?? '', $filter ) );
			expect( $filtered )->toHaveCount( $expectedCount );
		} )->with( [
			'keeps matching' => [ [ 'Autoload', 'Other', 'App\\Autoload' ], 'Autoload', 2 ],
			'keeps none'     => [ [ 'Other', 'Different' ], 'Autoload', 0 ],
			'keeps all'      => [ [ 'Autoload', 'Autoload' ], 'Autoload', 2 ],
		] );

	} );

	describe( 'filterCallable (via isCallable)', function () {

		it( 'keeps only callable entries', function ( array $entryProps, int $expectedCount ) {
			$entries  = array_map( fn( $props ) => makeEntry( $props ), $entryProps );
			$filtered = array_filter( $entries, fn( Entry $e ) => $e->isCallable() );
			expect( $filtered )->toHaveCount( $expectedCount );
		} )->with( [
			'functions and static methods' => [
				[
					[ 'type' => 'function' ],
					[ 'type' => 'method', 'class' => 'Foo', 'isStatic' => TRUE ],
					[ 'type' => 'method', 'class' => 'Foo', 'isStatic' => FALSE ],
				],
				2,
			],
			'all callable'                 => [
				[
					[ 'type' => 'function' ],
					[ 'type' => 'function' ],
				],
				2,
			],
			'none callable'                => [
				[
					[ 'type' => 'method', 'class' => 'Foo', 'isStatic' => FALSE ],
				],
				0,
			],
		] );

	} );

} );
