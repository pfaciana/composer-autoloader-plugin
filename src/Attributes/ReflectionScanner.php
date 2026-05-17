<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

use Render\Autoloader\Entry;
use Render\Autoloader\Import;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class ReflectionScanner
{
	/**
	 * Scan candidates using Reflection API for accurate attribute data.
	 *
	 * @param Entry[] $candidates    Token-scanned candidates to verify
	 * @param string  $attributeName Attribute to match
	 *
	 * @return Entry[] Verified entries with accurate priority
	 */
	public static function scan ( array $candidates, string $attributeName ): array
	{
		$entries = [];

		foreach ( $candidates as $candidate ) {
			$entry = $candidate->type === 'method'
				? self::scanMethod( $candidate, $attributeName )
				: self::scanFunction( $candidate, $attributeName );

			if ( $entry !== NULL ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Scan class method via reflection.
	 *
	 * @param Entry  $candidate     Candidate entry to verify
	 * @param string $attributeName Attribute to match
	 *
	 * @return Entry|null Verified entry or null if not valid
	 */
	public static function scanMethod ( Entry $candidate, string $attributeName ): ?Entry
	{
		if ( !$candidate->isStatic ) {
			return NULL;
		}

		if ( !class_exists( $candidate->class, FALSE ) ) {
			return NULL;
		}

		$reflection = new ReflectionClass( $candidate->class );

		if ( !$reflection->hasMethod( $candidate->name ) ) {
			return NULL;
		}

		$method = $reflection->getMethod( $candidate->name );

		if ( !$method->isStatic() || !$method->isPublic() ) {
			return NULL;
		}

		$values = self::extractAttributeValues( $method, $attributeName );

		if ( $values === null ) {
			return null;
		}

		return $candidate->with( $values );
	}

	/**
	 * Scan function via reflection.
	 *
	 * @param Entry  $candidate     Candidate entry to verify
	 * @param string $attributeName Attribute to match
	 *
	 * @return Entry|null Verified entry or null if not valid
	 */
	public static function scanFunction ( Entry $candidate, string $attributeName ): ?Entry
	{
		$fqfn = $candidate->class ? $candidate->class . '\\' . $candidate->name : $candidate->name;

		if ( !function_exists( $fqfn ) ) {
			return null;
		}

		$reflection = new ReflectionFunction( $fqfn );
		$values     = self::extractAttributeValues( $reflection, $attributeName );

		if ( $values === null ) {
			return null;
		}

		return $candidate->with( $values );
	}

	/**
	 * Extract attribute values from reflected method/function.
	 *
	 * @param ReflectionMethod|ReflectionFunction $reflection    Reflection object
	 * @param string                              $attributeName Attribute to match
	 *
	 * @return array{priority: ?float, import: ?Import, check: ?bool}|null Values or null if not found
	 */
	private static function extractAttributeValues (
		ReflectionMethod|ReflectionFunction $reflection,
		string                              $attributeName,
	): ?array {
		foreach ( $reflection->getAttributes() as $attr ) {
			if ( !Entry::matches( $attr->getName(), $attributeName ) ) {
				continue;
			}

			$args   = $attr->getArguments();
			$values = [];

			foreach ( Entry::MUTABLE_PROPS as $prop ) {
				$values[$prop] = $args[$prop] ?? null;
			}

			$values['import'] = Import::parse( $values['import'] );

			return $values;
		}

		return null;
	}
}
