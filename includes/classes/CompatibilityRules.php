<?php
/**
 * Compatibility rules registry.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Loads versioned compatibility rules for optimizer safeguards.
 *
 * @since 4.0.0
 */
class CompatibilityRules {

	const FORMAT         = 'powered-cache-compatibility-rules';
	const FORMAT_VERSION = '1.0';

	/**
	 * Registry file path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Decoded registry payload.
	 *
	 * @var array|null
	 */
	private $registry;

	/**
	 * Active plugin basenames.
	 *
	 * @var array|null
	 */
	private $active_plugins;

	/**
	 * Constructor.
	 *
	 * @param string|null $file Registry file path.
	 */
	public function __construct( $file = null ) {
		$file = null === $file ? self::default_file() : (string) $file;

		/**
		 * Filter the compatibility rules registry file.
		 *
		 * @hook powered_cache_compatibility_rules_file
		 *
		 * @param {string} $file Registry file path.
		 *
		 * @return {string} New value.
		 *
		 * @since 4.0.0
		 */
		$this->file = (string) apply_filters( 'powered_cache_compatibility_rules_file', $file );
	}

	/**
	 * Create a registry instance.
	 *
	 * @param string|null $file Registry file path.
	 *
	 * @return CompatibilityRules
	 */
	public static function factory( $file = null ) {
		return new self( $file );
	}

	/**
	 * Register runtime filters.
	 */
	public function setup() {
		add_filter( 'powered_cache_defer_exclusions', array( $this, 'add_defer_exclusions' ) );
		add_filter( 'powered_cache_delay_exclusions', array( $this, 'add_delay_exclusions' ) );
		add_filter( 'powered_cache_fo_excluded_css_files', array( $this, 'add_file_optimizer_css_exclusions' ) );
		add_filter( 'powered_cache_fo_excluded_js_files', array( $this, 'add_file_optimizer_js_exclusions' ) );
		add_filter( 'powered_cache_lazy_load_exclusions', array( $this, 'add_lazy_load_exclusions' ) );
	}

	/**
	 * Add default defer exclusions.
	 *
	 * @param array $exclusions Current exclusions.
	 *
	 * @return array
	 */
	public function add_defer_exclusions( array $exclusions ) {
		return $this->append_unique( $exclusions, $this->rules( 'defer_exclusions' ) );
	}

	/**
	 * Add default delay exclusions.
	 *
	 * @param array $exclusions Current exclusions.
	 *
	 * @return array
	 */
	public function add_delay_exclusions( array $exclusions ) {
		return $this->append_unique( $exclusions, $this->rules( 'delay_exclusions' ) );
	}

	/**
	 * Add default CSS optimizer exclusions.
	 *
	 * @param array $exclusions Current exclusions.
	 *
	 * @return array
	 */
	public function add_file_optimizer_css_exclusions( array $exclusions ) {
		return $this->append_unique( $exclusions, $this->rules( 'file_optimizer_css_exclusions' ) );
	}

	/**
	 * Add default JS optimizer exclusions.
	 *
	 * @param array $exclusions Current exclusions.
	 *
	 * @return array
	 */
	public function add_file_optimizer_js_exclusions( array $exclusions ) {
		return $this->append_unique( $exclusions, $this->rules( 'file_optimizer_js_exclusions' ) );
	}

	/**
	 * Add default lazy-load exclusions.
	 *
	 * @param array $exclusions Current exclusions.
	 *
	 * @return array
	 */
	public function add_lazy_load_exclusions( array $exclusions ) {
		return $this->append_unique( $exclusions, $this->rules( 'lazy_load_exclusions' ) );
	}

	/**
	 * Return rules from one registry bucket.
	 *
	 * @param string $bucket Rule bucket.
	 *
	 * @return array
	 */
	public function rules( $bucket ) {
		$registry = $this->registry();

		$rules = array();

		if ( ! empty( $registry['rules'][ $bucket ] ) && is_array( $registry['rules'][ $bucket ] ) ) {
			$rules = $this->normalize_rules( $registry['rules'][ $bucket ] );
		}

		return $this->append_unique( $rules, $this->conditional_rules( $registry, $bucket ) );
	}

	/**
	 * Return settings issues from the compatibility registry.
	 *
	 * @param array $settings Current settings.
	 *
	 * @return array
	 */
	public function settings_issues( array $settings ) {
		$registry = $this->registry();
		$issues   = array();

		if ( ! empty( $registry['settings_issues'] ) && is_array( $registry['settings_issues'] ) ) {
			$issues = $this->setting_issues_from_items( $registry['settings_issues'], $settings );
		}

		return $this->append_unique_issues( $issues, $this->conditional_setting_issues( $registry, $settings ) );
	}

