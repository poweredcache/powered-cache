<?php
/**
 * Plugin Name:   Powered Cache
 * Plugin URI:    https://poweredcache.com
 * Description:   Comprehensive caching and performance plugin for WordPress.
 * Author:        Powered Cache Team
 * Author URI:    https://poweredcache.com
 * Version:       1.0
 * Text Domain:   powered-cache
 * Domain Path:   /languages/
 * License:       GPLv2 (or later)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PC_REQUIRED_WP_VERSION', '4.1' );


if ( ! class_exists( 'Powered_Cache' ) ) :

	final class Powered_Cache {

		/**
		 * Stores the single instance of this plugin.
		 * @since 1.0
		 */
		private static $instance;


		/**
		 *  A dummy constructor
		 *
		 *  @since 1.0
		 */
		protected function __construct() { }


		/**
		 * Singleton instance of the current class
		 *
		 * @since 1.0
		 * @return Powered_Cache
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Powered_Cache;
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->setup_globals();
				self::$instance->setup();
			}

			return self::$instance;
		}


		/**
		 * Setup plugin constants.
		 *
		 * @since 1.0
		 * @return void
		 */
		private function setup_constants() {

			if ( ! defined( 'PC_PLUGIN_FILE' ) ) {
				define( 'PC_PLUGIN_FILE', __FILE__ );
			}

			if ( ! defined( 'PC_PLUGIN_VERSION' ) ) {
				define( 'PC_PLUGIN_VERSION', '1.0' );
			}

			if ( ! defined( 'PC_PLUGIN_DIR' ) ) {
				define( 'PC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			if ( ! defined( 'PC_PREMIUM_DIR' ) ) {
				define( 'PC_PREMIUM_DIR', PC_PLUGIN_DIR . 'premium/' );
			}

			if ( ! defined( 'PC_CACHE_DIR' ) ) {
				define( 'PC_CACHE_DIR', WP_CONTENT_DIR . '/cache/' );
			}

			if ( ! defined( 'PC_INC_DIR' ) ) {
				define( 'PC_INC_DIR', PC_PLUGIN_DIR . 'includes/' );
			}

			if ( ! defined( 'PC_DROPIN_DIR' ) ) {
				define( 'PC_DROPIN_DIR', PC_INC_DIR . 'dropins/' );
			}

			if ( ! defined( 'PC_ADMIN_DIR' ) ) {
				define( 'PC_ADMIN_DIR', PC_INC_DIR . 'admin/' );
			}

			if ( ! defined( 'PC_PLUGIN_URL' ) ) {
				define( 'PC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}
		}


		/**
		 * Include required files
		 *
		 * @since 1.0
		 */
		private function includes() {
			require_once PC_INC_DIR . 'functions.php';
			require_once PC_INC_DIR . 'class-pc-config.php';
			require_once PC_INC_DIR . 'class-pc-cdn.php';
			require_once PC_INC_DIR . 'class-pc-extensions.php';
			require_once PC_INC_DIR . 'class-pc-cron.php';
			require_once PC_INC_DIR . 'class-pc-advanced-cache.php';
			require_once PC_INC_DIR . 'class-pc-object-cache.php';

			if ( file_exists( PC_PREMIUM_DIR . 'loader.php' ) ) {
				require_once PC_PREMIUM_DIR . 'loader.php';
			}

			if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				require_once PC_ADMIN_DIR . 'notices.php';
				require_once PC_ADMIN_DIR . 'class-pc-extension-admin-base.php';
				require_once PC_ADMIN_DIR . 'class-pc-admin-actions.php';
				require_once PC_ADMIN_DIR . 'class-pc-admin-helper.php';
				require_once PC_ADMIN_DIR . 'class-pc-admin.php';
			}


		}

		/**
		 * Set up global variables
		 *
		 * @since 1.0
		 */
		private function setup_globals() {
			global $powered_cache_options;
			$powered_cache_options = pc_get_settings();
		}


		/**
		 * Setup plugin functionality
		 * @since 1.0
		 */
		private function setup() {
			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			PC_Config::factory();

			// load activated extensions
			PC_Extensions::factory()->load_extentions();

			// setup cron
			PC_Cron::factory();

			// init admin
			if ( is_admin() && class_exists( 'PC_Admin' ) ) {
				PC_Admin::factory();
			}

			if ( true === pc_get_option( 'enable_page_caching' ) ) {
				PC_Advanced_Cache::factory();
			}

			if ( 'off' !== pc_get_option( 'object_cache' ) ) {
				PC_Object_Cache::factory();
			}

			// setup CDN
			if ( true === pc_get_option( 'cdn_status' ) ) {
				if ( ! is_ssl() || ( is_ssl() && false === pc_get_option( 'cdn_ssl_disable' ) ) ) {
					PC_CDN::factory();
				}
			}

		}


		/**
		 * Loads the language packs
		 *
		 * @access public
		 * @since 1.0
		 * @return void
		 */
		public function load_textdomain() {
			$powered_lang_dir = dirname( plugin_basename( PC_PLUGIN_FILE ) ) . '/languages/';
			$powered_lang_dir = apply_filters( 'pc_lang_dir', $powered_lang_dir );
			load_plugin_textdomain( 'powered-cache', false, $powered_lang_dir );
		}


		/**
		 * We don't want the object to be cloned.
		 *
		 * @since 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'powered-cache' ), '1.0' ); }


		/**
		 * Disable unserializing
		 *
		 * @since 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'powered-cache' ), '1.0' ); }


	}


endif;


register_deactivation_hook( __FILE__, 'powered_cache_deactivation' );

function powered_cache_deactivation() {
	global $wp_filesystem;

	pc_flush();
	if ( ! is_multisite() ) {
		PC_Config::factory()->define_wp_cache( false );
		PC_Config::factory()->configure_htaccess( false );


		// delete object cache file
		if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' ) ) {
			$wp_filesystem->delete( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' );
		}

		// delete advanced cache file
		if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
			$wp_filesystem->delete( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
		}
	}

	delete_option( 'pc_preload_runtime_option' );


	// remove cron tasks
	wp_clear_scheduled_hook( 'pc_preload_hook' );
	wp_clear_scheduled_hook( 'pc_preload_child_process' );
	wp_clear_scheduled_hook( 'pc_purge_cache' );
}

/**
 * The main function for that returns Powered_Cache
 *
 * @since 1.0
 * @return Powered_Cache
 */
function powered_cache() {
	return Powered_Cache::instance();
}


/**
 * Check requirement
 */
function pc_requirements_notice() {
	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}
	?>

	<div id="message" class="error notice">
		<p><strong><?php esc_html_e( 'Your site does not support Powered Cache.', 'powered-cache' ); ?></strong></p>

		<p><?php printf( esc_html__( 'Your site is currently running WordPress version %1$s, while Powered Cache requires version %2$s or greater.', 'powered-cache' ), esc_html( get_bloginfo( 'version' ) ), PC_REQUIRED_WP_VERSION ); ?></p>

		<p><?php esc_html_e( 'Please update your WordPress or deactivate Powered Cache.', 'powered-cache' ); ?></p>
	</div>

	<?php
}

if ( version_compare( get_bloginfo( 'version' ), PC_REQUIRED_WP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'pc_requirements_notice' );
	add_action( 'network_admin_notices', 'pc_requirements_notice' );

	return;
}

// run
powered_cache();