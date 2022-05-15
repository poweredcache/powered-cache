<?php
/**
 * Compat with Cornerstone builder
 *
 * @package PoweredCache\Compat
 * @link    https://codecanyon.net/item/cornerstone-the-wordpress-page-builder/15518868
 */

namespace PoweredCache\Compat\CornerstoneBuilder;

/**
 * Cornerstone builder escapes form is_admin() checks
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.2
 */
if ( false !== stripos( $_SERVER['REQUEST_URI'], '/cornerstone/' ) ) {
	add_filter( 'powered_cache_fo_disable', '__return_true' );
}
