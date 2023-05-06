<?php
/**
 * Database optimization tasks
 *
 * @package PoweredCache\Async
 */

namespace PoweredCache\Async;

use const PoweredCache\Constants\DB_CLEANUP_COUNT_CACHE_KEY;
use \Powered_Cache_WP_Background_Process as Powered_Cache_WP_Background_Process;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching


/**
 * Class DatabaseOptimizer
 */
class DatabaseOptimizer extends Powered_Cache_WP_Background_Process {

	/**
	 * string
	 *
	 * @var $action
	 */
	protected $action = 'powered_cache_db_optimizer';

	/**
	 * Supported db optimization options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'db_cleanup_post_revisions',
			'db_cleanup_auto_drafts',
			'db_cleanup_trashed_posts',
			'db_cleanup_spam_comments',
			'db_cleanup_trashed_comments',
			'db_cleanup_expired_transients',
			'db_cleanup_all_transients',
			'db_cleanup_optimize_tables',
		];
	}

	/**
	 * Get counts for db entities
	 *
	 * @return array
	 */
	public static function get_db_cleanup_counts() {
		global $wpdb;

		if ( POWERED_CACHE_IS_NETWORK ) {
			$count = get_site_transient( DB_CLEANUP_COUNT_CACHE_KEY );
		} else {
			$count = get_transient( DB_CLEANUP_COUNT_CACHE_KEY );
		}

		if ( false === $count ) {
			$db_cleanup_post_revisions     = 0;
			$db_cleanup_auto_drafts        = 0;
			$db_cleanup_trashed_posts      = 0;
			$db_cleanup_spam_comments      = 0;
			$db_cleanup_trashed_comments   = 0;
			$db_cleanup_expired_transients = 0;
			$db_cleanup_all_transients     = 0;
			$db_cleanup_optimize_tables    = 0;

			if ( POWERED_CACHE_IS_NETWORK ) {
				$sites = get_sites();
				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					$db_cleanup_post_revisions     += $wpdb->get_var( "SELECT count(ID) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_status = 'inherit'" );
					$db_cleanup_auto_drafts        += $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
					$db_cleanup_trashed_posts      += $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_status = 'trash'" );
					$db_cleanup_spam_comments      += $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = 'spam'" );
					$db_cleanup_trashed_comments   += $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')" );
					$db_cleanup_expired_transients += $wpdb->get_var( "SELECT count(option_name) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\__%%' AND option_value < UNIX_TIMESTAMP()" );
					$db_cleanup_all_transients     += $wpdb->get_var( "SELECT count(option_name) FROM $wpdb->options WHERE option_name LIKE '%_transient_%'" );
					$db_cleanup_optimize_tables    += $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM information_schema.tables WHERE table_schema = %s and Engine <> 'InnoDB' and data_free > 0", DB_NAME ) );
					restore_current_blog();
				}
			} else {
				$db_cleanup_post_revisions     = $wpdb->get_var( "SELECT count(ID) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_status = 'inherit'" );
				$db_cleanup_auto_drafts        = $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
				$db_cleanup_trashed_posts      = $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_status = 'trash'" );
				$db_cleanup_spam_comments      = $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = 'spam'" );
				$db_cleanup_trashed_comments   = $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')" );
				$db_cleanup_expired_transients = $wpdb->get_var( "SELECT count(option_name) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\__%%' AND option_value < UNIX_TIMESTAMP()" );
				$db_cleanup_all_transients     = $wpdb->get_var( "SELECT count(option_name) FROM $wpdb->options WHERE option_name LIKE '%_transient_%'" );
				$db_cleanup_optimize_tables    = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM information_schema.tables WHERE table_schema = %s and Engine <> 'InnoDB' and data_free > 0", DB_NAME ) );
			}

			$count = [
				'db_cleanup_post_revisions'     => $db_cleanup_post_revisions,
				'db_cleanup_auto_drafts'        => $db_cleanup_auto_drafts,
				'db_cleanup_trashed_posts'      => $db_cleanup_trashed_posts,
				'db_cleanup_spam_comments'      => $db_cleanup_spam_comments,
				'db_cleanup_trashed_comments'   => $db_cleanup_trashed_comments,
				'db_cleanup_expired_transients' => $db_cleanup_expired_transients,
				'db_cleanup_all_transients'     => $db_cleanup_all_transients,
				'db_cleanup_optimize_tables'    => $db_cleanup_optimize_tables,
			];

			if ( POWERED_CACHE_IS_NETWORK ) {
				set_site_transient( DB_CLEANUP_COUNT_CACHE_KEY, $count, MINUTE_IN_SECONDS * 5 );
			} else {
				set_transient( DB_CLEANUP_COUNT_CACHE_KEY, $count, MINUTE_IN_SECONDS );
			}
		}

