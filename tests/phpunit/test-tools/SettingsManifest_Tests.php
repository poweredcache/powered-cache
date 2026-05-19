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
	 * It exposes schema fields in UI/API friendly shape.
	 */
	public function test_fields_include_schema_metadata() {
		$fields = SettingsManifest::fields( array( 'is_apache' => false ) );

		$this->assertSame( 'enable_page_cache', $fields['enable_page_cache']['key'] );
		$this->assertSame( SettingsSchema::TYPE_BOOLEAN, $fields['enable_page_cache']['type'] );
		$this->assertSame( 'cache', $fields['enable_page_cache']['section'] );
		$this->assertFalse( $fields['enable_page_cache']['premium'] );
		$this->assertFalse( $fields['auto_configure_htaccess']['default'] );
		$this->assertSame( array( 'redis', 'apcu' ), array_slice( $fields['object_cache']['enum'], -2 ) );
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
			'enable_google_tracking',
			'enable_fb_tracking',
		);

		foreach ( $premium_keys as $key ) {
			$this->assertArrayHasKey( $key, $fields );
			$this->assertTrue( $fields[ $key ]['premium'], $key );
		}
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
