<?php
/**
 * Compat with WooCommerce
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/woocommerce/
 * @since   3.0
 */

namespace PoweredCache\Compat\WooCommerce;

use PoweredCache\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WooCommerce' ) ) {
	add_filter( 'powered_cache_cache_query_strings', __NAMESPACE__ . '\\maybe_add_version_query_string' );
	add_action( 'woocommerce_settings_saved', __NAMESPACE__ . '\\update_config' );
}


/**
 * Add "v" parameter as cache query string when geolocation is enabled with WooCommerce
 *
 * @param array $cache_query_strings Cache query strings
 *
 * @return mixed
 * @since 3.0
 */
function maybe_add_version_query_string( $cache_query_strings ) {
	if ( 'geolocation_ajax' === get_option( 'woocommerce_default_customer_address' ) ) {
		$cache_query_strings[] = 'v';
	}

	return $cache_query_strings;
}

/**
 * Update configuration file
 *
 * @return void
 * @since 3.0
 */
function update_config() {
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
}

/**
 * Configuration update on WooCommerce activation
 *
 * @return void
 * @since 3.0
 */
function activate() {
	add_filter( 'powered_cache_cache_query_strings', __NAMESPACE__ . '\\maybe_add_version_query_string' );
	update_config();
}

/**
 * Configuration update on WooCommerce deactivation
 *
 * @return void
 * @since 3.0
 */
function deactivate() {
	remove_filter( 'powered_cache_cache_query_strings', __NAMESPACE__ . '\\maybe_add_version_query_string' );
	update_config();
}

add_action( 'activate_woocommerce/woocommerce.php', __NAMESPACE__ . '\\activate', 20 );
add_action( 'deactivate_woocommerce/woocommerce.php', __NAMESPACE__ . '\\deactivate', 20 );
