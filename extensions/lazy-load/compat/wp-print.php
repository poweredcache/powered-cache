<?php

function powered_cache_lazy_load_compat_wpprint() {
	if ( 1 == intval( get_query_var( 'print' ) ) || 1 == intval( get_query_var( 'printpage' ) ) ) {
		add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'powered_cache_lazy_load_compat', 'powered_cache_lazy_load_compat_wpprint' );
