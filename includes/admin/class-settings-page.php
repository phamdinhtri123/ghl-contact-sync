<?php
/**
 * Settings admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

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
	 * Save settings.
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

		?>
		<div class="wrap ghlcs-admin">
			<h1><?php esc_html_e( 'Settings', 'ghl-contact-sync' ); ?></h1>

			<?php if ( 'saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'token_removed' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Access token removed.', 'ghl-contact-sync' ); ?></p></div>
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

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'Data & Logs', 'ghl-contact-sync' ); ?></h2>
					<p><label><input type="checkbox" name="logs_enabled" value="1" <?php checked( $logs_enabled ); ?>> <?php esc_html_e( 'Enable plugin logs', 'ghl-contact-sync' ); ?></label></p>
					<p><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_data_on_uninstall ); ?>> <?php esc_html_e( 'Delete plugin data on uninstall', 'ghl-contact-sync' ); ?></label></p>
				</div>

				<?php submit_button( __( 'Save Changes', 'ghl-contact-sync' ) ); ?>
			</form>
		</div>
		<?php
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
