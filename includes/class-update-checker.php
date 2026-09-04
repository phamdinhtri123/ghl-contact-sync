<?php
/**
 * Plugin Update Checker integration.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads YahnisElsts Plugin Update Checker when bundled with this plugin.
 */
final class Update_Checker {

	/**
	 * Register update checker on plugins_loaded.
	 *
	 * @return void
	 */
	public function hooks() {
		$this->boot();
	}

	/**
	 * Initialize the update checker if the library and repository URL are available.
	 *
	 * @return void
	 */
	private function boot() {
		$library = GHLCS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $library ) ) {
			return;
		}

		require_once $library;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$repository_url = $this->get_repository_url();

		if ( empty( $repository_url ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$repository_url,
			GHLCS_PLUGIN_FILE,
			GHLCS_PLUGIN_SLUG
		);

		$branch = $this->get_branch();

		if ( method_exists( $checker, 'setBranch' ) && ! empty( $branch ) ) {
			$checker->setBranch( $branch );
		}
	}

	/**
	 * Get the configured Git repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url() {
		if ( defined( 'GHLCS_UPDATE_REPOSITORY_URL' ) ) {
			return esc_url_raw( GHLCS_UPDATE_REPOSITORY_URL );
		}

		$settings = get_option( 'ghlcs_settings', array() );

		return ! empty( $settings['update_repository_url'] ) ? esc_url_raw( $settings['update_repository_url'] ) : '';
	}

	/**
	 * Get update branch.
	 *
	 * @return string
	 */
	private function get_branch() {
		if ( defined( 'GHLCS_UPDATE_BRANCH' ) ) {
			return sanitize_key( GHLCS_UPDATE_BRANCH );
		}

		$settings = get_option( 'ghlcs_settings', array() );

		return ! empty( $settings['update_branch'] ) ? sanitize_key( $settings['update_branch'] ) : 'main';
	}
}

