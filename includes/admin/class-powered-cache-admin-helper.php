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

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			unset( $sections['premium'] );
			unset( $sections['support'] );
		}

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
	 * @since 1.2 apcu added
	 *
	 * @return array $object_caches
	 */
	public static function object_cache_dropins() {

		$object_caches = array(
			'memcache'  => POWERED_CACHE_DROPIN_DIR . 'memcache-object-cache.php',
			'memcached' => POWERED_CACHE_DROPIN_DIR . 'memcached-object-cache.php',
			'redis'     => POWERED_CACHE_DROPIN_DIR . 'redis-object-cache.php',
			'apcu'      => POWERED_CACHE_DROPIN_DIR . 'apcu-object-cache.php',
		);

		return apply_filters( 'powered_cache_object_cache_dropins', $object_caches );
	}


	/**
	 * Get available object cache backends
	 *
	 * @since 1.0
	 * @since 1.2 unset apcu
	 * @return array
	 */
	public static function available_object_caches() {
		$object_cache_methods = self::object_cache_dropins();

		if ( ! class_exists( 'Memcache' ) ) {
			unset( $object_cache_methods['memcache'] );
		}

		if ( ! class_exists( 'Memcached' ) ) {
			unset( $object_cache_methods['memcached'] );
		}

		if ( ! class_exists( 'Redis' ) ) {
			unset( $object_cache_methods['redis'] );
		}

		if ( ! function_exists( 'apcu_add' ) ) {
			unset( $object_cache_methods['apcu'] );
		}

		return array_keys( $object_cache_methods );
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
	 * @since 1.1 $url changed to admin-post.php
	 * @return string
	 */
	public static function flush_cache_button() {
		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_all_cache' ), 'powered_cache_purge_all_cache' );
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
	 * Link for the diagnostic
	 *
	 * @since 1.2
	 * @return string
	 */
	public static function diagnostic_button() {
		$url = add_query_arg( array(
			'page'                         => esc_attr( 'powered-cache' ),
			'section'                      => 'misc',
			'action'                       => 'run-diagnostic',
			'powered_cache_settings_nonce' => wp_create_nonce( 'powered_cache_update_settings' ),
		), admin_url( 'admin.php' ) );

		$html = '<a href="' . esc_url( $url ) . '" class="button" >' . esc_html__( 'Run Diagnostic', 'powered-cache' ) . '</a>';

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

	/**
	 * convert minutes to possible time format
	 *
	 * @param int $timeout_in_minutes
	 *
	 * @since 1.1
	 * @return array
	 */
	public static function get_timeout_interval( $timeout_in_minutes ) {
		$cache_timeout     = $timeout_in_minutes;
		$selected_interval = 'MINUTE';

		if ( $cache_timeout > 0 ) {
			if ( 0 === (int) ( $cache_timeout % 1440 ) ) {
				$cache_timeout     = $cache_timeout / 1440;
				$selected_interval = 'DAY';
			} elseif ( 0 === (int) ( $cache_timeout % 60 ) ) {
				$cache_timeout     = $cache_timeout / 60;
				$selected_interval = 'HOUR';
			}
		}

		return array(
			$cache_timeout,
			$selected_interval,
		);
	}


	/**
	 * run diagnostic checks
	 *
	 * @since 1.2
	 * @return array
	 */
	public static function diagnostic_info() {
		global $is_apache;

		// hold the checks! HODORRR!!!
		$checks = array();

		// check config file
		$config_file        = Powered_Cache_Config::factory()->find_wp_config_file();
		$config_file_status = is_writeable( $config_file );

		if ( $config_file_status ) {
			$config_file_desc = __( 'wp-config.php is writable.', 'powered-cache' );
		} else {
			$config_file_desc = sprintf( __( 'wp-config.php is not writable. Please make sure the file writable or you can manually define %s constant.', 'powered-cache' ), '<code>WP_CACHE</code>' );
		}

		$checks['config'] = array(
			'status'      => $config_file_status,
			'description' => $config_file_desc,
		);


		// check cache directory
		$cache_dir        = powered_cache_get_cache_dir();
		$cache_dir_status = false;
		if ( ! file_exists( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not exist!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		} elseif ( ! is_writeable( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not writeable!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		} else {
			$cache_dir_status = true;
			$cache_dir_desc   = sprintf( __( 'Cache directory %s exist and writable!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		}

		$checks['cache-dir'] = array(
			'status'      => $cache_dir_status,
			'description' => $cache_dir_desc,
		);


		// check .htaccess file
		if ( $is_apache && powered_cache_get_option( 'configure_htaccess' ) ) {
			$htaccess_file        = get_home_path() . '.htaccess';
			$htaccess_file_status = false;
			if ( ! file_exists( $htaccess_file ) ) {
				$htaccess_file_desc = sprintf( __( '.htaccess file %s is not exist!', 'powered-cache' ), '<code>' . $htaccess_file . '</code>' );
			} elseif ( ! is_writeable( $htaccess_file ) ) {
				$htaccess_file_desc = sprintf( __( '.htaccess file %s is not writeable!', 'powered-cache' ), '<code>' . $htaccess_file . '</code>' );
			} else {
				$htaccess_file_status = true;
				$htaccess_file_desc   = sprintf( __( '.htaccess file %s exist and writable!', 'powered-cache' ), '<code>' . $htaccess_file . '</code>' );
			}

			$checks['htaccess'] = array(
				'status'      => $htaccess_file_status,
				'description' => $htaccess_file_desc,
			);
		}


		// check page cache
		if ( powered_cache_get_option( 'enable_page_caching' ) ) {
			$advanced_cache_file        = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
			$advanced_cache_file_status = false;
			if ( ! file_exists( $advanced_cache_file ) ) {
				$advanced_cache_file_desc = sprintf( __( 'Required file for the page caching %s is not exist!', 'powered-cache' ), '<code>' . $advanced_cache_file . '</code>' );
			} elseif ( ! is_writeable( $advanced_cache_file ) ) {
				$advanced_cache_file_desc = sprintf( __( 'Required file for the page caching %s is not writeable!', 'powered-cache' ), '<code>' . $advanced_cache_file . '</code>' );
			} else {
				$advanced_cache_file_status = true;
				$advanced_cache_file_desc   = sprintf( __( 'Required file for the page caching %s exist and writable!', 'powered-cache' ), '<code>' . $advanced_cache_file . '</code>' );
			}

			$checks['advanced-cache'] = array(
				'status'      => $advanced_cache_file_status,
				'description' => $advanced_cache_file_desc,
			);
		}


		// check object cache
		if ( 'off' !== powered_cache_get_option( 'object_cache' ) ) {
			$object_cache_file        = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';
			$object_cache_file_status = false;
			if ( ! file_exists( $object_cache_file ) ) {
				$object_cache_file_desc = sprintf( __( 'Required file for the object caching %s is not exist!', 'powered-cache' ), '<code>' . $object_cache_file . '</code>' );
			} elseif ( ! is_writeable( $object_cache_file ) ) {
				$object_cache_file_desc = sprintf( __( 'Required file for the object caching %s is not writeable!', 'powered-cache' ), '<code>' . $object_cache_file . '</code>' );
			} else {
				$object_cache_file_status = true;
				$object_cache_file_desc   = sprintf( __( 'Required file for the object caching %s exist and writable!', 'powered-cache' ), '<code>' . $object_cache_file . '</code>' );
			}

			$checks['object-cache'] = array(
				'status'      => $object_cache_file_status,
				'description' => $object_cache_file_desc,
			);
		}


		return $checks;
	}


}


