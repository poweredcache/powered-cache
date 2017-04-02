<?php
function pc_lazy_load_compat_bp() {
	if ( function_exists( 'bp_is_my_profile' ) && bp_is_my_profile() ) {
		add_filter( 'pc_lazy_load_enabled', '__return_false' );
	}
}

add_action( 'pc_lazy_load_compat', 'pc_lazy_load_compat_bp' );