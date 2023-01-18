<?php
/**
 * Helpers for file optimizer
 *
 * @package PoweredCache\Optimizer
 */

namespace PoweredCache\Optimizer;

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found

/**
 * Class Helper
 */
class Helper {

	/**
	 * Check if given url is an internal URL
	 *
	 * @param string $test_url URL against website
	 * @param string $site_url Site URL
	 *
	 * @return bool
	 */
	public static function is_internal_url( $test_url, $site_url ) {
		$test_url_parsed = wp_parse_url( $test_url );
		$site_url_parsed = wp_parse_url( $site_url );

		if ( isset( $test_url_parsed['host'] )
			 && $test_url_parsed['host'] !== $site_url_parsed['host'] ) {
			return false;
		}

		if ( isset( $site_url_parsed['path'] )
			 && 0 !== strpos( $test_url_parsed['path'], $site_url_parsed['path'] )
			 && isset( $test_url_parsed['host'] ) // and if the URL of enqueued style is not relative
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get the realpath of given URL
	 *
	 * @param string $url      URL
	 * @param string $site_url Site URL
	 *
	 * @return bool|string
	 */
	public static function realpath( $url, $site_url ) {
		$url_path      = wp_parse_url( $url, PHP_URL_PATH );
		$site_url_path = wp_parse_url( $site_url, PHP_URL_PATH );
		// To avoid partial matches; subdir install at `/wp` would match `/wp-includes`
		$site_url_path = is_null( $site_url_path ) ? '/' : trailingslashit( $site_url_path );

		// If this is a subdirectory site, we need to strip off the subdir from the URL.
		// In a multisite install, the subdir is virtual and therefore not needed in the path.
		// In a single-site subdir install, the subdir is included in the ABSPATH and therefore ends up duplicated.
		if ( $site_url_path && '/' !== $site_url_path
			 && 0 === strpos( $url_path, $site_url_path ) ) {
			$url_path_without_subdir = preg_replace( '#^' . $site_url_path . '#', '', $url_path, 1 );

			return wp_normalize_path( realpath( ABSPATH . $url_path_without_subdir ) );
		}

		return wp_normalize_path( realpath( ABSPATH . $url_path ) );
	}

	/**
	 * Replace path in the buffer
	 *
	 * @param string $buf     buffer
	 * @param string $dirpath Directory path
	 *
	 * @return string|string[]|null
	 */
	public static function relative_path_replace( $buf, $dirpath ) {
		// url(relative/path/to/file) -> url(/absolute/and/not/relative/path/to/file)
		$buf = preg_replace(
			'/(:?\s*url\s*\()\s*(?:\'|")?\s*([^\/\'"\s\)](?:(?<!data:|http:|https:|[\(\'"]#|%23).)*)[\'"\s]*\)/isU',
			'$1' . ( '/' === $dirpath ? '/' : $dirpath . '/' ) . '$2)',
			$buf
		);

		return $buf;
	}

	/**
	 * Get the optimizer URL for given resource(s)
	 *
	 * @param string $path   The list of the optimized files
	 * @param bool   $minify minify flag
	 *
	 * @return string
	 */
	public static function get_optimized_url( $path, $minify ) {
		$optimizer_url = POWERED_CACHE_URL . 'includes/file-optimizer.php??';
		$optimized_url = $optimizer_url . $path . '&minify=' . absint( $minify );

		$optimized_url = esc_url_raw( apply_filters( 'powered_cache_fo_optimized_url', $optimized_url, $path, $minify ) );

		return $optimized_url;
	}

	/**
	 * Check if the given SRC excluded
	 *
	 * @param string $src URL
	 *
	 * @return bool
	 */
	public static function is_excluded_js( $src ) {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['excluded_js_files'], - 1, PREG_SPLIT_NO_EMPTY );
		$excluded_files = implode( '|', $excluded_files );

		if ( ! empty( $excluded_files ) && preg_match( '#(' . $excluded_files . ')#', $src ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the given SRC excluded
	 *
	 * @param string $src URL
	 *
	 * @return bool
	 */
	public static function is_excluded_css( $src ) {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['excluded_css_files'], - 1, PREG_SPLIT_NO_EMPTY );
		$excluded_files = implode( '|', $excluded_files );

		if ( ! empty( $excluded_files ) && preg_match( '#(' . $excluded_files . ')#', $src ) ) {
			return true;
		}

		return false;
	}

}
