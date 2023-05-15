<?php
/**
 * Utils
 *
 * @package PoweredCache
 */

namespace PoweredCache\Utils;

use const PoweredCache\Constants\SETTING_OPTION;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Is plugin activated network wide?
 *
 * @param string $plugin_file file path
 *
 * @return bool
 * @since 2.0
 */
function is_network_wide( $plugin_file ) {
	if ( ! is_multisite() ) {
		return false;
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
	}

	return is_plugin_active_for_network( plugin_basename( $plugin_file ) );
}

/**
 * Get settings with defaults
 *
 * @param bool $force_network_wide Whether getting settings for network or not.
 *                                 The function respects `POWERED_CACHE_IS_NETWORK` by default.
 *                                 However, `POWERED_CACHE_IS_NETWORK` is not functional on
 *                                 (de)activation hooks.
 *
 * @return array
 * @since  2.0
 */
function get_settings( $force_network_wide = false ) {
	$settings = [
		// basic options
		'enable_page_cache'              => true,
		'object_cache'                   => 'off',
		'cache_mobile'                   => true,
		'cache_mobile_separate_file'     => false,
		'loggedin_user_cache'            => false,
		'ssl_cache'                      => true, // deprecated
		'gzip_compression'               => false,
		'cache_timeout'                  => 1440,
		// advanced options
		'auto_configure_htaccess'        => true,
		'rejected_user_agents'           => '',
		'rejected_cookies'               => '',
		'vary_cookies'                   => '',
		'rejected_uri'                   => '',
		'ignored_query_strings'          => '',
		'cache_query_strings'            => '',
		'purge_additional_pages'         => '',
		// file optimization
		'minify_html'                    => false,
		'combine_google_fonts'           => false,
		'swap_google_fonts_display'      => true,
		'use_bunny_fonts'                => false,
		'minify_css'                     => false,
		'combine_css'                    => false,
		'critical_css'                   => false,
		'critical_css_additional_files'  => '',
		'critical_css_excluded_files'    => '',
		'critical_css_appended_content'  => '',
		'critical_css_fallback'          => '',
		'excluded_css_files'             => '',
		'remove_unused_css'              => false,
		'ucss_safelist'                  => '',
		'ucss_excluded_files'            => '',
		'minify_js'                      => false,
		'combine_js'                     => false,
		'excluded_js_files'              => '',
		'js_execution_method'            => 'blocking',
		'js_execution_optimized_only'    => true,
		// media optimization
		'enable_image_optimization'      => false,
		// lazyload
		'enable_lazy_load'               => false,
		'lazy_load_post_content'         => true,
		'lazy_load_images'               => true,
		'lazy_load_iframes'              => true,
		'lazy_load_widgets'              => true,
		'lazy_load_post_thumbnail'       => true,
		'lazy_load_avatars'              => true,
		'disable_wp_lazy_load'           => false,
		'disable_wp_embeds'              => false,
		'disable_emoji_scripts'          => false,
		// cdn
		'enable_cdn'                     => false,
		'cdn_hostname'                   => array( '' ),
		'cdn_zone'                       => array( '' ),
		'cdn_rejected_files'             => '',
		// preload
		'enable_cache_preload'           => false,
		'preload_homepage'               => true,
		'preload_public_posts'           => true,
		'preload_public_tax'             => true,
		'enable_sitemap_preload'         => false,
		'preload_sitemap'                => '',
		'prefetch_dns'                   => '',
		'preconnect_resource'            => '',
		// db options
		'db_cleanup_post_revisions'      => false,
		'db_cleanup_auto_drafts'         => false,
		'db_cleanup_trashed_posts'       => false,
		'db_cleanup_spam_comments'       => false,
		'db_cleanup_trashed_comments'    => false,
		'db_cleanup_expired_transients'  => false,
		'db_cleanup_all_transients'      => false,
		'db_cleanup_optimize_tables'     => false,
		'enable_scheduled_db_cleanup'    => false,
		'scheduled_db_cleanup_frequency' => 'daily',
		// add-ons
		'enable_cloudflare'              => false,
		'cloudflare_api_token'           => '',
		'cloudflare_email'               => '',
		'cloudflare_api_key'             => '',
		'cloudflare_zone'                => '',
		'enable_heartbeat'               => false, // extention status
		'heartbeat_dashboard_status'     => 'enable', // enable,disable,modify
		'heartbeat_dashboard_interval'   => 60, // default interval in seconds
		'heartbeat_editor_status'        => 'enable', // enable,disable,modify
		'heartbeat_editor_interval'      => 15, // default interval in seconds
		'heartbeat_frontend_status'      => 'enable', // enable,disable,modify
		'heartbeat_frontend_interval'    => 60, // default interval in seconds
		'enable_varnish'                 => false,
		'varnish_ip'                     => '',
		// misc
		'cache_footprint'                => true,
		'async_cache_cleaning'           => false,
		// new options needs to migrate from extensions
		'enable_google_tracking'         => false,
		'enable_fb_tracking'             => false,
	];

	/**
	 * Filter default settings.
	 *
	 * @hook   powered_cache_default_settings
	 *
	 * @param  {array} $settings Default settings.
	 *
	 * @return {array} New value
	 * @since  2.0
	 */
	$default_settings = apply_filters( 'powered_cache_default_settings', $settings );

	if ( POWERED_CACHE_IS_NETWORK || $force_network_wide ) {
		$settings = get_site_option( SETTING_OPTION, [] );
	} else {
		$settings = get_option( SETTING_OPTION, [] );
	}

	$settings = wp_parse_args( $settings, $default_settings );

	return $settings;
}


