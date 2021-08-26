<?php
/**
 * Compat with GDPR Plugin
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/gdpr/
 */

namespace PoweredCache\Compat\GDPR;

use PoweredCache\Config;
use function PoweredCache\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\GDPR' ) ) {
	add_filter( 'powered_cache_mod_rewrite', '__return_false', 23 );
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
	$cookies['gdpr'] = [
		'allowed_cookies',
		'consent_types',
	];

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
	remove_filter( 'powered_cache_mod_rewrite', '__return_false', 23 );
	remove_filter( 'powered_cache_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_gdpr/gdpr.php', __NAMESPACE__ . '\\activate', 23 );
add_action( 'deactivate_gdpr/gdpr.php', __NAMESPACE__ . '\\deactivate', 23 );


