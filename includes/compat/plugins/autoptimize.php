<?php
/**
 * Compatability with Autoptimize
 *
 * @package PoweredCache\Compat
 */

namespace PoweredCache\Compat\Autoptimize;

use function PoweredCache\Utils\clean_site_cache_dir;
use function PoweredCache\Utils\delete_page_cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) && class_exists( '\autoptimizeCache' ) ) {

	add_action( 'powered_cache_flushed', __NAMESPACE__ . '\\delete_autoptimize_cache' );
	add_action( 'powered_cache_clean_site_cache_dir', __NAMESPACE__ . '\\delete_autoptimize_cache' );
	add_action( 'autoptimize_action_cachepurged', __NAMESPACE__ . '\\flush_site_cache' );

	/**
	 * Delete site cache directoryu on autoptimize purge
	 */
	function flush_site_cache() {
		clean_site_cache_dir();
	}

	/**
	 * Delete autoptimize cache when page cache flushed
	 */
	function delete_autoptimize_cache() {
		if ( function_exists( '\autoptimizeCache::clearall' ) ) {
			\autoptimizeCache::clearall();
		}
	}

	add_action( 'powered_cache_admin_page_before_file_optimization', __NAMESPACE__ . '\\add_notice' );

	/**
	 * Show a message in the file optimization section
	 */
	function add_notice() {
		?>
		<div class="sui-notice sui-notice-warning sui-padding">
			<div class="sui-notice-content">

				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php esc_html_e( 'It seems autoptimize is activated on your site. No worries, Powered Cache works perfectly fine with autoptimize but you cannot use file optimization options that conflic with autoptimize unless you deactivate it.', 'powered-cache' ); ?><br>
						<a class="sui-button sui-button-yellow" href="<?php echo esc_url( admin_url( '/options-general.php?page=autoptimize' ) ); ?>" style="margin-top: 10px;"><?php esc_html_e( 'Configure Autoptimize', 'powered-cache' ); ?></a>
					</p>
				</div>

			</div>
		</div>
		<?php
	}

	if ( 'on' === get_option( 'autoptimize_html' ) ) {
		add_filter( 'powered_cache_admin_page_fo_basic_settings_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'powered_cache_fo_disable_html_minify', '__return_true' );
	}

	if ( 'on' === get_option( 'autoptimize_css' ) ) {
		add_filter( 'powered_cache_admin_page_fo_css_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'powered_cache_fo_disable_css_minify', '__return_true' );
		add_filter( 'powered_cache_fo_disable_css_combine', '__return_true' );
	}


	if ( 'on' === get_option( 'autoptimize_js' ) ) {
		add_filter( 'powered_cache_admin_page_fo_js_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'powered_cache_fo_disable_js_minify', '__return_true' );
		add_filter( 'powered_cache_fo_disable_js_combine', '__return_true' );
	}

	/**
	 * Disable UI element
	 *
	 * @param string $classes CSS classes
	 *
	 * @return string
	 */
	function disable_ui_option( $classes ) {
		$classes .= ' sui-disabled';

		return $classes;
	}
}

