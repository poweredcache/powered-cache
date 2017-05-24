<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Powered_Cache_Extensions {

	public $core_extension_dir;


	/**
	 * placeholder construct
	 *
	 * @since 1.0
	 */
	public function __construct() {}

	/**
	 * Setup $core_extension_dir
	 *
	 * @since 1.0
	 */
	private function setup() {
		$this->core_extension_dir = apply_filters( 'powered_cache_extension_dir', POWERED_CACHE_PLUGIN_DIR . 'extensions/' );
	}

	/**
	 * Get extension info
	 *
	 * @see   load_extentions() method gets extension path from here!
	 * @since 1.0
	 * @return mixed|void|array
	 */
	public function get_extentions() {
		global $powered_cache_fs;

		$extentions = array(
			'cloudflare'  => $this->core_extension_dir . 'cloudflare/cloudflare.php',
			'remote-cron' => $this->core_extension_dir . 'remote-cron/remote-cron.php',
			'lazy-load'   => $this->core_extension_dir . 'lazy-load/lazy-load.php',
			'preload'     => $this->core_extension_dir . 'preload/preload.php',
			'varnish'     => $this->core_extension_dir . 'varnish/varnish.php',
			'minifier'    => $this->core_extension_dir . 'minifier/minifier.php',
		);


		do_action_ref_array( 'powered_cache_register_extensions', array( $this->core_extension_dir, &$extentions ) );

		$extension_info = array();

		$default_headers = array(
			'Name'           => 'Extension Name',
			'ExtensionURI'   => 'Extension URI',
			'Version'        => 'Version',
			'Description'    => 'Description',
			'Author'         => 'Author',
			'AuthorURI'      => 'Author URI',
			'ExtensionImage' => 'Extension Image',
			'Premium'        => 'Premium',
		);

		foreach ( $extentions as $id => $extention ) {

			if ( $powered_cache_fs->exists( $extention ) ) {

				$header_data = get_file_data( $extention, $default_headers );
				if ( ! empty( $header_data['ExtensionImage'] ) && $powered_cache_fs->exists( plugin_dir_path( $extention ) . $header_data['ExtensionImage'] ) ) {
					$header_data['ExtensionImage'] = plugin_dir_url( $extention ) . $header_data['ExtensionImage'];
				}

				$extension_info[ $id ] = $header_data;
				$extension_info[ $id ]['path'] = $extention;
			}
		}


		return apply_filters( 'powered_cache_extension_info', $extension_info );
	}


	/**
	 * Load activated extensions
	 *
	 * @since 1.0
	 */
	public function load_extentions() {
		global $powered_cache_fs;

		do_action( 'powered_cache_before_extension_load' );

		$activated_extensions = powered_cache_get_option('active_extensions');

		if ( is_array( $activated_extensions ) ) {
			$extensions = $this->get_extentions();

			foreach ( $activated_extensions as $extension ) {
				if ( isset( $extensions[ $extension ] ) && $powered_cache_fs->exists( $extensions[ $extension ]['path'] ) ) {
					include_once $extensions[ $extension ]['path'];

					// fire after extension loaded
					do_action( 'powered_cache_extension_' . $extension . '_loaded', $extension );
				}
			}

		}

		// get active extensions and load
		do_action( 'powered_cache_extensions_loaded' );
	}

	/**
	 * Check powered cache's extension active
	 *
	 * @since 1.0
	 *
	 * @param string $extension_id
	 *
	 * @return bool
	 */
	public function is_active( $extension_id ) {
		$options = get_option( 'powered_cache_settings' );
		if ( is_array( $options ) && isset( $options['active_extensions'] )
		     && is_array( $options['active_extensions'] )
		     && in_array( $extension_id, $options['active_extensions'] )
		) {
			return true;
		}

		return false;
	}


	/**
	 * Activates extension
	 *
	 * @param $extension_id
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function activate( $extension_id ) {

		if ( $this->is_active( $extension_id ) ) {
			// bail if already active
			return false;
		}

		$old_options = $new_options = powered_cache_get_settings();

		if ( isset( $old_options['active_extensions'] ) && is_array( $old_options['active_extensions'] ) ) {
			$new_options['active_extensions'] = array_merge( $old_options['active_extensions'], array( $extension_id ) );
		} else {
			$new_options['active_extensions'][] = $extension_id;
		}

		do_action( 'powered_cache_extension_activate_' . $extension_id );
		powered_cache_save_settings( $old_options, $new_options );

		return true;
	}

	/**
	 * Deactivate given extension
	 *
	 * @param $extension_id
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function deactivate( $extension_id ) {

		if ( ! $this->is_active( $extension_id ) ) {
			// bail if already deactive
			return false;
		}

		$old_options = $new_options = powered_cache_get_settings();

		$key = array_search( $extension_id, $old_options['active_extensions'] );

		if ( false !== $key ) {
			unset( $new_options['active_extensions'][ $key ] );
		}


		do_action( 'powered_cache_extension_deactivate_' . $extension_id );
		powered_cache_save_settings( $old_options, $new_options );

		return true;
	}


	/**
	 * Return an instance of the current class
	 *
	 * @since 1.0
	 * @return Powered_Cache_Extensions
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}