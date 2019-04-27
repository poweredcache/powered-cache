<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Powered_Cache_Admin {

	/**
	 * Change by using powered_cache_cap filter
	 *
	 * @var string
	 */
	public $capability = 'manage_options';

	/**
	 * @var string $slug used for settings page, menu etc
	 * @since 1.0
	 */
	public $slug = 'powered-cache';

	function __construct() { }

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	function setup() {
		$this->capability = apply_filters( 'powered_cache_cap', $this->capability );

		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		}

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
		add_filter( 'plugin_action_links_' . plugin_basename( POWERED_CACHE_PLUGIN_FILE ), array( $this, 'action_links' ) );
		add_action( 'load-toplevel_page_powered-cache', array( $this, 'update_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_post_deactivate_plugin', array( $this, 'deactivate_plugin' ) );
		add_action( 'admin_post_powered_cache_download_rewrite_settings', array( $this, 'download_rewrite_config' ) );

	}


	/**
	 * Fires when clicking buttons from admin ui. `update_options` doesn't mean setup db things all the time.
	 *
	 * @since 1.0
	 */
	public function update_options() {

		Powered_Cache_Admin_Helper::check_cap_and_nonce( $this->capability );

		if ( ! empty( $_REQUEST['action'] ) ) {

			$action = apply_filters( 'powered_cache_update_options', $_REQUEST['action'] );

			switch ( $action ) {
				case 'powered_cache_update_settings':
					Powered_Cache_Admin_Actions::update_settings();
					break;
				case 'reset_settings':
					Powered_Cache_Admin_Actions::reset_settings();
					break;
				case 'export_settings':
					Powered_Cache_Admin_Actions::export_settings();
					break;
			}

			do_action( 'powered_cache_update_options', $action );

		}

	}


	/**
	 * Register assets for settings page
	 *
	 * @since 1.0
	 * @param $hook
	 */
	public function load_scripts( $hook ) {
		global $powered_cache_settings_page;

		wp_enqueue_style( 'powered-cache-admin', plugins_url( '/assets/css/admin.css', POWERED_CACHE_PLUGIN_FILE ), array(), POWERED_CACHE_PLUGIN_VERSION );

		if ( $hook != $powered_cache_settings_page ) {
			return;
		}

		wp_enqueue_script( 'powered-cache-admin', plugins_url( '/assets/js/admin.js', POWERED_CACHE_PLUGIN_FILE ), array( 'jquery' ), POWERED_CACHE_PLUGIN_VERSION );
		wp_localize_script( 'powered-cache-admin', 'powered_cache_vars', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'powered-cache-ajax-nonce' ),
		) );

	}


	/**
	 * Set up settings page
	 *
	 * @since 1.0
	 */
	public function settings_page() {
		// load settings template
		require_once POWERED_CACHE_ADMIN_DIR . 'settings.php';
	}

	/**
	 * Adds admin menu item
	 *
	 * @since 1.0
	 */
	public function admin_menu() {
		global $powered_cache_settings_page;
		$powered_cache_settings_page = add_menu_page( __( 'Powered Cache Settings', 'powered-cache' ), __( 'Powered Cache', 'powered-cache' ), $this->capability, $this->slug, array( $this, 'settings_page' ),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iMzJweCIgaWQ9IkxheWVyXzEiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMyIDMyOyIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMzIgMzIiIHdpZHRoPSIzMnB4IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQgMzM2KSI+PHBhdGggZD0iTS0xMTcuMTc2LTMzNC4wNjNoLTkuMzUzTC0xMzguMTExLTMyMGg5LjMwNGwtMTEuNzQ1LDE0LjA2M2wyNS4xMDUtMTguMzE2aC05LjgwOUwtMTE3LjE3Ni0zMzQuMDYzeiIvPjwvZz48L3N2Zz4=' );

		/**
		 * Different name submenu item, url point same address with parent.
		 */
		add_submenu_page( $this->slug, __( 'Powered Cache Settings', 'powered-cache' ), __( 'Settings', 'powered-cache' ), $this->capability, $this->slug );
	}

	/**
	 * Adds admin bar menu
	 * @since 1.0
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( current_user_can( $this->capability ) ) {
			$wp_admin_bar->add_menu( array(
				'id'    => $this->slug,
				'title' => __('Powered Cache','powered-cache'),
				'href'  => admin_url( 'admin.php?page=powered-cache' ),
			));
		}
	}

	/**
	 * Adds settings link to plugin actions
	 *
	 * @since  1.0
	 * @param  array $actions
	 * @return array
	 */
	function action_links( $actions ) {

		$actions['powered_settings'] = sprintf( __( '<a href="%s">Settings</a>', 'powered-cache' ), esc_url( admin_url( 'admin.php?page=powered-cache' ) ) );
		if ( ! powered_cache_is_premium() ) {
			$actions['get_premium'] = sprintf( __( '<a href="%s" style="color: red;">Get Premium</a>', 'powered-cache' ), esc_url( 'https://poweredcache.com' ) );
		}

		return array_reverse( $actions );
	}

	/**
	 * Deactivate incompatible plugins
	 *
	 * @since 1.0
	 */
	function deactivate_plugin()
	{
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'deactivate_plugin' ) ) {
			wp_nonce_ays( '' );
		}

		deactivate_plugins( $_GET['plugin'] );

		wp_safe_redirect( wp_get_referer() );
		die();
	}


	/**
	 * Downloads proper configuration file
	 *
	 * @since 1.1
	 */
	public function download_rewrite_config() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_download_rewrite' ) ) {
			wp_nonce_ays( '' );
		}

		$server = $_GET['server'];
		Powered_Cache_Config::factory()->download_rewrite_rules( $server );

		wp_safe_redirect( wp_get_referer() );
		die();
	}


	/**
	 * Return an instance of the current class
	 *
	 * @since 1.0
	 * @return Powered_Cache_Admin
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}
