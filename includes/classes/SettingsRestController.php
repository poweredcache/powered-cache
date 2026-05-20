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
		global $is_apache;

		return SettingsManifest::build(
			array(
				'is_apache' => (bool) $is_apache,
			),
			false
		);
	}

	/**
	 * Return current settings for admin UI consumers.
	 *
	 * @return array
	 */
	public function get_settings() {
		global $is_apache;

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			array(
				'is_apache' => (bool) $is_apache,
			)
		);
		$settings   = $repository->all();

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsTransfer::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory(
				array(
					'is_apache' => (bool) $is_apache,
				)
			)->report( $settings ),
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
		global $is_apache;

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			array(
				'is_apache' => (bool) $is_apache,
			)
		);

		$old_settings = $repository->all();
		$changes      = SettingsTransfer::unpack( $this->get_request_payload( $request ) );
		$settings     = $this->prepare_update_settings( $changes, $old_settings );
		$save_service = SettingsSaveService::factory( $repository, POWERED_CACHE_IS_NETWORK );
		$settings     = $save_service->save( $settings, $old_settings );

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsTransfer::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory(
				array(
					'is_apache' => (bool) $is_apache,
				)
			)->report( $settings ),
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

		foreach ( SettingsTransfer::sensitive_keys() as $key ) {
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

		return SettingsCapabilityPolicy::factory()->enforce( $settings, $current );
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
}
