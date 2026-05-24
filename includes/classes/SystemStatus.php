<?php
/**
 * System status report.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Builds admin-facing runtime checks for the settings app.
 *
 * @since 4.0.0
 */
class SystemStatus {

	const STATUS_GOOD    = 'good';
	const STATUS_WARNING = 'warning';
	const STATUS_INFO    = 'info';
	const STATUS_IDLE    = 'idle';

	/**
	 * Settings payload.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Runtime context.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param array $settings Settings payload.
	 * @param array $context Runtime context.
	 */
	public function __construct( array $settings, array $context = array() ) {
		$this->settings = $settings;
		$this->context  = $context;
	}

	/**
	 * Create a report instance.
	 *
	 * @param array $settings Settings payload.
	 * @param array $context Runtime context.
	 *
	 * @return SystemStatus
	 */
	public static function factory( array $settings, array $context = array() ) {
		return new self( $settings, $context );
	}

	/**
	 * Return a normalized status report.
	 *
	 * @return array
	 */
	public function report() {
		$checks = array(
			$this->page_cache_check(),
			$this->managed_host_cache_check(),
			$this->cache_storage_check(),
			$this->object_cache_check(),
			$this->action_scheduler_check(),
			$this->cron_check(),
			$this->rest_api_check(),
		);

		/**
		 * Filter settings system status checks.
		 *
		 * @hook powered_cache_system_status_checks
		 *
		 * @param {array} $checks Runtime status checks.
		 * @param {array} $settings Settings payload.
		 * @param {array} $context Runtime context.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$checks = apply_filters( 'powered_cache_system_status_checks', $checks, $this->settings, $this->context );
		$checks = array_values( array_filter( array_map( array( $this, 'normalize_check' ), is_array( $checks ) ? $checks : array() ) ) );

		return array(
			'status'  => $this->summary_status( $checks ),
			'counts'  => $this->counts( $checks ),
			'checks'  => $checks,
			'updated' => time(),
		);
	}

	/**
	 * Return the page cache readiness check.
	 *
	 * @return array
	 */
	private function page_cache_check() {
		if ( empty( $this->settings['enable_page_cache'] ) ) {
			return $this->check(
				'page_cache',
				self::STATUS_IDLE,
				'Page cache',
				'Page cache is disabled.',
				'Enable page cache when the site is ready to serve cached HTML.',
				'cache'
			);
		}

		if ( $this->context_flag_is( 'wp_cache_enabled', false ) ) {
			return $this->check(
				'page_cache',
				self::STATUS_WARNING,
				'Page cache',
				'WP_CACHE is not enabled.',
				'Add define( "WP_CACHE", true ) to wp-config.php, then save settings to refresh the drop-in.',
				'cache'
			);
		}

		if ( $this->context_flag_is( 'page_cache_loaded', false ) || $this->context_flag_is( 'page_cache_has_problem', true ) ) {
			return $this->check(
				'page_cache',
				self::STATUS_WARNING,
				'Page cache',
				'The page cache drop-in is not active.',
				'Save settings to recreate the configuration files, then verify advanced-cache.php is writable.',
				'cache'
			);
		}

		return $this->check(
			'page_cache',
			self::STATUS_GOOD,
			'Page cache',
			'Cached HTML delivery is ready.',
			'Anonymous visits can be served from the page cache.',
			'cache'
		);
	}

	/**
	 * Return managed hosting cache guidance.
	 *
	 * @return array
	 */
	private function managed_host_cache_check() {
		if ( empty( $this->settings['enable_page_cache'] ) ) {
			return array();
		}

		$hosts = array_intersect(
			$this->active_environments(),
			array(
				'host:kinsta',
				'host:wp-engine',
				'host:pantheon',
			)
		);

		if ( empty( $hosts ) ) {
			return array();
		}

		$labels = array(
			'host:kinsta'    => 'Kinsta',
			'host:wp-engine' => 'WP Engine',
			'host:pantheon'  => 'Pantheon',
		);
		$names  = array();

		foreach ( $hosts as $host ) {
			if ( isset( $labels[ $host ] ) ) {
				$names[] = $labels[ $host ];
			}
		}

		return $this->check(
			'managed_host_cache',
			self::STATUS_INFO,
			'Managed host cache',
			'Hosting cache layer detected.',
			sprintf( 'Purge %s cache when testing page cache changes so stale platform responses do not hide the result.', implode( ', ', $names ) ),
			'cache'
		);
	}

	/**
	 * Return the cache storage readiness check.
	 *
	 * @return array
	 */
	private function cache_storage_check() {
		$cache_dir = function_exists( '\PoweredCache\Utils\get_cache_dir' ) ? \PoweredCache\Utils\get_cache_dir() : '';
		$writable  = $this->path_is_writable( $cache_dir );

		if ( ! $writable ) {
			return $this->check(
				'cache_storage',
				self::STATUS_WARNING,
				'Cache storage',
				'Cache directory is not writable.',
				'Make sure wp-content/cache can be created and written by PHP.',
				'misc',
				$cache_dir
			);
		}

		return $this->check(
			'cache_storage',
			self::STATUS_GOOD,
			'Cache storage',
			'Cache directory is writable.',
			'Powered Cache can store generated page and optimization files.',
			'misc',
			$cache_dir
		);
	}

	/**
	 * Return the object cache readiness check.
	 *
	 * @return array
	 */
	private function object_cache_check() {
		$backend = isset( $this->settings['object_cache'] ) ? (string) $this->settings['object_cache'] : 'off';

		if ( '' === $backend || 'off' === $backend ) {
			return $this->check(
				'object_cache',
				self::STATUS_IDLE,
				'Object cache',
				'Persistent object cache is off.',
				'Only supported PHP drivers are shown when this server can use them.',
				'cache'
			);
		}

		$available = isset( $this->context['object_cache_backends'] ) && is_array( $this->context['object_cache_backends'] ) ? $this->context['object_cache_backends'] : array();

		if ( ! in_array( $backend, $available, true ) ) {
			return $this->check(
				'object_cache',
				self::STATUS_WARNING,
				'Object cache',
				'Selected object cache driver is not available.',
				'Install the matching PHP extension or switch the backend off.',
				'cache'
			);
		}

		if ( $this->context_flag_is( 'object_cache_dropin_exists', false ) || $this->context_flag_is( 'object_cache_has_problem', true ) ) {
			return $this->check(
				'object_cache',
				self::STATUS_WARNING,
				'Object cache',
				'Object cache drop-in needs attention.',
				'Save settings to recreate object-cache.php, then verify the file is writable.',
				'cache'
			);
		}

		return $this->check(
			'object_cache',
			self::STATUS_GOOD,
			'Object cache',
			ucfirst( $backend ) . ' object cache is configured.',
			'Persistent object caching can be used for dynamic WordPress data.',
			'cache'
		);
	}

	/**
	 * Return the async scheduler readiness check.
	 *
	 * @return array
	 */
	private function action_scheduler_check() {
		$ready = isset( $this->context['action_scheduler_ready'] )
			? (bool) $this->context['action_scheduler_ready']
			: (
				function_exists( 'as_enqueue_async_action' )
				&& class_exists( '\ActionScheduler' )
				&& is_callable( array( '\ActionScheduler', 'is_initialized' ) )
				&& \ActionScheduler::is_initialized( __METHOD__ )
			);

		if ( ! $ready ) {
			return $this->check(
				'action_scheduler',
				self::STATUS_WARNING,
				'Background jobs',
				'Action Scheduler is not ready yet.',
				'Background cache, preload, and optimization queues may wait until Action Scheduler initializes.',
				'misc'
			);
		}

		return $this->check(
			'action_scheduler',
			self::STATUS_GOOD,
			'Background jobs',
			'Action Scheduler is ready.',
			'Async cache and optimization jobs can be queued reliably.',
			'misc'
		);
	}

	/**
	 * Return the cron readiness check.
	 *
	 * @return array
	 */
	private function cron_check() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return $this->check(
				'wp_cron',
				self::STATUS_INFO,
				'WP-Cron',
				'Built-in WP-Cron is disabled.',
				'This is fine when a real server cron runs wp-cron.php regularly.',
				'misc'
			);
		}

