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
}
