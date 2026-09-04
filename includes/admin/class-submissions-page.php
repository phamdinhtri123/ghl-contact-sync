<?php
/**
 * Submissions admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Phase 1 submissions placeholder page.
 */
final class Submissions_Page {

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
			<h1><?php esc_html_e( 'Submissions', 'ghl-contact-sync' ); ?></h1>
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'Local submission storage', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'The submissions table, view action, retry action, and delete action will be implemented after frontend submission storage is wired.', 'ghl-contact-sync' ); ?></p>
			</div>
		</div>
		<?php
	}
}

