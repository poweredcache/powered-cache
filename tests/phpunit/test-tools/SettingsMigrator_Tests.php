<?php
/**
 * Settings migrator tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings migrator test case.
 */
class SettingsMigrator_Tests extends TestCase {

	/**
	 * It exposes versioned migration step metadata.
	 */
	public function test_exposes_versioned_migration_steps() {
		$migrator = new SettingsMigrator();
		$steps    = $migrator->steps();

		$this->assertSame( '4.0.0', $migrator->target_version() );
		$this->assertArrayHasKey( 'accepted_query_strings', $steps );
		$this->assertSame( array( 'accepted_query_strings' ), $steps['accepted_query_strings']['source_keys'] );
		$this->assertSame( array( 'ignored_query_strings' ), $steps['accepted_query_strings']['target_keys'] );
		$this->assertTrue( $steps['accepted_query_strings']['preserve_source'] );
		$this->assertArrayHasKey( 'js_execution_method', $steps );
		$this->assertSame( array( 'js_defer', 'js_delay', 'combine_js' ), $steps['js_execution_method']['target_keys'] );
		$this->assertTrue( $steps['js_execution_method']['preserve_source'] );
	}

	/**
	 * It reports migration steps that apply to a settings payload.
	 */
	public function test_applicable_steps_reports_matching_legacy_settings() {
		$migrator = new SettingsMigrator();

		$steps = $migrator->applicable_steps(
			array(
				'accepted_query_strings' => 'utm_source',
				'js_execution_method'    => '',
			)
		);

		$this->assertArrayHasKey( 'accepted_query_strings', $steps );
		$this->assertArrayNotHasKey( 'js_execution_method', $steps );
	}

	/**
	 * It migrates accepted query strings into ignored query strings.
	 */
	public function test_migrates_accepted_query_strings() {
		$migrator = new SettingsMigrator();

		$settings = $migrator->migrate(
			array(
				'accepted_query_strings' => 'utm_source',
			)
		);

		$this->assertSame( 'utm_source', $settings['ignored_query_strings'] );
		$this->assertSame( 'utm_source', $settings['accepted_query_strings'] );
	}

	/**
	 * It does not overwrite an existing ignored query string value.
	 */
	public function test_does_not_overwrite_existing_ignored_query_strings() {
		$migrator = new SettingsMigrator();

		$settings = $migrator->migrate(
			array(
				'accepted_query_strings' => 'utm_source',
				'ignored_query_strings'  => 'gclid',
			)
		);

		$this->assertSame( 'gclid', $settings['ignored_query_strings'] );
	}

	/**
	 * It migrates deprecated JS execution method values.
	 */
	public function test_migrates_js_execution_method() {
		$migrator = new SettingsMigrator();

		$defer_settings = $migrator->migrate(
			array(
				'js_execution_method' => 'defer',
			)
		);

		$delay_settings = $migrator->migrate(
			array(
				'js_execution_method' => 'delayed',
				'combine_js'          => true,
			)
		);

		$this->assertTrue( $defer_settings['js_defer'] );
		$this->assertTrue( $delay_settings['js_delay'] );
		$this->assertFalse( $delay_settings['combine_js'] );
	}

	/**
	 * It preserves deprecated keys when preparing settings for storage.
	 */
	public function test_for_storage_preserves_deprecated_keys() {
		$migrator = new SettingsMigrator();

		$settings = $migrator->for_storage(
			array(
				'accepted_query_strings'      => 'utm_source',
				'js_execution_method'         => 'defer',
				'js_execution_optimized_only' => true,
				'ssl_cache'                   => true,
				'enable_page_cache'           => true,
			)
		);

		$this->assertSame( 'utm_source', $settings['accepted_query_strings'] );
		$this->assertSame( 'utm_source', $settings['ignored_query_strings'] );
		$this->assertSame( 'defer', $settings['js_execution_method'] );
		$this->assertTrue( $settings['js_execution_optimized_only'] );
		$this->assertTrue( $settings['ssl_cache'] );
		$this->assertTrue( $settings['js_defer'] );
		$this->assertTrue( $settings['enable_page_cache'] );
	}

	/**
	 * It exposes deprecated keys from the schema.
	 */
	public function test_deprecated_keys_are_schema_driven() {
		$migrator = new SettingsMigrator();

		$this->assertContains( 'ssl_cache', $migrator->deprecated_keys() );
		$this->assertContains( 'js_execution_method', $migrator->deprecated_keys() );
		$this->assertContains( 'js_execution_optimized_only', $migrator->deprecated_keys() );
	}
}
