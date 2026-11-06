<?php
/**
 * WPML compatibility regression tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use function PoweredCache\Compat\WPML\add_language_urls_to_queue;
use function PoweredCache\Compat\WPML\add_post_language_urls;
use function PoweredCache\Compat\WPML\purge_translated_post;

/**
 * Isolate the SitePress stub and conditional compatibility function declarations.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WPML_Tests extends TestCase {

	/**
	 * WPML's SitePress stand-in, retained for component tests.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $sitepress_stub;

	/**
	 * Home URL used by the isolated WordPress environment.
	 *
	 * @var string
	 */
	private $home_url = 'https://example.test/';

	/**
	 * Load the compatibility hooks with WPML present.
	 */
	public function setUp(): void {
		parent::setUp();
		\WP_Mock::userFunction(
			'home_url',
			array(
				'return' => function () {
					return $this->home_url;
				},
			)
		);
		\WP_Mock::userFunction(
			'untrailingslashit',
			array(
				'return' => function ( $url ) {
					return rtrim( $url, '/\\' );
				},
			)
		);
		$this->sitepress_stub = \Mockery::namedMock( 'SitePress' );
		\WP_Mock::expectFilterAdded( 'powered_cache_post_related_urls', 'PoweredCache\Compat\WPML\add_post_language_urls', 10, 2 );
		\WP_Mock::expectActionAdded( 'wpml_after_save_post', 'PoweredCache\Compat\WPML\purge_translated_post' );
		\WP_Mock::expectActionAdded( 'wpml_pro_translation_completed', 'PoweredCache\Compat\WPML\purge_translated_post' );
		require_once PROJECT . 'compat/plugins/wpml.php';
	}

	/**
	 * The URL collector passes the saved post ID to compatibility filters.
	 */
	public function test_related_url_filter_receives_post_id() {
		require_once PROJECT . 'utils.php';
		\WP_Mock::userFunction( 'get_post_status', array( 'return' => 'draft' ) );
		\WP_Mock::userFunction( 'get_post', array( 'return' => null ) );
		\WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
		\WP_Mock::userFunction( 'get_permalink', array( 'return' => false ) );
		\WP_Mock::onFilter( 'powered_cache_post_related_urls' )->with( array(), 123 )->reply( array( 'https://example.test/es/' ) );

		$this->assertSame( array( 'https://example.test/es/' ), \PoweredCache\Utils\get_post_related_urls( 123 ) );
	}

	/**
	 * Config generation must not create a second WPML instance and query filters.
	 */
	public function test_domain_configuration_reuses_existing_wpml_instance() {
		define( 'WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN', 2 );
		$GLOBALS['sitepress'] = $this->sitepress_stub;
		$this->sitepress_stub->shouldReceive( 'get_setting' )->with( 'language_negotiation_type' )->once()->andReturn( 1 );
		try {
			\PoweredCache\Compat\WPML\generate_configuration_for_domains( 'unused.php', '<?php', false );
			$this->assertConditionsMet();
		} finally {
			unset( $GLOBALS['sitepress'] );
		}
	}

	/**
	 * Editor REST requests must convert the actual URL, not the editor referrer's language.
	 */
	public function test_unchanged_editor_url_uses_wpml_configured_strategy() {
		$url = 'https://example.test/2026/';
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $url );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', false )->reply( $url );
		\WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		$strategy = \Mockery::mock();
		$strategy->shouldReceive( 'convert_url_string' )->once()->with( $url, 'es' )->andReturn( 'https://example.test/es/2026/' );
		$converter = \Mockery::mock();
		$converter->shouldReceive( 'get_strategy' )->once()->andReturn( $strategy );
		$GLOBALS['wpml_url_converter'] = $converter;
		try {
			$this->assertSame( 'https://example.test/es/2026/', \PoweredCache\Compat\WPML\resolve_language_url( $url, 'es' ) );
		} finally {
			unset( $GLOBALS['wpml_url_converter'] );
		}
	}

	/**
	 * REST purges must use the saved language's term identity instead of an editor-adjusted term.
	 */
	public function test_rest_taxonomy_urls_use_saved_post_language() {
		\WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
		\WP_Mock::userFunction(
			'get_object_taxonomies',
			array(
				'return' => array(
					'category' => (object) array(
						'public'       => true,
						'hierarchical' => false,
					),
				),
			)
		);
		\WP_Mock::userFunction(
			'wp_get_object_terms',
			array(
				'args'   => array(
					123,
					array( 'category' ),
					array(
						'suppress_filters' => true,
						'wpml_skip_filters' => true,
						'lang'              => 'all',
					),
				),
				'return' => array(
					(object) array(
						'term_id'  => 9,
						'taxonomy' => 'category',
					),
				),
			)
		);
		\WP_Mock::onFilter( 'wpml_object_id' )->with( 9, 'category', true, 'es' )->reply( 5 );
		\WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
		\WP_Mock::userFunction(
			'remove_filter',
			array(
				'times'  => 1,
				'return' => true,
			)
		);
		\WP_Mock::userFunction(
			'get_term_link',
			array(
				'times' => 1,
				'args' => array( 5, 'category' ),
				'return' => 'https://example.test/category/noticias/',
			)
		);
		$url = 'https://example.test/category/noticias/';
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $url );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', false )->reply( 'https://example.test/es/category/noticias/' );

		$this->assertSame( array( 'https://example.test/es/category/noticias/' ), \PoweredCache\Compat\WPML\get_post_taxonomy_urls( 123, 'es' ) );
	}

	/**
	 * A different-language editor must not replace the saved post's translated posts page.
	 */
	public function test_rest_purge_resolves_posts_page_identity_in_saved_language() {
		define( 'REST_REQUEST', true );
		\WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
		\WP_Mock::userFunction( 'get_object_taxonomies', array( 'return' => array() ) );
		\WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( 'show_on_front' ),
				'return' => 'page',
			)
		);
		\WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( 'page_for_posts' ),
				'return' => 33,
			)
		);
		\WP_Mock::onFilter( 'wpml_post_language_details' )->with( null, 123 )->reply( array( 'language_code' => 'es' ) );
		\WP_Mock::onFilter( 'wpml_object_id' )->with( 33, 'page', true, 'es' )->reply( 32 );
		\WP_Mock::userFunction(
			'get_permalink',
			array(
				'args'   => array( 32 ),
				'times'  => 1,
				'return' => 'https://example.test/es/blog-es/',
			)
		);
		$this->assertSame( array( 'https://example.test/es/blog-es/' ), add_post_language_urls( array(), 123 ) );
	}

	/**
	 * A default-language save also purges the saved post's localized archives.
	 */
	public function test_saved_post_language_is_used_for_related_urls() {
		$urls = array(
			'https://example.test/',
			'https://example.test/2026/',
			'https://example.test/author/admin/',
			'https://example.test/news/',
			'https://example.test/category/news/',
		);
		$localized = array(
			'https://example.test/es/',
			'https://example.test/es/2026/',
			'https://example.test/es/author/admin/',
			'https://example.test/es/noticias/',
			'https://example.test/es/category/noticias/',
		);

		\WP_Mock::onFilter( 'wpml_post_language_details' )->with( null, 123 )->reply( array( 'language_code' => 'es' ) );
		\WP_Mock::userFunction(
			'get_post_type',
			array(
				'args'   => array( 123 ),
				'return' => 'post',
			)
		);
		foreach ( $urls as $index => $url ) {
			if ( $index < 3 ) {
				// WPML's object resolver leaves the home, date and author URLs unchanged.
				\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $url );
				\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', false )->reply( $localized[ $index ] );
			} else {
				\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $localized[ $index ] );
			}
		}

		$this->assertSame( array_merge( $urls, $localized ), add_post_language_urls( $urls, 123 ) );
	}

	/**
	 * Include published siblings, resolving their own language despite the request language.
	 */
	public function test_purge_includes_other_published_translations() {
		\WP_Mock::userFunction(
			'get_post_type',
			array(
				'args'   => array( 123 ),
				'return' => 'product',
			)
		);
		\WP_Mock::onFilter( 'wpml_element_trid' )->with( null, 123, 'post_product' )->reply( 7 );
		\WP_Mock::onFilter( 'wpml_get_element_translations' )->with( null, 7, 'post_product' )->reply(
			array(
				(object) array(
					'element_id'    => '123',
					'language_code' => 'es',
				),
				(object) array(
					'element_id'    => '456',
					'language_code' => 'en',
				),
				(object) array(
					'element_id'    => '789',
					'language_code' => 'fr',
				),
			)
		);
		\WP_Mock::userFunction(
			'get_post_status',
			array(
				'args'   => array( '456' ),
				'return' => 'publish',
			)
		);
		\WP_Mock::userFunction(
			'get_post_status',
			array(
				'args'   => array( '789' ),
				'return' => 'draft',
			)
		);
		\WP_Mock::userFunction(
			'get_permalink',
			array(
				'times'  => 1,
				'args'   => array( '456' ),
				'return' => 'https://example.test/es/original/',
			)
		);
		\WP_Mock::onFilter( 'wpml_permalink' )->with( 'https://example.test/es/original/', 'en', true )->reply( 'https://example.test/original/' );

		$this->assertSame( array( 'https://example.test/original/' ), add_post_language_urls( array(), 123 ) );
	}

	/**
	 * Untranslated content retains its existing purge URLs.
	 */
	public function test_missing_language_details_keep_original_urls() {
		\WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
		$urls = array( 'https://example.test/post/' );

		$this->assertSame( $urls, add_post_language_urls( $urls, 123 ) );
	}

	/**
	 * Older callers can still apply the related URL filter with one argument.
	 */
	public function test_legacy_related_url_filter_call_keeps_original_urls() {
		$urls = array( 'https://example.test/post/' );

		$this->assertSame( $urls, add_post_language_urls( $urls ) );
	}

	/**
	 * Full resolution queues translated post and term slugs for a selected language.
	 */
	public function test_selected_language_preloads_resolved_slugs() {
		$_GET['lang_code'] = 'es';
		\WP_Mock::userFunction( 'sanitize_key', array( 'return_arg' => 0 ) );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( 'https://example.test/hello/', 'es', true )->reply( 'https://example.test/es/hola/' );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( 'https://example.test/category/news/', 'es', true )->reply( 'https://example.test/es/category/noticias/' );

		try {
			$this->assertSame(
				array( 'https://example.test/es/hola/', 'https://example.test/es/category/noticias/' ),
				add_language_urls_to_queue( array( 'https://example.test/hello/', 'https://example.test/category/news/', 'https://example.test/hello/' ) )
			);
		} finally {
			unset( $_GET['lang_code'], $GLOBALS['sitepress'] );
		}
	}

	/**
	 * All-language preload keeps originals and deduplicates translated permalinks.
	 */
	public function test_all_languages_preload_uses_resolved_urls() {
		$GLOBALS['sitepress'] = new class() {
			/**
			 * Return the active languages.
			 *
			 * @return array
			 */
			public function get_active_languages() {
				return array( array( 'code' => 'en' ), array( 'code' => 'es' ) );
			}
		};
		$url = 'https://example.test/hello/';
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'en', true )->reply( $url );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'en', false )->reply( $url );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( 'https://es.example.test/hola/' );

		try {
			$this->assertSame( array( $url, 'https://es.example.test/hola/' ), add_language_urls_to_queue( array( $url ) ) );
		} finally {
			unset( $GLOBALS['sitepress'] );
		}
	}

	/**
	 * Keep each language home when WPML's object resolver cannot resolve it.
	 *
	 * @dataProvider unresolved_home_provider
	 *
	 * @param string $home_url     Original home URL.
	 * @param string $resolved_url Unchanged URL returned by full resolution.
	 * @param string $spanish_url  Spanish home URL from normal conversion.
	 * @param string $french_url   French home URL from normal conversion.
	 */
	public function test_preload_keeps_language_homes( $home_url, $resolved_url, $spanish_url, $french_url ) {
		$this->home_url = $home_url;
		$GLOBALS['sitepress'] = new class() {
			/**
			 * Return the active languages.
			 *
			 * @return array
			 */
			public function get_active_languages() {
				return array( array( 'code' => 'en' ), array( 'code' => 'es' ), array( 'code' => 'fr' ) );
			}
		};
		$language_homes = array(
			'en' => $home_url,
			'es' => $spanish_url,
			'fr' => $french_url,
		);
		foreach ( $language_homes as $code => $language_home ) {
			\WP_Mock::onFilter( 'wpml_permalink' )->with( $home_url, $code, true )->reply( $resolved_url );
			\WP_Mock::onFilter( 'wpml_permalink' )->with( $home_url, $code, false )->reply( $language_home );
		}

		try {
			$this->assertSame( array( $home_url, $spanish_url, $french_url ), add_language_urls_to_queue( array( $home_url ) ) );
		} finally {
			unset( $GLOBALS['sitepress'] );
		}
	}

	/**
	 * The same fallback applies when preloading only one selected language.
	 */
	public function test_selected_language_preload_keeps_home_and_translated_slugs() {
		$_GET['lang_code'] = 'es';
		\WP_Mock::userFunction( 'sanitize_key', array( 'return_arg' => 0 ) );
		$home = 'https://example.test/';
		$post = 'https://example.test/hello/';
		$term = 'https://example.test/category/news/';
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $home, 'es', true )->reply( $home );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $home, 'es', false )->reply( 'https://example.test/es/' );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $post, 'es', true )->reply( 'https://example.test/es/hola/' );
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $term, 'es', true )->reply( 'https://example.test/es/category/noticias/' );

		try {
			$this->assertSame(
				array( 'https://example.test/es/', 'https://example.test/es/hola/', 'https://example.test/es/category/noticias/' ),
				add_language_urls_to_queue( array( $home, $post, $term ) )
			);
		} finally {
			unset( $_GET['lang_code'], $GLOBALS['sitepress'] );
		}
	}

	/**
	 * A missing object translation must not turn into an invented language-prefixed URL.
	 */
	public function test_preload_does_not_invent_urls_for_untranslated_objects() {
		$_GET['lang_code'] = 'es';
		\WP_Mock::userFunction( 'sanitize_key', array( 'return_arg' => 0 ) );
		$urls = array( 'https://example.test/', 'https://example.test/english-only/', 'https://example.test/category/english-only/' );
		foreach ( $urls as $url ) {
			\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $url );
			// These fabricated URLs would redirect; only the home fallback may be used.
			\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', false )->reply( str_replace( 'example.test/', 'example.test/es/', $url ) );
		}
		try {
			$this->assertSame(
				array( 'https://example.test/es/', 'https://example.test/english-only/', 'https://example.test/category/english-only/' ),
				add_language_urls_to_queue( $urls )
			);
		} finally {
			unset( $_GET['lang_code'], $GLOBALS['sitepress'] );
		}
	}

	/**
	 * Preserve the home URL contract across directory and domain language formats.
	 *
	 * @return array
	 */
	public function unresolved_home_provider() {
		return array(
			'directories' => array( 'https://example.test/', 'https://example.test/', 'https://example.test/es/', 'https://example.test/fr/' ),
			'slash only'  => array( 'https://example.test/', 'https://example.test', 'https://example.test/es/', 'https://example.test/fr/' ),
			'subsite'     => array( 'https://example.test/blog/', 'https://example.test/blog/', 'https://example.test/blog/es/', 'https://example.test/blog/fr/' ),
			'domains'     => array( 'https://example.test/', 'https://example.test/', 'https://es.example.test/', 'https://fr.example.test/' ),
		);
	}

	/**
	 * Exercise WPML's real API, object resolver, converter and directory strategy.
	 *
	 * Set POWERED_CACHE_WPML_SOURCE to a separately installed WPML plugin directory.
	 * Only the post/term lookup results and WordPress environment are test doubles.
	 *
	 * @dataProvider wpml_request_context_provider
	 *
	 * @param bool $editor_context Whether to reproduce the Spanish editor REST referrer.
	 */
	public function test_real_wpml_components_resolve_home_and_translated_slugs( $editor_context ) {
		$wpml_source = getenv( 'POWERED_CACHE_WPML_SOURCE' );
		if ( ! $wpml_source || ! is_file( $wpml_source . '/vendor/autoload.php' ) ) {
			$this->markTestSkipped( 'Set POWERED_CACHE_WPML_SOURCE to test against an installed WPML package.' );
		}

		require_once $wpml_source . '/vendor/autoload.php';
		require_once $wpml_source . '/inc/functions.php';

		$home = 'https://example.test/';
		$_SERVER['REQUEST_URI'] = '/wp-cron.php';
		$_SERVER['HTTP_HOST']   = 'example.test';
		unset( $_SERVER['HTTP_REFERER'] );
		if ( $editor_context ) {
			define( 'REST_REQUEST', true );
			$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/123';
			$_SERVER['HTTP_REFERER'] = 'https://example.test/wp-admin/post.php?post=123&action=edit&lang=es';
		}
		\WP_Mock::userFunction(
			'trailingslashit',
			array(
				'return' => function ( $url ) {
					return rtrim( $url, '/\\' ) . '/';
				},
			)
		);
		\WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		\WP_Mock::userFunction( 'current_filter', array( 'return' => 'wpml_permalink' ) );
		\WP_Mock::userFunction( 'get_object_taxonomies', array( 'return' => array() ) );
		\WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		\WP_Mock::userFunction(
			'wp_parse_url',
			array(
				'return' => function ( $url, $component = -1 ) {
					return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				},
			)
		);
		\WP_Mock::userFunction(
			'get_option',
			array(
				'return' => function ( $option ) use ( $home ) {
					return 'permalink_structure' === $option ? '/%postname%/' : $home;
				},
			)
		);
		\Mockery::namedMock( 'WP_Http' );
		$language  = 'en';
		$sitepress = $this->sitepress_stub;
		$sitepress->shouldReceive( 'get_current_language' )->andReturnUsing(
			function () use ( &$language ) {
				return $language;
			}
		);
		$sitepress->shouldReceive( 'switch_lang' )->andReturnUsing(
			function ( $code = null ) use ( &$language ) {
				$language = $code ? $code : 'en';
			}
		);
		$link_targets = \Mockery::mock( 'WPML_Translate_Link_Targets' );
		$link_targets->shouldReceive( 'convert_url' )->andReturnUsing(
			function ( $url ) use ( &$language ) {
				$translations = array(
					'es' => array(
						'https://example.test/hello/'         => 'https://example.test/es/hola/',
						'https://example.test/category/news/' => 'https://example.test/es/category/noticias/',
					),
					'fr' => array(
						'https://example.test/hello/'         => 'https://example.test/fr/bonjour/',
						'https://example.test/category/news/' => 'https://example.test/fr/category/actualites/',
					),
				);
				return $translations[ $language ][ $url ] ?? $url;
			}
		);
		$resolver   = new \WPML_Resolve_Absolute_Url( $sitepress, $link_targets );
		$strategy   = new \WPML_URL_Converter_Subdir_Strategy( false, 'en', array( 'en', 'es', 'fr' ), array() );
		$converter  = new \WPML_URL_Converter( $strategy, $resolver, 'en', array( 'en', 'es', 'fr' ) );
		$url_helper = \Mockery::mock( 'WPML_URL_Converter_Url_Helper' )->makePartial();
		$url_helper->shouldReceive( 'get_abs_home', 'get_unfiltered_home_option' )->andReturn( $home );
		$converter->set_url_helper( $url_helper );
		$GLOBALS['wpml_url_converter'] = $converter;
		$handler                      = new \WPML_API_Hook_Permalink( $converter, $resolver );
		foreach ( array( $home, 'https://example.test/2026/', 'https://example.test/hello/', 'https://example.test/category/news/' ) as $url ) {
			foreach ( array( 'en', 'es', 'fr' ) as $code ) {
				foreach ( array( true, false ) as $full_resolution ) {
					\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, $code, $full_resolution )->reply(
						new \WP_Mock\InvokedFilterValue( array( $handler, 'wpml_permalink_filter' ) )
					);
				}
			}
		}

		try {
			$referrer_before = $_SERVER['HTTP_REFERER'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Assert exact preservation of the test fixture.
			$this->assertSame( $home, $handler->wpml_permalink_filter( $home, 'es', true ) );
			if ( $editor_context ) {
				$this->assertSame( $home, $handler->wpml_permalink_filter( $home, 'es', false ) );
			}
			$this->assertSame( 'https://example.test/es/', \PoweredCache\Compat\WPML\resolve_language_url( $home, 'es' ) );
			$this->assertSame( 'https://example.test/fr/', \PoweredCache\Compat\WPML\resolve_language_url( $home, 'fr' ) );
			$this->assertSame( 'https://example.test/es/2026/', \PoweredCache\Compat\WPML\resolve_language_url( 'https://example.test/2026/', 'es' ) );
			$this->assertSame( 'https://example.test/es/hola/', \PoweredCache\Compat\WPML\resolve_language_url( 'https://example.test/hello/', 'es' ) );
			$this->assertSame( 'https://example.test/es/category/noticias/', \PoweredCache\Compat\WPML\resolve_language_url( 'https://example.test/category/news/', 'es' ) );

			\WP_Mock::onFilter( 'wpml_post_language_details' )->with( null, 123 )->reply( array( 'language_code' => 'es' ) );
			\WP_Mock::userFunction( 'get_post_type', array( 'return' => 'post' ) );
			$purge_urls = add_post_language_urls( array( $home, 'https://example.test/2026/' ), 123 );
			$this->assertContains( 'https://example.test/es/', $purge_urls );
			$this->assertContains( 'https://example.test/es/2026/', $purge_urls );

			$GLOBALS['sitepress'] = new class() {
				/**
				 * Return active languages for the actual URL component check.
				 *
				 * @return array
				 */
				public function get_active_languages() {
					return array( array( 'code' => 'en' ), array( 'code' => 'es' ), array( 'code' => 'fr' ) );
				}
			};
			$this->assertSame(
				array(
					$home,
					'https://example.test/hello/',
					'https://example.test/category/news/',
					'https://example.test/es/',
					'https://example.test/es/hola/',
					'https://example.test/es/category/noticias/',
					'https://example.test/fr/',
					'https://example.test/fr/bonjour/',
					'https://example.test/fr/category/actualites/',
				),
				add_language_urls_to_queue( array( $home, 'https://example.test/hello/', 'https://example.test/category/news/' ) )
			);
			$this->assertSame( $referrer_before, $_SERVER['HTTP_REFERER'] ?? null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Assert exact preservation of the test fixture.
			$this->assertSame( 'en', $language );

			$other_strategies = array(
				array(
					new \WPML_URL_Converter_Domain_Strategy(
						array(
							'es' => 'https://es.example.test',
							'fr' => 'https://fr.example.test',
						),
						'en',
						array( 'en', 'es', 'fr' )
					),
					'https://es.example.test/',
					'https://es.example.test/2026/',
				),
				array( new \WPML_URL_Converter_Parameter_Strategy( 'en', array( 'en', 'es', 'fr' ) ), 'https://example.test/?lang=es', 'https://example.test/2026/?lang=es' ),
			);
			foreach ( $other_strategies as list( $other_strategy, $expected_home, $expected_year ) ) {
				$other_converter = new \WPML_URL_Converter( $other_strategy, $resolver, 'en', array( 'en', 'es', 'fr' ) );
				$other_converter->set_url_helper( $url_helper );
				$other_handler = new \WPML_API_Hook_Permalink( $other_converter, $resolver );
				$GLOBALS['wpml_url_converter'] = $other_converter;
				foreach ( array( $home, 'https://example.test/2026/' ) as $url ) {
					foreach ( array( true, false ) as $full_resolution ) {
						\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', $full_resolution )->reply(
							new \WP_Mock\InvokedFilterValue( array( $other_handler, 'wpml_permalink_filter' ) )
						);
					}
				}
				$this->assertSame( $expected_home, \PoweredCache\Compat\WPML\resolve_language_url( $home, 'es' ) );
				$this->assertSame( $expected_year, \PoweredCache\Compat\WPML\resolve_language_url( 'https://example.test/2026/', 'es' ) );
			}
		} finally {
			unset( $GLOBALS['wpml_url_converter'], $GLOBALS['sitepress'] );
		}
	}

	/**
	 * Exercise normal requests and the WPML editor-referrer failure separately.
	 *
	 * @return array
	 */
	public function wpml_request_context_provider() {
		return array(
			'normal request' => array( false ),
			'Spanish editor REST' => array( true ),
		);
	}

	/**
	 * Completed WPML saves purge only when page caching is enabled and public.
	 */
	public function test_completed_translation_triggers_purge() {
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', array( 'return' => array( 'enable_page_cache' => true ) ) );
		\WP_Mock::userFunction(
			'get_post_status',
			array(
				'args'   => array( 123 ),
				'return' => 'publish',
			)
		);
		$cache = \Mockery::mock( 'alias:PoweredCache\AdvancedCache' );
		$cache->shouldReceive( 'factory' )->once()->andReturnSelf();
		$cache->shouldReceive( 'purge_post' )->once()->with( 123 );

		purge_translated_post( 123 );
		$this->assertConditionsMet();
	}

	/**
	 * WPML events respect disabled page caching.
	 */
	public function test_completed_translation_respects_disabled_cache() {
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', array( 'return' => array( 'enable_page_cache' => false ) ) );
		\WP_Mock::userFunction( 'get_post_status', array( 'times' => 0 ) );

		purge_translated_post( 123 );
		$this->assertConditionsMet();
	}

	/**
	 * Saving an unfinished translation must not purge a published sibling.
	 */
	public function test_unpublished_translation_does_not_trigger_extra_purge() {
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', array( 'return' => array( 'enable_page_cache' => true ) ) );
		\WP_Mock::userFunction( 'get_post_status', array( 'return' => 'draft' ) );
		$cache = \Mockery::mock( 'alias:PoweredCache\AdvancedCache' );
		$cache->shouldNotReceive( 'factory' );

		purge_translated_post( 123 );
		$this->assertConditionsMet();
	}
}
