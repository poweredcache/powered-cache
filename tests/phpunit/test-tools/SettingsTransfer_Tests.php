<?php
/**
 * Settings transfer tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings transfer test case.
 */
class SettingsTransfer_Tests extends TestCase {

	/**
	 * It builds a versioned export payload.
	 */
	public function test_pack_builds_versioned_payload() {
		$payload = SettingsTransfer::pack(
			array(
				'enable_page_cache' => true,
				'cache_timeout'     => 60,
			)
		);

		$this->assertSame( SettingsTransfer::FORMAT, $payload['format'] );
		$this->assertSame( SettingsTransfer::FORMAT_VERSION, $payload['format_version'] );
		$this->assertSame( POWERED_CACHE_VERSION, $payload['plugin_version'] );
		$this->assertNotEmpty( $payload['generated_at'] );
		$this->assertSame( 60, $payload['settings']['cache_timeout'] );
	}

	/**
	 * It extracts settings from versioned payloads.
	 */
	public function test_unpack_reads_versioned_payload_settings() {
		$payload = array(
			'format'         => SettingsTransfer::FORMAT,
			'format_version' => SettingsTransfer::FORMAT_VERSION,
			'settings'       => array(
				'enable_page_cache' => false,
				'cache_timeout'     => 120,
			),
		);

		$this->assertSame( $payload['settings'], SettingsTransfer::unpack( $payload ) );
	}

	/**
	 * It keeps legacy flat exports importable.
	 */
	public function test_unpack_accepts_legacy_flat_settings_payload() {
		$payload = array(
			'enable_page_cache' => true,
			'cache_timeout'     => 30,
		);

		$this->assertSame( $payload, SettingsTransfer::unpack( $payload ) );
	}

	/**
	 * It preserves Premium settings in portable payloads.
	 */
	public function test_premium_settings_survive_pack_and_unpack() {
		$settings = array(
			'critical_css'                     => true,
			'critical_css_additional_files'    => '/theme.css',
			'remove_unused_css'                => true,
			'ucss_safelist'                    => '.is-active',
			'ucss_excluded_files'              => '/dynamic.css',
			'enable_image_optimization'        => true,
			'image_optimizer_preferred_format' => 'webp',
			'add_missing_image_dimensions'     => true,
			'enable_lcp_optimization'          => true,
			'enable_google_tracking'           => true,
			'enable_fb_tracking'               => true,
		);

		$payload = SettingsTransfer::pack( $settings );

		$this->assertSame( $settings, SettingsTransfer::unpack( $payload ) );
	}

	/**
	 * It removes sensitive values before export.
	 */
	public function test_redact_sensitive_clears_secret_values() {
		$settings = SettingsTransfer::redact_sensitive(
			array(
				'cloudflare_email'     => 'admin@example.test',
				'cloudflare_api_key'   => 'secret-key',
				'cloudflare_api_token' => 'secret-token',
				'enable_page_cache'    => true,
			)
		);

		$this->assertSame( '', $settings['cloudflare_email'] );
		$this->assertSame( '', $settings['cloudflare_api_key'] );
		$this->assertSame( '', $settings['cloudflare_api_token'] );
		$this->assertTrue( $settings['enable_page_cache'] );
	}
}
