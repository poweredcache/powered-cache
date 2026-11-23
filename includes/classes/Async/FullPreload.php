<?php
/**
 * Resumable full-cache preload.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use PoweredCache\Preloader;
use function PoweredCache\Utils\get_settings as get_cache_settings;
use function PoweredCache\Utils\is_url_cached;
use function PoweredCache\Utils\log;

/**
 * Keep one full-preload step eligible for the shared runner at a time.
 */
class FullPreload {

	public const STEP_HOOK  = 'powered_cache_full_preload_step';
	public const DAILY_HOOK = 'powered_cache_daily_preload';

	private const RECOVERY_HOOK = 'powered_cache_recover_full_preload';
	private const START_HOOK    = 'powered_cache_start_full_preload';
	private const STATE_OPTION  = 'powered_cache_full_preload_state';
	private const URLS_OPTION   = 'powered_cache_full_preload_urls';
	private const WINDOW_OPTION = 'powered_cache_full_preload_window';
	private const CHUNK_SIZE    = 50;
	private const INLINE_DELAY  = 2;
	private const RECOVERY_TIME = 60;

	/**
	 * Register workers on every request, including when automatic preload is off.
	 */
	public static function setup() {
		add_action( self::STEP_HOOK, [ self::class, 'run_step' ], 10, 2 );
		add_action( self::DAILY_HOOK, [ self::class, 'run_daily' ] );
		add_action( self::RECOVERY_HOOK, [ self::class, 'recover' ] );
		add_action( self::START_HOOK, [ self::class, 'start' ], 10, 2 );
		add_action( 'powered_cache_settings_saved', [ self::class, 'settings_saved' ], 10, 2 );
	}

	/**
	 * Start or wake a full run.
	 *
	 * @param string $language WPML language selection.
	 * @param bool   $automatic Whether a cache event triggered the run.
	 * @return bool
	 */
	public static function start( $language = '', $automatic = false ) {
		return self::on_scheduler_site(
			static function ( $switched ) use ( $language, $automatic ) {
				$started = self::locked(
					static function () use ( $language, $automatic, $switched ) {
						if ( ! self::can_run( get_cache_settings(), $automatic ) ) {
							return false;
						}

						$state = get_option( self::STATE_OPTION, [] );
						if ( ! $state || ( ! $automatic && ( $state['automatic'] || $state['language'] !== $language ) ) ) {
							$previous = $state;
							$state = self::new_state( $language, $automatic );
							if ( ! update_option( self::STATE_OPTION, $state, false ) ) {
								return false;
							}
							if ( $previous && ! $switched ) {
								self::cancel_step( $previous );
							}
						} elseif ( $automatic && empty( $state['restart'] ) && ( $state['site'] || $state['has_warmed'] ) ) {
							// A purge can invalidate URLs the current run already warmed.
							$state['restart'] = true;
							update_option( self::STATE_OPTION, $state, false );
						}

						// The network's main-site cron starts the first step when the caller is on another site.
						$recovery = self::schedule_recovery( $switched ? 0 : self::RECOVERY_TIME );
						return ( ! $switched && self::queue_step( $state ) ) || $recovery;
					}
				);
				if ( null === $started ) {
					$args = [ $language, $automatic ];
					return (bool) wp_next_scheduled( self::START_HOOK, $args ) || wp_schedule_single_event( time() + 5, self::START_HOOK, $args );
				}
				return $started;
			}
		);
	}

	/**
	 * Apply full-preload mode and schedule changes after settings have been saved.
	 *
	 * @param array $previous Previous settings.
	 * @param array $current Saved settings.
	 */
	public static function settings_saved( array $previous, array $current ) {
		$old_mode = $previous['preload_full_mode'] ?? 'automatic';
		$new_mode = $current['preload_full_mode'] ?? 'automatic';
		if ( ! empty( $previous['enable_page_cache'] ) && empty( $current['enable_page_cache'] ) ) {
			self::cancel();
		} elseif ( self::can_run( $previous, true ) && ! self::can_run( $current, true ) ) {
			self::cancel( true );
		}
		if ( ( 'scheduled' === $old_mode || 'scheduled' === $new_mode ) && (
			$old_mode !== $new_mode ||
			( $previous['preload_schedule_time'] ?? '02:00' ) !== ( $current['preload_schedule_time'] ?? '02:00' ) ||
			empty( $previous['enable_page_cache'] ) !== empty( $current['enable_page_cache'] )
		) ) {
			self::refresh_schedule( $current );
		}
		if ( 'automatic' !== $old_mode && 'automatic' === $new_mode && self::can_run( $current, true ) ) {
			self::start( '', true );
		}
	}

