<?php
/**
 * Cache purger tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Cache purger test case.
 */
class CachePurger_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'package/deliciousbrains/wp-background-processing/classes/wp-async-request.php',
		'package/deliciousbrains/wp-background-processing/classes/wp-background-process.php',
		'classes/Async/CachePurger.php',
	);

	/**
	 * It schedules cache purge items with Action Scheduler when available.
	 */
	public function test_dispatch_schedules_cache_purge_items_with_action_scheduler() {
		$item = array(
			'call' => 'powered_cache_flush',
		);

		\WP_Mock::userFunction(
			'as_unschedule_all_actions',
			array(
				'return' => null,
			)
		);

		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			array(
				'times' => 1,
				'args'  => array(
					\PoweredCache\Async\CachePurger::ACTION_SCHEDULER_HOOK,
					array( $item ),
					\PoweredCache\Async\CachePurger::ACTION_SCHEDULER_GROUP,
				),
			)
		);

		\WP_Mock::onFilter( 'powered_cache_cache_purger_use_action_scheduler' )->with( true )->reply( true );

		$purger = new Async\CachePurger();
		$result = $purger->push_to_queue( $item )->save()->dispatch();

		$this->assertTrue( $result );
		$this->assertConditionsMet();
	}

	/**
	 * It cancels Action Scheduler cache purge jobs when available.
	 */
	public function test_cancel_process_unschedules_action_scheduler_cache_purge_items() {
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			array(
				'return' => 1,
			)
		);

		\WP_Mock::userFunction(
			'as_unschedule_all_actions',
			array(
				'times' => 1,
				'args'  => array(
					\PoweredCache\Async\CachePurger::ACTION_SCHEDULER_HOOK,
					null,
					\PoweredCache\Async\CachePurger::ACTION_SCHEDULER_GROUP,
				),
			)
		);

		\WP_Mock::onFilter( 'powered_cache_cache_purger_use_action_scheduler' )->with( true )->reply( true );

		$purger = new Async\CachePurger();
		$purger->cancel_process();

		$this->assertConditionsMet();
	}
}
