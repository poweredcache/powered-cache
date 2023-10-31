<?php
/**
 * Compat with WPS Hide Login
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/wps-hide-login/
 */

namespace PoweredCache\Compat\WPSHideLogin;

use PoweredCache\Config;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\WPS\WPS_Hide_Login\Plugin' ) || defined( 'WPS_HIDE_LOGIN_VERSION' ) ) {
	add_filter( 'powered_cache_rejected_uri_list', __NAMESPACE__ . '\\add_rejected_uri' );
}

/**
 * Add rejected URI to not cache list
 *
 * @param array $urls Rejected URI list
 *
 * @return mixed
 */
function add_rejected_uri( $urls ) {
	if ( class_exists( '\WPS\WPS_Hide_Login\Plugin' ) ) {
		$login_url = \WPS\WPS_Hide_Login\Plugin::get_instance()->new_login_url();
		$slug      = wp_parse_url( $login_url, PHP_URL_PATH );
		if ( $slug ) {
			$urls[] = trailingslashit( $slug ) . '(.*)';
		}
	}

	return $urls;
}

/**
 * Update configuration file
 *
 * @return void
 */
function update_config() {
	$settings = \PoweredCache\Utils\get_settings();
	Config::factory()->save_configuration( $settings, POWERED_CACHE_IS_NETWORK );
}

/**
 * Configuration update on WPS Hide Login activation
 *
 * @return void
 */
function activate() {
	add_filter( 'powered_cache_rejected_uri_list', __NAMESPACE__ . '\\add_rejected_uri' );
	update_config();
}

/**
 * Configuration update on WPS Hide Login deactivation
 *
 * @return void
 */
function deactivate() {
	remove_filter( 'powered_cache_rejected_uri_list', __NAMESPACE__ . '\\add_rejected_uri' );
	update_config();
}

add_action( 'activate_wps-hide-login/wps-hide-login.php', __NAMESPACE__ . '\\activate' );
add_action( 'deactivate_wps-hide-login/wps-hide-login.php', __NAMESPACE__ . '\\deactivate' );
