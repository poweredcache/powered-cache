<?php
/**
 * Install and upgrade tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Install test case.
 */
class Install_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'classes/Install.php',
	);

	/**
	 * It runs the 4.0 settings migration during upgrades without dropping legacy keys.
	 */
	public function test_upgrade_40_persists_compatible_settings_shape() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 2,
				'return' => function ( $option, $default = false ) {
					if ( \PoweredCache\Constants\DB_VERSION_OPTION_NAME === $option ) {
						return '3.7.3';
					}

					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertSame( array(), $default );

					return array(
						'accepted_query_strings' => 'utm_source',
						'js_execution_method'    => 'delayed',
						'combine_js'             => true,
					);
				},
			)
		);

		\WP_Mock::userFunction(
			'PoweredCache\Utils\log',
			array(
				'times' => 1,
				'args'  => array( 'Upgraded to version 4.0' ),
			)
		);

		$install = new Testable_Install();
		$install->upgrade_40();

		$this->assertCount( 1, $install->saved_settings );
		$this->assertFalse( $install->saved_settings[0]['network_wide'] );
		$this->assertSame( 'utm_source', $install->saved_settings[0]['settings']['accepted_query_strings'] );
		$this->assertSame( 'utm_source', $install->saved_settings[0]['settings']['ignored_query_strings'] );
		$this->assertSame( 'delayed', $install->saved_settings[0]['settings']['js_execution_method'] );
		$this->assertTrue( $install->saved_settings[0]['settings']['js_delay'] );
		$this->assertFalse( $install->saved_settings[0]['settings']['combine_js'] );
	}

	/**
	 * It skips 4.0 settings migration when the stored version is already current.
	 */
	public function test_upgrade_40_skips_current_install() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\DB_VERSION_OPTION_NAME ),
				'return' => '4.0',
			)
		);

		$install = new Testable_Install();
		$install->upgrade_40();

		$this->assertSame( array(), $install->saved_settings );
	}

	/**
	 * It passes the network storage flag through 4.0 migrations.
	 */
	public function test_upgrade_40_supports_network_storage() {
		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 2,
				'return' => function ( $option, $default = false ) {
					if ( \PoweredCache\Constants\DB_VERSION_OPTION_NAME === $option ) {
						return '3.7.3';
					}

					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertSame( array(), $default );

					return array(
						'accepted_query_strings' => 'utm_campaign',
					);
				},
			)
		);

		\WP_Mock::userFunction(
			'PoweredCache\Utils\log',
			array(
				'times' => 1,
				'args'  => array( 'Upgraded to version 4.0' ),
			)
		);

		$install = new Testable_Install();
		$install->upgrade_40( true );

		$this->assertCount( 1, $install->saved_settings );
		$this->assertTrue( $install->saved_settings[0]['network_wide'] );
		$this->assertSame( 'utm_campaign', $install->saved_settings[0]['settings']['ignored_query_strings'] );
	}
}

/**
 * Test double for install routines.
 */
class Testable_Install extends Install {

	/**
	 * Captured settings saves.
	 *
	 * @var array
	 */
	public $saved_settings = array();

	/**
	 * Capture settings saves without touching filesystem-backed configuration.
	 *
	 * @param array $settings Settings payload.
	 * @param bool  $network_wide Whether settings should use network storage.
	 *
	 * @return array Saved settings.
	 */
	protected function save_settings( array $settings, $network_wide = false ) {
		$this->saved_settings[] = array(
			'settings'     => $settings,
			'network_wide' => $network_wide,
		);

		return $settings;
	}
}
