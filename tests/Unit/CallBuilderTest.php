<?php

declare( strict_types=1 );

use Render\Autoloader\CallBuilder;
use Render\Autoloader\Entry;
use Render\Autoloader\Import;

describe( 'CallBuilder', function () {

	describe( 'sortByPriority', function () {

		it( 'sorts ascending', function ( array $priorities, array $expectedOrder ) {
			$entries = array_map(
				fn( $p, $i ) => makeEntry( [ 'name' => "e{$i}", 'priority' => $p ] ),
				$priorities,
				array_keys( $priorities ),
			);
			$sorted  = CallBuilder::sortByPriority( $entries );
			$names   = array_map( fn( $e ) => $e->name, $sorted );
			expect( $names )->toBe( $expectedOrder );
		} )->with( [
			'reverse order'  => [ [ 30.0, 10.0, 20.0 ], [ 'e1', 'e2', 'e0' ] ],
			'already sorted' => [ [ 1.0, 2.0, 3.0 ], [ 'e0', 'e1', 'e2' ] ],
			'same priority'  => [ [ 5.0, 5.0 ], [ 'e0', 'e1' ] ],
		] );

	} );

	describe( 'build', function () {

		it( 'applies Entry defaults and sorts', function () {
			$entries = [
				makeEntry( [ 'name' => 'z', 'priority' => 50.0 ] ),
				makeEntry( [ 'name' => 'a', 'priority' => null ] ),
				makeEntry( [ 'name' => 'm', 'priority' => 25.0 ] ),
			];

			$result = CallBuilder::build( $entries );

			expect( $result )->toHaveCount( 3 );
			expect( $result[0]->name )->toBe( 'a' );
			expect( $result[0]->priority )->toBe( Entry::DEFAULTS['priority'] );
			expect( $result[1]->name )->toBe( 'm' );
			expect( $result[2]->name )->toBe( 'z' );
		} );

		it( 'fills null import and check with Entry defaults', function () {
			$entries = [ makeEntry( [ 'import' => null, 'check' => null ] ) ];

			$result = CallBuilder::build( $entries );

			expect( $result[0]->import )->toBe( Entry::DEFAULTS['import'] );
			expect( $result[0]->check )->toBe( Entry::DEFAULTS['check'] );
		} );

		it( 'preserves explicit values over defaults', function () {
			$entries = [ makeEntry( [ 'priority' => 5.0, 'import' => Import::None, 'check' => false ] ) ];

			$result = CallBuilder::build( $entries );

			expect( $result[0]->priority )->toBe( 5.0 );
			expect( $result[0]->import )->toBe( Import::None );
			expect( $result[0]->check )->toBe( false );
		} );

		it( 'uses custom defaults over Entry::DEFAULTS', function () {
			$entries  = [ makeEntry( [ 'priority' => null, 'import' => null, 'check' => null ] ) ];
			$defaults = [ 'priority' => 25.0, 'import' => Import::Include, 'check' => false ];

			$result = CallBuilder::build( $entries, $defaults );

			expect( $result[0]->priority )->toBe( 25.0 );
			expect( $result[0]->import )->toBe( Import::Include );
			expect( $result[0]->check )->toBe( false );
		} );

		it( 'merges custom defaults with Entry::DEFAULTS', function () {
			$entries  = [ makeEntry( [ 'priority' => null, 'import' => null, 'check' => null ] ) ];
			$defaults = [ 'priority' => 25.0 ]; // only override priority

			$result = CallBuilder::build( $entries, $defaults );

			expect( $result[0]->priority )->toBe( 25.0 );
			expect( $result[0]->import )->toBe( Entry::DEFAULTS['import'] );
			expect( $result[0]->check )->toBe( Entry::DEFAULTS['check'] );
		} );

	} );

} );