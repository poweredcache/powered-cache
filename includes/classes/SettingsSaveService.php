<?php
/**
 * Settings save service.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Async\CachePreloader;
use PoweredCache\Async\CachePurger;
use const PoweredCache\Constants\PURGE_CACHE_CRON_NAME;

/**
 * Persists settings and runs post-save side effects.
 *
 * @since 4.0.0
 */
class SettingsSaveService {

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $repository;

	/**
	 * Whether settings use network storage.
	 *
	 * @var bool
	 */
	private $network_wide;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository|null $repository Settings repository.
	 * @param bool                    $network_wide Whether settings use network storage.
	 */
	public function __construct( ?SettingsRepository $repository = null, $network_wide = false ) {
		$this->network_wide = (bool) $network_wide;
		$this->repository   = null === $repository ? SettingsRepository::factory( $this->network_wide ) : $repository;
	}

	/**
	 * Create a service instance.
	 *
	 * @param SettingsRepository|null $repository Settings repository.
	 * @param bool                    $network_wide Whether settings use network storage.
	 *
	 * @return SettingsSaveService
	 */
	public static function factory( ?SettingsRepository $repository = null, $network_wide = false ) {
		return new self( $repository, $network_wide );
	}

	/**
	 * Save settings and run post-save hooks.
	 *
	 * @param array      $settings Settings payload.
	 * @param array|null $old_settings Previous settings.
	 *
	 * @return array Saved settings.
	 */
	public function save( array $settings, ?array $old_settings = null ) {
		if ( null === $old_settings ) {
			$old_settings = $this->repository->all();
		}

		$settings = SettingsSchema::enforce_editable( $settings, $old_settings );

		$this->repository->save( $settings );
		$settings = $this->repository->all();

		Config::factory()->save_configuration( $settings, $this->network_wide );

		$this->handle_side_effects( $old_settings, $settings );

		return $settings;
	}

	/**
	 * Run post-save side effects.
	 *
	 * @param array $old_settings Previous settings.
	 * @param array $settings Current settings.
	 */
	public function handle_side_effects( array $old_settings, array $settings ) {
		if ( isset( $settings['object_cache'], $old_settings['object_cache'] ) && $old_settings['object_cache'] !== $settings['object_cache'] ) {
			wp_cache_flush();
		}

		if ( ! empty( $old_settings['dev_mode'] ) && empty( $settings['dev_mode'] ) ) {
			wp_cache_flush();
		}

		if ( ! empty( $old_settings['enable_cache_preload'] ) && empty( $settings['enable_cache_preload'] ) ) {
			$this->cancel_preloading();
		}

		if ( empty( $old_settings['enable_cache_preload'] ) && ! empty( $settings['enable_cache_preload'] ) ) {
			$this->start_preloading();
		}

		if ( ! empty( $old_settings['async_cache_cleaning'] ) && empty( $settings['async_cache_cleaning'] ) ) {
			$this->cancel_async_cache_cleaning();
		}

		if ( ! empty( $old_settings['enable_page_cache'] ) && empty( $settings['enable_page_cache'] ) ) {
			\PoweredCache\Utils\clean_site_cache_dir();
		}

		if ( ! empty( $old_settings['rewrite_file_optimizer'] ) && empty( $settings['rewrite_file_optimizer'] ) ) {
			\PoweredCache\Utils\clean_site_cache_dir();
		}

		if ( isset( $old_settings['cache_timeout'], $settings['cache_timeout'] ) && $old_settings['cache_timeout'] !== $settings['cache_timeout'] ) {
			$timestamp = wp_next_scheduled( PURGE_CACHE_CRON_NAME );

			wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );
		}

		/**
		 * Fires after saving configurations.
		 *
		 * @hook  powered_cache_settings_saved
		 *
		 * @param {array} $old_settings Old settings.
		 * @param {array} $settings     New settings.
		 *
		 * @since 1.0
		 */
		do_action( 'powered_cache_settings_saved', $old_settings, $settings );
	}

	/**
	 * Cancel preloading process when preload is disabled.
	 */
	private function cancel_preloading() {
		\PoweredCache\Utils\log( 'Cancel preload process - Settings toggle' );
		$cache_preloader = CachePreloader::factory();
		$cache_preloader->delete_all();
	}

	/**
	 * Kick-start preloading process when preload is enabled.
	 */
	private function start_preloading() {
		\PoweredCache\Utils\log( 'Enable Preloader - Settings toggle' );
		Preloader::factory()->setup_preload_queue();
		Preloader::factory()->dispatch_preload_queue();
	}

	/**
	 * Cancel async cache purging processes.
	 */
	private function cancel_async_cache_cleaning() {
		\PoweredCache\Utils\log( 'Cancel CachePurger process' );
		$cache_purger = CachePurger::factory();
		$cache_purger->cancel_process();
	}
}
