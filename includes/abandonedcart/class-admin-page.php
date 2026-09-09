<?php
/**
 * Abandoned cart admin UI.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

use GHLContactSync\GHL\GHL_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders abandoned cart overview, list, settings, and logs.
 */
final class Admin_Page {

	const FIELD_KEYS = array(
		'cart_id'       => 'Cart ID',
		'cart_status'   => 'Cart Status',
		'cart_total'    => 'Cart Total',
		'cart_currency' => 'Cart Currency',
		'item_count'    => 'Cart Item Count',
		'products'      => 'Cart Products Summary',
		'recovery_url'  => 'Recovery URL',
		'last_activity' => 'Last Cart Activity',
		'abandoned_at'  => 'Abandoned At',
		'order_id'      => 'WooCommerce Order ID',
	);

	/**
	 * Repository.
	 *
	 * @var Cart_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Cart_Repository();
	}

	/**
	 * Register admin post hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_ghlcs_save_abandoned_cart_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ghlcs_refresh_ac_fields', array( $this, 'refresh_fields' ) );
		add_action( 'admin_post_ghlcs_create_ac_fields', array( $this, 'create_fields' ) );
		add_action( 'admin_post_ghlcs_clear_ac_logs', array( $this, 'clear_logs' ) );
		add_action( 'admin_post_ghlcs_delete_empty_ac_carts', array( $this, 'delete_empty_carts' ) );
		add_action( 'admin_post_ghlcs_process_due_ac_carts', array( $this, 'process_due_carts' ) );
	}

	/**
	 * Render active tab.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ghl-contact-sync' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		?>
		<div class="wrap ghlcs-admin">
			<h1><?php esc_html_e( 'Abandoned Cart', 'ghl-contact-sync' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( array( 'overview' => 'Overview', 'carts' => 'Carts', 'settings' => 'Settings', 'logs' => 'Sync Logs' ) as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'carts' === $tab ) {
				$this->render_carts();
			} elseif ( 'settings' === $tab ) {
				$this->render_settings();
			} elseif ( 'logs' === $tab ) {
				$this->render_logs();
			} else {
				$this->render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save settings.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_save_abandoned_cart_settings' );

		$mapping = array();
		foreach ( self::FIELD_KEYS as $key => $label ) {
			$mapping[ $key ] = isset( $_POST['field_mapping'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['field_mapping'][ $key ] ) ) : '';
		}

		Settings::save(
			array(
				'enabled'                  => empty( $_POST['enabled'] ) ? 0 : 1,
				'abandoned_after_minutes'  => max( 1, absint( $_POST['abandoned_after_minutes'] ?? 60 ) ),
				'track_guest_carts'        => empty( $_POST['track_guest_carts'] ) ? 0 : 1,
				'track_logged_in_carts'    => empty( $_POST['track_logged_in_carts'] ) ? 0 : 1,
				'minimum_cart_total'       => $this->decimal( wp_unslash( $_POST['minimum_cart_total'] ?? 0 ) ),
				'ghl_sync_enabled'         => empty( $_POST['ghl_sync_enabled'] ) ? 0 : 1,
				'abandoned_cart_tag'       => sanitize_text_field( wp_unslash( $_POST['abandoned_cart_tag'] ?? 'abandoned-cart' ) ),
				'field_mapping'            => $mapping,
				'recovery_expires_days'    => max( 1, absint( $_POST['recovery_expires_days'] ?? 7 ) ),
				'recovery_redirect'        => in_array( $_POST['recovery_redirect'] ?? 'checkout', array( 'cart', 'checkout' ), true ) ? sanitize_key( $_POST['recovery_redirect'] ) : 'checkout',
				'recovery_cart_behavior'   => in_array( $_POST['recovery_cart_behavior'] ?? 'merge', array( 'merge', 'replace' ), true ) ? sanitize_key( $_POST['recovery_cart_behavior'] ) : 'merge',
				'anonymous_retention_days' => max( 1, absint( $_POST['anonymous_retention_days'] ?? 14 ) ),
				'abandoned_retention_days' => max( 1, absint( $_POST['abandoned_retention_days'] ?? 90 ) ),
				'recovered_retention_days' => max( 1, absint( $_POST['recovered_retention_days'] ?? 90 ) ),
				'logs_retention_days'      => max( 1, absint( $_POST['logs_retention_days'] ?? 30 ) ),
			)
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'settings', 'message' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Refresh GHL custom fields cache.
	 *
	 * @return void
	 */
	public function refresh_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to refresh fields.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_refresh_ac_fields' );

