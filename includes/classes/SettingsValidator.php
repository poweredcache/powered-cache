<?php
/**
 * Settings validator.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Optimizer\Helper;

/**
 * Builds non-blocking validation reports for settings payloads.
 *
 * @since 4.0.0
 */
class SettingsValidator {

	const SEVERITY_ERROR   = 'error';
	const SEVERITY_WARNING = 'warning';
	const SEVERITY_INFO    = 'info';

	/**
	 * Settings whose lines are matched as regular expression fragments.
	 *
	 * Keep this list in sync with the places that build `#(line|line)#` patterns from
	 * these settings: Optimizer\Helper, LazyLoad, CDN, AdvancedCache, the page cache
	 * drop-in, and Premium's unused CSS exclusions.
	 *
	 * @var string[]
	 */
	const REGEX_FIELDS = array(
		'rejected_uri',
		'rejected_user_agents',
		'rejected_cookies',
		'excluded_css_files',
		'excluded_js_files',
		'js_defer_exclusions',
		'js_delay_exclusions',
		'lazy_load_exclusions',
		'cdn_rejected_files',
		'ucss_excluded_files',
	);

	/**
	 * Runtime context for schema metadata.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Whether Premium fields can be edited.
	 *
	 * @var bool|null
	 */
	private $premium_available;

	/**
	 * Constructor.
	 *
	 * @param array     $context Runtime context.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 */
	public function __construct( array $context = array(), $premium_available = null ) {
		$this->context           = $context;
		$this->premium_available = null === $premium_available ? null : (bool) $premium_available;
	}

	/**
	 * Create a validator instance.
	 *
	 * @param array     $context Runtime context.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return SettingsValidator
	 */
	public static function factory( array $context = array(), $premium_available = null ) {
		return new self( $context, $premium_available );
	}

