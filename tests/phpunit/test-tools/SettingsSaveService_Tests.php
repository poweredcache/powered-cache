<?php
/**
 * Settings save service tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings save service test case.
 */
class SettingsSaveService_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
	);

	/**
	 * It refreshes cache purge scheduling when cache timeout changes.
	 */
	public function test_handle_side_effects_refreshes_cache_timeout_schedule() {
		$old_settings = array(
			'cache_timeout' => 30,
		);

		$settings = array(
			'cache_timeout' => 60,
		);

		\WP_Mock::userFunction(
			'wp_next_scheduled',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\PURGE_CACHE_CRON_NAME ),
				'return' => 12345,
			)
		);

		\WP_Mock::userFunction(
			'wp_unschedule_event',
			array(
				'times' => 1,
				'args'  => array( 12345, \PoweredCache\Constants\PURGE_CACHE_CRON_NAME ),
			)
		);

		\WP_Mock::expectAction( 'powered_cache_settings_saved', $old_settings, $settings );

		$service = new SettingsSaveService();
		$service->handle_side_effects( $old_settings, $settings );

		$this->assertConditionsMet();
	}

	/**
	 * It flushes object cache when the backend changes.
	 */
	public function test_handle_side_effects_flushes_cache_when_object_cache_changes() {
		$old_settings = array(
			'object_cache'  => 'off',
			'cache_timeout' => 60,
		);

		$settings = array(
			'object_cache'  => 'redis',
			'cache_timeout' => 60,
		);

		\WP_Mock::userFunction(
			'wp_cache_flush',
			array(
				'times' => 1,
			)
		);

		\WP_Mock::expectAction( 'powered_cache_settings_saved', $old_settings, $settings );

		$service = new SettingsSaveService();
		$service->handle_side_effects( $old_settings, $settings );

		$this->assertConditionsMet();
	}
}
