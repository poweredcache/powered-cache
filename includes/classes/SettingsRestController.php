<?php
/**
 * Settings REST controller.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Exposes settings schema payloads for admin UI consumers.
 *
 * @since 4.0.0
 */
class SettingsRestController {

	const REST_NAMESPACE = 'powered-cache/v1';
	const STATE_FORMAT   = 'powered-cache-settings-state';

	/**
	 * Return an instance of the current class.
	 *
	 * @return SettingsRestController
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks.
	 */
	public function setup() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register settings routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_read_manifest' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_read_manifest' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_manifest' ),
				'permission_callback' => array( $this, 'can_read_manifest' ),
			)
		);
	}

	/**
	 * Determine whether the current user can read the settings manifest.
	 *
	 * @return bool
	 */
	public function can_read_manifest() {
		return \PoweredCache\Utils\can_control_all_settings();
	}

	/**
	 * Return the settings manifest.
	 *
	 * @return array
	 */
	public function get_manifest() {
		return SettingsManifest::build( $this->settings_context(), false );
	}

	/**
	 * Return current settings for admin UI consumers.
	 *
	 * @return array
	 */
	public function get_settings() {
		$context = $this->settings_context();

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			$context
		);
		$settings   = $repository->all();

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsRepository::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory( $context )->report( $settings ),
		);
	}

	/**
	 * Update settings from REST payload.
	 *
	 * @param mixed $request REST request.
	 *
	 * @return array
	 */
	public function update_settings( $request ) {
		$context = $this->settings_context();

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			$context
		);

		$old_settings = $repository->all();
		$changes      = SettingsRepository::settings_from_payload( $this->get_request_payload( $request ) );
		$settings     = $this->prepare_update_settings( $changes, $old_settings );
		$save_service = SettingsSaveService::factory( $repository, POWERED_CACHE_IS_NETWORK );
		$settings     = $save_service->save( $settings, $old_settings );

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsRepository::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory( $context )->report( $settings ),
		);
	}

	/**
	 * Prepare partial update payload before storage.
	 *
	 * @param array $changes Changed settings.
	 * @param array $current Current settings.
	 *
	 * @return array
	 */
	public function prepare_update_settings( array $changes, array $current ) {
		$settings = array_merge( $current, $changes );

		foreach ( SettingsRepository::sensitive_keys() as $key ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				continue;
			}

			if ( '' === $changes[ $key ] ) {
				$settings[ $key ] = isset( $current[ $key ] ) ? $current[ $key ] : '';
				continue;
			}

			if ( null === $changes[ $key ] ) {
				$settings[ $key ] = '';
				continue;
			}

			if ( in_array( $key, array( 'cloudflare_api_key', 'cloudflare_api_token' ), true ) ) {
				$settings[ $key ] = ( new Encryption() )->encrypt( $changes[ $key ] );
			}
		}

		return SettingsSchema::enforce_editable( $settings, $current );
	}

	/**
	 * Read request payload.
	 *
	 * @param mixed $request REST request.
	 *
	 * @return array
	 */
	private function get_request_payload( $request ) {
		if ( is_array( $request ) ) {
			return $request;
		}

		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$params = $request->get_json_params();

			if ( is_array( $params ) ) {
				return $params;
			}
		}

		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			$params = $request->get_params();

			return is_array( $params ) ? $params : array();
		}

		return array();
	}

	/**
	 * Build runtime context for schema defaults and settings validation.
	 *
	 * @return array
	 */
	private function settings_context() {
		global $is_apache;

		$context = array(
			'is_apache'                => (bool) $is_apache,
			'wp_cache_enabled'         => defined( 'WP_CACHE' ) && true === WP_CACHE,
			'page_cache_loaded'        => defined( 'POWERED_CACHE_PAGE_CACHING' ) && true === POWERED_CACHE_PAGE_CACHING,
			'page_cache_has_problem'   => defined( 'POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM' ) && POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM,
			'object_cache_has_problem' => defined( 'POWERED_OBJECT_CACHE_HAS_PROBLEM' ) && POWERED_OBJECT_CACHE_HAS_PROBLEM,
		);

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$context['object_cache_dropin_exists'] = file_exists( rtrim( WP_CONTENT_DIR, '/\\' ) . '/object-cache.php' );
		}

		if ( $context['is_apache'] && function_exists( 'get_home_path' ) ) {
			$htaccess_file                = rtrim( get_home_path(), '/\\' ) . '/.htaccess';
			$context['htaccess_exists']   = file_exists( $htaccess_file );
			$context['htaccess_writable'] = is_writable( $htaccess_file );
		}

		/**
		 * Filter settings validation runtime context.
		 *
		 * @hook powered_cache_settings_validation_context
		 *
		 * @param {array} $context Runtime context.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$context = apply_filters( 'powered_cache_settings_validation_context', $context );

		return is_array( $context ) ? $context : array();
	}
}
