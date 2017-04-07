<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Cloudflare_Admin' ) ):

	class Powered_Cache_Cloudflare_Admin extends Powered_Cache_Extension_Admin_Base {

		public $capability = 'manage_options';
		public $fields;
		public $api;

		function __construct( ) {
			$this->fields = array(
				'email' => array(
					'default' => false,
					'sanitizer' => 'sanitize_email'
				),
				'api_key'=> array(
					'default' => false,
					'sanitizer' => 'sanitize_text_field'
				),
				'zone' => array(
					'default'   => false,
					'sanitizer' => 'sanitize_text_field',
				),
			);

			parent::__construct( array(
				'extension_id'   => 'cloudflare',
				'extension_name' => __( 'Cloudflare', 'powered-cache' ),
				'admin_bar_menu' => true,
			) );

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
		function setup() {
			parent::setup();
			add_action( 'load-powered-cache_page_powered-cache-extension-' . $this->extension_id, array( $this, 'flush_cache' ) );
		}

		/**
		 * Adds menu item
		 *
		 * @since 1.0
		 */
		public function admin_menu() {
			parent::admin_menu();
		}

		public function admin_bar_menu( $wp_admin_bar ) {
			parent::admin_bar_menu( $wp_admin_bar );
			if ( is_a( $this->api, 'Powered_Cache_Cloudflare_Api' ) &&  $this->get_option( 'zone' )  ) {
				$wp_admin_bar->add_menu( array(
					'id'     => 'cf-purge-cache',
					'title'  => __( 'Purge Cache', 'powered-cache' ),
					'href'   => $this->flush_url(),
					'parent' => $this->extension_id,
				) );
			}
		}


		public function settings_page() {
			$settings_file[] = realpath( dirname( __FILE__ ) ) . '/settings.php';
			parent::settings_template( $settings_file );
		}


		public function flush_cache(){
			Powered_Cache_Admin_Helper::check_cap_and_nonce( $this->capability );

			if ( isset( $_REQUEST['action'] ) && 'purge_cf_cache' === $_REQUEST['action'] ) {

				if ( $this->api->purge( $this->get_option( 'zone' ) ) ) {
					Powered_Cache_Admin_Helper::set_flash_message( __( 'Cloudflare cache flushed!', 'powered-cache' ) );
				} else {
					Powered_Cache_Admin_Helper::set_flash_message( __( 'Problem with Cloudflare cache clean!', 'powered-cache' ), 'error' );
				}

				Powered_Cache_Admin_Actions::exit_with_redirect();
			}
		}


		public function get_zones() {
			if ( is_a( $this->api, 'Powered_Cache_Cloudflare_Api' ) ) {
				$req = $this->api->get_zones();
				if ( is_object( $req ) && isset( $req->result ) ) {
					// runtime option
					$this->options['zone_list']= $req->result;

					return $req->result;
				}
			}

			return array();
		}


		public function flush_url() {
			$url = add_query_arg( array(
				'page'                         => 'powered-cache-extension-cloudflare',
				'action'                       => 'purge_cf_cache',
				'wp_http_referer'              => urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
				'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
			), admin_url( 'admin.php' ) );

			return $url;
		}

		/**
		 * Generates cache flush button
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public  function flush_cache_button() {
			$url = $this->flush_url();
			$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Clear Cache', 'powered-cache' ) . '</a>';

			return $html;
		}


		/**
		 * Return an instance of the current class
		 *
		 * @since 1.0
		 * @return Powered_Cache_Cloudflare_Admin
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