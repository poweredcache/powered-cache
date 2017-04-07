<?php

function powered_cache_lazy_load_compat_mobilepress() {
	if ( function_exists( 'mopr_get_option' ) && WP_CONTENT_DIR . mopr_get_option( 'mobile_theme_root', 1 ) == get_theme_root() ) {
		add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'powered_cache_lazy_load_compat', 'powered_cache_lazy_load_compat_mobilepress' );
