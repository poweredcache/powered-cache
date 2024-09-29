<?php
/**
 * Compat with clear-cache-for-widgets
 *
 * @package PoweredCache\Compat
 * @link    https://wordpress.org/plugins/clear-cache-for-widgets/
 */

namespace PoweredCache\Compat\ClearCacheForWidgets;

use function PoweredCache\Utils\powered_cache_flush;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'ccfm_supported_caching_exists', '__return_true' );


add_action( 'ccfm_clear_cache_for_me', __NAMESPACE__ . '\\purge_cache' );


/**
 * Purge cache by using the powered_cache_flush function.
 *
 * @return void
 */
function purge_cache() {
	powered_cache_flush();
}
