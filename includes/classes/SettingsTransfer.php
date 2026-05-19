<?php
/**
 * Settings import/export payloads.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Builds and reads portable settings payloads.
 *
 * @since 4.0.0
 */
class SettingsTransfer {

	const FORMAT         = 'powered-cache-settings';
	const FORMAT_VERSION = '1.0';

	/**
	 * Build a versioned export payload.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public static function pack( array $settings ) {
		return array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'generated_at'   => gmdate( 'c' ),
			'settings'       => $settings,
		);
	}

	/**
	 * Extract settings from a versioned or legacy export payload.
	 *
	 * @param mixed $payload Decoded JSON payload.
	 *
	 * @return array
	 */
	public static function unpack( $payload ) {
		if ( ! is_array( $payload ) ) {
			return array();
		}

		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			return $payload['settings'];
		}

		return $payload;
	}

	/**
	 * Remove sensitive values from an export payload.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public static function redact_sensitive( array $settings ) {
		foreach ( self::sensitive_keys() as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = '';
			}
		}

		return $settings;
	}

	/**
	 * Return settings keys that should not be exported with values.
	 *
	 * @return array
	 */
	public static function sensitive_keys() {
		return array(
			'cloudflare_email',
			'cloudflare_api_key',
			'cloudflare_api_token',
		);
	}
}
