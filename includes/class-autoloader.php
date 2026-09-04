<?php
/**
 * Lightweight class autoloader.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin classes from the includes directory.
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes in the GHLContactSync namespace.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$file     = 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		$path     = GHLCS_PLUGIN_DIR . 'includes/';

		if ( ! empty( $parts ) ) {
			$path .= strtolower( implode( '/', $parts ) ) . '/';
		}

		$path .= $file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

