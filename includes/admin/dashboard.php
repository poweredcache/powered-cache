<?php
/**
 * Dashboard Page
 *
 * @package PoweredCache
 */

namespace PoweredCache\Admin\Dashboard;

use PoweredCache\Async\CachePreloader;
use PoweredCache\Async\CachePurger;
use PoweredCache\Async\DatabaseOptimizer;
use PoweredCache\Config;
use const PoweredCache\Constants\ICON_BASE64;
use const PoweredCache\Constants\MENU_SLUG;
use const PoweredCache\Constants\PURGE_CACHE_CRON_NAME;
use const PoweredCache\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT;
use const PoweredCache\Constants\SETTING_OPTION;
use function PoweredCache\Utils\can_configure_htaccess;
use function PoweredCache\Utils\can_configure_object_cache;
use function PoweredCache\Utils\can_control_all_settings;
use function PoweredCache\Utils\cdn_zones;
use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\get_available_object_caches;
use function PoweredCache\Utils\get_cache_dir;
use function PoweredCache\Utils\get_timeout_with_interval;
use function PoweredCache\Utils\is_premium;
use function PoweredCache\Utils\powered_cache_flush;
use function PoweredCache\Utils\remove_dir;
use function PoweredCache\Utils\sanitize_css;

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	if ( POWERED_CACHE_IS_NETWORK ) {
		add_action( 'network_admin_menu', __NAMESPACE__ . '\\admin_menu' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_message' );
	} else {
		add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
	}

	add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_message' );

	add_action( 'admin_init', __NAMESPACE__ . '\\process_form_submit' );
	add_filter( 'admin_body_class', __NAMESPACE__ . '\\add_sui_admin_body_class' );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\admin_bar_menu', 999 );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\purge_all_admin_bar_menu' );
	add_action( 'admin_post_powered_cache_purge_all_cache', __NAMESPACE__ . '\\purge_all_cache' );
	add_action( 'admin_post_powered_cache_download_rewrite_settings', __NAMESPACE__ . '\\download_rewrite_config' );
	add_action( 'wp_ajax_powered_cache_run_diagnostic', __NAMESPACE__ . '\\run_diagnostic' );
	add_action( 'admin_post_deactivate_plugin', __NAMESPACE__ . '\\deactivate_plugin' );
	add_filter( 'plugin_action_links_' . plugin_basename( POWERED_CACHE_PLUGIN_FILE ), __NAMESPACE__ . '\\action_links' );
	add_filter( 'network_admin_plugin_action_links_' . plugin_basename( POWERED_CACHE_PLUGIN_FILE ), __NAMESPACE__ . '\\action_links' );
}

/**
 * Add required class for shared UI
 *
 * @param string $classes css classes for admin area
 *
 * @return string
 * @see https://wpmudev.github.io/shared-ui/installation/
 */
function add_sui_admin_body_class( $classes ) {
	$classes .= ' sui-2-12-20 ';

	return $classes;
}

/**
 * Adds admin menu item
 *
 * @since 1.0
 */
function admin_menu() {
	global $powered_cache_settings_page;

	$capability = 'manage_options';

	if ( POWERED_CACHE_IS_NETWORK ) {
		$capability = 'manage_network';
	}

	$powered_cache_settings_page = add_menu_page(
		esc_html__( 'Powered Cache Settings', 'powered-cache' ),
		esc_html__( 'Powered Cache', 'powered-cache' ),
		$capability,
		MENU_SLUG,
		__NAMESPACE__ . '\settings_page',
		ICON_BASE64
	);

	/**
	 * Different name submenu item, url point same address with parent.
	 */
	add_submenu_page(
		MENU_SLUG,
		esc_html__( 'Powered Cache Settings', 'powered-cache' ),
		esc_html__( 'Settings', 'powered-cache' ),
		$capability,
		MENU_SLUG
	);
}

/**
 * Main settings page of the plugin
 */
function settings_page() {
	include __DIR__ . '/partials/settings-page.php';
}

