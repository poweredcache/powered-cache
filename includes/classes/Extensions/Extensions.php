<?php
/**
 * Extension Loader
 *
 * @package PoweredCache\Extensions
 */

namespace PoweredCache\Extensions;

use PoweredCache\Extensions\Cloudflare;
use PoweredCache\Extensions\Heartbeat\Heartbeat;

/**
 * Class Extensions
 */
class Extensions {

	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	private $settings;

	/**
	 * Placeholder constructor
	 */
	public function __construct() {
	}


	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @return object
	 * @since  1.0
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks
	 */
	public function setup() {
		$this->settings = \PoweredCache\Utils\get_settings();
		add_action( 'plugins_loaded', [ $this, 'initialize_active_extensions' ] );
	}

	/**
	 * Init active extensions
	 */
	public function initialize_active_extensions() {
		if ( $this->is_load_cloudflare() ) {
			Cloudflare\Cloudflare::factory();
		}

		if ( $this->is_load_heartbeat() ) {
			Heartbeat::factory();
		}

	}

	/**
	 * Whether load or not load CF
	 *
	 * @return bool
	 */
	private function is_load_cloudflare() {
		if ( ! $this->settings['enable_cloudflare'] ) {
			return false;
		}

		if ( empty( $this->settings['cloudflare_zone'] ) ) {
			return false;
		}

		if (
			( ! empty( $this->settings['cloudflare_email'] ) && ! empty( $this->settings['cloudflare_email'] ) )
			|| ( ! empty( $this->settings['cloudflare_api_token'] ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Whether load or not load heartbeat
	 *
	 * @return bool
	 */
	private function is_load_heartbeat() {
		return (bool) $this->settings['enable_heartbeat'];
	}

}
