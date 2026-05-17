<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

class Attribute
{
	private ?string $name = NULL;
	private array $arguments = [];

	/**
	 * Parse attribute text into name and arguments.
	 *
	 * @param string $attributeText Raw attribute string (e.g., "Autoload(5)" or "#[Autoload(5)]")
	 */
	public function __construct ( string $attributeText )
	{
		$content = self::unwrap( $attributeText );
		$parts   = self::getNameWithArgs( $content );

		if ( $parts !== NULL ) {
			$this->name      = $parts['name'];
			$this->arguments = self::parseArgs( $parts['args'] );
		}
	}

	/**
	 * Get parsed attribute name.
	 *
	 * @return string Attribute name or empty string if parsing failed
	 */
	public function getName (): string
	{
		return $this->name ?? '';
	}

	/**
	 * Get parsed attribute arguments.
	 *
	 * @return array<int|string, mixed> Positional and named arguments
	 */
	public function getArguments (): array
	{
		return $this->arguments;
	}

	/**
	 * Get typed argument by name with optional fallback key.
	 *
	 * @param string          $name        Argument name
	 * @param string          $type        Expected type: 'float'|'string'|'bool'
	 * @param int|string|null $fallbackKey Fallback key (e.g., 0 for positional)
	 *
	 * @return float|string|bool|null Typed value or null
	 */
	public function getArgument ( string $name, string $type, int|string|null $fallbackKey = NULL ): mixed
	{
		$value = $this->arguments[$name] ?? ( $fallbackKey !== NULL ? ( $this->arguments[$fallbackKey] ?? NULL ) : NULL );

		if ( $value === NULL ) {
			return NULL;
		}

		return match ( $type ) {
			'float' => is_numeric( $value ) ? (float) $value : NULL,
			'string' => is_string( $value ) ? $value : NULL,
			'bool' => is_bool( $value ) ? $value : NULL,
			default => NULL,
		};
	}

	/**
	 * Remove #[ and ] wrapper from attribute text.
	 *
	 * @param string $text Raw attribute text
	 *
	 * @return string Unwrapped content
	 */
	public static function unwrap ( string $text ): string
	{
		$text = trim( $text );

		if ( str_starts_with( $text, '#[' ) ) {
			$text = substr( $text, 2 );
		}

		if ( str_ends_with( $text, ']' ) ) {
			$text = substr( $text, 0, -1 );
		}

		return $text;
	}

	/**
	 * Extract attribute name and raw args string.
	 *
	 * @param string $content Unwrapped attribute content
	 *
	 * @return array{name: string, args: string}|null Parsed parts or null if invalid
	 */
	public static function getNameWithArgs ( string $content ): ?array
	{
		$content = trim( $content );

		if ( !preg_match( '/^(\\\\?[a-zA-Z_]\w*(?:\\\\[a-zA-Z_]\w*)*)(?:\s*\((.*)\))?$/s', $content, $matches ) ) {
			return NULL;
		}

		return [
			'name' => $matches[1],
			'args' => $matches[2] ?? '',
		];
	}

	/**
	 * Parse attribute arguments string into array.
	 *
	 * Supports positional, named (key: value), and array syntax.
	 *
	 * @param string $args Raw arguments string
	 *
	 * @return array<int|string, mixed> Parsed arguments
	 */
	public static function parseArgs ( string $args ): array
	{
		$args = trim( $args );

		if ( $args === '' ) {
			return [];
		}

		$result   = [];
		$position = 0;
		$parts    = self::splitArgParts( $args );

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( $part === '' ) {
				continue;
			}

			if ( preg_match( '/^(\w+)\s*:(?!:)\s*(.+)$/s', $part, $matches ) ) {
				$result[$matches[1]] = self::parseValue( trim( $matches[2] ) );
			}
			elseif ( preg_match( '/^(["\'])(.+?)\1\s*=>\s*(.+)$/s', $part, $matches ) ) {
				$result[$matches[2]] = self::parseValue( trim( $matches[3] ) );
			}
			elseif ( preg_match( '/^(\w+)\s*=>\s*(.+)$/s', $part, $matches ) ) {
				$key          = is_numeric( $matches[1] ) ? (int) $matches[1] : $matches[1];
				$result[$key] = self::parseValue( trim( $matches[2] ) );
			}
			else {
				$result[$position++] = self::parseValue( $part );
			}
		}

