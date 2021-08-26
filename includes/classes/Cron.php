<?php
/**
 * Cron based functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\PURGE_CACHE_CRON_NAME;
use const PoweredCache\Constants\PURGE_FO_CRON_NAME;
use function PoweredCache\Utils\get_expired_files;
use function PoweredCache\Utils\is_dir_empty;
use function PoweredCache\Utils\site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cron
 */
class Cron {

	/**
	 * Placeholder constructor
	 */
	public function __construct() {
	}


	/**
	 * Setup actions and filters
	 *
	 * @since 1.0
	 */
	private function setup() {
		add_action( PURGE_CACHE_CRON_NAME, array( $this, 'purge_expired_page_cache' ) );
		add_action( PURGE_FO_CRON_NAME, array( $this, 'purge_expired_minify_files' ) );
		add_action( 'init', array( $this, 'schedule_page_cache_events' ) );
		add_action( 'init', array( $this, 'schedule_fo_cache_events' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
	}

	/**
	 * Add custom cron schedule
	 *
	 * @param array $schedules registered cron schedules
	 *
	 * @return array $schedules
	 * @since  1.0
	 */
	public function cron_schedules( $schedules ) {
		$settings = \PoweredCache\Utils\get_settings();

		$interval = $settings['cache_timeout'] * 60; // in seconds

		$schedules['powered_cache'] = [
			/**
			 * Filters page cache purge interval
			 *
			 * @hook   powered_cache_cache_purge_interval
			 *
			 * @param  {int} TTL in seconds.
			 *
			 * @return {int} New value.
			 * @since  2.0
			 */
			'interval' => apply_filters( 'powered_cache_cache_purge_interval', $interval ),
			'display'  => esc_html__( 'Powered Cache Purge Interval', 'powered-cache' ),
		];

		return $schedules;
	}

	/**
	 * Unschedule events
	 *
	 * @since  1.0
	 */
	public function unschedule_events() {
		$timestamp = wp_next_scheduled( PURGE_CACHE_CRON_NAME );
		wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );

		$timestamp = wp_next_scheduled( PURGE_FO_CRON_NAME );
		wp_unschedule_event( $timestamp, PURGE_FO_CRON_NAME );
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 1.0
	 */
	public function schedule_page_cache_events() {
		$settings = \PoweredCache\Utils\get_settings();

		$timestamp = wp_next_scheduled( PURGE_CACHE_CRON_NAME );

		// we don't need when page cache off
		if ( true !== $settings['enable_page_cache'] ) {
			wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );

			return;
		}

		// Expire cache never
		if ( intval( $settings['cache_timeout'] ) === 0 ) {
			wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );

			return;
		}

		if ( ! $timestamp ) {
			/**
			 * Only use `powered_cache` interval when TTL under the an hour
			 */
			if ( intval( $settings['cache_timeout'] ) < 60 ) {
				return wp_schedule_event( time(), 'powered_cache', PURGE_CACHE_CRON_NAME );
			}

			return wp_schedule_event( time(), 'hourly', PURGE_CACHE_CRON_NAME );
		}

	}


	/**
	 * Schedule a cron event keep only most recent min. files in cache
	 *
	 * @return bool|void|\WP_Error
	 */
	public function schedule_fo_cache_events() {
		$timestamp = wp_next_scheduled( PURGE_FO_CRON_NAME );

		if ( ! file_exists( POWERED_CACHE_FO_CACHE_DIR ) || is_dir_empty( POWERED_CACHE_FO_CACHE_DIR ) ) {
			return;
		}

		if ( ! $timestamp ) {
			return wp_schedule_event( time(), 'hourly', PURGE_FO_CRON_NAME );
		}
	}

	/**
	 * Initiate a cache purge
	 *
	 * @since 1.0
	 */
	public function purge_expired_page_cache() {
		$settings = \PoweredCache\Utils\get_settings();

		// Do nothing, caching is turned off
		if ( true !== $settings['enable_page_cache'] ) {
			return;
		}

		$lifespan = $settings['cache_timeout'] * 60; // TTL in seconds

		$expired_files = get_expired_files( site_cache_dir(), $lifespan );

		foreach ( $expired_files as $file_path ) {
			\PoweredCache\Utils\log( sprintf( 'Cleanup via cron: %s', $file_path ) );
			@unlink( $file_path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// cleanup dirs..
		foreach ( $expired_files as $file_path ) {
			$dir = dirname( $file_path );
			if ( file_exists( $dir ) && is_dir_empty( $dir ) ) {
				@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		/**
		 * Fire when expired files deleted
		 *
		 * @since 2.0 added $expired_files $arg
		 * @since 1.0
		 */
		do_action( 'powered_cache_expired_files_deleted', $expired_files );
	}

	/**
	 * Remove minify files
	 */
	public function purge_expired_minify_files() {
		/**
		 * Filters default TTL for minified assets.
		 *
		 * @hook   powered_cache_fo_cache_ttl
		 *
		 * @param  {int} TTL in seconds.
		 *
		 * @return {int} New value.
		 * @since  2.0
		 */
		$lifespan      = apply_filters( 'powered_cache_fo_cache_ttl', 60 * 60 ); // in seconds
		$expired_files = get_expired_files( POWERED_CACHE_FO_CACHE_DIR, $lifespan );
		foreach ( $expired_files as $file_path ) {
			\PoweredCache\Utils\log( sprintf( 'Cleanup via cron: %s', $file_path ) );
			@unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}


	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @return object
	 * @since  1.0
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
