<?php
/**
 * Logs admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Phase 1 logs placeholder page.
 */
final class Logs_Page {

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
			<h1><?php esc_html_e( 'Logs', 'ghl-contact-sync' ); ?></h1>
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'Plugin activity', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'Log listing and retention controls will be implemented in the logging phase.', 'ghl-contact-sync' ); ?></p>
			</div>
		</div>
		<?php
	}
}

