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

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'utils.php',
	);

	/**
	 * It keeps schema defaults in parity with the public settings helper.
	 */
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

		$this->assertSame( Utils\get_settings(), $schema_defaults );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It reads network settings when the public helper forces network mode.
	 */
	public function test_get_settings_respects_force_network_wide() {
		global $is_apache;

		$is_apache = false;

		$schema_defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $schema_defaults )->reply( $schema_defaults );

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

		$settings = Utils\get_settings( true );

		$this->assertTrue( $settings['enable_cdn'] );
		$this->assertFalse( $settings['auto_configure_htaccess'] );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It exposes critical field metadata for consumers.
	 */
	public function test_critical_settings_have_expected_metadata() {
		$fields = SettingsSchema::fields(
			array(
				'is_apache'             => false,
				'object_cache_backends' => array( 'memcache', 'memcached', 'redis', 'apcu' ),
			)
		);

		$this->assertSame( SettingsSchema::TYPE_BOOLEAN, $fields['enable_page_cache']['type'] );
		$this->assertSame( 'cache', $fields['enable_page_cache']['section'] );
		$this->assertFalse( $fields['enable_page_cache']['premium'] );

		$this->assertSame( SettingsSchema::TYPE_ENUM, $fields['object_cache']['type'] );
		$this->assertContains( 'redis', $fields['object_cache']['enum'] );

		$this->assertTrue( $fields['critical_css']['premium'] );
		$this->assertTrue( $fields['remove_unused_css']['premium'] );
		$this->assertTrue( $fields['enable_image_optimization']['premium'] );
		$this->assertTrue( $fields['enable_lcp_optimization']['premium'] );
		$this->assertTrue( $fields['enable_sitemap_preload']['premium'] );
		$this->assertTrue( $fields['preload_sitemap']['premium'] );
		$this->assertTrue( $fields['prefetch_links']['premium'] );
		$this->assertTrue( $fields['enable_scheduled_db_cleanup']['premium'] );
		$this->assertTrue( $fields['scheduled_db_cleanup_frequency']['premium'] );
		$this->assertTrue( $fields['enable_varnish']['premium'] );
		$this->assertTrue( $fields['varnish_ip']['premium'] );
		$this->assertFalse( $fields['preload_fonts']['premium'] );
		$this->assertSame( SettingsSchema::SANITIZE_TEXTAREA, $fields['preload_fonts']['sanitizer'] );

		$this->assertContains( 'js_delay', $fields['js_delay_timeout']['dependencies'] );
		$this->assertTrue( $fields['js_execution_method']['deprecated'] );
		$this->assertContains( 'delayed', $fields['js_execution_method']['enum'] );
	}

	/**
	 * It limits object cache choices to supported PHP drivers.
	 */
	public function test_object_cache_enum_uses_supported_runtime_backends() {
		$fields = SettingsSchema::fields(
			array(
				'is_apache'             => false,
				'object_cache_backends' => array( 'redis' ),
			)
		);

		$this->assertSame( array( 'off', 'redis' ), $fields['object_cache']['enum'] );
	}

	/**
	 * It returns fields for one schema section.
	 */
	public function test_sections_return_only_matching_fields() {
		$media_fields = SettingsSchema::section( 'media' );

		$this->assertArrayHasKey( 'enable_image_optimization', $media_fields );
		$this->assertArrayHasKey( 'enable_lazy_load', $media_fields );
		$this->assertArrayNotHasKey( 'enable_page_cache', $media_fields );
	}

	/**
	 * It resolves Apache-specific defaults from runtime context.
	 */
	public function test_dynamic_apache_defaults_are_context_aware() {
		$apache_defaults     = SettingsSchema::defaults( array( 'is_apache' => true ) );
		$non_apache_defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		$this->assertTrue( $apache_defaults['auto_configure_htaccess'] );
		$this->assertTrue( $apache_defaults['rewrite_file_optimizer'] );
		$this->assertFalse( $non_apache_defaults['auto_configure_htaccess'] );
		$this->assertFalse( $non_apache_defaults['rewrite_file_optimizer'] );
	}

	/**
	 * It returns a conservative recommended setup profile.
	 */
	public function test_recommended_settings_enable_safe_baseline_controls() {
		$recommended = SettingsSchema::recommended( array( 'is_apache' => true ), false );

		$this->assertTrue( $recommended['enable_page_cache'] );
		$this->assertTrue( $recommended['cache_mobile'] );
		$this->assertTrue( $recommended['gzip_compression'] );
		$this->assertTrue( $recommended['auto_configure_htaccess'] );
		$this->assertTrue( $recommended['minify_html'] );
		$this->assertTrue( $recommended['minify_css'] );
		$this->assertTrue( $recommended['minify_js'] );
		$this->assertFalse( $recommended['combine_css'] );
		$this->assertFalse( $recommended['combine_js'] );
		$this->assertFalse( $recommended['js_delay'] );
		$this->assertTrue( $recommended['enable_lazy_load'] );
		$this->assertFalse( $recommended['lazy_load_background_images'] );
		$this->assertTrue( $recommended['enable_cache_preload'] );
		$this->assertArrayNotHasKey( 'critical_css', $recommended );
		$this->assertArrayNotHasKey( 'remove_unused_css', $recommended );
	}

	/**
	 * It includes image delivery when Premium is available.
	 */
	public function test_recommended_settings_include_premium_delivery_when_available() {
		$recommended = SettingsSchema::recommended( array( 'is_apache' => false ), true );

		$this->assertTrue( $recommended['enable_image_optimization'] );
		$this->assertSame( '', $recommended['image_optimizer_preferred_format'] );
		$this->assertFalse( $recommended['auto_configure_htaccess'] );
		$this->assertFalse( $recommended['rewrite_file_optimizer'] );
	}

	/**
	 * It avoids overlapping optimization layers in the recommended setup profile.
	 */
	public function test_recommended_settings_respect_active_optimizer_layers() {
		$recommended = SettingsSchema::recommended(
			array(
				'is_apache'      => true,
				'active_plugins' => array(
					'autoptimize/autoptimize.php',
					'a3-lazy-load/a3-lazy-load.php',
				),
			),
			false
		);

		$this->assertTrue( $recommended['enable_page_cache'] );
		$this->assertFalse( $recommended['minify_html'] );
		$this->assertFalse( $recommended['minify_css'] );
		$this->assertFalse( $recommended['minify_js'] );
		$this->assertFalse( $recommended['enable_lazy_load'] );
		$this->assertTrue( $recommended['enable_cache_preload'] );
	}

	/**
	 * It avoids enabling page cache when another page cache layer is active.
	 */
	public function test_recommended_settings_respect_active_cache_layers() {
		$recommended = SettingsSchema::recommended(
			array(
				'is_apache'      => true,
				'active_plugins' => array( 'litespeed-cache/litespeed-cache.php' ),
			),
			false
		);

		$this->assertFalse( $recommended['enable_page_cache'] );
		$this->assertFalse( $recommended['enable_cache_preload'] );
		$this->assertTrue( $recommended['cache_mobile'] );
		$this->assertTrue( $recommended['gzip_compression'] );
	}

	/**
	 * It keeps Premium fields locked when Premium is unavailable.
	 */
	public function test_premium_fields_are_locked_without_premium() {
		$this->assertTrue( SettingsSchema::can_edit( 'enable_page_cache', array(), false ) );
		$this->assertFalse( SettingsSchema::can_edit( 'enable_image_optimization', array(), false ) );
		$this->assertFalse( SettingsSchema::can_edit( 'enable_sitemap_preload', array(), false ) );
		$this->assertFalse( SettingsSchema::can_edit( 'prefetch_links', array(), false ) );
		$this->assertFalse( SettingsSchema::can_edit( 'enable_varnish', array(), false ) );
		$this->assertSame( 'premium', SettingsSchema::lock_reason( 'enable_image_optimization', array(), false ) );
		$this->assertSame( '', SettingsSchema::lock_reason( 'enable_page_cache', array(), false ) );
	}

	/**
	 * It allows Premium fields when Premium is available.
	 */
	public function test_premium_fields_are_editable_with_premium() {
		$this->assertTrue( SettingsSchema::can_edit( 'enable_image_optimization', array(), true ) );
		$this->assertSame( '', SettingsSchema::lock_reason( 'enable_image_optimization', array(), true ) );
	}

	/**
	 * It preserves existing Premium values while applying Free changes.
	 */
	public function test_enforce_editable_preserves_existing_premium_values_in_free() {
		$settings = SettingsSchema::enforce_editable(
			array(
				'enable_page_cache'         => false,
				'enable_image_optimization' => true,
				'critical_css'              => true,
				'prefetch_links'            => true,
				'enable_varnish'            => true,
			),
			array(
				'enable_page_cache'         => true,
				'enable_image_optimization' => false,
				'critical_css'              => true,
				'prefetch_links'            => false,
				'enable_varnish'            => false,
			),
			array(),
			false
		);

		$this->assertFalse( $settings['enable_page_cache'] );
		$this->assertFalse( $settings['enable_image_optimization'] );
		$this->assertTrue( $settings['critical_css'] );
		$this->assertFalse( $settings['prefetch_links'] );
		$this->assertFalse( $settings['enable_varnish'] );
	}

	/**
	 * It allows Premium changes when Premium is available.
	 */
	public function test_enforce_editable_allows_premium_changes_with_premium() {
		$settings = SettingsSchema::enforce_editable(
			array(
				'enable_image_optimization' => true,
			),
			array(
				'enable_image_optimization' => false,
			),
			array(),
			true
		);

		$this->assertTrue( $settings['enable_image_optimization'] );
	}

	/**
	 * It keeps unknown extension settings editable.
	 */
	public function test_unknown_settings_remain_editable() {
		$settings = SettingsSchema::enforce_editable(
			array(
				'custom_extension_key' => 'changed',
			),
			array(
				'custom_extension_key' => 'current',
			),
			array(),
			false
		);

		$this->assertTrue( SettingsSchema::can_edit( 'custom_extension_key', array(), false ) );
		$this->assertSame( 'changed', $settings['custom_extension_key'] );
	}
}