/**
 * return base caching dir
 * use this function to get base caching directory instead of directly calling constant
 *
 * @return string path
 * @since 1.0
 */
function get_cache_dir() {
	if ( defined( 'POWERED_CACHE_CACHE_DIR' ) ) {
		return POWERED_CACHE_CACHE_DIR; // don't change unless have a particular reason
	}

	return WP_CONTENT_DIR . '/cache/';
}


/**
 * Object cache methods keys will use as option
 *
 * @return array $object_caches
 * @since 1.2 apcu added
 *
 * @since 1.0
 */
function get_object_cache_dropins() {

	$object_caches = array(
		'memcache'  => POWERED_CACHE_DROPIN_DIR . 'memcache-object-cache.php',
		'memcached' => POWERED_CACHE_DROPIN_DIR . 'memcached-object-cache.php',
		'redis'     => POWERED_CACHE_DROPIN_DIR . 'redis-object-cache.php',
		'apcu'      => POWERED_CACHE_DROPIN_DIR . 'apcu-object-cache.php',
	);

	/**
	 * Filter object cache dropins.
	 *
	 * @hook   powered_cache_object_cache_dropins
	 *
	 * @param  {array} $object_caches The list of supported object-cache dropins.
	 *
	 * @return {array} New value
	 * @since  1.0
	 */
	return apply_filters( 'powered_cache_object_cache_dropins', $object_caches );
}


/**
 * Get available object cache backends
 *
 * @return array
 * @since 1.2 unset apcu
 * @since 1.0
 */
function get_available_object_caches() {
	$object_cache_methods = get_object_cache_dropins();

	if ( ! class_exists( '\Memcache' ) || version_compare( PHP_VERSION, '5.6.20', '<' ) ) {
		unset( $object_cache_methods['memcache'] );
	}

	if ( ! class_exists( '\Memcached' ) ) {
		unset( $object_cache_methods['memcached'] );
	}

	if ( ! class_exists( '\Redis' ) ) {
		unset( $object_cache_methods['redis'] );
	}

	if ( ! function_exists( '\apcu_add' ) ) {
		unset( $object_cache_methods['apcu'] );
	}

	return array_keys( $object_cache_methods );
}


/**
 * convert minutes to possible time format
 *
 * @param int $timeout_in_minutes TTL in minutes
 *
 * @return array
 * @since 1.1
 */
