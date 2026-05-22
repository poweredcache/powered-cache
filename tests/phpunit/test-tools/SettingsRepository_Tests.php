<?php
/**
 * Settings repository tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings repository test case.
 */
class SettingsRepository_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
	);

	/**
	 * It reads single-site settings and fills missing defaults.
	 */
	public function test_all_reads_and_normalizes_single_site_settings() {
		$this->expect_default_settings_filter( array( 'is_apache' => true ) );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'enable_page_cache' => false,
					'cache_timeout'     => 30,
					'custom_legacy_key' => 'keep-me',
				),
			)
		);

		$settings = SettingsRepository::factory( false, array( 'is_apache' => true ) )->all();

		$this->assertFalse( $settings['enable_page_cache'] );
		$this->assertSame( 30, $settings['cache_timeout'] );
		$this->assertTrue( $settings['cache_mobile'] );
		$this->assertSame( 'keep-me', $settings['custom_legacy_key'] );
	}

	/**
	 * It reads network settings when network mode is requested.
	 */
	public function test_all_reads_network_settings_when_requested() {
		$this->expect_default_settings_filter( array( 'is_apache' => false ) );

		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'enable_cdn' => true,
				),
			)
		);

		$settings = SettingsRepository::factory( true, array( 'is_apache' => false ) )->all();

		$this->assertTrue( $settings['enable_cdn'] );
		$this->assertFalse( $settings['auto_configure_htaccess'] );
	}

	/**
	 * It keeps the public default settings filter in the read path.
	 */
	public function test_all_applies_default_settings_filter() {
		$defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );
		$filtered = array_merge(
			$defaults,
			array(
				'enable_cdn'         => true,
				'custom_extension'   => 'default-value',
				'rejected_referrers' => 'example.test',
			)
		);

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $defaults )->reply( $filtered );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'enable_cdn'         => false,
					'rejected_referrers' => 'stored.test',
				),
			)
		);

		$settings = SettingsRepository::factory( false, array( 'is_apache' => false ) )->all();

		$this->assertFalse( $settings['enable_cdn'] );
		$this->assertSame( 'default-value', $settings['custom_extension'] );
		$this->assertSame( 'stored.test', $settings['rejected_referrers'] );
	}

	/**
	 * It sanitizes schema-known fields without dropping unknown keys.
	 */
	public function test_sanitize_uses_schema_metadata_and_preserves_unknown_keys() {
		$repository = SettingsRepository::factory( false, array( 'is_apache' => false ) );

		$settings = $repository->sanitize(
			array(
				'enable_page_cache'       => '1',
				'cache_timeout'           => '-45',
				'object_cache'            => 'invalid-cache',
				'cdn_hostname'            => array( ' cdn.example.com ', '<b>assets.example.com</b>' ),
				'rejected_uri'            => "<script>alert('x')</script>\n/cart/",
				'custom_legacy_key'       => '<b>untouched</b>',
				'auto_configure_htaccess' => '',
			)
		);

		$this->assertTrue( $settings['enable_page_cache'] );
		$this->assertSame( 45, $settings['cache_timeout'] );
		$this->assertSame( 'off', $settings['object_cache'] );
		$this->assertSame( array( 'cdn.example.com', 'assets.example.com' ), $settings['cdn_hostname'] );
		$this->assertSame( "alert('x')\n/cart/", $settings['rejected_uri'] );
		$this->assertSame( '<b>untouched</b>', $settings['custom_legacy_key'] );
		$this->assertFalse( $settings['auto_configure_htaccess'] );
	}

	/**
	 * It writes sanitized and normalized settings to single-site storage.
	 */
	public function test_save_writes_sanitized_normalized_single_site_settings() {
		\WP_Mock::userFunction(
			'update_option',
			array(
				'times'  => 1,
				'return' => function ( $option, $settings ) {
					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertFalse( $settings['enable_page_cache'] );
					$this->assertSame( 10, $settings['cache_timeout'] );
					$this->assertTrue( $settings['cache_mobile'] );

					return true;
				},
			)
		);

		$result = SettingsRepository::factory( false, array( 'is_apache' => false ) )->save(
			array(
				'enable_page_cache' => false,
				'cache_timeout'     => '10',
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * It keeps legacy and deprecated settings in storage for rollback safety.
	 */
	public function test_save_preserves_legacy_storage_keys() {
		\WP_Mock::userFunction(
			'update_option',
			array(
				'times'  => 1,
				'return' => function ( $option, $settings ) {
					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertSame( 'utm_source', $settings['accepted_query_strings'] );
					$this->assertSame( 'utm_source', $settings['ignored_query_strings'] );
					$this->assertSame( 'delayed', $settings['js_execution_method'] );
					$this->assertTrue( $settings['js_delay'] );
					$this->assertFalse( $settings['combine_js'] );
					$this->assertFalse( $settings['ssl_cache'] );
					$this->assertSame( 'keep-me', $settings['custom_legacy_key'] );

					return true;
				},
			)
		);

		$result = SettingsRepository::factory( false, array( 'is_apache' => false ) )->save(
			array(
				'accepted_query_strings' => 'utm_source',
				'js_execution_method'    => 'delayed',
				'ssl_cache'              => false,
				'custom_legacy_key'      => 'keep-me',
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * It detects legacy settings that need 4.0 migration.
	 */
	public function test_has_legacy_settings_detects_migration_inputs() {
		$this->assertTrue( SettingsRepository::has_legacy_settings( array( 'accepted_query_strings' => 'utm_source' ) ) );
		$this->assertTrue( SettingsRepository::has_legacy_settings( array( 'js_execution_method' => 'delayed' ) ) );
		$this->assertFalse( SettingsRepository::has_legacy_settings( array( 'ignored_query_strings' => 'utm_source' ) ) );
	}

	/**
	 * It migrates accepted query strings into ignored query strings.
	 */
	public function test_migrates_accepted_query_strings() {
		$settings = SettingsRepository::migrate_legacy_settings(
			array(
				'accepted_query_strings' => 'utm_source',
			)
		);

		$this->assertSame( 'utm_source', $settings['ignored_query_strings'] );
		$this->assertSame( 'utm_source', $settings['accepted_query_strings'] );
	}

	/**
	 * It does not overwrite an existing ignored query string value.
	 */
	public function test_migration_does_not_overwrite_existing_ignored_query_strings() {
		$settings = SettingsRepository::migrate_legacy_settings(
			array(
				'accepted_query_strings' => 'utm_source',
				'ignored_query_strings'  => 'gclid',
			)
		);

		$this->assertSame( 'gclid', $settings['ignored_query_strings'] );
	}

	/**
	 * It migrates deprecated JS execution method values.
	 */
	public function test_migrates_js_execution_method() {
		$defer_settings = SettingsRepository::migrate_legacy_settings(
			array(
				'js_execution_method' => 'defer',
			)
		);

		$delay_settings = SettingsRepository::migrate_legacy_settings(
			array(
				'js_execution_method' => 'delayed',
				'combine_js'          => true,
			)
		);

		$this->assertTrue( $defer_settings['js_defer'] );
		$this->assertTrue( $delay_settings['js_delay'] );
		$this->assertFalse( $delay_settings['combine_js'] );
	}

	/**
	 * It exposes deprecated keys from the schema.
	 */
	public function test_deprecated_keys_are_schema_driven() {
		$this->assertContains( 'ssl_cache', SettingsRepository::deprecated_keys() );
		$this->assertContains( 'js_execution_method', SettingsRepository::deprecated_keys() );
		$this->assertContains( 'js_execution_optimized_only', SettingsRepository::deprecated_keys() );
	}

	/**
	 * It merges partial updates with current settings before saving.
	 */
	public function test_update_merges_partial_settings_with_current_values() {
		$this->expect_default_settings_filter( array( 'is_apache' => false ) );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'enable_page_cache' => false,
					'cache_timeout'     => 30,
				),
			)
		);

		\WP_Mock::userFunction(
			'update_option',
			array(
				'times'  => 1,
				'return' => function ( $option, $settings ) {
					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertFalse( $settings['enable_page_cache'] );
					$this->assertSame( 60, $settings['cache_timeout'] );

					return true;
				},
			)
		);

		$result = SettingsRepository::factory( false, array( 'is_apache' => false ) )->update(
			array(
				'cache_timeout' => 60,
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * It deletes network settings when network mode is requested.
	 */
	public function test_delete_uses_network_storage_when_requested() {
		\WP_Mock::userFunction(
			'delete_site_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION ),
				'return' => true,
			)
		);

		$this->assertTrue( SettingsRepository::factory( true )->delete() );
	}

	/**
	 * Expect the default settings filter to keep legacy read parity.
	 *
	 * @param array $context Runtime context.
	 */
	private function expect_default_settings_filter( array $context ) {
		$defaults = SettingsSchema::defaults( $context );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $defaults )->reply( $defaults );
	}
}
