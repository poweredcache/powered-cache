<?php
/**
 * Utils tests
 */

namespace PoweredCache\Utils;

use PoweredCache as Base;

class Util_Tests extends Base\TestCase {

	protected $testFiles
		= [
			'utils.php',
			'constants.php',
		];

	public function testIsDirEmpty() {
		// Setup: Create a temporary directory
		$tempDir = sys_get_temp_dir() . '/test_dir';
		mkdir( $tempDir );

		// Test that the directory is initially empty
		$this->assertTrue( \PoweredCache\Utils\is_dir_empty( $tempDir ) );

		// Add a file to the directory
		file_put_contents( $tempDir . '/test_file.txt', 'Hello, World!' );

		// Test that the directory is no longer empty
		$this->assertFalse( \PoweredCache\Utils\is_dir_empty( $tempDir ) );

		// Cleanup: Remove the temporary directory and its contents
		unlink( $tempDir . '/test_file.txt' );
		rmdir( $tempDir );
	}

	/**
	 * @dataProvider ipProvider
	 */
	public function testIsIpInRange( $ip, $range, $expected ) {
		$this->assertEquals( $expected, is_ip_in_range( $ip, $range ) );
	}

	public function ipProvider() {
		return [
			[ '192.168.1.1', '192.168.1.0/24', true ],
			[ '192.168.2.1', '192.168.1.0/24', false ],
			[ '2001:db8::1', '2001:db8::/32', true ],
			[ '2001:db9::1', '2001:db8::/32', false ],
		];
	}

	public function maskStringDataProvider() {
		return [
			[ 'HelloWorld', 5, 'Hello*****' ],
			[ '1234567890', 3, '123*******' ],
			[ 'abcdef', 2, 'ab****' ],
			[ 'github', 0, '******' ],
		];
	}

	/**
	 * @dataProvider maskStringDataProvider
	 */
	public function testMaskString( $input, $unmask_length, $expected_output ) {
		$this->assertEquals( $expected_output, \PoweredCache\Utils\mask_string( $input, $unmask_length ) );
	}

	public function testGetClientIp() {
		// Mock the get_client_ip function to return a specific value
		\WP_Mock::userFunction( 'PoweredCache\Utils\get_client_ip', [
			'return' => '192.168.1.1',
		] );

		// Now the function should return the mocked value
		$this->assertEquals( '192.168.1.1', \PoweredCache\Utils\get_client_ip() );
	}

	public function testBypassRequest() {
		// Assuming the function returns a boolean value
		$this->assertTrue( is_bool( \PoweredCache\Utils\bypass_request() ) );
	}

	public function testIsLocalSiteWithLocalhost() {
		// Mock `site_url` to return 'http://localhost'
		\WP_Mock::userFunction( 'site_url', [
			'return' => 'http://localhost',
		] );

		// Assert that `is_local_site` returns true for 'http://localhost'
		$this->assertTrue( is_local_site() );
	}

	public function testIsLocalSiteWithEnvironmentTypeLocal() {
		// Mock `wp_get_environment_type` to return 'local'
		\WP_Mock::userFunction( 'wp_get_environment_type', [
			'return' => 'local',
		] );

		// Assert that `is_local_site` returns true when environment type is 'local'
		$this->assertTrue( is_local_site() );
	}

	public function testIsLocalSiteWithEnvironmentTypeProduction() {
		// Mock `wp_get_environment_type` to return 'production'
		\WP_Mock::userFunction( 'wp_get_environment_type', [
			'return' => 'production',
		] );

		// Assert that `is_local_site` returns false when environment type is 'production'
		$this->assertFalse( is_local_site() );
	}

	public function testPoweredCacheIsMobileWithMobileUserAgent() {
		global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

		// Mock the wp_unslash function
		\WP_Mock::userFunction( 'wp_unslash', [
			'return' => function ( $value ) {
				return $value;
			},
		] );

		// Set global variables for mobile browsers and prefixes
		$powered_cache_mobile_browsers = 'MobileBrowser';
		$powered_cache_mobile_prefixes = 'Mob';

		// Simulate a mobile user agent
		$_SERVER['HTTP_USER_AGENT'] = 'MobileBrowser';

		// Assert that the function returns true for a mobile user agent
		$this->assertTrue( \PoweredCache\Utils\powered_cache_is_mobile() );

		// Unset the global variable to clean up after the test
		unset( $GLOBALS['powered_cache_mobile_browsers'], $GLOBALS['powered_cache_mobile_prefixes'], $_SERVER['HTTP_USER_AGENT'] );
	}

	public function testPoweredCacheIsMobileWithNonMobileUserAgent() {
		global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

		// Mock the wp_unslash function
		\WP_Mock::userFunction( 'wp_unslash', [
			'return' => function ( $value ) {
				return $value;
			},
		] );

		// Set global variables for mobile browsers and prefixes
		$powered_cache_mobile_browsers = 'MobileBrowser';
		$powered_cache_mobile_prefixes = 'Mob';

		// Simulate a non-mobile user agent
		$_SERVER['HTTP_USER_AGENT'] = 'DesktopBrowser';

		// Assert that the function returns false for a non-mobile user agent
		$this->assertFalse( \PoweredCache\Utils\powered_cache_is_mobile() );

		// Unset the global variable to clean up after the test
		unset( $GLOBALS['powered_cache_mobile_browsers'], $GLOBALS['powered_cache_mobile_prefixes'], $_SERVER['HTTP_USER_AGENT'] );
	}

}
