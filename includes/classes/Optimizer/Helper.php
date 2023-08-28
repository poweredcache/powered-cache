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
		$excluded_files = self::get_excluded_js();
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
		$excluded_files = self::get_excluded_css();
		$excluded_files = implode( '|', $excluded_files );

		if ( ! empty( $excluded_files ) && preg_match( '#(' . $excluded_files . ')#', $src ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if excluded or not from defer
	 *
	 * @param string $tag Script tag
	 *
	 * @return bool
	 */
	public static function is_defer_excluded( $tag ) {
		$excluded_files = self::get_defer_exclusions();
		$excluded_files = implode( '|', $excluded_files );

		if ( false !== stripos( $tag, 'data-no-defer' ) ) {
			return true;
		}

		if ( ! empty( $excluded_files ) && preg_match( '#(' . $excluded_files . ')#', $tag ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if excluded or not from delay
	 *
	 * @param string $tag Script tag
	 *
	 * @return bool
	 */
	public static function is_delay_excluded( $tag ) {
		$excluded_files = self::get_delay_exclusions();
		$excluded_files = implode( '|', $excluded_files );

		if ( false !== stripos( $tag, 'data-no-delay' ) ) {
			return true;
		}

		if ( ! empty( $excluded_files ) && preg_match( '#(' . $excluded_files . ')#', $tag ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get defer exclusion list
	 *
	 * @return array
	 * @since 3.2
	 */
	public static function get_defer_exclusions() {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['js_defer_exclusions'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filter the defer exclusions
		 *
		 * @hook   powered_cache_defer_exclusions
		 *
		 * @param  {array} $settings Excluded files
		 *
		 * @return {array} New value
		 * @since  3.2
		 */
		return (array) apply_filters( 'powered_cache_defer_exclusions', $excluded_files );
	}

	/**
	 * Get delay exclusion list
	 *
	 * @return array
	 * @since 3.2
	 */
	public static function get_delay_exclusions() {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['js_delay_exclusions'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filter the delay exclusions
		 *
		 * @hook   powered_cache_delay_exclusions
		 *
		 * @param  {array} $settings Excluded files
		 *
		 * @return {array} New value
		 * @since  3.2
		 */
		return (array) apply_filters( 'powered_cache_delay_exclusions', $excluded_files );
	}

	/**
	 * Get JS exclusion list
	 *
	 * @return array
	 * @since 3.2
	 */
	public static function get_excluded_js() {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['excluded_js_files'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filter the excluded JS files
		 *
		 * @hook   powered_cache_fo_excluded_js_files
		 *
		 * @param  {array} $settings Excluded files
		 *
		 * @return {array} New value
		 * @since  3.2
		 */
		return (array) apply_filters( 'powered_cache_fo_excluded_js_files', $excluded_files );
	}

	/**
	 * Get CSS exclusion list
	 *
	 * @return array
	 * @since 3.2
	 */
	public static function get_excluded_css() {
		$settings       = \PoweredCache\Utils\get_settings();
		$excluded_files = preg_split( '#(\r\n|\n|\r)#', $settings['excluded_css_files'], - 1, PREG_SPLIT_NO_EMPTY );

		/**
		 * Filter the excluded css files
		 *
		 * @hook   powered_cache_fo_excluded_css_files
		 *
		 * @param  {array} $settings Excluded files
		 *
		 * @return {array} New value
		 * @since  3.2
		 */
		return (array) apply_filters( 'powered_cache_fo_excluded_css_files', $excluded_files );
	}


}
