<?php
/**
 * Action Scheduler process readiness tests.
 *
 * @package PoweredCache
 */

namespace {
	if ( ! class_exists( 'ActionScheduler', false ) ) {
		/**
		 * Minimal Action Scheduler stub.
		 */
		class ActionScheduler {

			/**
			 * Whether Action Scheduler is initialized.
			 *
			 * @var bool
			 */
			public static $initialized = true;

			/**
			 * Return initialization state.
			 *
			 * @return bool
			 */
			public static function is_initialized() {
				return self::$initialized;
			}
		}
	}
}

namespace PoweredCache {

	use PoweredCache\Async\ActionSchedulerProcess;

	/**
	 * Readiness test process.
	 */
	class ActionSchedulerProcessReadiness_Test_Process extends ActionSchedulerProcess {

		/**
		 * Action hook.
		 *
		 * @var string
		 */
		protected $action = 'powered_cache_readiness_test_process';

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
	 * Action Scheduler process readiness test case.
	 */
	class ActionSchedulerProcessReadiness_Tests extends TestCase {

		/**
		 * Test files loaded before each test.
		 *
		 * @var array
		 */
		protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
			'classes/Async/ActionSchedulerProcess.php',
		);

		/**
		 * Reset test state.
		 */
		public function tearDown(): void {
			\ActionScheduler::$initialized = true;

			parent::tearDown();
		}

		/**
		 * It does not dispatch before Action Scheduler has initialized its datastore.
		 */
		public function test_dispatch_returns_false_when_action_scheduler_is_not_initialized() {
			\ActionScheduler::$initialized = false;

			\WP_Mock::userFunction(
				'as_enqueue_async_action',
				array(
					'times'  => 0,
					'return' => 1,
				)
			);

			$process = new ActionSchedulerProcessReadiness_Test_Process();
			$result  = $process->push_to_queue( 'early' )->dispatch();

			$this->assertFalse( $result );
			$this->assertConditionsMet();
		}
	}
}
