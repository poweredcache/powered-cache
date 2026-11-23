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
	 * Number of URLs to allow before simulating a load spike.
	 *
	 * @var int
	 */
	public $urls_allowed = 0;

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

		return $this->urls_allowed-- > 0;
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
		'utils.php',
		'classes/Async/ActionSchedulerProcess.php',
		'classes/Async/CachePreloader.php',
		'classes/Preloader.php',
	);

	/**
	 * A queue containing pre-update actions and new batches uses the same hooks after upgrading.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_pending_actions_from_before_update_are_compatible() {
		$url = 'https://example.test/legacy/';
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			[
				'return' => [
					'enable_cache_preload'     => true,
					'enable_page_cache'        => true,
					'cache_mobile'             => false,
					'gzip_compression'        => false,
					'preload_request_interval' => 2,
				],
			]
		);
		\WP_Mock::userFunction( 'absint', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'PoweredCache\Utils\log', [ 'return' => true ] );
		\WP_Mock::userFunction( 'as_has_scheduled_action', [ 'times' => 0 ] );
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			[
				'times'  => 1,
				'args'   => [ \Mockery::type( 'int' ), 'powered_cache_preload', [ $url, 4 ], 'powered-cache', false, 20 ],
				'return' => 1,
			]
		);
		$checked = [];
		\WP_Mock::userFunction(
			'PoweredCache\Utils\is_url_cached',
			[
				'times'  => 3,
				'return' => static function ( $url ) use ( &$checked ) {
					$checked[] = $url;
					return true;
				},
			]
		);

		$preloader = new CachePreloader_Testable();
		$this->assertSame( $url, $preloader->process_scheduled_item( ...json_decode( '["https://example.test/legacy/",3]', true ) ) );
		$preloader->urls_allowed = 3;
		$this->assertFalse( $preloader->process_scheduled_item( ...json_decode( '["https://example.test/legacy/"]', true ) ) );
		$this->assertFalse( $preloader->process_scheduled_item( ...json_decode( '[["https://example.test/new-1/","https://example.test/new-2/"]]', true ) ) );
		$preloader->process_complete();
		$this->assertSame( [ $url, 'https://example.test/new-1/', 'https://example.test/new-2/' ], $checked );
		$this->assertConditionsMet();
	}

	/**
	 * It enqueues unique URLs in small, lower-priority batches.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_dispatch_batches_urls_and_uses_lower_priority() {
		\WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => static function ( $value ) {
					return json_encode( $value );
				},
			)
		);
		\WP_Mock::onFilter( 'powered_cache_preload_action_priority' )->with( 20 )->reply( 42 );

		$first   = 'https://example.test/first/';
		$second  = 'https://example.test/' . str_repeat( 'a', 4100 );
		$third   = 'https://example.test/' . str_repeat( 'b', 4100 );
		$actions = [];
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			array(
				'times'  => 3,
				'return' => static function ( $hook, $args, $group, $unique, $priority ) use ( &$actions ) {
					$actions[] = array( $hook, $args, $group, $unique, $priority );
					return 2 === count( $actions ) ? 0 : 1;
				},
			)
		);

		$preloader = new CachePreloader();
		$preloader->push_to_queue( $first )->push_to_queue( $second )->push_to_queue( $first )->push_to_queue( $third );
		$this->assertFalse( $preloader->dispatch() );
		$this->assertTrue( $preloader->dispatch() );
		$this->assertSame(
			array(
				array( 'powered_cache_preload', array( array( $first, $second ) ), 'powered-cache', false, 42 ),
				array( 'powered_cache_preload', array( array( $third ) ), 'powered-cache', false, 42 ),
				array( 'powered_cache_preload', array( array( $third ) ), 'powered-cache', false, 42 ),
			),
			$actions
		);
	}

	/**
	 * URLs that fit the original argument format still queue and survive a failed dispatch.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_dispatch_preserves_a_url_too_large_to_batch() {
		$prefix = 'https://example.test/';
		$url    = $prefix . str_repeat( 'a', 7993 - strlen( $prefix ) );
		$this->assertSame( 8000, strlen( json_encode( [ $url ] ) ) );
		\WP_Mock::userFunction(
			'wp_json_encode',
			[
				'return' => static function ( $value ) {
					return json_encode( $value );
				},
			]
		);
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			[
				'times'           => 2,
				'args'            => [ 'powered_cache_preload', [ $url ], 'powered-cache', false, 20 ],
				'return_in_order' => [ 0, 1 ],
			]
		);

		$preloader = new CachePreloader();
		$preloader->push_to_queue( $url );
		$this->assertFalse( $preloader->dispatch() );
		$this->assertTrue( $preloader->dispatch() );
		$this->assertConditionsMet();
	}

	/**
	 * It bounds payload size and retains every URL in a large preload run.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_dispatch_schedules_ten_thousand_urls_in_bounded_actions() {
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			[
				'return' => [
					'enable_cache_preload'     => true,
					'enable_page_cache'        => true,
					'cache_mobile'             => false,
					'gzip_compression'        => false,
					'preload_request_interval' => 2,
				],
			]
		);
		\WP_Mock::userFunction( 'absint', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction(
			'wp_json_encode',
			[
				'return' => static function ( $value ) {
					return json_encode( $value );
				},
			]
		);

		$preloader = new CachePreloader_Testable();
		$preloader->urls_allowed = PHP_INT_MAX;
		$expected  = [];
		for ( $index = 0; $index < 10000; ++$index ) {
			$url        = 'https://example.test/product/' . $index . '/';
			$expected[] = $url;
			$preloader->push_to_queue( $url );
		}

		// Individually valid arguments that cannot fit together in the same action.
		foreach ( [ 'a', 'b' ] as $character ) {
			$url        = 'https://example.test/' . str_repeat( $character, 4100 );
			$expected[] = $url;
			$preloader->push_to_queue( $url );
		}

		$batches = [];
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			[
				'times'  => 2002,
				'return' => function ( $hook, $args ) use ( &$batches ) {
					$this->assertSame( 'powered_cache_preload', $hook );
					$this->assertLessThanOrEqual( 8000, strlen( json_encode( [ $args[0], 1 ] ) ) );
					$batches[] = $args[0];
					return 1;
				},
			]
		);

		$this->assertTrue( $preloader->dispatch() );
		$this->assertCount( 502, $batches );
		$this->assertSame( $expected, array_merge( ...$batches ) );

		$processed = [];
		\WP_Mock::userFunction( 'PoweredCache\Utils\log', [ 'return' => true ] );
		\WP_Mock::userFunction(
			'PoweredCache\Utils\is_url_cached',
			[
				'times'  => 10002,
				'return' => static function ( $url ) use ( &$processed ) {
					$processed[] = $url;
					return true;
				},
			]
		);
		for ( $index = 0; isset( $batches[ $index ] ); ++$index ) {
			$before = count( $processed );
			$this->assertFalse( $preloader->process_scheduled_item( $batches[ $index ] ) );
			$this->assertLessThanOrEqual( 5, count( $processed ) - $before );
		}
		$this->assertEqualsCanonicalizing( $expected, $processed );
		$this->assertConditionsMet();
	}

	/**
	 * It retries only URLs left in a batch if server load rises mid-action.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_action_retries_remaining_urls_at_preload_priority() {
		$first  = 'https://example.test/first/';
		$second = 'https://example.test/second/';
		$third  = 'https://example.test/third/';
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			array(
				'return' => array(
					'enable_cache_preload'     => true,
					'enable_page_cache'        => true,
					'preload_request_interval' => 2,
					'gzip_compression'        => false,
					'cache_mobile'            => false,
				),
			)
		);
		\WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
		\WP_Mock::userFunction( 'PoweredCache\Utils\log', array( 'times' => 3 ) );
		\WP_Mock::userFunction(
			'PoweredCache\Utils\is_url_cached',
			array(
				'times' => 1,
				'args' => array( $first, false, false ),
				'return' => true,
			)
		);
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			array(
				'times'  => 1,
				'args'   => array( \Mockery::type( 'int' ), 'powered_cache_preload', array( array( $second, $third ), 1 ), 'powered-cache', false, 20 ),
				'return' => 1,
			)
		);

		$preloader = new CachePreloader_Testable();
		$preloader->urls_allowed = 1;
		$this->assertSame( array( $second, $third ), $preloader->process_scheduled_item( array( $first, $second, $third ) ) );
		$this->assertConditionsMet();
	}

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
