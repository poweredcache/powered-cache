<?php
/**
 * Settings setup profile tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings setup profile test case.
 */
class SettingsSetupProfile_Tests extends TestCase {

	/**
	 * It detects plugin families for guided setup recommendations.
	 */
	public function test_report_detects_plugin_families_for_guided_setup() {
		$active_plugins = array(
			'elementor/elementor.php',
			'complianz-gdpr/complianz-gdpr.php',
			'smart-slider-3/smart-slider-3.php',
			'autoptimize/autoptimize.php',
			'litespeed-cache/litespeed-cache.php',
			'a3-lazy-load/a3-lazy-load.php',
		);

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => $active_plugins,
			)
		);

		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_sitewide_plugins', array() ),
				'return' => array(),
			)
		);

		$profile = SettingsSetupProfile::factory()->report(
			array(
				'enable_page_cache' => true,
				'object_cache'       => 'off',
			)
		);

		$detected_keys       = array_column( $profile['detected'], 'key' );
		$recommendation_keys = array_column( $profile['recommendations'], 'key' );

		$this->assertSame( count( $active_plugins ), $profile['plugin_count'] );
		$this->assertContains( 'page_builder', $detected_keys );
		$this->assertContains( 'consent', $detected_keys );
		$this->assertContains( 'slider', $detected_keys );
		$this->assertContains( 'optimizer_overlap', $detected_keys );
		$this->assertContains( 'external_cache', $detected_keys );
		$this->assertContains( 'external_lazy_load', $detected_keys );
		$this->assertContains( 'optimizer_overlap', $recommendation_keys );
		$this->assertContains( 'external_cache', $recommendation_keys );
		$this->assertContains( 'page_builder_optimization', $recommendation_keys );
	}

	/**
	 * It prioritizes high-impact recommendations.
	 */
	public function test_report_prioritizes_high_impact_recommendations() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array(
					'woocommerce/woocommerce.php',
					'autoptimize/autoptimize.php',
				),
			)
		);

		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_sitewide_plugins', array() ),
				'return' => array(),
			)
		);

		$profile = SettingsSetupProfile::factory()->report(
			array(
				'enable_page_cache' => false,
				'object_cache'       => 'off',
			)
		);

		$recommendation_keys = array_column( $profile['recommendations'], 'key' );

		$this->assertSame( 'page_cache', $recommendation_keys[0] );
		$this->assertSame( 'woocommerce_safeguards', $recommendation_keys[1] );
		$this->assertSame( 'optimizer_overlap', $recommendation_keys[2] );
	}
}