function get_timeout_with_interval( $timeout_in_minutes ) {
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
 * Determine whether display or not display htaccess configuration
 * .htaccess can affect the way of serving cached files.
 * Therefore it's only available for network admin on multisite
 *
 * @return bool
 */
function can_configure_htaccess() {
	global $is_apache;

	if ( ! $is_apache ) {
		return false;
	}

	if ( POWERED_CACHE_IS_NETWORK && current_user_can( 'manage_network' ) ) {
		return true;
	}

	if ( is_multisite() && ! POWERED_CACHE_IS_NETWORK ) {
		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}

/**
 * Whether current user capable to do any configuration changes
 *
 * @return bool
 */
function can_control_all_settings() {
	if ( is_multisite() ) {
		if ( current_user_can( 'manage_network' ) ) {
			return true;
		}

		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}


/**
 * Object cache has an effect on all WP
 * So, it should be available for the network admin on multisite
 * regardless of network-wide or individual activated
 *
 * @return bool
 */
function can_configure_object_cache() {
	if ( is_multisite() ) {
		// only allow on network-wide activation
		if ( POWERED_CACHE_IS_NETWORK && current_user_can( 'manage_network' ) ) {
			return true;
		}

		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}

/**
 * Supported js execution methods
 *
 * @return mixed|void
 */
function js_execution_methods() {
	$methods = [
		'blocking' => esc_html__( 'Blocking – (default)', 'powered-cache' ),
		'async'    => esc_html__( 'Non-blocking using async', 'powered-cache' ),
		'defer'    => esc_html__( 'Non-blocking using defer', 'powered-cache' ),
		'delayed'  => esc_html__( 'Delayed for user interaction', 'powered-cache' ),
	];

	/**
	 * Filter supported JS execution methods.
	 *
	 * @hook   powered_cache_js_execution_methods
	 *
	 * @param  {array} $powered_cache_js_execution_methods JS execution methods.
	 *
	 * @return {array} New value
	 * @since  2.0
	 */
	return apply_filters( 'powered_cache_js_execution_methods', $methods );
}


/**
 * Get available zones
 *
 * @return mixed|void
 * @since 1.0
 */
function cdn_zones() {
	$zones = [
		'all'   => esc_html__( 'All files', 'powered-cache' ),
		'image' => esc_html__( 'Images', 'powered-cache' ),
		'js'    => esc_html__( 'JavaScript', 'powered-cache' ),
		'css'   => esc_html__( 'CSS', 'powered-cache' ),
	];

	/**
	 * Filter CDN zone options.
	 *
	 * @hook   powered_cache_cdn_zones
	 *
	 * @param  {array} $zones CDN Zones (all,image,js,css)
	 *
	 * @return {array} New value
	 * @since  1.0
	 */
	return apply_filters( 'powered_cache_cdn_zones', $zones );
}

/**
 * Which version of plugin running
 *
 * @return bool
 */
function is_premium() {
	if ( defined( 'POWERED_CACHE_PREMIUM_PLUGIN_FILE' ) && POWERED_CACHE_PREMIUM_PLUGIN_FILE ) {
		return true;
	}

	return false;
}

/**
 * Scheduled cleanup options
 *
 * @return array
 */
function scheduled_cleanup_frequency_options() {
	$options = [
		'daily'   => esc_html__( 'Daily', 'powered-cache' ),
		'weekly'  => esc_html__( 'Weekly', 'powered-cache' ),
		'monthly' => esc_html__( 'Monthly', 'powered-cache' ),
	];

	/**
	 * Filter scheduled cleanup options.
	 *
	 * @hook   powered_cache_scheduled_cleanup_frequency_options
	 *
	 * @param  {array} $options The list of supported schedules.
	 *
	 * @return {array} New value
	 * @since  2.0
	 */
	return apply_filters( 'powered_cache_scheduled_cleanup_frequency_options', $options );
}

/**
 * ports \settings_errors for SUI
 *
 * @param string $setting        Slug title of a specific setting
 * @param bool   $sanitize       Whether to re-sanitize the setting value before returning errors
 * @param bool   $hide_on_update Whether hide or not hide on update
 *
 * @see settings_errors
 */
function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {

	if ( $hide_on_update && ! empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$settings_errors = get_settings_errors( $setting, $sanitize );

	if ( empty( $settings_errors ) ) {
		return;
	}

	$output = '';

	foreach ( $settings_errors as $key => $details ) {
		if ( 'updated' === $details['type'] ) {
			$details['type'] = 'sui-notice-success';
		}

		if ( in_array( $details['type'], array( 'error', 'success', 'warning', 'info' ), true ) ) {
			$details['type'] = 'sui-notice-' . $details['type'];
		}

		$css_id = sprintf(
			'setting-error-%s',
			esc_attr( $details['code'] )
		);

		$css_class = sprintf(
			'sui-notice %s settings-error is-dismissible',
			esc_attr( $details['type'] )
		);

		$output .= "<div id='$css_id' class='$css_class'> \n";
		$output .= "<div class='sui-notice-content'><div class='sui-notice-message'>";
		$output .= "<span class='sui-notice-icon sui-icon-info sui-md' aria-hidden='true'></span>";
		$output .= "<p>{$details['message']}</p></div></div>";
		$output .= "</div> \n";
	}

	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * remove directories recursively
 *
 * Adopted from W3TC Utility
 *
 * @param string $path    The target path
 * @param array  $exclude list of the files that will excluded
 *
 * @return void
 * @since 1.2.5
 */
function remove_dir( $path, $exclude = array() ) {
	// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
	$dir = @opendir( $path );

	if ( $dir ) {
		while ( ( $entry = @readdir( $dir ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			foreach ( $exclude as $mask ) {
				if ( fnmatch( $mask, basename( $entry ) ) ) {
					continue 2;
				}
			}

			$full_path = $path . DIRECTORY_SEPARATOR . $entry;

			if ( @is_dir( $full_path ) ) {
				remove_dir( $full_path, $exclude );
			} else {
				@unlink( $full_path );
			}
		}

		@closedir( $dir );
		@rmdir( $path );
	}
	// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Get base caching directory of the site.
 *
 * @return mixed|void
 * @since 1.1
 */
function site_cache_dir() {
	$base_dir = get_page_cache_dir();

	// compatible with multisite
	$site_url = get_site_url();

	$site_url_parsed = wp_parse_url( $site_url );

	$site_path = $site_url_parsed['host'];

	if ( ! empty( $site_url_parsed['path'] ) ) {
		$site_path .= $site_url_parsed['path'];
	}

	$site_cache_dir = trailingslashit( $base_dir . $site_path );

	/**
	 * Filter get base caching directory of site
	 *
	 * @hook   powered_cache_site_cache_dir
	 *
	 * @param  {string} $site_cache_dir Site cache dir.
	 *
	 * @return {string} New value
	 * @since  1.1
	 */
	return apply_filters( 'powered_cache_site_cache_dir', $site_cache_dir );
}

/**
 * Page cache base directory.
 *
 * @return string
 * @since 1.1 $url parameter removed
 * @since 1.0
 */
function get_page_cache_dir() {
	$path = get_cache_dir() . 'powered-cache/';

	/**
	 * Filter page cache base directory.
	 *
	 * @hook   powered_cache_get_page_cache_dir
	 *
	 * @param  {string} $path Page cache dir
	 *
	 * @return {string} New value
	 * @since  1.0
	 */
	return apply_filters( 'powered_cache_get_page_cache_dir', $path );
}

/**
 * Clean up cache directory
 *
 * @return mixed
 * @since 1.0
 */
function clean_page_cache_dir() {
	remove_dir( get_page_cache_dir() );
}

/**
 * Clean cache base for the current site
 *
 * @return mixed
 * @since 1.1
 */
function clean_site_cache_dir() {
	$site_cache_dir = site_cache_dir();

	/**
	 * When deleting cache for the main site on multisite subdirectory setup
	 * Don't delete other site's cache
	 */
	if ( is_multisite() && ! is_subdomain_install() && is_main_site() ) {
		$base_dir    = get_page_cache_dir();
		$directories = glob( $site_cache_dir . '*', GLOB_ONLYDIR );
		$site_url    = get_site_url();

		$site_domain = wp_parse_url( $site_url, PHP_URL_HOST );
		foreach ( $directories as $directory ) {
			$dir_name  = str_replace( $base_dir . $site_domain, '', $directory );
			$site_info = get_site_by_path( $site_domain, $dir_name );
			if ( ! $site_info || is_main_site( $site_info->blog_id ) ) {
				remove_dir( $directory );
			}
		}
	} else {
		remove_dir( $site_cache_dir );
	}

	/**
	 * Fires after deleting site cache dir
	 *
	 * @hook  powered_cache_clean_site_cache_dir
	 *
	 * @param {string} $site_cache_dir The caching directory of the current site.
	 *
	 * @since 2.0
	 */
	do_action( 'powered_cache_clean_site_cache_dir', $site_cache_dir );
}


/**
 * Supported mobile browsers
 *
 * @return mixed|void
 * @since 1.0
 */
function mobile_browsers() {
	$mobile_browsers
		= '2.0 MMP, 240x320, 400X240, AvantGo, BlackBerry, Blazer, Cellphone, Danger, DoCoMo, Elaine/3.0, EudoraWeb, Googlebot-Mobile, hiptop, IEMobile, KYOCERA/WX310K, LG/U990, MIDP-2., MMEF20, MOT-V, NetFront, Newt, Nintendo Wii, Nitro, Nokia, Opera Mini, Palm, PlayStation Portable, portalmmm, Proxinet, ProxiNet, SHARP-TQ-GX10, SHG-i900, Small, SonyEricsson, Symbian OS, SymbianOS, TS21i-10, UP.Browser, UP.Link, webOS, Windows CE, WinWAP, YahooSeeker/M1A1-R2D2, iPhone, iPod, Android, BlackBerry9530, LG-TU915 Obigo, LGE VX, webOS, Nokia5800';

	/**
	 * Filters supported mobile browsers.
	 *
	 * @hook  powered_cache_mobile_browsers
	 *
	 * @param {string} $mobile_browsers Comma separated list of the defined mobile browsers.
	 *
	 * @since 1.0
	 */
	return apply_filters( 'powered_cache_mobile_browsers', $mobile_browsers );
}

/**
 * Supported mobile prefixes
 *
 * @return mixed|void
 * @since 1.0
 */
function mobile_prefixes() {
	$mobile_prefixes
		= 'w3c , w3c-, acs-, alav, alca, amoi, audi, avan, benq, bird, blac, blaz, brew, cell, cldc, cmd-, dang, doco, eric, hipt, htc_, inno, ipaq, ipod, jigs, kddi, keji, leno, lg-c, lg-d, lg-g, lge-, lg/u, maui, maxo, midp, mits, mmef, mobi, mot-, moto, mwbp, nec-, newt, noki, palm, pana, pant, phil, play, port, prox, qwap, sage, sams, sany, sch-, sec-, send, seri, sgh-, shar, sie-, siem, smal, smar, sony, sph-, symb, t-mo, teli, tim-, tosh, tsm-, upg1, upsi, vk-v, voda, wap-, wapa, wapi, wapp, wapr, webc, winw, winw, xda , xda-';

	/**
	 * Filters supported mobile prefixes.
	 *
	 * @hook  powered_cache_mobile_prefixes
	 *
	 * @param {string} $mobile_prefixes Comma separated list of the defined mobile prefixes.
	 *
	 * @since 1.0
	 */
	return apply_filters( 'powered_cache_mobile_prefixes', $mobile_prefixes );
}


/**
 * Collect post related urls
 *
 * @param int $post_id Post ID
 *
 * @return array
 * @since 1.0
 * @since 1.1 powered_cache_post_related_urls filter added
 */
function get_post_related_urls( $post_id ) {

	$current_post_status = get_post_status( $post_id );

	// array to collect all our URLs
	$related_urls = array();

	if ( get_permalink( $post_id ) ) {
		// we're going to add a ton of things to flush.

		// related category urls
		$categories = get_the_category( $post_id );
		if ( $categories ) {
			foreach ( $categories as $cat ) {
				array_push( $related_urls, get_category_link( $cat->term_id ) );
			}
		}

		// related tags url
		$tags = get_the_tags( $post_id );
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				array_push( $related_urls, get_tag_link( $tag->term_id ) );
			}
		}

		// Author URL
		array_push( $related_urls, get_author_posts_url( get_post_field( 'post_author', $post_id ) ), get_author_feed_link( get_post_field( 'post_author', $post_id ) ) );

		// Archives and their feeds
		if ( get_post_type_archive_link( get_post_type( $post_id ) ) === true ) {
			array_push( $related_urls, get_post_type_archive_link( get_post_type( $post_id ) ), get_post_type_archive_feed_link( get_post_type( $post_id ) ) );
		}

		// Post URL
		array_push( $related_urls, get_permalink( $post_id ) );

		// Also clean URL for trashed post.
		if ( 'trash' === $current_post_status ) {
			$trashpost = get_permalink( $post_id );
			$trashpost = str_replace( '__trashed', '', $trashpost );
			array_push( $related_urls, $trashpost, $trashpost . 'feed/' );
		}

		// Add in AMP permalink if Automattic's AMP is installed
		if ( function_exists( 'amp_get_permalink' ) ) {
			array_push( $related_urls, amp_get_permalink( $post_id ) );
		}

		// Regular AMP url for posts
		array_push( $related_urls, get_permalink( $post_id ) . 'amp/' );

		// Feeds
		array_push( $related_urls, get_bloginfo_rss( 'rdf_url' ), get_bloginfo_rss( 'rss_url' ), get_bloginfo_rss( 'rss2_url' ), get_bloginfo_rss( 'atom_url' ), get_bloginfo_rss( 'comments_rss2_url' ), get_post_comments_feed_link( $post_id ) );

		// Home Page and (if used) posts page
		array_push( $related_urls, trailingslashit( home_url() ) );
		if ( get_option( 'show_on_front' ) === 'page' ) {
			// Ensure we have a page_for_posts setting to avoid empty URL
			if ( get_option( 'page_for_posts' ) ) {
				array_push( $related_urls, get_permalink( get_option( 'page_for_posts' ) ) );
			}
		}
	}

	/**
	 * Filters post related urls.
	 *
	 * @hook  powered_cache_post_related_urls
	 *
	 * @param {array} $related_urls The list of the URLs that related with the post.
	 *
	 * @since 1.0
	 */
	$related_urls = apply_filters( 'powered_cache_post_related_urls', $related_urls );

	return $related_urls;
}


/**
 * Delete cache file
 *
 * @param string $url Target URL
 *
 * @return bool  true when found cache dir, otherwise false
 * @since 1.0
 */
function delete_page_cache( $url ) {

	$dir = trailingslashit( get_url_dir( trim( $url ) ) );

	if ( is_dir( $dir ) ) {
		$files = scandir( $dir );
		foreach ( $files as $file ) {
			/**
			 * Don't need to lookup for index-https, index-https-mobile etc..
			 * Just clean that directory's files only.
			 */
			if ( ! is_dir( $dir . $file ) && file_exists( $dir . $file ) && ! in_array( $file, array( '.', '..' ), true ) ) {
				unlink( $dir . $file );
			}
		}

		if ( file_exists( $dir ) && is_dir_empty( $dir ) ) {
			remove_dir( $dir );
		}

		return true;
	}

	return false;
}


/**
 * Get cache location of given url
 *
 * @param string $url The url to retrieve path
 *
 * @return mixed|void
 * @since 1.1
 */
function get_url_dir( $url ) {
	$url_info = wp_parse_url( $url );
	$sub_dir  = $url_info['host'];

	if ( ! empty( $url_info['path'] ) ) {
		$sub_dir .= $url_info['path'];
	}

	$path = trailingslashit( get_page_cache_dir() ) . ltrim( $sub_dir, '/' );

	/**
	 * Filters the path of the given url in the cache directory.
	 *
	 * @hook  powered_cache_get_url_dir
	 *
	 * @param {string} $path The cache directory of the given URL.
	 *
	 * @since 1.1
	 */
	return apply_filters( 'powered_cache_get_url_dir', $path );
}

/**
 * Prepare cdn addresses with hostname + zone
 *
 * @return mixed|void
 * @since 1.0
 */
function cdn_addresses() {
	$settings = get_settings(); // phpcs:ignore WordPress.WP.DeprecatedFunctions.get_settingsFound

	$hostnames = $settings['cdn_hostname'];
	$zones     = $settings['cdn_zone'];

	$cdn_addresses = array();
	foreach ( $hostnames as $host_key => $host ) {
		if ( filter_var( $host, FILTER_VALIDATE_URL ) ) {
			$host = wp_parse_url( $host, PHP_URL_HOST );
		}

		if ( ! empty( $host ) ) {
			$cdn_addresses[ $zones[ $host_key ] ][] = $host;
		}
	}

	/**
	 * Filters CDN Addresses.
	 *
	 * @hook   powered_cache_cdn_addresses
	 *
	 * @param  {array} $cdn_addresses CDN Adresses.
	 *
	 * @return {array} New value.
	 *
	 * @since  1.0
	 */
	return apply_filters( 'powered_cache_cdn_addresses', $cdn_addresses );
}


/**
 * Get list of expired files for given directory
 *
 * @param string $path     directory location
 * @param int    $lifespan lifespan in seconds
 *
 * @return array expired file list
 * @since 1.1
 */
function get_expired_files( $path, $lifespan = 0 ) {

	$current_time = time();

	$expired_files = array();

	// return immediately if the path is not exist!
	if ( ! file_exists( $path ) ) {
		return $expired_files;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

	foreach ( $files as $file ) {

		if ( $file->isDir() ) {
			continue;
		}

		$path = $file->getPathname();

		if ( @filemtime( $path ) + $lifespan <= $current_time ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$expired_files[] = $path;
		}
	}

	return $expired_files;
}


/**
 * Flush object cache and clean cache directory
 *
 * @since 1.0
 */
function powered_cache_flush() {
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	remove_dir( get_cache_dir() );

	/**
	 * Fires after cache flush.
	 *
	 * @hook   powered_cache_flushed
	 *
	 * @since  1.0
	 */
	do_action( 'powered_cache_flushed' );
}

/**
 * Log to stdout or a file
 *
 * @param string $message Log message
 *
 * @return bool
 */
function log( $message ) {
	if ( ! defined( 'POWERED_CACHE_ENABLE_LOG' ) ) {
		return false;
	}

	if ( ! POWERED_CACHE_ENABLE_LOG ) {
		return false;
	}

	$log_message = gmdate( 'H:i:s' ) . ' ' . getmypid() . ' ' . get_client_ip() . " {$message}" . PHP_EOL;

	/**
	 * Filters log message.
	 *
	 * @hook   powered_cache_log_message
	 *
	 * @param  {string} $log_message The log message.
	 *
	 * @return {string} New value.
	 *
	 * @since  2.0
	 */
	$log_message = apply_filters( 'powered_cache_log_message', $log_message );

	/**
	 * Filters log message type.
	 *
	 * @hook   powered_cache_log_message_type
	 *
	 * @param  {null|int} null default message type
	 *
	 * @return {null|int} New value.
	 *
	 * @since  2.0
	 */
	$message_type = apply_filters( 'powered_cache_log_message_type', null );
	$destination  = null;

	if ( defined( 'POWERED_CACHE_LOG_FILE' ) ) {
		$destination  = POWERED_CACHE_LOG_FILE;
		$message_type = 3;
	}

	/**
	 * Filters destination of the log.
	 *
	 * @hook   powered_cache_log_destination
	 *
	 * @param  {null|string} $destination The destination of the log.
	 *
	 * @return {null|string} New value.
	 *
	 * @since  2.0
	 */
	$log_destination = apply_filters( 'powered_cache_log_destination', $destination );

	// don't log anything when it used for particular IP address
	if ( defined( 'POWERED_CACHE_LOG_IP' ) && POWERED_CACHE_LOG_IP !== get_client_ip() ) {
		return false;
	}

	return error_log( $log_message, $message_type, $log_destination ); // phpcs:ignore
}

/**
 * Get client raw ip
 *
 * @return mixed
 */
function get_client_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}

	return $_SERVER['REMOTE_ADDR'];
}

/**
 * Fragment caching
 *
 * @link  https://gist.github.com/markjaquith/2653957
 * @see   https://gist.github.com/westonruter/5475349
 *
 * @param string   $key      Fragment key
 * @param int      $ttl      Cache TTL
 * @param callable $function callback
 *
 * @throws \Exception Exception
 * @since 1.2
 * @since 2.0 Renamed powered_cache_fragment -> \PoweredCache\Utils\cache_fragment
 */
function cache_fragment( $key, $ttl, $function ) {
	$group  = 'powered-fragments';
	$output = wp_cache_get( $key, $group );
	if ( empty( $output ) ) {
		ob_start();
		call_user_func( $function );
		$output = ob_get_clean();
		wp_cache_add( $key, $output, $group, $ttl );
	}
	echo $output; // phpcs:ignore
}

/**
 * Fetches known headers, ported from WP Super Cache but not using apache_response_headers
 *
 * @return array|false
 * @since 1.2
 */
function get_response_headers() {
	static $known_headers = array(
		'Access-Control-Allow-Origin',
		'Accept-Ranges',
		'Age',
		'Allow',
		'Cache-Control',
		'Connection',
		'Content-Encoding',
		'Content-Language',
		'Content-Length',
		'Content-Location',
		'Content-MD5',
		'Content-Disposition',
		'Content-Range',
		'Content-Type',
		'Date',
		'ETag',
		'Expires',
		'Last-Modified',
		'Link',
		'Location',
		'P3P',
		'Pragma',
		'Proxy-Authenticate',
		'Referrer-Policy',
		'Refresh',
		'Retry-After',
		'Server',
		'Status',
		'Strict-Transport-Security',
		'Trailer',
		'Transfer-Encoding',
		'Upgrade',
		'Vary',
		'Via',
		'Warning',
		'WWW-Authenticate',
		'X-Frame-Options',
		'Public-Key-Pins',
		'X-XSS-Protection',
		'Content-Security-Policy',
		'X-Pingback',
		'X-Content-Security-Policy',
		'X-WebKit-CSP',
		'X-Content-Type-Options',
		'X-Powered-By',
		'X-UA-Compatible',
		'X-Robots-Tag',
	);

	/**
	 * Filters known headers.
	 *
	 * @hook   powered_cache_known_headers
	 *
	 * @param  {array} $known_headers The list of known HTTP headers.
	 *
	 * @return {array} New value.
	 *
	 * @since  1.2
	 */
	$known_headers = apply_filters( 'powered_cache_known_headers', $known_headers );

	if ( ! isset( $known_headers['age'] ) ) {
		$known_headers = array_map( 'strtolower', $known_headers );
	}

	$headers = array();

	if ( function_exists( 'headers_list' ) ) {
		$headers = array();
		foreach ( headers_list() as $hdr ) {
			$header_parts = explode( ':', $hdr, 2 );
			$header_name  = isset( $header_parts[0] ) ? trim( $header_parts[0] ) : '';
			$header_value = isset( $header_parts[1] ) ? trim( $header_parts[1] ) : '';

			$headers[ $header_name ] = $header_value;
		}
	}

	foreach ( $headers as $key => $value ) {
		if ( ! in_array( strtolower( $key ), $known_headers, true ) ) {
			unset( $headers[ $key ] );
		}
	}

	return $headers;
}


/**
 * Check if the given url exists in the cache
 *
 * @param string $url       URL
 * @param bool   $is_mobile Check mobile cache
 * @param bool   $is_gzip   check gzipped cache
 *
 * @return bool
 */
function is_url_cached( $url, $is_mobile = false, $is_gzip = false ) {
	$file_name = 'index';
	$url_parts = wp_parse_url( $url );

	if ( 'https' === $url_parts['scheme'] ) {
		$file_name .= '-https';
	}

	if ( $is_mobile ) {
		$file_name .= '-mobile';
	}

	$file_name .= '.html';

	if ( $is_gzip ) {
		$file_name .= '.gz';
	}

	$rel_path = $url_parts['host'];
	if ( ! empty( $url_parts['path'] ) ) {
		$rel_path .= $url_parts['path'];
	}

	$path = trailingslashit( get_page_cache_dir() . $rel_path );

	$cache_file = $path . $file_name;

	return file_exists( $cache_file );
}

/**
 * Check if the permalink structure of the site end with trailingslash
 *
 * @return bool
 * @since 2.0
 */
function permalink_structure_has_trailingslash() {
	if ( '/' === substr( get_option( 'permalink_structure' ), - 1 ) ) {
		return true;
	}

	return false;
}

/**
 * Check if the given directory empty
 *
 * @param string $dir Path
 *
 * @return bool
 */
function is_dir_empty( $dir ) {
	foreach ( new \DirectoryIterator( $dir ) as $file_info ) {
		if ( $file_info->isDot() ) {
			continue;
		}

		return false;
	}

	return true;
}

/**
 * Get the documentation url
 *
 * @param string $path     The path of documentation
 * @param string $fragment URL Fragment
 *
 * @return string final URL
 */
function get_doc_url( $path = null, $fragment = '' ) {
	$doc_site       = 'https://docs.poweredcache.com/';
	$utm_parameters = '?utm_source=wp_admin&utm_medium=plugin&utm_campaign=settings_page';

	if ( ! empty( $path ) ) {
		$doc_site .= ltrim( $path, '/' );
	}

	$doc_url = trailingslashit( $doc_site ) . $utm_parameters;

	if ( ! empty( $fragment ) ) {
		$doc_url .= '#' . $fragment;
	}

	return $doc_url;
}

/**
 * Sanitize CSS
 *
 * @param string $css Input
 *
 * @return string|string[] $css
 * @since 2.1
 */
function sanitize_css( $css ) {
	$css = wp_strip_all_tags( $css );

	if ( false !== strpos( $css, '<' ) ) {
		$css = preg_replace( '#<(\/?\w+)#', '\00003C$1', $css );
	}

	return $css;
}


/**
 *  Test if the current browser runs on a mobile device (smart phone, tablet, etc.)
 *  Sort of custom version of wp_is_mobile
 */
function powered_cache_is_mobile() {

	global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

	$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_browsers ) ), ' ' );
	$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_prefixes ) ), ' ' );

	if ( ( preg_match( '#^.*(' . $mobile_browsers . ').*#i', $_SERVER['HTTP_USER_AGENT'] ) || preg_match( '#^(' . $mobile_prefixes . ').*#i', substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) ) ) ) {
		return true;
	}

	return false;
}

