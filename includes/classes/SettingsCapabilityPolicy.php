<?php
/**
 * Settings capability policy.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Determines which schema fields can be edited in the current product context.
 *
 * @since 4.0.0
 */
class SettingsCapabilityPolicy {

	/**
	 * Whether Premium fields can be edited.
	 *
	 * @var bool|null
	 */
	private $premium_available;

	/**
	 * Runtime context for schema metadata.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 * @param array     $context Runtime context for schema metadata.
	 */
	public function __construct( $premium_available = null, array $context = array() ) {
		$this->premium_available = null === $premium_available ? null : (bool) $premium_available;
		$this->context           = $context;
	}

	/**
	 * Create a policy instance.
	 *
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 * @param array     $context Runtime context for schema metadata.
	 *
	 * @return SettingsCapabilityPolicy
	 */
	public static function factory( $premium_available = null, array $context = array() ) {
		return new self( $premium_available, $context );
	}

	/**
	 * Determine whether a setting key can be edited.
	 *
	 * Unknown keys stay editable for backward compatibility with extensions.
	 *
	 * @param string $key Setting key.
	 *
	 * @return bool
	 */
	public function can_edit( $key ) {
		$field = SettingsSchema::get( $key, $this->context );

		if ( ! $field || empty( $field['premium'] ) ) {
			return true;
		}

		return $this->is_premium_available();
	}

	/**
	 * Return why a setting is locked.
	 *
	 * @param string $key Setting key.
	 *
	 * @return string
	 */
	public function lock_reason( $key ) {
		if ( $this->can_edit( $key ) ) {
			return '';
		}

		$field = SettingsSchema::get( $key, $this->context );

		return $field && ! empty( $field['premium'] ) ? 'premium' : '';
	}

	/**
	 * Preserve locked settings while applying an incoming settings payload.
	 *
	 * This keeps Free installs from enabling Premium controls through REST,
	 * imports, or legacy form posts while avoiding silent loss of existing
	 * customer configuration.
	 *
	 * @param array $settings Incoming settings payload.
	 * @param array $current Current stored settings.
	 *
	 * @return array
	 */
	public function enforce( array $settings, array $current = array() ) {
		if ( $this->is_premium_available() ) {
			return $settings;
		}

		foreach ( SettingsSchema::fields( $this->context ) as $key => $field ) {
			if ( empty( $field['premium'] ) || ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$settings[ $key ] = array_key_exists( $key, $current ) ? $current[ $key ] : $field['default'];
		}

		return $settings;
	}

	/**
	 * Determine whether the current runtime has Premium available.
	 *
	 * @return bool
	 */
	private function is_premium_available() {
		if ( null !== $this->premium_available ) {
			return $this->premium_available;
		}

		if ( function_exists( '\PoweredCache\Utils\is_premium' ) ) {
			return (bool) \PoweredCache\Utils\is_premium();
		}

		return false;
	}
}
