<?php
/**
 * Compat with PhastPress plugin
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/phastpress/
 * @since   3.0.4
 */

namespace PoweredCache\Compat\PhastPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'PHASTPRESS_VERSION' ) ) {
	return;
}


add_action( 'powered_cache_flushed', __NAMESPACE__ . '\\flush_phast_cache' );
add_action( 'powered_cache_purge_all_cache', __NAMESPACE__ . '\\flush_phast_cache' );
add_action( 'powered_cache_clean_site_cache_dir', __NAMESPACE__ . '\\flush_phast_cache' );

/**
 * Flush PhastPress cache db
 *
 * @return void
 */
function flush_phast_cache() {
	$cache_dir = WP_CONTENT_DIR . '/cache/';

	$db_files = glob( $cache_dir . 'phast.*/*sqlite3*' );
	foreach ( $db_files as $file ) {
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
