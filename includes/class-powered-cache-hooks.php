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

		add_action( 'admin_bar_menu', array( $this, 'purge_all_admin_bar_menu' ) );
		add_action( 'admin_post_powered_cache_purge_all_cache', array( $this, 'purge_all_cache' ) );

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
	 * Adds `Purge All Cache` menu bar item
	 *
	 * @param $wp_admin_bar
	 *
	 * @since 1.1
	 */
	public function purge_all_admin_bar_menu( $wp_admin_bar ) {
		// Only available for the network admins on multisite.
		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'     => 'all-cache-purge',
			'title'  => __( 'Purge All Cache', 'powered-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_all_cache' ), 'powered_cache_purge_all_cache' ),
			'parent' => 'powered-cache',
		) );
	}


	/**
	 * Purges all cache related things
	 *
	 * @since 1.1
	 */
	public function purge_all_cache() {

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_all_cache' ) ) {
			wp_nonce_ays( '' );
		}

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			Powered_Cache_Admin_Helper::set_flash_message( __( "You don't have permission to perform this action!", 'powered-cache' ), 'error' );
			wp_safe_redirect( wp_get_referer() );
			die();
		}

		powered_cache_flush();// cleans object cache + page cache dir

		// extensions should use this action
		do_action( 'powered_cache_purge_all_cache' );

		$msg = __( 'Cache flushed successfully', 'powered-cache' );
		Powered_Cache_Admin_Helper::set_flash_message( $msg );

		wp_safe_redirect( wp_get_referer() );
		die();
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