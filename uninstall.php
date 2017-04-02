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

global $wp_filesystem;

// clean cache
pc_flush();

// delete settings
delete_option( 'powered_cache_settings' );
// delete preloader runtime option, just in case
delete_option( 'pc_preload_runtime_option' );

// turn off page cache
PC_Config::factory()->define_wp_cache( false );

// delete object cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' ) ) {
	$wp_filesystem->delete( untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php' );
}

// delete advanced cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
	$wp_filesystem->delete( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
}

// delete cache directory
if ( file_exists( pc_get_cache_dir() ) ) {
	$wp_filesystem->delete( pc_get_cache_dir(), true );
}

// delete configuration files
if ( file_exists( WP_CONTENT_DIR . '/pc-config' ) ) {
	$wp_filesystem->delete( WP_CONTENT_DIR . '/pc-config', true );
}


// remove cron tasks
wp_clear_scheduled_hook( 'pc_preload_hook' );
wp_clear_scheduled_hook( 'pc_preload_child_process' );
wp_clear_scheduled_hook( 'pc_purge_cache' );

