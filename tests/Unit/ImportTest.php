<?php

declare( strict_types=1 );

use Render\Autoloader\Import;

describe( 'Import', function () {

	describe( 'precedence', function () {

		it( 'returns correct value', function ( Import $import, int $expected ) {
			expect( $import->precedence() )->toBe( $expected );
		} )->with( [
			'Include'     => [ Import::Include, 1 ],
			'Require'     => [ Import::Require, 2 ],
			'IncludeOnce' => [ Import::IncludeOnce, 3 ],
			'RequireOnce' => [ Import::RequireOnce, 4 ],
			'None'        => [ Import::None, 5 ],
		] );

		it( 'orders correctly (None > RequireOnce > IncludeOnce > Require > Include)', function () {
			$sorted = Import::cases();
			usort( $sorted, fn( Import $a, Import $b ) => $b->precedence() <=> $a->precedence() );

			expect( $sorted )->toBe( [
				Import::None,
				Import::RequireOnce,
				Import::IncludeOnce,
				Import::Require,
				Import::Include,
			] );
		} );

	} );

	describe( 'parse', function () {

		it( 'returns Import from string', function ( string $value, Import $expected ) {
			expect( Import::parse( $value ) )->toBe( $expected );
		} )->with( [
			'none'         => [ 'none', Import::None ],
			'require_once' => [ 'require_once', Import::RequireOnce ],
			'include_once' => [ 'include_once', Import::IncludeOnce ],
			'require'      => [ 'require', Import::Require ],
			'include'      => [ 'include', Import::Include ],
		] );

		it( 'returns null for invalid string', function ( string $value ) {
			expect( Import::parse( $value ) )->toBeNull();
		} )->with( [
			'empty'       => [ '' ],
			'unknown'     => [ 'unknown' ],
			'misspelled'  => [ 'requre_once' ],
			'uppercase'   => [ 'REQUIRE_ONCE' ],
			'mixed case'  => [ 'Require_Once' ],
		] );

		it( 'returns null when null', function () {
			expect( Import::parse( null ) )->toBeNull();
		} );

		it( 'returns same Import when Import passed', function ( Import $import ) {
			expect( Import::parse( $import ) )->toBe( $import );
		} )->with( [
			'None'        => [ Import::None ],
			'RequireOnce' => [ Import::RequireOnce ],
			'IncludeOnce' => [ Import::IncludeOnce ],
			'Require'     => [ Import::Require ],
			'Include'     => [ Import::Include ],
		] );

	} );

	describe( 'highest', function () {

		it( 'returns highest precedence from multiple', function ( array $imports, Import $expected ) {
			expect( Import::highest( ...$imports ) )->toBe( $expected );
		} )->with( [
			'none wins over all'         => [ [ Import::Include, Import::None, Import::RequireOnce ], Import::None ],
			'require_once over include'  => [ [ Import::Include, Import::RequireOnce ], Import::RequireOnce ],
			'include_once over require'  => [ [ Import::Require, Import::IncludeOnce ], Import::IncludeOnce ],
			'require over include'       => [ [ Import::Include, Import::Require ], Import::Require ],
			'single value'               => [ [ Import::Include ], Import::Include ],
			'all same'                   => [ [ Import::Require, Import::Require ], Import::Require ],
			'order independent'          => [ [ Import::RequireOnce, Import::None ], Import::None ],
			'reverse order independent'  => [ [ Import::None, Import::RequireOnce ], Import::None ],
		] );

		it( 'returns RequireOnce for empty args', function () {
			expect( Import::highest() )->toBe( Import::RequireOnce );
		} );

	} );

} );
