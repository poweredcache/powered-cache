<?php
/**
 * Legacy async process compatibility.
 *
 * @package PoweredCache
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Powered_Cache_WP_Background_Process', false ) ) {
	class_alias( \PoweredCache\Async\ActionSchedulerProcess::class, 'Powered_Cache_WP_Background_Process' );
}
