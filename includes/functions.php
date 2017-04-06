<?php
/**
 * Common functions
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Get all settings, if db hasn't settings options set defaults.
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_get_settings() {
	$settings = get_option( 'powered_cache_settings' );

	if ( empty( $settings ) ) {
		$settings = PC_Config::factory()->default_settings();
		update_option( 'powered_cache_settings', $settings );
	}

	return apply_filters( 'pc_get_settings', $settings );
}


/**
 * Get single settings item of plugin
 *
 * @since 1.0
 * @param string     $key
 * @param bool|false $default
 *
 * @return mixed|void
 */
function pc_get_option( $key = '', $default = false ) {
	global $powered_cache_options;
	$value = ! empty( $powered_cache_options[ $key ] ) ? $powered_cache_options[ $key ] : $default;
	$value = apply_filters( 'pc_get_option', $value, $key, $default );

	return apply_filters( 'pc_get_option_' . $key, $value, $key, $default );
}


/**
 * Flush object cache and clean cache directory
 *
 * @since 1.0
 */
function pc_flush() {
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	pc_clean_page_cache_dir();
	do_action( 'pc_flushed' );
}


/**
 * Save powered cache settings, update global settings variable and write to file
 *
 * @since 1.0
 * @param $old_settings array
 * @param $new_settings array
 */
