<?php
/**
 * Settings validator.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

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
	 * Runtime context for schema metadata.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Settings capability policy.
	 *
	 * @var SettingsCapabilityPolicy
	 */
	private $policy;

	/**
	 * Constructor.
	 *
	 * @param array                         $context Runtime context.
	 * @param SettingsCapabilityPolicy|null $policy Settings capability policy.
	 */
	public function __construct( array $context = array(), SettingsCapabilityPolicy $policy = null ) {
		$this->context = $context;
		$this->policy  = null === $policy ? SettingsCapabilityPolicy::factory( null, $context ) : $policy;
	}

	/**
	 * Create a validator instance.
	 *
	 * @param array                         $context Runtime context.
	 * @param SettingsCapabilityPolicy|null $policy Settings capability policy.
	 *
	 * @return SettingsValidator
	 */
	public static function factory( array $context = array(), SettingsCapabilityPolicy $policy = null ) {
		return new self( $context, $policy );
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

		if ( ! $this->policy->can_edit( $key ) && $this->has_value( $value, $field ) ) {
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

		foreach ( $field['dependencies'] as $dependency ) {
			if ( empty( $settings[ $dependency ] ) && $this->has_custom_value( $value, $field ) ) {
				$issues[] = $this->issue(
					$key,
					self::SEVERITY_WARNING,
					'inactive_dependency',
					'This setting has a value but its parent setting is disabled.'
				);
			}
		}

		return $issues;
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
	 * Determine whether a field differs from its default with a meaningful value.
	 *
	 * @param mixed $value Setting value.
	 * @param array $field Schema field.
	 *
	 * @return bool
	 */
	private function has_custom_value( $value, array $field ) {
		if ( $value === $field['default'] ) {
			return false;
		}

		return $this->has_value( $value, $field );
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