	/**
	 * Cancel the current run and its recovery task.
	 *
	 * @param bool $automatic_only Only cancel a run started by a cache event.
	 */
	public static function cancel( $automatic_only = false ) {
		self::on_scheduler_site(
			static function ( $switched ) use ( $automatic_only ) {
				self::locked(
					static function () use ( $automatic_only, $switched ) {
						$state = get_option( self::STATE_OPTION, [] );
						if ( ! $automatic_only || ! empty( $state['automatic'] ) ) {
							self::clear_run();
							if ( $state && ! $switched ) {
								self::cancel_step( $state );
							}
						}
						return true;
					}
				);
			}
		);
	}

	/**
	 * Remove full-preload state and cron events when the plugin is disabled.
	 */
	public static function deactivate() {
		self::on_scheduler_site(
			static function () {
				self::cancel();
				wp_clear_scheduled_hook( self::DAILY_HOOK );
				wp_unschedule_hook( self::START_HOOK );
			}
		);
	}

	/**
	 * Update the next daily event on a schedule or page-cache change.
	 *
	 * @param array $settings Saved settings.
	 */
	public static function refresh_schedule( array $settings ) {
		self::on_scheduler_site(
			static function () use ( $settings ) {
				wp_clear_scheduled_hook( self::DAILY_HOOK );
				self::schedule_daily( $settings );
			}
		);
	}

	/**
	 * Restore a missing daily event after activation or a failed schedule write.
	 */
	public static function ensure_schedule() {
		if ( ( ! POWERED_CACHE_IS_NETWORK || (int) get_current_blog_id() === (int) get_main_site_id() ) && ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			self::schedule_daily( get_cache_settings() );
		}
	}

	/**
	 * Start today's run and calculate tomorrow's time in the site timezone.
	 */
	public static function run_daily() {
		$settings = get_cache_settings();
		if ( 'scheduled' !== ( $settings['preload_full_mode'] ?? 'automatic' ) || empty( $settings['enable_page_cache'] ) ) {
			return;
		}
		self::schedule_daily( $settings );
		self::start();
	}

	/**
	 * Repair a missing continuation, including after a PHP or queue write failure.
	 */
	public static function recover() {
		self::locked(
			static function () {
				$state = get_option( self::STATE_OPTION, [] );
				if ( ! $state ) {
					return true;
				}
				if ( ! self::can_run( get_cache_settings(), $state['automatic'] ) ) {
					self::clear_run();
					return true;
				}
				self::schedule_recovery();
				return self::queue_step( $state );
			}
		);
		// The cron event is consumed even when another worker currently holds the lock.
		if ( get_option( self::STATE_OPTION, [] ) ) {
			self::schedule_recovery();
		}
	}