function pc_save_settings( $old_settings, $new_settings ) {
	global $powered_cache_options;
	$settings  = array_merge( $old_settings, $new_settings );

	$powered_cache_options = $settings;

	$changed_settings = array_diff_assoc( array_map( 'serialize', $settings ), array_map( 'serialize', $old_settings ) );

	update_option( 'powered_cache_settings', $settings );

	if ( isset( $changed_settings['object_cache'] ) && function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	if ( isset( $changed_settings['enable_page_caching'] ) ) {
		pc_clean_page_cache_dir();
	}

	PC_Config::factory()->setup_object_cache( $settings['object_cache'] );
	PC_Config::factory()->setup_page_cache( $settings['enable_page_caching'] );

	do_action( 'pc_settings_saved', $settings );

	unset( $settings['extension_settings'] );
	PC_Config::factory()->save_to_file( $settings );

}

/**
 * Prepare regex string for browser detetch
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_mobile_browsers_regex(){
	$browsers = pc_mobile_browsers();
	$regex_str = addcslashes( implode( '|', explode( ',', $browsers ) ),' ');

	return apply_filters( 'pc_mobile_browsers_regex', $regex_str, $browsers );
}

/**
 * Prepare regex string for mobile prefix
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_mobile_prefixes_regex(){
	$prefixes = pc_mobile_prefixes();
	$regex_str = addcslashes( implode( '|', explode( ',', $prefixes ) ),' ');

	return apply_filters( 'pc_mobile_prefixes_regex', $regex_str, $prefixes );
}

/**
 * Supported mobile browsers
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_mobile_browsers() {
	$mobile_browsers = '2.0 MMP, 240x320, 400X240, AvantGo, BlackBerry, Blazer, Cellphone, Danger, DoCoMo, Elaine/3.0, EudoraWeb, Googlebot-Mobile, hiptop, IEMobile, KYOCERA/WX310K, LG/U990, MIDP-2., MMEF20, MOT-V, NetFront, Newt, Nintendo Wii, Nitro, Nokia, Opera Mini, Palm, PlayStation Portable, portalmmm, Proxinet, ProxiNet, SHARP-TQ-GX10, SHG-i900, Small, SonyEricsson, Symbian OS, SymbianOS, TS21i-10, UP.Browser, UP.Link, webOS, Windows CE, WinWAP, YahooSeeker/M1A1-R2D2, iPhone, iPod, Android, BlackBerry9530, LG-TU915 Obigo, LGE VX, webOS, Nokia5800';

	return apply_filters( 'pc_mobile_browsers', $mobile_browsers );
}


/**
 * Supported mobile prefixes
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_mobile_prefixes() {
	$mobile_prefixes = 'w3c , w3c-, acs-, alav, alca, amoi, audi, avan, benq, bird, blac, blaz, brew, cell, cldc, cmd-, dang, doco, eric, hipt, htc_, inno, ipaq, ipod, jigs, kddi, keji, leno, lg-c, lg-d, lg-g, lge-, lg/u, maui, maxo, midp, mits, mmef, mobi, mot-, moto, mwbp, nec-, newt, noki, palm, pana, pant, phil, play, port, prox, qwap, sage, sams, sany, sch-, sec-, send, seri, sgh-, shar, sie-, siem, smal, smar, sony, sph-, symb, t-mo, teli, tim-, tosh, tsm-, upg1, upsi, vk-v, voda, wap-, wapa, wapi, wapp, wapr, webc, winw, winw, xda , xda-';

	return apply_filters( 'pc_mobile_prefixes', $mobile_prefixes );
}


/**
 * Prepare cdn addresses with hostname + zone
 *
 * @since 1.0
 * @return mixed|void
 */
function pc_cdn_addresses() {
	global $powered_cache_options;

	$hostnames = $powered_cache_options['cdn_hostname'];
	$zones     = $powered_cache_options['cdn_zone'];

	$cdn_addresses = array();
	foreach ( $hostnames as $host_key => $host ) {
		if ( ! empty( $host ) ) {
			$cdn_addresses[ $zones[ $host_key ] ][] = $host;
		}
	}

	return apply_filters( 'pc_cdn_addresses', $cdn_addresses );
}


/**
 * Check premium version running
 * This is just simple helper function, our premium checks are more strict than your thoughts :)
 *
 * @since 1.0
 * @return bool
 */
function is_powered_cache_premium() {
	if ( defined( 'POWERED_CACHE_PREMIUM' ) && true === POWERED_CACHE_PREMIUM ) {
		return true;
	}

	return false;
}


function maybe_require_powered_cache_premium_html() {

	if ( ! is_powered_cache_premium() ) {?>
		<div class="<?php echo( ! is_powered_cache_premium() ? 'need-upgrade' : '' ); ?>">
			<span class="upgrade-msg"><?php echo __( 'This feature available only premium users', 'powered-cache' ); ?></span>
		</div>
	<?php
	}
}


/**
 * get page cache dir
 *
 * @param string $url
 * @since 1.0
 *
 * @return string
 */
function pc_get_page_cache_dir( $url = false ) {
	if ( $url ) {
		$url_info = parse_url( $url );
		$sub_dir  = $url_info['host'] . $url_info['path'];

		return pc_get_cache_dir() . 'powered-cache/' . ltrim( $sub_dir, '/' );
	}

	return pc_get_cache_dir() . 'powered-cache/';
}

/**
 * Delete cache file
 *
 * @param string $url
 *
 * @since 1.0
 * @return bool  true when found cache dir, otherwhise false
 */
function pc_delete_page_cache( $url ) {

	$dir = trailingslashit( pc_get_page_cache_dir( $url ) );

	if ( is_dir( $dir ) ) {
		$files = scandir( $dir );
		foreach ( $files as $file ) {
			/**
			 * Don't need to lookup for index-https, index-https-mobile etc..
			 * Just clean that directory's files only.
			 */
			if ( ! is_dir( $dir . $file ) && ! in_array( $file, array( '.', '..' ) ) ) {
				unlink( $dir . $file );
			}
		}

		return true;
	}

	return false;
}

/**
 * Get all settings for plugin
 *
 * @since 1.0
 * @param $extension_id
 *
 * @return bool
 */
function pc_get_extension_settings( $extension_id ) {
	$extension_settings = pc_get_option( 'extension_settings' );

	if ( ! is_array( $extension_settings ) ) {
		return false;
	}

	if ( array_key_exists( $extension_id, $extension_settings ) ) {
		return $extension_settings[ $extension_id ];
	}

	return false;
}


/**
 * Update options of extension
 * @since 1.0
 * @param       $extension_id
 * @param array $settings
 *
 * @return bool
 */
function pc_update_extension_option( $extension_id, $settings = array() ) {
	$options = get_option( 'powered_cache_settings' );

	$options['extension_settings'][ $extension_id ] = $settings;

	$options_updated = update_option( 'powered_cache_settings', $options );

	do_action( 'pc_extension_option_updated', $options );

	return $options_updated;
}


/**
 * Get single option of plugin. Generally used for fields
 *
 * @since 1.0
 * @param            $extension_id
 * @param string     $option_name
 * @param bool|false $default
 *
 * @return bool
 */
function pc_get_extension_option( $extension_id, $option_name = '', $default = false ) {
	$option = pc_get_extension_settings( $extension_id );

	if ( is_array( $option ) && array_key_exists( $option_name, $option ) ) {
		return $option[ $option_name ];
	}

	return $default;
}

/**
 * return base caching dir
 * use this function to get base caching directory instead of directly calling constant
 *
 * @since 1.0
 * @return string path
 */
function pc_get_cache_dir() {
	if ( defined( 'PC_CACHE_DIR' ) ) {
		return PC_CACHE_DIR;
	}

	return WP_CONTENT_DIR . '/cache/';
}


/**
 * Clean up cache directory
 *
 * @since 1.0
 * @return mixed
 */
function pc_clean_page_cache_dir() {
	global $wp_filesystem;

	return $wp_filesystem->rmdir( untrailingslashit( pc_get_cache_dir() ) . '/powered-cache', true );
}



/**
 * Collect post related urls
 *
 * @since 1.0
 * @param $post_id
 *
 * @return array
 */
function pc_get_post_related_urls( $post_id ) {

	$current_post_status = get_post_status( $post_id );

	// array to collect all our URLs
	$related_urls = array();

	if ( get_permalink( $post_id ) == true ) {
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
		if ( get_post_type_archive_link( get_post_type( $post_id ) ) == true ) {
			array_push( $related_urls, get_post_type_archive_link( get_post_type( $post_id ) ), get_post_type_archive_feed_link( get_post_type( $post_id ) ) );
		}

		// Post URL
		array_push( $related_urls, get_permalink( $post_id ) );

		// Also clean URL for trashed post.
		if ( $current_post_status == "trash" ) {
			$trashpost = get_permalink( $post_id );
			$trashpost = str_replace( "__trashed", "", $trashpost );
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
		if ( get_option( 'show_on_front' ) == 'page' ) {
			// Ensure we have a page_for_posts setting to avoid empty URL
			if ( get_option( 'page_for_posts' ) ) {
				array_push( $related_urls, get_permalink( get_option( 'page_for_posts' ) ) );
			}
		}
	}

	return $related_urls;
}


/**
 * Get site related info. This could help us to debug.
 *
 * @since 1.0
 * @return array
 */
function pc_get_debug_info() {
	global $wpdb;
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active_plugins_option = get_option( 'active_plugins' );
	$active_plugins        = array();
	$plugins               = get_plugins();

	foreach ( $active_plugins_option as $aplugin ) {
		$active_plugins[ $plugins[ $aplugin ]['Name'] ] = array(
			'file' => $aplugin,
			'Name'    => $plugins[ $aplugin ]['Name'],
			'Version' => $plugins[ $aplugin ]['Version'],
		);
	}


	$theme = wp_get_theme();

	$theme_info = array(
		'Name'     => $theme->get( 'Name' ),
		'Version'  => $theme->get( 'Version' ),
		'ThemeURI' => $theme->get( 'ThemeURI' ),
	);


	$mysql_version = $wpdb->get_var( 'select version() as mysqlversion' );
	$php_version   = phpversion();

	$debug_info['php_version']   = $php_version;
	$debug_info['mysql_version'] = $mysql_version;
	$debug_info['active_plugins'] = $active_plugins;
	$debug_info['active_theme'] = $theme_info;
	$debug_info['is_multisite']  = is_multisite();

	if ( is_multisite() && function_exists( 'is_subdomain_install' ) ) {
		$debug_info['is_subdomain_install'] = is_subdomain_install();
	}

	return $debug_info;
}


/**
 * Is saving options now?
 *
 * @since 1.0
 * @return bool
 */
function pc_is_saving_options() {
	if ( defined( 'PC_SAVING_OPTIONS' ) && true === PC_SAVING_OPTIONS ) {
		return true;
	}

	return false;
}


if ( ! function_exists( 'boolval' ) ) {
	/**
	 * For compatible reason.
	 * If we create compat file someday this function should move there
	 *
	 * @since 1.0
	 * @param $val
	 *
	 * @return bool
	 */
	function boolval( $val ) {
		return (bool) $val;
	}
}