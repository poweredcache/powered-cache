<?php
function powered_cache_lazy_load_compat_bp() {
	if ( function_exists( 'bp_is_my_profile' ) && bp_is_my_profile() ) {
		add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'powered_cache_lazy_load_compat', 'powered_cache_lazy_load_compat_bp' );