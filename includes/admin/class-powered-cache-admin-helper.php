<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Powered_Cache_Admin_Helper {
	/**
	 * Register admin sections
	 *
	 * @since 1.0
	 *
	 * @return mixed|void
	 */
	public static function admin_sections(){
		$sections = array(
			'basic-options'    => __( 'Basic Options', 'powered-cache' ),
			'advanced-options' => __( 'Advanced Options', 'powered-cache' ),
			'cdn'              => __( 'CDN', 'powered-cache' ),
			'extensions'       => __( 'Extensions', 'powered-cache' ),
			'misc'             => __( 'Misc', 'powered-cache' ),
			'premium'          => __( 'Go Premium', 'powered-cache' ),
			'support'          => __( 'Support', 'powered-cache' ),
		);

		return apply_filters( 'powered_cache_admin_settings_sections', $sections );
	}


	/**
	 * Set flash message for admin page
	 *
	 * @since 1.0
	 *
	 * @param $msg   string
	 * @param $class string
	 */
	public static function set_flash_message( $msg, $class = 'updated' ) {
		if ( ! empty( $msg ) ) {
			$message_data = array( 'message' => $msg, 'class' => $class );
			set_site_transient( 'powered_cache_flash_msg', $message_data, 30 );
		}
	}

	/**
	 * Echo flash message and destroy
	 *
	 * @since 1.0
	 */
	public static function get_flash_message() {
		$flash_message = get_site_transient( 'powered_cache_flash_msg' );

		if ( $flash_message && is_array( $flash_message ) ) {
			$html = '<div id="setting-error-settings_updated" class="' . esc_attr( $flash_message['class'] ) . ' notice is-dismissible">
						<p><strong>' . esc_attr( $flash_message['message'] ) . '</strong></p>
						<button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __( 'Dismiss this notice', 'powered-cache' ) . '</span></button>
					</div>';
			echo $html;
			//destroy transient
			delete_site_transient( 'powered_cache_flash_msg' );
		}
	}

	/**
	 * Object cache methods keys will use as option
	 *
	 * @since 1.0
	 *
	 * @return array $object_caches
	 */
	public static function object_cache_dropins() {

		$object_caches = array(
			'memcache'  => POWERED_CACHE_DROPIN_DIR . 'memcache-object-cache.php',
			'memcached' => POWERED_CACHE_DROPIN_DIR . 'memcached-object-cache.php',
			'redis'     => POWERED_CACHE_DROPIN_DIR . 'redis-object-cache.php',
		);

		return apply_filters( 'powered_cache_object_cache_dropins', $object_caches );
	}


	/**
	 * Get available object cache backends
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function available_object_caches() {
		$object_cache_methods = self::object_cache_dropins();

		if ( ! function_exists( 'apc_fetch' ) ) {
			unset( $object_cache_methods['apc'] );
		}

		if ( ! class_exists( 'Memcache' ) ) {
			unset( $object_cache_methods['memcache'] );
		}

		if ( ! class_exists( 'Memcached' ) ) {
			unset( $object_cache_methods['memcached'] );
		}

		if ( ! class_exists( 'Redis' ) ) {
			unset( $object_cache_methods['redis'] );
		}

		return array_keys($object_cache_methods);
	}




	/**
	 * Generates button for given plugin
	 *
	 * @since 1.0
	 *
	 * @param string $plugin_id unique plugin identity
	 * @return string html output
	 */
	public static function plugin_button( $plugin_id ) {

		if ( true === Powered_Cache_Extensions::factory()->is_active( $plugin_id ) ) {
			$action = 'deactivate';
			$button = __( 'Dectivate', 'powered-cache' );
		} else {
			$action = 'activate';
			$button = __( 'Activate', 'powered-cache' );
		}

		$url = add_query_arg( array(
			'page'                         => esc_attr( 'powered-cache' ),
			'section'                      => 'extensions',
			'extension'                    => $plugin_id,
			'status'                       => $action,
			'wp_http_referer'              => urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			'action'                       => 'powered_cache_update_settings',
			'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
		), admin_url( 'admin.php' ) );

		$html = '<a href="' . esc_url( $url ) . '" class="button button-primary" >' . $button . '</a>';

		return $html;
	}

	public static function upgrade_button(){
		$url = 'https://poweredcache.com/';
		$html = '<a href="' . esc_url( $url ) . '" class="upgrade-now" >' . __('Upgrade Now','powered-cache') . '</a>';

		return $html;
	}

	/**
	 * Generates cache flush button
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function flush_cache_button() {
		$url = add_query_arg( array(
			'page'                         => esc_attr( 'powered-cache' ),
			'section'                      => 'misc',
			'action'                       => 'purge_cache',
			'wp_http_referer'              => urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
		), admin_url( 'admin.php' ) );

		$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Clear Cache', 'powered-cache' ) . '</a>';

		return $html;
	}

	/**
	 * Generates settings export button
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function export_settings_button(){
		$url = add_query_arg( array(
			'page'                         => esc_attr( 'powered-cache' ),
			'section'                      => 'misc',
			'action'                       => 'export_settings',
			'wp_http_referer'              => urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
		), admin_url( 'admin.php' ) );

		$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Download Settings', 'powered-cache' ) . '</a>';

		return $html;
	}


	/**
	 * Generates settings reset button
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function reset_settings_button(){
		$url = add_query_arg( array(
			'page'                         => esc_attr( 'powered-cache' ),
			'section'                      => 'misc',
			'action'                       => 'reset_settings',
			'wp_http_referer'              => urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
		), admin_url( 'admin.php' ) );

		$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Reset Settings', 'powered-cache' ) . '</a>';

		return $html;
	}


	/**
	 * Check capability and nonce during admin action
	 *
	 * @since 1.0
	 * @param $cap string capability
	 */
	public static function check_cap_and_nonce( $cap ) {
		if ( isset( $_REQUEST['action'] )
		     && ( ! current_user_can( $cap ) || empty( $_REQUEST['powered_cache_settings_nonce'] )
		          || ! wp_verify_nonce( $_REQUEST['powered_cache_settings_nonce'], 'powered_cache_update_settings' ) )
		) {
			wp_die( esc_html__( 'Cheatin, uh?', 'powered-cache' ) );
		}
	}

	/**
	 * Get available zones
	 *
	 * @since 1.0
	 *
	 * @return mixed|void
	 */
	public static function cdn_zones() {
		$zones = array(
			'all'   => __( 'All files', 'powered-cache' ),
			'image' => __( 'Images', 'powered-cache' ),
			'js'    => __( 'JavaScript', 'powered-cache' ),
			'css'   => __( 'CSS', 'powered-cache' ),
		);

		return apply_filters( 'powered_cache_admin_cdn_zones', $zones );
	}


	/**
	 * Template loader of settings page
	 * actions fires before and after it can be handy when custom message needed
	 *
	 * @since 1.0
	 *
	 * @param string $section tab of settings
	 */
	public static function load_settings_template( $section ) {
		$page = POWERED_CACHE_ADMIN_DIR . 'settings/' . $section . '.php';
		if ( file_exists( $page ) ) {
			do_action( 'powered_cache_before_load_settings_template_' . $section );
			include $page;
			do_action( 'powered_cache_after_load_settings_template_' . $section );
		}
	}

}


