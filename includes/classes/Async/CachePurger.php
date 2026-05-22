<?php
/**
 * Background process for cache purging
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use \Powered_Cache_WP_Background_Process as Powered_Cache_WP_Background_Process;
use function PoweredCache\Utils\clean_page_cache_dir;
use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\delete_page_cache;
use function PoweredCache\Utils\powered_cache_flush;

/**
 * Class CachePurger
 */
class CachePurger extends Powered_Cache_WP_Background_Process {

	const ACTION_SCHEDULER_HOOK  = 'powered_cache_action_scheduler_cache_purge';
	const ACTION_SCHEDULER_GROUP = 'powered-cache';

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
	protected $action = 'powered_cache_purger';

	/**
	 * Constructor.
	 *
	 * @param bool|array $allowed_batch_data_classes Optional batch data classes.
	 */
	public function __construct( $allowed_batch_data_classes = true ) {
		parent::__construct( $allowed_batch_data_classes );

		add_action( self::ACTION_SCHEDULER_HOOK, array( $this, 'process_scheduled_item' ), 10, 1 );
	}

	/**
	 * Push to the queue.
	 *
	 * @param mixed $data Data.
	 *
	 * @return $this
	 */
	public function push_to_queue( $data ) {
		if ( $this->use_action_scheduler() ) {
			$this->data[] = $data;

			return $this;
		}

		return parent::push_to_queue( $data );
	}

	/**
	 * Save queued items.
	 *
	 * Action Scheduler persists each action during dispatch.
	 *
	 * @return $this
	 */
	public function save() {
		if ( $this->use_action_scheduler() ) {
			return $this;
		}

		return parent::save();
	}

	/**
	 * Dispatch queued cache purge items.
	 *
	 * @return mixed
	 */
	public function dispatch() {
		if ( ! $this->use_action_scheduler() ) {
			return parent::dispatch();
		}

		foreach ( $this->data as $item ) {
			as_enqueue_async_action(
				self::ACTION_SCHEDULER_HOOK,
				array( $item ),
				self::ACTION_SCHEDULER_GROUP
			);
		}

		$this->data = array();

		return true;
	}

	/**
	 * Process one Action Scheduler cache purge item.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return void
	 */
	public function process_scheduled_item( $item ) {
		$this->task( $item );
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

		if ( empty( $item['call'] ) ) {
			return;
		}

		\PoweredCache\Utils\log( sprintf( 'Call: %s', $item['call'] ) );

		switch ( $item['call'] ) {
			case 'powered_cache_flush':
				powered_cache_flush();
				break;
			case 'clean_page_cache_dir':
				clean_page_cache_dir();
				break;
			case 'clean_site_cache_dir':
				clean_site_cache_dir();
				break;
			case 'clean_site_cache_for_language':
				if ( function_exists( '\PoweredCache\Compat\WPML\clean_site_cache_for_language' ) ) {
					\PoweredCache\Compat\WPML\clean_site_cache_for_language( $item['lang_code'] );
				}

				break;
			case 'delete_page_cache':
				$urls = $item['urls'];
				if ( ! empty( $urls ) ) {
					foreach ( $urls as $url ) {
						\PoweredCache\Utils\log( sprintf( 'delete_page_cache - URL: %s', $url ) );
						delete_page_cache( $url );
					}
				}
				break;
		}

		return false;
	}


	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		\PoweredCache\Utils\log( 'Async cache purge has been completed!' );
		parent::complete();
	}

	/**
	 * Sometimes canceling a process is glitchy
	 * Try to cancel all items in the queue up to $max_attempt
	 */
	public function cancel_process() {
		if ( $this->use_action_scheduler() ) {
			as_unschedule_all_actions( self::ACTION_SCHEDULER_HOOK, null, self::ACTION_SCHEDULER_GROUP );

			return;
		}

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
	public function is_process_running() {
		if ( $this->use_action_scheduler() && function_exists( 'as_has_scheduled_action' ) ) {
			return as_has_scheduled_action( self::ACTION_SCHEDULER_HOOK, null, self::ACTION_SCHEDULER_GROUP );
		}

		return parent::is_processing();
	}

	/**
	 * Determine whether Action Scheduler should process cache purge jobs.
	 *
	 * @return bool
	 */
	private function use_action_scheduler() {
		$available = function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_unschedule_all_actions' );

		if ( $available && class_exists( '\Action_Scheduler' ) && method_exists( '\Action_Scheduler', 'is_initialized' ) && ! \Action_Scheduler::is_initialized() ) {
			$available = false;
		}

		/**
		 * Filter whether cache purge jobs should use Action Scheduler.
		 *
		 * @hook powered_cache_cache_purger_use_action_scheduler
		 *
		 * @param {bool} $available Whether Action Scheduler is available.
		 *
		 * @return {bool} New value.
		 *
		 * @since 4.0.0
		 */
		return (bool) apply_filters( 'powered_cache_cache_purger_use_action_scheduler', $available );
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return CachePurger
	 * @since 2.3
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
