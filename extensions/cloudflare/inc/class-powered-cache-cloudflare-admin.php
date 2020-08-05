<?php
/**
 * Cloudflare extension admin functionalities
 *
 * @package PoweredCache
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Cloudflare_Admin' ) ) :
	/**
	 * class Powered_Cache_Cloudflare_Admin
	 */
	class Powered_Cache_Cloudflare_Admin extends Powered_Cache_Extension_Admin_Base {
		/**
		 * User capability
		 *
		 * @var string $capability
		 */
		public $capability = 'manage_options';

		/**
		 * Form fields
		 *
		 * @var array $fields
		 */
		public $fields;

		/**
		 * API instance
		 *
		 * @var Powered_Cache_Cloudflare_Api
		 */
		public $api;

		/**
		 * Powered_Cache_Cloudflare_Admin constructor.
		 */
		public function __construct() {
			$this->fields = array(
				'email'   => array(
					'default'   => false,
					'sanitizer' => 'sanitize_email',
				),
				'api_key' => array(
					'default'   => false,
					'sanitizer' => 'sanitize_text_field',
				),
				'zone'    => array(
					'default'   => false,
					'sanitizer' => 'sanitize_text_field',
				),
			);

			parent::__construct(
				array(
					'extension_id'   => 'cloudflare',
					'extension_name' => __( 'Cloudflare', 'powered-cache' ),
					'admin_bar_menu' => true,
				)
			);

			$this->setup();

			if ( $this->get_option( 'email' ) && $this->get_option( 'api_key' ) ) {
				$this->api = new Powered_Cache_Cloudflare_Api( $this->get_option( 'email' ), $this->get_option( 'api_key' ) );
			}

		}

		/**
		 * Get things started
		 *
		 * @since 1.0
		 */
		public function setup() {
			parent::setup();
			add_action( 'load-powered-cache_page_powered-cache-extension-' . $this->extension_id, array( $this, 'flush_cache' ) );
		}

		/**
		 * Add admin bar item
		 *
		 * @param object $wp_admin_bar \WP_Admin_Bar
		 */
		public function admin_bar_menu( $wp_admin_bar ) {
			parent::admin_bar_menu( $wp_admin_bar );
			if ( is_a( $this->api, 'Powered_Cache_Cloudflare_Api' ) && $this->get_option( 'zone' ) ) {
				$wp_admin_bar->add_menu(
					array(
						'id'     => 'cf-purge-cache',
						'title'  => __( 'Purge Cache', 'powered-cache' ),
						'href'   => $this->flush_url(),
						'parent' => $this->extension_id,
					)
				);
			}
		}

		/**
		 * render settings page
		 */
		public function settings_page() {
			$settings_file[] = realpath( dirname( __FILE__ ) ) . '/settings.php';
			parent::settings_template( $settings_file );
		}

		/**
		 * Flush CF cache
		 */
		public function flush_cache() {
			Powered_Cache_Admin_Helper::check_cap_and_nonce( $this->capability );

			if ( isset( $_REQUEST['action'] ) && 'purge_cf_cache' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( $this->api->purge( $this->get_option( 'zone' ) ) ) {
					Powered_Cache_Admin_Helper::set_flash_message( __( 'Cloudflare cache flushed!', 'powered-cache' ) );
				} else {
					Powered_Cache_Admin_Helper::set_flash_message( __( 'Problem with Cloudflare cache clean!', 'powered-cache' ), 'error' );
				}

				Powered_Cache_Admin_Actions::exit_with_redirect();
			}
		}

		/**
		 * Get CF zones
		 *
		 * @return array
		 */
		public function get_zones() {
			if ( is_a( $this->api, 'Powered_Cache_Cloudflare_Api' ) ) {
				$req = $this->api->get_zones();
				if ( is_object( $req ) && isset( $req->result ) ) {
					// runtime option
					$this->options['zone_list'] = $req->result;

					return $req->result;
				}
			}

			return array();
		}

		/**
		 * Prepare flush url
		 *
		 * @return string
		 */
		public function flush_url() {
			$url = add_query_arg(
				array(
					'page'                         => 'powered-cache-extension-cloudflare',
					'action'                       => 'purge_cf_cache',
					'wp_http_referer'              => rawurlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
					'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
				),
				admin_url( 'admin.php' )
			);

			return $url;
		}

		/**
		 * Generates cache flush button
		 *
		 * @return string
		 * @since 1.0
		 */
		public function flush_cache_button() {
			$url  = $this->flush_url();
			$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Clear Cache', 'powered-cache' ) . '</a>';

			return $html;
		}


		/**
		 * Return an instance of the current class
		 *
		 * @return Powered_Cache_Cloudflare_Admin
		 * @since 1.0
		 */
		public static function factory() {
			static $instance = false;

			if ( ! $instance ) {
				$instance = new self();
			}

			return $instance;
		}

	}


endif;
