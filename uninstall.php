<?php
/**
 * Uninstall Powered Cache
 * Deletes all plugin related data and configurations
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

include_once( 'powered-cache.php' );

global $powered_cache_fs;

// clean cache
powered_cache_flush();

// delete settings
delete_option( 'powered_cache_settings' );
// delete preloader runtime option, just in case
delete_option( 'powered_cache_preload_runtime_option' );

// turn off page cache
Powered_Cache_Config::factory()->define_wp_cache( false );

// delete object cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' ) ) {
	$powered_cache_fs->delete( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' );
}

// delete advanced cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
	$powered_cache_fs->delete( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
}

// delete cache directory
if ( file_exists( powered_cache_get_cache_dir() ) ) {
	$powered_cache_fs->delete( powered_cache_get_cache_dir(), true );
}

// delete configuration files
if ( file_exists( WP_CONTENT_DIR . '/pc-config' ) ) {
	$powered_cache_fs->delete( WP_CONTENT_DIR . '/pc-config', true );
}


// remove cron tasks
wp_clear_scheduled_hook( 'powered_cache_preload_hook' );
wp_clear_scheduled_hook( 'powered_cache_preload_child_process' );
wp_clear_scheduled_hook( 'powered_cache_purge_cache' );

