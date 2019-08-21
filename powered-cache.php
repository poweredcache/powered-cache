<?php
/**
 * Plugin Name:   Powered Cache
 * Plugin URI:    https://poweredcache.com
 * Description:   Comprehensive caching and performance plugin for WordPress.
 * Author:        SKOP, Mustafa Uysal
 * Author URI:    https://poweredcache.com
 * Version:       1.2.6
 * Text Domain:   powered-cache
 * Domain Path:   /languages/
 * License:       GPLv2 (or later)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'POWERED_CACHE_REQUIRED_WP_VERSION', '4.5' );


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

			if ( ! defined( 'POWERED_CACHE_PLUGIN_FILE' ) ) {
				define( 'POWERED_CACHE_PLUGIN_FILE', __FILE__ );
			}

			if ( ! defined( 'POWERED_CACHE_PLUGIN_VERSION' ) ) {
				define( 'POWERED_CACHE_PLUGIN_VERSION', '1.2.6' );
			}

			if ( ! defined( 'POWERED_CACHE_PLUGIN_DIR' ) ) {
				define( 'POWERED_CACHE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			if ( ! defined( 'POWERED_CACHE_PREMIUM_DIR' ) ) {
				define( 'POWERED_CACHE_PREMIUM_DIR', POWERED_CACHE_PLUGIN_DIR . 'premium/' );
			}

			if ( ! defined( 'POWERED_CACHE_CACHE_DIR' ) ) {
				define( 'POWERED_CACHE_CACHE_DIR', WP_CONTENT_DIR . '/cache/' );
			}

			if ( ! defined( 'POWERED_CACHE_INC_DIR' ) ) {
				define( 'POWERED_CACHE_INC_DIR', POWERED_CACHE_PLUGIN_DIR . 'includes/' );
			}

			if ( ! defined( 'POWERED_CACHE_DROPIN_DIR' ) ) {
				define( 'POWERED_CACHE_DROPIN_DIR', POWERED_CACHE_INC_DIR . 'dropins/' );
			}

			if ( ! defined( 'POWERED_CACHE_ADMIN_DIR' ) ) {
				define( 'POWERED_CACHE_ADMIN_DIR', POWERED_CACHE_INC_DIR . 'admin/' );
			}

			if ( ! defined( 'POWERED_CACHE_PLUGIN_URL' ) ) {
				define( 'POWERED_CACHE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}
		}


		/**
		 * Include required files
		 *
		 * @since 1.0
		 */
		private function includes() {
			require_once POWERED_CACHE_INC_DIR . 'functions.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-config.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-cdn.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-extensions.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-cron.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-hooks.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-advanced-cache.php';
			require_once POWERED_CACHE_INC_DIR . 'class-powered-cache-object-cache.php';

			if ( file_exists( POWERED_CACHE_PREMIUM_DIR . 'loader.php' ) ) {
				require_once POWERED_CACHE_PREMIUM_DIR . 'loader.php';
			}

			if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				require_once POWERED_CACHE_ADMIN_DIR . 'notices.php';
				require_once POWERED_CACHE_ADMIN_DIR . 'class-powered-cache-extension-admin-base.php';
				require_once POWERED_CACHE_ADMIN_DIR . 'class-powered-cache-admin-actions.php';
				require_once POWERED_CACHE_ADMIN_DIR . 'class-powered-cache-admin-helper.php';
				require_once POWERED_CACHE_ADMIN_DIR . 'class-powered-cache-admin.php';
			}


		}

		/**
		 * Set up global variables
		 *
		 * @since 1.0
		 */
		private function setup_globals() {
			global $powered_cache_options;
			$powered_cache_options = powered_cache_get_settings();
		}


		/**
		 * Setup plugin functionality
		 * @since 1.0
		 */
		private function setup() {
			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			Powered_Cache_Config::factory();

			// load activated extensions
			Powered_Cache_Extensions::factory()->load_extentions();

			// setup cron
			Powered_Cache_Cron::factory();

			// Attached hooks
			Powered_Cache_Hooks::factory();

			// init admin
			if ( is_admin() && class_exists( 'Powered_Cache_Admin' ) ) {
				Powered_Cache_Admin::factory();
			}

			if ( true === powered_cache_get_option( 'enable_page_caching' ) ) {
				Powered_Cache_Advanced_Cache::factory();
			}

			if ( 'off' !== powered_cache_get_option( 'object_cache' ) ) {
				Powered_Cache_Object_Cache::factory();
			}

			// setup CDN
			if ( true === powered_cache_get_option( 'cdn_status' ) ) {
				if ( ! is_ssl() || ( is_ssl() && false === powered_cache_get_option( 'cdn_ssl_disable' ) ) ) {
					Powered_Cache_CDN::factory();
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
			$powered_lang_dir = dirname( plugin_basename( POWERED_CACHE_PLUGIN_FILE ) ) . '/languages/';
			$powered_lang_dir = apply_filters( 'powered_cache_lang_dir', $powered_lang_dir );
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
	global $powered_cache_fs;

	powered_cache_flush();
	if ( ! is_multisite() ) {
		Powered_Cache_Config::factory()->define_wp_cache( false );
		Powered_Cache_Config::factory()->configure_htaccess( false );


		// delete object cache file
		if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' ) ) {
			$powered_cache_fs->delete( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' );
		}

		// delete advanced cache file
		if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
			$powered_cache_fs->delete( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
		}
	}

	delete_option( 'powered_cache_preload_runtime_option' );


	// remove cron tasks
	wp_clear_scheduled_hook( 'powered_cache_preload_hook' );
	wp_clear_scheduled_hook( 'powered_cache_preload_child_process' );
	wp_clear_scheduled_hook( 'powered_cache_purge_cache' );
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
function powered_cache_requirements_notice() {
	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}
	?>

	<div id="message" class="error notice">
		<p><strong><?php esc_html_e( 'Your site does not support Powered Cache.', 'powered-cache' ); ?></strong></p>

		<p><?php printf( esc_html__( 'Your site is currently running WordPress version %1$s, while Powered Cache requires version %2$s or greater.', 'powered-cache' ), esc_html( get_bloginfo( 'version' ) ), POWERED_CACHE_REQUIRED_WP_VERSION ); ?></p>

		<p><?php esc_html_e( 'Please update your WordPress or deactivate Powered Cache.', 'powered-cache' ); ?></p>
	</div>

	<?php
}

if ( version_compare( get_bloginfo( 'version' ), POWERED_CACHE_REQUIRED_WP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'powered_cache_requirements_notice' );
	add_action( 'network_admin_notices', 'powered_cache_requirements_notice' );

	return;
}

// run
powered_cache();