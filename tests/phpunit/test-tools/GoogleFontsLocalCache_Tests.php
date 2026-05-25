<?php
/**
 * Google Fonts local cache tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Google Fonts local cache test case.
 */
class GoogleFontsLocalCache_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'utils.php',
		'classes/GoogleFontsLocalCache.php',
	);

	/**
	 * It downloads allowed stylesheets and rewrites font files locally.
	 */
	public function test_stylesheet_and_font_files_are_cached_locally() {
		$this->mock_filesystem_helpers();
		$this->clean_cache_dir();

		$stylesheet_url = 'https://fonts.googleapis.com/css?family=Inter&display=swap';
		$font_url       = 'https://fonts.gstatic.com/s/inter/v12/font.woff2';
		$stylesheet     = '@font-face{font-family:Inter;src:url(' . $font_url . ') format("woff2");}';

		\WP_Mock::userFunction(
			'wp_remote_get',
			array(
				'times'  => 2,
				'return' => function ( $url ) use ( $stylesheet_url, $font_url, $stylesheet ) {
					if ( $stylesheet_url === $url ) {
						return array( 'body' => $stylesheet );
					}

					if ( $font_url === $url ) {
						return array( 'body' => 'font-binary' );
					}

					return array( 'body' => '' );
				},
			)
		);
		\WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
		\WP_Mock::userFunction( 'wp_remote_retrieve_response_code', array( 'return' => 200 ) );
		\WP_Mock::userFunction(
			'wp_remote_retrieve_body',
			array(
				'return' => function ( $response ) {
					return $response['body'];
				},
			)
		);
		\WP_Mock::onFilter( 'powered_cache_google_fonts_cache_ttl' )->with( 2592000 )->reply( 2592000 );

		$cache     = new GoogleFontsLocalCache();
		$local_url = $cache->get_stylesheet_url( $stylesheet_url );
		$css_file  = $cache->cache_dir() . md5( $stylesheet_url ) . '.css';

		$this->assertSame( 'https://example.test/wp-content/cache/powered-cache/google-fonts/' . md5( $stylesheet_url ) . '.css', $local_url );
		$this->assertFileExists( $css_file );
		$this->assertStringContainsString( 'cache/powered-cache/google-fonts/' . md5( $font_url ) . '.woff2', file_get_contents( $css_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertFileExists( $cache->cache_dir() . md5( $font_url ) . '.woff2' );
	}

	/**
	 * It leaves non-font URLs unchanged.
	 */
	public function test_unknown_stylesheet_hosts_are_not_fetched() {
		$this->mock_filesystem_helpers();

		\WP_Mock::userFunction( 'wp_remote_get', array( 'times' => 0 ) );

		$cache = new GoogleFontsLocalCache();
		$url   = 'https://example.com/css?family=Inter';

		$this->assertSame( $url, $cache->get_stylesheet_url( $url ) );
	}

	/**
	 * Mock small WordPress filesystem/url helpers.
	 */
	private function mock_filesystem_helpers() {
		\WP_Mock::userFunction(
			'trailingslashit',
			array(
				'return' => function ( $path ) {
					return rtrim( $path, '/\\' ) . '/';
				},
			)
		);
		\WP_Mock::userFunction(
			'content_url',
			array(
				'return' => function ( $path = '' ) {
					return 'https://example.test/wp-content/' . ltrim( $path, '/' );
				},
			)
		);
		\WP_Mock::userFunction(
			'wp_mkdir_p',
			array(
				'return' => function ( $dir ) {
					return is_dir( $dir ) || mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
				},
			)
		);
	}

	/**
	 * Reset the cache directory for deterministic assertions.
	 */
	private function clean_cache_dir() {
		$dir = WP_CONTENT_DIR . '/cache/powered-cache/google-fonts/';

		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( glob( $dir . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}
}
