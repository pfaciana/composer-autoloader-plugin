<?php

declare(strict_types=1);

use Render\Autoloader\Entry;
use Render\Autoloader\Import;

function php ( string $code ): string
{
	return "<?php\n\n{$code}";
}

function makeEntry ( array $overrides = [] ): Entry
{
	$importValue = array_key_exists( 'import', $overrides ) ? $overrides['import'] : Import::RequireOnce;
	$import      = Import::parse( $importValue );

	return new Entry(
		type: $overrides['type'] ?? 'function',
		name: $overrides['name'] ?? 'test',
		class: $overrides['class'] ?? null,
		isStatic: $overrides['isStatic'] ?? false,
		priority: array_key_exists( 'priority', $overrides ) ? $overrides['priority'] : 10.0,
		file: $overrides['file'] ?? '/path/file.php',
		line: $overrides['line'] ?? 1,
		attribute: $overrides['attribute'] ?? 'Autoload',
		import: $import,
		check: array_key_exists( 'check', $overrides ) ? $overrides['check'] : true,
	);
}
