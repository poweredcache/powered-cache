<?php
/**
 * Compat with BricksBuilder
 *
 * @package PoweredCache\Compat
 * @link    https://bricksbuilder.io/
 */

namespace PoweredCache\Compat\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Don't use file optimizer when the page on builder mode.
 *
 * @since 3.2
 */
if ( isset( $_GET['bricks'] ) || isset( $_GET['bricks_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'powered_cache_fo_disable', '__return_true' );
}
