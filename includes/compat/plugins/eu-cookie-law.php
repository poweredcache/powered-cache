<?php
/**
 * Compat with EU Cookie Law for GDPR/CCPA Plugin
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/eu-cookie-law/
 */

namespace PoweredCache\Compat\EUCookieLaw;

use PoweredCache\Config;
use function PoweredCache\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( '\eucookie_start' ) ) {
	add_filter( 'powered_cache_mod_rewrite', '__return_false', 22 );
	add_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
}


/**
 * Add cookie to vary cookie options
 *
 * @param array $cookies The list of vary cookies
 *
 * @return array Altered cookie list.
 * @since 2.0
 */
function add_vary_cookie( $cookies ) {
	$options = get_option( 'peadig_eucookie' );

	if ( ! empty( $options['enabled'] ) ) {
		$cookies[] = 'euCookie';
	}

	return $cookies;
}

/**
 * Setup vary cookie on activation
 *
 * @since 2.0
 */
function activate() {
	add_filter( 'powered_cache_mod_rewrite', '__return_false' );
	add_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
	clean_site_cache_dir();
}

/**
 * Remove vary cookie on deactivation
 *
 * @since 2.0
 */
function deactivate() {
	remove_filter( 'powered_cache_mod_rewrite', '__return_false', 22 );
	remove_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_eu-cookie-law/eu-cookie-law.php', __NAMESPACE__ . '\\activate', 22 );
add_action( 'deactivate_eu-cookie-law/eu-cookie-law.php', __NAMESPACE__ . '\\deactivate', 22 );


