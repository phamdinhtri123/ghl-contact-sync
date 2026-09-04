<?php
/**
 * Log repository.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes access to the logs table.
 */
final class Log_Repository {

	/**
	 * Get the logs table name.
	 *
	 * @return string
	 */
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ghl_contact_sync_logs';
	}
}

