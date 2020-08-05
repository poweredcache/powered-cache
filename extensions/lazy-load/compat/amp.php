<?php
/**
 * Lazy-load AMP compat
 *
 * @package PoweredCache
 */

/**
 * Disable lady-load on AMP pages
 */
function powered_cache_lazy_load_compat_amp() {
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'powered_cache_lazy_load_compat', 'powered_cache_lazy_load_compat_amp' );
