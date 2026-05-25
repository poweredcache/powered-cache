<?php
/**
 * LazyLoad tests
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Lazy-load behavior tests.
 */
class LazyLoad_Tests extends TestCase {

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = [
		'utils.php',
		'constants.php',
	];
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	/**
	 * Set up test doubles.
	 */
	public function setUp(): void {
		parent::setUp();

		global $is_apache;

		$is_apache = false;

		\WP_Mock::passthruFunction( 'esc_attr' );
		\WP_Mock::userFunction(
			'get_option',
			[
				'return' => [
					'lazy_load_exclusions' => 'skip-lazy',
				],
			]
		);
		\WP_Mock::userFunction(
			'wp_parse_args',
			[
				'return' => function ( $args, $defaults ) {
					return array_merge( $defaults, $args );
				},
			]
		);
	}

	/**
	 * It rewrites supported image tags.
	 */
	public function test_filter_images_rewrites_supported_images(): void {
		$html = '<p><img src="https://example.test/image.jpg" class="alignwide" srcset="https://example.test/image-2x.jpg 2x"></p>';

		$filtered = LazyLoad::filter_images( $html );

		$this->assertStringContainsString( 'data-lazy-type="image"', $filtered );
		$this->assertStringContainsString( 'data-lazy-src="https://example.test/image.jpg"', $filtered );
		$this->assertStringContainsString( 'data-lazy-srcset="https://example.test/image-2x.jpg 2x"', $filtered );
		$this->assertStringContainsString( 'class="lazy lazy-hidden alignwide"', $filtered );
		$this->assertStringContainsString( '<noscript><img src="https://example.test/image.jpg"', $filtered );
	}

	/**
	 * It skips image tags that should not be lazy-loaded.
	 */
	public function test_filter_images_skips_data_uri_and_configured_exclusions(): void {
		$html = '<img src="data:image/png;base64,abc"><img src="https://example.test/skip.jpg" class="skip-lazy">';

		$filtered = LazyLoad::filter_images( $html );

		$this->assertSame( $html, $filtered );
	}

	/**
	 * It rewrites inline CSS background image URLs.
	 */
	public function test_filter_background_images_rewrites_inline_background_urls(): void {
		$html = '<div class="hero" style=\'background-image:url(https://example.test/hero.jpg); color: #fff;\'>Hero</div>';

		$filtered = LazyLoad::filter_background_images( $html );

		$this->assertStringContainsString( 'data-lazy-type="background"', $filtered );
		$this->assertStringContainsString( 'data-lazy-style="background-image:url(https://example.test/hero.jpg); color: #fff;"', $filtered );
		$this->assertStringContainsString( 'style="background-image:none; color: #fff;"', $filtered );
		$this->assertStringContainsString( 'class="lazy lazy-hidden hero"', $filtered );
	}

	/**
	 * It skips background image URLs that should not be lazy-loaded.
	 */
	public function test_filter_background_images_skips_data_uri_and_configured_exclusions(): void {
		$html = '<div class="skip-lazy" style="background-image:url(https://example.test/skip.jpg)"></div><div style="background-image:url(data:image/png;base64,abc)"></div>';

		$filtered = LazyLoad::filter_background_images( $html );

		$this->assertSame( $html, $filtered );
	}

	/**
	 * It rewrites regular iframes but preserves Gravity Forms iframes.
	 */
	public function test_filter_iframes_rewrites_regular_iframes_and_skips_gravity_forms(): void {
		$html = '<iframe src="https://player.example.test/embed"></iframe><iframe id="gform_ajax_frame_1" src="about:blank"></iframe>';

		$filtered = LazyLoad::filter_iframes( $html );

		$this->assertStringContainsString( 'data-lazy-type="iframe"', $filtered );
		$this->assertStringContainsString( 'data-lazy-src="<iframe src="https://player.example.test/embed"></iframe>"', $filtered );
		$this->assertStringContainsString( 'id="gform_ajax_frame_1"', $filtered );
	}

	/**
	 * It replaces YouTube iframes with lightweight thumbnails.
	 */
	public function test_replace_youtube_iframe_with_thumbnail(): void {
		$html = '<iframe src="https://www.youtube.com/embed/abc123_XY?start=30"></iframe>';

		$filtered = LazyLoad::replace_youtube_iframe_with_thumbnail( $html );

		$this->assertStringContainsString( 'class="pcll-youtube-player"', $filtered );
		$this->assertStringContainsString( 'data-src="https://www.youtube.com/embed/abc123_XY?start=30"', $filtered );
		$this->assertStringContainsString( 'https://img.youtube.com/vi/abc123_XY/0.jpg', $filtered );
		$this->assertStringNotContainsString( '<iframe', $filtered );
	}
}
