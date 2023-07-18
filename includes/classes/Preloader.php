<?php
/**
 * Cache Preloading functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\CachePreloader;
use function PoweredCache\Utils\permalink_structure_has_trailingslash;
use function PoweredCache\Utils\site_cache_dir;

/**
 * Class Preloader
 */
class Preloader {
	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	private $settings;

	/**
	 * Instance of CachePreloader
	 *
	 * @var CachePreloader
	 */
	private $cache_preloader;

	/**
	 * Return an instance of the current class
	 *
	 * @return Preloader
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
	 * Setup routine
	 */
	public function setup() {
		$this->settings = \PoweredCache\Utils\get_settings();
		add_filter( 'wp_resource_hints', [ $this, 'dns_prefetch' ], 10, 2 );
		add_filter( 'wp_resource_hints', [ $this, 'preconnect_resources' ], 10, 2 );

		// bail if the preload not activated
		if ( ! $this->settings['enable_cache_preload'] ) {
			return;
		}

		$this->cache_preloader = CachePreloader::factory();

		/**
		 * Page cache needs to be activated to get benefits of preloading
		 */
		if ( ! $this->settings['enable_page_cache'] ) {
			return;
		}

		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ] );
		add_action( 'admin_post_powered_cache_preload_cache', [ $this, 'start_preload' ] );
		add_action( 'powered_cache_purge_all_cache', [ $this, 'setup_preload_queue' ] );
		add_action( 'powered_cache_clean_site_cache_dir', [ $this, 'setup_preload_queue' ] );
		add_action( 'powered_cache_advanced_cache_purge_post', [ $this, 'add_purged_urls_to_preload_queue' ], 10, 2 );
		add_action( 'powered_cache_expired_files_deleted', [ $this, 'add_expired_urls_to_preload_queue' ], 10, 2 );
	}

	/**
	 * Preload Admin bar menu
	 *
	 * @param object $wp_admin_bar Admin bar object
	 *
	 * @since 1.0
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'id'     => 'preload-cache',
				'title'  => __( 'Preload Cache', 'powered-cache' ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_preload_cache' ), 'powered_cache_preload_cache' ),
				'parent' => 'powered-cache',
			)
		);
	}

	/**
	 * Add preloading items to queue
	 */
	public function start_preload() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered_cache_preload_cache' ) ) { // phpcs:ignore
			wp_nonce_ays( '' );
		}

		if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
			$redirect_url = add_query_arg( 'pc_action', 'start_preload_err_permission', wp_get_referer() );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$redirect_url = add_query_arg( 'pc_action', 'start_preload_err_permission', wp_get_referer() );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		$this->setup_preload_queue();

		$redirect_url = add_query_arg( 'pc_action', 'start_preload', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Setup preload
	 */
	public function setup_preload_queue() {
		// cancel existing process before populating
		$this->cache_preloader->cancel_process();

		if ( POWERED_CACHE_IS_NETWORK ) {
			$sites = get_sites( [ 'fields' => 'ids' ] );
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				\PoweredCache\Utils\log( sprintf( 'Populating preload queue for site: %d', $site_id ) );
				$this->populate_preload_queue();
				restore_current_blog();
			}
		} else {
			$this->populate_preload_queue();
		}

		$this->cache_preloader->save()->dispatch();

		if ( $this->settings['enable_sitemap_preload'] && function_exists( '\PoweredCachePremium\Utils\preload_sitemap' ) ) {
			\PoweredCachePremium\Utils\preload_sitemap();
		}
	}

	/**
	 * Populate preload queue based on settings
	 */
	protected function populate_preload_queue() {
		$preload_urls = [];

		if ( $this->settings['preload_homepage'] ) {
			$front_page = get_option( 'page_on_front' );
			if ( ! empty( $front_page ) ) {
				$front_page_url = get_permalink( $front_page );
				$preload_urls[] = $front_page_url;
				\PoweredCache\Utils\log( sprintf( 'Front page URL added to preload queue: %s', $front_page_url ) );
			}

			$posts_page = get_option( 'page_for_posts' );
			if ( ! empty( $posts_page ) ) {
				$posts_page_url = get_permalink( $posts_page );
				$preload_urls[] = $posts_page_url;
				\PoweredCache\Utils\log( sprintf( 'Posts Page URL added to preload queue: %s', $posts_page_url ) );
			}

			/**
			 * trailingslashit important here,
			 * likely redirection is not followed for non-blocking preload request
			 */
			$home_url       = trailingslashit( get_home_url() );
			$preload_urls[] = $home_url;
			\PoweredCache\Utils\log( sprintf( 'Home URL added to preload queue: %s', $home_url ) );
		}

		if ( $this->settings['preload_public_posts'] ) {
			$public_post_urls = $this->prepare_public_posts_urls();
			$preload_urls     = array_merge( $preload_urls, $public_post_urls );
			\PoweredCache\Utils\log( sprintf( 'Public posts added to preload queue. ' ) );
		}

		if ( $this->settings['preload_public_tax'] ) {
			$public_tax_term_urls = $this->prepare_public_tax_terms_urls();
			$preload_urls         = array_merge( $preload_urls, $public_tax_term_urls );
			\PoweredCache\Utils\log( sprintf( 'Public tax terms added to preload queue. ' ) );
		}

		/**
		 * Filters preload urls before sending to queue
		 *
		 * @hook   populate_preload_queue_urls
		 *
		 * @param  {array} $preload_urls The list of preload urls
		 *
		 * @return {array} New value.
		 * @since  2.4
		 */
		$preload_urls = apply_filters( 'populate_preload_queue_urls', $preload_urls );

		foreach ( $preload_urls as $url ) {
			$this->cache_preloader->push_to_queue( $url );
		}
	}


	/**
	 * Add related pages to preload queue when the cache got cleared
	 *
	 * @param int   $post_id Post ID
	 * @param array $urls    The URL list of the related pages that cleared during post update
	 *
	 * @since 2.0
	 */
	public function add_purged_urls_to_preload_queue( $post_id, $urls ) {
		if ( ! $urls ) {
			return;
		}

		foreach ( $urls as $url ) {
			$this->cache_preloader->push_to_queue( $url );
			\PoweredCache\Utils\log( sprintf( 'Pushed to preload queue [add_purged_urls_to_preload_queue]: %s', $url ) );
		}

		$this->cache_preloader->save()->dispatch();
	}

	/**
	 * Requeue deleted URLs
	 *
	 * @param array $expired_files Full path of cached item
	 */
	public function add_expired_urls_to_preload_queue( $expired_files ) {

		/**
		 * Filters expired urls preloading status
		 *
		 * @hook   powered_cache_preload_expired_urls
		 *
		 * @param  {boolean} $status True by default for preloading urls.
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		$preload_expired_urls = apply_filters( 'powered_cache_preload_expired_urls', true );

		if ( true !== $preload_expired_urls ) {
			return;
		}

		/**
		 * Get dir path without cache file name (eg index.html)
		 */
		$expired_files = array_map(
			function ( $item ) {
				return dirname( $item );
			},
			$expired_files
		);

		$expired_files = array_unique( $expired_files ); // prevent double request due to meta.php + index.php file

		// replace path with site url
		$expired_urls      = str_replace( site_cache_dir(), trailingslashit( get_site_url() ), $expired_files );
		$has_trailingslash = permalink_structure_has_trailingslash();

		foreach ( $expired_urls as $url ) {
			if ( $has_trailingslash ) {
				$url = trailingslashit( $url );
			}
			$this->cache_preloader->push_to_queue( $url );
			\PoweredCache\Utils\log( sprintf( 'Pushed to preload queue [add_expired_urls_to_preload_queue]: %s', $url ) );
		}

		$this->cache_preloader->save()->dispatch();
	}

	/**
	 * Prep. public post urls for preload queue
	 */
	public function prepare_public_posts_urls() {
		global $wpdb;

		$public_posts_url = [];
		/**
		 * Filters posts offset for preload.
		 *
		 * @hook   powered_cache_preload_public_posts_offset
		 *
		 * @param  {int} $offset The offset of the post query.
		 *
		 * @return {int} New value.
		 * @since  2.0
		 */
		$offset           = (int) apply_filters( 'powered_cache_preload_public_posts_offset', 0 );
		$max_preload_item = $this->preload_max_post_count();

		\PoweredCache\Utils\log( sprintf( 'Adding public posts to queue' ) );
		\PoweredCache\Utils\log( sprintf( 'OFFSET: %s', $offset ) );
		\PoweredCache\Utils\log( sprintf( 'LIMIT: %s', $max_preload_item ) );

		/**
		 * Filters public post types.
		 *
		 * @hook   powered_cache_preload_post_types
		 *
		 * @param  {array} $types Public post types.
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		$types = apply_filters(
			'powered_cache_preload_post_types',
			get_post_types(
				array(
					'public'             => true,
					'publicly_queryable' => true,
				),
				'names',
				'or'
			)
		);

		$types = array_map( 'esc_sql', $types );
		$types = "'" . implode( "','", $types ) . "'";
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ( post_type IN ( $types ) ) AND post_status = 'publish' ORDER BY ID ASC LIMIT {$offset},{$max_preload_item}" ); // phpcs:ignore

		\PoweredCache\Utils\log( sprintf( 'Found posts: %s', count( $posts ) ) );

		foreach ( $posts as $post_id ) {
			$public_posts_url[] = get_permalink( $post_id );
		}

		return $public_posts_url;
	}

	/**
	 * Prep. public tax terms urls for preload queue
	 */
	public function prepare_public_tax_terms_urls() {
		$public_tax_term_urls = [];
		$taxonomies           = get_taxonomies( [ 'public' => true ] );

		/**
		 * Filters public taxonomies.
		 *
		 * @hook   powered_cache_preload_taxonomies
		 *
		 * @param  {array} $taxonomies Public taxonomies.
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		$taxonomies = apply_filters( 'powered_cache_preload_taxonomies', $taxonomies );

		foreach ( $taxonomies as $tax ) {
			\PoweredCache\Utils\log( sprintf( 'Taxonomy added to queue: %s', $tax ) );

			/**
			 * Filters taxonomy offset for preload.
			 *
			 * @hook   powered_cache_preload_public_taxonomies_offset
			 *
			 * @param  {int} $offset Public taxonomies.
			 *
			 * @return {int} New value.
			 * @since  2.0
			 */
			$offset = (int) apply_filters( 'powered_cache_preload_public_taxonomies_offset', 0 );
			$limit  = $this->preload_max_term_count();

			/**
			 * Filters term query args.
			 *
			 * @hook   powered_cache_preload_tax_term_args
			 *
			 * @param  {array} $args Arguments for terms.
			 *
			 * @return {array} New value.
			 * @since  2.0
			 */
			$args = apply_filters(
				'powered_cache_preload_tax_term_args',
				[
					'hide_empty' => false,
					'orderby'    => 'count',
					'offset'     => $offset,
					'number'     => $limit,
				],
				$tax
			);

			\PoweredCache\Utils\log( 'Taxonomy preload args: [powered_cache_preload_tax_term_args]:' . print_r( $args, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			$terms = get_terms( $tax, $args );

			foreach ( $terms as $term ) {
				$public_tax_term_urls[] = get_term_link( $term );
			}
		}

		return $public_tax_term_urls;
	}


	/**
	 * Max number of posts that will preloaded
	 *
	 * @return mixed|void
	 */
	public function preload_max_post_count() {
		/**
		 * Filters preload post count
		 *
		 * @hook   powered_cache_preload_post_limit
		 *
		 * @param  {int} $limit The number of posts that will preloaded.
		 *
		 * @return {int} New value.
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_preload_post_limit', 1000 );
	}

	/**
	 * Max number of public terms that will preloaded
	 *
	 * @return mixed|void
	 */
	public function preload_max_term_count() {
		/**
		 * Filters preload term count
		 *
		 * @hook   powered_cache_preload_term_limit
		 *
		 * @param  {int} $limit The number of terms that will preloaded.
		 *
		 * @return {int} New value.
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_preload_term_limit', 100 );
	}

	/**
	 * Filters domains and URLs for resource hints of relation type
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for, e.g. 'preconnect' or 'prerender'.
	 *
	 * @return array $urls resource hints
	 * @since 2.2 "preconnect" hint split from dns prefetch
	 */
	public function dns_prefetch( $urls, $relation_type ) {
		$domains = $this->get_prefetch_dns();
		if ( $domains && is_array( $domains ) ) {
			foreach ( $domains as $domain ) {
				if ( 'dns-prefetch' === $relation_type ) {
					$domain = str_replace( [ 'http://', 'https://' ], '//', $domain );
					$urls[] = $domain;
				}
			}
		}

		return $urls;
	}

	/**
	 * Add "preconnect" hint for critical resources
	 *
	 * @param array  $urls          URLs
	 * @param string $relation_type the type of hint
	 *
	 * @return array $urls Resource list
	 * @since 2.2
	 */
	public function preconnect_resources( $urls, $relation_type ) {
		$domains = $this->get_preconnect_resources();
		if ( $domains && is_array( $domains ) ) {
			foreach ( $domains as $domain ) {
				if ( 'preconnect' === $relation_type ) {
					$parsed = wp_parse_url( $domain );
					if ( empty( $parsed['scheme'] ) ) {
						$domain = set_url_scheme( $domain );
					}

					$urls[] = $domain;
				}
			}
		}

		return $urls;
	}


	/**
	 *
	 * Get the list of prefetch domains
	 *
	 * @return mixed|void
	 */
	public function get_prefetch_dns() {
		$settings = \PoweredCache\Utils\get_settings();

		$prefetch_dns = preg_split( '#(\r\n|\r|\n)#', $settings['prefetch_dns'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filters Prefetched DNS list.
		 *
		 * @hook   powered_cache_prefetch_dns
		 *
		 * @param  {array} $prefetch_dns The list of prefetch domains.
		 *
		 * @return {array} New value.
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_prefetch_dns', $prefetch_dns );
	}

	/**
	 * Get the list of preconnect domains
	 *
	 * @return mixed|void
	 * @since 2.2
	 */
	public function get_preconnect_resources() {
		$settings = \PoweredCache\Utils\get_settings();

		$preconnect_resources = preg_split( '#(\r\n|\r|\n)#', $settings['preconnect_resource'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filters Preconnect  resource list.
		 *
		 * @hook   powered_cache_preconnect_resource
		 *
		 * @param  {array} $preconnect_resources The list of prefetch domains.
		 *
		 * @return {array} New value.
		 * @since  2.2
		 */
		return apply_filters( 'powered_cache_preconnect_resource', $preconnect_resources );
	}


	/**
	 * Make preload request
	 *
	 * @param string $url  Target URL
	 * @param array  $args request args
	 *
	 * @return array|\WP_Error
	 */
	public static function preload_request( $url, $args = [] ) {

		/**
		 * Filters args of preload requests.
		 *
		 * @hook   powered_cache_preload_url_request_args
		 *
		 * @param  {array} $args Request defaults.
		 *
		 * @return {array} New value.
		 * @since  2.0
		 */
		$request_args = apply_filters(
			'powered_cache_preload_url_request_args',
			[
				'timeout'    => 0.01,
				'blocking'   => false,
				'user-agent' => 'Powered Cache Preloader',
				'sslverify'  => false,
			]
		);

		$args = wp_parse_args( $args, $request_args );

		/**
		 * Fires before doing preload HTTP request.
		 *
		 * @hook  powered_cache_preload_http_request
		 *
		 * @param {string} $url Preload URL.
		 * @param {array} $args Request arguments.
		 *
		 * @since 2.0
		 */
		do_action( 'powered_cache_preload_http_request', $url, $args );

		\PoweredCache\Utils\log( sprintf( 'Preloading...%s: %s', $url, print_r( $args, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		return wp_remote_get( esc_url_raw( $url ), $args );
	}

	/**
	 * Wait internal between HTTP reqs
	 *
	 * @param int $delay delay time in microseconds
	 */
	public static function wait( $delay = 500000 ) {
		/**
		 * Filters wait time between preload requests.
		 *
		 * @hook   powered_cache_preload_request_interval
		 *
		 * @param  {int} $delay Delay time in milliseconds.
		 *
		 * @return {int} New value.
		 * @since  2.0
		 */
		usleep( absint( apply_filters( 'powered_cache_preload_request_interval', $delay ) ) );
	}


	/**
	 * Get mobile user agent for preload requests
	 *
	 * @return mixed|void
	 */
	public static function mobile_user_agent() {
		/**
		 * Filters mobile agent name for mobile preload requests
		 *
		 * @hook   powered_cache_preload_mobile_user_agent
		 *
		 * @param  {string} $agent Mobile agent name.
		 *
		 * @return {string} New value.
		 * @since  2.0
		 */
		return apply_filters( 'powered_cache_preload_mobile_user_agent', 'Powered Cache Preloader mobile iPhone' );
	}

}
