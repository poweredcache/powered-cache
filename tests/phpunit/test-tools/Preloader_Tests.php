<?php
/**
 * Preloader tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Preloader test case.
 */
class Preloader_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'classes/SettingsSchema.php',
		'classes/SettingsRepository.php',
		'utils.php',
		'classes/Preloader.php',
	);

	/**
	 * The admin request queues preparation and redirects without discovering any URLs.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider manual_preload_queue_statuses
	 *
	 * @param bool $already_queued Whether an action already exists for the language.
	 * @param int  $action_id      Action Scheduler enqueue result.
	 * @param bool $has_referrer   Whether the admin request has a referrer.
	 */
	public function test_admin_preload_redirects_after_scheduling( $already_queued, $action_id, $has_referrer ) {
		$referrer   = $has_referrer ? 'https://example.test/wp-admin/' : false;
		$fallback   = 'https://example.test/wp-admin/admin.php?page=powered-cache';
		$return_url = $has_referrer ? $referrer : $fallback;
		$status     = $already_queued || $action_id ? 'start_preload' : 'start_preload_err_queue';
		$expected   = $return_url . ( $has_referrer ? '?' : '&' ) . 'pc_action=' . $status;
		$_GET       = [
			'_wpnonce'  => 'test-nonce',
			'lang_code' => 'es',
		];

		\WP_Mock::userFunction( 'wp_unslash', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'sanitize_text_field', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'sanitize_key', [ 'return_arg' => 0 ] );
		\WP_Mock::userFunction( 'wp_verify_nonce', [ 'return' => true ] );
		\WP_Mock::userFunction( 'current_user_can', [ 'return' => true ] );
		\WP_Mock::userFunction( 'wp_get_referer', [ 'return' => $referrer ] );
		\WP_Mock::userFunction( 'admin_url', [ 'return' => $fallback ] );
		\WP_Mock::userFunction(
			'as_has_scheduled_action',
			[
				'args'   => [ 'powered_cache_prepare_manual_preload', [ 'es' ], 'powered-cache' ],
				'return' => $already_queued,
			]
		);
		\WP_Mock::userFunction(
			'as_enqueue_async_action',
			[
				'times'  => $already_queued ? 0 : 1,
				'args'   => [ 'powered_cache_prepare_manual_preload', [ 'es' ], 'powered-cache', false, 19 ],
				'return' => $action_id,
			]
		);
		\WP_Mock::userFunction(
			'add_query_arg',
			[
				'args'   => [ 'pc_action', $status, $return_url ],
				'return' => $expected,
			]
		);
		\WP_Mock::userFunction(
			'wp_safe_redirect',
			[
				'times'  => 1,
				'args'   => [ $expected ],
				'return' => static function () {
					throw new \RuntimeException( 'Redirected' );
				},
			]
		);

		$preloader = \Mockery::mock( Preloader::class )->makePartial();
		$preloader->shouldNotReceive( 'setup_preload_queue' );
		$this->expectExceptionMessage( 'Redirected' );
		$preloader->start_preload();
	}

	/**
	 * Manual preload scheduling outcomes.
	 *
	 * @return array
	 */
	public function manual_preload_queue_statuses() {
		return [
			'queued'         => [ false, 17, true ],
			'already queued' => [ true, 0, true ],
			'queue failed'   => [ false, 0, false ],
		];
	}

	/**
	 * Manual preload retains the selected language when automatic preload is disabled.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_scheduled_manual_preload_preserves_selected_language() {
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			[
				'return' => [
					'enable_page_cache' => true,
					'enable_cache_preload' => false,
				],
			]
		);
		\WP_Mock::userFunction( 'sanitize_key', [ 'return_arg' => 0 ] );
		$start = \Patchwork\redefine(
			Async\FullPreload::class . '::start',
			function ( $language, $automatic = false ) {
				$this->assertSame( 'es', $language );
				$this->assertFalse( $automatic );
				return true;
			}
		);

		$preloader = new Preloader();
		\WP_Mock::expectActionAdded( 'powered_cache_prepare_manual_preload', [ $preloader, 'run_scheduled_preload' ] );
		\WP_Mock::expectActionAdded( 'admin_post_powered_cache_preload_cache', [ $preloader, 'start_preload' ] );
		$preloader->setup();
		$preloader->run_scheduled_preload( 'es' );
		\Patchwork\restore( $start );
		$this->assertConditionsMet();
	}

	/**
	 * Full flush wakes preload after the cache directory is removed.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider flush_modes
	 *
	 * @param bool $async Whether a background purge worker runs the flush.
	 */
	public function test_full_flush_rebuilds_preload_after_removal( $async ) {
		$settings = [
			'enable_cache_preload' => true,
			'enable_page_cache' => true,
		];
		$removed  = false;
		$enqueued = false;
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', [ 'return' => $settings ] );
		\WP_Mock::userFunction(
			'PoweredCache\Utils\remove_dir',
			[
				'times'  => 1,
				'return' => static function () use ( &$removed ) {
					$removed = true;
				},
			]
		);
		$start = \Patchwork\redefine(
			Async\FullPreload::class . '::start',
			function ( $language, $automatic ) use ( &$removed, &$enqueued ) {
				$this->assertTrue( $removed );
				$this->assertSame( '', $language );
				$this->assertTrue( $automatic );
				$enqueued = true;
				return true;
			}
		);
		$preloader = new Preloader();
		\WP_Mock::expectActionAdded( 'powered_cache_full_purge_completed', [ $preloader, 'setup_preload_queue' ] );
		\WP_Mock::expectActionAdded( 'powered_cache_clean_site_cache_dir', [ $preloader, 'setup_preload_queue' ] );
		\WP_Mock::expectActionNotAdded( 'powered_cache_flushed', [ $preloader, 'setup_preload_queue' ] );
		$preloader->setup();
		\WP_Mock::onAction( 'powered_cache_full_purge_completed' )->with( null )->perform( [ $preloader, 'setup_preload_queue' ] );

		if ( $async ) {
			( new Async\CachePurger() )->process_scheduled_item( [ 'call' => 'powered_cache_flush' ] );
		} else {
			require_once PROJECT . 'admin/dashboard.php';
			\WP_Mock::userFunction( 'delete_transient', [ 'return' => true ] );
			Admin\Dashboard\purge_all_cache( [ 'async_cache_cleaning' => false ] );
		}
		\Patchwork\restore( $start );
		$this->assertTrue( $enqueued );
		$this->assertConditionsMet();
	}

	/**
	 * Flush entry points.
	 *
	 * @return array
	 */
	public function flush_modes() {
		return [
			'synchronous'  => [ false ],
			'asynchronous' => [ true ],
		];
	}

	/**
	 * Generic flush callers keep their existing behavior.
	 */
	public function test_generic_flush_does_not_rebuild_the_preload_queue() {
		\WP_Mock::userFunction( 'PoweredCache\Utils\remove_dir', [ 'times' => 1 ] );
		\WP_Mock::expectAction( 'powered_cache_flushed' );
		\WP_Mock::onAction( 'powered_cache_full_purge_completed' )->with( null )->perform(
			function () {
				$this->fail( 'Generic flush should not rebuild the preload queue.' );
			}
		);

		Utils\powered_cache_flush();
		$this->assertConditionsMet();
	}

	/**
	 * Purge-triggered preload uses the same path and query exclusions as page caching.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_queue_skips_rejected_request_uris() {
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', [ 'return' => [ 'rejected_uri' => '/private/*' ] ] );
		\WP_Mock::userFunction( 'get_current_blog_id', [ 'return' => 1 ] );
		\WP_Mock::userFunction( 'get_permalink', [ 'return' => false ] );
		\WP_Mock::userFunction(
			'wp_parse_url',
			[
				'return' => static function ( $url ) {
					return parse_url( $url );
				},
			]
		);

		$preloader = new Preloader();
		$queue     = \Mockery::mock( Async\CachePreloader::class );
		$queue->shouldReceive( 'push_to_queue' )->with( 'https://example.test/public/' )->once();
		$property = new \ReflectionProperty( Preloader::class, 'cache_preloader' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $preloader, $queue );

		$preloader->add_purged_urls_to_preload_queue( 123, [ 'https://example.test/private/page/?view=1', 'https://example.test/public/' ] );
		$this->assertConditionsMet();
	}

	/**
	 * It skips configured WooCommerce URLs with either pretty or plain permalinks.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_excludes_configured_dynamic_pages() {
		require_once PROJECT . 'compat/plugins/woocommerce.php';
		\WP_Mock::userFunction( 'get_current_blog_id', [ 'return' => 1 ] );
		\WP_Mock::userFunction(
			'wc_get_page_id',
			[
				'return' => static function ( $page ) {
					return [
						'cart' => 42,
						'checkout' => 54,
						'myaccount' => 63,
					][ $page ];
				},
			]
		);
		\WP_Mock::userFunction(
			'get_permalink',
			[
				'return' => static function ( $id ) {
					return 42 === $id ? 'https://example.test/store/basket/' : 'https://example.test/?page_id=' . $id;
				},
			]
		);
		\WP_Mock::userFunction(
			'wp_parse_url',
			[
				'return' => static function ( $url ) {
					return parse_url( $url );
				},
			]
		);
		$this->assertFalse( Compat\WooCommerce\exclude_dynamic_pages_from_preload( true, 'https://example.test/store/basket/?utm_source=test' ) );
		$this->assertFalse( Compat\WooCommerce\exclude_dynamic_pages_from_preload( true, 'https://example.test/?checkout_token=test&page_id=54' ) );
		$this->assertFalse( Compat\WooCommerce\exclude_dynamic_pages_from_preload( true, 'https://example.test/?page_id=63' ) );
		$this->assertTrue( Compat\WooCommerce\exclude_dynamic_pages_from_preload( true, 'https://example.test/?page_id=55' ) );
	}

	/**
	 * It normalizes configured font preload URLs and rejects unsafe values.
	 */
	public function test_get_preload_fonts_normalizes_safe_font_urls() {
		global $is_apache;

		$is_apache = false;

		$this->mock_preload_font_settings();

		\WP_Mock::userFunction(
			'home_url',
			array(
				'return' => function ( $path ) {
					return 'https://example.test' . $path;
				},
			)
		);

		$expected = array(
			'https://cdn.example.test/fonts/app.woff2',
			'https://example.test/wp-content/uploads/local.woff',
			'https://static.example.test/font.otf?ver=1',
		);

		\WP_Mock::onFilter( 'powered_cache_preload_fonts' )->with( $expected )->reply( $expected );

		$preloader = new Preloader();

		$this->assertSame( $expected, $preloader->get_preload_fonts() );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It prints preload hints with the correct font MIME types.
	 */
	public function test_preload_fonts_prints_link_tags() {
		global $is_apache;

		$is_apache = false;

		$this->mock_preload_font_settings();

		\WP_Mock::userFunction(
			'home_url',
			array(
				'return' => function ( $path ) {
					return 'https://example.test' . $path;
				},
			)
		);

		\WP_Mock::userFunction(
			'esc_url',
			array(
				'return_arg' => 0,
			)
		);

		\WP_Mock::userFunction(
			'esc_attr',
			array(
				'return_arg' => 0,
			)
		);

		$expected = array(
			'https://cdn.example.test/fonts/app.woff2',
			'https://example.test/wp-content/uploads/local.woff',
			'https://static.example.test/font.otf?ver=1',
		);

		\WP_Mock::onFilter( 'powered_cache_preload_fonts' )->with( $expected )->reply( $expected );

		$preloader = new Preloader();

		ob_start();
		$preloader->preload_fonts();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<link rel="preload" href="https://cdn.example.test/fonts/app.woff2" as="font" type="font/woff2" crossorigin>', $output );
		$this->assertStringContainsString( '<link rel="preload" href="https://example.test/wp-content/uploads/local.woff" as="font" type="font/woff" crossorigin>', $output );
		$this->assertStringContainsString( '<link rel="preload" href="https://static.example.test/font.otf?ver=1" as="font" type="font/otf" crossorigin>', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
		$this->assertStringNotContainsString( 'readme.txt', $output );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It schedules the full related URL list when a post purge is deferred.
	 */
	public function test_deferred_preload_queue_uses_full_related_urls() {
		$deleted_urls = array(
			'https://example.test/post/',
		);

		$related_urls = array(
			'https://example.test/post/',
			'https://example.test/category/news/',
			'https://example.test/',
		);

		\WP_Mock::userFunction(
			'wp_schedule_single_event',
			array(
				'times' => 1,
				'args'  => array(
					\Mockery::on(
						function ( $timestamp ) {
							return is_int( $timestamp ) && $timestamp >= time() && $timestamp <= time() + 20;
						}
					),
					Constants\DEFERRED_PRELOAD_QUEUE_CRON_NAME,
					array(
						'post_id' => 123,
						'urls'    => $related_urls,
					),
				),
			)
		);

		$preloader = new Preloader();
		$preloader->deferred_preload_queue( 123, $deleted_urls, $related_urls );

		$this->assertConditionsMet();
	}

	/**
	 * It keeps the two-argument deferred preload contract for older callers.
	 */
	public function test_deferred_preload_queue_keeps_deleted_urls_fallback() {
		$deleted_urls = array(
			'https://example.test/post/',
			'https://example.test/',
		);

		\WP_Mock::userFunction(
			'wp_schedule_single_event',
			array(
				'times' => 1,
				'args'  => array(
					\Mockery::on(
						function ( $timestamp ) {
							return is_int( $timestamp ) && $timestamp >= time() && $timestamp <= time() + 20;
						}
					),
					Constants\DEFERRED_PRELOAD_QUEUE_CRON_NAME,
					array(
						'post_id' => 123,
						'urls'    => $deleted_urls,
					),
				),
			)
		);

		$preloader = new Preloader();
		$preloader->deferred_preload_queue( 123, $deleted_urls );

		$this->assertConditionsMet();
	}

	/**
	 * Root-relative fonts use the current origin, including mapped domains and ports.
	 *
	 * @dataProvider font_origin_provider
	 *
	 * @param string $home_url Language-specific home URL.
	 * @param string $origin   Expected asset origin.
	 */
	public function test_root_relative_font_ignores_language_and_install_paths( $home_url, $origin ) {
		$this->mock_preload_font_settings();
		\WP_Mock::userFunction(
			'home_url',
			array(
				'args'   => array( '/' ),
				'return' => $home_url,
			)
		);

		$preloader = new Preloader();
		$fonts     = $preloader->get_preload_fonts();

		$this->assertContains( $origin . '/wp-content/uploads/local.woff', $fonts );
		$this->assertContains( 'https://cdn.example.test/fonts/app.woff2', $fonts );
	}

	/**
	 * Language and WordPress installation paths must not prefix root-relative assets.
	 *
	 * @return array
	 */
	public function font_origin_provider() {
		return array(
			'directory language' => array( 'https://example.test/es/', 'https://example.test' ),
			'subdirectory'       => array( 'https://example.test/blog/es/', 'https://example.test' ),
			'mapped domain'      => array( 'https://es.example.test/', 'https://es.example.test' ),
			'custom port'        => array( 'http://example.test:8080/es/', 'http://example.test:8080' ),
		);
	}

	/**
	 * Mock stored settings with representative font preload values.
	 */
	private function mock_preload_font_settings() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'return' => function ( $option, $default = array() ) {
					if ( \PoweredCache\Constants\SETTING_OPTION !== $option ) {
						return $default;
					}

					return array(
						'preload_fonts' => implode(
							"\n",
							array(
								'https://cdn.example.test/fonts/app.woff2',
								'/wp-content/uploads/local.woff',
								'//static.example.test/font.otf?ver=1',
								'javascript:alert(1)',
								'https://example.test/readme.txt',
							)
						),
					);
				},
			)
		);
	}
}
