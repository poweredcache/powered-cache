<?php
/**
 * Background process for cache preloading
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use PoweredCache\Preloader;
use function PoweredCache\Utils\detect_cpu_cores;
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
		// Stop early if system load is too high
		if ( ! $this->should_continue() ) {
			\PoweredCache\Utils\log( 'Preload task aborted early due to system load' );
			return $item;
		}

		$this->settings = \PoweredCache\Utils\get_settings();
		$delay          = absint( $this->settings['preload_request_interval'] ) * 1000000; // convert to microseconds

		\PoweredCache\Utils\log( sprintf( 'Preloading..: %s', $item ) );

		if ( filter_var( $item, FILTER_VALIDATE_URL ) ) {

			if ( ! is_url_cached( $item, false, $this->settings['gzip_compression'] ) ) {
				Preloader::preload_request( $item );
				Preloader::wait( $delay );
			}

			// make the preload request by using mobile agent
			if ( $this->settings['cache_mobile'] && $this->settings['cache_mobile_separate_file'] ) {
				if ( ! is_url_cached( $item, true, $this->settings['gzip_compression'] ) ) {
					Preloader::preload_request( $item, [ 'user-agent' => Preloader::mobile_user_agent() ] );
					Preloader::wait( $delay );
				}
			}
		}

		\PoweredCache\Utils\log( sprintf( 'Preloaded...: %s', $item ) );

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
	 * Try to cancel all items in the queue up to $max_attempt
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
	 * Determine if the background process should continue.
	 * Stops the process if server load is too high or spikes suddenly.
	 *
	 * @return bool
	 */
	public function should_continue() {
		$should_continue = parent::should_continue();
		$halted_by_load  = false;

		$load = function_exists( 'sys_getloadavg' )
			? sys_getloadavg()
			: [ 0.0, 0.0, 0.0 ];

		$load_1min  = isset( $load[0] ) ? (float) $load[0] : 0.0;
		$load_5min  = isset( $load[1] ) ? (float) $load[1] : 0.0;
		$load_15min = isset( $load[2] ) ? (float) $load[2] : 0.0;

		// Weighted average: gives slightly more weight to short-term load.
		$weighted_load = ( $load_1min * 0.5 + $load_5min * 0.3 + $load_15min * 0.2 );

		// Consider a spike if 1min load is significantly higher than longer-term averages.
		$load_spike_detected = ( $load_1min > $load_5min * 2 || $load_5min > $load_15min * 2 );

		// Fallback max load threshold (used if no CPU core info or not desired)
		$default_max_load = 4.0;

		/**
		 * Filter the max allowed server load before preloading pauses.
		 *
		 * @hook   powered_cache_preloader_max_server_load
		 *
		 * @param  {float} $default_max_load Default load threshold.
		 * @param  {array} $load             [1min, 5min, 15min] load averages.
		 *
		 * @return {float} Maximum allowed server load before preloading pauses.
		 * @since  3.6
		 */
		$max_allowed_load = apply_filters(
			'powered_cache_preloader_max_server_load',
			$default_max_load,
			$load
		);

		// Allow setting a custom maximum server load threshold.
		if ( defined( 'POWERED_CACHE_PRELOADER_MAX_SERVER_LOAD' ) && is_numeric( POWERED_CACHE_PRELOADER_MAX_SERVER_LOAD ) ) {
			$max_allowed_load = (float) POWERED_CACHE_PRELOADER_MAX_SERVER_LOAD;
		}

		if ( $should_continue && ( $weighted_load > $max_allowed_load || $load_spike_detected ) ) {
			\PoweredCache\Utils\log(
				sprintf(
					'Preload paused: server load too high or spike detected (1min: %.2f, 5min: %.2f, 15min: %.2f, weighted: %.2f, threshold: %.2f)',
					$load_1min,
					$load_5min,
					$load_15min,
					$weighted_load,
					$max_allowed_load
				)
			);
			$should_continue = false;
			$halted_by_load  = true;
		}

		/**
		 * Allow complete control over continuation logic.
		 *
		 * @hook  powered_cache_preloader_should_continue
		 *
		 * @param bool  $should_continue  Whether to continue processing.
		 * @param bool  $halted_by_load   True if we halted due to load.
		 * @param array $load             [1min, 5min, 15min] load averages.
		 * @param float $max_allowed_load Final load limit used for decision.
		 *
		 * @return bool Whether to continue processing.
		 * @since 3.6
		 */
		return (bool) apply_filters(
			'powered_cache_preloader_should_continue',
			$should_continue,
			$halted_by_load,
			$load,
			$max_allowed_load
		);
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
