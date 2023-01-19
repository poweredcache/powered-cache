<?php
/**
 * Compat with Divi Builder
 *
 * @package PoweredCache\Compat
 * @link    https://www.elegantthemes.com/
 */

namespace PoweredCache\Compat\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.5
 */
if ( ! empty( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'powered_cache_fo_disable', '__return_true' );
}