	/**
	 * Process one discovery or warming step.
	 *
	 * @param string $id Run ID.
	 * @param int    $step Step number.
	 */
	public static function run_step( $id, $step ) {
		$next = self::locked(
			static function () use ( $id, $step ) {
				$state = get_option( self::STATE_OPTION, [] );
				if ( ! $state || $state['id'] !== $id || $state['step'] !== $step ) {
					return true;
				}
				$settings = get_cache_settings();
				if ( ! self::can_run( $settings, $state['automatic'] ) ) {
					self::clear_run();
					return true;
				}
				self::schedule_recovery();
				if ( $state['due'] > microtime( true ) ) {
					return true;
				}
				$state = self::advance( $state, $settings );
				if ( ! $state ) {
					$previous = get_option( self::STATE_OPTION, [] );
					if ( ! empty( $previous['restart'] ) && self::can_run( $settings, true ) ) {
						$state = self::new_state( '', true );
					} else {
						self::clear_run();
						return true;
					}
				}

				++$state['step'];
				if ( ! update_option( self::STATE_OPTION, $state, false ) ) {
					return false;
				}

				return $state;
			}
		);
		if ( ! is_array( $next ) ) {
			return;
		}

		$delay = $next['due'] - microtime( true );
		if ( $delay > 0 && $delay <= self::INLINE_DELAY ) {
			// Preserve the default request spacing while keeping this action short.
			usleep( (int) ceil( $delay * 1000000 ) );
		}
		self::locked(
			static function () use ( $next ) {
				$current = get_option( self::STATE_OPTION, [] );
				return $current && $current['id'] === $next['id'] && $current['step'] === $next['step'] && self::queue_step( $current );
			}
		);
	}