/**
 * Process settings form action
 *
 * @since 2.0
 */
function process_form_submit() {

	if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$nonce = filter_input( INPUT_POST, 'powered_cache_settings_nonce', FILTER_SANITIZE_SPECIAL_CHARS );
	if ( wp_verify_nonce( $nonce, 'powered_cache_update_settings' ) ) {
		$action      = isset( $_POST['powered_cache_form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['powered_cache_form_action'] ) ) : 'save_settings';
		$old_options = \PoweredCache\Utils\get_settings();
		$options     = sanitize_options( $_POST );

		switch ( $action ) {
			case 'reset_settings':
				if ( POWERED_CACHE_IS_NETWORK ) {
					delete_site_option( SETTING_OPTION );
				} else {
					delete_option( SETTING_OPTION );
				}

				if ( 'off' !== $old_options['object_cache'] ) {
					wp_cache_flush();
				}

				$options = \PoweredCache\Utils\get_settings();

				break;
			case 'export_settings':
				$filename = sprintf( 'powered-cache-settings-%s-%s.json', gmdate( 'Y-m-d' ), uniqid() );
				if ( POWERED_CACHE_IS_NETWORK ) {
					$options = wp_json_encode( get_site_option( SETTING_OPTION ), JSON_PRETTY_PRINT );
				} else {
					$options = wp_json_encode( get_option( SETTING_OPTION ), JSON_PRETTY_PRINT );
				}

				nocache_headers();
				// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
				@header( 'Content-Type: application/json' );
				@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				@header( 'Content-Transfer-Encoding: binary' );
				@header( 'Content-Length: ' . strlen( $options ) );
				@header( 'Connection: close' );
				// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
				echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			case 'import_settings':
				if ( $_FILES['import_file'] && ! empty( $_FILES['import_file']['tmp_name'] ) ) { // phpcs:ignore
					$import_data     = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore
					$import_settings = json_decode( $import_data, true );
					$options         = sanitize_options( $import_settings );
				}
				break;
			case 'save_settings_and_optimize':
				db_optimize( $options );
				break;
			case 'save_settings':
			default:
				break;
		}

		if ( POWERED_CACHE_IS_NETWORK ) {
			update_site_option( SETTING_OPTION, $options );
		} else {
			update_option( SETTING_OPTION, $options );
		}

		Config::factory()->save_configuration( $options, POWERED_CACHE_IS_NETWORK );

		// drop object cache on backend changes
		if ( isset( $options['object_cache'] ) && $old_options['object_cache'] !== $options['object_cache'] ) {
			wp_cache_flush();
		}

		// maybe cancel preloading process when it turned off
		if ( $old_options['enable_cache_preload'] && ! $options['enable_cache_preload'] ) {
			cancel_preloading();
		}

		if ( $old_options['async_cache_cleaning'] && ! $options['async_cache_cleaning'] ) {
			cancel_async_cache_cleaning();
		}

		// cleanup existing cache on toggling cache option
		if ( $old_options['enable_page_cache'] && ! $options['enable_page_cache'] ) {
			clean_site_cache_dir();
		}

		if ( $old_options['cache_timeout'] !== $options['cache_timeout'] ) {
			$timestamp = wp_next_scheduled( PURGE_CACHE_CRON_NAME );

			wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );
		}

		/**
		 * Fires after saving configurations.
		 *
		 * @hook  powered_cache_settings_saved
		 *
		 * @param {array} $old_options Old settings.
		 * @param {array} $options New settings.
		 *
		 * @since 1.0
		 */
		do_action( 'powered_cache_settings_saved', $old_options, $options );

		$redirect_url = wp_get_referer();

		if ( empty( $redirect_url ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_url = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$redirect_url = add_query_arg(
			[
				'pc_action' => $action,
			],
			$redirect_url
		);

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}
}

/**
 * Sanitize options
 *
 * @param array $options Raw input, most likely $_POST request
 *
 * @return array|mixed Sanitized options
 */
function sanitize_options( $options ) {
	$sanitized_options = [];

	if ( isset( $options['object_cache'] ) && in_array( $options['object_cache'], get_available_object_caches(), true ) ) {
		$sanitized_options['object_cache'] = sanitize_text_field( $options['object_cache'] );
	} else {
		$sanitized_options['object_cache'] = 'off';
	}

	$sanitized_options['enable_page_cache']             = ! empty( $options['enable_page_cache'] );
	$sanitized_options['cache_mobile']                  = ! empty( $options['cache_mobile'] );
	$sanitized_options['cache_mobile_separate_file']    = ! empty( $options['cache_mobile_separate_file'] );
	$sanitized_options['loggedin_user_cache']           = ! empty( $options['loggedin_user_cache'] );
	$sanitized_options['gzip_compression']              = ! empty( $options['gzip_compression'] );
	$sanitized_options['cache_timeout']                 = absint( $options['cache_timeout'] );
	$sanitized_options['auto_configure_htaccess']       = ! empty( $options['auto_configure_htaccess'] );
	$sanitized_options['rejected_user_agents']          = sanitize_textarea_field( $options['rejected_user_agents'] );
	$sanitized_options['rejected_cookies']              = sanitize_textarea_field( $options['rejected_cookies'] );
	$sanitized_options['vary_cookies']                  = sanitize_textarea_field( $options['vary_cookies'] );
	$sanitized_options['rejected_uri']                  = sanitize_textarea_field( $options['rejected_uri'] );
	$sanitized_options['cache_query_strings']           = sanitize_textarea_field( $options['cache_query_strings'] );
	$sanitized_options['ignored_query_strings']         = sanitize_textarea_field( $options['ignored_query_strings'] );
	$sanitized_options['purge_additional_pages']        = sanitize_textarea_field( $options['purge_additional_pages'] );
	$sanitized_options['minify_html']                   = ! empty( $options['minify_html'] );
	$sanitized_options['combine_google_fonts']          = ! empty( $options['combine_google_fonts'] );
	$sanitized_options['swap_google_fonts_display']     = ! empty( $options['swap_google_fonts_display'] );
	$sanitized_options['use_bunny_fonts']               = ! empty( $options['use_bunny_fonts'] );
	$sanitized_options['minify_css']                    = ! empty( $options['minify_css'] );
	$sanitized_options['combine_css']                   = ! empty( $options['combine_css'] );
	$sanitized_options['critical_css']                  = ! empty( $options['critical_css'] );
	$sanitized_options['critical_css_additional_files'] = sanitize_textarea_field( $options['critical_css_additional_files'] );
	$sanitized_options['critical_css_excluded_files']   = sanitize_textarea_field( $options['critical_css_excluded_files'] );
	$sanitized_options['excluded_css_files']            = sanitize_textarea_field( $options['excluded_css_files'] );
	$sanitized_options['remove_unused_css']             = ! empty( $options['remove_unused_css'] );
	$sanitized_options['ucss_safelist']                 = sanitize_textarea_field( $options['ucss_safelist'] );
	$sanitized_options['ucss_excluded_files']           = sanitize_textarea_field( $options['ucss_excluded_files'] );
	$sanitized_options['minify_js']                     = ! empty( $options['minify_js'] );
	$sanitized_options['combine_js']                    = ! empty( $options['combine_js'] );
	$sanitized_options['excluded_js_files']             = sanitize_textarea_field( $options['excluded_js_files'] );
	$sanitized_options['js_defer']                      = ! empty( $options['js_defer'] );
	$sanitized_options['js_defer_exclusions']           = sanitize_textarea_field( $options['js_defer_exclusions'] );
	$sanitized_options['js_delay']                      = ! empty( $options['js_delay'] );
	$sanitized_options['js_delay_exclusions']           = sanitize_textarea_field( $options['js_delay_exclusions'] );
	$sanitized_options['enable_image_optimization']     = ! empty( $options['enable_image_optimization'] );
	$sanitized_options['enable_lazy_load']              = ! empty( $options['enable_lazy_load'] );
	$sanitized_options['lazy_load_post_content']        = ! empty( $options['lazy_load_post_content'] );
	$sanitized_options['lazy_load_images']              = ! empty( $options['lazy_load_images'] );
	$sanitized_options['lazy_load_iframes']             = ! empty( $options['lazy_load_iframes'] );
	$sanitized_options['lazy_load_widgets']             = ! empty( $options['lazy_load_widgets'] );
	$sanitized_options['lazy_load_post_thumbnail']      = ! empty( $options['lazy_load_post_thumbnail'] );
	$sanitized_options['lazy_load_avatars']             = ! empty( $options['lazy_load_avatars'] );
	$sanitized_options['lazy_load_skip_first_nth_img']  = absint( $options['lazy_load_skip_first_nth_img'] );
	$sanitized_options['disable_wp_lazy_load']          = ! empty( $options['disable_wp_lazy_load'] );
	$sanitized_options['add_missing_image_dimensions']  = ! empty( $options['add_missing_image_dimensions'] );
	$sanitized_options['disable_wp_embeds']             = ! empty( $options['disable_wp_embeds'] );
	$sanitized_options['disable_emoji_scripts']         = ! empty( $options['disable_emoji_scripts'] );
	$sanitized_options['enable_cdn']                    = ! empty( $options['enable_cdn'] );

	// convert TTL in minute
	if ( $options['cache_timeout'] > 0 && isset( $options['cache_timeout_interval'] ) ) {
		switch ( $options['cache_timeout_interval'] ) {
			case 'DAY':
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 1440;
				break;
			case 'HOUR':
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 60;
				break;
			case 'MINUTE':
			default:
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 1;
		}
	}

	$cdn_hostname = [];
	if ( isset( $options['cdn_hostname'] ) ) {
		foreach ( (array) $options['cdn_hostname'] as $hostname ) {
			$hostname = trim( $hostname );
			if ( filter_var( $hostname, FILTER_VALIDATE_URL ) ) {
				$cdn_hostname[] = wp_parse_url( $hostname, PHP_URL_HOST );
			} else {
				$cdn_hostname[] = $hostname;
			}
		}
	}

	$cdn_zone = [];

	if ( isset( $options['cdn_zone'] ) ) {
		foreach ( (array) $options['cdn_zone'] as $zone ) {
			$cdn_zone[] = sanitize_text_field( $zone );
		}
	}

	if ( empty( $cdn_hostname ) ) {
		$cdn_hostname = [ '' ];
	}

	if ( empty( $cdn_zone ) ) {
		$cdn_zone = [ '' ];
	}

	$sanitized_options['cdn_hostname']                   = $cdn_hostname;
	$sanitized_options['cdn_zone']                       = $cdn_zone;
	$sanitized_options['cdn_rejected_files']             = sanitize_textarea_field( $options['cdn_rejected_files'] );
	$sanitized_options['enable_cache_preload']           = ! empty( $options['enable_cache_preload'] );
	$sanitized_options['preload_homepage']               = ! empty( $options['preload_homepage'] );
	$sanitized_options['preload_public_posts']           = ! empty( $options['preload_public_posts'] );
	$sanitized_options['preload_public_tax']             = ! empty( $options['preload_public_tax'] );
	$sanitized_options['enable_sitemap_preload']         = ! empty( $options['enable_sitemap_preload'] );
	$sanitized_options['preload_sitemap']                = sanitize_textarea_field( $options['preload_sitemap'] );
	$sanitized_options['prefetch_dns']                   = sanitize_textarea_field( $options['prefetch_dns'] );
	$sanitized_options['preconnect_resource']            = sanitize_textarea_field( $options['preconnect_resource'] );
	$sanitized_options['prefetch_links']                 = ! empty( $options['prefetch_links'] );
	$sanitized_options['db_cleanup_post_revisions']      = ! empty( $options['db_cleanup_post_revisions'] );
	$sanitized_options['db_cleanup_auto_drafts']         = ! empty( $options['db_cleanup_auto_drafts'] );
	$sanitized_options['db_cleanup_trashed_posts']       = ! empty( $options['db_cleanup_trashed_posts'] );
	$sanitized_options['db_cleanup_spam_comments']       = ! empty( $options['db_cleanup_spam_comments'] );
	$sanitized_options['db_cleanup_trashed_comments']    = ! empty( $options['db_cleanup_trashed_comments'] );
	$sanitized_options['db_cleanup_expired_transients']  = ! empty( $options['db_cleanup_expired_transients'] );
	$sanitized_options['db_cleanup_all_transients']      = ! empty( $options['db_cleanup_all_transients'] );
	$sanitized_options['db_cleanup_optimize_tables']     = ! empty( $options['db_cleanup_optimize_tables'] );
	$sanitized_options['enable_scheduled_db_cleanup']    = ! empty( $options['enable_scheduled_db_cleanup'] );
	$sanitized_options['scheduled_db_cleanup_frequency'] = sanitize_text_field( $options['scheduled_db_cleanup_frequency'] );
	$sanitized_options['enable_cloudflare']              = ! empty( $options['enable_cloudflare'] );
	$sanitized_options['cloudflare_email']               = sanitize_email( $options['cloudflare_email'] );
	$sanitized_options['cloudflare_api_key']             = sanitize_text_field( $options['cloudflare_api_key'] );
	$sanitized_options['cloudflare_api_token']           = sanitize_text_field( $options['cloudflare_api_token'] );
	$sanitized_options['cloudflare_zone']                = sanitize_text_field( $options['cloudflare_zone'] );
	$sanitized_options['enable_heartbeat']               = ! empty( $options['enable_heartbeat'] );
	$sanitized_options['heartbeat_dashboard_status']     = sanitize_text_field( $options['heartbeat_dashboard_status'] );
	$sanitized_options['heartbeat_editor_status']        = sanitize_text_field( $options['heartbeat_editor_status'] );
	$sanitized_options['heartbeat_frontend_status']      = sanitize_text_field( $options['heartbeat_frontend_status'] );
	$sanitized_options['heartbeat_dashboard_interval']   = absint( $options['heartbeat_dashboard_interval'] );
	$sanitized_options['heartbeat_editor_interval']      = absint( $options['heartbeat_editor_interval'] );
	$sanitized_options['heartbeat_frontend_interval']    = absint( $options['heartbeat_frontend_interval'] );
	$sanitized_options['enable_varnish']                 = ! empty( $options['enable_varnish'] );
	$sanitized_options['varnish_ip']                     = sanitize_text_field( $options['varnish_ip'] );
	$sanitized_options['cache_footprint']                = ! empty( $options['cache_footprint'] );
	$sanitized_options['async_cache_cleaning']           = ! empty( $options['async_cache_cleaning'] );
	$sanitized_options['enable_google_tracking']         = ! empty( $options['enable_google_tracking'] );
	$sanitized_options['enable_fb_tracking']             = ! empty( $options['enable_fb_tracking'] );

	if ( isset( $options['critical_css_appended_content'] ) ) {
		$sanitized_options['critical_css_appended_content'] = sanitize_css( $options['critical_css_appended_content'] );
	}

	if ( isset( $options['critical_css_fallback'] ) ) {
		$sanitized_options['critical_css_fallback'] = sanitize_css( $options['critical_css_fallback'] );
	}

	/**
	 * Filters sanitized options.
	 *
	 * @hook   powered_cache_sanitized_options
	 *
	 * @param  {array} $sanitized_options Sanitized options.
	 * @param  {array} $options raw input.
	 *
	 * @return {array} New value.
	 *
	 * @since  2.0
	 */
	return apply_filters( 'powered_cache_sanitized_options', $sanitized_options, $options );
}

/**
 * Add base admin bar menu
 *
 * @param object $wp_admin_bar Admin bar object
 *
 * @since 2.0
 */
function admin_bar_menu( $wp_admin_bar ) {
	$href = admin_url( 'admin.php?page=powered-cache' );

	if ( POWERED_CACHE_IS_NETWORK && current_user_can( 'manage_network' ) ) {
		$href = network_admin_url( 'admin.php?page=powered-cache' );
	}

	if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
		$href = '#';
	}

	if ( current_user_can( 'manage_options' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'id'    => MENU_SLUG,
				'title' => __( 'Powered Cache', 'powered-cache' ),
				'href'  => $href,
			)
		);
	}
}

/**
 * Maybe display feedback messages when certain action is taken
 *
 * @since 2.0
 */
function maybe_display_message() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['pc_action'] ) ) {
		return;
	}

	/**
	 * Dont display multiple message when saving the options while having the query_params
	 */
	if ( ! empty( $_POST ) ) {
		return;
	}

	$screen = get_current_screen();

	$success_messages = [
		'flush_page_cache_network'   => esc_html__( 'Page cache deleted for all websites!', 'powered-cache' ),
		'flush_page_cache'           => esc_html__( 'Page cache deleted successfully!', 'powered-cache' ),
		'flush_object_cache'         => esc_html__( 'Object cache deleted successfully!', 'powered-cache' ),
		'flush_all_cache'            => esc_html__( 'All cached items flushed successfully!', 'powered-cache' ),
		'start_preload'              => esc_html__( 'The cache preloading has been initialized!', 'powered-cache' ),
		'generate_critical'          => esc_html__( 'The Critical CSS generation process has been initialized!', 'powered-cache' ),
		'generate_critical_network'  => esc_html__( 'The Critical CSS generation process has been initialized for all sites! This might take a while, depending on the network size.', 'powered-cache' ),
		'generate_ucss'              => esc_html__( 'The UCSS generation process has been initialized!', 'powered-cache' ),
		'generate_ucss_network'      => esc_html__( 'The UCSS generation process has been initialized for all sites! This might take a while, depending on the network size.', 'powered-cache' ),
		'flush_cf_cache'             => esc_html__( 'Cloudflare cache flushed, it can take up to 30 seconds to delete all cache from Cloudflare!', 'powered-cache' ),
		'reset_settings'             => esc_html__( 'Settings have been reset!', 'powered-cache' ),
		'import_settings'            => esc_html__( 'Settings have been imported!', 'powered-cache' ),
		'save_settings_and_optimize' => esc_html__( 'Settings saved and database being optimized...', 'powered-cache' ),
		'save_settings'              => esc_html__( 'Settings saved.', 'powered-cache' ),
	];

	if ( isset( $_GET['language'] ) ) {
		$success_messages['flush_lang_cache'] = sprintf( esc_html__( 'Page cache for %s language has been deleted!', 'powered-cache' ), esc_attr( urldecode_deep( $_GET['language'] ) ) ); // phpcs:ignore
	}

	$err_messages = [
		'generic_permission_err'                  => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'flush_page_cache_network_err_permission' => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'flush_page_cache_err_permission'         => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'flush_object_cache_err_permission'       => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'flush_all_cache_err_permission'          => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'start_preload_err_permission'            => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'start_critical_err_permission'           => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'start_ucss_err_permission'               => esc_html__( 'You don\'t have permission to perform this action!', 'powered-cache' ),
		'start_critical_err_license'              => esc_html__( 'Your license key does not seem valid. A valid license is required for the Critical CSS!', 'powered-cache' ),
		'start_ucss_err_license'                  => esc_html__( 'Your license key does not seem valid. A valid license is required for removing unused CSS!', 'powered-cache' ),
		'flush_cf_cache_failed'                   => esc_html__( 'Could not flush Cloudflare cache. Please make sure you entered the correct credentials and zone id!', 'powered-cache' ),
	];

	if ( isset( $success_messages[ $_GET['pc_action'] ] ) ) {
		if ( MENU_SLUG === $screen->parent_base ) { // display with shared-ui on plugin page
			add_settings_error( $screen->parent_file, MENU_SLUG, $success_messages[ $_GET['pc_action'] ], 'success' ); // phpcs:ignore

			return;
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', $success_messages[ $_GET['pc_action'] ] ); // phpcs:ignore
	}

	if ( isset( $err_messages[ $_GET['pc_action'] ] ) ) {
		if ( MENU_SLUG === $screen->parent_base ) { // display with shared-ui on plugin page
			add_settings_error( $screen->parent_file, MENU_SLUG, $err_messages[ $_GET['pc_action'] ], 'error' ); // phpcs:ignore

			return;
		}

		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', $err_messages[ $_GET['pc_action'] ] ); // phpcs:ignore
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}


/**
 * Adds `Purge All Cache` menu bar item
 *
 * @param object $wp_admin_bar Admin bar object
 *
 * @since 1.1
 */
function purge_all_admin_bar_menu( $wp_admin_bar ) {
	// Only available for the network admins on multisite.
	if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	$wp_admin_bar->add_menu(
		array(
			'id'     => 'all-cache-purge',
			'title'  => __( 'Purge All Cache', 'powered-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_all_cache' ), 'powered_cache_purge_all_cache' ),
			'parent' => 'powered-cache',
		)
	);
}

