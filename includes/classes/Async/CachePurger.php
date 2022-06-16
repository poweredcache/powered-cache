<?php
/**
 * Background process for cache purging
 *
 * @package PoweredCache
 */

namespace PoweredCache\Async;

use \Powered_Cache_WP_Background_Process as Powered_Cache_WP_Background_Process;
use function PoweredCache\Utils\clean_page_cache_dir;
use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\delete_page_cache;
use function PoweredCache\Utils\powered_cache_flush;

/**
 * Class CachePurger
 */
class CachePurger extends Powered_Cache_WP_Background_Process {

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
	protected $action = 'powered_cache_purger';

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
			parent::cancel_process();
			$cancelled ++;
		}
	}

	/**
	 * Whether the process running or not
	 *
	 * @return bool
	 */
	public function is_process_running() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		return parent::is_process_running();
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
