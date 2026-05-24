<?php
/**
 * Cache preloader tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\CachePreloader;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Testable cache preloader.
 */
class CachePreloader_Testable extends CachePreloader {

	/**
	 * Whether the expensive preload path was reached.
	 *
	 * @var bool
	 */
	public $continued = false;

	/**
	 * Allow tests to call the protected task method.
	 *
	 * @param mixed $item Queue item.
	 *
	 * @return mixed
	 */
	public function run_task( $item ) {
		return $this->task( $item );
	}

	/**
	 * Determine if processing can continue.
	 *
	 * @return bool
	 */
	public function should_continue() {
		$this->continued = true;

		return false;
	}
}

/**
 * Cache preloader test case.
 */
class CachePreloader_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Async/ActionSchedulerProcess.php',
		'classes/Async/CachePreloader.php',
	);

	/**
	 * It skips queued work when cache preload is disabled after scheduling.
	 */
	public function test_task_skips_preload_when_cache_preload_is_disabled() {
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			array(
				'times'  => 1,
				'return' => array(
					'enable_cache_preload'    => false,
					'enable_page_cache'       => true,
					'preload_request_interval' => 2,
				),
			)
		);

		\WP_Mock::userFunction(
			'PoweredCache\Utils\log',
			array(
				'times'  => 1,
				'return' => true,
			)
		);

		$preloader = new CachePreloader_Testable();

		$this->assertFalse( $preloader->run_task( 'https://example.test/' ) );
		$this->assertFalse( $preloader->continued );
		$this->assertConditionsMet();
	}

	/**
	 * It skips queued work when page cache is disabled after scheduling.
	 */
	public function test_task_skips_preload_when_page_cache_is_disabled() {
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			array(
				'times'  => 1,
				'return' => array(
					'enable_cache_preload'    => true,
					'enable_page_cache'       => false,
					'preload_request_interval' => 2,
				),
			)
		);

		\WP_Mock::userFunction(
			'PoweredCache\Utils\log',
			array(
				'times'  => 1,
				'return' => true,
			)
		);

		$preloader = new CachePreloader_Testable();

		$this->assertFalse( $preloader->run_task( 'https://example.test/' ) );
		$this->assertFalse( $preloader->continued );
		$this->assertConditionsMet();
	}
}
