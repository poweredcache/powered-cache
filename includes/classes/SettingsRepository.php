<?php
/**
 * Settings repository.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\SETTING_OPTION;

/**
 * Read and write Powered Cache settings through the current option storage.
 *
 * This class keeps the legacy `powered_cache_settings` option as the storage
 * source while providing a schema-aware API for 4.0 services.
 *
 * @since 4.0.0
 */
class SettingsRepository {
	const EXPORT_FORMAT         = 'powered-cache-settings';
	const EXPORT_FORMAT_VERSION = '1.0';

	/**
	 * Whether settings should be read from network options.
	 *
	 * @var bool
	 */
	private $network_wide;

	/**
	 * Runtime context for dynamic defaults.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param bool|null $network_wide Whether to use network option storage.
	 * @param array     $context Runtime context for dynamic defaults.
	 */
	public function __construct( $network_wide = null, array $context = array() ) {
		$this->network_wide = null === $network_wide ? $this->detect_network_mode() : (bool) $network_wide;
		$this->context      = empty( $context ) ? $this->default_context() : $context;
	}

	/**
	 * Create a repository instance.
	 *
	 * @param bool|null $network_wide Whether to use network option storage.
	 * @param array     $context Runtime context for dynamic defaults.
	 *
	 * @return SettingsRepository
	 */
	public static function factory( $network_wide = null, array $context = array() ) {
		return new self( $network_wide, $context );
	}

	/**
	 * Read normalized settings.
	 *
	 * @return array
	 */
	public function all() {
		return $this->normalize( self::migrate_legacy_settings( $this->read_raw() ), $this->defaults() );
	}

	/**
	 * Get one setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback value when the key is unknown.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Save a full settings payload.
	 *
	 * @param array $settings Settings to save.
	 *
	 * @return bool
	 */
	public function save( array $settings ) {
		$settings = $this->sanitize( self::migrate_legacy_settings( $this->normalize( $settings ) ) );

		if ( $this->network_wide ) {
			return (bool) update_site_option( SETTING_OPTION, $settings );
		}

		return (bool) update_option( SETTING_OPTION, $settings );
	}

	/**
	 * Merge a partial settings payload into the current settings.
	 *
	 * @param array $settings Settings patch.
	 *
	 * @return bool
	 */
	public function update( array $settings ) {
		return $this->save( array_merge( $this->all(), $settings ) );
	}

	/**
	 * Delete the stored settings option.
	 *
	 * @return bool
	 */
	public function delete() {
		if ( $this->network_wide ) {
			return (bool) delete_site_option( SETTING_OPTION );
		}

		return (bool) delete_option( SETTING_OPTION );
	}

	/**
	 * Determine whether a raw settings payload needs 4.0 compatibility migration.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return bool
	 */
	public static function has_legacy_settings( array $settings ) {
		foreach ( array( 'accepted_query_strings', 'js_execution_method' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Migrate known legacy settings without dropping rollback-safe keys.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	public static function migrate_legacy_settings( array $settings ) {
		if ( ! empty( $settings['accepted_query_strings'] ) && empty( $settings['ignored_query_strings'] ) ) {
			$settings['ignored_query_strings'] = $settings['accepted_query_strings'];
		}

		if ( ! empty( $settings['js_execution_method'] ) && in_array( $settings['js_execution_method'], array( 'async', 'defer' ), true ) ) {
			$settings['js_defer'] = true;
		}

		if ( ! empty( $settings['js_execution_method'] ) && in_array( $settings['js_execution_method'], array( 'delay', 'delayed' ), true ) ) {
			$settings['js_delay']   = true;
			$settings['combine_js'] = false;
		}

		return $settings;
	}

	/**
	 * Return deprecated schema keys.
	 *
	 * @return array
	 */
	public static function deprecated_keys() {
		$deprecated = array();

		foreach ( SettingsSchema::fields() as $key => $field ) {
			if ( ! empty( $field['deprecated'] ) ) {
				$deprecated[] = $key;
			}
		}

		return $deprecated;
	}

	/**
	 * Build a versioned settings export payload.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public static function export_payload( array $settings ) {
		return array(
			'format'         => self::EXPORT_FORMAT,
			'format_version' => self::EXPORT_FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'generated_at'   => gmdate( 'c' ),
			'settings'       => $settings,
		);
	}

	/**
	 * Extract settings from a versioned or legacy import payload.
	 *
	 * @param mixed $payload Decoded JSON payload.
	 *
	 * @return array
	 */
	public static function settings_from_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return array();
		}

		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			return $payload['settings'];
		}

		return $payload;
	}

