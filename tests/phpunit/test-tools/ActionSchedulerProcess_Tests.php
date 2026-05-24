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
	 * Process one queue item.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		return $item;
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
