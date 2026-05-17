<?php

declare( strict_types=1 );

namespace Render\Autoloader;

class CodeAnalyzer
{
	/**
	 * Check if PHP code is safe to require without side effects.
	 *
	 * Safe code only contains declarations (classes, functions, traits, etc.)
	 * with no top-level statements that execute on require.
	 *
	 * @param string $code PHP source code
	 *
	 * @return bool True if code can be safely required
	 */
	public static function isSafe ( string $code ): bool
	{
		return self::isSafeTokens( token_get_all( $code ) );
	}

	/**
	 * Extract namespace declaration from PHP code.
	 *
	 * @param string $code PHP source code
	 *
	 * @return string|null Namespace string or null if none declared
	 */
	public static function extractNamespace ( string $code ): ?string
	{
		return self::extractNamespaceFromTokens( token_get_all( $code ) );
	}

	/**
	 * Analyze tokens for top-level side effects.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens PHP tokens
	 *
	 * @return bool True if no executable statements at top level
	 */
	private static function isSafeTokens ( array $tokens ): bool
	{
		$braceDepth     = 0;
		$parenDepth     = 0;
		$attrDepth      = 0;
		$arrowFnStack   = [];
		$pendingArrowFn = FALSE;
		$count          = count( $tokens );
		$skipNextString = FALSE;
		$skipStatement  = FALSE;
		$skipSignature  = FALSE;

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[$i];

			if ( is_string( $token ) ) {
				if ( $token === '{' ) {
					$braceDepth++;
				}
				elseif ( $token === '}' ) {
					$braceDepth--;
				}
				elseif ( $token === '(' ) {
					$parenDepth++;
				}
				elseif ( $token === ')' ) {
					$parenDepth--;
					while ( $arrowFnStack && $parenDepth < end( $arrowFnStack ) ) {
						array_pop( $arrowFnStack );
					}
				}
				elseif ( $token === ';' ) {
					$arrowFnStack   = [];
					$pendingArrowFn = FALSE;
					$skipStatement  = FALSE;
					$skipSignature  = FALSE;
				}
				elseif ( $token === '[' && $attrDepth > 0 ) {
					$attrDepth++;
				}
				elseif ( $token === ']' && $attrDepth > 0 ) {
					$attrDepth--;
				}

				if ( $token === '{' && $skipSignature ) {
					$skipSignature = FALSE;
				}

				continue;
			}

			[ $id ] = $token;

			if ( $id === T_ATTRIBUTE ) {
				$attrDepth = 1;
				continue;
			}

			if ( $attrDepth > 0 ) {
				continue;
			}

			if ( $id === T_FN ) {
				$pendingArrowFn = TRUE;
				continue;
			}

			if ( $id === T_DOUBLE_ARROW && $pendingArrowFn ) {
				$arrowFnStack[] = $parenDepth;
				$pendingArrowFn = FALSE;
				continue;
			}

			if ( in_array( $id, [ T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_CONST, T_USE ], TRUE ) ) {
				$skipNextString = TRUE;
				$pendingArrowFn = FALSE;
				$skipSignature  = $id !== T_CONST && $id !== T_USE;
				$skipStatement  = $id === T_CONST || $id === T_USE;
				continue;
			}

			if ( in_array( $id, [ T_DECLARE, T_NAMESPACE ], TRUE ) ) {
				$skipStatement = TRUE;
				continue;
			}

			$inArrowFnBody = !empty( $arrowFnStack );

			if ( $braceDepth === 0 && !$inArrowFnBody ) {
				if ( $skipStatement || $skipSignature ) {
					continue;
				}

				if ( $id === T_STRING && $skipNextString ) {
					$skipNextString = FALSE;
					continue;
				}

				if ( $id === T_STRING && self::isFollowedByOpenParen( $tokens, $i ) ) {
					return FALSE;
				}

				if ( !in_array( $id, [
					T_WHITESPACE,
					T_COMMENT,
					T_DOC_COMMENT,
					T_OPEN_TAG,
					T_FINAL,
					T_ABSTRACT,
					T_READONLY,
				], TRUE ) ) {
					return FALSE;
				}
			}

			if ( $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT ) {
				$skipNextString = FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Extract namespace from token stream.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens PHP tokens
	 *
	 * @return string|null Namespace or null
	 */
	private static function extractNamespaceFromTokens ( array $tokens ): ?string
	{
		$count = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[$i];

			if ( !is_array( $token ) || $token[0] !== T_NAMESPACE ) {
				continue;
			}

			$namespace = '';
			$i++;

			while ( $i < $count ) {
				$t = $tokens[$i];

				if ( is_string( $t ) && ( $t === ';' || $t === '{' ) ) {
					break;
				}

				if ( is_array( $t ) && ( $t[0] === T_NAME_QUALIFIED || $t[0] === T_STRING ) ) {
					$namespace .= $t[1];
				}

				$i++;
			}

			return $namespace ?: NULL;
		}

		return NULL;
	}

	/**
	 * Check if token at position is followed by open parenthesis.
	 *
	 * Skips whitespace between token and paren.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens   PHP tokens
	 * @param int                                        $position Current token index
	 *
	 * @return bool True if followed by "("
	 */
	private static function isFollowedByOpenParen ( array $tokens, int $position ): bool
	{
		$count = count( $tokens );
		$position++;

		while ( $position < $count ) {
			$t = $tokens[$position];

			if ( is_array( $t ) && $t[0] === T_WHITESPACE ) {
				$position++;
				continue;
			}

			return is_string( $t ) && $t === '(';
		}

		return FALSE;
	}
}
