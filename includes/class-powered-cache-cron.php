<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Powered_Cache_Cron {

	/**
	 * Placeholder constructer
	 */
	public function __construct() { }


	/**
	 * Setup actions and filters
	 *
	 * @since 1.0
	 */
	private function setup() {
		add_action( 'powered_cache_purge_cache', array( $this, 'purge_cache' ) );
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
	}

	/**
	 * Add custom cron schedule
	 *
	 * @param  array $schedules
	 *
	 * @since  1.0
	 * @return array $schedules
	 */
	public function cron_schedules( $schedules ) {
		$interval = powered_cache_get_option( 'cache_timeout' ) * 60;

		$schedules['powered_cache'] = array(
			'interval' => apply_filters( 'powered_cache_cache_purge_interval', $interval ),
			'display'  => esc_html__( 'Powered Cache Purge Interval', 'powered-cache' ),
		);

		return $schedules;
	}

	/**
	 * Unschedule events
	 *
	 * @since  1.0
	 */
	public function unschedule_events() {
		$timestamp = wp_next_scheduled( 'powered_cache_purge_cache' );

		wp_unschedule_event( $timestamp, 'powered_cache_purge_cache' );
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 1.0
	 */
	public function schedule_events() {

		$timestamp = wp_next_scheduled( 'powered_cache_purge_cache' );

		// we don't need when page cache off
		if ( true !== powered_cache_get_option( 'enable_page_caching' ) ) {
			wp_unschedule_event( $timestamp, 'powered_cache_purge_cache' );

			return;
		}

		// Expire cache never
		if ( intval( powered_cache_get_option( 'cache_timeout' ) ) === 0 ) {
			wp_unschedule_event( $timestamp, 'powered_cache_purge_cache' );

			return;
		}

		if ( ! $timestamp ) {
			wp_schedule_event( time(), 'powered_cache', 'powered_cache_purge_cache' );
		}
	}

	/**
	 * Initiate a cache purge
	 *
	 * @since 1.0
	 */
	public function purge_cache() {
		// Do nothing, caching is turned off
		if ( true !== powered_cache_get_option( 'enable_page_caching' ) ) {
			return;
		}

		$lifespan = powered_cache_get_option( 'cache_timeout' ) * 60;

		$expired_files = powered_cache_get_exprired_files( powered_cache_site_cache_dir(), $lifespan );

		foreach ( $expired_files as $file_path ) {
			@unlink( $file_path );
		}

		do_action( 'powered_cache_expired_files_deleted' );
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
