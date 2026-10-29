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
	 * Load the compatibility hooks with WPML present.
	 */
	public function setUp(): void {
		parent::setUp();
		\Mockery::namedMock( 'SitePress' );
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
			\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( $localized[ $index ] );
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
		\WP_Mock::onFilter( 'wpml_permalink' )->with( $url, 'es', true )->reply( 'https://es.example.test/hola/' );

		try {
			$this->assertSame( array( $url, 'https://es.example.test/hola/' ), add_language_urls_to_queue( array( $url ) ) );
		} finally {
			unset( $GLOBALS['sitepress'] );
		}
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
