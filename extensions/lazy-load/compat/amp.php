<?php

function pc_lazy_load_compat_amp() {
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		add_filter( 'pc_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'pc_lazy_load_compat', 'pc_lazy_load_compat_amp' );
