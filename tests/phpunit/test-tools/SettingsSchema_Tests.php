<?php
/**
 * Settings schema tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Utils;

/**
 * Settings schema test case.
 */
class SettingsSchema_Tests extends TestCase {

	protected $testFiles = array(
		'constants.php',
		'utils.php',
	);

	public function test_defaults_match_legacy_get_settings_defaults() {
		global $is_apache;

		$is_apache = true;

		$schema_defaults = SettingsSchema::defaults( array( 'is_apache' => true ) );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $schema_defaults )->reply( $schema_defaults );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(),
			)
		);

		\WP_Mock::userFunction(
			'wp_parse_args',
			array(
				'times'  => 1,
				'return' => static function ( $args, $defaults ) {
					return array_merge( $defaults, $args );
				},
			)
		);

		$this->assertSame( Utils\get_settings(), $schema_defaults );

		unset( $GLOBALS['is_apache'] );
	}

	public function test_critical_settings_have_expected_metadata() {
		$fields = SettingsSchema::fields( array( 'is_apache' => false ) );

		$this->assertSame( SettingsSchema::TYPE_BOOLEAN, $fields['enable_page_cache']['type'] );
		$this->assertSame( 'cache', $fields['enable_page_cache']['section'] );
		$this->assertFalse( $fields['enable_page_cache']['premium'] );

		$this->assertSame( SettingsSchema::TYPE_ENUM, $fields['object_cache']['type'] );
		$this->assertContains( 'redis', $fields['object_cache']['enum'] );

		$this->assertTrue( $fields['critical_css']['premium'] );
		$this->assertTrue( $fields['remove_unused_css']['premium'] );
		$this->assertTrue( $fields['enable_image_optimization']['premium'] );
		$this->assertTrue( $fields['enable_lcp_optimization']['premium'] );

		$this->assertContains( 'js_delay', $fields['js_delay_timeout']['dependencies'] );
		$this->assertTrue( $fields['js_execution_method']['deprecated'] );
	}

	public function test_sections_return_only_matching_fields() {
		$media_fields = SettingsSchema::section( 'media' );

		$this->assertArrayHasKey( 'enable_image_optimization', $media_fields );
		$this->assertArrayHasKey( 'enable_lazy_load', $media_fields );
		$this->assertArrayNotHasKey( 'enable_page_cache', $media_fields );
	}

	public function test_dynamic_apache_defaults_are_context_aware() {
		$apache_defaults     = SettingsSchema::defaults( array( 'is_apache' => true ) );
		$non_apache_defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		$this->assertTrue( $apache_defaults['auto_configure_htaccess'] );
		$this->assertTrue( $apache_defaults['rewrite_file_optimizer'] );
		$this->assertFalse( $non_apache_defaults['auto_configure_htaccess'] );
		$this->assertFalse( $non_apache_defaults['rewrite_file_optimizer'] );
	}
}
