<?php
/**
 * Cloudflare Extension
 *
 * @package PoweredCache\Extensions
 */

namespace PoweredCache\Extensions\Cloudflare;

use function PoweredCache\Utils\get_decrypted_setting;

/**
 * Class Cloudflare
 */
class Cloudflare {

	/**
	 * CF API Class
	 *
	 * @var $cf_api
	 */
	private $cf_api;

	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	private $settings;

	/**
	 * Placeholder constructor
	 */
	public function __construct() {
	}


	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @return object
	 * @since  1.0
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks
	 */
	public function setup() {
		$this->settings = \PoweredCache\Utils\get_settings();
		$cf_api_key     = self::get_cf_api_key();
		$cf_api_token   = self::get_cf_api_token();

		$this->cf_api = API::factory( $this->settings['cloudflare_email'], $cf_api_key, $cf_api_token );

		// Fixes Flexible SSL
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
			$_SERVER['HTTPS'] = 'on';
		}

		// real user ip
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP']; // phpcs:ignore
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		add_action( 'powered_cache_flushed', [ $this, 'delete_cloudflare_cache_on_flush' ] );
		add_action( 'admin_post_powered_cache_purge_cf_cache', [ $this, 'delete_cloudflare_cache' ] );

	}

	/**
	 * Add admin bar CF flush menu
	 *
	 * @param Object $wp_admin_bar admin bar object
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'id'     => 'cf-purge-cache',
				'title'  => esc_html__( 'Purge Cloudflare Cache', 'powered-cache' ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_cf_cache' ), 'powered_cache_purge_cf_cache' ),
				'parent' => 'powered-cache',
			)
		);
	}

	/**
	 * Delete CF cache when deleting all cache
	 */
	public function delete_cloudflare_cache_on_flush() {
		$this->cf_api->purge( $this->settings['cloudflare_zone'] );
	}

	/**
	 * Delete CF cache when it triggered from admin menu
	 */
	public function delete_cloudflare_cache() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_cf_cache' ) ) { // phpcs:ignore
			wp_nonce_ays( '' );
		}

		if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
			$redirect_url = add_query_arg( 'pc_action', 'generic_permission_err', wp_get_referer() );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$redirect_url = add_query_arg( 'pc_action', 'generic_permission_err', wp_get_referer() );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		if ( $this->cf_api->purge( $this->settings['cloudflare_zone'] ) ) {
			$redirect_url = add_query_arg( 'pc_action', 'flush_cf_cache', wp_get_referer() );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		$redirect_url = add_query_arg( 'pc_action', 'flush_cf_cache_failed', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Get CF API Key
	 *
	 * @return bool|mixed|string
	 */
	public static function get_cf_api_key() {
		if ( defined( 'POWERED_CACHE_CF_API_KEY' ) && POWERED_CACHE_CF_API_KEY ) {
			return POWERED_CACHE_CF_API_KEY;
		}

		$cf_api_key = get_decrypted_setting( 'cloudflare_api_key' );

		return $cf_api_key;
	}

	/**
	 * Get CF API Token
	 *
	 * @return bool|mixed|string
	 */
	public static function get_cf_api_token() {
		if ( defined( 'POWERED_CACHE_CF_API_TOKEN' ) && POWERED_CACHE_CF_API_TOKEN ) {
			return POWERED_CACHE_CF_API_TOKEN;
		}

		$cf_api_token = get_decrypted_setting( 'cloudflare_api_token' );

		return $cf_api_token;
	}

}
