<?php
/**
 * Extension Name: Cloudflare
 * Extension URI: https://poweredcache.com/extensions/cloudflare
 * Description: Cloudflare extension for Powered Cache
 * Author: Powered Cache Team
 * Version: 1.0
 * Author URI: https://poweredcache.com
 * Extension Image: extension-image.png
 * License: GPLv2 (or later)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'inc/class-powered-cache-cloudflare-ip-rewrite.php';
require_once 'inc/class-powered-cache-cloudflare-api.php';

$ip_rewrite = new Powered_Cache_Cloudflare_IP_Rewrite();
$is_cf      = $ip_rewrite->isCloudFlare();
if ( $is_cf ) {
	// Fixes Flexible SSL
	if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
		$_SERVER['HTTPS'] = 'on';
	}
}

if ( is_admin() ) {
	require_once 'inc/class-powered-cache-cloudflare-admin.php';
	Powered_Cache_Cloudflare_Admin::factory();
}


/**
 * Purge cloudflare cache
 *
 * @since 1.0
 * @return array|bool|mixed|object|string
 */
function powered_cache_cloudflare_purge_cache() {
	$email   = powered_cache_get_extension_option( 'cloudflare', 'email' );
	$api_key = powered_cache_get_extension_option( 'cloudflare', 'api_key' );
	if ( $email && $api_key ) {
		var_dump($email);
		var_dump($api_key);
		exit;
		$api = new Powered_Cache_Cloudflare_Api( $email, $api_key );

		$zone = powered_cache_get_extension_option( 'cloudflare', 'zone' );
		if ( $zone ) {
			return $api->purge( $zone );
		}
	}

	return false;
}

// make description translatable
__( 'Cloudflare extension for Powered Cache', 'powered-cache' );
