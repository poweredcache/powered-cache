<?php

namespace PoweredCache\Core;

/**
 * This is a very basic test case to get things started. You should probably rename this and make
 * it work for your project. You can use all the tools provided by WP Mock and Mockery to create
 * your tests. Coverage is calculated against your includes/ folder, so try to keep all of your
 * functional code self contained in there.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */

use function Patchwork\Config\get;
use PoweredCache as Base;

class Core_Tests extends Base\TestCase {

	protected $testFiles
		= [
			'core.php',
			'utils.php',
			'constants.php'
		];

	/**
	 * Test load method.
	 */
	public function test_setup() {
		if ( ! defined( 'POWERED_CACHE_IS_NETWORK' ) ) {
			define( 'POWERED_CACHE_IS_NETWORK', false );
		}

		// Setup
		\WP_Mock::expectActionAdded( 'init', 'PoweredCache\Core\i18n' );
		\WP_Mock::expectActionAdded( 'init', 'PoweredCache\Core\init' );
		\WP_Mock::expectAction( 'powered_cache_loaded' );

		// Act
		setup();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Test internationalization integration.
	 */
	public function test_i18n() {
		// Setup
		\WP_Mock::userFunction( 'get_locale', array(
			'times'  => 1,
			'args'   => array(),
			'return' => 'en_US',
		) );
		\WP_Mock::onFilter( 'plugin_locale' )->with( 'en_US', 'powered-cache' )->reply( 'en_US' );
		\WP_Mock::userFunction( 'load_textdomain', array(
			'times' => 1,
			'args'  => array( 'powered-cache', 'lang_dir/powered-cache/powered-cache-en_US.mo' ),
		) );
		\WP_Mock::userFunction( 'plugin_basename', array(
			'times'  => 1,
			'args'   => array( 'path' ),
			'return' => 'path',
		) );
		\WP_Mock::userFunction( 'load_plugin_textdomain', array(
			'times' => 1,
			'args'  => array( 'powered-cache', false, 'path/languages/' ),
		) );

		// Act
		i18n();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Test initialization method.
	 */
	public function test_init() {
		// Setup
		\WP_Mock::expectAction( 'powered_cache_init' );

		// Act
		init();

		// Verify
		$this->assertConditionsMet();
	}

}
