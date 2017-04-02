<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Advanced_Cache {

	/**
	 * Return an instance of the current class
	 *
	 * @since 1.0
	 * @return PC_Advanced_Cache
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
		add_action( 'admin_post_pc_purge_page_cache', array( $this, 'purge_page_cache' ) );

		add_action( 'pre_post_update', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'save_post', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'wp_trash_post', array( $this, 'purge_on_post_update' ), 10, 1 );
		add_action( 'wp_set_comment_status', array( $this, 'purge_post_on_comment_status_change' ), 10, 2 );
		add_action( 'set_comment_cookies', array( $this, 'set_comment_cookie' ), 10, 2 );
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
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=pc_purge_page_cache' ), 'pc_purge_page_cache' ),
			'parent' => 'powered-cache',
		) );
	}


	/**
	 * Purge page cache directory
	 * @since 1.0
	 */
	public function purge_page_cache() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'pc_purge_page_cache' ) ) {
			wp_nonce_ays( '' );
		}

		pc_clean_page_cache_dir();
		PC_Admin_Helper::set_flash_message( __( 'Page cache deleted successfully!', 'powered-cache' ) );

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
			$urls = pc_get_post_related_urls( $post_id );

			$urls = apply_filters( 'pc_advanced_cache_purge_urls', $urls, $post_id );

			foreach ( $urls as $url ) {
				pc_delete_page_cache( $url );
			}
		}

		do_action( 'pc_advanced_cache_purge_post', $post_id, $urls );
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
		pc_delete_page_cache( get_permalink( $post_id ) );
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
		setcookie( 'pc_commented_posts[' . $post_id . ']', parse_url( get_permalink( $post_id ), PHP_URL_PATH ), ( time() + HOUR_IN_SECONDS * 24 * 30 ) );
	}

}