<?php
/**
 * Action Scheduler process for cache purging.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use function PoweredCache\Utils\clean_page_cache_dir;
use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\delete_page_cache;
use function PoweredCache\Utils\powered_cache_flush;

/**
 * Class CachePurger
 */
class CachePurger extends ActionSchedulerProcess {

	const ACTION_SCHEDULER_HOOK  = 'powered_cache_action_scheduler_cache_purge';
	const ACTION_SCHEDULER_GROUP = 'powered-cache';

	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	protected $settings;

	/**
	 * string
	 *
	 * @var $action
	 */
	protected $action = self::ACTION_SCHEDULER_HOOK;

	/**
	 * Task
	 *
	 * Perform Preload Tasks
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$this->settings = \PoweredCache\Utils\get_settings();

		if ( empty( $item['call'] ) ) {
			return;
		}

		\PoweredCache\Utils\log( sprintf( 'Call: %s', $item['call'] ) );

		switch ( $item['call'] ) {
			case 'powered_cache_flush':
				powered_cache_flush();
				break;
			case 'clean_page_cache_dir':
				clean_page_cache_dir();
				break;
			case 'clean_site_cache_dir':
				clean_site_cache_dir();
				break;
			case 'clean_site_cache_for_language':
				if ( function_exists( '\PoweredCache\Compat\WPML\clean_site_cache_for_language' ) ) {
					\PoweredCache\Compat\WPML\clean_site_cache_for_language( $item['lang_code'] );
				}

				break;
			case 'delete_page_cache':
				$urls = $item['urls'];
				if ( ! empty( $urls ) ) {
					foreach ( $urls as $url ) {
						\PoweredCache\Utils\log( sprintf( 'delete_page_cache - URL: %s', $url ) );
						delete_page_cache( $url );
					}
				}
				break;
		}

		return false;
	}


	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		\PoweredCache\Utils\log( 'Async cache purge has been completed!' );
		parent::complete();
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return CachePurger
	 * @since 2.3
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
