<?php
/**
 * Action Scheduler process for cache preloading.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use PoweredCache\Preloader;
use function PoweredCache\Utils\is_url_cached;

/**
 * Class CachePreloader
 */
class CachePreloader extends ActionSchedulerProcess {

	public const ACTION_HOOK = 'powered_cache_preload';

	private const MAX_QUEUED_URLS  = 20;
	private const MAX_URLS_PER_RUN = 5;
	private const RUN_TIME_BUDGET = 10;
	// Action Scheduler allows 8,000 bytes of JSON arguments. Leave room for the retry count.
	private const MAX_BATCH_URL_BYTES = 7980;

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
	protected $action = self::ACTION_HOOK;

	/**
	 * Preloading has no completion work.
	 *
	 * @var bool
	 */
	protected $schedule_completion_action = false;

	/**
	 * Let default-priority actions take precedence over preloading.
	 *
	 * @return int
	 */
	protected function get_action_priority() {
		return self::preload_action_priority();
	}

	/**
	 * Resolve the priority shared by page preloads and sitemap discovery.
	 *
	 * @return int
	 */
	public static function preload_action_priority() {
		/**
		 * Filters the priority of preload actions and retries.
		 *
		 * Action Scheduler accepts 0–255. Lower numbers run first.
		 *
		 * @param int $priority Preload priority. Default 20.
		 */
		$priority = apply_filters( 'powered_cache_preload_action_priority', 20 );
		return is_numeric( $priority ) ? max( 0, min( 255, (int) $priority ) ) : 20;
	}

	/**
	 * Use Action Scheduler's bulk cancellation path for the preload-only hook.
	 *
	 * @return string
	 */
	protected function get_cancellation_group() {
		return '';
	}

	/**
	 * Let Action Scheduler retain the failed action when a retry cannot be persisted.
	 *
	 * @param mixed $item    Pending URLs.
	 * @param int   $attempt Retry count.
	 * @return bool
	 * @throws \RuntimeException When Action Scheduler cannot persist the retry.
	 */
	protected function schedule_retry( $item, $attempt ) {
		if ( ! parent::schedule_retry( $item, $attempt ) ) {
			throw new \RuntimeException( 'Powered Cache could not schedule a preload retry.' );
		}

		return true;
	}

	/**
	 * Persist compact batches so large preloads do not write one action per URL.
	 *
	 * @return bool
	 */
	public function dispatch() {
		if ( empty( $this->data ) ) {
			return parent::dispatch();
		}

		$this->data = $this->batch_urls();
		$dispatched = parent::dispatch();

		// Restore URLs from any batches that Action Scheduler did not accept.
		$this->data = $this->flatten_queue_items( $this->data );

		return $dispatched;
	}

	/**
	 * Build queue items within Action Scheduler's argument size limit.
	 *
	 * @return array
	 */
	private function batch_urls() {
		$batches = [];
		$batch   = [];
		$bytes   = 0;
		foreach ( array_unique( $this->data ) as $url ) {
			$url_bytes = strlen( (string) wp_json_encode( $url ) ) + 1;
			if ( $batch && ( count( $batch ) >= self::MAX_QUEUED_URLS || $bytes + $url_bytes > self::MAX_BATCH_URL_BYTES ) ) {
				$batches[] = $batch;
				$batch     = [];
				$bytes     = 0;
			}
			if ( $url_bytes > self::MAX_BATCH_URL_BYTES ) {
				// Keep the original single-URL format when the batch size limit is too small.
				$batches[] = $url;
				continue;
			}
			$batch[] = $url;
			$bytes  += $url_bytes;
		}
		if ( $batch ) {
			$batches[] = $batch;
		}

		return $batches;
	}

	/**
	 * Expand a mix of individual URLs and URL batches.
	 *
	 * @param array $items Queue items.
	 * @return array
	 */
	private function flatten_queue_items( array $items ) {
		$batches = [];
		foreach ( $items as $item ) {
			$batches[] = (array) $item;
		}
		return array_merge( ...$batches );
	}

	/**
	 * Keep the queue inspection API returning a flat list of URLs.
	 *
	 * @param int $limit Maximum number of URLs to return.
	 * @return array
	 */
	public function get_batches( $limit = 0 ) {
		$batches = parent::get_batches( $limit );

		if ( empty( $batches ) ) {
			return $batches;
		}

		$urls             = $this->flatten_queue_items( $batches[0]->data );
		$batches[0]->data = $limit > 0 ? array_slice( $urls, 0, $limit ) : $urls;

		return $batches;
	}

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

		if ( empty( $this->settings['enable_cache_preload'] ) || empty( $this->settings['enable_page_cache'] ) ) {
			\PoweredCache\Utils\log( 'Preload task skipped because page cache or cache preload is disabled.' );
			return false;
		}

		$delay    = absint( $this->settings['preload_request_interval'] ) * 1000000; // convert to microseconds
		$urls     = array_values( (array) $item );
		$deadline = microtime( true ) + self::RUN_TIME_BUDGET;

		foreach ( $urls as $index => $url ) {
			if ( ! $this->should_continue() ) {
				\PoweredCache\Utils\log( 'Preload task aborted early due to system load' );
				return is_array( $item ) ? array_slice( $urls, $index ) : $item;
			}

			if ( $index > 0 && ( $index >= self::MAX_URLS_PER_RUN || microtime( true ) >= $deadline ) ) {
				// If the continuation cannot be enqueued, retry only the unprocessed URLs.
				$remaining = array_slice( $urls, $index );
				$action_id = as_enqueue_async_action( $this->get_action_hook(), [ $remaining ], self::ACTION_SCHEDULER_GROUP, false, $this->get_action_priority() );
				return $action_id ? false : $remaining;
			}

			$this->preload_url( $url, $delay );
		}

		return false;
	}

	/**
	 * Warm the applicable cache variants of one URL.
	 *
	 * @param string $item  URL to warm.
	 * @param int    $delay Delay between requests in microseconds.
	 */
	private function preload_url( $item, $delay ) {
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
	}

	/**
	 * Determine if the preload queue should continue.
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

		// Fallback max load threshold
		$default_max_load = 16.0;

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
