<?php
/**
 * Legacy async compatibility tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

/**
 * Legacy async compatibility test case.
 */
class LegacyAsyncCompatibility_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Async/ActionSchedulerProcess.php',
		'compat/legacy-async.php',
	);

	/**
	 * It keeps the old async base class available through the Action Scheduler adapter.
	 */
	public function test_legacy_async_base_class_uses_action_scheduler_adapter() {
		$this->assertTrue( class_exists( '\\Powered_Cache_WP_Background_Process', false ) );
		$this->assertTrue( is_a( '\\Powered_Cache_WP_Background_Process', Async\ActionSchedulerProcess::class, true ) );
	}

	/**
	 * It supports the cancellation override used by pre-4.0 Premium queues.
	 */
	public function test_legacy_cancel_override_clears_buffered_items() {
		$process = new class() extends \Powered_Cache_WP_Background_Process {

			/**
			 * Action hook.
			 *
			 * @var string
			 */
			protected $action = 'powered_cache_legacy_test';

			/**
			 * Process one item.
			 *
			 * @param mixed $item Queue item.
			 *
			 * @return mixed
			 */
			protected function task( $item ) {
				return $item;
			}

			/**
			 * Match the cancellation override used by older Premium releases.
			 *
			 * @return void
			 */
			public function cancel_process() {
				if ( ! parent::is_queue_empty() ) {
					parent::cancel();
				}
			}

			/**
			 * Return buffered queue items.
			 *
			 * @return array
			 */
			public function buffered_items() {
				return $this->data;
			}
		};

		$process->push_to_queue( 'legacy-item' );
		$process->cancel_process();

		$this->assertSame( array(), $process->buffered_items() );
	}
}
