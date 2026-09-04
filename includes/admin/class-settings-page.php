<?php
/**
 * Settings admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Phase 1 settings placeholder page.
 */
final class Settings_Page {

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ghl-contact-sync' ) );
		}

		?>
		<div class="wrap ghlcs-admin">
			<h1><?php esc_html_e( 'Settings', 'ghl-contact-sync' ); ?></h1>
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'GHL Connection', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'Location ID, encrypted access token storage, wp-config overrides, and Test Connection will be implemented in the settings phase.', 'ghl-contact-sync' ); ?></p>
			</div>
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'Plugin Updates', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'The update checker bootstrap is present and will run when the plugin-update-checker library and a repository URL are configured.', 'ghl-contact-sync' ); ?></p>
			</div>
		</div>
		<?php
	}
}

