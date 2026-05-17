<?php

declare( strict_types=1 );

namespace Render\Autoloader;

class Filesystem
{
	public static function makeDirectory ( string $target ): bool
	{
		$target = str_replace( '//', '/', $target );
		$target = rtrim( $target, '/' );
		if ( empty( $target ) ) {
			$target = '/';
		}

		if ( file_exists( $target ) ) {
			return @is_dir( $target );
		}

		// Do not allow path traversals.
		if ( str_contains( $target, '../' ) || str_contains( $target, '..' . DIRECTORY_SEPARATOR ) ) {
			return FALSE;
		}

		// We need to find the permissions of the parent folder that exists and inherit that.
		$target_parent = dirname( $target );
		while ( '.' !== $target_parent && !is_dir( $target_parent ) && dirname( $target_parent ) !== $target_parent ) {
			$target_parent = dirname( $target_parent );
		}

		// Get the permission bits.
		$stat = @stat( $target_parent );
		if ( $stat ) {
			$dir_perms = $stat['mode'] & 0007777;
		}
		else {
			$dir_perms = 0777;
		}

		if ( @mkdir( $target, $dir_perms, TRUE ) ) {

			/*
			 * If a umask is set that modifies $dir_perms, we'll have to re-set
			 * the $dir_perms correctly with chmod()
			 */
			if ( ( $dir_perms & ~umask() ) !== $dir_perms ) {
				$folder_parts = explode( '/', substr( $target, strlen( $target_parent ) + 1 ) );
				for ( $i = 1, $c = count( $folder_parts ); $i <= $c; $i++ ) {
					chmod( $target_parent . '/' . implode( '/', array_slice( $folder_parts, 0, $i ) ), $dir_perms );
				}
			}

			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Write content to file, creating directories as needed.
	 *
	 * @param string $path    Absolute file path
	 * @param string $content Content to write
	 *
	 * @return int|false True on success
	 */
	public static function writeFile ( string $path, string $content ): int|false
	{
		if ( !self::makeDirectory( dirname( $path ) ) ) {
			return FALSE;
		}

		return file_put_contents( $path, $content );
	}
}
