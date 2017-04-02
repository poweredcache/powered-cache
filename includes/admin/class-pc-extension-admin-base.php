<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Extension_Admin_Base {

	public $plugin_id;
	public $plugin_name;
	public $is_premium;
	public $options;
	public $capability = 'manage_options';
	protected $fields;


	protected function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->options = pc_get_plugin_settings( $this->plugin_id );
		$this->is_premium = is_powered_cache_premium();
	}

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	protected function setup() {
		$this->capability = apply_filters( 'pc_cap', $this->capability, $this->plugin_id );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 999 );
		if ( property_exists( $this, 'admin_bar_menu' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
		}
		add_action( 'load-powered-cache_page_pc_' . $this->plugin_id, array( $this, 'update_options' ) );
	}

	/**
	 * Adds menu item
	 *
	 * @since 1.0
	 */
	public function admin_menu() {
		global $powered_cache_plugin_pages;
		$powered_cache_plugin_pages[ $this->plugin_id ] = add_submenu_page( 'powered-cache', $this->plugin_name, $this->plugin_name, $this->capability,'pc_'. $this->plugin_id, array( $this, 'settings_page' ) );
	}

	public function admin_bar_menu( $wp_admin_bar ) {
		if ( current_user_can( $this->capability ) ) {
			$wp_admin_bar->add_menu( array(
				'id'     => $this->plugin_id,
				'title'  => $this->plugin_name,
				'href'   => admin_url( 'admin.php?page=' . $this->plugin_id ),
				'parent' => 'powered-cache',
			) );
		}
	}

	public function settings_template( $settings_files = array() ) {
		$this->settings_files = $settings_files;
		require_once PC_ADMIN_DIR . 'extension-settings.php';
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

		if ( ! defined( 'PC_SAVING_OPTIONS' ) ) {
			define( 'PC_SAVING_OPTIONS', true );
		}

		PC_Admin_Helper::check_cap_and_nonce( $this->capability );

		if ( isset( $_REQUEST['extension'] ) && ( $_REQUEST['extension'] === $this->plugin_id ) ) {
			$_post = $_POST[ $this->plugin_id ];


			foreach ( $this->fields as $key => $field ) {
				$options[ $key ] = $field['default'];

				if ( isset( $_post[ $key ] ) ) {
					$options[ $key ] = call_user_func( $field['sanitizer'], $_post[ $key ] );
				} elseif ( 'boolval' === $field['sanitizer'] && ! isset( $_post[ $key ] ) ) {
					//checkbox deleted option
					$options[ $key ] = false;
				}

			}


			if ( isset( $options ) && ( pc_update_plugin_option( $this->plugin_id, $options ) ) ) {
				// update runtime values
				$this->options = $options;
			}

			$msg = __( 'Options updated', 'powered-cache' );
			PC_Admin_Helper::set_flash_message( $msg );
			PC_Admin_Actions::exit_with_redirect();
		}
	}


}