<?php
/**
 * Htaccess exclusion rule tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Optimizer\Helper;

/**
 * Htaccess exclusion rule test case.
 */
class HtaccessExclusions_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/Optimizer/Helper.php',
		'classes/Htaccess.php',
	);

	/**
	 * Call the private RewriteCond helper.
	 *
	 * @param array $fragments Regex fragments.
	 *
	 * @return string
	 */
	private function alternation( array $fragments ) {
		$method = new \ReflectionMethod( Htaccess::class, 'rewrite_cond_alternation' );
		$method->setAccessible( true );

		return $method->invoke( null, $fragments );
	}

	/**
	 * It keeps RewriteCond patterns free of unescaped spaces.
	 */
	public function test_rewrite_cond_alternation_escapes_spaces() {
		$fragments = Helper::normalize_exclusion_patterns( array( 'Mozilla Foo*', 'Bad\ Bot', 'Googlebot' ) );
		$pattern   = $this->alternation( $fragments );

		$this->assertStringNotContainsString( ' ', str_replace( '\ ', '', $pattern ) );
		$this->assertSame( 1, preg_match( '#(' . $pattern . ')#', 'Mozilla Foo/1.0' ) );
		$this->assertSame( 1, preg_match( '#(' . $pattern . ')#', 'Bad Bot' ) );
	}
}
