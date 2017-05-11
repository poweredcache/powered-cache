<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.1
 * Class Powered_Cache_Hooks
 */
class Powered_Cache_Hooks {

	/**
	 * Setup actions and filters
	 *
	 * @since 1.1
	 */
	public function setup() {
		if ( powered_cache_get_option( 'remove_query_string' ) ) {
			add_filter( 'script_loader_src', array( $this, 'remove_script_version' ), 15, 1 );
			add_filter( 'style_loader_src', array( $this, 'remove_script_version' ), 15, 1 );
		}

	}

	/**
	 * Removes query string from the url
	 *
	 * @param string $src resource url
	 *
	 * @since 1.1
	 * @return mixed
	 */
	public function remove_script_version( $src ) {
		$parts = explode( '?', $src );

		return $parts[0];
	}

	/**
	 * @since 1.1
	 * @return Powered_Cache_Hooks
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