	/**
	 * Return the decoded registry payload.
	 *
	 * @return array
	 */
	public function registry() {
		if ( null !== $this->registry ) {
			return $this->registry;
		}

		$this->registry = array();

		if ( ! is_readable( $this->file ) ) {
			return $this->registry;
		}

		$contents = file_get_contents( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$registry = json_decode( $contents, true );

		if ( ! $this->is_valid_registry( $registry ) ) {
			return $this->registry;
		}

		/**
		 * Filter the decoded compatibility rules registry.
		 *
		 * Use this to replace or extend the bundled registry with a locally cached
		 * update source while keeping the runtime fail-closed.
		 *
		 * @hook powered_cache_compatibility_rules_registry
		 *
		 * @param {array}  $registry Registry payload.
		 * @param {string} $file     Registry file path.
		 *
		 * @return {array} New value.
		 *
		 * @since 4.0.0
		 */
		$registry = apply_filters( 'powered_cache_compatibility_rules_registry', $registry, $this->file );

		if ( ! $this->is_valid_registry( $registry ) ) {
			return $this->registry;
		}

		$this->registry = $registry;

		return $this->registry;
	}

	/**
	 * Check whether a registry payload can be used.
	 *
	 * @param mixed $registry Registry payload.
	 *
	 * @return bool
	 */
	private function is_valid_registry( $registry ) {
		return is_array( $registry )
			&& self::FORMAT === ( isset( $registry['format'] ) ? $registry['format'] : '' )
			&& self::FORMAT_VERSION === ( isset( $registry['format_version'] ) ? $registry['format_version'] : '' )
			&& isset( $registry['rules'] )
			&& is_array( $registry['rules'] );
	}

	/**
	 * Return normalized unique rules from a registry bucket.
	 *
	 * @param array $rules Rule values.
	 *
	 * @return array
	 */
	private function normalize_rules( array $rules ) {
		$normalized = array();

		foreach ( $rules as $rule ) {
			if ( ! is_scalar( $rule ) ) {
				continue;
			}

			$rule = trim( (string) $rule );

			if ( '' === $rule || in_array( $rule, $normalized, true ) ) {
				continue;
			}

			$normalized[] = $rule;
		}

		return $normalized;
	}

	/**
	 * Return conditional rules that match the current environment.
	 *
	 * @param array  $registry Registry payload.
	 * @param string $bucket   Rule bucket.
	 *
	 * @return array
	 */
	private function conditional_rules( array $registry, $bucket ) {
		if ( empty( $registry['conditional_rules']['plugins'] ) || ! is_array( $registry['conditional_rules']['plugins'] ) ) {
			return array();
		}

		$active_plugins = $this->active_plugins();
		$rules          = array();

		foreach ( $registry['conditional_rules']['plugins'] as $plugin => $plugin_rules ) {
			if ( ! is_string( $plugin ) || ! in_array( $plugin, $active_plugins, true ) || empty( $plugin_rules[ $bucket ] ) || ! is_array( $plugin_rules[ $bucket ] ) ) {
				continue;
			}

			$rules = $this->append_unique( $rules, $this->normalize_rules( $plugin_rules[ $bucket ] ) );
		}

		return $rules;
	}

	/**
	 * Return conditional setting issues that match the current environment.
	 *
	 * @param array $registry Registry payload.
	 * @param array $settings Current settings.
	 *
	 * @return array
	 */
	private function conditional_setting_issues( array $registry, array $settings ) {
		if ( empty( $registry['conditional_rules']['plugins'] ) || ! is_array( $registry['conditional_rules']['plugins'] ) ) {
			return array();
		}

		$active_plugins = $this->active_plugins();
		$issues         = array();

		foreach ( $registry['conditional_rules']['plugins'] as $plugin => $plugin_rules ) {
			if ( ! is_string( $plugin ) || ! in_array( $plugin, $active_plugins, true ) || empty( $plugin_rules['settings_issues'] ) || ! is_array( $plugin_rules['settings_issues'] ) ) {
				continue;
			}

			$issues = $this->append_unique_issues( $issues, $this->setting_issues_from_items( $plugin_rules['settings_issues'], $settings ) );
		}

		return $issues;
	}

	/**
	 * Return active plugin basenames.
	 *
	 * @return array
	 */
	private function active_plugins() {
		if ( null !== $this->active_plugins ) {
			return $this->active_plugins;
		}

		$plugins = array();

		if ( function_exists( 'get_option' ) ) {
			$option_plugins = get_option( 'active_plugins', array() );

			if ( is_array( $option_plugins ) ) {
				$plugins = array_merge( $plugins, $option_plugins );
			}
		}

		if ( function_exists( 'get_site_option' ) ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			if ( is_array( $network_plugins ) ) {
				$plugins = array_merge( $plugins, array_keys( $network_plugins ) );
			}
		}

		$plugins = $this->normalize_rules( $plugins );

		/**
		 * Filter active plugin basenames used by compatibility rules.
		 *
		 * @hook powered_cache_compatibility_rules_active_plugins
		 *
		 * @param {array} $plugins Active plugin basenames.
		 *
		 * @return {array} New value.
		 *
		 * @since 4.0.0
		 */
		$plugins = apply_filters( 'powered_cache_compatibility_rules_active_plugins', $plugins );

		$this->active_plugins = is_array( $plugins ) ? $this->normalize_rules( $plugins ) : array();

		return $this->active_plugins;
	}

	/**
	 * Return normalized setting issues from registry items.
	 *
	 * @param array $items    Registry issue items.
	 * @param array $settings Current settings.
	 *
	 * @return array
	 */
	private function setting_issues_from_items( array $items, array $settings ) {
		$issues = array();

		foreach ( $items as $item ) {
			$issue = $this->normalize_setting_issue( $item, $settings );

			if ( empty( $issue ) ) {
				continue;
			}

			$issues[] = $issue;
		}

		return $issues;
	}

	/**
	 * Return a normalized setting issue when it matches the current settings.
	 *
	 * @param mixed $item     Registry issue item.
	 * @param array $settings Current settings.
	 *
	 * @return array
	 */
	private function normalize_setting_issue( $item, array $settings ) {
		if ( ! is_array( $item ) || ! $this->setting_issue_matches( $item, $settings ) ) {
			return array();
		}

		$key      = isset( $item['key'] ) && is_scalar( $item['key'] ) ? trim( (string) $item['key'] ) : '';
		$severity = isset( $item['severity'] ) && is_scalar( $item['severity'] ) ? trim( (string) $item['severity'] ) : '';
		$code     = isset( $item['code'] ) && is_scalar( $item['code'] ) ? trim( (string) $item['code'] ) : '';
		$message  = isset( $item['message'] ) && is_scalar( $item['message'] ) ? trim( (string) $item['message'] ) : '';

		if ( '' === $key || '' === $code || '' === $message || ! in_array( $severity, array( 'error', 'warning', 'info' ), true ) ) {
			return array();
		}

		return array(
			'key'      => $key,
			'severity' => $severity,
			'code'     => $code,
			'message'  => $message,
		);
	}

	/**
	 * Determine whether a registry issue applies to the current settings.
	 *
	 * @param array $item     Registry issue item.
	 * @param array $settings Current settings.
	 *
	 * @return bool
	 */
	private function setting_issue_matches( array $item, array $settings ) {
		if ( empty( $item['when'] ) ) {
			return true;
		}

		if ( ! is_array( $item['when'] ) || empty( $item['when']['setting'] ) || ! is_scalar( $item['when']['setting'] ) ) {
			return false;
		}

		$setting = (string) $item['when']['setting'];

		if ( ! array_key_exists( $setting, $settings ) ) {
			return false;
		}

		if ( array_key_exists( 'value', $item['when'] ) ) {
			if ( is_bool( $item['when']['value'] ) ) {
				return (bool) $settings[ $setting ] === $item['when']['value'];
			}

			return $settings[ $setting ] === $item['when']['value'];
		}

		return ! empty( $settings[ $setting ] );
	}

	/**
	 * Append values while preserving order and avoiding duplicates.
	 *
	 * @param array $current Current values.
	 * @param array $additions Additional values.
	 *
	 * @return array
	 */
	private function append_unique( array $current, array $additions ) {
		foreach ( $additions as $addition ) {
			if ( in_array( $addition, $current, true ) ) {
				continue;
			}

			$current[] = $addition;
		}

		return $current;
	}

	/**
	 * Append validation issues while preserving order and avoiding duplicates.
	 *
	 * @param array $current   Current issues.
	 * @param array $additions Additional issues.
	 *
	 * @return array
	 */
	private function append_unique_issues( array $current, array $additions ) {
		$seen = array();

		foreach ( $current as $issue ) {
			if ( isset( $issue['key'], $issue['code'] ) ) {
				$seen[ $issue['key'] . ':' . $issue['code'] ] = true;
			}
		}

		foreach ( $additions as $issue ) {
			if ( ! isset( $issue['key'], $issue['code'] ) ) {
				continue;
			}

			$id = $issue['key'] . ':' . $issue['code'];

			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			$current[]   = $issue;
			$seen[ $id ] = true;
		}

		return $current;
	}

	/**
	 * Return default registry file path.
	 *
	 * @return string
	 */
	private static function default_file() {
		$path = defined( 'POWERED_CACHE_PATH' ) ? rtrim( POWERED_CACHE_PATH, '/\\' ) . '/data/compatibility-rules.json' : '';

		if ( $path && file_exists( $path ) ) {
			return $path;
		}

		return dirname( __DIR__, 2 ) . '/data/compatibility-rules.json';
	}
}
