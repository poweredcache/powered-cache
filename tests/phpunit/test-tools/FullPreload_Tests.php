<?php
/**
 * Tests for the resumable full-cache preload.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\FullPreload;

/**
 * Full preload lifecycle tests.
 */
class FullPreload_Tests extends TestCase {

	/**
	 * Source files needed in isolated test processes.
	 *
	 * @var array
	 */
	protected $testFiles = [ // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Async/FullPreload.php',
		'classes/Async/ActionSchedulerProcess.php',
		'classes/Async/CachePreloader.php',
		'classes/Preloader.php',
		'utils.php',
	];

	/**
	 * Run a manually selected language in scheduled mode with automatic preload disabled.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_full_run_resumes_after_an_enqueue_failure_with_only_one_pending_action() {
		$options  = [];
		$actions  = [];
		$recovery = false;
		$fail     = false;
		$fail_checkpoint = false;
		$settings = [
			'enable_page_cache'        => true,
			'enable_cache_preload'     => false,
			'preload_full_mode'        => 'scheduled',
			'preload_public_posts'    => true,
			'enable_sitemap_preload'   => true,
			'gzip_compression'        => false,
			'preload_request_interval' => 0,
		];

		$previous_db = $GLOBALS['wpdb'] ?? null;
		$wpdb = \Mockery::mock();
		$wpdb->dbname  = 'test';
		$wpdb->options = 'wp_options';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( $query ) {
				return $query;
			}
		);
		$wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$GLOBALS['wpdb'] = $wpdb;

		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', [ 'return' => $settings ] );
		\WP_Mock::userFunction( 'get_current_blog_id', [ 'return' => 1 ] );
		\WP_Mock::userFunction( 'wp_generate_uuid4', [ 'return' => 'some-run' ] );
		\WP_Mock::userFunction( 'absint', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		\WP_Mock::userFunction( 'has_filter', [ 'return' => true ] );

		\WP_Mock::userFunction(
			'get_option',
			[
				'return' => static function ( $option, $default = false ) use ( &$options ) {
					return $options[ $option ] ?? $default;
				},
			]
		);
		\WP_Mock::userFunction(
			'update_option',
			[
				'return' => static function ( $option, $value ) use ( &$options, &$fail_checkpoint ) {
					if ( $fail_checkpoint && 'powered_cache_full_preload_state' === $option ) {
						$fail_checkpoint = false;
						return false;
					}
					$options[ $option ] = $value;
					return true;
				},
			]
		);
		\WP_Mock::userFunction(
			'delete_option',
			[
				'return' => static function ( $option ) use ( &$options ) {
					unset( $options[ $option ] );
					return true;
				},
			]
		);
		\WP_Mock::userFunction(
			'wp_next_scheduled',
			[
				'return' => static function () use ( &$recovery ) {
					return $recovery;
				},
			]
		);
		\WP_Mock::userFunction(
			'wp_schedule_single_event',
			[
				'return' => static function () use ( &$recovery ) {
					$recovery = true;
					return true;
				},
			]
		);
		\WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			[
				'return' => static function () use ( &$recovery ) {
					$recovery = false;
					return 1;
				},
			]
		);
		\WP_Mock::userFunction(
			'as_has_scheduled_action',
			[
				'return' => static function ( $hook, $args ) use ( &$actions ) {
					return in_array( $args, $actions, true );
				},
			]
		);
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			[
				'return' => function ( $hook, $args, $group, $unique, $priority ) use ( &$actions, &$fail ) {
					$this->assertSame( FullPreload::STEP_HOOK, $hook );
					$this->assertSame( 20, $priority );
					if ( $fail ) {
						$fail = false;
						return 0;
					}
					$actions[] = $args;
					$this->assertCount( 1, $actions );
					return 1;
				},
			]
		);

		$urls = [];
		for ( $index = 0; $index < 55; ++$index ) {
			$urls[] = 'https://example.test/post-' . $index;
		}
		$pages = [];
		$posts = \Patchwork\redefine(
			Preloader::class . '::prepare_public_posts_urls',
			function ( $offset, $size ) use ( $urls, &$pages ) {
				$pages[] = [ $offset, $size ];
				return array_slice( $urls, $offset, $size );
			}
		);
		$eligible = \Patchwork\redefine(
			Preloader::class . '::should_preload_url',
			function () {
				return true;
			}
		);
		$warmed = [];
		$warm   = \Patchwork\redefine(
			Preloader::class . '::preload_request',
			static function ( $url ) use ( &$warmed ) {
				$warmed[] = $url;
				return [];
			}
		);
		$continue = \Patchwork\redefine(
			Async\CachePreloader::class . '::should_continue',
			function () {
				return true;
			}
		);
		$translated = 'https://example.test/es/inicio';
		$from_map   = 'https://example.test/es/mapa';
		$sitemap    = 'https://example.test/sitemap.xml';
		\WP_Mock::onFilter( 'populate_preload_queue_urls' )->with( $urls, 'es' )->reply( array_merge( [ $translated ], $urls ) );
		\WP_Mock::onFilter( 'powered_cache_full_preload_sitemaps' )->with( [] )->reply( [ $sitemap ] );
		\WP_Mock::onFilter( 'powered_cache_full_preload_read_sitemap' )->with( false, $sitemap )->reply( [ 'urls' => [ $from_map ] ] );
		\WP_Mock::userFunction( 'PoweredCache\Utils\is_url_cached', [ 'return' => false ] );

		try {
			$this->assertFalse( FullPreload::start( '', true ) );
			$this->assertTrue( FullPreload::start( 'es' ) );
			$this->assertTrue( FullPreload::start( 'es' ) );
			$this->assertSame( [ [ 'some-run', 0 ] ], $actions );
			$this->assertSame( [], $pages );
			$fail = true;
			FullPreload::run_step( ...array_shift( $actions ) );
			$this->assertSame( [], $actions );
			$this->assertTrue( $recovery );
			FullPreload::recover();
			$this->assertSame( [ [ 'some-run', 1 ] ], $actions );

			$fail_checkpoint = true;
			FullPreload::run_step( ...array_shift( $actions ) );
			$this->assertSame( [], $actions );
			FullPreload::recover();
			$this->assertSame( [ [ 'some-run', 1 ] ], $actions );

			for ( $step = 0; $actions && $step < 100; ++$step ) {
				FullPreload::run_step( ...array_shift( $actions ) );
			}
			$this->assertSame( [ [ 0, 50 ], [ 0, 50 ], [ 50, 50 ] ], $pages );
			$this->assertSame( array_merge( [ $translated ], $urls, [ $from_map ] ), $warmed );
			$this->assertSame( [], $options );
			$this->assertFalse( $recovery );
			$this->assertConditionsMet();
		} finally {
			\Patchwork\restore( $posts );
			\Patchwork\restore( $eligible );
			\Patchwork\restore( $warm );
			\Patchwork\restore( $continue );
			$GLOBALS['wpdb'] = $previous_db;
		}
	}

	/**
	 * The daily event uses the site timezone rather than a fixed UTC time.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_schedule_uses_the_site_timezone() {
		$timezone = new \DateTimeZone( 'Europe/Istanbul' );
		\WP_Mock::userFunction( 'get_current_blog_id', [ 'return' => 1 ] );
		\WP_Mock::userFunction( 'wp_timezone', [ 'return' => $timezone ] );
		\WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			[
				'args' => [ FullPreload::DAILY_HOOK ],
				'times' => 1,
			]
		);
		\WP_Mock::userFunction(
			'wp_schedule_single_event',
			[
				'times'  => 1,
				'return' => function ( $timestamp, $hook ) use ( $timezone ) {
					$local = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
					$this->assertSame( '02:00', $local->format( 'H:i' ) );
					$this->assertSame( FullPreload::DAILY_HOOK, $hook );
					$this->assertGreaterThan( time(), $timestamp );
					return true;
				},
			]
		);

		FullPreload::refresh_schedule(
			[
				'preload_full_mode' => 'scheduled',
				'preload_schedule_time' => '02:00',
				'enable_page_cache' => true,
			]
		);
		$this->assertConditionsMet();
	}
}
