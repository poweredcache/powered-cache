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

require_once 'inc/class-pc-cloudflare-ip-rewrite.php';
require_once 'inc/class-pc-cloudflare-api.php';

$ip_rewrite = new PC_Cloudflare_IP_Rewrite();
$is_cf      = $ip_rewrite->isCloudFlare();
if ( $is_cf ) {
	// Fixes Flexible SSL
	if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
		$_SERVER['HTTPS'] = 'on';
	}
}

if ( is_admin() ) {
	require_once 'inc/class-pc-cloudflare-admin.php';
	PC_Cloudflare_Admin::factory();
}


/**
 * Purge cloudflare cache
 *
 * @since 1.0
 * @return array|bool|mixed|object|string
 */
function pc_cloudflare_purge_cache() {
	$api  = new PC_Cloudflare_Api( pc_get_extension_option( 'cloudflare', 'email' ), pc_get_extension_option( 'cloudflare', 'api_key' ) );
	$zone = pc_get_extension_option( 'cloudflare', 'zone' );
	if ( $zone ) {
		return $api->purge( $zone );
	}

	return false;
}

// make description translatable
__( 'Cloudflare extension for Powered Cache', 'powered-cache' );
