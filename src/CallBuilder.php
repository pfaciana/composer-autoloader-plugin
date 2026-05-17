<?php

declare( strict_types=1 );

namespace Render\Autoloader;

class CallBuilder
{
	/**
	 * Build final entry list with defaults applied and sorted.
	 *
	 * @param Entry[]                                                     $entries  Raw entries from scanner
	 * @param array<string, mixed>|null $defaults Custom defaults (merged over Entry::DEFAULTS)
	 *
	 * @return Entry[] Processed entries sorted by priority
	 */
	public static function build ( array $entries, ?array $defaults = null ): array
	{
		return self::sortByPriority(
			array_map( fn( Entry $e ) => $e->withDefaults( $defaults ), $entries ),
		);
	}

	/**
	 * Sort entries by priority ascending.
	 *
	 * Lower priority values execute first.
	 *
	 * @param Entry[] $entries Entries to sort
	 *
	 * @return Entry[] Sorted entries (new array)
	 */
	public static function sortByPriority ( array $entries ): array
	{
		$sorted = array_values( $entries );

		usort( $sorted, fn( Entry $a, Entry $b ) => $a->priority <=> $b->priority );

		return $sorted;
	}
}
