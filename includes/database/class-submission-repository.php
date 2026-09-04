<?php
/**
 * Submission repository.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes access to the submissions table.
 */
final class Submission_Repository {

	/**
	 * Get the submissions table name.
	 *
	 * @return string
	 */
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ghl_contact_sync_submissions';
	}
}

