<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package GHLContactSync
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'ghlcs_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ghl_contact_sync_submissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ghl_contact_sync_logs" );

delete_option( 'ghlcs_settings' );
delete_option( 'ghlcs_version' );
delete_option( 'ghlcs_db_version' );

$forms = get_posts(
	array(
		'post_type'      => 'ghlcs_form',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $forms as $form_id ) {
	wp_delete_post( (int) $form_id, true );
}

