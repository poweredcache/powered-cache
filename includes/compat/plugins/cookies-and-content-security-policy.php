<?php
/**
 * Compat with Cookies and Content Security Policy
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/cookies-and-content-security-policy/
 * @since   2.1.1
 */

namespace PoweredCache\Compat\CookiesAndContentSecurityPolicy;

use PoweredCache\Config;
use function PoweredCache\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( '\get_cacsp_options' ) ) {
	add_filter( 'powered_cache_mod_rewrite', '__return_false', 24 );
	add_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
}


/**
 * Add cookie to vary cookie options
 *
 * @param array $cookies The list of vary cookies
 *
 * @return array Altered cookie list.
 * @since 2.1.1
 */
function add_vary_cookie( $cookies ) {
	$status = get_cacsp_options( 'cacsp_option_actived' );

	if ( 'true' === $status && ! in_array( 'cookies_and_content_security_policy', $cookies, true ) ) {
		$cookies[] = 'cookies_and_content_security_policy';
	}

	return $cookies;
}

/**
 * Setup vary cookie on activation
 *
 * @since 2.1.1
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
 * @since 2.1.1
 */
function deactivate() {
	remove_filter( 'powered_cache_mod_rewrite', '__return_false', 24 );
	remove_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_cookies-and-content-security-policy/cookies-and-content-security-policy.php', __NAMESPACE__ . '\\activate', 24 );
add_action( 'deactivate_cookies-and-content-security-policy/cookies-and-content-security-policy.php', __NAMESPACE__ . '\\deactivate', 24 );
add_action( 'update_option_cacsp_option_actived', __NAMESPACE__ . '\\activate' );
