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
	 * It merges partial updates with current settings before saving.
	 */
	public function test_update_merges_partial_settings_with_current_values() {
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
}
