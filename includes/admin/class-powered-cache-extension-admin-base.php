<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Powered_Cache_Extension_Admin_Base {

	public $extension_id;
	public $extension_name;
	public $is_premium;
	public $options;
	public $capability = 'manage_options';
	protected $fields;


	protected function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->options = powered_cache_get_extension_settings( $this->extension_id );
		$this->is_premium = powered_cache_is_premium();
	}

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	protected function setup() {
		$this->capability = apply_filters( 'powered_cache_cap', $this->capability, $this->extension_id );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 999 );
		if ( property_exists( $this, 'admin_bar_menu' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
		}
		add_action( 'load-powered-cache_page_powered-cache-extension-' . $this->extension_id, array( $this, 'update_options' ) );
	}

	/**
	 * Adds menu item
	 *
	 * @since 1.0
	 */
	public function admin_menu() {
		global $powered_cache_plugin_pages;
		$powered_cache_plugin_pages[ $this->extension_id ] = add_submenu_page( 'powered-cache', $this->extension_name, $this->extension_name, $this->capability,'powered-cache-extension-'. $this->extension_id, array( $this, 'settings_page' ) );
	}

	public function admin_bar_menu( $wp_admin_bar ) {
		if ( current_user_can( $this->capability ) ) {
			$wp_admin_bar->add_menu( array(
				'id'     => $this->extension_id,
				'title'  => $this->extension_name,
				'href'   => admin_url( 'admin.php?page=powered-cache-extension-' . $this->extension_id ),
				'parent' => 'powered-cache',
			) );
		}
	}

	public function settings_template( $settings_files = array() ) {
		$this->settings_files = $settings_files;
		require_once POWERED_CACHE_ADMIN_DIR . 'extension-settings.php';
	}

	public function get_option( $key ) {
		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		} elseif ( isset( $this->fields[ $key ] ) && false !== $this->fields[ $key ]['default'] ) {
			return $this->fields[ $key ]['default'];
		}

		return '';
	}


	public function is_premium() {
		return $this->is_premium;
	}


	public function settings_page() {

	}


	public function update_options() {

		Powered_Cache_Admin_Helper::check_cap_and_nonce( $this->capability );

		if ( isset( $_REQUEST['extension'] ) && ( $_REQUEST['extension'] === $this->extension_id ) ) {
			$_post = $_POST[ $this->extension_id ];


			foreach ( $this->fields as $key => $field ) {
				$options[ $key ] = $field['default'];

				if ( isset( $_post[ $key ] ) ) {
					$options[ $key ] = call_user_func( $field['sanitizer'], $_post[ $key ] );
				} elseif ( 'boolval' === $field['sanitizer'] && ! isset( $_post[ $key ] ) ) {
					//checkbox deleted option
					$options[ $key ] = false;
				}

			}


			if ( isset( $options ) && ( powered_cache_update_extension_option( $this->extension_id, $options ) ) ) {
				// update runtime values
				$this->options = $options;
			}

			$msg = __( 'Options updated', 'powered-cache' );
			Powered_Cache_Admin_Helper::set_flash_message( $msg );
			Powered_Cache_Admin_Actions::exit_with_redirect();
		}
	}


}