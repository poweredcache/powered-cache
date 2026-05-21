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
	 * It normalizes registry rules before exposing them.
	 */
	public function test_registry_rules_are_normalized() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'         => CompatibilityRules::FORMAT,
					'format_version' => CompatibilityRules::FORMAT_VERSION,
					'rules'          => array(
						'delay_exclusions' => array(
							' custom-script ',
							'custom-script',
							'',
							array( 'bad' ),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'custom-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It allows local registry replacement through a filter.
	 */
	public function test_registry_can_be_replaced_through_filter() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		$base = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'bundled-script' ),
			),
		);
		$replacement = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'replacement-script' ),
			),
		);

		file_put_contents( $file, json_encode( $base ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_registry' )->with( $base, $file )->reply( $replacement );

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'replacement-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
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

	/**
	 * It fails closed when the registry version is unsupported.
	 */
	public function test_unsupported_registry_version_returns_no_rules() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'         => CompatibilityRules::FORMAT,
					'format_version' => '99.0',
					'rules'          => array(
						'delay_exclusions' => array( 'x' ),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array(), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
