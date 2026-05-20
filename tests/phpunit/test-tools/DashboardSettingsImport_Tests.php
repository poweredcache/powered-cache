<?php
/**
 * Dashboard settings import tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Admin\Dashboard;

use PoweredCache\SettingsRepository;
use PoweredCache\SettingsSchema;
use PoweredCache\TestCase;

/**
 * Dashboard settings import test case.
 */
class DashboardSettingsImport_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'admin/dashboard.php',
	);

	/**
	 * It preserves legacy and extension-owned keys when importing settings.
	 */
	public function test_prepare_import_options_preserves_legacy_keys() {
		$defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $defaults )->reply( $defaults );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'custom_legacy_key' => 'stored',
					'cache_timeout'     => 60,
				),
			)
		);

		$options = prepare_import_options(
			SettingsRepository::factory( false, array( 'is_apache' => false ) ),
			array(
				'accepted_query_strings' => 'utm_source',
				'cache_timeout'          => '45',
				'custom_legacy_key'      => 'imported',
				'js_execution_method'    => 'delayed',
				'rejected_uri'           => '<b>/cart/</b>',
			)
		);

		$this->assertSame( 'utm_source', $options['accepted_query_strings'] );
		$this->assertSame( 45, $options['cache_timeout'] );
		$this->assertSame( 'imported', $options['custom_legacy_key'] );
		$this->assertSame( 'delayed', $options['js_execution_method'] );
		$this->assertSame( '/cart/', $options['rejected_uri'] );
	}
}
