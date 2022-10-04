<?php
/**
 * Uninstall Powered Cache
 * Deletes all plugin related data and configurations
 *
 * @package PoweredCache
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once 'powered-cache.php';

// flush cache
\PoweredCache\Utils\powered_cache_flush();

if ( is_multisite() ) {
	$sites = get_sites();
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		\PoweredCache\Utils\log( sprintf( 'Uninstalling site %s', $site->blog_id ) );
		powered_cache_uninstall_site();
		restore_current_blog();
	}
} else {
	\PoweredCache\Utils\log( sprintf( 'Uninstalling...' ) );
	powered_cache_uninstall_site();
}

\PoweredCache\Config::factory()->define_wp_cache( false );

$object_cache_file = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';

// delete object cache file
if ( file_exists( $object_cache_file ) && false !== strpos( file_get_contents( $object_cache_file ), 'POWERED_OBJECT_CACHE' ) ) {
	\PoweredCache\Utils\log( sprintf( 'Removing: %s', 'object-cache.php' ) );

	unlink( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' );
}

// delete advanced cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
	\PoweredCache\Utils\log( sprintf( 'Removing: %s', 'advanced-cache.php' ) );

	unlink( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
}

// delete cache directory
if ( file_exists( \PoweredCache\Utils\get_cache_dir() ) ) {
	\PoweredCache\Utils\log( sprintf( 'Removing dir: %s', \PoweredCache\Utils\get_cache_dir() ) );
	\PoweredCache\Utils\remove_dir( \PoweredCache\Utils\get_cache_dir() );
}

// delete configuration files
if ( file_exists( WP_CONTENT_DIR . '/pc-config' ) ) {
	\PoweredCache\Utils\log( 'Removing config dir...' );
	\PoweredCache\Utils\remove_dir( WP_CONTENT_DIR . '/pc-config' );
}

/**
 * Uninstall Powered Cache
 *
 * @since 2.0
 */
function powered_cache_uninstall_site() {
	// delete network settings
	delete_site_option( \PoweredCache\Constants\SETTING_OPTION );
	delete_site_option( \PoweredCache\Constants\DB_VERSION_OPTION_NAME );

	// delete site settings
	delete_option( \PoweredCache\Constants\SETTING_OPTION );
	delete_option( \PoweredCache\Constants\DB_VERSION_OPTION_NAME );

	// remove cron tasks
	wp_clear_scheduled_hook( \PoweredCache\Constants\PURGE_CACHE_CRON_NAME );
	wp_clear_scheduled_hook( \PoweredCache\Constants\PURGE_FO_CRON_NAME );
}
