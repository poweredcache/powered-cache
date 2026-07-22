<?php
/**
 * Database optimizer tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\DatabaseOptimizer;

/**
 * Testable database optimizer.
 */
class DatabaseOptimizer_Test_Process extends DatabaseOptimizer {

	/**
	 * Run a database optimization task.
	 *
	 * @param string $item Optimization task name.
	 *
	 * @return mixed
	 */
	public function run_task( $item ) {
		return $this->task( $item );
	}
}

/**
 * Database optimizer test case.
 */
class DatabaseOptimizer_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Async/ActionSchedulerProcess.php',
		'classes/Async/DatabaseOptimizer.php',
	);

	/**
	 * It forces expired transient cleanup in the database for persistent caches.
	 */
	public function test_expired_transient_cleanup_forces_database_cleanup() {
		\WP_Mock::userFunction(
			'PoweredCache\\Utils\\log',
			array(
				'times' => 1,
			)
		);

		\WP_Mock::userFunction(
			'delete_expired_transients',
			array(
				'times' => 1,
				'args'  => array( true ),
			)
		);

		$optimizer = new DatabaseOptimizer_Test_Process();
		$result    = $optimizer->run_task( 'db_cleanup_expired_transients' );

		$this->assertFalse( $result );
		$this->assertConditionsMet();
	}
}
