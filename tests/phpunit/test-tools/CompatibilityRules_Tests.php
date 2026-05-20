<?php
/**
 * Compatibility rules tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Compatibility rules test case.
 */
class CompatibilityRules_Tests extends TestCase {

	/**
	 * It loads the bundled compatibility registry.
	 */
	public function test_bundled_registry_loads_default_rules() {
		$rules = CompatibilityRules::factory();

		$this->assertContains( 'wp-includes/js/dist/interactivity.min.js', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'wp-includes/blocks/image/view.min.js', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'selectors.core.image.lightboxObjectFit', $rules->rules( 'lazy_load_exclusions' ) );
	}

	/**
	 * It ignores unknown registry buckets.
	 */
	public function test_unknown_rule_bucket_returns_empty_list() {
		$rules = CompatibilityRules::factory();

		$this->assertSame( array(), $rules->rules( 'missing_bucket' ) );
	}

	/**
	 * It appends default rules without duplicating existing exclusions.
	 */
	public function test_filter_callbacks_append_unique_rules() {
		$rules = CompatibilityRules::factory();

		$delay_exclusions = $rules->add_delay_exclusions(
			array(
				'custom-script',
				'wp-includes/js/dist/interactivity.min.js',
			)
		);

		$this->assertSame(
			array(
				'custom-script',
				'wp-includes/js/dist/interactivity.min.js',
				'wp-includes/blocks/image/view.min.js',
			),
			$delay_exclusions
		);

		$this->assertSame(
			array(
				'selectors.core.image.lightboxObjectFit',
			),
			$rules->add_lazy_load_exclusions( array() )
		);
	}

	/**
	 * It fails closed when the registry payload is invalid.
	 */
	public function test_invalid_registry_payload_returns_no_rules() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents( $file, '{"format":"invalid","rules":{"delay_exclusions":["x"]}}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array(), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
