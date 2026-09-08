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
		$carts           = $wpdb->prefix . 'ghl_contact_sync_carts';
		$cart_sync_logs  = $wpdb->prefix . 'ghl_contact_sync_cart_sync_logs';

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

		$sql_carts = "CREATE TABLE {$carts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cart_uuid varchar(64) NOT NULL,
			session_key varchar(190) NULL,
			contact_id bigint(20) unsigned NULL,
			user_id bigint(20) unsigned NULL,
			ghl_contact_id varchar(190) NULL,
			email varchar(190) NULL,
			first_name varchar(190) NULL,
			last_name varchar(190) NULL,
			phone varchar(80) NULL,
			cart_data longtext NULL,
			item_count int(10) unsigned NOT NULL DEFAULT 0,
			subtotal decimal(18,6) NOT NULL DEFAULT 0,
			total decimal(18,6) NOT NULL DEFAULT 0,
			currency varchar(12) NULL,
			status varchar(30) NOT NULL DEFAULT 'active',
			order_id bigint(20) unsigned NULL,
			abandonment_cycle int(10) unsigned NOT NULL DEFAULT 0,
			recovery_token_hash varchar(255) NULL,
			recovery_expires_at datetime NULL,
			ghl_sync_status varchar(30) NOT NULL DEFAULT 'pending',
			ghl_synced_at datetime NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_activity_at datetime NOT NULL,
			abandoned_at datetime NULL,
			recovered_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cart_uuid (cart_uuid),
			KEY session_key (session_key),
			KEY email (email),
			KEY user_id (user_id),
			KEY status (status),
			KEY order_id (order_id),
			KEY last_activity_at (last_activity_at),
			KEY abandoned_at (abandoned_at)
		) {$charset_collate};";

		$sql_cart_sync_logs = "CREATE TABLE {$cart_sync_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cart_id bigint(20) unsigned NOT NULL DEFAULT 0,
			level varchar(20) NOT NULL DEFAULT 'info',
			event varchar(100) NOT NULL,
			message text NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY cart_id (cart_id),
			KEY level (level),
			KEY event (event),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_logs );
		dbDelta( $sql_carts );
		dbDelta( $sql_cart_sync_logs );
	}

	/**
	 * Seed default settings without overwriting existing values.
	 *
	 * @return void
	 */
	private static function add_default_options() {
		$defaults = array(
			'location_id'              => '',
			'access_token_encrypted'   => '',
			'logs_enabled'             => 1,
			'delete_data_on_uninstall' => 0,
			'abandoned_cart'           => array(
				'enabled'                  => 0,
				'abandoned_after_minutes'  => 60,
				'track_guest_carts'        => 1,
				'track_logged_in_carts'    => 1,
				'minimum_cart_total'       => 0,
				'ghl_sync_enabled'         => 1,
				'abandoned_cart_tag'       => 'abandoned-cart',
				'field_mapping'            => array(),
				'recovery_expires_days'    => 7,
				'recovery_redirect'        => 'checkout',
				'recovery_cart_behavior'   => 'merge',
				'anonymous_retention_days' => 14,
				'abandoned_retention_days' => 90,
				'recovered_retention_days' => 90,
				'logs_retention_days'      => 30,
			),
		);

		$current = get_option( 'ghlcs_settings', array() );

		if ( empty( $current ) || ! is_array( $current ) ) {
			add_option( 'ghlcs_settings', $defaults, '', false );
			return;
		}

		$current['abandoned_cart'] = wp_parse_args( $current['abandoned_cart'] ?? array(), $defaults['abandoned_cart'] );

		update_option( 'ghlcs_settings', wp_parse_args( $current, $defaults ), false );
	}
}
