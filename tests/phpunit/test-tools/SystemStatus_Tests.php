<?php
/**
 * System status tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * System status test case.
 */
class SystemStatus_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'utils.php',
	);

	/**
	 * It reports page cache readiness issues.
	 */
	public function test_reports_page_cache_readiness_issues() {
		$status = new SystemStatus(
			array(
				'enable_page_cache' => true,
				'object_cache'      => 'off',
			),
			array(
				'wp_cache_enabled' => false,
			)
		);
		$report = $status->report();

		$this->assertSame( SystemStatus::STATUS_WARNING, $report['status'] );
		$this->assertCheckExists( 'page_cache', SystemStatus::STATUS_WARNING, $report['checks'] );
	}

	/**
	 * It reports object cache readiness issues.
	 */
	public function test_reports_object_cache_readiness_issues() {
		$status = new SystemStatus(
			array(
				'object_cache' => 'redis',
			),
			array(
				'object_cache_backends'      => array( 'redis' ),
				'object_cache_dropin_exists' => false,
			)
		);
		$report = $status->report();

		$this->assertSame( SystemStatus::STATUS_WARNING, $report['status'] );
		$this->assertCheckExists( 'object_cache', SystemStatus::STATUS_WARNING, $report['checks'] );
	}

	/**
	 * Assert that a system check exists.
	 *
	 * @param string $code Checks code.
	 * @param string $status Checks status.
	 * @param array  $checks Checks.
	 */
	private function assertCheckExists( $code, $status, array $checks ) {
		foreach ( $checks as $check ) {
			if ( $code === $check['code'] && $status === $check['status'] ) {
				$this->assertTrue( true );
				return;
			}
		}

		$this->fail( sprintf( 'Expected %s check with %s status.', $code, $status ) );
	}
}
