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
}
