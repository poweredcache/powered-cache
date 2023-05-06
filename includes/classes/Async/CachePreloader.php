<?php
/**
 * Background process for cache preloading
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use PoweredCache\Preloader;
use function PoweredCache\Utils\is_url_cached;
use \Powered_Cache_WP_Background_Process as Powered_Cache_WP_Background_Process;

/**
 * Class CachePreloader
 */
class CachePreloader extends Powered_Cache_WP_Background_Process {

	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	protected $settings;

	/**
	 * string
	 *
	 * @var $action
	 */
	protected $action = 'powered_cache_preload';

	/**
	 * Supported preloading options
	 * Options are the key of plugin settings
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'preload_homepage',
			'preload_public_posts',
			'preload_public_tax',
		];
	}

	/**
	 * Task
	 *
	 * Perform Preload Tasks
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$this->settings = \PoweredCache\Utils\get_settings();

		\PoweredCache\Utils\log( sprintf( 'Preloading...%s', $item ) );

		if ( filter_var( $item, FILTER_VALIDATE_URL ) ) {

			if ( ! is_url_cached( $item, false, $this->settings['gzip_compression'] ) ) {
				Preloader::preload_request( $item );
				Preloader::wait();
			}

			// make the preload request by using mobile agent
			if ( $this->settings['cache_mobile'] && $this->settings['cache_mobile_separate_file'] ) {
				if ( ! is_url_cached( $item, true, $this->settings['gzip_compression'] ) ) {
					Preloader::preload_request( $item, [ 'user-agent' => Preloader::mobile_user_agent() ] );
					Preloader::wait();
				}
			}
		}

		\PoweredCache\Utils\log( sprintf( 'Preloading completed...%s', $item ) );

		return false;
	}


	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::complete();
	}

	/**
	 * Sometimes canceling a process is glitchy
	 * Try to cancell all items in the queue up to $max_attempt
	 */
	public function cancel_process() {
		$max_attempt = 5;
		$cancelled   = 0;
		while ( ! parent::is_queue_empty() ) {
			if ( $cancelled >= $max_attempt ) {
				break;
			}
			parent::cancel();
			$cancelled ++;
		}
	}

	/**
	 * Whether the process running or not
	 *
	 * @return bool
	 */
	public function is_process_running() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		return parent::is_processing();
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return CachePreloader
	 * @since 2.0
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
