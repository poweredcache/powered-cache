<?php
/**
 * Settings capability policy tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings capability policy test case.
 */
class SettingsCapabilityPolicy_Tests extends TestCase {

	/**
	 * It keeps Premium fields locked when Premium is unavailable.
	 */
	public function test_premium_fields_are_locked_without_premium() {
		$policy = new SettingsCapabilityPolicy( false );

		$this->assertTrue( $policy->can_edit( 'enable_page_cache' ) );
		$this->assertFalse( $policy->can_edit( 'enable_image_optimization' ) );
		$this->assertFalse( $policy->can_edit( 'prefetch_links' ) );
		$this->assertFalse( $policy->can_edit( 'enable_varnish' ) );
		$this->assertSame( 'premium', $policy->lock_reason( 'enable_image_optimization' ) );
		$this->assertSame( '', $policy->lock_reason( 'enable_page_cache' ) );
	}

	/**
	 * It allows Premium fields when Premium is available.
	 */
	public function test_premium_fields_are_editable_with_premium() {
		$policy = new SettingsCapabilityPolicy( true );

		$this->assertTrue( $policy->can_edit( 'enable_image_optimization' ) );
		$this->assertSame( '', $policy->lock_reason( 'enable_image_optimization' ) );
	}

	/**
	 * It preserves existing Premium values while applying Free changes.
	 */
	public function test_enforce_preserves_existing_premium_values_in_free() {
		$policy = new SettingsCapabilityPolicy( false );

		$settings = $policy->enforce(
			array(
				'enable_page_cache'         => false,
				'enable_image_optimization' => true,
				'critical_css'              => true,
				'prefetch_links'            => true,
				'enable_varnish'            => true,
			),
			array(
				'enable_page_cache'         => true,
				'enable_image_optimization' => false,
				'critical_css'              => true,
				'prefetch_links'            => false,
				'enable_varnish'            => false,
			)
		);

		$this->assertFalse( $settings['enable_page_cache'] );
		$this->assertFalse( $settings['enable_image_optimization'] );
		$this->assertTrue( $settings['critical_css'] );
		$this->assertFalse( $settings['prefetch_links'] );
		$this->assertFalse( $settings['enable_varnish'] );
	}

	/**
	 * It allows Premium changes when Premium is available.
	 */
	public function test_enforce_allows_premium_changes_with_premium() {
		$policy = new SettingsCapabilityPolicy( true );

		$settings = $policy->enforce(
			array(
				'enable_image_optimization' => true,
			),
			array(
				'enable_image_optimization' => false,
			)
		);

		$this->assertTrue( $settings['enable_image_optimization'] );
	}

	/**
	 * It keeps unknown extension settings editable.
	 */
	public function test_unknown_settings_remain_editable() {
		$policy = new SettingsCapabilityPolicy( false );

		$settings = $policy->enforce(
			array(
				'custom_extension_key' => 'changed',
			),
			array(
				'custom_extension_key' => 'current',
			)
		);

		$this->assertTrue( $policy->can_edit( 'custom_extension_key' ) );
		$this->assertSame( 'changed', $settings['custom_extension_key'] );
	}
}