		$result = ( new GHL_Client( get_option( 'ghlcs_settings', array() ) ) )->get_contact_custom_fields();
		if ( ! is_wp_error( $result ) && ! empty( $result['success'] ) ) {
			update_option( 'ghlcs_ac_custom_fields', $result['body'], false );
			delete_option( 'ghlcs_ac_last_field_create_result' );
		} else {
			update_option(
				'ghlcs_ac_last_field_create_result',
				array(
					'errors' => array(
						is_wp_error( $result ) ? $result->get_error_message() : ( $result['message'] ?? __( 'Could not refresh GHL fields.', 'ghl-contact-sync' ) ),
					),
				),
				false
			);
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'settings', 'message' => is_wp_error( $result ) || empty( $result['success'] ) ? 'fields_failed' : 'fields_refreshed' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create missing GHL custom fields and auto-map them.
	 *
	 * @return void
	 */
	public function create_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to create fields.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_create_ac_fields' );

		$client  = new GHL_Client( get_option( 'ghlcs_settings', array() ) );
		$current = $client->get_contact_custom_fields();

		if ( is_wp_error( $current ) || empty( $current['success'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'settings', 'message' => 'fields_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$fields  = $this->fields_from_body( $current['body'] );
		$created = 0;
		$failed  = 0;
		$errors  = array();

		foreach ( $this->recommended_fields() as $key => $field ) {
			if ( $this->find_field_id_by_name( $fields, $field['name'] ) ) {
				continue;
			}

			$result = $client->create_contact_custom_field( $field['name'], $field['type'] );

			if ( is_wp_error( $result ) || empty( $result['success'] ) ) {
				$failed++;
				$errors[] = sprintf(
					'%s: %s',
					$field['name'],
					is_wp_error( $result ) ? $result->get_error_message() : ( $result['message'] ?? __( 'GHL rejected the field create request.', 'ghl-contact-sync' ) )
				);
				continue;
			}

			$created_field = $result['body']['customField'] ?? array();
			if ( ! empty( $created_field['id'] ) ) {
				$fields[] = array(
					'id'   => sanitize_text_field( $created_field['id'] ),
					'name' => sanitize_text_field( $created_field['name'] ?? $field['name'] ),
				);
			}

			$created++;
		}

		update_option(
			'ghlcs_ac_last_field_create_result',
			array(
				'created' => $created,
				'failed'  => $failed,
				'errors'  => $errors,
			),
			false
		);

		$refreshed = $client->get_contact_custom_fields();
		if ( ! is_wp_error( $refreshed ) && ! empty( $refreshed['success'] ) ) {
			update_option( 'ghlcs_ac_custom_fields', $refreshed['body'], false );
			$fields = $this->fields_from_body( $refreshed['body'] );
		}

		$settings = Settings::get();
		foreach ( $this->recommended_fields() as $key => $field ) {
			$field_id = $this->find_field_id_by_name( $fields, $field['name'] );
			if ( $field_id ) {
				$settings['field_mapping'][ $key ] = $field_id;
			}
		}
		Settings::save( $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'ghl-contact-sync-abandoned-cart',
					'tab'     => 'settings',
					'message' => $failed ? 'fields_partial' : ( $created ? 'fields_created' : 'fields_already_exist' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Clear abandoned cart sync logs.
	 *
	 * @return void
	 */
	public function clear_logs() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear logs.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_clear_ac_logs' );
		$wpdb->query( "TRUNCATE TABLE {$this->repository->logs_table_name()}" );

		wp_safe_redirect( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'logs', 'message' => 'cleared' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Delete empty/expired carts from admin.
	 *
	 * @return void
	 */
	public function delete_empty_carts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete carts.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_delete_empty_ac_carts' );

		$deleted = $this->repository->delete_empty_carts();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'ghl-contact-sync-abandoned-cart',
					'tab'     => 'carts',
					'message' => 'empty_deleted',
					'deleted' => $deleted,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Process due abandoned carts immediately for testing.
	 *
	 * @return void
	 */
	public function process_due_carts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to process carts.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_process_due_ac_carts' );

		$settings = Settings::get();
		$service  = new Abandonment_Service( $this->repository );
		$sync     = new GHL_Cart_Sync_Service( $this->repository );
		$count    = 0;

		foreach ( $this->repository->due_for_abandonment( $settings['abandoned_after_minutes'], $settings['minimum_cart_total'], 50 ) as $cart ) {
			$result = $service->mark_abandoned( $cart, false );
			if ( $result && ! empty( $settings['ghl_sync_enabled'] ) ) {
				$sync->sync_abandoned( $result['cart_id'], $result['cycle'], $result['token'] );
			}
			$count++;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'ghl-contact-sync-abandoned-cart',
					'tab'     => 'carts',
					'message' => 'due_processed',
					'count'   => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	private function render_overview() {
		$stats = $this->repository->stats();
		$rate  = $stats['abandoned'] + $stats['recovered'] > 0 ? round( ( $stats['recovered'] / ( $stats['abandoned'] + $stats['recovered'] ) ) * 100, 1 ) : 0;
		?>
		<div class="ghlcs-metrics">
			<?php foreach ( array( 'Active Carts' => $stats['active'], 'Anonymous Carts' => $stats['anonymous'], 'Abandoned Carts' => $stats['abandoned'], 'Recovered Carts' => $stats['recovered'], 'Abandoned Cart Value' => $this->money( $stats['abandoned_value'] ), 'Recovered Revenue' => $this->money( $stats['recovered_revenue'] ), 'Recovery Rate' => $rate . '%' ) as $label => $value ) : ?>
				<div class="ghlcs-metric"><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( $value ); ?></strong></div>
			<?php endforeach; ?>
		</div>
		<div class="ghlcs-panel ghlcs-help-panel">
			<h2><?php esc_html_e( 'GHL Workflow Setup Guide', 'ghl-contact-sync' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Create a GoHighLevel Workflow.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Use Contact Tag Added as the trigger.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Choose the same abandoned cart tag configured in WordPress.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Enable workflow re-entry if future abandoned carts should trigger again.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Add a Wait step.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Before every email, check that the contact still has the abandoned cart tag.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Send the recovery email only on the YES branch.', 'ghl-contact-sync' ); ?></li>
				<li><?php esc_html_e( 'Repeat the tag check before every later recovery email.', 'ghl-contact-sync' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Carts list/detail.
	 *
	 * @return void
	 */
	private function render_carts() {
		$cart_id = isset( $_GET['cart_id'] ) ? absint( $_GET['cart_id'] ) : 0;
		if ( $cart_id ) {
			$this->render_cart_detail( $cart_id );
			return;
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$result = $this->repository->list_carts( array( 'status' => $status, 'search' => $search, 'page' => $page ) );
		?>
		<?php $this->render_carts_notice(); ?>
		<form method="get" class="ghlcs-filter-bar">
			<input type="hidden" name="page" value="ghl-contact-sync-abandoned-cart">
			<input type="hidden" name="tab" value="carts">
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'ghl-contact-sync' ); ?></option>
				<?php foreach ( array( 'active', 'abandoned', 'order_pending', 'recovered', 'expired' ) as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $option ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, cart ID, order ID', 'ghl-contact-sync' ); ?>">
			<button class="button"><?php esc_html_e( 'Filter', 'ghl-contact-sync' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ghlcs-inline-form">
			<input type="hidden" name="action" value="ghlcs_delete_empty_ac_carts">
			<?php wp_nonce_field( 'ghlcs_delete_empty_ac_carts' ); ?>
			<button class="button button-secondary"><?php esc_html_e( 'Delete Empty/Expired Carts', 'ghl-contact-sync' ); ?></button>
			<p class="description"><?php esc_html_e( 'Removes test rows with zero items or expired status. Orders and lead/form data are not touched.', 'ghl-contact-sync' ); ?></p>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ghlcs-inline-form">
			<input type="hidden" name="action" value="ghlcs_process_due_ac_carts">
			<?php wp_nonce_field( 'ghlcs_process_due_ac_carts' ); ?>
			<button class="button button-secondary"><?php esc_html_e( 'Process Due Carts Now', 'ghl-contact-sync' ); ?></button>
			<p class="description"><?php esc_html_e( 'For testing: immediately marks eligible inactive carts as abandoned, syncs cart fields, and adds the configured GHL tag.', 'ghl-contact-sync' ); ?></p>
		</form>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Cart ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Customer', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Email', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Items', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Total', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Current Status', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'GHL Tag State', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Last Activity', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Last Abandoned', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Order ID', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'GHL Sync', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Created', 'ghl-contact-sync' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $result['rows'] as $cart ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'carts', 'cart_id' => (int) $cart['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $cart['cart_uuid'] ); ?></a></td>
						<td><?php echo esc_html( trim( $cart['first_name'] . ' ' . $cart['last_name'] ) ?: __( 'Guest', 'ghl-contact-sync' ) ); ?></td>
						<td><?php echo esc_html( $cart['email'] ); ?></td>
						<td><?php echo esc_html( $cart['item_count'] ); ?></td>
						<td><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $cart['total'] ) : esc_html( $cart['total'] ) ); ?></td>
						<td><?php echo esc_html( $this->status_label( $cart['status'] ) ); ?></td>
						<td><?php echo esc_html( $this->tag_state_label( $cart ) ); ?></td>
						<td><?php echo esc_html( $cart['last_activity_at'] ); ?></td>
						<td><?php echo esc_html( $cart['abandoned_at'] ); ?></td>
						<td><?php echo esc_html( $cart['order_id'] ); ?></td>
						<td><?php echo esc_html( $cart['ghl_sync_status'] ); ?></td>
						<td><?php echo esc_html( $cart['created_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $result['rows'] ) ) : ?>
					<tr><td colspan="12"><?php esc_html_e( 'No carts found.', 'ghl-contact-sync' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render cart list notices.
	 *
	 * @return void
	 */
	private function render_carts_notice() {
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

		if ( 'empty_deleted' === $message ) {
			$deleted = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
			$text    = sprintf( _n( '%d empty or expired cart deleted.', '%d empty or expired carts deleted.', $deleted, 'ghl-contact-sync' ), $deleted );
		} elseif ( 'due_processed' === $message ) {
			$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
			$text  = sprintf( _n( '%d due cart queued for abandonment processing.', '%d due carts queued for abandonment processing.', $count, 'ghl-contact-sync' ), $count );
		} else {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $text ); ?></p></div>
		<?php
	}

	/**
	 * Cart detail.
	 *
	 * @param int $cart_id Cart ID.
	 * @return void
	 */
	private function render_cart_detail( $cart_id ) {
		$cart = $this->repository->get( $cart_id );
		if ( ! $cart ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Cart not found.', 'ghl-contact-sync' ) . '</p></div>';
			return;
		}
		?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ghl-contact-sync-abandoned-cart', 'tab' => 'carts' ), admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to carts', 'ghl-contact-sync' ); ?></a></p>
		<div class="ghlcs-panel">
			<h2><?php esc_html_e( 'Customer', 'ghl-contact-sync' ); ?></h2>
			<p><?php echo esc_html( trim( $cart['first_name'] . ' ' . $cart['last_name'] ) ); ?> &lt;<?php echo esc_html( $cart['email'] ); ?>&gt;</p>
			<p><?php esc_html_e( 'Phone:', 'ghl-contact-sync' ); ?> <?php echo esc_html( $cart['phone'] ); ?> | <?php esc_html_e( 'User ID:', 'ghl-contact-sync' ); ?> <?php echo esc_html( $cart['user_id'] ); ?> | <?php esc_html_e( 'GHL Contact ID:', 'ghl-contact-sync' ); ?> <?php echo esc_html( $cart['ghl_contact_id'] ); ?></p>
		</div>
		<div class="ghlcs-panel">
			<h2><?php esc_html_e( 'Products', 'ghl-contact-sync' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Variation', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Quantity', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Snapshot Price', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Line Total', 'ghl-contact-sync' ); ?></th></tr></thead><tbody>
				<?php foreach ( $cart['cart_data']['items'] ?? array() as $item ) : ?>
					<tr><td><?php echo esc_html( $item['name'] ?? '' ); ?></td><td><code><?php echo esc_html( wp_json_encode( $item['attributes'] ?? array() ) ); ?></code></td><td><?php echo esc_html( $item['quantity'] ?? 0 ); ?></td><td><?php echo esc_html( $item['price'] ?? 0 ); ?></td><td><?php echo esc_html( $item['line_total'] ?? 0 ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		</div>
		<div class="ghlcs-panel">
			<h2><?php esc_html_e( 'Timeline & GHL', 'ghl-contact-sync' ); ?></h2>
			<p><?php echo esc_html( sprintf( 'Current Status: %s | GHL Tag State: %s | Created: %s | Updated: %s | Last Activity: %s | Last Abandoned: %s | Recovered: %s | Order: %s', $this->status_label( $cart['status'] ), $this->tag_state_label( $cart ), $cart['created_at'], $cart['updated_at'], $cart['last_activity_at'], $cart['abandoned_at'], $cart['recovered_at'], $cart['order_id'] ) ); ?></p>
			<p><?php echo esc_html( sprintf( 'Sync: %s | Last Sync: %s | Last Error: %s | Token Expires: %s', $cart['ghl_sync_status'], $cart['ghl_synced_at'], $cart['last_error'], $cart['recovery_expires_at'] ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Human-readable cart status.
	 *
	 * @param string $status Cart status.
	 * @return string
	 */
	private function status_label( $status ) {
		return ucwords( str_replace( '_', ' ', (string) $status ) );
	}

	/**
	 * Expected GHL tag/workflow state from the current cart status.
	 *
	 * @param array $cart Cart row.
	 * @return string
	 */
	private function tag_state_label( array $cart ) {
		if ( 'abandoned' === $cart['status'] ) {
			return __( 'Tag should be active', 'ghl-contact-sync' );
		}

		if ( in_array( $cart['status'], array( 'active', 'order_pending', 'recovered', 'expired' ), true ) && ! empty( $cart['abandoned_at'] ) ) {
			return __( 'Tag should be removed', 'ghl-contact-sync' );
		}

		return __( 'Not tagged yet', 'ghl-contact-sync' );
	}

	/**
	 * Settings form.
	 *
	 * @return void
	 */
	private function render_settings() {
		$settings = Settings::get();
		$fields   = $this->custom_fields();
		$message  = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
		?>
		<?php $this->render_settings_notice( $message ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ghlcs_save_abandoned_cart_settings">
			<?php wp_nonce_field( 'ghlcs_save_abandoned_cart_settings' ); ?>
			<div class="ghlcs-panel"><h2><?php esc_html_e( 'General', 'ghl-contact-sync' ); ?></h2>
				<p><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable Abandoned Cart Tracking', 'ghl-contact-sync' ); ?></label></p>
				<p><label><?php esc_html_e( 'Abandoned After', 'ghl-contact-sync' ); ?> <input type="number" name="abandoned_after_minutes" min="1" value="<?php echo esc_attr( $settings['abandoned_after_minutes'] ); ?>"> <?php esc_html_e( 'minutes', 'ghl-contact-sync' ); ?></label></p>
				<p><label><input type="checkbox" name="track_guest_carts" value="1" <?php checked( $settings['track_guest_carts'] ); ?>> <?php esc_html_e( 'Track Guest Carts', 'ghl-contact-sync' ); ?></label></p>
				<p><label><input type="checkbox" name="track_logged_in_carts" value="1" <?php checked( $settings['track_logged_in_carts'] ); ?>> <?php esc_html_e( 'Track Logged-In Carts', 'ghl-contact-sync' ); ?></label></p>
				<p><label><?php esc_html_e( 'Minimum Cart Total', 'ghl-contact-sync' ); ?> <input type="number" step="0.01" name="minimum_cart_total" value="<?php echo esc_attr( $settings['minimum_cart_total'] ); ?>"></label></p>
			</div>
			<div class="ghlcs-panel"><h2><?php esc_html_e( 'GoHighLevel', 'ghl-contact-sync' ); ?></h2>
				<p><label><input type="checkbox" name="ghl_sync_enabled" value="1" <?php checked( $settings['ghl_sync_enabled'] ); ?>> <?php esc_html_e( 'Enable GHL Cart Sync', 'ghl-contact-sync' ); ?></label></p>
				<p><label><?php esc_html_e( 'Abandoned Cart Tag', 'ghl-contact-sync' ); ?> <input type="text" class="regular-text" name="abandoned_cart_tag" value="<?php echo esc_attr( $settings['abandoned_cart_tag'] ); ?>"></label></p>
				<p class="description"><?php esc_html_e( 'This tag is added when a cart becomes abandoned and removed when the customer returns, starts an order, or completes recovery. Use this tag as the trigger in your GoHighLevel workflow.', 'ghl-contact-sync' ); ?></p>
			</div>
			<div class="ghlcs-panel"><h2><?php esc_html_e( 'GHL Field Mapping', 'ghl-contact-sync' ); ?></h2>
				<?php foreach ( self::FIELD_KEYS as $key => $label ) : ?>
					<p><label><?php echo esc_html( $label ); ?> <?php $this->field_select( 'field_mapping[' . $key . ']', $settings['field_mapping'][ $key ] ?? '', $fields ); ?></label></p>
				<?php endforeach; ?>
			</div>
			<div class="ghlcs-panel"><h2><?php esc_html_e( 'Recovery', 'ghl-contact-sync' ); ?></h2>
				<p><label><?php esc_html_e( 'Recovery Link Expiration', 'ghl-contact-sync' ); ?> <input type="number" name="recovery_expires_days" min="1" value="<?php echo esc_attr( $settings['recovery_expires_days'] ); ?>"> <?php esc_html_e( 'days', 'ghl-contact-sync' ); ?></label></p>
				<p><label><?php esc_html_e( 'Redirect After Recovery', 'ghl-contact-sync' ); ?> <select name="recovery_redirect"><option value="checkout" <?php selected( $settings['recovery_redirect'], 'checkout' ); ?>>Checkout</option><option value="cart" <?php selected( $settings['recovery_redirect'], 'cart' ); ?>>Cart</option></select></label></p>
				<p><label><?php esc_html_e( 'Recovery Cart Behavior', 'ghl-contact-sync' ); ?> <select name="recovery_cart_behavior"><option value="merge" <?php selected( $settings['recovery_cart_behavior'], 'merge' ); ?>>Merge</option><option value="replace" <?php selected( $settings['recovery_cart_behavior'], 'replace' ); ?>>Replace</option></select></label></p>
			</div>
			<div class="ghlcs-panel"><h2><?php esc_html_e( 'Data Retention', 'ghl-contact-sync' ); ?></h2>
				<?php foreach ( array( 'anonymous_retention_days' => 'Anonymous Carts', 'abandoned_retention_days' => 'Abandoned Carts', 'recovered_retention_days' => 'Recovered Carts', 'logs_retention_days' => 'Sync Logs' ) as $key => $label ) : ?>
					<p><label><?php echo esc_html( $label ); ?> <input type="number" min="1" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>"> <?php esc_html_e( 'days', 'ghl-contact-sync' ); ?></label></p>
				<?php endforeach; ?>
			</div>
			<p class="submit"><button class="button button-primary"><?php esc_html_e( 'Save Changes', 'ghl-contact-sync' ); ?></button></p>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ghlcs_refresh_ac_fields">
			<?php wp_nonce_field( 'ghlcs_refresh_ac_fields' ); ?>
			<button class="button"><?php esc_html_e( 'Refresh GHL Fields', 'ghl-contact-sync' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ghlcs-inline-form">
			<input type="hidden" name="action" value="ghlcs_create_ac_fields">
			<?php wp_nonce_field( 'ghlcs_create_ac_fields' ); ?>
			<button class="button button-secondary"><?php esc_html_e( 'Create Missing GHL Fields', 'ghl-contact-sync' ); ?></button>
			<p class="description"><?php esc_html_e( 'Creates the recommended abandoned cart contact fields in GoHighLevel, refreshes the dropdowns, and maps them automatically.', 'ghl-contact-sync' ); ?></p>
		</form>
		<?php
	}

	/**
	 * Logs table.
	 *
	 * @return void
	 */
	private function render_logs() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ghlcs-submit-actions">
			<input type="hidden" name="action" value="ghlcs_clear_ac_logs">
			<?php wp_nonce_field( 'ghlcs_clear_ac_logs' ); ?>
			<button class="button"><?php esc_html_e( 'Clear Logs', 'ghl-contact-sync' ); ?></button>
		</form>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Cart', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Level', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Event', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Message', 'ghl-contact-sync' ); ?></th><th><?php esc_html_e( 'Date', 'ghl-contact-sync' ); ?></th></tr></thead><tbody>
			<?php foreach ( $this->repository->logs( 0, 100 ) as $log ) : ?>
				<tr><td><?php echo esc_html( $log['cart_id'] ); ?></td><td><?php echo esc_html( $log['level'] ); ?></td><td><?php echo esc_html( $log['event'] ); ?></td><td><?php echo esc_html( $log['message'] ); ?></td><td><?php echo esc_html( $log['created_at'] ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/**
	 * Cached custom fields as simple id/name pairs.
	 *
	 * @return array
	 */
	private function custom_fields() {
		$body   = get_option( 'ghlcs_ac_custom_fields', array() );
		return $this->fields_from_body( is_array( $body ) ? $body : array() );
	}

	/**
	 * Render custom field select.
	 *
	 * @param string $name Name.
	 * @param string $selected Selected ID.
	 * @param array  $fields Fields.
	 * @return void
	 */
	private function field_select( $name, $selected, array $fields ) {
		?>
		<select name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php esc_html_e( 'Not mapped', 'ghl-contact-sync' ); ?></option>
			<?php foreach ( $fields as $field ) : ?>
				<option value="<?php echo esc_attr( $field['id'] ); ?>" <?php selected( $selected, $field['id'] ); ?>><?php echo esc_html( $field['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render settings action notices.
	 *
	 * @param string $message Message key.
	 * @return void
	 */
	private function render_settings_notice( $message ) {
		if ( '' === $message ) {
			return;
		}

		$is_error = in_array( $message, array( 'fields_failed', 'fields_partial' ), true );
		$result   = get_option( 'ghlcs_ac_last_field_create_result', array() );
		$text     = array(
			'saved'                => __( 'Settings saved.', 'ghl-contact-sync' ),
			'fields_refreshed'     => __( 'GHL fields refreshed.', 'ghl-contact-sync' ),
			'fields_created'       => __( 'Missing GHL fields created and mapped.', 'ghl-contact-sync' ),
			'fields_already_exist' => __( 'Recommended GHL fields already exist and were mapped.', 'ghl-contact-sync' ),
			'fields_partial'       => __( 'Some GHL fields could not be created.', 'ghl-contact-sync' ),
			'fields_failed'        => __( 'Could not fetch or create GHL fields.', 'ghl-contact-sync' ),
		);
		?>
		<div class="notice <?php echo esc_attr( $is_error ? 'notice-error' : 'notice-success' ); ?> is-dismissible">
			<p><?php echo esc_html( $text[ $message ] ?? $message ); ?></p>
			<?php if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) : ?>
				<ul>
					<?php foreach ( array_slice( $result['errors'], 0, 5 ) as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Recommended GHL fields to create.
	 *
	 * @return array
	 */
	private function recommended_fields() {
		return array(
			'cart_id'       => array( 'name' => 'Abandoned Cart ID', 'type' => 'TEXT' ),
			'cart_status'   => array( 'name' => 'Abandoned Cart Status', 'type' => 'TEXT' ),
			'cart_total'    => array( 'name' => 'Abandoned Cart Total', 'type' => 'TEXT' ),
			'cart_currency' => array( 'name' => 'Abandoned Cart Currency', 'type' => 'TEXT' ),
			'item_count'    => array( 'name' => 'Abandoned Cart Item Count', 'type' => 'TEXT' ),
			'products'      => array( 'name' => 'Abandoned Cart Products', 'type' => 'TEXT' ),
			'recovery_url'  => array( 'name' => 'Abandoned Cart Recovery URL', 'type' => 'TEXT' ),
			'last_activity' => array( 'name' => 'Abandoned Cart Last Activity', 'type' => 'TEXT' ),
			'abandoned_at'  => array( 'name' => 'Abandoned Cart Abandoned At', 'type' => 'TEXT' ),
			'order_id'      => array( 'name' => 'Abandoned Cart Order ID', 'type' => 'TEXT' ),
		);
	}

	/**
	 * Normalize fields from a GHL response body.
	 *
	 * @param array $body API body.
	 * @return array
	 */
	private function fields_from_body( array $body ) {
		$source = $body['customFields'] ?? ( $body['fields'] ?? array() );
		$fields = array();

		foreach ( is_array( $source ) ? $source : array() as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}
			$fields[] = array(
				'id'   => sanitize_text_field( $field['id'] ),
				'name' => sanitize_text_field( $field['name'] ?? $field['fieldKey'] ?? $field['id'] ),
			);
		}

		return $fields;
	}

	/**
	 * Find a GHL field ID by display name.
	 *
	 * @param array  $fields Fields.
	 * @param string $name Name.
	 * @return string
	 */
	private function find_field_id_by_name( array $fields, $name ) {
		$target = strtolower( trim( (string) $name ) );

		foreach ( $fields as $field ) {
			if ( $target === strtolower( trim( (string) $field['name'] ) ) ) {
				return sanitize_text_field( $field['id'] );
			}
		}

		return '';
	}

	/**
	 * Format a decimal with WooCommerce when available.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function decimal( $value ) {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $value );
		}

		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * Format money with WooCommerce when available.
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private function money( $value ) {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $value );
		}

		return number_format_i18n( (float) $value, 2 );
	}
}
