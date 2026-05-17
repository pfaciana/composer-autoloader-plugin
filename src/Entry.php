<?php

declare( strict_types=1 );

namespace Render\Autoloader;

readonly class Entry
{
	/**
	 * Props that can be overridden via with() and withDefaults().
	 */
	public const MUTABLE_PROPS = [ 'priority', 'import', 'check' ];

	/**
	 * Default values for mutable props.
	 */
	public const DEFAULTS = [
		'priority' => 10.0,
		'import'   => Import::RequireOnce,
		'check'    => FALSE,
	];

	/**
	 * Create a new Entry representing a discovered autoload item.
	 *
	 * @param 'function'|'method'|'file' $type      Entry type
	 * @param string                     $name      Function/method name
	 * @param string|null                $class     FQCN for methods, namespace for functions
	 * @param bool                       $isStatic  Whether method is static
	 * @param float|null                 $priority  Sort priority (null = use default)
	 * @param string                     $file      Absolute file path
	 * @param int                        $line      Line number in file
	 * @param string|null                $attribute Attribute short name (e.g., "AutoRun")
	 * @param Import|null                $import    Import strategy (null = use default)
	 * @param bool|null                  $check     Include in function_exists check (null = use default)
	 */
	public function __construct (
		public string  $type,
		public string  $name,
		public ?string $class,
		public bool    $isStatic,
		public ?float  $priority,
		public string  $file,
		public int     $line,
		public ?string $attribute = NULL,
		public ?Import $import = NULL,
		public ?bool   $check = NULL,
	) {
	}

	/**
	 * Check if entry can be called at top-level without instantiation.
	 *
	 * @return bool True if function or static method
	 */
	public function isCallable (): bool
	{
		return $this->type === 'function' || $this->isStatic;
	}

	/**
	 * Get fully-qualified callable string for code generation.
	 *
	 * @return string E.g., "\App\Service::init" or "\App\bootstrap"
	 */
	public function getCallable (): string
	{
		if ( $this->type === 'file' ) {
			return '';
		}

		if ( $this->type === 'method' ) {
			return '\\' . $this->class . '::' . $this->name;
		}

		return $this->class ? '\\' . $this->class . '\\' . $this->name : '\\' . $this->name;
	}

	/**
	 * Check if entry needs require_once in generated bootstrap.
	 *
	 * Functions and file entries need explicit require; classes use autoloader.
	 *
	 * @return bool True if function or file type
	 */
	public function needsRequire (): bool
	{
		return $this->type === 'function' || $this->type === 'file';
	}

	/**
	 * Return new Entry with overridden values.
	 *
	 * @param array<string, mixed> $values Values to override (keys from MUTABLE_PROPS)
	 *
	 * @return self New Entry with updated values
	 */
	public function with ( array $values ): self
	{
		return new self(
			type: $this->type,
			name: $this->name,
			class: $this->class,
			isStatic: $this->isStatic,
			priority: $values['priority'] ?? $this->priority,
			file: $this->file,
			line: $this->line,
			attribute: $this->attribute,
			import: $values['import'] ?? $this->import,
			check: $values['check'] ?? $this->check,
		);
	}

	/**
	 * Return new Entry with defaults applied to null mutable props.
	 *
	 * @param array<string, mixed>|null $defaults Custom defaults (merged over DEFAULTS)
	 *
	 * @return self Entry with defaults applied (or same instance if no changes)
	 */
	public function withDefaults ( ?array $defaults = NULL ): self
	{
		$merged    = $defaults ? array_merge( self::DEFAULTS, $defaults ) : self::DEFAULTS;
		$overrides = [];

		foreach ( self::MUTABLE_PROPS as $prop ) {
			if ( $this->$prop === NULL && isset( $merged[$prop] ) ) {
				$overrides[$prop] = $merged[$prop];
			}
		}

		return empty( $overrides ) ? $this : $this->with( $overrides );
	}

	/**
	 * Check if attribute names match by short name or FQCN.
	 *
	 * Compares the short name (after last backslash) of both strings,
	 * or exact match if both are FQCNs.
	 *
	 * @param string $found    Attribute name from code (may be FQCN)
	 * @param string $expected Attribute name to match against
	 *
	 * @return bool True if names match
	 */
	public static function matches ( string $found, string $expected ): bool
	{
		$foundPos   = strrpos( $found, '\\' );
		$foundShort = $foundPos === FALSE ? $found : substr( $found, $foundPos + 1 );

		$expectedPos   = strrpos( $expected, '\\' );
		$expectedShort = $expectedPos === FALSE ? $expected : substr( $expected, $expectedPos + 1 );

		return $foundShort === $expectedShort || $found === $expected;
	}
}
