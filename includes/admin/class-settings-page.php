<?php
/**
 * Settings admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

use GHLContactSync\GHL\GHL_Client;
use GHLContactSync\Security\Token_Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves plugin settings.
 */
final class Settings_Page {

	/**
	 * Register page hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_ghlcs_save_settings', array( $this, 'save' ) );
	}

	/**
	 * Save settings and optionally test the GHL connection.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_save_settings' );

		$settings = get_option( 'ghlcs_settings', array() );

		if ( ! defined( 'GHL_CONTACT_SYNC_LOCATION_ID' ) ) {
			$settings['location_id'] = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : '';
		}

		if ( ! defined( 'GHL_CONTACT_SYNC_ACCESS_TOKEN' ) ) {
			if ( ! empty( $_POST['remove_access_token'] ) ) {
				$settings['access_token_encrypted'] = '';
				delete_option( 'ghlcs_last_connection_test' );
			} elseif ( ! empty( $_POST['access_token'] ) ) {
				$encrypted_token = Token_Encryption::encrypt_token( sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) );

				if ( is_wp_error( $encrypted_token ) ) {
					$this->redirect_with_message( 'token_error' );
				}

				$settings['access_token_encrypted'] = $encrypted_token;
			}
		}

		$settings['logs_enabled']             = empty( $_POST['logs_enabled'] ) ? 0 : 1;
		$settings['delete_data_on_uninstall'] = empty( $_POST['delete_data_on_uninstall'] ) ? 0 : 1;

		update_option( 'ghlcs_settings', $settings, false );

		if ( ! empty( $_POST['test_connection'] ) ) {
			$client = new GHL_Client( $settings );
			$result = $client->test_connection();

			update_option( 'ghlcs_last_connection_test', $result, false );

			$this->redirect_with_message( ! empty( $result['connected'] ) && ! empty( $result['contacts_accessible'] ) ? 'test_success' : 'test_failed' );
		}

		$this->redirect_with_message( ! empty( $_POST['remove_access_token'] ) ? 'token_removed' : 'saved' );
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

		$settings                 = get_option( 'ghlcs_settings', array() );
		$location_from_constant   = defined( 'GHL_CONTACT_SYNC_LOCATION_ID' );
		$token_from_constant      = defined( 'GHL_CONTACT_SYNC_ACCESS_TOKEN' );
		$location_id              = $location_from_constant ? GHL_CONTACT_SYNC_LOCATION_ID : ( $settings['location_id'] ?? '' );
		$has_token                = $token_from_constant || ! empty( $settings['access_token_encrypted'] );
		$logs_enabled             = ! empty( $settings['logs_enabled'] );
		$delete_data_on_uninstall = ! empty( $settings['delete_data_on_uninstall'] );
		$message                  = isset( $_GET['ghlcs_message'] ) ? sanitize_key( wp_unslash( $_GET['ghlcs_message'] ) ) : '';
		$connection_test          = get_option( 'ghlcs_last_connection_test', array() );

		?>
		<div class="wrap ghlcs-admin">
			<h1><?php esc_html_e( 'Settings', 'ghl-contact-sync' ); ?></h1>

			<?php if ( 'saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'token_removed' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Access token removed.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'test_success' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connected successfully.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'test_failed' === $message ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Connection test failed.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'token_error' === $message ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Access token could not be encrypted on this server. Please make sure the PHP OpenSSL extension is enabled.', 'ghl-contact-sync' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ghlcs_save_settings">
				<?php wp_nonce_field( 'ghlcs_save_settings' ); ?>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'GHL Connection', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="ghlcs-location-id"><?php esc_html_e( 'Location ID', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<input type="text" id="ghlcs-location-id" name="location_id" class="regular-text" value="<?php echo esc_attr( $location_id ); ?>" <?php disabled( $location_from_constant ); ?>>
									<?php if ( $location_from_constant ) : ?>
										<p class="description"><?php esc_html_e( 'Location ID is configured in wp-config.php and overrides this field.', 'ghl-contact-sync' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Access Token', 'ghl-contact-sync' ); ?></th>
								<td>
									<?php if ( $has_token ) : ?>
										<div class="ghlcs-token-row">
											<input type="text" class="regular-text code" value="<?php echo esc_attr( $this->masked_token() ); ?>" readonly>
											<?php if ( ! $token_from_constant ) : ?>
												<button type="submit" name="remove_access_token" value="1" class="button button-secondary"><?php esc_html_e( 'Remove', 'ghl-contact-sync' ); ?></button>
											<?php endif; ?>
										</div>
										<?php if ( $token_from_constant ) : ?>
											<p class="description"><?php esc_html_e( 'Access Token is configured in wp-config.php and cannot be removed here.', 'ghl-contact-sync' ); ?></p>
										<?php else : ?>
											<p class="description"><?php esc_html_e( 'Stored token is masked for security.', 'ghl-contact-sync' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>

									<?php if ( ! $token_from_constant ) : ?>
										<label class="ghlcs-token-replace" for="ghlcs-access-token">
											<?php echo esc_html( $has_token ? __( 'Replace Access Token', 'ghl-contact-sync' ) : __( 'Access Token', 'ghl-contact-sync' ) ); ?>
										</label>
										<input type="password" id="ghlcs-access-token" name="access_token" class="regular-text" value="" placeholder="<?php esc_attr_e( 'Paste your GHL access token', 'ghl-contact-sync' ); ?>" autocomplete="new-password">
										<?php if ( $has_token ) : ?>
											<p class="description"><?php esc_html_e( 'Leave this field blank to keep the existing token.', 'ghl-contact-sync' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<p class="submit ghlcs-submit-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'ghl-contact-sync' ); ?></button>
					<button type="submit" name="test_connection" value="1" class="button button-secondary"><?php esc_html_e( 'Test Connection', 'ghl-contact-sync' ); ?></button>
				</p>

				<?php $this->render_connection_result( $connection_test ); ?>

				<div class="ghlcs-panel ghlcs-help-panel">
					<h2><?php esc_html_e( 'How to Find Your GHL Location ID', 'ghl-contact-sync' ); ?></h2>
					<p><?php esc_html_e( 'Each GoHighLevel Sub-Account has a unique Location ID. Use the Location ID for the same Sub-Account used to create your Access Token.', 'ghl-contact-sync' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Log in to your GoHighLevel account.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Open the Sub-Account (Location) that you want to connect to this website.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Go to Settings -> Business Profile.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Find the Location ID in the Business Information section.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Copy the Location ID and paste it into the Location ID field above.', 'ghl-contact-sync' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'A Location ID usually looks similar to:', 'ghl-contact-sync' ); ?></p>
					<p><code>ve9EPM428h8vSh1RW1KT</code></p>
					<p><strong><?php esc_html_e( 'Important:', 'ghl-contact-sync' ); ?></strong> <?php esc_html_e( 'The Location ID and Access Token must belong to the same GoHighLevel Sub-Account.', 'ghl-contact-sync' ); ?></p>
				</div>

				<div class="ghlcs-panel ghlcs-help-panel">
					<h2><?php esc_html_e( 'How to Get Your GHL Access Token', 'ghl-contact-sync' ); ?></h2>
					<p><?php esc_html_e( 'Follow these steps to connect this website to your GoHighLevel account:', 'ghl-contact-sync' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Log in to your GoHighLevel account.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Open the Sub-Account (Location) that you want to connect to this website.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Go to Settings -> Private Integrations.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Click Create New Private Integration.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Enter a name for the integration, for example: WordPress - GHL Contact Sync.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Select permissions: Contacts - View Contacts, Contacts - Edit Contacts, Locations - View Locations.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Create the Private Integration.', 'ghl-contact-sync' ); ?></li>
						<li><?php esc_html_e( 'Copy the generated Access Token and paste it into the Access Token field above.', 'ghl-contact-sync' ); ?></li>
					</ol>
					<p><strong><?php esc_html_e( 'Important:', 'ghl-contact-sync' ); ?></strong> <?php esc_html_e( 'Keep your Access Token secure. Do not share it publicly or expose it in frontend code.', 'ghl-contact-sync' ); ?></p>
				</div>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'Data & Logs', 'ghl-contact-sync' ); ?></h2>
					<p><label><input type="checkbox" name="logs_enabled" value="1" <?php checked( $logs_enabled ); ?>> <?php esc_html_e( 'Enable plugin logs', 'ghl-contact-sync' ); ?></label></p>
					<p><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_data_on_uninstall ); ?>> <?php esc_html_e( 'Delete plugin data on uninstall', 'ghl-contact-sync' ); ?></label></p>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Render the most recent connection test result.
	 *
	 * @param array $result Connection test result.
	 * @return void
	 */
	private function render_connection_result( $result ) {
		if ( empty( $result ) || ! is_array( $result ) ) {
			return;
		}

		$connected           = ! empty( $result['connected'] );
		$contacts_accessible = ! empty( $result['contacts_accessible'] );
		$checked_at          = ! empty( $result['checked_at'] ) ? (int) $result['checked_at'] : current_time( 'timestamp' );
		$status_label        = $connected && $contacts_accessible ? __( 'Connected', 'ghl-contact-sync' ) : __( 'Connection Failed', 'ghl-contact-sync' );
		?>
		<div class="ghlcs-connection-result <?php echo esc_attr( $connected && $contacts_accessible ? 'is-success' : 'is-failed' ); ?>">
			<h3><?php esc_html_e( 'Connection Status', 'ghl-contact-sync' ); ?></h3>
			<div class="ghlcs-result-status"><?php echo esc_html( '. ' . $status_label ); ?></div>
			<dl>
				<dt><?php esc_html_e( 'Location', 'ghl-contact-sync' ); ?></dt>
				<dd><?php echo esc_html( $result['location_name'] ?? __( 'Unable to verify', 'ghl-contact-sync' ) ); ?></dd>

				<dt><?php esc_html_e( 'Location ID', 'ghl-contact-sync' ); ?></dt>
				<dd><?php echo esc_html( $this->mask_location_id( $result['location_id'] ?? '' ) ); ?></dd>

				<dt><?php esc_html_e( 'Contacts', 'ghl-contact-sync' ); ?></dt>
				<dd><?php echo esc_html( $contacts_accessible ? 'Accessible' : 'Not accessible' ); ?></dd>

				<?php if ( ! empty( $result['error'] ) ) : ?>
					<dt><?php esc_html_e( 'Error', 'ghl-contact-sync' ); ?></dt>
					<dd><?php echo esc_html( $result['error'] ); ?></dd>
				<?php endif; ?>

				<dt><?php esc_html_e( 'Last checked', 'ghl-contact-sync' ); ?></dt>
				<dd><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A', $checked_at ) ); ?></dd>
			</dl>
		</div>
		<?php
	}

	/**
	 * Mask Location ID in the test result.
	 *
	 * @param string $location_id Location ID.
	 * @return string
	 */
	private function mask_location_id( $location_id ) {
		if ( '' === $location_id ) {
			return '****************';
		}

		return str_repeat( 'x', min( 16, strlen( $location_id ) ) );
	}

	/**
	 * Return a masked token display value.
	 *
	 * @return string
	 */
	private function masked_token() {
		return '****************....';
	}

	/**
	 * Redirect back to settings with a status message.
	 *
	 * @param string $message Message key.
	 * @return void
	 */
	private function redirect_with_message( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ghl-contact-sync-settings',
					'ghlcs_message' => sanitize_key( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