	/**
	 * Advance local discovery or sitemap warming for one site.
	 *
	 * @param array $state Run state.
	 * @param array $settings Current settings.
	 * @return array
	 * @throws \RuntimeException If a URL snapshot cannot be saved.
	 */
	private static function advance( array $state, array $settings ) {
		$site_id = $state['blog_id'];
		if ( ! $site_id ) {
			$site_id = POWERED_CACHE_IS_NETWORK ? self::site_at_offset( $state['site'] ) : (int) get_current_blog_id();
		}
		if ( ! $site_id ) {
			return [];
		}

		$state['blog_id'] = $site_id;
		if ( empty( $settings['enable_sitemap_preload'] ) && ( 'sitemap' === $state['phase'] || $state['seen'] ) ) {
			$state['phase']    = 'warm';
			$state['offset']   = $state['url_count'];
			$state['sitemaps'] = [];
		}

		$urls     = 'warm' === $state['phase'] ? self::warm_window( $state ) : self::stored_urls( $state );
		$switched = (int) get_current_blog_id() !== $site_id;
		if ( $switched ) {
			switch_to_blog( $site_id );
		}
		try {
			if ( 'warm' === $state['phase'] ) {
				$state = self::warm( $state, $urls, $settings );
			} else {
				list( $state, $urls ) = self::discover( $state, $urls, $settings, ! $switched );
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
		if ( ! $state ) {
			return [];
		}

		if ( isset( $state['save_urls'] ) ) {
			unset( $state['save_urls'] );
			// Keep the old URL list until the new checkpoint commits.
			$state['urls_slot'] = 1 - $state['urls_slot'];
			$state['url_count'] = count( $urls );
			$state['snapshot']  = $state['step'];
			$option             = self::URLS_OPTION . $state['urls_slot'];
			$stored             = [
				'id' => $state['id'],
				'site' => $state['site'],
				'urls' => $urls,
			];
			if ( get_option( $option, [] ) !== $stored && ! update_option( $option, $stored, false ) ) {
				throw new \RuntimeException( 'Powered Cache could not save full preload URLs.' );
			}
		}
		return $state;
	}

	/**
	 * Read the active URL snapshot for this site.
	 *
	 * @param array $state Current checkpoint.
	 * @return array
	 */
	private static function stored_urls( array $state ) {
		$stored = get_option( self::URLS_OPTION . $state['urls_slot'], [] );
		return ( $stored['id'] ?? null ) === $state['id'] && $stored['site'] === $state['site'] ? $stored['urls'] : [];
	}

	/**
	 * Read the current window without loading the full URL list for each request.
	 *
	 * @param array $state Current checkpoint.
	 * @return array
	 */
	private static function warm_window( array $state ) {
		if ( $state['offset'] >= $state['url_count'] ) {
			return [];
		}
		$offset  = intdiv( $state['offset'], self::CHUNK_SIZE ) * self::CHUNK_SIZE;
		$key     = [ $state['id'], $state['snapshot'], $offset ];
		$current = get_option( self::WINDOW_OPTION, [] );
		if ( ( $current['key'] ?? null ) === $key ) {
			return $current['urls'];
		}
		$urls = array_slice( self::stored_urls( $state ), $offset, self::CHUNK_SIZE, true );
		update_option(
			self::WINDOW_OPTION,
			[
				'key' => $key,
				'urls' => $urls,
			],
			false
		);
		return $urls;
	}

	/**
	 * Discover a page of posts or one taxonomy per step. Apply whole-list filters once.
	 *
	 * @param array $state Run state.
	 * @param array $urls URLs collected for this site.
	 * @param array $settings Current settings.
	 * @param bool  $include_sitemaps Whether to read this request's site's sitemaps.
	 * @return array
	 */
	private static function discover( array $state, array $urls, array $settings, $include_sitemaps ) {
		$preloader = Preloader::factory();
		$offset    = $state['offset'];
		$state['save_urls'] = true;

		switch ( $state['phase'] ) {
			case 'home':
				$urls           = ! empty( $settings['preload_homepage'] ) ? $preloader->prepare_preload_home_urls() : [];
				$state['phase'] = 'posts';
				break;
			case 'posts':
				$page = ! empty( $settings['preload_public_posts'] ) ? $preloader->prepare_public_posts_urls( $offset, self::CHUNK_SIZE ) : [];
				$urls = array_merge( $urls, $page );
				$state['offset'] += count( $page );
				if ( count( $page ) < self::CHUNK_SIZE ) {
					$state['phase']  = 'terms';
					$state['offset'] = 0;
					$state['terms']  = ! empty( $settings['preload_public_tax'] ) ? $preloader->get_preload_taxonomies() : [];
				}
				break;
			case 'terms':
				if ( $state['terms'] ) {
					$tax  = reset( $state['terms'] );
					$page = $preloader->prepare_preload_taxonomy_urls( $tax, $offset, self::CHUNK_SIZE );
					$urls = array_merge( $urls, $page );
					$state['offset'] += count( $page );
					if ( count( $page ) < self::CHUNK_SIZE ) {
						array_shift( $state['terms'] );
						$state['offset'] = 0;
					}
					break;
				}
				$state['phase']    = 'warm';
				$state['offset']   = 0;
				$state['sitemaps'] = $include_sitemaps && ! empty( $settings['enable_sitemap_preload'] )
					? array_values( array_unique( apply_filters( 'powered_cache_full_preload_sitemaps', [] ) ) ) : [];
				if ( $include_sitemaps && ! empty( $settings['enable_sitemap_preload'] ) && ! has_filter( 'powered_cache_full_preload_sitemaps' ) && ! empty( $settings['enable_cache_preload'] ) && function_exists( '\\PoweredCachePremium\\Utils\\preload_sitemap' ) ) {
					\PoweredCachePremium\Utils\preload_sitemap();
				}
				$urls = self::unique_urls( $preloader->filter_preload_urls( $urls, $state['language'] ) );
				break;
			case 'sitemap':
				list( $state, $urls ) = self::discover_sitemap( $state );
				break;
		}

		return [ $state, $urls ];
	}

	/**
	 * Read a single sitemap and switch back to warming its eligible URLs.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function discover_sitemap( array $state ) {
		$sitemap = reset( $state['sitemaps'] );
		$result  = apply_filters( 'powered_cache_full_preload_read_sitemap', false, $sitemap );
		if ( null === $result && $state['attempt'] < ActionSchedulerProcess::MAX_RETRIES ) {
			++$state['attempt'];
			$state['due'] = time() + ActionSchedulerProcess::RETRY_DELAY;
			return [ $state, [] ];
		}
		$state['attempt'] = 0;
		array_shift( $state['sitemaps'] );
		$state['seen'][ $sitemap ] = true;
		foreach ( $result['sitemaps'] ?? [] as $child ) {
			if ( ! isset( $state['seen'][ $child ] ) && ! in_array( $child, $state['sitemaps'], true ) ) {
				$state['sitemaps'][] = $child;
			}
		}
		$state['phase']  = 'warm';
		$state['offset'] = 0;
		return [ $state, self::unique_urls( $result['urls'] ?? [] ) ];
	}

	/**
	 * Check a bounded number of URLs and send at most one uncached request.
	 *
	 * @param array $state Run state.
	 * @param array $urls URLs for the current site.
	 * @param array $settings Current settings.
	 * @return array
	 */
	private static function warm( array $state, array $urls, array $settings ) {
		$preloader = Preloader::factory();
		$mobile    = ! empty( $settings['cache_mobile'] ) && ! empty( $settings['cache_mobile_separate_file'] );
		for ( $i = 0; $i < self::CHUNK_SIZE && isset( $urls[ $state['offset'] ] ); ++$i ) {
			$url           = $urls[ $state['offset'] ];
			$variant       = $mobile && $state['mobile'];
			$needs_request = $preloader->should_preload_url( $url ) && ! is_url_cached( $url, $variant, $settings['gzip_compression'] );
			if ( $needs_request && ! CachePreloader::factory()->should_continue() ) {
				$state['due'] = time() + ActionSchedulerProcess::RETRY_DELAY;
				return $state;
			}
			if ( $variant || ! $mobile ) {
				++$state['offset'];
			}
			$state['mobile']     = $mobile && ! $variant;
			$state['has_warmed'] = true;
			if ( ! $needs_request ) {
				continue;
			}
			$response      = Preloader::preload_request( $url, $variant ? [ 'user-agent' => Preloader::mobile_user_agent() ] : [] );
			$state['due']  = microtime( true ) + Preloader::get_request_interval( absint( $settings['preload_request_interval'] ) * 1000000 ) / 1000000;
			if ( is_wp_error( $response ) ) {
				log( sprintf( 'Full preload HTTP request failed for %s: %s', $url, $response->get_error_message() ) );
			}
			return $state;
		}

		if ( $state['offset'] < $state['url_count'] ) {
			return $state;
		}
		if ( ! empty( $settings['enable_sitemap_preload'] ) && $state['sitemaps'] ) {
			$state['phase'] = 'sitemap';
			return $state;
		}
		if ( ! POWERED_CACHE_IS_NETWORK ) {
			return [];
		}
		$next = self::new_state( $state['language'], $state['automatic'] );
		return array_merge( $next, array_intersect_key( $state, array_flip( [ 'id', 'step', 'restart' ] ) ), [ 'site' => $state['site'] + 1 ] );
	}

	/**
	 * Create an empty run checkpoint.
	 *
	 * @param string $language Selected language.
	 * @param bool   $automatic Whether the run was started automatically.
	 * @return array
	 */
	private static function new_state( $language, $automatic ) {
		return [
			'id'         => wp_generate_uuid4(),
			'step'       => 0,
			'language'   => $language,
			'automatic'  => $automatic,
			'restart'    => false,
			'phase'      => 'home',
			'site'       => 0,
			'blog_id'    => 0,
			'offset'     => 0,
			'urls_slot'  => 0,
			'url_count'  => 0,
			'snapshot'   => 0,
			'terms'      => [],
			'sitemaps'   => [],
			'seen'       => [],
			'mobile'     => false,
			'has_warmed' => false,
			'due'        => 0,
			'attempt'    => 0,
		];
	}

	/**
	 * Remove empty, non-string, and duplicate URLs after discovery.
	 *
	 * @param array $urls Discovered URLs.
	 * @return array
	 */
	private static function unique_urls( array $urls ) {
		return array_values( array_unique( array_filter( $urls, 'is_string' ) ) );
	}

	/**
	 * Find the next site without collecting the full network in memory.
	 *
	 * @param int $offset Site offset.
	 * @return int
	 */
	private static function site_at_offset( $offset ) {
		$sites = get_sites(
			[
				'fields' => 'ids',
				'number' => 1,
				'offset' => $offset,
				'orderby' => 'id',
				'order' => 'ASC',
			]
		);
		return (int) reset( $sites );
	}

	/**
	 * Check whether a request is eligible in the selected mode.
	 *
	 * @param array $settings Current settings.
	 * @param bool  $automatic Whether the request was caused by a cache event.
	 * @return bool
	 */
	private static function can_run( array $settings, $automatic ) {
		return ! empty( $settings['enable_page_cache'] ) && ( ! $automatic || ( ! empty( $settings['enable_cache_preload'] ) && 'automatic' === ( $settings['preload_full_mode'] ?? 'automatic' ) ) );
	}

	/**
	 * Keep one pending action for this checkpoint.
	 *
	 * @param array $state Checkpoint to schedule.
	 * @return bool
	 */
	private static function queue_step( array $state ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ( class_exists( '\ActionScheduler', false ) && ! \ActionScheduler::is_initialized() ) ) {
			return false;
		}

		$args     = [ $state['id'], $state['step'] ];
		$group    = ActionSchedulerProcess::ACTION_SCHEDULER_GROUP;
		$priority = CachePreloader::preload_action_priority();
		if ( as_has_scheduled_action( self::STEP_HOOK, $args, $group ) ) {
			return true;
		}
		if ( $state['due'] > microtime( true ) ) {
			return (bool) as_schedule_single_action( (int) ceil( $state['due'] ), self::STEP_HOOK, $args, $group, false, $priority );
		}
		return (bool) as_enqueue_async_action( self::STEP_HOOK, $args, $group, false, $priority );
	}

	/**
	 * Remove the pending action for a superseded checkpoint.
	 *
	 * @param array $state Checkpoint being cancelled.
	 */
	private static function cancel_step( array $state ) {
		if ( function_exists( 'as_unschedule_all_actions' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( self::STEP_HOOK, [ $state['id'], $state['step'] ], ActionSchedulerProcess::ACTION_SCHEDULER_GROUP );
		}
	}

	/**
	 * Schedule a cron wakeup to recover a missing step.
	 *
	 * @param int $delay Delay in seconds.
	 * @return bool
	 */
	private static function schedule_recovery( $delay = self::RECOVERY_TIME ) {
		return (bool) wp_next_scheduled( self::RECOVERY_HOOK ) || wp_schedule_single_event( time() + $delay, self::RECOVERY_HOOK );
	}

	/**
	 * Schedule the next local occurrence, including across DST changes.
	 *
	 * @param array $settings Current settings.
	 */
	private static function schedule_daily( array $settings ) {
		if ( 'scheduled' !== ( $settings['preload_full_mode'] ?? 'automatic' ) || empty( $settings['enable_page_cache'] ) ) {
			return;
		}

		$clock = $settings['preload_schedule_time'] ?? '02:00';
		if ( ! is_string( $clock ) || ! preg_match( '/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $clock ) ) {
			$clock = '02:00';
		}
		$now   = new \DateTimeImmutable( 'now', wp_timezone() );
		$next  = new \DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $clock, $now->getTimezone() );
		if ( $next <= $now ) {
			$next = new \DateTimeImmutable( $now->modify( '+1 day' )->format( 'Y-m-d' ) . ' ' . $clock, $now->getTimezone() );
		}
		wp_schedule_single_event( $next->getTimestamp(), self::DAILY_HOOK );
	}

	/**
	 * Delete stored work and prevent old callbacks from reviving it.
	 */
	private static function clear_run() {
		delete_option( self::STATE_OPTION );
		delete_option( self::URLS_OPTION . '0' );
		delete_option( self::URLS_OPTION . '1' );
		delete_option( self::WINDOW_OPTION );
		wp_clear_scheduled_hook( self::RECOVERY_HOOK );
	}

	/**
	 * Serialize run starts, worker steps and cancellations across PHP processes.
	 *
	 * @param callable $callback Work to run under the lock.
	 * @return mixed Null when another connection holds the lock.
	 */
	private static function locked( callable $callback ) {
		global $wpdb;

		$key = 'pc-preload-' . md5( $wpdb->dbname . ':' . $wpdb->options );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ) ) {
			return null;
		}
		try {
			return $callback();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Place network-wide runs and their cron events on the main site.
	 *
	 * @param callable $callback Scheduler operation.
	 * @return mixed
	 */
	private static function on_scheduler_site( callable $callback ) {
		$main     = POWERED_CACHE_IS_NETWORK ? (int) get_main_site_id() : (int) get_current_blog_id();
		$switched = (int) get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		try {
			return $callback( $switched );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}
}
