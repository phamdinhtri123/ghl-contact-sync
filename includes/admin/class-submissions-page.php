<?php
/**
 * Submissions admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

use GHLContactSync\Database\Submission_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders saved form submissions.
 */
final class Submissions_Page {

	/**
	 * Submission repository.
	 *
	 * @var Submission_Repository
	 */
	private $submissions;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->submissions = new Submission_Repository();
	}

	/**
	 * Register admin actions.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_ghlcs_delete_submissions', array( $this, 'delete_submissions' ) );
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

		?>
		<div class="wrap ghlcs-admin">
			<h1><?php esc_html_e( 'Submissions', 'ghl-contact-sync' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php $this->render_content(); ?>
		</div>
		<?php
	}

	/**
	 * Delete selected submissions.
	 *
	 * @return void
	 */
	public function delete_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete submissions.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_delete_submissions' );

		$ids     = isset( $_POST['submission_ids'] ) && is_array( $_POST['submission_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['submission_ids'] ) ) : array();
		$deleted = $this->submissions->delete_many( $ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'ghl-contact-sync-submissions',
					'message' => 'deleted',
					'deleted' => $deleted,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

		if ( 'deleted' !== $message ) {
			return;
		}

		$deleted = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
		$text    = sprintf( _n( '%d submission deleted.', '%d submissions deleted.', $deleted, 'ghl-contact-sync' ), $deleted );
		?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $text ); ?></p></div>
		<?php
	}

	/**
	 * Render list or detail.
	 *
	 * @return void
	 */
	private function render_content() {
		$submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
		if ( $submission_id ) {
			$this->render_detail( $submission_id );
			return;
		}

		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$result  = $this->submissions->list_submissions(
			array(
				'status'  => $status,
				'search'  => $search,
				'form_id' => $form_id,
			)
		);
		?>
		<form method="get" class="ghlcs-filter-bar">
			<input type="hidden" name="page" value="ghl-contact-sync-submissions">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, phone, GHL contact ID', 'ghl-contact-sync' ); ?>">
			<select name="status">
				<option value=""><?php esc_html_e( 'All sync statuses', 'ghl-contact-sync' ); ?></option>
				<?php foreach ( array( 'pending', 'synced', 'failed', 'disabled' ) as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button"><?php esc_html_e( 'Filter', 'ghl-contact-sync' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ghlcs_delete_submissions">
			<?php wp_nonce_field( 'ghlcs_delete_submissions' ); ?>
			<p><button class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete selected submissions?', 'ghl-contact-sync' ) ); ?>');"><?php esc_html_e( 'Delete Selected', 'ghl-contact-sync' ); ?></button></p>
			<table class="widefat striped">
				<thead><tr><th><input type="checkbox" class="ghlcs-check-all"></th><th><?php esc_html_e( 'ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Form', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Email', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Phone', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Sync Status', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'GHL Contact ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Submitted', 'ghl-contact-sync' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $result['rows'] as $submission ) : ?>
						<tr>
							<td><input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr( $submission['id'] ); ?>"></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-submissions&submission_id=' . absint( $submission['id'] ) ) ); ?>">#<?php echo esc_html( $submission['id'] ); ?></a></td>
							<td><?php echo esc_html( get_the_title( (int) $submission['form_id'] ) ?: sprintf( 'Form #%d', (int) $submission['form_id'] ) ); ?></td>
							<td><?php echo esc_html( $submission['email'] ); ?></td>
							<td><?php echo esc_html( $submission['phone'] ); ?></td>
							<td><?php echo esc_html( $submission['sync_status'] ); ?></td>
							<td><?php echo esc_html( $submission['ghl_contact_id'] ); ?></td>
							<td><?php echo esc_html( $submission['created_at'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $result['rows'] ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No submissions found.', 'ghl-contact-sync' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</form>
		<?php $this->render_check_all_script(); ?>
		<?php
	}

	/**
	 * Render one submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return void
	 */
	private function render_detail( $submission_id ) {
		$submission = $this->submissions->get( $submission_id );
		if ( ! $submission ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Submission not found.', 'ghl-contact-sync' ) . '</p></div>';
			return;
		}
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync-submissions' ) ); ?>">&larr; <?php esc_html_e( 'Back to submissions', 'ghl-contact-sync' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ghlcs-inline-form">
			<input type="hidden" name="action" value="ghlcs_delete_submissions">
			<input type="hidden" name="submission_ids[]" value="<?php echo esc_attr( $submission['id'] ); ?>">
			<?php wp_nonce_field( 'ghlcs_delete_submissions' ); ?>
			<button class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'ghl-contact-sync' ) ); ?>');"><?php esc_html_e( 'Delete Submission', 'ghl-contact-sync' ); ?></button>
		</form>
		<div class="ghlcs-panel">
			<h2><?php echo esc_html( sprintf( 'Submission #%d', (int) $submission['id'] ) ); ?></h2>
			<p><?php echo esc_html( sprintf( 'Form: %s | Sync: %s | GHL Contact ID: %s | Submitted: %s', get_the_title( (int) $submission['form_id'] ) ?: sprintf( 'Form #%d', (int) $submission['form_id'] ), $submission['sync_status'], $submission['ghl_contact_id'], $submission['created_at'] ) ); ?></p>
			<?php if ( ! empty( $submission['last_error'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Last Error:', 'ghl-contact-sync' ); ?></strong> <?php echo esc_html( $submission['last_error'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ghlcs-panel">
			<h2><?php esc_html_e( 'Submitted Data', 'ghl-contact-sync' ); ?></h2>
			<table class="widefat striped"><tbody>
				<?php foreach ( $submission['submission_data'] as $key => $value ) : ?>
					<tr><th><?php echo esc_html( $key ); ?></th><td><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		</div>
		<div class="ghlcs-panel">
			<h2><?php esc_html_e( 'Context', 'ghl-contact-sync' ); ?></h2>
			<p><?php echo esc_html( sprintf( 'Landing Page: %s | Referrer: %s', $submission['landing_page'], $submission['referrer'] ) ); ?></p>
			<p><?php echo esc_html( sprintf( 'UTM Source: %s | Medium: %s | Campaign: %s | Term: %s | Content: %s', $submission['utm_source'], $submission['utm_medium'], $submission['utm_campaign'], $submission['utm_term'], $submission['utm_content'] ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render check-all helper.
	 *
	 * @return void
	 */
	private function render_check_all_script() {
		?>
		<script>
			document.querySelectorAll('.ghlcs-check-all').forEach(function(control) {
				control.addEventListener('change', function() {
					control.closest('table').querySelectorAll('tbody input[type="checkbox"]').forEach(function(checkbox) {
						checkbox.checked = control.checked;
					});
				});
			});
		</script>
		<?php
	}
}
