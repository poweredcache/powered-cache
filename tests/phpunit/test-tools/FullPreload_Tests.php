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
		$retry_delays = [];
		$fail_checkpoint = false;
		$clock = 1800000000.0;
		$started_at = $clock;
		$queued_at = 0;
		$other_action_ready = false;
		$log_messages = [];
		$settings = [
			'enable_page_cache'        => true,
			'enable_cache_preload'     => false,
			'preload_full_mode'        => 'scheduled',
			'preload_public_posts'    => true,
			'enable_sitemap_preload'   => true,
			'gzip_compression'        => false,
			'preload_request_interval' => 2,
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
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing(
			static function ( $prefix ) {
				return $prefix;
			}
		);
		$chunk_names = static function () use ( &$options ) {
			return array_values(
				array_filter(
					array_keys( $options ),
					static function ( $name ) {
						return 0 === strpos( $name, 'powered_cache_full_preload_chunk_' );
					}
				)
			);
		};
		$wpdb->shouldReceive( 'get_col' )->andReturnUsing( $chunk_names );
		$wpdb->shouldReceive( 'query' )->andReturnUsing(
			static function () use ( &$options, $chunk_names ) {
				$names = $chunk_names();
				foreach ( $names as $name ) {
					unset( $options[ $name ] );
				}
				return count( $names );
			}
		);
		$GLOBALS['wpdb'] = $wpdb;

		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', [ 'return' => $settings ] );
		\WP_Mock::userFunction( 'get_current_blog_id', [ 'return' => 1 ] );
		\WP_Mock::userFunction( 'wp_generate_uuid4', [ 'return' => 'some-run' ] );
		\WP_Mock::userFunction( 'absint', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		\WP_Mock::userFunction( 'has_filter', [ 'return' => true ] );
		\WP_Mock::userFunction( 'wp_cache_delete_multiple', [ 'args' => [ \Mockery::type( 'array' ), 'options' ] ] );
		\WP_Mock::userFunction(
			'PoweredCache\Utils\log',
			[
				'return' => static function ( $message ) use ( &$log_messages ) {
					$log_messages[] = $message;
				},
			]
		);
		\WP_Mock::userFunction(
			'as_get_scheduled_actions',
			[
				'return' => static function () use ( &$other_action_ready ) {
					return $other_action_ready ? [ 99 ] : [];
				},
			]
		);
		\WP_Mock::userFunction(
			'PoweredCache\Async\microtime',
			[
				'args'   => [ true ],
				'return' => static function () use ( &$clock ) {
					return $clock;
				},
			]
		);
		\WP_Mock::userFunction(
			'PoweredCache\Async\time',
			[
				'return' => static function () use ( &$clock ) {
					return (int) $clock;
				},
			]
		);
		\WP_Mock::userFunction(
			'PoweredCache\Async\time_nanosleep',
			[
				'return' => static function ( $seconds, $nanoseconds ) use ( &$clock ) {
					$clock += $seconds + $nanoseconds / 1000000000;
					return true;
				},
			]
		);

		\WP_Mock::userFunction(
			'get_option',
			[
				'return' => static function ( $option, $default = false ) use ( &$options ) {
					return $options[ $option ] ?? $default;
				},
			]
		);
		\WP_Mock::userFunction(
			'as_schedule_single_action',
			[
				'return' => function ( $timestamp, $hook, $args, $group, $unique, $priority ) use ( &$actions, &$retry_delays, &$queued_at, &$clock ) {
					$this->assertSame( FullPreload::STEP_HOOK, $hook );
					$this->assertSame( 19, $priority );
					$retry_delays[] = $timestamp - $clock;
					$queued_at      = $timestamp;
					$actions[]      = $args;
					$this->assertCount( 1, $actions );
					return 1;
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
				'return' => function ( $hook, $args, $group, $unique, $priority ) use ( &$actions, &$fail, &$queued_at ) {
					$this->assertSame( FullPreload::STEP_HOOK, $hook );
					$this->assertSame( 19, $priority );
					if ( $fail ) {
						$fail = false;
						return 0;
					}
					$queued_at = 0;
					$actions[] = $args;
					$this->assertCount( 1, $actions );
					return 1;
				},
			]
		);

		$urls = [];
		for ( $index = 0; $index < 1000; ++$index ) {
			$urls[] = 'https://example.test/post-' . $index;
		}
		$pages = [];
		$posts = \Patchwork\redefine(
			Preloader::class . '::prepare_public_posts_page',
			function ( $offset, $size, $after_id ) use ( $urls, &$pages ) {
				$pages[] = [ $offset, $size, $after_id ];
				$keyed   = array_combine( range( 1, count( $urls ) ), $urls );
				return array_slice( $keyed, $offset, $size, true );
			}
		);
		$eligible = \Patchwork\redefine(
			Preloader::class . '::should_preload_url',
			function () {
				return true;
			}
		);
		$warmed      = [];
		$fail_http   = true;
		$failed_http = [];
		$request_times = [];
		$warm   = \Patchwork\redefine(
			Preloader::class . '::preload_request',
			static function ( $url ) use ( &$warmed, &$fail_http, &$failed_http, &$request_times, &$clock, &$other_action_ready ) {
				$request_times[] = $clock;
				if ( $fail_http ) {
					$fail_http     = false;
					$failed_http[] = $url;
					return [ 'response' => [ 'code' => 503 ] ];
				}
				$warmed[] = $url;
				if ( 1 === count( $warmed ) ) {
					$other_action_ready = true;
				}
				return [];
			}
		);
		$continue = \Patchwork\redefine(
			Async\CachePreloader::class . '::should_continue',
			function () {
				return true;
			}
		);
		$interval = \Patchwork\redefine(
			Preloader::class . '::get_request_interval',
			static function ( $delay ) {
				return $delay;
			}
		);
		$translated = 'https://example.test/es/inicio';
		$from_map   = 'https://example.test/es/mapa';
		$sitemap    = 'https://example.test/sitemap.xml';
		\WP_Mock::onFilter( 'populate_preload_queue_urls' )->with( $urls, 'es' )->reply( array_merge( [ $translated ], $urls ) );
		\WP_Mock::onFilter( 'powered_cache_full_preload_sitemaps' )->with( [] )->reply( [ $sitemap ] );
		\WP_Mock::onFilter( 'powered_cache_full_preload_read_sitemap' )->with( false, $sitemap, 0 )->reply( [ 'urls' => [ $from_map ] ] );
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

			$lost_chunk = false;
			$largest_batch = 0;
			$yielded_to_other_action = false;
			$runner_started_at = $clock;
			for ( $step = 0; $actions && $step < 400; ++$step ) {
				$state = $options['powered_cache_full_preload_state'];
				if ( ! $lost_chunk && 'warm' === $state['phase'] && $state['chunks'] ) {
					unset( $options[ 'powered_cache_full_preload_chunk_' . $state['chunk_slot'] . '_0' ] );
					$lost_chunk = true;
				}
				// Model a 30-second Action Scheduler runner started by cron once a minute.
				if ( $queued_at > $clock || $clock - $runner_started_at >= 30 ) {
					$clock = ceil( max( $runner_started_at + 60, $queued_at ) / 60 ) * 60;
					$runner_started_at = $clock;
				}
				$before = count( $request_times );
				FullPreload::run_step( ...array_shift( $actions ) );
				if ( $other_action_ready ) {
					$this->assertSame( $before + 1, count( $request_times ) );
					$this->assertTrue( $options['powered_cache_full_preload_state']['yielded'] );
					$this->assertGreaterThan( $clock, $queued_at );
					$other_action_ready = false;
					$yielded_to_other_action = true;
				}
				$largest_batch = max( $largest_batch, count( $request_times ) - $before );
			}
			$this->assertTrue( $lost_chunk );
			$expected_pages = [];
			$url_count      = count( $urls );
			for ( $offset = 0; $offset <= $url_count; $offset += 50 ) {
				$expected_pages[] = [ $offset, 50, $offset ];
			}
			$this->assertSame( array_merge( [ [ 0, 50, 0 ] ], $expected_pages, $expected_pages ), $pages );
			$this->assertSame( [ $translated ], $failed_http );
			$this->assertCount( 4, $retry_delays );
			$this->assertGreaterThanOrEqual( 59, $retry_delays[0] );
			$this->assertGreaterThanOrEqual( 14, $retry_delays[1] );
			$this->assertEquals( 2, $retry_delays[2] ); // Yield to the other scheduled action.
			$this->assertEquals( 2, $retry_delays[3] ); // The sitemap fetch is a separate step.
			$this->assertTrue( $yielded_to_other_action );
			$this->assertSame( 4, $largest_batch );
			$this->assertLessThan( 7200, $clock - $started_at );
			$request_count = count( $request_times );
			for ( $index = 1; $index < $request_count; ++$index ) {
				$this->assertGreaterThanOrEqual( 2, $request_times[ $index ] - $request_times[ $index - 1 ] );
			}
			$this->assertSame( array_merge( [ $translated ], $urls, [ $from_map ] ), $warmed );
			$this->assertSame( [], $options );
			$this->assertFalse( $recovery );
			$log = implode( "\n", $log_messages );
			$this->assertStringContainsString( 'Full preload some-run step 0 (home): Started on demand or on schedule.', $log );
			$this->assertMatchesRegularExpression( '/Full preload some-run step \d+ \(warm\): Page request failed \(HTTP 503\)\. Retrying after 15 seconds\./', $log );
			$this->assertMatchesRegularExpression( '/Full preload some-run step \d+ \(warm\): Yielded to other scheduled actions\./', $log );
			$this->assertMatchesRegularExpression( '/Full preload some-run step \d+ \(warm\): Finished processing the run\./', $log );
			$this->assertConditionsMet();
		} finally {
			\Patchwork\restore( $posts );
			\Patchwork\restore( $eligible );
			\Patchwork\restore( $warm );
			\Patchwork\restore( $continue );
			\Patchwork\restore( $interval );
			$GLOBALS['wpdb'] = $previous_db;
		}
	}

	/**
	 * Automatic discovery resumes from both cursor fields, including when posts share a date.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_automatic_discovery_resumes_with_the_publication_date_cursor() {
		\WP_Mock::userFunction( 'wp_generate_uuid4', [ 'return' => 'test-run' ] );
		\WP_Mock::userFunction( 'has_filter', [ 'return' => false ] );

		$date  = '2026-10-17 12:00:00';
		$urls  = array_fill_keys( range( 100, 51 ), 'https://example.test/post' );
		$older = [ 50 => 'https://example.test/older' ];

		$preloader = \Mockery::mock( Preloader::class );
		$preloader->shouldReceive( 'prepare_recent_public_posts_page' )->once()->with( 0, 50, 0, '' )->andReturn( [ $urls, $date ] );
		$preloader->shouldReceive( 'prepare_recent_public_posts_page' )->once()->with( 50, 50, 51, $date )->andReturn( [ $older, $date ] );
		$preloader->shouldReceive( 'prepare_public_posts_page' )->once()->with( 50, 50, 55 )->andReturn( [ 56 => 'https://example.test/next' ] );
		$factory = \Patchwork\redefine(
			Preloader::class . '::factory',
			static function () use ( $preloader ) {
				return $preloader;
			}
		);

		try {
			$new_state = new \ReflectionMethod( FullPreload::class, 'new_state' );
			$discover  = new \ReflectionMethod( FullPreload::class, 'discover' );
			if ( PHP_VERSION_ID < 80100 ) {
				$new_state->setAccessible( true );
				$discover->setAccessible( true );
			}
			$state          = $new_state->invoke( null, '', true );
			$state['phase'] = 'posts';
			$settings       = [ 'preload_public_posts' => true ];

			list( $state ) = $discover->invoke( null, $state, [], $settings, false );
			$this->assertSame( 51, $state['post_id'] );
			$this->assertSame( $date, $state['post_date'] );
			list( $next, $found ) = $discover->invoke( null, $state, [], $settings, false );
			$this->assertSame( 'terms', $next['phase'] );
			$this->assertSame( array_values( $older ), $found );

			// A run already using ascending IDs finishes discovery in that order after updating.
			unset( $state['post_date'] );
			$state['post_id'] = 55;
			list( $next ) = $discover->invoke( null, $state, [], $settings, false );
			$this->assertSame( 56, $next['post_id'] );
			$this->assertArrayNotHasKey( 'post_date', $next );
			$this->assertConditionsMet();
		} finally {
			\Patchwork\restore( $factory );
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
