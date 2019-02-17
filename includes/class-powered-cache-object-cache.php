<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Powered_Cache_Object_Cache {

	/**
	 * Return an instance of the current class
	 *
	 * @since 1.0
	 * @return Powered_Cache_Object_Cache
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
	 *
	 * @since 1.0
	 */
	public function setup() {
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		add_action( 'admin_post_powered_cache_purge_object_cache', array( $this, 'purge_object_cache' ) );

		add_action( 'added_option', array( $this, 'maybe_clear_alloptions_cache' ) );
		add_action( 'updated_option', array( $this, 'maybe_clear_alloptions_cache' ) );
		add_action( 'deleted_option', array( $this, 'maybe_clear_alloptions_cache' ) );
	}


	/**
	 * Add purge button on admin bar
	 *
	 * @since 1.0
	 *
	 * @param $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'     => 'object-cache-purge',
			'title'  => __( 'Purge Object Cache', 'powered-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_object_cache' ), 'powered_cache_purge_object_cache' ),
			'parent' => 'powered-cache',
		) );
	}


	/**
	 * Purge object cache
	 *
	 * @since 1.0
	 */
	public function purge_object_cache() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_object_cache' ) ) {
			wp_nonce_ays( '' );
		}

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			Powered_Cache_Admin_Helper::set_flash_message( __( "You don't have permission to perform this action!", 'powered-cache' ) ,'error' );
			wp_safe_redirect( wp_get_referer() );
			die();
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		Powered_Cache_Admin_Helper::set_flash_message( __( 'Object cache deleted successfully!', 'powered-cache' ) );

		wp_safe_redirect( wp_get_referer() );
		die();
	}


	/**
	 * Fix a race condition in alloptions caching
	 *
	 * @see https://github.com/skopco/powered-cache/issues/47
	 * @see https://core.trac.wordpress.org/ticket/31245
	 *
	 *
	 * Ported from https://core.trac.wordpress.org/ticket/31245#comment:57
	 * @since 1.2.4
	 */
	public function maybe_clear_alloptions_cache( $option ) {
		if ( ! wp_installing() ) {
			$alloptions = wp_load_alloptions(); //alloptions should be cached at this point

			if ( isset( $alloptions[ $option ] ) ) { //only if option is among alloptions
				wp_cache_delete( 'alloptions', 'options' );
			}
		}
	}

}