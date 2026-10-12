<?php
/**
 * Action Scheduler process tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\ActionSchedulerProcess;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Test action object.
 */
class ActionSchedulerProcess_Test_Action {

	/**
	 * Action args.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Constructor.
	 *
	 * @param array $args Action args.
	 */
	public function __construct( array $args ) {
		$this->args = $args;
	}

	/**
	 * Return action args.
	 *
	 * @return array
	 */
	public function get_args() {
		return $this->args;
	}
}

/**
 * Test process.
 */
class ActionSchedulerProcess_Test_Process extends ActionSchedulerProcess {

	/**
	 * Action hook.
	 *
	 * @var string
	 */
	protected $action = 'powered_cache_test_process';

	/**
	 * Task result.
	 *
	 * @var mixed
	 */
	private $task_result = false;

	/**
	 * Whether completion ran.
	 *
	 * @var bool
	 */
	private $completed = false;

	/**
	 * Process one queue item.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		return $this->task_result;
	}

	/**
	 * Set the task result.
	 *
	 * @param mixed $result Task result.
	 *
	 * @return void
	 */
	public function set_task_result( $result ) {
		$this->task_result = $result;
	}

	/**
	 * Record completion.
	 *
	 * @return void
	 */
	protected function complete() {
		$this->completed = true;
	}

	/**
	 * Whether completion ran.
	 *
	 * @return bool
	 */
	public function is_completed() {
		return $this->completed;
	}

	/**
	 * Return buffered queue data.
	 *
	 * @return array
	 */
	public function get_buffered_data() {
		return $this->data;
	}
}

/**
 * Action Scheduler process test case.
 */
class ActionSchedulerProcess_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Async/ActionSchedulerProcess.php',
	);

	/**
	 * It schedules queue items and a completion action.
	 */
	public function test_dispatch_schedules_items_and_completion() {
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			array(
				'times'  => 3,
				'return' => 1,
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$result  = $process
			->push_to_queue( 'first' )
			->push_to_queue( 'second' )
			->save()
			->dispatch();

		$this->assertTrue( $result );
		$this->assertConditionsMet();
	}

	/**
	 * It reports an Action Scheduler enqueue failure and retains failed items.
	 */
	public function test_dispatch_reports_enqueue_failure() {
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			array(
				'times'  => 1,
				'return' => 0,
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$result  = $process->push_to_queue( 'failed' )->dispatch();

		$this->assertFalse( $result );
		$this->assertSame( array( 'failed' ), $process->get_buffered_data() );
		$this->assertConditionsMet();
	}

	/**
	 * It reschedules task results returned for retry.
	 */
	public function test_process_scheduled_item_reschedules_retry_result() {
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			array(
				'times'  => 1,
				'return' => function ( $timestamp, $hook, $args, $group, $unique = false ) {
					$this->assertFalse( $unique );

					return 1;
				},
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$process->set_task_result( 'retry' );

		$this->assertSame( 'retry', $process->process_scheduled_item( 'item' ) );
		$this->assertConditionsMet();
	}

	/**
	 * It passes the next attempt number to the retry action.
	 */
	public function test_process_scheduled_item_increments_retry_attempt() {
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			array(
				'times'  => 1,
				'return' => function ( $timestamp, $hook, $args ) {
					$this->assertSame( array( 'retry', 3 ), $args );

					return 1;
				},
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$process->set_task_result( 'retry' );

		$process->process_scheduled_item( 'item', 2 );
		$this->assertConditionsMet();
	}

	/**
	 * It stops retrying once the retry limit is reached.
	 */
	public function test_process_scheduled_item_stops_after_max_retries() {
		\WP_Mock::userFunction( 'as_schedule_single_action', array( 'times' => 0 ) );
		\WP_Mock::userFunction( 'as_enqueue_async_action', array( 'times' => 0 ) );

		$process = new ActionSchedulerProcess_Test_Process();
		$process->set_task_result( 'retry' );

		$this->assertSame( 'retry', $process->process_scheduled_item( 'item', ActionSchedulerProcess::MAX_RETRIES ) );
		$this->assertConditionsMet();
	}

	/**
	 * It does not retry tasks that return null.
	 */
	public function test_process_scheduled_item_does_not_retry_null_result() {
		\WP_Mock::userFunction( 'as_schedule_single_action', array( 'times' => 0 ) );
		\WP_Mock::userFunction( 'as_enqueue_async_action', array( 'times' => 0 ) );

		$process = new ActionSchedulerProcess_Test_Process();
		$process->set_task_result( null );

		$this->assertNull( $process->process_scheduled_item( 'item' ) );
		$this->assertConditionsMet();
	}

	/**
	 * It delays completion while queue items remain scheduled.
	 */
	public function test_process_complete_waits_for_scheduled_items() {
		\WP_Mock::userFunction(
			'as_has_scheduled_action',
			array(
				'times'  => 1,
				'return' => true,
			)
		);
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			array(
				'times'  => 1,
				'return' => function ( $timestamp, $hook, $args, $group, $unique = false ) {
					$this->assertFalse( $unique );

					return 1;
				},
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$process->process_complete();

		$this->assertFalse( $process->is_completed() );
		$this->assertConditionsMet();
	}

	/**
	 * It cancels queued items and completion actions.
	 */
	public function test_cancel_process_unschedules_items_and_completion() {
		\WP_Mock::userFunction(
			'as_unschedule_all_actions',
			array(
				'times'  => 2,
				'return' => null,
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$process->cancel_process();

		$this->assertConditionsMet();
	}

	/**
	 * It keeps the previous delete_all cancellation contract.
	 */
	public function test_delete_all_cancels_items_and_completion() {
		\WP_Mock::userFunction(
			'as_unschedule_all_actions',
			array(
				'times'  => 2,
				'return' => null,
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$process->push_to_queue( 'queued' );
		$process->delete_all();

		$this->assertSame( array(), $process->get_buffered_data() );
		$this->assertConditionsMet();
	}

	/**
	 * It exposes pending Action Scheduler items in a batch shape.
	 */
	public function test_get_batches_returns_action_scheduler_batch_shape() {
		\WP_Mock::userFunction(
			'as_get_scheduled_actions',
			array(
				'return' => array(
					new ActionSchedulerProcess_Test_Action( array( 'first' ) ),
					new ActionSchedulerProcess_Test_Action( array( 'second' ) ),
				),
			)
		);

		$process = new ActionSchedulerProcess_Test_Process();
		$batches = $process->get_batches( 1 );

		$this->assertCount( 1, $batches );
		$this->assertSame( array( 'first', 'second' ), $batches[0]->data );
	}
}
