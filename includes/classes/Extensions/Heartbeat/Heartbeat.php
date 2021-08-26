<?php
/**
 * Heartbeat Extension
 *
 * @package PoweredCache\Extensions
 */

namespace PoweredCache\Extensions\Heartbeat;

/**
 * Class Heartbeat
 */
class Heartbeat {

	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	private $settings;

	/**
	 * Holds location for condition
	 *
	 * @var string $current_location
	 */
	private $current_location;

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
	 * Setup routine
	 */
	public function setup() {
		if ( wp_doing_ajax() ) {
			return;
		}

		$this->settings = \PoweredCache\Utils\get_settings();
		$this->set_location();

		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_stop_heartbeat' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_stop_heartbeat' ] );
		add_action( 'heartbeat_settings', [ $this, 'maybe_change_frequency' ] );
	}

	/**
	 * Maybe stop heartbeat for particular location
	 */
	public function maybe_stop_heartbeat() {
		$settings_key = sprintf( 'heartbeat_%s_status', $this->current_location );

		if ( isset( $this->settings[ $settings_key ] ) && 'disable' === $this->settings[ $settings_key ] ) {
			wp_deregister_script( 'heartbeat' );

			return;
		}
	}

	/**
	 * Maybe modify heartbeat frequency
	 *
	 * @param array $settings heartbeat settings
	 *
	 * @return mixed
	 */
	public function maybe_change_frequency( $settings ) {
		$status_key   = sprintf( 'heartbeat_%s_status', $this->current_location );
		$interval_key = sprintf( 'heartbeat_%s_interval', $this->current_location );

		if ( ! isset( $this->settings[ $status_key ] ) ) {
			return $settings;
		}

		if ( 'modify' !== $this->settings[ $status_key ] ) {
			return $settings;
		}

		if ( ! empty( $this->settings[ $interval_key ] ) ) {
			$settings['interval'] = absint( $this->settings[ $interval_key ] );
		}

		return $settings;
	}

	/**
	 * Set current location
	 * Supported locations [editor,dashboard,frontend]
	 */
	public function set_location() {
		$editor_pages = [
			'/wp-admin/post-new.php',
			'/wp-admin/post.php',
		];

		if ( is_admin() && in_array( $_SERVER['REQUEST_URI'], $editor_pages, true ) ) {
			$this->current_location = 'editor';
		} elseif ( is_admin() ) {
			$this->current_location = 'dashboard';
		} else {
			$this->current_location = 'frontend';
		}
	}

}
