<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Powered_Cache_Advanced_Cache {

	/**
	 * Return an instance of the current class
	 *
	 * @since 1.0
	 * @return Powered_Cache_Advanced_Cache
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
		add_action( 'admin_post_powered_cache_purge_page_cache', array( $this, 'purge_page_cache' ) );

		add_action( 'pre_post_update', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'save_post', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'wp_trash_post', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'wp_set_comment_status', array( $this, 'purge_post_on_comment_status_change' ), 10, 2 );
		add_action( 'set_comment_cookies', array( $this, 'set_comment_cookie' ), 10, 2 );
		add_filter( 'powered_cache_post_related_urls', array( $this, 'powered_cache_post_related_urls' ) );
	}

	/**
	 * Add purge button on admin bar
	 * @since 1.0
	 * @param $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		$wp_admin_bar->add_menu( array(
			'id'     => 'advanced-cache-purge',
			'title'  => __( 'Purge Page Cache', 'powered-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache' ), 'powered_cache_purge_page_cache' ),
			'parent' => 'powered-cache',
		) );
	}


	/**
	 * Purge page cache directory
	 *
	 * @since 1.0
	 * @since 1.1 clean site dir instead of root page caching dir
	 */
	public function purge_page_cache() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_page_cache' ) ) {
			wp_nonce_ays( '' );
		}

		powered_cache_clean_site_cache_dir();
		Powered_Cache_Admin_Helper::set_flash_message( __( 'Page cache deleted successfully!', 'powered-cache' ) );

		wp_safe_redirect( wp_get_referer() );
		die();
	}


	/**
	 * Purge post related page cache
	 *
	 * @since 1.0
	 *
	 * @param $post_id
	 */
	public function purge_on_post_update( $post_id ) {
		$current_post_status = get_post_status( $post_id );

		$urls = array();

		if ( in_array( $current_post_status, array( "publish", "trash" ) ) ) {
			$urls = powered_cache_get_post_related_urls( $post_id );

			$urls = apply_filters( 'powered_cache_advanced_cache_purge_urls', $urls, $post_id );

			foreach ( $urls as $url ) {
				powered_cache_delete_page_cache( $url );
			}
		}

		do_action( 'powered_cache_advanced_cache_purge_post', $post_id, $urls );
	}

	/**
	 * Delete page cache when post update
	 *
	 * @since 1.0
	 *
	 * @param $comment_ID
	 * @param $comment_status
	 */
	public function purge_post_on_comment_status_change( $comment_ID, $comment_status ) {
		$comment = get_comment( $comment_ID );
		$post_id = $comment->comment_post_ID;
		powered_cache_delete_page_cache( get_permalink( $post_id ) );
	}

	/**
	 * leave a cookie when comment a post as usual and don't show cached page.
	 *
	 * @since 1.0
	 *
	 * @param $comment
	 * @param $user
	 */
	public function set_comment_cookie( $comment, $user ) {
		$post_id = $comment->comment_post_ID;
		setcookie( 'powered_cache_commented_posts[' . $post_id . ']', parse_url( get_permalink( $post_id ), PHP_URL_PATH ), ( time() + HOUR_IN_SECONDS * 24 * 30 ) );
	}


	/**
	 * Adds custom pages to post related urls
	 *
	 * @since 1.1
	 *
	 * @param array $urls
	 *
	 * @return array urls
	 */
	public function powered_cache_post_related_urls( $urls ) {
		$additional_pages = powered_cache_get_option( 'purge_additional_pages' );

		if ( empty( $additional_pages ) ) {
			return $urls;
		}

		// we only need relative path
		$additional_pages      = str_replace( site_url(), '', $additional_pages );
		$additional_page_paths = explode( PHP_EOL, $additional_pages );
		$additional_urls       = array();

		foreach ( $additional_page_paths as $page ) {
			$additional_urls[] = site_url( $page );
		}

		$urls = array_merge( $urls, $additional_urls );

		return $urls;
	}

}