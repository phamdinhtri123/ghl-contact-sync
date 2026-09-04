<?php
/**
 * Form editor admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Phase 1 form editor placeholder.
 */
final class Form_Editor {

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
			<h1><?php esc_html_e( 'Add New Form', 'ghl-contact-sync' ); ?></h1>
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'Form Builder', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'General settings, newsletter defaults, contact fields, layouts, themes, and submit behavior will be added in the form builder phase.', 'ghl-contact-sync' ); ?></p>
			</div>
		</div>
		<?php
	}
}

