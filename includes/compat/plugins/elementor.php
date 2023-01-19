<?php
/**
 * Compat with Elementor Builder
 *
 * @package PoweredCache\Compat
 * @link    https://elementor.com/
 */

namespace PoweredCache\Compat\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.5
 */
if ( ! empty( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'powered_cache_fo_disable', '__return_true' );
}
