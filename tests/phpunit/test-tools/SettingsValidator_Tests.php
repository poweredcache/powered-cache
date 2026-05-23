<?php
/**
 * Settings validator tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings validator test case.
 */
class SettingsValidator_Tests extends TestCase {

	/**
	 * It reports invalid schema values.
	 */
	public function test_reports_invalid_schema_values() {
		$validator = new SettingsValidator( array(), true );
		$report    = $validator->report(
			array(
				'object_cache'              => 'bad-cache',
				'cache_timeout'             => 'not-a-number',
				'cdn_hostname'              => 'cdn.example.test',
				'enable_image_optimization' => true,
			)
		);

		$this->assertFalse( $report['valid'] );
		$this->assertSame( 3, $report['counts'][ SettingsValidator::SEVERITY_ERROR ] );
		$this->assertIssueExists( 'object_cache', 'invalid_enum', $report['issues'] );
		$this->assertIssueExists( 'cache_timeout', 'invalid_integer', $report['issues'] );
		$this->assertIssueExists( 'cdn_hostname', 'invalid_array', $report['issues'] );
	}

	/**
	 * It reports inactive dependency values.
	 */
	public function test_reports_inactive_dependency_values() {
		$validator = new SettingsValidator( array(), true );
		$report    = $validator->report(
			array(
				'enable_lazy_load'     => false,
				'lazy_load_exclusions' => '.skip-lazy',
				'lazy_load_youtube'    => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 2, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
		$this->assertIssueExists( 'lazy_load_exclusions', 'inactive_dependency', $report['issues'] );
		$this->assertIssueExists( 'lazy_load_youtube', 'inactive_dependency', $report['issues'] );
	}

	/**
	 * It ignores CDN zone placeholders when no hostname is configured.
	 */
	public function test_ignores_empty_cdn_zone_placeholders() {
		$validator = new SettingsValidator( array(), true );
		$report    = $validator->report(
			array(
				'enable_cdn'   => false,
				'cdn_hostname' => array( '' ),
				'cdn_zone'     => array( 'all' ),
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 0, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
	}

	/**
	 * It reports locked Premium values without making the report invalid.
	 */
	public function test_reports_locked_premium_values() {
		$validator = new SettingsValidator( array(), false );
		$report    = $validator->report(
			array(
				'critical_css' => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 1, $report['counts'][ SettingsValidator::SEVERITY_INFO ] );
		$this->assertIssueExists( 'critical_css', 'locked_setting_preserved', $report['issues'] );
	}

	/**
	 * It reports unknown settings as compatibility info.
	 */
	public function test_reports_unknown_settings_as_info() {
		$validator = new SettingsValidator( array(), true );
		$report    = $validator->report(
			array(
				'custom_extension_key' => 'keep',
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 1, $report['counts'][ SettingsValidator::SEVERITY_INFO ] );
		$this->assertIssueExists( 'custom_extension_key', 'unknown_setting', $report['issues'] );
	}

	/**
	 * It includes compatibility registry notes.
	 */
	public function test_reports_compatibility_registry_notes() {
		$validator = new SettingsValidator( array(), true );
		$report    = $validator->report(
			array(
				'js_delay' => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 1, $report['counts'][ SettingsValidator::SEVERITY_INFO ] );
		$this->assertIssueExists( 'js_delay', 'compatibility_rule_applied', $report['issues'] );
	}

	/**
	 * It reports page cache runtime readiness issues.
	 */
	public function test_reports_page_cache_runtime_readiness_issues() {
		$validator = new SettingsValidator(
			array(
				'wp_cache_enabled'       => true,
				'page_cache_loaded'      => false,
				'page_cache_has_problem' => true,
			),
			true
		);
		$report    = $validator->report(
			array(
				'enable_page_cache' => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 2, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
		$this->assertIssueExists( 'enable_page_cache', 'advanced_cache_dropin_inactive', $report['issues'] );
		$this->assertIssueExists( 'enable_page_cache', 'page_cache_dropin_unavailable', $report['issues'] );
	}

	/**
	 * It reports missing WP_CACHE as a page cache readiness issue.
	 */
	public function test_reports_missing_wp_cache_runtime_readiness_issue() {
		$validator = new SettingsValidator(
			array(
				'wp_cache_enabled'  => false,
				'page_cache_loaded' => false,
			),
			true
		);
		$report    = $validator->report(
			array(
				'enable_page_cache' => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 1, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
		$this->assertIssueExists( 'enable_page_cache', 'wp_cache_disabled', $report['issues'] );
	}

	/**
	 * It reports object cache runtime readiness issues.
	 */
	public function test_reports_object_cache_runtime_readiness_issues() {
		$validator = new SettingsValidator(
			array(
				'object_cache_dropin_exists' => false,
				'object_cache_has_problem'   => true,
			),
			true
		);
		$report    = $validator->report(
			array(
				'object_cache' => 'redis',
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 2, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
		$this->assertIssueExists( 'object_cache', 'object_cache_dropin_missing', $report['issues'] );
		$this->assertIssueExists( 'object_cache', 'object_cache_dropin_unavailable', $report['issues'] );
	}

	/**
	 * It reports Apache runtime readiness issues.
	 */
	public function test_reports_apache_runtime_readiness_issues() {
		$validator = new SettingsValidator(
			array(
				'is_apache'         => true,
				'htaccess_exists'   => true,
				'htaccess_writable' => false,
			),
			true
		);
		$report    = $validator->report(
			array(
				'auto_configure_htaccess' => true,
			)
		);

		$this->assertTrue( $report['valid'] );
		$this->assertSame( 1, $report['counts'][ SettingsValidator::SEVERITY_WARNING ] );
		$this->assertIssueExists( 'auto_configure_htaccess', 'htaccess_not_writable', $report['issues'] );
	}

	/**
	 * Assert that a validation issue exists.
	 *
	 * @param string $key Setting key.
	 * @param string $code Issue code.
	 * @param array  $issues Issues.
	 */
	private function assertIssueExists( $key, $code, array $issues ) {
		foreach ( $issues as $issue ) {
			if ( $key === $issue['key'] && $code === $issue['code'] ) {
				$this->assertTrue( true );
				return;
			}
		}

		$this->fail( sprintf( 'Expected issue %s for %s.', $code, $key ) );
	}
}
