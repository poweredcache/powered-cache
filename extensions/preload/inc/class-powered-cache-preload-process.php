<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Preload_Process' ) ):

	class Powered_Cache_Preload_Process {

		public function __construct() { }

		/**
		 * Setup actions and filters
		 *
		 * @since 1.0
		 */
		private function setup() {
			add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
			add_action( 'init', array( $this, 'schedule_events' ) );

			add_action( 'powered_cache_preload_hook', array( $this, 'setup_child_process' ) );
			add_action( 'powered_cache_preload_child_process', array( $this, 'preload' ) );
		}


		public function cron_schedules( $schedules ) {

			if ( powered_cache_get_extension_option( 'preload', 'interval', 60 ) > 0 ) {
				$interval = powered_cache_get_extension_option( 'preload', 'interval', 60 ) * 60;

				$schedules['powered_cache_preload_interval'] = array(
					'interval' => apply_filters( 'powered_cache_preload_interval', $interval ),
					'display'  => esc_html__( 'Preload interval', 'powered-cache' ),
				);
			}


			return $schedules;
		}

		/**
		 * Unschedule events
		 *
		 * @since  1.0
		 */
		public function unschedule_events() {
			if ( wp_next_scheduled( 'powered_cache_preload_hook' ) ) {
				wp_clear_scheduled_hook( 'powered_cache_preload_hook' );
			}

			if ( wp_next_scheduled( 'powered_cache_preload_child_process' ) ) {
				wp_clear_scheduled_hook( 'powered_cache_preload_child_process' );
			}

			if ( get_option( 'powered_cache_preload_runtime_option' ) ) {
				delete_option( 'powered_cache_preload_runtime_option' );
			}
		}

		/**
		 * Setup cron jobs
		 *
		 * @since 1.0
		 */
		public function schedule_events() {

			$timestamp = wp_next_scheduled( 'powered_cache_preload_hook' );

			// Do nothing if page caching disable
			if ( true !== powered_cache_get_option( 'enable_page_caching' ) ) {
				self::unschedule_events();

				return;
			}

			$value = powered_cache_get_extension_option( 'preload', 'post_count', 1000 );

			if ( $value > 0 && false === $timestamp ) {
				wp_schedule_single_event( time() + 10, 'powered_cache_preload_hook' );
			}
		}


		/**
		 * Kick start child process
		 *
		 * @since 1.0
		 */
		public function setup_child_process() {
			$timestamp = wp_next_scheduled( 'powered_cache_preload_child_process' );

			if ( true !== powered_cache_get_option( 'enable_page_caching' ) || 0 == powered_cache_get_extension_option( 'preload', 'interval', 60 ) ) {
				wp_unschedule_event( $timestamp, 'powered_cache_preload_child_process' );

				return;
			}

			if ( ! $timestamp ) {
				wp_schedule_single_event( time() + 40, 'powered_cache_preload_child_process' );

				return;
			}
		}

		/**
		 * Main method of preload tasks.
		 *
		 * @since 1.0
		 */
		public function preload(){
			global $wpdb;

			// setup runtime option
			$runtime_option = get_option( 'powered_cache_preload_runtime_option' );
			if ( ! is_array( $runtime_option ) ) {
				update_option( 'powered_cache_preload_runtime_option', array( 'post_count' => 0, 'time' => time() ) );
			}

			$post_count = $runtime_option['post_count'];

			if ( powered_cache_get_extension_option( 'preload', 'post_count', 1000 ) > $post_count ) {
				if ( true === powered_cache_get_extension_option( 'preload', 'taxonomies', true ) ) {
					$this->preload_taxonomies();
				}

				$this->preload_posts();
				// keep working
				wp_schedule_single_event( time() + 40, 'powered_cache_preload_child_process' );
			} else {
				// preload homepage at last
				$this->preload_homepage();
				// we run preload tasks, that's all.
				delete_option( 'powered_cache_preload_runtime_option' );

				// clean child process
				if ( wp_next_scheduled( 'powered_cache_preload_child_process' ) ) {
					wp_clear_scheduled_hook( 'powered_cache_preload_child_process' );
				}

				$cron_interval = (int) powered_cache_get_extension_option( 'preload', 'interval', 60 );
				if ( $cron_interval > 0 ) {
					// re-schedule main cron
					wp_schedule_single_event( time() + ( $cron_interval * 60 ), 'powered_cache_preload_hook' );
				}
			}

		}


		/**
		 * Preload taxonomies
		 * @since 1.0
		 */
		public function preload_taxonomies() {
			$runtime_option = get_option( 'powered_cache_preload_runtime_option' );
			$taxonomies = apply_filters( 'powered_cache_preload_taxonomies', array( 'post_tag' => 'tag', 'category' => 'category' ) );
			foreach ( $taxonomies as $taxonomy => $path ) {
				$taxonomy_filename = trailingslashit( powered_cache_get_cache_dir() ) . "taxonomy_" . $taxonomy . ".txt";
				if ( 0 == $runtime_option['post_count'] ) {
					@unlink( $taxonomy_filename );
				}

				if ( false == @file_exists( $taxonomy_filename ) ) {
					$out     = '';
					$records = get_terms( $taxonomy );
					foreach ( $records as $term ) {
						$out .= get_term_link( $term ) . PHP_EOL;
					}
					$fp = @fopen( $taxonomy_filename, 'w' );
					if ( $fp ) {
						@fwrite( $fp, $out );
						@fclose( $fp );
					}
					$details = explode( PHP_EOL, $out );
				} else {
					$details = explode( PHP_EOL, file_get_contents( $taxonomy_filename ) );
				}
				if ( count( $details ) != 1 && $details[0] != '' ) {
					$rows = array_splice( $details, 0, 40 );
					foreach ( (array) $rows as $key => $url ) {
						set_time_limit( 30 );
						if ( $url == '' ) {
							continue;
						}
						powered_cache_delete_page_cache( $url );
						wp_remote_get( $url, array( 'timeout' => 30, 'blocking' => true ) );
						unset( $rows[ $key ] );
						sleep( 1 );
					}
					// if we couldn't finish urls, put back
					if ( $rows ) {
						$details = array_merge( $details, $rows );
					}
					$fp = @fopen( $taxonomy_filename, 'w' );
					if ( $fp ) {
						@fwrite( $fp, implode( PHP_EOL, $details ) );
						@fclose( $fp );
					}
				}
			}
		}


		public function preload_posts() {
			global $wpdb;
			$runtime_option = get_option( 'powered_cache_preload_runtime_option' );
			$post_count = $runtime_option['post_count'];

			if ( get_option( 'show_on_front' ) == 'page' ) {
				$page_on_front  = get_option( 'page_on_front' );
				$page_for_posts = get_option( 'page_for_posts' );
			} else {
				$page_on_front = $page_for_posts = 0;
			}

			$types = apply_filters( 'powered_cache_preload_post_types', get_post_types( array( 'public' => true, 'publicly_queryable' => true ), 'names', 'or' ) );
			$types = array_map( 'esc_sql', $types );
			$types = "'" . implode( "','", $types ) . "'";
			$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ( post_type IN ( $types ) ) AND post_status = 'publish' ORDER BY ID ASC LIMIT $post_count, 100" );
			foreach ( $posts as $post_id ) {
				set_time_limit( 30 );

				//skip page used for front
				if ( $page_on_front != 0 && ( $post_id == $page_on_front || $post_id == $page_for_posts ) ){
					continue;
				}

				$url = get_permalink( $post_id );
				powered_cache_delete_page_cache( $url );
				$this->http_request( $url, array( 'timeout' => 30, 'blocking' => true ) );
				sleep( 1 );
				$post_count ++;
			}

			$runtime_option['post_count'] = $post_count;
			$runtime_option['time']       = time();
			update_option( 'powered_cache_preload_runtime_option', $runtime_option );
		}


		public function preload_homepage() {
			$site_url = site_url();
			powered_cache_delete_page_cache( $site_url );
			$this->http_request( $site_url, array( 'timeout' => 30, 'blocking' => true ) );
		}


		/**
		 * Make crawl request
		 *
		 * @since 1.0
		 * @param  string     $url
		 * @param array $args
		 */
		public function http_request( $url, $args = array() ) {

			$args['headers']['user-agent'] = 'Powered Cache Preloader';
			wp_remote_get( $url, $args );

			if ( true === powered_cache_get_option( 'cache_mobile' ) && true === powered_cache_get_option( 'cache_mobile_separate_file' ) ) {

				$sleep_time = apply_filters( 'powered_cache_preload_http_request_time', 2 );
				sleep( $sleep_time ); // wait before new request

				$args['headers']['user-agent'] = 'Powered Cache Preloader mobile iPhone';
				wp_remote_get( $url, $args );
			}

			do_action( 'powered_cache_preload_http_request', $url, $args );

			return;
		}

		/**
		 * Return an instance of the current class, create one if it doesn't exist
		 *
		 * @since  1.0
		 * @return object
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
endif;