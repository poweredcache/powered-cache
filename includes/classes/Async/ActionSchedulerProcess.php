<?php
/**
 * Action Scheduler backed async process.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

/**
 * Small compatibility layer for queue-like async jobs.
 */
abstract class ActionSchedulerProcess {

	const ACTION_SCHEDULER_GROUP = 'powered-cache';

	/**
	 * Queue items waiting for dispatch.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Action hook name.
	 *
	 * @var string
	 */
	protected $action = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( $this->get_action_hook(), array( $this, 'process_scheduled_item' ), 10, 1 );
		add_action( $this->get_complete_hook(), array( $this, 'process_complete' ), 10, 0 );
	}

	/**
	 * Push to the queue.
	 *
	 * @param mixed $data Data.
	 *
	 * @return $this
	 */
	public function push_to_queue( $data ) {
		$this->data[] = $data;

		return $this;
	}

	/**
	 * Save queued items.
	 *
	 * @return $this
	 */
	public function save() {
		return $this;
	}

	/**
	 * Dispatch queued items.
	 *
	 * @return bool
	 */
	public function dispatch() {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! $this->is_action_scheduler_ready() ) {
			return false;
		}

		foreach ( $this->data as $item ) {
			as_enqueue_async_action( $this->get_action_hook(), array( $item ), self::ACTION_SCHEDULER_GROUP );
		}

		if ( ! empty( $this->data ) ) {
			as_enqueue_async_action( $this->get_complete_hook(), array(), self::ACTION_SCHEDULER_GROUP );
		}

		$this->data = array();

		return true;
	}

	/**
	 * Process one scheduled item.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return mixed
	 */
	public function process_scheduled_item( $item ) {
		return $this->task( $item );
	}

	/**
	 * Process completion callback.
	 *
	 * @return void
	 */
	public function process_complete() {
		$this->complete();
	}

	/**
	 * Cancel scheduled items.
	 *
	 * @return void
	 */
	public function cancel_process() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) || ! $this->is_action_scheduler_ready() ) {
			return;
		}

		as_unschedule_all_actions( $this->get_action_hook(), null, self::ACTION_SCHEDULER_GROUP );
		as_unschedule_all_actions( $this->get_complete_hook(), null, self::ACTION_SCHEDULER_GROUP );
	}

	/**
	 * Cancel scheduled items.
	 *
	 * @return void
	 */
	public function cancel() {
		$this->cancel_process();
	}

	/**
	 * Whether the process is running.
	 *
	 * @return bool
	 */
	public function is_process_running() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! $this->is_action_scheduler_ready() ) {
			return false;
		}

		return as_has_scheduled_action( $this->get_action_hook(), null, self::ACTION_SCHEDULER_GROUP )
			|| as_has_scheduled_action( $this->get_complete_hook(), null, self::ACTION_SCHEDULER_GROUP );
	}

	/**
	 * Whether the process is running.
	 *
	 * @return bool
	 */
	public function is_processing() {
		return $this->is_process_running();
	}

	/**
	 * Return pending items in a queue batch shape used by existing callers.
	 *
	 * @param int $limit Limit.
	 *
	 * @return array
	 */
	public function get_batches( $limit = 0 ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! $this->is_action_scheduler_ready() ) {
			return array();
		}

		$query = array(
			'hook'     => $this->get_action_hook(),
			'group'    => self::ACTION_SCHEDULER_GROUP,
			'status'   => class_exists( '\ActionScheduler_Store' ) ? \ActionScheduler_Store::STATUS_PENDING : 'pending',
			'per_page' => $limit > 0 ? $limit : -1,
		);

		$actions = as_get_scheduled_actions( $query );
		$data    = array();

		foreach ( $actions as $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'get_args' ) ) {
				continue;
			}

			$args   = $action->get_args();
			$data[] = isset( $args[0] ) ? $args[0] : $args;
		}

		return empty( $data ) ? array() : array( (object) array( 'data' => $data ) );
	}

	/**
	 * Whether processing should continue.
	 *
	 * @return bool
	 */
	public function should_continue() {
		return true;
	}

	/**
	 * Whether Action Scheduler can safely accept API calls.
	 *
	 * @return bool
	 */
	protected function is_action_scheduler_ready() {
		if ( ! class_exists( '\ActionScheduler', false ) || ! method_exists( '\ActionScheduler', 'is_initialized' ) ) {
			return true;
		}

		return \ActionScheduler::is_initialized();
	}

	/**
	 * Return the Action Scheduler item hook.
	 *
	 * @return string
	 */
	protected function get_action_hook() {
		return $this->action;
	}

	/**
	 * Return the Action Scheduler completion hook.
	 *
	 * @return string
	 */
	protected function get_complete_hook() {
		return $this->action . '_complete';
	}

	/**
	 * Process one queue item.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return mixed
	 */
	abstract protected function task( $item );

	/**
	 * Complete hook.
	 *
	 * @return void
	 */
	protected function complete() {}
}
