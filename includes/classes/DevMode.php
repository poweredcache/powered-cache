<?php
/**
 * Powered Cache Development Mode Handler
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Utils;
use const PoweredCache\Constants\SETTING_OPTION;

defined( 'ABSPATH' ) || exit;

/**
 * Development Mode handler for Powered Cache.
 * Handles admin bar integration, UI display, and disabling logic
 * when development mode is active.
 *
 * @since 3.6
 */
class DevMode {

	/**
	 * Initialize development mode behaviors.
	 *
	 * @return void
	 * @since 3.6
	 */
	public static function setup() {
		add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_item' ], 100 );
		add_action( 'admin_head', [ __CLASS__, 'add_admin_bar_css' ] );
		add_action( 'wp_head', [ __CLASS__, 'add_admin_bar_css' ] );
		add_action( 'admin_post_powered_cache_exit_dev_mode', [ __CLASS__, 'handle_exit_dev_mode' ] );
	}

	/**
	 * Add admin bar button for disabling development mode.
	 * Only shown to authorized users based on network mode.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar object.
	 *
	 * @return void
	 * @since 3.6
	 */
	public static function add_admin_bar_item( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$title = is_admin() ? esc_html__( 'Disable Dev Mode', 'powered-cache' ) : esc_html__( 'Powered Cache: Disable Dev Mode', 'powered-cache' );

		$wp_admin_bar->add_node(
			[
				'id'    => 'powered-cache-exit-dev-mode',
				'title' => '🚧 ' . $title,
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_exit_dev_mode' ), 'powered_cache_exit_dev_mode' ),
				'meta'  => [ 'class' => 'powered-cache-dev-mode-warning' ],
			]
		);
	}

	/**
	 * Inject custom styling into admin bar when dev mode is active.
	 *
	 * @return void
	 * @since 3.6
	 */
	public static function add_admin_bar_css() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		echo '<style>
			#wpadminbar #wp-admin-bar-powered-cache-exit-dev-mode > .ab-item {
				background-color: #f1c40f !important;
				color: #000 !important;
				font-weight: bold;
			}
		</style>';
	}

	/**
	 * Handle the request to disable development mode.
	 * Only authorized users can perform this action based on the context.
	 *
	 * @return void
	 * @since 3.6
	 */
	public static function handle_exit_dev_mode() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'powered-cache' ) );
		}

		if ( ! check_admin_referer( 'powered_cache_exit_dev_mode' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'powered-cache' ) );
		}

		$settings             = Utils\get_settings();
		$settings['dev_mode'] = false;

		if ( POWERED_CACHE_IS_NETWORK ) {
			update_site_option( SETTING_OPTION, $settings );
		} else {
			update_option( SETTING_OPTION, $settings );
		}

		Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );

		$redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=powered-cache' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

}