		return $count;
	}


	/**
	 * Perform DB cleanup tasks.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		global $wpdb;

		\PoweredCache\Utils\log( sprintf( 'Optimizing...%s', $item ) );

		switch ( $item ) {
			case 'db_cleanup_post_revisions':
				$query = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_status = 'inherit'" );
				if ( $query ) {
					foreach ( $query as $post_id ) {
						wp_delete_post_revision( absint( $post_id ) );
					}
				}
				break;
			case 'db_cleanup_auto_drafts':
				$query = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
				if ( $query ) {
					foreach ( $query as $post_id ) {
						wp_delete_post( absint( $post_id ) );
					}
				}
				break;
			case 'db_cleanup_trashed_posts':
				$query = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'trash'" );
				if ( $query ) {
					foreach ( $query as $post_id ) {
						wp_delete_post( absint( $post_id ) );
					}
				}
				break;
			case 'db_cleanup_spam_comments':
				$query = $wpdb->get_col( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = 'spam'" );
				if ( $query ) {
					foreach ( $query as $comment_id ) {
						wp_delete_comment( absint( $comment_id ), true );
					}
				}
				break;
			case 'db_cleanup_trashed_comments':
				$query = $wpdb->get_col( "SELECT comment_ID FROM $wpdb->comments WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')" );
				if ( $query ) {
					foreach ( $query as $comment_id ) {
						wp_delete_comment( absint( $comment_id ), true );
					}
				}
				break;
			case 'db_cleanup_expired_transients':
				$query = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\__%%' AND option_value < UNIX_TIMESTAMP()" );
				if ( $query ) {
					foreach ( $query as $transient ) {
						$key = str_replace( '_transient_timeout_', '', $transient );
						delete_transient( $key );
					}
				}
				break;
			case 'db_cleanup_all_transients':
				$query = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%_transient_%'" );
				if ( $query ) {
					foreach ( $query as $transient ) {
						if ( strpos( $transient, '_site_transient_' ) !== false ) {
							$transient = str_replace( '_site_transient_', '', $transient );
							delete_site_transient( $transient );
							if ( wp_using_ext_object_cache() ) { // make sure transients deleted from db
								$option_timeout = '_site_transient_timeout_' . $transient;
								$option         = '_site_transient_' . $transient;
								$result         = delete_site_option( $option );

								if ( $result ) {
									delete_site_option( $option_timeout );
								}
							}
						} else {
							$transient = str_replace( '_transient_', '', $transient );
							delete_transient( $transient );
							if ( wp_using_ext_object_cache() ) { // make sure transients deleted from db
								$option_timeout = '_transient_timeout_' . $transient;
								$option         = '_transient_' . $transient;
								$result         = delete_option( $option );

								if ( $result ) {
									delete_option( $option_timeout );
								}
							}
						}
					}
				}
				break;
			case 'db_cleanup_optimize_tables':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT table_name, data_free FROM information_schema.tables WHERE table_schema = %s and Engine <> 'InnoDB' and data_free > 0", DB_NAME ) );
				if ( $query ) {
					foreach ( $query as $table ) {
						$wpdb->query( "OPTIMIZE TABLE $table->table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				}
				break;
		}

		return false;
	}

	/**
	 * Sometimes canceling a process is glitchy
	 * Try to cancell all items in the queue up to $max_attempt
	 */
	public function cancel_process() {
		$max_attempt = 5;
		$cancelled   = 0;
		while ( ! parent::is_queue_empty() ) {
			if ( $cancelled >= $max_attempt ) {
				break;
			}
			parent::cancel();
			$cancelled ++;
		}
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		\PoweredCache\Utils\log( sprintf( 'Optimization completed...' ) );

		parent::complete();

		if ( POWERED_CACHE_IS_NETWORK ) {
			delete_site_transient( DB_CLEANUP_COUNT_CACHE_KEY );
		} else {
			delete_transient( DB_CLEANUP_COUNT_CACHE_KEY );
		}
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return DatabaseOptimizer
	 * @since 2.0
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
