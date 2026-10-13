<?php
/**
 * Exclusion pattern helper tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Optimizer\Helper;

/**
 * Exclusion pattern helper test case.
 */
class OptimizerHelperExclusions_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Optimizer/Helper.php',
		'classes/AdvancedCache.php',
	);

	/**
	 * It accepts plain strings and regex fragments, and rejects malformed ones.
	 */
	public function test_validates_exclusion_patterns() {
		$this->assertTrue( Helper::is_valid_exclusion_pattern( 'jquery.min.js' ) );
		$this->assertTrue( Helper::is_valid_exclusion_pattern( 'wp-content/themes/(foo|bar)/' ) );
		$this->assertFalse( Helper::is_valid_exclusion_pattern( 'hero-(image' ) );
		$this->assertFalse( Helper::is_valid_exclusion_pattern( '#comments' ) );
		$this->assertFalse( Helper::is_valid_exclusion_pattern( '(*' ) );
	}

	/**
	 * It skips empty and malformed patterns when building the combined regex.
	 */
	public function test_build_exclusion_regex_skips_invalid_patterns() {
		$this->assertSame(
			'#(jquery\.js|skip-lazy)#',
			Helper::build_exclusion_regex( array( 'jquery\.js', '', '  ', 'hero-(image', 'skip-lazy', 'skip-lazy' ) )
		);
	}

	/**
	 * It never matches when no usable pattern remains.
	 *
	 * A naive implode of an empty list builds `#()#`, which matches every subject.
	 */
	public function test_empty_exclusion_list_matches_nothing() {
		$this->assertSame( '', Helper::build_exclusion_regex( array() ) );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- HTML fixture, not rendered output.
		$this->assertFalse( Helper::matches_exclusion( array(), '<link rel="stylesheet" href="style.css">' ) );
		$this->assertFalse( Helper::matches_exclusion( array( 'hero-(image' ), 'hero-(image' ) );
	}

	/**
	 * It keeps matching valid patterns when another line is malformed.
	 */
	public function test_valid_patterns_still_match_next_to_invalid_ones() {
		$patterns = array( 'hero-(image', 'skip-lazy' );

		$this->assertTrue( Helper::matches_exclusion( $patterns, '<img class="skip-lazy" src="a.jpg">' ) );
		$this->assertFalse( Helper::matches_exclusion( $patterns, '<img class="lazy" src="a.jpg">' ) );
	}

	/**
	 * It applies the exclusion helper to script exclusions.
	 */
	public function test_is_excluded_js_ignores_malformed_lines() {
		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			array(
				'return' => array(
					'excluded_js_files' => "analytics-(broken\nvendor/jquery",
				),
			)
		);

		$this->assertTrue( Helper::is_excluded_js( 'https://example.test/vendor/jquery.js' ) );
		$this->assertFalse( Helper::is_excluded_js( 'https://example.test/app.js' ) );
	}

	/**
	 * It removes malformed fragments before writing page-cache and server rules.
	 */
	public function test_advanced_cache_exclusions_ignore_malformed_lines() {
		$settings        = array(
			'rejected_cookies'     => "valid_cookie\nbroken-(cookie",
			'rejected_user_agents' => "ValidBot\nbroken-(agent",
			'rejected_uri'         => "private/.*\nbroken-(uri",
		);
		$cookie_patterns = array(
			'valid_cookie',
			'broken-(cookie',
			'wp-postpass',
			'wordpressuser_',
			'wordpresspass_',
			'wordpress_sec_',
			'wordpress_logged_in_',
			'powered_cache_commented_posts',
			'comment_author_',
			'comment_author_email_',
			'comment_author_url_',
		);
		$user_agents     = array( 'ValidBot', 'broken-(agent', 'facebookexternalhit' );
		$uri_patterns    = array( 'private/.*', 'broken-(uri' );

		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', array( 'return' => $settings ) );
		\WP_Mock::onFilter( 'powered_cache_rejected_cookies' )->with( $cookie_patterns )->reply( $cookie_patterns );
		\WP_Mock::onFilter( 'powered_cache_rejected_user_agents' )->with( $user_agents )->reply( $user_agents );
		\WP_Mock::onFilter( 'powered_cache_rejected_uri_list' )->with( $uri_patterns )->reply( $uri_patterns );

		$cookies = AdvancedCache::get_rejected_cookies();

		$this->assertContains( 'valid_cookie', $cookies );
		$this->assertNotContains( 'broken-(cookie', $cookies );
		$this->assertSame( array( 'ValidBot', 'facebookexternalhit' ), AdvancedCache::get_rejected_user_agents() );
		$this->assertSame( array( 'private/.*' ), AdvancedCache::get_rejected_uri() );
	}

	/**
	 * Never Cache URL rules as entered on real sites, mixing paths and wildcards.
	 *
	 * @return array
	 */
	private function wildcard_uri_rules() {
		return array(
			'/wp-admin/',
			'/wp-json/',
			'/?elementor-preview=*',
			'*elementor*',
			'*elementor-pro*',
			'/wp-content/plugins/elementor*',
			'/wp-content/plugins/elementor-pro*',
			'*action=elementor',
			'/wishlist',
		);
	}

	/**
	 * Match a request URI the way the page cache drop-in does: each rule is anchored.
	 *
	 * @param array  $rules Normalized rules.
	 * @param string $uri   Request URI.
	 *
	 * @return bool
	 */
	private function drop_in_rejects( array $rules, $uri ) {
		foreach ( $rules as $rule ) {
			if ( preg_match( '#^(' . $rule . ')$#', $uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * It converts wildcard lines and leaves regex lines untouched.
	 */
	public function test_normalizes_wildcard_patterns() {
		$this->assertSame( '.*elementor.*', Helper::normalize_exclusion_pattern( '*elementor*' ) );
		$this->assertSame( '/\?elementor\-preview\=.*', Helper::normalize_exclusion_pattern( '/?elementor-preview=*' ) );
		$this->assertSame( '/wp\-content/plugins/elementor.*', Helper::normalize_exclusion_pattern( '/wp-content/plugins/elementor*' ) );
		$this->assertSame( '.*\#comments', Helper::normalize_exclusion_pattern( '*#comments' ) );

		// Regex users writing ".*" get the same result in both modes.
		$this->assertSame( 'private/.*', Helper::normalize_exclusion_pattern( 'private/.*' ) );

		// Lines without "*" or with regex-only characters stay regex.
		$this->assertSame( '/wishlist', Helper::normalize_exclusion_pattern( '/wishlist' ) );
		$this->assertSame( '/product/(.*)', Helper::normalize_exclusion_pattern( '/product/(.*)' ) );
		$this->assertSame( '^/shop/.*$', Helper::normalize_exclusion_pattern( '^/shop/.*$' ) );
		$this->assertSame( '\d+*', Helper::normalize_exclusion_pattern( '\d+*' ) );
	}

	/**
	 * It accepts wildcard rules that are not valid regex on their own.
	 */
	public function test_wildcard_rules_are_valid_patterns() {
		foreach ( $this->wildcard_uri_rules() as $rule ) {
			$this->assertTrue( Helper::is_valid_exclusion_pattern( $rule ), $rule );
		}

		$this->assertTrue( Helper::is_wildcard_exclusion_pattern( '*elementor*' ) );
		$this->assertFalse( Helper::is_wildcard_exclusion_pattern( '/product/(.*)' ) );
		$this->assertFalse( Helper::is_wildcard_exclusion_pattern( '/wishlist' ) );
	}

	/**
	 * It rejects the URLs that wildcard Never Cache rules were written for.
	 */
	public function test_wildcard_uri_rules_match_intended_urls() {
		$rules = Helper::normalize_exclusion_patterns( $this->wildcard_uri_rules() );

		$this->assertCount( 9, $rules );

		foreach ( array(
			'/wp-admin/',
			'/wp-json/',
			'/?elementor-preview=123&ver=1',
			'/sample-page/?elementor-preview=5',
			'/elementor-library/foo/',
			'/wp-content/plugins/elementor/assets/x.js',
			'/wp-content/plugins/elementor-pro/assets/y.js',
			'/shop/?action=elementor',
			'/wishlist',
		) as $uri ) {
			$this->assertTrue( $this->drop_in_rejects( $rules, $uri ), $uri );
		}

		foreach ( array( '/', '/blog/hello-world/', '/wishlist/', '/shop/' ) as $uri ) {
			$this->assertFalse( $this->drop_in_rejects( $rules, $uri ), $uri );
		}
	}

	/**
	 * It keeps the query of full-URL wildcard rules, and keeps path-only matching for regex rules.
	 */
	public function test_full_url_rejected_uri_rules() {
		$rules = array(
			'https://example.test/?preview=*',
			'https://example.test/checkout/?step=2',
		);

		\WP_Mock::userFunction( 'PoweredCache\Utils\get_settings', array( 'return' => array( 'rejected_uri' => implode( "\n", $rules ) ) ) );
		\WP_Mock::onFilter( 'powered_cache_rejected_uri_list' )->with( $rules )->reply( $rules );

		$normalized = AdvancedCache::get_rejected_uri();

		$this->assertSame( array( '/\?preview\=.*', '/checkout/' ), $normalized );
		$this->assertTrue( $this->drop_in_rejects( $normalized, '/?preview=abc' ) );
		$this->assertFalse( $this->drop_in_rejects( $normalized, '/' ) );
	}
}
