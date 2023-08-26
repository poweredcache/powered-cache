<?php
/**
 * Installation functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\DB_VERSION_OPTION_NAME;
use const PoweredCache\Constants\SETTING_OPTION;

/**
 * Class Install
 */
class Install {
	/**
	 * Placeholder constructor.
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class
	 *
	 * @since 2.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup Hooks
	 */
	public function setup() {
		add_action( 'init', [ $this, 'check_version' ], 5 );
	}

	/**
	 * Check DB version and run the updater is required.
	 */
	public function check_version() {
		if ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) {
			return;
		}

		if ( version_compare( get_option( DB_VERSION_OPTION_NAME ), POWERED_CACHE_DB_VERSION, '<' ) ) {
			$this->install();
			/**
			 * Fires after plugin update.
			 *
			 * @hook  powered_cache_updated
			 *
			 * @since 2.0
			 */
			do_action( 'powered_cache_updated' );
		}
	}

	/**
	 * Perform Installation
	 */
	public function install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		$lock_key = 'powered_cache_installing';
		// Check if we are not already running
		if ( $this->has_lock( $lock_key ) ) {
			return;
		}

		// lets set the transient now.
		$this->set_lock( $lock_key );

		if ( POWERED_CACHE_IS_NETWORK ) {
			$this->maybe_upgrade_network_wide();
		} else {
			$this->maybe_upgrade();
		}

		$this->remove_lock( $lock_key );
	}

	/**
	 * Upgrade routine for network wide activation
	 */
	public function maybe_upgrade_network_wide() {
		if ( version_compare( get_site_option( DB_VERSION_OPTION_NAME ), POWERED_CACHE_DB_VERSION, '<' ) ) {
			$this->upgrade_30( true );
			$this->upgrade_32( true );
			\PoweredCache\Utils\log( sprintf( '[Networkwide] Upgrade DB version: %s', POWERED_CACHE_DB_VERSION ) );
			update_site_option( DB_VERSION_OPTION_NAME, POWERED_CACHE_DB_VERSION );
		}
	}

	/**
	 * Upgrade routine
	 */
	public function maybe_upgrade() {
		if ( version_compare( get_option( DB_VERSION_OPTION_NAME ), POWERED_CACHE_DB_VERSION, '<' ) ) {
			$this->maybe_migrate_from_1x();
			$this->upgrade_30();
			$this->upgrade_32();

			\PoweredCache\Utils\log( sprintf( 'Upgrade DB version: %s', POWERED_CACHE_DB_VERSION ) );
			update_option( DB_VERSION_OPTION_NAME, POWERED_CACHE_DB_VERSION );
		}
	}

	/**
	 * Changing "accepted query string" as "ignored query string" with version 3.x
	 *
	 * @param bool $network_wide whether plugin activated network-wide or not
	 *
	 * @return void
	 * @since 3.0
	 */
	public function upgrade_30( $network_wide = false ) {
		$current_version = $network_wide ? get_site_option( DB_VERSION_OPTION_NAME ) : get_option( DB_VERSION_OPTION_NAME );
		if ( ! version_compare( $current_version, '3.0', '<' ) ) {
			return;
		}

		$settings = \PoweredCache\Utils\get_settings( $network_wide );

		if ( empty( $settings['accepted_query_strings'] ) ) {
			return;
		}

		$settings['ignored_query_strings'] = $settings['accepted_query_strings'];
		unset( $settings['accepted_query_strings'] );

		if ( $network_wide ) {
			update_site_option( SETTING_OPTION, $settings );
		} else {
			update_option( SETTING_OPTION, $settings );
		}

		Config::factory()->save_configuration( $settings, $network_wide );
		\PoweredCache\Utils\log( 'Upgraded to version 3.0' );
	}

	/**
	 * Changing js execution method
	 *
	 * @param bool $network_wide whether plugin activated network-wide or not
	 *
	 * @return void
	 * @since 3.2
	 */
	public function upgrade_32( $network_wide = false ) {
		$current_version = $network_wide ? get_site_option( DB_VERSION_OPTION_NAME ) : get_option( DB_VERSION_OPTION_NAME );
		if ( ! version_compare( $current_version, '3.2', '<' ) ) {
			return;
		}

		$settings = \PoweredCache\Utils\get_settings( $network_wide );

		if ( ! empty( $settings['js_execution_method'] ) ) {
			if ( in_array( $settings['js_execution_method'], [ 'async', 'defer' ], true ) ) {
				$settings['js_defer'] = true;
			}

			if ( 'delayed' === $settings['js_execution_method'] ) {
				$settings['js_delay']   = true;
				$settings['combine_js'] = false;
			}

			unset( $settings['js_execution_method'] );
			unset( $settings['js_execution_optimized_only'] );
		}

		if ( $network_wide ) {
			update_site_option( SETTING_OPTION, $settings );
		} else {
			update_option( SETTING_OPTION, $settings );
		}

		Config::factory()->save_configuration( $settings, $network_wide );
		\PoweredCache\Utils\log( 'Upgraded to version 3.2' );
	}


	/**
	 * Check if a lock exists of the upgrade routine
	 *
	 * @param string $lock_name transient name
	 *
	 * @return bool
	 */
	private function has_lock( $lock_name ) {
		if ( POWERED_CACHE_IS_NETWORK ) {
			if ( 'yes' === get_site_transient( $lock_name ) ) {
				return true;
			}

			return false;
		}

		if ( 'yes' === get_transient( $lock_name ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Set the lock
	 *
	 * @param string $lock_name transient name for the lock
	 *
	 * @return bool
	 */
	private function set_lock( $lock_name ) {
		if ( POWERED_CACHE_IS_NETWORK ) {
			return set_site_transient( $lock_name, 'yes', MINUTE_IN_SECONDS );
		}

		return set_transient( $lock_name, 'yes', MINUTE_IN_SECONDS );
	}

	/**
	 * Remove lock
	 *
	 * @param string $lock_name transient name for the lock
	 *
	 * @return bool
	 */
	private function remove_lock( $lock_name ) {
		if ( POWERED_CACHE_IS_NETWORK ) {
			return delete_site_transient( $lock_name );
		}

		return delete_transient( $lock_name );
	}


	/**
	 * Migrate existing options and extension settings from 1.x
	 */
	public function maybe_migrate_from_1x() {
		if ( POWERED_CACHE_IS_NETWORK ) { // 1x wasn't support network wide activation
			return;
		}

		$db_version      = get_option( DB_VERSION_OPTION_NAME );
		$old_options     = get_option( SETTING_OPTION );
		$default_options = \PoweredCache\Utils\get_settings();

		if ( empty( $db_version ) && ! empty( $old_options ) ) {
			\PoweredCache\Utils\log( 'Upgrading from version 1.x' );
			$migrated_options = [];

			// migrate the settings with same key
			foreach ( $default_options as $key => $default_value ) {
				if ( isset( $old_options[ $key ] ) ) {
					$migrated_options[ $key ] = $old_options[ $key ];
				} else {
					$migrated_options[ $key ] = $default_value;
				}
			}

			$changed_keys = [
				'enable_page_caching' => 'enable_page_cache',
				'configure_htaccess'  => 'auto_configure_htaccess',
				'cdn_status'          => 'enable_cdn',
				'show_cache_message'  => 'cache_footprint',
			];

			foreach ( $changed_keys as $old_key => $new_key ) {
				if ( isset( $old_options[ $old_key ] ) ) {
					$migrated_options[ $new_key ] = $old_options[ $old_key ];
				}
			}

			$active_extensions  = (array) $old_options['active_extensions'];
			$extension_settings = (array) $old_options['extension_settings'];

			$migrated_options['enable_cloudflare'] = in_array( 'cloudflare', $active_extensions, true );
			if ( ! empty( $extension_settings['cloudflare'] ) ) {
				$migrated_options['cloudflare_email']   = $extension_settings['cloudflare']['email'];
				$migrated_options['cloudflare_api_key'] = $extension_settings['cloudflare']['api_key'];
				$migrated_options['cloudflare_zone']    = $extension_settings['cloudflare']['zone'];
			}

			$migrated_options['enable_lazy_load'] = in_array( 'lazy-load', $active_extensions, true );

			if ( ! empty( $extension_settings['lazyload'] ) ) {
				$migrated_options['lazy_load_post_content']   = $extension_settings['lazyload']['post_content'];
				$migrated_options['lazy_load_images']         = $extension_settings['lazyload']['image'];
				$migrated_options['lazy_load_iframes']        = $extension_settings['lazyload']['iframe'];
				$migrated_options['lazy_load_widgets']        = $extension_settings['lazyload']['widget_text'];
				$migrated_options['lazy_load_post_thumbnail'] = $extension_settings['lazyload']['post_thumbnail'];
				$migrated_options['lazy_load_avatars']        = $extension_settings['lazyload']['avatar'];
			}

			$migrated_options['enable_cache_preload'] = in_array( 'preload', $active_extensions, true );

			if ( ! empty( $extension_settings['preload'] ) ) {
				$migrated_options['preload_homepage']       = (bool) $extension_settings['preload']['homepage'];
				$migrated_options['preload_public_posts']   = (bool) $extension_settings['preload']['post_count'];
				$migrated_options['preload_public_tax']     = (bool) $extension_settings['preload']['taxonomies'];
				$migrated_options['enable_sitemap_preload'] = (bool) $extension_settings['preload']['sitemap_integration'];
				$migrated_options['preload_sitemap']        = $extension_settings['preload']['sitemaps'];
			}

			$migrated_options['enable_varnish'] = in_array( 'varnish', $active_extensions, true );
			if ( ! empty( $extension_settings['varnish'] ) ) {
				$migrated_options['varnish_ip'] = $extension_settings['varnish']['varnish_ip'];
			}

			if ( in_array( 'minifier', $active_extensions, true ) && ! empty( $extension_settings['minifier'] ) ) {
				$migrated_options['minify_html']         = $extension_settings['minifier']['minify_html'];
				$migrated_options['minify_css']          = $extension_settings['minifier']['minify_css'];
				$migrated_options['combine_css']         = $extension_settings['minifier']['concat_css'];
				$migrated_options['minify_js']           = $extension_settings['minifier']['minify_js'];
				$migrated_options['combine_js']          = $extension_settings['minifier']['concat_js'];
				$migrated_options['excluded_css_files']  = $extension_settings['minifier']['excluded_css'];
				$migrated_options['excluded_js_files']   = $extension_settings['minifier']['excluded_js'];
				$migrated_options['js_execution_method'] = $extension_settings['minifier']['js_execution'];
			}

			update_option( SETTING_OPTION, $migrated_options );
			Config::factory()->save_configuration( $migrated_options ); // make it current

			\PoweredCache\Utils\log( 'Upgraded from version 1.x' );

			// remove old crons
			wp_clear_scheduled_hook( 'powered_cache_preload_hook' );
			wp_clear_scheduled_hook( 'powered_cache_preload_child_process' );
			wp_clear_scheduled_hook( 'powered_cache_purge_cache' );
		}
	}


}