	/**
	 * Remove sensitive values from settings before export or REST output.
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

	/**
	 * Return filtered settings defaults.
	 *
	 * @return array
	 */
	public function defaults() {
		$defaults = SettingsSchema::defaults( $this->context );

		/**
		 * Filter default settings.
		 *
		 * @hook   powered_cache_default_settings
		 *
		 * @param  {array} $defaults Default settings.
		 *
		 * @return {array} New value
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_default_settings', $defaults );
	}

	/**
	 * Normalize settings with schema defaults.
	 *
	 * Unknown keys are preserved for backward compatibility with older
	 * extensions, imports, or customer customizations.
	 *
	 * @param array      $settings Raw settings.
	 * @param array|null $defaults Optional defaults.
	 *
	 * @return array
	 */
	public function normalize( array $settings, array $defaults = null ) {
		if ( null === $defaults ) {
			$defaults = SettingsSchema::defaults( $this->context );
		}

		return array_merge( $defaults, $settings );
	}

	/**
	 * Sanitize known settings using schema metadata.
	 *
	 * Unknown keys are preserved because the legacy option array is public and
	 * may contain values added by older versions or extensions.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	public function sanitize( array $settings ) {
		$fields    = SettingsSchema::fields( $this->context );
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			$sanitized[ $key ] = $this->sanitize_value( $value, $fields[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Read raw option payload.
	 *
	 * @return array
	 */
	private function read_raw() {
		$settings = $this->network_wide ? get_site_option( SETTING_OPTION, array() ) : get_option( SETTING_OPTION, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Sanitize one value.
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field metadata.
	 *
	 * @return mixed
	 */
	private function sanitize_value( $value, array $field ) {
		switch ( $field['sanitizer'] ) {
			case SettingsSchema::SANITIZE_BOOLEAN:
				return ! empty( $value );
			case SettingsSchema::SANITIZE_INTEGER:
				return $this->absint( $value );
			case SettingsSchema::SANITIZE_ARRAY:
				return $this->sanitize_array( $value );
			case SettingsSchema::SANITIZE_ENUM:
				$value = $this->sanitize_text( $value );

				if ( ! empty( $field['enum'] ) && ! in_array( $value, $field['enum'], true ) ) {
					return $field['default'];
				}

				return $value;
			case SettingsSchema::SANITIZE_TEXTAREA:
				return $this->sanitize_textarea( $value );
			case SettingsSchema::SANITIZE_TEXT:
			default:
				return $this->sanitize_text( $value );
		}
	}

	/**
	 * Sanitize an array as a list of text values.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array
	 */
	private function sanitize_array( $value ) {
		$values = is_array( $value ) ? $value : array( $value );

		return array_map( array( $this, 'sanitize_text' ), array_values( $values ) );
	}

	/**
	 * Sanitize a text value.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private function sanitize_text( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}

	/**
	 * Sanitize a textarea value.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private function sanitize_textarea( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $value );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}

	/**
	 * Return an absolute integer.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private function absint( $value ) {
		if ( function_exists( 'absint' ) ) {
			return absint( $value );
		}

		return abs( (int) $value );
	}

	/**
	 * Detect network mode.
	 *
	 * @return bool
	 */
	private function detect_network_mode() {
		return defined( 'POWERED_CACHE_IS_NETWORK' ) && POWERED_CACHE_IS_NETWORK;
	}

	/**
	 * Build default runtime context.
	 *
	 * @return array
	 */
	private function default_context() {
		global $is_apache;

		return array(
			'is_apache' => (bool) $is_apache,
		);
	}
}
