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
			'kadence-blocks/kadence-blocks.php',
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

	/**
	 * It reports Perfmatters as an overlapping optimization layer.
	 */
	public function test_report_detects_perfmatters_optimizer_overlap() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array( 'perfmatters/perfmatters.php' ),
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

		$this->assertContains( 'optimizer_overlap', array_column( $profile['detected'], 'key' ) );
		$this->assertContains( 'optimizer_overlap', array_column( $profile['recommendations'], 'key' ) );
	}

	/**
	 * It recommends connecting Cloudflare when edge signals are detected.
	 */
	public function test_report_recommends_cloudflare_integration_when_edge_signals_are_detected() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array(),
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

		$profile = SettingsSetupProfile::factory(
			array(
				'active_environments' => array( 'cdn:cloudflare' ),
			)
		)->report(
			array(
				'enable_cloudflare' => false,
				'enable_page_cache' => true,
				'object_cache'       => 'off',
			)
		);

		$detected_keys       = array_column( $profile['detected'], 'key' );
		$recommendation_keys = array_column( $profile['recommendations'], 'key' );

		$this->assertContains( 'cloudflare', $detected_keys );
		$this->assertContains( 'cloudflare_integration', $recommendation_keys );
	}

	/**
	 * It reports precise hosting and server recommendations.
	 */
	public function test_report_recommends_hosting_and_server_actions_from_environment_signals() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array(),
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

		$profile = SettingsSetupProfile::factory(
			array(
				'active_environments' => array( 'host:kinsta', 'server:nginx' ),
			)
		)->report(
			array(
				'auto_configure_htaccess' => true,
				'enable_page_cache'       => true,
				'object_cache'            => 'off',
			)
		);

		$detected_keys       = array_column( $profile['detected'], 'key' );
		$recommendation_keys = array_column( $profile['recommendations'], 'key' );

		$this->assertContains( 'host_kinsta', $detected_keys );
		$this->assertContains( 'server_nginx', $detected_keys );
		$this->assertContains( 'managed_host_cache', $recommendation_keys );
		$this->assertContains( 'nginx_htaccess', $recommendation_keys );
	}

	/**
	 * It skips Cloudflare integration recommendation when already enabled.
	 */
	public function test_report_skips_cloudflare_integration_when_enabled() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array( 'cloudflare/cloudflare.php' ),
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
				'enable_cloudflare' => true,
				'enable_page_cache' => true,
				'object_cache'       => 'off',
			)
		);

		$detected_keys       = array_column( $profile['detected'], 'key' );
		$recommendation_keys = array_column( $profile['recommendations'], 'key' );

		$this->assertContains( 'cloudflare', $detected_keys );
		$this->assertNotContains( 'cloudflare_integration', $recommendation_keys );
	}

	/**
	 * It reports recommended setup progress.
	 */
	public function test_report_includes_recommended_setup_progress() {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'active_plugins', array() ),
				'return' => array(),
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

		$settings = SettingsSchema::recommended( array( 'is_apache' => false ), false );
		$profile  = SettingsSetupProfile::factory( array( 'is_apache' => false ) )->report( $settings );

		$this->assertArrayHasKey( 'recommended', $profile );
		$this->assertTrue( $profile['recommended']['applied'] );
		$this->assertSame( $profile['recommended']['total'], $profile['recommended']['matched'] );
		$this->assertSame( array(), $profile['recommended']['pending'] );
	}
}
