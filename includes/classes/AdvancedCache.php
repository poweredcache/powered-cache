<?php
/**
 * Admin
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\CachePurger;
use const PoweredCache\Constants\POST_META_DISABLE_CACHE_KEY;
use function PoweredCache\Utils\clean_page_cache_dir;
use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\delete_page_cache;
use function PoweredCache\Utils\get_post_related_urls;
use const PoweredCache\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Admin
 *
 * @package PoweredCache
 */
class AdvancedCache {

	/**
	 * Holds plugin settings
	 *
	 * @var array $settings
	 */
	private $settings;


	/**
	 * Instance of CachePreloader
	 *
	 * @var CachePurger
	 */
	private $cache_purger;

	/**
	 * Return an instance of the current class
	 *
	 * @return AdvancedCache
	 * @since 1.0
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
		$this->settings = \PoweredCache\Utils\get_settings();

		if ( ! $this->settings['enable_page_cache'] ) {
			return;
		}

		$this->cache_purger = CachePurger::factory();

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		add_action( 'admin_post_powered_cache_purge_page_cache', array( $this, 'purge_page_cache' ) );
		add_action( 'admin_post_powered_cache_purge_page_cache_network', array( $this, 'purge_page_cache_network_wide' ) );
		add_action( 'transition_post_status', array( $this, 'purge_on_post_update' ), 9999, 3 );
		add_action( 'switch_theme', array( $this, 'purge_on_switch_theme' ) );
		add_action( 'wp_set_comment_status', array( $this, 'purge_post_on_comment_update' ) );
		add_action( 'edit_comment', array( $this, 'purge_post_on_comment_update' ) );
		add_action( 'set_comment_cookies', array( $this, 'set_comment_cookie' ), 10, 2 );
		add_filter( 'powered_cache_post_related_urls', array( $this, 'powered_cache_post_related_urls' ) );
		add_filter( 'powered_cache_page_cache_enable', array( $this, 'maybe_caching_disabled' ) );
		add_filter( 'powered_cache_mod_rewrite', array( $this, 'maybe_disable_mod_rewrite' ), 99 );
		add_action( 'wp_update_site', array( $this, 'purge_on_site_update' ), 10, 2 );
		add_action( 'create_term', array( $this, 'purge_on_term_change' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'purge_on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'purge_on_term_change' ), 10, 3 );
	}

	/**
	 * Add purge button on admin bar
	 *
	 * @param object $wp_admin_bar Admin bar object
	 *
	 * @since 1.0
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( POWERED_CACHE_IS_NETWORK && current_user_can( 'manage_network' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'     => 'advanced-cache-purge-network',
					'title'  => __( 'Purge Page Cache [Network Wide - All Sites]', 'powered-cache' ),
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache_network' ), 'powered_cache_purge_page_cache_network' ),
					'parent' => 'powered-cache',
				)
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'     => 'advanced-cache-purge',
					'title'  => __( 'Purge Page Cache', 'powered-cache' ),
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache' ), 'powered_cache_purge_page_cache' ),
					'parent' => 'powered-cache',
				)
			);

			if ( is_singular() && ! is_preview() && is_post_publicly_viewable( get_the_ID() ) ) {
				$wp_admin_bar->add_menu(
					array(
						'id'     => 'advanced-cache-current-page-purge',
						'title'  => esc_html__( 'Purge Current Page', 'powered-cache' ),
						'href'   => wp_nonce_url( admin_url( sprintf( 'admin-post.php?action=powered_cache_purge_page_cache&type=current-page&post=%d', get_the_ID() ) ), 'powered_cache_purge_page_cache' ),
						'parent' => 'powered-cache',
					)
				);
			}
		}
	}


	/**
	 * Purge page cache network wide
	 *
	 * @since 2.0
	 */
	public function purge_page_cache_network_wide() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_purge_page_cache_network' ) ) { // phpcs:ignore
			wp_nonce_ays( '' );
		}

		if ( current_user_can( 'manage_network' ) ) {
			if ( $this->settings['async_cache_cleaning'] ) {
				$this->cache_purger->push_to_queue( [ 'call' => 'clean_page_cache_dir' ] );
				$this->cache_purger->save()->dispatch();
			} else {
				clean_page_cache_dir();
			}
			$redirect_url = add_query_arg( 'pc_action', 'flush_page_cache_network', wp_get_referer() );
		} else {
			$redirect_url = add_query_arg( 'pc_action', 'flush_page_cache_network_err_permission', wp_get_referer() );
		}

		delete_site_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Purge page cache directory
	 *
	 * @since 1.0
	 * @since 1.1 clean site dir instead of root page caching dir
	 */
	public function purge_page_cache() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'powered_cache_purge_page_cache' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			wp_nonce_ays( '' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			if ( isset( $_GET['type'] ) && 'current-page' === $_GET['type'] && ! empty( $_GET['post'] ) ) {
				$this->purge_post( absint( $_GET['post'] ) );
			} elseif ( $this->settings['async_cache_cleaning'] ) {
				$this->cache_purger->push_to_queue( [ 'call' => 'clean_site_cache_dir' ] );
				$this->cache_purger->save()->dispatch();
			} else {
				clean_site_cache_dir();
			}

			$redirect_url = add_query_arg( 'pc_action', 'flush_page_cache', wp_get_referer() );
		} else {
			$redirect_url = add_query_arg( 'pc_action', 'flush_page_cache_err_permission', wp_get_referer() );
		}

		delete_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}


	/**
	 * Purge cache when post updated
	 *
	 * @param string   $new_status New Post status.
	 * @param string   $old_status Old Post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @return void
	 * @since 3.4 adjusted for `transition_post_status` hook
	 * @since 1.0
	 */
	public function purge_on_post_update( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			$this->purge_post( $post->ID );
		}
	}

	/**
	 * Purge post related page cache
	 *
	 * @param int $post_id Post ID
	 *
	 * @since 3.4
	 */
	public function purge_post( $post_id ) {
		$current_post = get_post( $post_id );

		if ( ! is_a( $current_post, 'WP_Post' ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! is_post_type_viewable( $current_post->post_type ) ) {
			return;
		}

		if ( ! in_array( $current_post->post_status, array( 'publish', 'private', 'trash', 'pending', 'draft' ), true ) ) {
			return;
		}

		$urls = get_post_related_urls( $post_id );

		/**
		 * Page cache purge urls.
		 *
		 * @hook   powered_cache_advanced_cache_purge_urls
		 *
		 * @param  {array} $urls The list of URLs that will purged
		 * @param  {int} $post_id Post ID.
		 *
		 * @return {array} New value
		 * @since  1.0
		 */
		$urls         = apply_filters( 'powered_cache_advanced_cache_purge_urls', $urls, $post_id );
		$deleted_urls = [];

		if ( $this->settings['async_cache_cleaning'] ) {
			$this->cache_purger->push_to_queue(
				[
					'call' => 'delete_page_cache',
					'urls' => $urls,
				]
			);
			$deleted_urls = $urls;
			$this->cache_purger->save()->dispatch();
		} else {
			foreach ( $urls as $url ) {
				if ( delete_page_cache( $url ) ) {
					$deleted_urls[] = $url;
				}
			}
		}

		/**
		 * Fires after purging cache on post update.
		 *
		 * @hook  powered_cache_advanced_cache_purge_post
		 *
		 * @param {int} $post_id The Post ID.
		 * @param {array} $deleted_urls The list of purged urls with the updated post.
		 * @param {array} $urls The list of urls that will be purged. @since 3.6
		 *
		 * @since 1.0
		 */
		do_action( 'powered_cache_advanced_cache_purge_post', $post_id, $deleted_urls, $urls );
	}

	/**
	 * Purge post cache when comment status change
	 *
	 * @param int    $comment_id     Comment ID
	 * @param string $comment_status Comment status
	 *
	 * @return void
	 * @deprecated since 3.4.4
	 */
	public function purge_post_on_comment_status_change( $comment_id, $comment_status ) {
		// deprecate this method in favor of `purge_post_on_comment_update`
		_deprecated_function( __METHOD__, '3.4.4', 'purge_post_on_comment_update' );
		$this->purge_post_on_comment_update( $comment_id );
		$comment  = get_comment( $comment_id );
		$post_id  = $comment->comment_post_ID;
		$post_url = get_permalink( $post_id );

		do_action( 'powered_cache_advanced_cache_purge_on_comment_update', $post_id, $post_url, $comment_id );
	}

	/**
	 * Delete page cache when a comment update
	 *
	 * @param int $comment_id Comment ID
	 *
	 * @since 3.4.4
	 */
	public function purge_post_on_comment_update( $comment_id ) {
		$comment  = get_comment( $comment_id );
		$post_id  = $comment->comment_post_ID;
		$post_url = get_permalink( $post_id );
		delete_page_cache( $post_url, true );

		/**
		 * Fires after purging cache for a post that associated a comment
		 *
		 * @hook  powered_cache_advanced_cache_purge_on_comment_update
		 *
		 * @param {int} $post_id Post ID.
		 * @param {string} $post_url Post permalink.
		 * @param {int} $comment_id Comment ID.
		 *
		 * @since 3.4.4
		 */
		do_action( 'powered_cache_advanced_cache_purge_on_comment_update', $post_id, $post_url, $comment_id );
	}

	/**
	 * Purge site when switching to a new theme
	 *
	 * @since 2.0
	 */
	public function purge_on_switch_theme() {
		if ( $this->settings['async_cache_cleaning'] ) {
			$this->cache_purger->push_to_queue( [ 'call' => 'clean_site_cache_dir' ] );
			$this->cache_purger->save()->dispatch();
		} else {
			clean_site_cache_dir();
		}
	}

	/**
	 * Purge site cache when site updated (eg: archived, deleted etc...)
	 *
	 * @param \WP_Site $new_site New site object.
	 * @param \WP_Site $old_site Old site object.
	 *
	 * @return void
	 * @since 2.5.3
	 */
	public function purge_on_site_update( $new_site, $old_site ) {
		switch_to_blog( $old_site->id );
		if ( $this->settings['async_cache_cleaning'] ) {
			$this->cache_purger->push_to_queue( [ 'call' => 'clean_site_cache_dir' ] );
			$this->cache_purger->save()->dispatch();
		} else {
			clean_site_cache_dir();
		}
		restore_current_blog();
	}

	/**
	 * Leave a cookie when commenting on a post as usual and don't show the cached page.
	 * `comment_author_*` cookies are site-wide available.
	 * Don't show cached results just for left a comment 5 months ago is not seems efficient here.
	 *
	 * @param object $comment Comment object
	 * @param object $user    User Object
	 *
	 * @since 1.0
	 */
	public function set_comment_cookie( $comment, $user ) {
		$post_id = $comment->comment_post_ID;
		$path    = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );
		setcookie( 'powered_cache_commented_posts[' . $post_id . ']', $path, ( time() + DAY_IN_SECONDS * 30 ), $path );
	}


	/**
	 * Adds custom pages to post related urls
	 *
	 * @param array $urls The list of the urls
	 *
	 * @return array urls
	 * @since 1.1
	 */
	public function powered_cache_post_related_urls( $urls ) {
		$settings = \PoweredCache\Utils\get_settings();

		$additional_pages = $settings['purge_additional_pages'];

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


	/**
	 * Get the list of rejected cookies
	 *
	 * @return mixed|void
	 * @since 2.0
	 */
	public static function get_rejected_cookies() {
		$settings = \PoweredCache\Utils\get_settings();
		$cookies  = [];

		if ( ! empty( $settings['rejected_cookies'] ) ) {
			$cookies = preg_split( '#(\r\n|\r|\n)#', $settings['rejected_cookies'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		$wp_cookies = [
			'wp-postpass',
			'wordpressuser_',
			'wordpresspass_',
			'wordpress_sec_',
			'wordpress_logged_in_',
			'powered_cache_commented_posts',
			'comment_author_',
			'comment_author_email_',
			'comment_author_url_',
		];

		if ( ! empty( $cookies ) ) {
			$wp_cookies = array_merge( $cookies, $wp_cookies );
		}

		/**
		 * Filter rejected cookie list.
		 *
		 * @hook   powered_cache_rejected_cookies
		 *
		 * @param  {array} $wp_cookies The rejected cookie list from the caching.
		 *
		 * @return {array} Rejected cookie list
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_rejected_cookies', $wp_cookies );
	}

	/**
	 * Get the list of rejected cookies
	 *
	 * @return mixed|void
	 * @since 2.0
	 */
	public static function get_vary_cookies() {
		$settings = \PoweredCache\Utils\get_settings();
		$cookies  = [];

		if ( ! empty( $settings['vary_cookies'] ) ) {
			$cookies = preg_split( '#(\r\n|\r|\n)#', $settings['vary_cookies'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		/**
		 * Filter vary cookie list.
		 *
		 * @hook   powered_cache_vary_cookies
		 *
		 * @param  {array} $cookies The varied cookie list, which allows to create separate cache based on the match.
		 *
		 * @return {array} Vary cookie list
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_vary_cookies', $cookies );
	}

	/**
	 * Get the list of rejected referrers
	 *
	 * @return mixed|null
	 * @since 3.6
	 */
	public static function get_rejected_referrers() {
		$settings           = \PoweredCache\Utils\get_settings();
		$rejected_referrers = [];

		if ( ! empty( $settings['rejected_referrers'] ) ) {
			$rejected_referrers = preg_split( '#(\r\n|\r|\n)#', $settings['rejected_referrers'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		/**
		 * Filter rejected referrer list.
		 *
		 * @hook   powered_cache_rejected_referrers
		 *
		 * @param  {array} $rejected_referrers The referrer that will not see the cached page.
		 *
		 * @return {array} Rejected referrer list.
		 * @since  3.6
		 */
		return apply_filters( 'powered_cache_rejected_referrers', $rejected_referrers );
	}

	/**
	 * Get the list of rejected user agents
	 *
	 * @return mixed|void
	 * @since 2.0
	 */
	public static function get_rejected_user_agents() {
		$settings             = \PoweredCache\Utils\get_settings();
		$rejected_user_agents = [];

		if ( ! empty( $settings['rejected_user_agents'] ) ) {
			$rejected_user_agents = preg_split( '#(\r\n|\r|\n)#', $settings['rejected_user_agents'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		$rejected_user_agents[] = 'facebookexternalhit';

		/**
		 * Filter rejected user agent list.
		 *
		 * @hook   powered_cache_rejected_user_agents
		 *
		 * @param  {array} $rejected_user_agents The user agents that will not see the cached page.
		 *
		 * @return {array} Rejected user agent list.
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_rejected_user_agents', $rejected_user_agents );
	}

	/**
	 * Get the list of rejected uri that never get cached
	 *
	 * @return mixed|void
	 * @since 2.0
	 */
	public static function get_rejected_uri() {
		$settings          = \PoweredCache\Utils\get_settings();
		$rejected_uri_list = [];

		if ( ! empty( $settings['rejected_uri'] ) ) {
			$rejected_uri_list = preg_split( '#(\r\n|\r|\n)#', $settings['rejected_uri'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		/**
		 * Filter rejected uri list
		 *
		 * @hook   powered_cache_rejected_uri_list
		 *
		 * @param  {array} $rejected_uri_list The list of rejected uri that never get cached.
		 *
		 * @return {array} New value
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_rejected_uri_list', $rejected_uri_list );
	}


	/**
	 * Get the allowed cache query parameters
	 *
	 * @return mixed|null
	 * @since 3.0
	 */
	public static function get_cache_query_string() {
		$settings            = \PoweredCache\Utils\get_settings();
		$cache_query_strings = [];

		if ( ! empty( $settings['cache_query_strings'] ) ) {
			$cache_query_strings = preg_split( '#(\r\n|\r|\n)#', $settings['cache_query_strings'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		$query_strings = [
			'lang',
		];

		if ( ! empty( $cache_query_strings ) ) {
			$cache_query_strings = array_merge( $query_strings, $cache_query_strings );
		}

		/**
		 * Filter accepted query strings.
		 *
		 * @hook   powered_cache_cache_query_strings
		 *
		 * @param  {array} $query_strings The list of query strings that will be cached based on their value
		 *
		 * @return {array} New value
		 * @since  3.0
		 */
		return apply_filters( 'powered_cache_cache_query_strings', $cache_query_strings );
	}

	/**
	 * These query strings will be ignored during the caching
	 *
	 * @return array
	 * @since     3.0 deprecated
	 * @deprecated Use `self::get_ignored_query_strings` instead
	 */
	public static function get_accepted_query_strings() {
		_deprecated_function( '\PoweredCache\AdvancedCache::get_accepted_query_strings', '3.0', '\PoweredCache\AdvancedCache::get_ignored_query_strings' );

		return self::get_ignored_query_strings();
	}

	/**
	 * These query strings will be ignored during the caching
	 *
	 * @return mixed|void
	 * @since 3.0
	 */
	public static function get_ignored_query_strings() {
		$settings              = \PoweredCache\Utils\get_settings();
		$ignored_query_strings = [];

		if ( ! empty( $settings['ignored_query_strings'] ) ) {
			$ignored_query_strings = preg_split( '#(\r\n|\r|\n)#', $settings['ignored_query_strings'], - 1, PREG_SPLIT_NO_EMPTY );
		}

		$query_strings = [
			'fbclid',
			'fb_action_ids',
			'fb_action_types',
			'ref',
			'gclid',
			'fb_source',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'utm_expid',
			'_ga',
			'mc_cid',
			'mc_eid',
			'campaignid',
			'adgroupid',
			'adid',
			'age-verified',
			'usqp',
			'cn-reloaded',
			'ao_noptimize',
		];

		if ( ! empty( $ignored_query_strings ) ) {
			$query_strings = array_merge( $query_strings, $ignored_query_strings );
		}

		/**
		 * Filters ignored query strings.
		 *
		 * @hook        powered_cache_accepted_query_strings
		 *
		 * @param       {array} $query_strings The list of query strings will be ignored during the caching
		 *
		 * @return      {array} New value
		 * @since       2.0
		 * @depreacated since 3.0
		 */
		$query_strings = apply_filters( 'powered_cache_accepted_query_strings', $query_strings );

		/**
		 * Filters ignored query strings.
		 *
		 * @hook   powered_cache_ignored_query_strings
		 *
		 * @param  {array} $query_strings The list of query strings will be ignored during the caching
		 *
		 * @return {array} New value
		 * @since  3.0
		 */
		$query_strings = apply_filters( 'powered_cache_ignored_query_strings', $query_strings );

		return $query_strings;
	}


	/**
	 * Disable caching of the individual page when it marked as dont' cache
	 *
	 * @param bool $status Cache status
	 *
	 * @return bool
	 */
	public function maybe_caching_disabled( $status ) {
		if ( is_single() ) {
			$is_cache_disabled = (bool) get_post_meta( get_the_ID(), POST_META_DISABLE_CACHE_KEY, true );
			if ( $is_cache_disabled ) {
				return false;
			}
		}

		return $status;
	}

	/**
	 * Maybe disable rewrite rules based on the settings
	 *
	 * @param bool $rewrite_status mod rewrite status
	 *
	 * @return bool
	 * @since 2.0
	 */
	public function maybe_disable_mod_rewrite( $rewrite_status ) {
		$vary_cookies = self::get_vary_cookies();
		if ( ! empty( $vary_cookies ) ) {
			$rewrite_status = false;
		}

		return $rewrite_status;
	}


	/**
	 * Purge cache when term change
	 *
	 * @param int    $term_id  Term ID
	 * @param int    $tt_id    Term Taxonomy ID
	 * @param string $taxonomy Taxonomy
	 *
	 * @since 3.4.2
	 */
	public function purge_on_term_change( $term_id, $tt_id, $taxonomy ) {
		$term_taxonomy = get_taxonomy( $taxonomy );

		if ( ! $term_taxonomy->public ) {
			return;
		}

		$term_url = get_term_link( $term_id, $taxonomy );

		if ( ! is_wp_error( $term_url ) ) {
			if ( $this->settings['async_cache_cleaning'] ) {
				$this->cache_purger->push_to_queue(
					[
						'call' => 'delete_page_cache',
						'urls' => $term_url,
					]
				);
				$this->cache_purger->save()->dispatch();
			} else {
				delete_page_cache( $term_url );
			}
		}
	}

}

