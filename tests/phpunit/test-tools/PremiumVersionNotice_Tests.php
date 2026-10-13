<?php
/**
 * Premium version notice tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use function PoweredCache\Admin\Notices\is_premium_version_outdated;

/**
 * Premium version notice test case.
 */
class PremiumVersionNotice_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'admin/notices.php',
	);

	/**
	 * It flags Premium releases older than the supported minimum.
	 */
	public function test_flags_premium_releases_before_minimum() {
		$this->assertTrue( is_premium_version_outdated( '3.7.1' ) );
		$this->assertTrue( is_premium_version_outdated( '3.9' ) );
	}

	/**
	 * It accepts the minimum release, newer releases and pre-releases of the minimum.
	 */
	public function test_accepts_supported_premium_releases() {
		$this->assertFalse( is_premium_version_outdated( '4.0' ) );
		$this->assertFalse( is_premium_version_outdated( '4.0.0' ) );
		$this->assertFalse( is_premium_version_outdated( '4.1.2' ) );
		$this->assertFalse( is_premium_version_outdated( '4.0.0-beta.1' ) );
	}

	/**
	 * It does not warn when the version cannot be read.
	 */
	public function test_ignores_unknown_versions() {
		$this->assertFalse( is_premium_version_outdated( '' ) );
		$this->assertFalse( is_premium_version_outdated( 'dev' ) );
	}
}
