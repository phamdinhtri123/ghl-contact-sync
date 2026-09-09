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
			<?php $this->render_content(); ?>
		</div>
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
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Form', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Email', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Phone', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Sync Status', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'GHL Contact ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Submitted', 'ghl-contact-sync' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $result['rows'] as $submission ) : ?>
					<tr>
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
					<tr><td colspan="7"><?php esc_html_e( 'No submissions found.', 'ghl-contact-sync' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
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
}
