<?php
/**
 * Compat with bj-lazy-load
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/bj-lazy-load/
 */

namespace PoweredCache\Compat\BJLazyLoad;

use function PoweredCache\Compat\add_conflict_message;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( '\bj_lazy_load' ) ) {

	add_action( 'powered_cache_admin_page_before_media_optimization', __NAMESPACE__ . '\\add_notice' );

	/**
	 * Add notice to media optimization section
	 */
	function add_notice() {
		add_conflict_message( 'BJ Lazy Load', esc_html__( 'lazy load', 'powered-cache' ) );
	}

	/**
	 * Disable UI options on admin settings
	 */
	add_filter(
		'powered_cache_admin_page_lazy_load_settings_classes',
		function ( $classes ) {
			return $classes . ' sui-disabled';
		}
	);

	// disable lazy-load related functionalities
	add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
	add_filter( 'powered_cache_disable_native_lazyload', '__return_false' );
}
