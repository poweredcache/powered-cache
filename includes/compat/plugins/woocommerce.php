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
	add_filter( 'powered_cache_preload_add_url_to_queue', __NAMESPACE__ . '\\exclude_dynamic_pages_from_preload', 10, 2 );
	add_action( 'woocommerce_settings_saved', __NAMESPACE__ . '\\update_config' );
}

/**
 * Skip the store's configured cart, checkout, and account pages.
 *
 * @param bool   $preload Whether to preload the URL.
 * @param string $url     Candidate URL.
 * @return bool
 */
function exclude_dynamic_pages_from_preload( $preload, $url ) {
	if ( ! $preload || ! function_exists( 'wc_get_page_id' ) ) {
		return $preload;
	}

	static $page_urls = [];
	$site_id = get_current_blog_id();
	if ( ! isset( $page_urls[ $site_id ] ) ) {
		$page_urls[ $site_id ] = [];
		foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
			$page_id   = wc_get_page_id( $page );
			$permalink = $page_id > 0 ? get_permalink( $page_id ) : false;
			$page_url  = $permalink ? wp_parse_url( $permalink ) : false;
			if ( empty( $page_url['host'] ) ) {
				continue;
			}
			parse_str( $page_url['query'] ?? '', $page_query );
			ksort( $page_query );
			$page_url['query_args']  = $page_query;
			$page_urls[ $site_id ][] = $page_url;
		}
	}

	$parts = wp_parse_url( $url );
	$path  = rtrim( $parts['path'] ?? '/', '/' );
	$query = [];
	if ( isset( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		ksort( $query );
	}

	foreach ( $page_urls[ $site_id ] as $page_url ) {
		$page_path = rtrim( $page_url['path'] ?? '/', '/' );
		if ( ( $page_url['host'] ?? '' ) !== ( $parts['host'] ?? '' ) || ( $page_url['port'] ?? null ) !== ( $parts['port'] ?? null ) ) {
			continue;
		}

		if ( ( $page_path === $path || ( '' !== $page_path && 0 === strpos( $path, $page_path . '/' ) ) ) && array_intersect_key( $query, $page_url['query_args'] ) === $page_url['query_args'] ) {
			return false;
		}
	}

	return true;
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