		return $this->check(
			'wp_cron',
			self::STATUS_GOOD,
			'WP-Cron',
			'WP-Cron is available.',
			'Scheduled maintenance and background queues have a default runner.',
			'misc'
		);
	}

	/**
	 * Return the REST API readiness check.
	 *
	 * @return array
	 */
	private function rest_api_check() {
		if ( ! function_exists( 'rest_url' ) ) {
			return $this->check(
				'rest_api',
				self::STATUS_WARNING,
				'REST API',
				'REST API helpers are unavailable.',
				'Settings, status refreshes, and service actions require WordPress REST API support.',
				'misc'
			);
		}

		return $this->check(
			'rest_api',
			self::STATUS_GOOD,
			'REST API',
			'REST API is available.',
			'The settings app can save changes and refresh runtime status without page reloads.',
			'misc'
		);
	}

	/**
	 * Build one check.
	 *
	 * @param string $code Check code.
	 * @param string $status Check status.
	 * @param string $label Check label.
	 * @param string $message Short message.
	 * @param string $description Longer description.
	 * @param string $section Settings section key.
	 * @param string $detail Optional detail.
	 *
	 * @return array
	 */
	private function check( $code, $status, $label, $message, $description, $section = '', $detail = '' ) {
		return array(
			'code'        => $code,
			'status'      => $status,
			'label'       => $label,
			'message'     => $message,
			'description' => $description,
			'section'     => $section,
			'detail'      => $detail,
		);
	}

	/**
	 * Normalize one check for REST output.
	 *
	 * @param mixed $check Check payload.
	 *
	 * @return array
	 */
	private function normalize_check( $check ) {
		if ( ! is_array( $check ) || empty( $check['code'] ) ) {
			return array();
		}

		$status = isset( $check['status'] ) ? (string) $check['status'] : self::STATUS_INFO;

		if ( ! in_array( $status, array( self::STATUS_GOOD, self::STATUS_WARNING, self::STATUS_INFO, self::STATUS_IDLE ), true ) ) {
			$status = self::STATUS_INFO;
		}

		return array(
			'code'        => $this->sanitize_key( $check['code'] ),
			'status'      => $status,
			'label'       => isset( $check['label'] ) ? $this->sanitize_text( $check['label'] ) : '',
			'message'     => isset( $check['message'] ) ? $this->sanitize_text( $check['message'] ) : '',
			'description' => isset( $check['description'] ) ? $this->sanitize_text( $check['description'] ) : '',
			'section'     => isset( $check['section'] ) ? $this->sanitize_key( $check['section'] ) : '',
			'detail'      => isset( $check['detail'] ) ? $this->sanitize_text( $check['detail'] ) : '',
		);
	}

	/**
	 * Return summary counts.
	 *
	 * @param array $checks Checks.
	 *
	 * @return array
	 */
	private function counts( array $checks ) {
		$counts = array(
			self::STATUS_GOOD    => 0,
			self::STATUS_WARNING => 0,
			self::STATUS_INFO    => 0,
			self::STATUS_IDLE    => 0,
		);

		foreach ( $checks as $check ) {
			if ( isset( $counts[ $check['status'] ] ) ) {
				$counts[ $check['status'] ]++;
			}
		}

		return $counts;
	}

	/**
	 * Return summary status.
	 *
	 * @param array $checks Checks.
	 *
	 * @return string
	 */
	private function summary_status( array $checks ) {
		$counts = $this->counts( $checks );

		if ( $counts[ self::STATUS_WARNING ] > 0 ) {
			return self::STATUS_WARNING;
		}

		if ( $counts[ self::STATUS_INFO ] > 0 ) {
			return self::STATUS_INFO;
		}

		return self::STATUS_GOOD;
	}

	/**
	 * Determine whether a known runtime context flag matches an expected value.
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
	 * Return active hosting/runtime environment identifiers.
	 *
	 * @return array
	 */
	private function active_environments() {
		if ( isset( $this->context['active_environments'] ) && is_array( $this->context['active_environments'] ) ) {
			return $this->normalize_list( $this->context['active_environments'] );
		}

		$environments = array();

		if ( defined( 'KINSTAMU_VERSION' ) ) {
			$environments[] = 'host:kinsta';
		}

		if ( defined( 'WPE_APIKEY' ) || defined( 'WPE_CLUSTER_ID' ) || defined( 'PWP_NAME' ) ) {
			$environments[] = 'host:wp-engine';
		}

		if ( defined( 'PANTHEON_ENVIRONMENT' ) ) {
			$environments[] = 'host:pantheon';
		}

		return $this->normalize_list( $environments );
	}

	/**
	 * Normalize a scalar list.
	 *
	 * @param array $values Values.
	 *
	 * @return array
	 */
	private function normalize_list( array $values ) {
		$normalized = array();

		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Determine whether a path or its parent can be written.
	 *
	 * @param string $path Path.
	 *
	 * @return bool
	 */
	private function path_is_writable( $path ) {
		$path = (string) $path;

		if ( '' === $path ) {
			return false;
		}

		if ( file_exists( $path ) ) {
			return is_writable( $path );
		}

		$parent = dirname( rtrim( $path, '/\\' ) );

		return $parent && file_exists( $parent ) && is_writable( $parent );
	}

	/**
	 * Sanitize a REST-safe key.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function sanitize_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Sanitize one line of admin text.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function sanitize_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( (string) $value ) );
		}

		return trim( strip_tags( (string) $value ) );
	}
}
