<?php

declare( strict_types=1 );

namespace Render\Autoloader;

/**
 * PHP file import strategy for bootstrap code generation.
 *
 * Defines how function files are imported in generated bootstrap.
 * Each strategy maps to a PHP import statement (require_once, include, etc.)
 * with precedence rules for resolving conflicts when multiple functions
 * share the same file.
 */
enum Import: string
{
	case None        = 'none';
	case RequireOnce = 'require_once';
	case IncludeOnce = 'include_once';
	case Require     = 'require';
	case Include     = 'include';

	/**
	 * Get precedence rank for import strategy comparison.
	 *
	 * When multiple functions in same file have different import strategies,
	 * higher precedence wins: none > require_once > include_once > require > include
	 *
	 * @return int Precedence rank (1-5, higher = wins)
	 */
	public function precedence (): int
	{
		return match ( $this ) {
			self::Include     => 1,
			self::Require     => 2,
			self::IncludeOnce => 3,
			self::RequireOnce => 4,
			self::None        => 5,
		};
	}

	/**
	 * Parse string or Import to Import enum.
	 *
	 * Accepts backed enum string values (e.g., 'require_once'), Import instances,
	 * or null. Invalid strings return null.
	 *
	 * @param string|self|null $value Value to parse
	 *
	 * @return self|null Parsed Import or null if invalid/null input
	 */
	public static function parse ( string|self|null $value ): ?self
	{
		if ( $value === null || $value instanceof self ) {
			return $value;
		}

		return self::tryFrom( $value );
	}

	/**
	 * Return highest precedence Import from given values.
	 *
	 * Compares all provided imports and returns the one with highest precedence.
	 * Empty input returns RequireOnce as safe default.
	 *
	 * @param self ...$imports Import values to compare
	 *
	 * @return self Highest precedence Import (RequireOnce if empty)
	 */
	public static function highest ( self ...$imports ): self
	{
		return empty( $imports ) ? self::RequireOnce : array_reduce(
			$imports,
			fn( self $a, self $b ) => $b->precedence() > $a->precedence() ? $b : $a,
			self::Include,
		);
	}
}
