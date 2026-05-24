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
