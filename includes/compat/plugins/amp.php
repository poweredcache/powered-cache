<?php
/**
 * Compat with AMP
 *
 * @package PoweredCache\Compat
 */

namespace PoweredCache\Compat\AMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'AMP__VERSION' ) ) {
	return;
}

/**
 * Skip delayed JS for AMP
 *
 * @param bool $is_delay_skipped Whether to skip delayed JS.
 *
 * @return mixed|true
 * @since 3.5.3
 */
function skip_delayed_js( $is_delay_skipped ) {
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		$is_delay_skipped = true;
	}

	return $is_delay_skipped;
}

add_filter( 'powered_cache_delayed_js_skip', __NAMESPACE__ . '\\skip_delayed_js' );
