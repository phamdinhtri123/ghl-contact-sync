<?php
/**
 * Plugin Name: GHL Contact Sync
 * Plugin URI: https://example.com/ghl-contact-sync
 * Description: Build reusable frontend forms, store submissions locally, and sync contacts to GoHighLevel.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: SeaMKT
 * Text Domain: ghl-contact-sync
 * Domain Path: /languages
 *
 * @package GHLContactSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GHLCS_VERSION', '0.1.0' );
define( 'GHLCS_DB_VERSION', '0.1.0' );
define( 'GHLCS_PLUGIN_FILE', __FILE__ );
define( 'GHLCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GHLCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GHLCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GHLCS_PLUGIN_SLUG', 'ghl-contact-sync' );

require_once GHLCS_PLUGIN_DIR . 'includes/class-autoloader.php';

\GHLContactSync\Autoloader::register();

register_activation_hook( __FILE__, array( \GHLContactSync\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \GHLContactSync\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\GHLContactSync\Plugin::instance()->run();
	}
);

