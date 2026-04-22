<?php
/**
 * CDN tests
 */

namespace PoweredCache;

class CDN_Tests extends TestCase {

	protected $testFiles = [
		'utils.php',
		'constants.php',
	];

	public function setUp(): void {
		parent::setUp();

		global $powered_cache_cdn_addresses, $is_apache;

		$is_apache                   = false;
		$powered_cache_cdn_addresses = [
			'all'   => [ 'cdn.example.test' ],
			'image' => [ 'img.example.test' ],
			'css'   => [ 'css.example.test' ],
		];

		$_SERVER['HTTP_HOST'] = 'example.test';

		\WP_Mock::userFunction(
			'get_option',
			[
				'return' => [
					'cdn_rejected_files' => 'skip-this',
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
		\WP_Mock::userFunction(
			'home_url',
			[
				'return' => 'https://example.test',
			]
		);
	}

	public function test_rewriter_replaces_full_and_relative_asset_urls(): void {
		$html = '<img src="https://example.test/wp-content/uploads/photo.jpg"><link href="/wp-content/themes/app/style.css">';

		$rewritten = CDN::rewriter( $html );

		$this->assertStringContainsString( 'https://img.example.test/wp-content/uploads/photo.jpg', $rewritten );
		$this->assertStringContainsString( '//css.example.test/wp-content/themes/app/style.css', $rewritten );
	}

	public function test_rewriter_keeps_excluded_and_existing_cdn_urls(): void {
		$html = '<img src="https://example.test/wp-content/uploads/skip-this.jpg"><script src="https://cdn.example.test/app.js"></script>';

		$rewritten = CDN::rewriter( $html );

		$this->assertStringContainsString( 'https://example.test/wp-content/uploads/skip-this.jpg', $rewritten );
		$this->assertStringContainsString( 'https://cdn.example.test/app.js', $rewritten );
	}

	public function test_cdn_optimizer_url_uses_zone_specific_hostname(): void {
		$cdn = new CDN();

		$optimized_url = $cdn->cdn_optimizer_url( 'https://example.test/wp-content/cache/min/app.css', 'app.css' );

		$this->assertSame( '//css.example.test/wp-content/cache/min/app.css', $optimized_url );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'], $GLOBALS['powered_cache_cdn_addresses'] );

		parent::tearDown();
	}
}