/**
 * If the site is a local site.
 *
 * @return bool
 * @since 2.2
 */
function is_local_site() {
	$site_url = site_url();

	// Check for localhost and sites using an IP only first.
	$is_local = $site_url && false === strpos( $site_url, '.' );

	// Use Core's environment check, if available. Added in 5.5.0 / 5.5.1 (for `local` return value).
	if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
		$is_local = true;
	}

	// Then check for usual usual domains used by local dev tools.
	$known_local = array(
		'#\.local$#i',
		'#\.localhost$#i',
		'#\.test$#i',
		'#\.docksal$#i',      // Docksal.
		'#\.docksal\.site$#i', // Docksal.
		'#\.dev\.cc$#i',       // ServerPress.
		'#\.lndo\.site$#i',    // Lando.
	);

	if ( ! $is_local ) {
		foreach ( $known_local as $url ) {
			if ( preg_match( $url, $site_url ) ) {
				$is_local = true;
				break;
			}
		}
	}

	/**
	 * Filters is_local_site check.
	 *
	 * @param bool $is_local If the current site is a local site.
	 *
	 * @since 2.2
	 */
	$is_local = apply_filters( 'powered_cache_is_local_site', $is_local );

	return $is_local;
}

/**
 * Check whether request for bypass or process normally
 *
 * @return bool
 * @since 3.0
 */
function bypass_request() {
	if ( isset( $_GET['nopoweredcache'] ) && $_GET['nopoweredcache'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}

	return false;
}
