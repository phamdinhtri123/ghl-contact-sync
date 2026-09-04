<?php
/**
 * Forms admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Phase 1 forms placeholder page.
 */
final class Forms_Page {

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
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'ghl-contact-sync' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'ghl-contact-sync' ); ?>
			</a>
			<hr class="wp-header-end">
			<div class="ghlcs-panel">
				<h2><?php esc_html_e( 'Reusable GHL forms', 'ghl-contact-sync' ); ?></h2>
				<p><?php esc_html_e( 'The full forms table will be implemented in the next phase.', 'ghl-contact-sync' ); ?></p>
			</div>
		</div>
		<?php
	}
}

