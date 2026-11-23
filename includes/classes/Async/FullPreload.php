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

	private const RECOVERY_HOOK         = 'powered_cache_recover_full_preload';
	private const START_HOOK            = 'powered_cache_start_full_preload';
	private const STATE_OPTION          = 'powered_cache_full_preload_state';
	private const URLS_OPTION           = 'powered_cache_full_preload_urls';
	private const CHUNKS_PREFIX         = 'powered_cache_full_preload_chunk_';
	private const CHUNK_SIZE            = 50;
	private const STORED_CHUNK_SIZE     = 250;
	private const MAX_REQUESTS_PER_STEP = 4;
	private const PAGE_RETRY_DELAY      = 15;
	private const INLINE_DELAY          = 2;
	private const RECOVERY_TIME         = 60;

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
				if ( ! isset( $state['chunks'] ) ) {
					// Older versions saved one URL list. Rebuild using the saved run settings.
					$state = self::reset_source( $state );
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

				$delay = $state['due'] - microtime( true );
				if ( $delay <= 0 || $delay > self::INLINE_DELAY ) {
					return self::queue_step( $state );
				}

				return $state;
			}
		);
		if ( ! is_array( $next ) ) {
			return;
		}

		$delay = $next['due'] - microtime( true );
		if ( $delay > 0 && $delay <= self::INLINE_DELAY && ! self::has_due_actions() ) {
			// Keep the current runner useful only while nothing else is due.
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
		if ( empty( $settings['enable_sitemap_preload'] ) && ( 'sitemap' === $state['phase'] || $state['sitemap_url'] ) ) {
			$state['sitemaps'] = [];
			return self::finish_source( $state );
		}

		// Chunk options belong to the scheduler site, even when warming another site.
		$urls = [];
		if ( 'warm' === $state['phase'] && $state['chunk'] < $state['chunks'] ) {
			$urls = self::read_chunk( $state, $state['chunk'] );
			if ( ! isset( $urls[ $state['offset'] ] ) ) {
				return self::recover_source( $state );
			}
		} elseif ( 'terms' === $state['phase'] && ! $state['terms'] && has_filter( 'populate_preload_queue_urls' ) ) {
			// Preserve the whole-list filter. The usual path never reads the full list.
			for ( $index = 0; $index < $state['chunks']; ++$index ) {
				$chunk = self::read_chunk( $state, $index );
				if ( ! $chunk ) {
					return self::recover_source( $state );
				}
				array_push( $urls, ...$chunk );
			}
		}

		$switched = (int) get_current_blog_id() !== $site_id;
		if ( $switched ) {
			switch_to_blog( $site_id );
		}
		try {
			if ( 'warm' === $state['phase'] ) {
				return self::warm( $state, $urls, $settings );
			}
			list( $state, $found ) = self::discover( $state, $urls, $settings, ! $switched );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		return self::save_chunks( $state, $found );
	}

	/**
	 * Open a new set of chunks without overwriting the active checkpoint.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function reset_chunks( array $state ) {
		$state['chunk_slot'] = 1 - $state['chunk_slot'];
		$state['chunk_set']  = $state['step'] + 1;
		$state['chunks']     = 0;
		$state['chunk']      = 0;
		$state['offset']     = 0;
		$state['mobile']     = false;
		return $state;
	}

	/**
	 * Restart discovery for the current site or sitemap.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function reset_source( array $state ) {
		$fresh = self::new_state( $state['language'], $state['automatic'] );
		$fresh = array_replace( $fresh, array_intersect_key( $state, array_flip( [ 'id', 'step', 'site', 'blog_id', 'restart', 'has_warmed', 'chunk_slot', 'sitemaps', 'seen' ] ) ) );
		$fresh = self::reset_chunks( $fresh );
		if ( ! empty( $state['sitemap_url'] ) ) {
			array_unshift( $fresh['sitemaps'], $state['sitemap_url'] );
			$fresh['phase'] = 'sitemap';
		}
		return $fresh;
	}

	/**
	 * Rebuild missing work once, then advance to the next source.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function recover_source( array $state ) {
		if ( $state['rebuilding'] ) {
			log( 'A full preload URL chunk was unavailable after rebuilding. Moving to the next source.' );
			return self::finish_source( $state );
		}
		$state = self::reset_source( $state );
		$state['rebuilding'] = true;
		$state['due']        = time() + ActionSchedulerProcess::RETRY_DELAY;
		return $state;
	}

	/**
	 * Return a bounded chunk belonging to the current run and source.
	 *
	 * @param array $state Run state.
	 * @param int   $index Chunk index.
	 * @return array
	 */
	private static function read_chunk( array $state, $index ) {
		$stored = get_option( self::CHUNKS_PREFIX . $state['chunk_slot'] . '_' . $index, [] );
		if ( ( $stored['owner'] ?? null ) !== [ $state['id'], $state['site'], $state['chunk_set'] ] || ! is_array( $stored['urls'] ?? null ) ) {
			return [];
		}
		return $stored['urls'];
	}

	/**
	 * Write new chunks before publishing the checkpoint that refers to them.
	 *
	 * @param array $state Run state.
	 * @param array $urls Discovered URLs.
	 * @return array
	 * @throws \RuntimeException If a chunk cannot be saved.
	 */
	private static function save_chunks( array $state, array $urls ) {
		foreach ( array_chunk( $urls, self::STORED_CHUNK_SIZE ) as $chunk ) {
			$option = self::CHUNKS_PREFIX . $state['chunk_slot'] . '_' . $state['chunks'];
			$stored = [
				'owner' => [ $state['id'], $state['site'], $state['chunk_set'] ],
				'urls'  => $chunk,
			];
			if ( ! update_option( $option, $stored, false ) && get_option( $option ) !== $stored ) {
				throw new \RuntimeException( 'Powered Cache could not save a preload URL chunk.' );
			}
			++$state['chunks'];
		}
		return $state;
	}

	/**
	 * Discover a page of posts, terms or sitemap URLs.
	 *
	 * @param array $state Run state.
	 * @param array $urls All discovered local URLs when the compatibility filter is present.
	 * @param array $settings Current settings.
	 * @param bool  $include_sitemaps Whether to read this request's site's sitemaps.
	 * @return array
	 */
	private static function discover( array $state, array $urls, array $settings, $include_sitemaps ) {
		$preloader = Preloader::factory();
		$offset    = $state['offset'];
		$found     = [];

		switch ( $state['phase'] ) {
			case 'home':
				$found             = ! empty( $settings['preload_homepage'] ) ? $preloader->prepare_preload_home_urls() : [];
				$state['phase']    = 'posts';
				$state['sitemaps'] = $include_sitemaps && ! empty( $settings['enable_sitemap_preload'] )
					? array_values( array_unique( apply_filters( 'powered_cache_full_preload_sitemaps', [] ) ) ) : [];
				break;
			case 'posts':
				if ( ! empty( $settings['preload_public_posts'] ) ) {
					$found = $preloader->prepare_public_posts_page( $offset, self::CHUNK_SIZE, $state['post_id'] ?? 0 );
					if ( $found ) {
						$state['post_id'] = array_key_last( $found );
					}
				}
				$state['offset'] += count( $found );
				if ( count( $found ) < self::CHUNK_SIZE ) {
					$state['phase']  = 'terms';
					$state['offset'] = 0;
					$state['terms']  = ! empty( $settings['preload_public_tax'] ) ? $preloader->get_preload_taxonomies() : [];
				}
				break;
			case 'terms':
				if ( $state['terms'] ) {
					$tax   = reset( $state['terms'] );
					$found = $preloader->prepare_preload_taxonomy_urls( $tax, $offset, self::CHUNK_SIZE );
					$state['offset'] += count( $found );
					if ( count( $found ) < self::CHUNK_SIZE ) {
						array_shift( $state['terms'] );
						$state['offset'] = 0;
					}
					break;
				}
				$state['phase']    = 'warm';
				$state['offset']   = 0;
				if ( $include_sitemaps && ! empty( $settings['enable_sitemap_preload'] ) && ! has_filter( 'powered_cache_full_preload_sitemaps' ) && ! empty( $settings['enable_cache_preload'] ) && function_exists( '\\PoweredCachePremium\\Utils\\preload_sitemap' ) ) {
					\PoweredCachePremium\Utils\preload_sitemap();
				}
				if ( has_filter( 'populate_preload_queue_urls' ) ) {
					$state = self::reset_chunks( $state );
					$found = self::unique_urls( $preloader->filter_preload_urls( $urls, $state['language'] ) );
				}
				break;
			case 'sitemap':
				return self::discover_sitemap( $state );
		}

		return [ $state, has_filter( 'populate_preload_queue_urls' ) ? $found : self::unique_urls( $found ) ];
	}

	/**
	 * Read a single sitemap and switch back to warming its eligible URLs.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function discover_sitemap( array $state ) {
		if ( ! CachePreloader::factory()->should_continue() ) {
			$state['due'] = time() + ActionSchedulerProcess::RETRY_DELAY;
			return [ $state, [] ];
		}
		$sitemap = reset( $state['sitemaps'] );
		$result  = apply_filters( 'powered_cache_full_preload_read_sitemap', false, $sitemap, $state['attempt'] );
		if ( null === $result && $state['attempt'] < ActionSchedulerProcess::MAX_RETRIES ) {
			++$state['attempt'];
			$state['due'] = time() + ActionSchedulerProcess::RETRY_DELAY;
			return [ $state, [] ];
		}
		$state['attempt'] = 0;
		array_shift( $state['sitemaps'] );
		$state['seen'][ $sitemap ] = true;
		$pending = array_fill_keys( $state['sitemaps'], true );
		foreach ( $result['sitemaps'] ?? [] as $child ) {
			if ( ! isset( $state['seen'][ $child ] ) ) {
				$pending[ $child ] = true;
			}
		}
		$state['sitemaps']   = array_keys( $pending );
		$state                = self::reset_chunks( $state );
		$state['phase']       = 'warm';
		$state['sitemap_url'] = $sitemap;
		return [ $state, self::unique_urls( $result['urls'] ?? [] ) ];
	}

	/**
	 * Send due cache requests within a short action budget. Never exceed the configured rate.
	 *
	 * @param array $state Run state.
	 * @param array $urls URLs in the active chunk.
	 * @param array $settings Current settings.
	 * @return array
	 */
	private static function warm( array $state, array $urls, array $settings ) {
		$preloader = Preloader::factory();
		$mobile    = ! empty( $settings['cache_mobile'] ) && ! empty( $settings['cache_mobile_separate_file'] );
		$deadline  = microtime( true ) + self::INLINE_DELAY;
		$requests  = 0;
		for ( $i = 0; $i < self::CHUNK_SIZE && isset( $urls[ $state['offset'] ] ) && microtime( true ) < $deadline; ++$i ) {
			$url     = $urls[ $state['offset'] ];
			$variant = $mobile && $state['mobile'];
			if ( $preloader->should_preload_url( $url ) && ! is_url_cached( $url, $variant, $settings['gzip_compression'] ) ) {
				if ( $requests >= self::MAX_REQUESTS_PER_STEP || $state['due'] > microtime( true ) || microtime( true ) >= $deadline ) {
					break;
				}
				if ( ! CachePreloader::factory()->should_continue() ) {
					$state['due'] = time() + ActionSchedulerProcess::RETRY_DELAY;
					return $state;
				}

				++$requests;
				$response      = Preloader::preload_request( $url, $variant ? [ 'user-agent' => Preloader::mobile_user_agent() ] : [] );
				$state['due']  = microtime( true ) + Preloader::get_request_interval( absint( $settings['preload_request_interval'] ) * 1000000 ) / 1000000;
				if ( self::is_temporary_http_error( $response ) ) {
					// Back off across URLs if the origin keeps failing, then resume automatically.
					$backoff      = self::PAGE_RETRY_DELAY << min( $state['failed_urls'], 3 );
					$state['due'] = max( $state['due'], time() + $backoff );
					if ( ! $state['retry_url'] ) {
						$state['retry_url'] = true;
						return $state;
					}
					++$state['failed_urls'];
				} else {
					$state['failed_urls'] = 0;
				}
			}
			if ( $variant || ! $mobile ) {
				++$state['offset'];
			}
			$state['mobile']     = $mobile && ! $variant;
			$state['retry_url']  = false;
			$state['has_warmed'] = true;
		}

		if ( isset( $urls[ $state['offset'] ] ) ) {
			return $state;
		}
		if ( ++$state['chunk'] < $state['chunks'] ) {
			$state['offset'] = 0;
			return $state;
		}
		return self::finish_source( $state );
	}

	/**
	 * Check for an HTTP transport error or a status that can succeed on retry.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return bool
	 */
	private static function is_temporary_http_error( $response ) {
		if ( is_wp_error( $response ) ) {
			return true;
		}
		$code = (int) ( $response['response']['code'] ?? 0 );
		return 408 === $code || 429 === $code || $code >= 500;
	}

	/**
	 * Move from local pages to sitemaps or to the next site.
	 *
	 * @param array $state Run state.
	 * @return array
	 */
	private static function finish_source( array $state ) {
		if ( $state['sitemaps'] ) {
			$state['phase']       = 'sitemap';
			$state['sitemap_url'] = '';
			$state['rebuilding']  = false;
			return $state;
		}
		if ( ! POWERED_CACHE_IS_NETWORK ) {
			return [];
		}
		$next = self::new_state( $state['language'], $state['automatic'] );
		return array_replace( $next, array_intersect_key( $state, array_flip( [ 'id', 'step', 'restart', 'due' ] ) ), [ 'site' => $state['site'] + 1 ] );
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
			'post_id'    => 0,
			'chunk_slot' => 0,
			'chunk_set'  => 0,
			'chunks'     => 0,
			'chunk'      => 0,
			'sitemap_url' => '',
			'rebuilding' => false,
			'retry_url'  => false,
			'failed_urls' => 0,
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
	 * Yield the shared runner instead of waiting inline when other work is due.
	 *
	 * @return bool
	 */
	private static function has_due_actions() {
		return function_exists( 'as_get_scheduled_actions' ) && (bool) as_get_scheduled_actions(
			[
				'status'   => 'pending',
				'date'     => time(),
				'per_page' => 1,
				'orderby'  => 'none',
			],
			'ids'
		);
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
		delete_option( 'powered_cache_full_preload_window' );
		self::clear_chunks();
		wp_clear_scheduled_hook( self::RECOVERY_HOOK );
	}

	/**
	 * Remove both reusable chunk slots and invalidate their non-autoloaded option caches.
	 */
	private static function clear_chunks() {
		global $wpdb;

		$prefix = $wpdb->esc_like( self::CHUNKS_PREFIX ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) );
		if ( $names && false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) ) ) {
			wp_cache_delete_multiple( $names, 'options' );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
