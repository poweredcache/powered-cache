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

	/**
	 * Build a wpdb double for table optimization tests.
	 *
	 * @param array $tables Table names returned by information_schema.
	 *
	 * @return \Mockery\MockInterface
	 */
	private function mock_wpdb( array $tables ) {
		global $wpdb;

		if ( ! defined( 'DB_NAME' ) ) {
			define( 'DB_NAME', 'wordpress' );
		}

		$wpdb              = \Mockery::mock( 'wpdb' );
		$wpdb->base_prefix = 'wp_';

		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing(
			function ( $text ) {
				return addcslashes( $text, '_%\\' );
			}
		);
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, ...$args ) {
				$this->assertStringContainsString( 'table_name LIKE %s', $query );
				$this->assertSame( array( 'wordpress', 'wp\\_%' ), $args );

				return 'prepared-query';
			}
		);
		$wpdb->shouldReceive( 'get_col' )->with( 'prepared-query' )->andReturn( $tables );

		return $wpdb;
	}

	/**
	 * It optimizes fragmented tables using the positional table name column.
	 */
	public function test_optimize_tables_runs_for_fragmented_tables() {
		\WP_Mock::userFunction( 'PoweredCache\\Utils\\log' );
		\WP_Mock::userFunction( 'is_multisite', array( 'return' => false ) );
		\WP_Mock::userFunction( 'esc_sql', array( 'return_arg' => 0 ) );

		$wpdb = $this->mock_wpdb( array( 'wp_options', 'wp_postmeta' ) );
		$wpdb->shouldReceive( 'query' )->once()->with( 'OPTIMIZE TABLE `wp_options`' );
		$wpdb->shouldReceive( 'query' )->once()->with( 'OPTIMIZE TABLE `wp_postmeta`' );

		$optimizer = new DatabaseOptimizer_Test_Process();

		$this->assertFalse( $optimizer->run_task( 'db_cleanup_optimize_tables' ) );
		$this->assertConditionsMet();
	}

	/**
	 * It leaves network-wide table optimization to the main site.
	 */
	public function test_optimize_tables_skips_network_subsites() {
		global $wpdb;

		\WP_Mock::userFunction( 'PoweredCache\\Utils\\log' );
		\WP_Mock::userFunction( 'is_multisite', array( 'return' => true ) );
		\WP_Mock::userFunction( 'is_main_site', array( 'return' => false ) );

		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->shouldNotReceive( 'get_col' );
		$wpdb->shouldNotReceive( 'query' );

		$optimizer = new DatabaseOptimizer_Test_Process();

		$this->assertFalse( $optimizer->run_task( 'db_cleanup_optimize_tables' ) );
		$this->assertConditionsMet();
	}

	/**
	 * It ignores empty table names returned by the database.
	 */
	public function test_get_fragmented_tables_filters_empty_names() {
		$this->mock_wpdb( array( 'wp_options', '', null ) );

		$this->assertSame( array( 'wp_options' ), DatabaseOptimizer::get_fragmented_tables() );
	}
}
