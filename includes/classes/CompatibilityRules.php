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
	 * Constructor.
	 *
	 * @param string|null $file Registry file path.
	 */
	public function __construct( $file = null ) {
		$this->file = null === $file ? self::default_file() : (string) $file;
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

		if ( empty( $registry['rules'][ $bucket ] ) || ! is_array( $registry['rules'][ $bucket ] ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', $registry['rules'][ $bucket ] ) ) );
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

		if ( ! is_array( $registry ) || self::FORMAT !== ( isset( $registry['format'] ) ? $registry['format'] : '' ) ) {
			return $this->registry;
		}

		$this->registry = $registry;

		return $this->registry;
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
