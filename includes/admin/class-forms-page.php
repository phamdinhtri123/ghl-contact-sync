<?php
/**
 * Forms admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

use GHLContactSync\Forms\Form_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and handles the forms list page.
 */
final class Forms_Page {

	/**
	 * Form repository.
	 *
	 * @var Form_Repository
	 */
	private $forms;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->forms = new Form_Repository();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_ghlcs_duplicate_form', array( $this, 'duplicate' ) );
		add_action( 'admin_post_ghlcs_delete_form', array( $this, 'delete' ) );
	}

	/**
	 * Duplicate a form.
	 *
	 * @return void
	 */
	public function duplicate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate forms.', 'ghl-contact-sync' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		check_admin_referer( 'ghlcs_duplicate_form_' . $form_id );

		$new_id = $this->forms->duplicate( $form_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ghl-contact-sync',
					'ghlcs_message' => is_wp_error( $new_id ) ? 'duplicate_failed' : 'duplicated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a form.
	 *
	 * @return void
	 */
	public function delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete forms.', 'ghl-contact-sync' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		check_admin_referer( 'ghlcs_delete_form_' . $form_id );

		$deleted = $this->forms->delete( $form_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ghl-contact-sync',
					'ghlcs_message' => $deleted ? 'deleted' : 'delete_failed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ghl-contact-sync' ) );
		}

		$forms   = $this->forms->all();
		$message = isset( $_GET['ghlcs_message'] ) ? sanitize_key( wp_unslash( $_GET['ghlcs_message'] ) ) : '';

		?>
		<div class="wrap ghlcs-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'ghl-contact-sync' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'ghl-contact-sync' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->render_notice( $message ); ?>

			<table class="wp-list-table widefat fixed striped ghlcs-forms-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Form Name', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Form Type', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Submissions', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Created Date', 'ghl-contact-sync' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ghl-contact-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $forms ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No forms found. Create your first form to get a shortcode.', 'ghl-contact-sync' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $forms as $form ) : ?>
							<?php $shortcode = sprintf( '[ghl_form id="%d"]', $form['id'] ); ?>
							<tr>
								<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-add&form_id=' . absint( $form['id'] ) ) ); ?>"><?php echo esc_html( $form['name'] ); ?></a></strong></td>
								<td><?php echo esc_html( 'contact' === $form['type'] ? __( 'Contact Form', 'ghl-contact-sync' ) : __( 'Newsletter', 'ghl-contact-sync' ) ); ?></td>
								<td><code class="ghlcs-shortcode"><?php echo esc_html( $shortcode ); ?></code> <button type="button" class="button button-small ghlcs-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy', 'ghl-contact-sync' ); ?></button></td>
								<td><?php echo esc_html( $this->forms->count_submissions( $form['id'] ) ); ?></td>
								<td><span class="ghlcs-status-pill is-<?php echo esc_attr( $form['status'] ); ?>"><?php echo esc_html( ucfirst( $form['status'] ) ); ?></span></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $form['created_at'] ) ); ?></td>
								<td class="ghlcs-row-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-add&form_id=' . absint( $form['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'ghl-contact-sync' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ghlcs_duplicate_form&form_id=' . absint( $form['id'] ) ), 'ghlcs_duplicate_form_' . absint( $form['id'] ) ) ); ?>"><?php esc_html_e( 'Duplicate', 'ghl-contact-sync' ); ?></a>
									<a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ghlcs_delete_form&form_id=' . absint( $form['id'] ) ), 'ghlcs_delete_form_' . absint( $form['id'] ) ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this form?', 'ghl-contact-sync' ) ); ?>');"><?php esc_html_e( 'Delete', 'ghl-contact-sync' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script>
			document.querySelectorAll('.ghlcs-copy-shortcode').forEach(function(button) {
				button.addEventListener('click', function() {
					var shortcode = button.getAttribute('data-shortcode');
					navigator.clipboard.writeText(shortcode).then(function() {
						button.textContent = '<?php echo esc_js( __( 'Copied', 'ghl-contact-sync' ) ); ?>';
						setTimeout(function() { button.textContent = '<?php echo esc_js( __( 'Copy', 'ghl-contact-sync' ) ); ?>'; }, 1200);
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Render notices.
	 *
	 * @param string $message Message key.
	 * @return void
	 */
	private function render_notice( $message ) {
		$messages = array(
			'duplicated'       => __( 'Form duplicated.', 'ghl-contact-sync' ),
			'duplicate_failed' => __( 'Form could not be duplicated.', 'ghl-contact-sync' ),
			'deleted'          => __( 'Form deleted.', 'ghl-contact-sync' ),
			'delete_failed'    => __( 'Form could not be deleted.', 'ghl-contact-sync' ),
		);

		if ( empty( $messages[ $message ] ) ) {
			return;
		}

		$type = false === strpos( $message, 'failed' ) ? 'success' : 'error';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $message ] ); ?></p></div>
		<?php
	}
}