		return $result;
	}

	/**
	 * Parse array literal [a, b, c] into PHP array.
	 *
	 * @param string $value Array literal string with brackets
	 *
	 * @return array<int|string, mixed> Parsed array
	 */
	private static function parseArrayLiteral ( string $value ): array
	{
		$inner = substr( $value, 1, -1 );

		return self::parseArgs( $inner );
	}

	/**
	 * Parse single value into PHP type.
	 *
	 * Handles: null, bool, int, float, strings, arrays, bare identifiers.
	 *
	 * @param string $value Raw value string
	 *
	 * @return mixed Parsed PHP value
	 */
	public static function parseValue ( string $value ): mixed
	{
		if ( $value === 'null' ) {
			return NULL;
		}

		if ( $value === 'true' ) {
			return TRUE;
		}
		if ( $value === 'false' ) {
			return FALSE;
		}

		if ( preg_match( '/^-?\d+$/', $value ) ) {
			return (int) $value;
		}

		if ( preg_match( '/^-?\d+\.\d+$/', $value ) ) {
			return (float) $value;
		}

		if ( preg_match( '/^"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"$/', $value, $matches ) ) {
			return self::processDoubleQuotedEscapes( $matches[1] );
		}

		if ( preg_match( "/^'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'$/", $value, $matches ) ) {
			return self::processSingleQuotedEscapes( $matches[1] );
		}

		if ( str_starts_with( $value, '[' ) && str_ends_with( $value, ']' ) ) {
			return self::parseArrayLiteral( $value );
		}

		return $value;
	}

	/**
	 * Process escape sequences in double-quoted strings.
	 *
	 * Handles: \n, \r, \t, \v, \e, \f, \\, \$, \", octal, hex.
	 *
	 * @param string $str String content (without quotes)
	 *
	 * @return string Processed string with escapes resolved
	 */
	public static function processDoubleQuotedEscapes ( string $str ): string
	{
		return preg_replace_callback(
			'/\\\\(n|r|t|v|e|f|\\\\|\\$|"|[0-7]{1,3}|x[0-9A-Fa-f]{1,2})/',
			function ( $m ) {
				return match ( $m[1] ) {
					'n' => "\n",
					'r' => "\r",
					't' => "\t",
					'v' => "\v",
					'e' => "\e",
					'f' => "\f",
					'\\' => '\\',
					'$' => '$',
					'"' => '"',
					default => str_starts_with( $m[1], 'x' )
						? chr( hexdec( substr( $m[1], 1 ) ) )
						: chr( octdec( $m[1] ) ),
				};
			},
			$str
		);
	}

	/**
	 * Process escape sequences in single-quoted strings.
	 *
	 * Only handles: \' and \\.
	 *
	 * @param string $str String content (without quotes)
	 *
	 * @return string Processed string
	 */
	public static function processSingleQuotedEscapes ( string $str ): string
	{
		return str_replace( [ "\\'", '\\\\' ], [ "'", '\\' ], $str );
	}

	/**
	 * Split argument string by comma, respecting nesting.
	 *
	 * Tracks depth of (), [], {} to avoid splitting inside nested structures.
	 *
	 * @param string $args Raw arguments string
	 *
	 * @return string[] Individual argument strings
	 */
	private static function splitArgParts ( string $args ): array
	{
		$parts   = [];
		$current = '';
		$depth   = 0;
		$len     = strlen( $args );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = $args[$i];

			if ( $char === '(' || $char === '[' || $char === '{' ) {
				$depth++;
				$current .= $char;
			}
			elseif ( $char === ')' || $char === ']' || $char === '}' ) {
				$depth--;
				$current .= $char;
			}
			elseif ( $char === ',' && $depth === 0 ) {
				$parts[] = $current;
				$current = '';
			}
			else {
				$current .= $char;
			}
		}

		if ( $current !== '' ) {
			$parts[] = $current;
		}

		return $parts;
	}

}
