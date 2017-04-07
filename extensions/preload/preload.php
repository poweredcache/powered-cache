<?php
/**
 * Extension Name: Preloader
 * Extension URI: https://poweredcache.com/extensions/preload
 * Description: Preload extension for Powered Cache
 * Author: Powered Cache Team
 * Version: 1.0
 * Author URI: https://poweredcache.com
 * Extension Image: extension-image.png
 * License: GPLv2 (or later)
*/

require_once 'inc/class-powered-cache-preload-process.php';


Powered_Cache_Preload_Process::factory();

if ( is_admin() ) {
	require_once 'inc/class-powered-cache-preload-admin.php';
	Powered_Cache_Preload_Admin::factory();
}


// make description translatable
__( 'Preload extension for Powered Cache', 'powered-cache' );
