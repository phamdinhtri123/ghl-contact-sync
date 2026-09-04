<?php
/**
 * Activation routines.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation and database setup.
 */
final class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::add_default_options();

		update_option( 'ghlcs_version', GHLCS_VERSION, false );
		update_option( 'ghlcs_db_version', GHLCS_DB_VERSION, false );
	}

	/**
	 * Upgrade database schema when the installed schema version is stale.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_db_version = get_option( 'ghlcs_db_version' );

		if ( GHLCS_DB_VERSION === $installed_db_version ) {
			return;
		}

		self::create_tables();
		self::add_default_options();

		update_option( 'ghlcs_version', GHLCS_VERSION, false );
		update_option( 'ghlcs_db_version', GHLCS_DB_VERSION, false );
	}

	/**
	 * Create plugin-owned database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = $wpdb->prefix . 'ghl_contact_sync_submissions';
		$logs            = $wpdb->prefix . 'ghl_contact_sync_logs';

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			submission_data longtext NULL,
			email varchar(190) NULL,
			phone varchar(80) NULL,
			ghl_contact_id varchar(190) NULL,
			sync_status varchar(20) NOT NULL DEFAULT 'pending',
			sync_attempts int(10) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			ip_hash varchar(128) NULL,
			user_agent text NULL,
			landing_page text NULL,
			referrer text NULL,
			utm_source varchar(190) NULL,
			utm_medium varchar(190) NULL,
			utm_campaign varchar(190) NULL,
			utm_term varchar(190) NULL,
			utm_content varchar(190) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY email (email),
			KEY sync_status (sync_status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			event varchar(100) NOT NULL,
			message text NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY event (event),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_logs );
	}

	/**
	 * Seed default settings without overwriting existing values.
	 *
	 * @return void
	 */
	private static function add_default_options() {
		add_option(
			'ghlcs_settings',
			array(
				'location_id'              => '',
				'access_token_encrypted'   => '',
				'logs_enabled'             => 1,
				'delete_data_on_uninstall' => 0,
				'update_repository_url'    => '',
				'update_branch'            => 'main',
			),
			'',
			false
		);
	}
}