/**
 * Purges all cache related things
 *
 * @since 1.1
 */
function purge_all_cache() {

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_all_cache' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
		$redirect_url = add_query_arg( 'pc_action', 'flush_all_cache_err_permission', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		$redirect_url = add_query_arg( 'pc_action', 'flush_all_cache_err_permission', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$cache_purger = CachePurger::factory();
	$settings     = \PoweredCache\Utils\get_settings();

	if ( $settings['async_cache_cleaning'] ) {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		$cache_purger->push_to_queue( [ 'call' => 'powered_cache_flush' ] );
		$cache_purger->save()->dispatch();
	} else {
		powered_cache_flush();// cleans object cache + page cache dir
	}

	/**
	 * Fires after purging all cache
	 *
	 * @hook  powered_cache_purge_all_cache
	 *
	 * @since 1.1
	 */
	do_action( 'powered_cache_purge_all_cache' );

	if ( POWERED_CACHE_IS_NETWORK ) {
		delete_site_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
	} else {
		delete_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
	}

	$redirect_url = add_query_arg( 'pc_action', 'flush_all_cache', wp_get_referer() );

	wp_safe_redirect( esc_url_raw( $redirect_url ) );
	exit;
}


/**
 * Downloads proper configuration file
 *
 * @since 1.1
 */
function download_rewrite_config() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_download_rewrite' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( ! can_control_all_settings() ) {
		$redirect_url = add_query_arg( 'pc_action', 'generic_permission_err', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! empty( $_GET['server'] ) ) {
		$server = sanitize_text_field( wp_unslash( $_GET['server'] ) );
		Config::factory()->download_rewrite_rules( $server );
	}

	wp_safe_redirect( wp_get_referer() );
	die();
}

/**
 * Run DB optimization
 *
 * @param array $options plugin settings
 */
function db_optimize( $options ) {
	$powered_cache_db_optimizer = DatabaseOptimizer::factory();

	$supported_options = $powered_cache_db_optimizer->get_supported_options();

	if ( POWERED_CACHE_IS_NETWORK ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			foreach ( $supported_options as $optimization_item ) {
				if ( $options[ $optimization_item ] ) {
					$powered_cache_db_optimizer->push_to_queue( $optimization_item );
				}
			}

			$powered_cache_db_optimizer->save()->dispatch();
			restore_current_blog();
		}
	} else {
		foreach ( $supported_options as $optimization_item ) {
			if ( $options[ $optimization_item ] ) {
				$powered_cache_db_optimizer->push_to_queue( $optimization_item );
			}
		}

		$powered_cache_db_optimizer->save()->dispatch();
	}

}