	/**
	 * Validate a settings payload.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public function validate( array $settings ) {
		$issues = array();
		$fields = SettingsSchema::fields( $this->context );

		foreach ( $settings as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$issues[] = $this->issue(
					$key,
					self::SEVERITY_INFO,
					'unknown_setting',
					'This setting is not defined in the current schema and will be preserved for compatibility.'
				);

				continue;
			}

			$issues = array_merge( $issues, $this->validate_field( $key, $value, $fields[ $key ], $settings ) );
		}

		$issues = array_merge( $issues, CompatibilityRules::factory()->settings_issues( $settings ) );
		$issues = array_merge( $issues, $this->validate_environment( $settings ) );

		/**
		 * Filter settings validation issues.
		 *
		 * @hook powered_cache_settings_validation_issues
		 *
		 * @param {array} $issues Validation issues.
		 * @param {array} $settings Settings payload.
		 *
		 * @return {array} New value.
		 *
		 * @since 4.0.0
		 */
		return apply_filters( 'powered_cache_settings_validation_issues', $issues, $settings );
	}

	/**
	 * Build a validation summary.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public function report( array $settings ) {
		$issues = $this->validate( $settings );
		$counts = array(
			self::SEVERITY_ERROR   => 0,
			self::SEVERITY_WARNING => 0,
			self::SEVERITY_INFO    => 0,
		);

		foreach ( $issues as $issue ) {
			if ( isset( $counts[ $issue['severity'] ] ) ) {
				$counts[ $issue['severity'] ]++;
			}
		}

		return array(
			'valid'  => 0 === $counts[ self::SEVERITY_ERROR ],
			'counts' => $counts,
			'issues' => $issues,
		);
	}

	/**
	 * Validate one known schema field.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @param array  $field Schema field.
	 * @param array  $settings Full settings payload.
	 *
	 * @return array
	 */
	private function validate_field( $key, $value, array $field, array $settings ) {
		$issues = array();

		if ( ! SettingsSchema::can_edit( $key, $this->context, $this->premium_available ) && $this->has_value( $value, $field ) ) {
			$issues[] = $this->issue(
				$key,
				self::SEVERITY_INFO,
				'locked_setting_preserved',
				'This setting is preserved but cannot be edited in the current product context.'
			);
		}

		if ( SettingsSchema::TYPE_ENUM === $field['type'] && ! empty( $field['enum'] ) && ! in_array( $value, $field['enum'], true ) ) {
			$issues[] = $this->issue(
				$key,
				self::SEVERITY_ERROR,
				'invalid_enum',
				'This setting contains a value outside the allowed options.'
			);
		}

		if ( SettingsSchema::TYPE_INTEGER === $field['type'] && ! is_numeric( $value ) ) {
			$issues[] = $this->issue(
				$key,
				self::SEVERITY_ERROR,
				'invalid_integer',
				'This setting must be a number.'
			);
		}

		if ( SettingsSchema::TYPE_ARRAY === $field['type'] && ! is_array( $value ) ) {
			$issues[] = $this->issue(
				$key,
				self::SEVERITY_ERROR,
				'invalid_array',
				'This setting must be a list.'
			);
		}

		if ( in_array( $key, self::REGEX_FIELDS, true ) && is_string( $value ) && $this->dependencies_met( $field, $settings ) ) {
			$invalid_patterns = $this->invalid_patterns( $value );

			if ( ! empty( $invalid_patterns ) ) {
				// Warning rather than error: the value is still saved and invalid lines are skipped at runtime.
				$issues[] = $this->issue(
					$key,
					self::SEVERITY_WARNING,
					'invalid_pattern',
					sprintf(
						'These lines are not valid patterns and will be ignored: %s. Use * as a wildcard, or escape regex characters such as ( ) [ ] + ? and # with a backslash.',
						implode( ', ', $invalid_patterns )
					)
				);
			}
		}

		return $issues;
	}

	/**
	 * Whether every parent toggle of a field is enabled.
	 *
	 * Values of inactive settings are kept but not used, so they should not raise warnings.
	 *
	 * @param array $field    Schema field.
	 * @param array $settings Full settings payload.
	 *
	 * @return bool
	 */
	private function dependencies_met( array $field, array $settings ) {
		foreach ( (array) ( $field['dependencies'] ?? array() ) as $dependency ) {
			if ( empty( $settings[ $dependency ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find the lines of a multi-line setting that do not compile as regex fragments.
	 *
	 * @param string $value Multi-line setting value.
	 *
	 * @return string[] Invalid lines, trimmed.
	 */
	private function invalid_patterns( $value ) {
		$invalid = array();
		$lines   = preg_split( '#(\r\n|\r|\n)#', $value, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );

			if ( '' !== $line && ! Helper::is_valid_exclusion_pattern( $line ) ) {
				$invalid[] = $line;
			}
		}

		return array_values( array_unique( $invalid ) );
	}

	/**
	 * Validate runtime environment signals that can keep settings from working.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	private function validate_environment( array $settings ) {
		$issues = array();

		if ( ! empty( $settings['enable_page_cache'] ) ) {
			if ( $this->context_flag_is( 'wp_cache_enabled', false ) ) {
				$issues[] = $this->issue(
					'enable_page_cache',
					self::SEVERITY_WARNING,
					'wp_cache_disabled',
					'Page cache needs WP_CACHE enabled in wp-config.php before cached pages can be served.'
				);
			}

			if ( $this->context_flag_is( 'page_cache_loaded', false ) && $this->context_flag_is( 'wp_cache_enabled', true ) ) {
				$issues[] = $this->issue(
					'enable_page_cache',
					self::SEVERITY_WARNING,
					'advanced_cache_dropin_inactive',
					'The advanced-cache.php drop-in is not loading Powered Cache page caching. Save settings to recreate the configuration files.'
				);
			}

			if ( $this->context_flag_is( 'page_cache_has_problem', true ) ) {
				$issues[] = $this->issue(
					'enable_page_cache',
					self::SEVERITY_WARNING,
					'page_cache_dropin_unavailable',
					'Powered Cache could not load the page cache drop-in from this hosting environment.'
				);
			}
		}

		if ( ! empty( $settings['object_cache'] ) && 'off' !== $settings['object_cache'] ) {
			if ( $this->context_flag_is( 'object_cache_dropin_exists', false ) ) {
				$issues[] = $this->issue(
					'object_cache',
					self::SEVERITY_WARNING,
					'object_cache_dropin_missing',
					'Object cache is enabled, but wp-content/object-cache.php is missing or not accessible.'
				);
			}

			if ( $this->context_flag_is( 'object_cache_has_problem', true ) ) {
				$issues[] = $this->issue(
					'object_cache',
					self::SEVERITY_WARNING,
					'object_cache_dropin_unavailable',
					'Powered Cache could not load the selected object cache drop-in from this hosting environment.'
				);
			}
		}

		if ( ! empty( $settings['auto_configure_htaccess'] ) && $this->context_flag_is( 'is_apache', true ) ) {
			if ( $this->context_flag_is( 'htaccess_exists', false ) ) {
				$issues[] = $this->issue(
					'auto_configure_htaccess',
					self::SEVERITY_WARNING,
					'htaccess_missing',
					'Automatic .htaccess configuration is enabled, but the .htaccess file could not be found.'
				);
			} elseif ( $this->context_flag_is( 'htaccess_writable', false ) ) {
				$issues[] = $this->issue(
					'auto_configure_htaccess',
					self::SEVERITY_WARNING,
					'htaccess_not_writable',
					'Automatic .htaccess configuration is enabled, but the .htaccess file is not writable.'
				);
			}
		}

		if ( ! empty( $settings['enable_cloudflare'] ) && empty( $settings['cloudflare_zone'] ) ) {
			$issues[] = $this->issue(
				'cloudflare_zone',
				self::SEVERITY_WARNING,
				'cloudflare_zone_missing',
				'Cloudflare purge is enabled, but no Cloudflare Zone ID is configured.'
			);
		}

		if ( true === $this->premium_available && ! empty( $settings['enable_varnish'] ) && empty( $settings['varnish_ip'] ) && ! $this->has_varnish_default_target() ) {
			$issues[] = $this->issue(
				'varnish_ip',
				self::SEVERITY_WARNING,
				'varnish_target_missing',
				'Varnish purge is enabled, but no Varnish server IP address is configured.'
			);
		}

		return $issues;
	}

	/**
	 * Determine whether a Varnish purge target is configured through constants.
	 *
	 * @return bool
	 */
	private function has_varnish_default_target() {
		return defined( 'VHP_VARNISH_IP' ) && false !== VHP_VARNISH_IP && '' !== trim( (string) VHP_VARNISH_IP );
	}

	/**
	 * Determine whether a known runtime context flag matches an expected value.
	 *
	 * Missing flags are ignored so validators can run safely in older contexts.
	 *
	 * @param string $flag     Context flag.
	 * @param bool   $expected Expected value.
	 *
	 * @return bool
	 */
	private function context_flag_is( $flag, $expected ) {
		return array_key_exists( $flag, $this->context ) && (bool) $this->context[ $flag ] === (bool) $expected;
	}

	/**
	 * Determine whether a field has a meaningful value.
	 *
	 * @param mixed $value Setting value.
	 * @param array $field Schema field.
	 *
	 * @return bool
	 */
	private function has_value( $value, array $field ) {
		if ( SettingsSchema::TYPE_BOOLEAN === $field['type'] ) {
			return ! empty( $value );
		}

		if ( is_array( $value ) ) {
			return ! empty( array_filter( $value ) );
		}

		return '' !== (string) $value;
	}

	/**
	 * Build one validation issue.
	 *
	 * @param string $key Setting key.
	 * @param string $severity Issue severity.
	 * @param string $code Issue code.
	 * @param string $message Human-readable message.
	 *
	 * @return array
	 */
	private function issue( $key, $severity, $code, $message ) {
		return array(
			'key'      => $key,
			'severity' => $severity,
			'code'     => $code,
			'message'  => $message,
		);
	}
}
