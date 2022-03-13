<?php
/**
 * Compat with Jetpack Boost
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/jetpack-boost
 * @since   2.1
 */

namespace PoweredCache\Compat\JetpackBoost;

use function PoweredCache\Compat\add_conflict_message;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'JETPACK_BOOST_VERSION' ) ) {
	return;
}

$boost_config = get_option( 'jetpack_boost_config' );

if ( class_exists( '\Automattic\Jetpack_Boost\Modules\Lazy_Images\Lazy_Images' ) && ! empty( $boost_config['lazy-images']['enabled'] ) ) {
	add_action( 'powered_cache_admin_page_before_media_optimization', __NAMESPACE__ . '\\add_notice' );

	/**
	 * Add notice
	 */
	function add_notice() {
		add_conflict_message( 'Jetpack Boost', esc_html__( 'lazy load', 'powered-cache' ) );
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