/**
 * Cancel preloading process on toggling preload option
 */
function cancel_preloading() {
	\PoweredCache\Utils\log( 'Cancel preload process' );
	$cache_preloader = CachePreloader::factory();
	$cache_preloader->cancel_process();
}

/**
 * Cancel async cache purging processes
 *
 * @since 2.3
 */
function cancel_async_cache_cleaning() {
	\PoweredCache\Utils\log( 'Cancel CachePurger process' );
	$cache_preloader = CachePurger::factory();
	$cache_preloader->cancel_process();
}


/**
 * Perform diagnostic checks
 */
function run_diagnostic() {
	global $is_apache;

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( wp_verify_nonce( $nonce, 'powered_cache_run_diagnostic' ) ) {
		$settings = \PoweredCache\Utils\get_settings();
		$checks   = array();

		// check config file
		$config_file        = Config::factory()->find_wp_config_file();
		$config_file_status = is_writeable( $config_file );

		if ( $config_file_status ) {
			$config_file_desc = esc_html__( 'wp-config.php is writable.', 'powered-cache' );
		} else {
			$config_file_desc = sprintf( __( 'wp-config.php is not writable. Please make sure the file writable or you can manually define %s constant.', 'powered-cache' ), '<code>WP_CACHE</code>' );
		}

		$checks[] = array(
			'check'       => 'config',
			'status'      => $config_file_status,
			'description' => $config_file_desc,
		);

		// check cache directory
		$cache_dir        = get_cache_dir();
		$cache_dir_status = false;
		if ( ! file_exists( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not exist!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		} elseif ( ! is_writeable( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not writeable!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		} else {
			$cache_dir_status = true;
			$cache_dir_desc   = sprintf( __( 'Cache directory %s exist and writable!', 'powered-cache' ), '<code>' . $cache_dir . '</code>' );
		}

		$checks[] = array(
			'check'       => 'cache-dir',
			'status'      => $cache_dir_status,
			'description' => $cache_dir_desc,
		);

		// check .htaccess file
		if ( $is_apache && $settings['auto_configure_htaccess'] ) {
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

			$checks[] = array(
				'check'       => 'htaccess',
				'status'      => $htaccess_file_status,
				'description' => $htaccess_file_desc,
			);
		}

		// check page cache
		if ( $settings['enable_page_cache'] ) {
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

			$checks[] = array(
				'check'       => 'advanced-cache',
				'status'      => $advanced_cache_file_status,
				'description' => $advanced_cache_file_desc,
			);
		}

		// check object cache
		if ( 'off' !== $settings['object_cache'] ) {
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

			$checks[] = array(
				'check'       => 'object-cache',
				'status'      => $object_cache_file_status,
				'description' => $object_cache_file_desc,
			);
		}

		wp_send_json_success( $checks );
	}

	wp_send_json_error( [ esc_html__( 'Invalid request', 'powered-cache' ) ] );
}


/**
 * Deactivate incompatible plugins
 *
 * @since 1.0
 */
function deactivate_plugin() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'deactivate_plugin' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		$redirect_url = add_query_arg( 'pc_action', 'generic_permission_err', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( isset( $_GET['plugin'] ) ) {
		$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
		deactivate_plugins( $plugin );
	}

	wp_safe_redirect( wp_get_referer() );
	die();
}

/**
 * Adds settings link to plugin actions
 *
 * @param array $actions Plugin actions.
 *
 * @return array
 * @since  1.0
 */
function action_links( $actions ) {

	$settings_url      = POWERED_CACHE_IS_NETWORK ? network_admin_url( 'admin.php?page=powered-cache' ) : admin_url( 'admin.php?page=powered-cache' );
	$powered_cache_url = 'https://poweredcache.com/?utm_source=wp_admin&utm_medium=plugin&utm_campaign=plugin_action_links';

	$actions['powered_settings'] = sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'powered-cache' ) );

	if ( ! is_premium() ) {
		$actions['get_premium'] = sprintf( '<a href="%s" style="color: red;">%s</a>', esc_url( $powered_cache_url ), esc_html__( 'Get Premium', 'powered-cache' ) );
	}

	return array_reverse( $actions );
}
