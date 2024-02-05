<?php
/**
 * Cloudflare Extension
 *
 * @package PoweredCache\Extensions
 */

namespace PoweredCache\Extensions\Cloudflare;

use function PoweredCache\Utils\get_decrypted_setting;
use function PoweredCache\Utils\is_ip_in_range;

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
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && isset( $_SERVER['REMOTE_ADDR'] ) && self::is_cf_ip() ) {
			$cf_ip     = filter_var( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ), FILTER_VALIDATE_IP );
			$remote_ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );

			// Overwrite REMOTE_ADDR with the valid Cloudflare IP only if they are different.
			if ( false !== $cf_ip && $cf_ip !== $remote_ip ) {
				$_SERVER['REMOTE_ADDR'] = $cf_ip;
			}
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

	/**
	 * Check if a request comes from a CloudFlare IP.
	 *
	 * @return bool
	 */
	public static function is_cf_ip() {
		$cloudflare_ips = [
			// Cloudflare IPv4 ranges
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'131.0.72.0/22',
			// Cloudflare IPv6 ranges
			'2400:cb00::/32',
			'2606:4700::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		];

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );
			if ( false !== $remote_ip ) {
				foreach ( $cloudflare_ips as $cloudflare_ip ) {
					if ( is_ip_in_range( $remote_ip, $cloudflare_ip ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

}
