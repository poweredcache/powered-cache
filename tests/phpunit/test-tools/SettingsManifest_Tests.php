<?php
/**
 * Settings manifest tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings manifest test case.
 */
class SettingsManifest_Tests extends TestCase {

	/**
	 * It builds a versioned manifest payload.
	 */
	public function test_build_returns_versioned_manifest_payload() {
		$manifest = SettingsManifest::build( array( 'is_apache' => true ) );

		$this->assertSame( SettingsManifest::FORMAT, $manifest['format'] );
		$this->assertSame( SettingsManifest::FORMAT_VERSION, $manifest['format_version'] );
		$this->assertSame( POWERED_CACHE_VERSION, $manifest['plugin_version'] );
		$this->assertArrayHasKey( 'sections', $manifest );
		$this->assertArrayHasKey( 'fields', $manifest );
	}

	/**
	 * It exposes ordered section metadata.
	 */
	public function test_sections_include_expected_product_areas() {
		$sections = SettingsManifest::sections();

		$this->assertSame( 'Cache', $sections['cache']['label'] );
		$this->assertSame( 10, $sections['cache']['order'] );
		$this->assertSame( 'File Optimization', $sections['file_optimization']['label'] );
		$this->assertSame( 'Integrations', $sections['integrations']['label'] );
	}

	/**
	 * It allows extensions to add settings sections.
	 */
	public function test_sections_can_be_extended() {
		$sections = SettingsManifest::sections();
		$expected = $sections;

		$expected['license'] = array(
			'label'       => 'License',
			'description' => 'Manage Premium license activation.',
			'order'       => 1000,
		);

		\WP_Mock::onFilter( 'powered_cache_settings_sections' )->with( $sections )->reply( $expected );

		$this->assertSame( $expected, SettingsManifest::sections() );
	}

	/**
	 * It exposes schema fields in UI/API friendly shape.
	 */
	public function test_fields_include_schema_metadata() {
		$fields = SettingsManifest::fields( array( 'is_apache' => false ) );

		$this->assertSame( 'enable_page_cache', $fields['enable_page_cache']['key'] );
		$this->assertSame( 'Page Cache', $fields['enable_page_cache']['label'] );
		$this->assertSame( 'Serve cached pages', $fields['enable_page_cache']['control_label'] );
		$this->assertSame( 'Core cache', $fields['enable_page_cache']['group'] );
		$this->assertSame( SettingsSchema::TYPE_BOOLEAN, $fields['enable_page_cache']['type'] );
		$this->assertSame( 'toggle', $fields['enable_page_cache']['control'] );
		$this->assertSame( 'cache', $fields['enable_page_cache']['section'] );
		$this->assertSame( 10, $fields['enable_page_cache']['order'] );
		$this->assertFalse( $fields['enable_page_cache']['premium'] );
		$this->assertTrue( $fields['enable_page_cache']['editable'] );
		$this->assertFalse( $fields['enable_page_cache']['locked'] );
		$this->assertFalse( $fields['auto_configure_htaccess']['default'] );
		$this->assertSame( array( 'redis', 'apcu' ), array_slice( $fields['object_cache']['enum'], -2 ) );
		$this->assertSame( 'Redis', $fields['object_cache']['enum_labels']['redis'] );
		$this->assertSame( 'select', $fields['object_cache']['control'] );
		$this->assertSame( 'duration', $fields['cache_timeout']['control'] );
		$this->assertSame( 'Set how long cached pages stay fresh.', $fields['cache_timeout']['description'] );
		$this->assertSame( 'Include the homepage', $fields['preload_homepage']['control_label'] );
		$this->assertSame( 'Warm the homepage when cache preloading runs.', $fields['preload_homepage']['description'] );
		$this->assertSame( 'select', $fields['image_optimizer_preferred_format']['control'] );
		$this->assertSame(
			array(
				''     => 'Automatic (AVIF when supported)',
				'webp' => 'Prefer WebP',
			),
			$fields['image_optimizer_preferred_format']['options']
		);
		$this->assertSame( 'cdn_zones', $fields['cdn_hostname']['control'] );
		$this->assertSame( 'cdn_zone', $fields['cdn_hostname']['zone_key'] );
		$this->assertSame(
			array(
				'all'   => 'All files',
				'image' => 'Images',
				'js'    => 'JavaScript',
				'css'   => 'CSS',
			),
			$fields['cdn_hostname']['zone_options']
		);
		$this->assertSame( 'hidden', $fields['cdn_zone']['control'] );
	}

	/**
	 * It marks Premium feature fields for Free/Premium UI visibility.
	 */
	public function test_premium_fields_are_marked_in_manifest() {
		$fields = SettingsManifest::fields();

		$premium_keys = array(
			'critical_css',
			'remove_unused_css',
			'enable_image_optimization',
			'add_missing_image_dimensions',
			'enable_lcp_optimization',
			'enable_scheduled_db_cleanup',
			'scheduled_db_cleanup_frequency',
			'enable_google_tracking',
			'enable_fb_tracking',
		);

		foreach ( $premium_keys as $key ) {
			$this->assertArrayHasKey( $key, $fields );
			$this->assertTrue( $fields[ $key ]['premium'], $key );
			$this->assertFalse( $fields[ $key ]['editable'], $key );
			$this->assertTrue( $fields[ $key ]['locked'], $key );
			$this->assertSame( 'premium', $fields[ $key ]['lock_reason'], $key );
			$this->assertArrayHasKey( 'upgrade', $fields[ $key ] );
			$this->assertSame( 'Upgrade to Premium', $fields[ $key ]['upgrade']['label'] );
		}
	}

	/**
	 * It exposes section descriptions for the settings app.
	 */
	public function test_sections_include_descriptions_for_settings_app() {
		$sections = SettingsManifest::sections();

		$this->assertArrayHasKey( 'description', $sections['cache'] );
		$this->assertNotEmpty( $sections['cache']['description'] );
		$this->assertSame( 'Tools', $sections['misc']['label'] );
	}

	/**
	 * It can omit deprecated fields for new UI consumers.
	 */
	public function test_fields_can_exclude_deprecated_settings() {
		$fields = SettingsManifest::fields( array(), false );

		$this->assertArrayNotHasKey( 'ssl_cache', $fields );
		$this->assertArrayNotHasKey( 'js_execution_method', $fields );
		$this->assertArrayNotHasKey( 'js_execution_optimized_only', $fields );
	}
}
