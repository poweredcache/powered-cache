<?php

function pc_lazy_load_compat_wptouch() {
	if ( function_exists( 'bnc_wptouch_is_mobile' ) || defined( 'WPTOUCH_VERSION' ) ) {
		add_filter( 'pc_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'pc_lazy_load_compat', 'pc_lazy_load_compat_wptouch' );
