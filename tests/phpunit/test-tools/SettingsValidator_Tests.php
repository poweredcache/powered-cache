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
