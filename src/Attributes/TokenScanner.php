<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

use Render\Autoloader\Entry;
use Render\Autoloader\Import;

class TokenScanner
{
	/**
	 * Scan PHP code for attribute candidates via tokens.
	 *
	 * Fast first-pass scan without requiring file inclusion.
	 *
	 * @param string $code PHP source code
	 * @param string $file File path for Entry metadata
	 *
	 * @return Entry[] Candidate entries (may need reflection verification)
	 */
	public static function scan ( string $code, string $file = '' ): array
	{
		return self::extractCandidatesFromTokens( token_get_all( $code ), $file );
	}

	/**
	 * Extract attribute candidates from token stream.
	 *
	 * Tracks namespace, class context, and pending attributes.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens PHP tokens
	 * @param string                                     $file   File path
	 *
	 * @return Entry[] Discovered candidates
	 */
	private static function extractCandidatesFromTokens ( array $tokens, string $file ): array
	{
		$candidates = [];
		$count      = count( $tokens );

		$namespace    = NULL;
		$currentClass = NULL;
		$braceDepth   = 0;
		$classDepth   = 0;

		$pendingAttributes = [];
		$pendingStatic     = FALSE;

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[$i];

			if ( is_string( $token ) ) {
				if ( $token === '{' ) {
					$braceDepth++;
				}
				elseif ( $token === '}' ) {
					$braceDepth--;
					if ( $currentClass !== NULL && $braceDepth < $classDepth ) {
						$currentClass = NULL;
						$classDepth   = 0;
					}
				}
				continue;
			}

			[ $id, $text, $line ] = $token;

			switch ( $id ) {
				case T_NAMESPACE:
					$namespace = self::parseNamespaceAt( $tokens, $i );
					break;

				case T_CLASS:
				case T_INTERFACE:
				case T_TRAIT:
				case T_ENUM:
					if ( $braceDepth === 0 || $currentClass === NULL ) {
						$currentClass = self::parseIdentifierAfter( $tokens, $i );
						$classDepth   = $braceDepth + 1;
					}
					$pendingAttributes = [];
					$pendingStatic     = FALSE;
					break;

				case T_STATIC:
					$pendingStatic = TRUE;
					break;

				case T_ATTRIBUTE:
					if ( ( $attribute = self::parseAttributeAt( $tokens, $i ) ) !== NULL ) {
						$pendingAttributes[] = $attribute;
					}
					break;

				case T_FUNCTION:
					$funcName = self::parseIdentifierAfter( $tokens, $i );

					if ( $funcName !== NULL && !empty( $pendingAttributes ) ) {
						foreach ( $pendingAttributes as $pendingAttribute ) {
							$candidates[] = self::buildCandidate(
								$funcName,
								$pendingAttribute,
								$pendingStatic,
								$namespace,
								$currentClass,
								$file,
								$line,
							);
						}
					}

					$pendingAttributes = [];
					$pendingStatic     = FALSE;
					break;

				case T_PUBLIC:
				case T_PROTECTED:
				case T_PRIVATE:
				case T_FINAL:
				case T_ABSTRACT:
				case T_READONLY:
					break;

				default:
					if ( $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT ) {
						if ( !empty( $pendingAttributes ) && $id !== T_STATIC ) {
							$pendingAttributes = [];
							$pendingStatic     = FALSE;
						}
					}
			}
		}

		return $candidates;
	}

	/**
	 * Parse namespace declaration at current position.
	 *
	 * Advances position past namespace declaration.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens   PHP tokens
	 * @param int                                        $position Current index (modified)
	 *
	 * @return string|null Namespace string or null
	 */
	private static function parseNamespaceAt ( array $tokens, int &$position ): ?string
	{
		$namespace = '';
		$count     = count( $tokens );
		$position++;

		while ( $position < $count ) {
			$t = $tokens[$position];

			if ( is_string( $t ) && ( $t === ';' || $t === '{' ) ) {
				break;
			}

			if ( is_array( $t ) && ( $t[0] === T_NAME_QUALIFIED || $t[0] === T_STRING ) ) {
				$namespace .= $t[1];
			}

			$position++;
		}

		return $namespace ?: NULL;
	}

	/**
	 * Parse identifier (class/function name) after current position.
	 *
	 * Skips whitespace, returns first T_STRING found.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens   PHP tokens
	 * @param int                                        $position Current index (modified)
	 *
	 * @return string|null Identifier or null
	 */
	private static function parseIdentifierAfter ( array $tokens, int &$position ): ?string
	{
		$count = count( $tokens );
		$position++;

		while ( $position < $count ) {
			$t = $tokens[$position];

			if ( is_array( $t ) ) {
				if ( $t[0] === T_STRING ) {
					return $t[1];
				}
				if ( $t[0] !== T_WHITESPACE ) {
					return NULL;
				}
			}

			$position++;
		}

		return NULL;
	}

	/**
	 * Parse attribute at current position into Parser instance.
	 *
	 * Collects tokens until matching ] bracket.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens   PHP tokens
	 * @param int                                        $position Current index (modified)
	 *
	 * @return Attribute|null Parsed attribute or null if invalid
	 */
	private static function parseAttributeAt ( array $tokens, int &$position ): ?Attribute
	{
		$attrText = '';
		$depth    = 1;
		$count    = count( $tokens );
		$position++;

		while ( $position < $count && $depth > 0 ) {
			$t = $tokens[$position];

			if ( is_string( $t ) ) {
				$attrText .= $t;
				if ( $t === '[' ) {
					$depth++;
				}
				elseif ( $t === ']' ) {
					$depth--;
				}
			}
			else {
				$attrText .= $t[1];
			}

			$position++;
		}

		$parser = new Attribute( $attrText );

		return $parser->getName() !== '' ? $parser : NULL;
	}

	/**
	 * Build Entry from parsed components.
	 *
	 * @param string      $funcName     Function/method name
	 * @param Attribute   $attribute    Parsed attribute
	 * @param bool        $isStatic     Whether static modifier present
	 * @param string|null $namespace    Current namespace
	 * @param string|null $currentClass Current class name (short)
	 * @param string      $file         File path
	 * @param int         $line         Line number
	 *
	 * @return Entry Constructed entry
	 */
	private static function buildCandidate (
		string    $funcName,
		Attribute $attribute,
		bool      $isStatic,
		?string   $namespace,
		?string   $currentClass,
		string    $file,
		int       $line,
	): Entry {
		$priority = $attribute->getArgument( 'priority', 'float', 0 );
		$import   = Import::parse( $attribute->getArgument( 'import', 'string' ) );
		$check    = $attribute->getArgument( 'check', 'bool' );

		$isMethod = $currentClass !== NULL;

		return new Entry(
			type: $isMethod ? 'method' : 'function',
			name: $funcName,
			class: $isMethod ? ( $namespace ? $namespace . '\\' . $currentClass : $currentClass ) : $namespace,
			isStatic: $isMethod ? $isStatic : FALSE,
			priority: $priority,
			file: $file,
			line: $line,
			attribute: $attribute->getName(),
			import: $import,
			check: $check,
		);
	}

}
