<?php

function powered_cache_lazy_load_compat_operamini() {
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' ) ) {
		add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'powered_cache_lazy_load_compat', 'powered_cache_lazy_load_compat_operamini' );
