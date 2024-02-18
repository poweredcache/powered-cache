<?php
/**
 * Compat with ShortPixel Adaptive Images
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/shortpixel-adaptive-images/
 */

namespace PoweredCache\Compat\ShortPixelAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'ShortPixelAI' ) ) {
	return;
}

add_filter( 'powered_cache_delay_exclusions', __NAMESPACE__ . '\\delay_exclusions' );

/**
 * Add ShortPixel Adaptive Images to the delay exclusions
 *
 * @param array $exclusions Excluded files
 *
 * @return array
 * @since 3.4.2
 */
function delay_exclusions( $exclusions ) {
	$exclusions[] = 'spai_js';

	return $exclusions;
}
