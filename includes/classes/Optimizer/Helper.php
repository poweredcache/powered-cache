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
		$settings      = \PoweredCache\Utils\get_settings();
		$optimizer_url = POWERED_CACHE_URL . 'includes/file-optimizer.php??';
		if ( $settings['rewrite_file_optimizer'] ) {
			$optimizer_url = site_url() . '/_static/??';
		}

		$optimized_url = $optimizer_url . $path . '&minify=' . absint( $minify );

		$optimized_url = esc_url_raw( apply_filters( 'powered_cache_fo_optimized_url', $optimized_url, $path, $minify ) );

		return $optimized_url;
	}

	/**
	 * Get the optimizer relative path
	 * This is used for rewrite_file_optimizer option
	 *
	 * @return mixed|null
	 * @since 3.3.2
	 */
	public static function get_file_optimizer_relative_path() {
		// Calculate the relative path from the WordPress root to file-optimizer.php
		$relative_path = str_replace( ABSPATH, '', POWERED_CACHE_PATH . 'includes/file-optimizer.php' );
		$relative_path = ltrim( $relative_path, '/' ); // Remove leading slash
		/**
		 * Filters relative path for file optimizer
		 *
		 * @hook   powered_cache_fo_relative_path
		 *
		 * @param  {string} relative path of file-optimizer.php
		 *
		 * @return {string} New value.
		 * @since  3.3.2
		 */
		$relative_path = apply_filters( 'powered_cache_fo_relative_path', $relative_path );

		return $relative_path;
	}

	/**
	 * Whether an exclusion line uses wildcard syntax rather than regex syntax.
	 *
	 * @param string $pattern Pattern as entered by the user.
	 *
	 * @return bool True when the line contains `*` and no regex-only characters.
	 * @see   Helper::normalize_exclusion_pattern()
	 * @since 4.0
	 */
	public static function is_wildcard_exclusion_pattern( $pattern ) {
		$pattern = (string) $pattern;

		return false !== strpos( $pattern, '*' ) && ! preg_match( '/[()\[\]{}|^$+\\\\]/', $pattern );
	}

	/**
	 * Convert one exclusion line into a regex fragment.
	 *
	 * Exclusion lines are written in three styles, and all of them should match
	 * what the user typed:
	 *
	 * - Wildcards such as `*elementor*` or `/?preview=*`: used when the line has a
	 *   `*` and none of the regex-only characters ( ) [ ] { } | ^ $ + \
	 *   `*` matches any sequence and everything else, including `.` and `?`, is
	 *   literal. A `.*` written by regex users is kept as "any sequence".
	 * - Regular expressions such as `/product/(.*)`: kept as they are. When the
	 *   regex does not match its own text, the exact text is accepted as well.
	 *   That way a literal entry such as `app.js?ver=2`, where `?` would
	 *   otherwise act as a quantifier, still matches the URL it was copied from.
	 * - Lines that are not valid regex, such as `hero-(image`: matched as plain
	 *   text instead of breaking the combined pattern.
	 *
	 * The result has no delimiters. It is safe inside `#(...)#` for "contains"
	 * checks and inside `#^(...)$#` for the anchored Never Cache URL check.
	 *
	 * @param string $pattern Pattern as entered by the user.
	 *
	 * @return string Regex fragment, or an empty string for an empty line.
	 * @since 4.0
	 */
	public static function normalize_exclusion_pattern( $pattern ) {
		$pattern = trim( (string) $pattern );

		if ( '' === $pattern ) {
			return '';
		}

		if ( self::is_wildcard_exclusion_pattern( $pattern ) ) {
			$pattern = str_replace( '.*', '*', $pattern );
			$pattern = preg_replace( '/\*+/', '*', $pattern );

			return str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
		}

		if ( ! self::is_regex_fragment( $pattern ) ) {
			return preg_quote( $pattern, '#' );
		}

		// Most regex lines already match their own text, e.g. "jquery.min.js" or "/product/(.*)".
		if ( 1 === preg_match( '#^(?:' . $pattern . ')$#', $pattern ) ) {
			return $pattern;
		}

		return $pattern . '|' . preg_quote( $pattern, '#' );
	}

	/**
	 * Whether an exclusion line works the way it is written.
	 *
	 * True for wildcard lines and for lines that compile as a regex fragment.
	 * Other lines still match as plain text (see normalize_exclusion_pattern()),
	 * so this is only used to tell users that regex syntax was not applied.
	 *
	 * @param string $pattern Pattern as entered by the user.
	 *
	 * @return bool
	 * @since 4.0
	 */
	public static function is_valid_exclusion_pattern( $pattern ) {
		$pattern = trim( (string) $pattern );

		return self::is_wildcard_exclusion_pattern( $pattern ) || self::is_regex_fragment( $pattern );
	}

	/**
	 * Whether a fragment compiles when wrapped as `#(fragment)#`.
	 *
	 * @param string $fragment Regex fragment without delimiters.
	 *
	 * @return bool
	 */
	private static function is_regex_fragment( $fragment ) {
		static $results = array();

		if ( ! isset( $results[ $fragment ] ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning is the signal we are testing for.
			$results[ $fragment ] = false !== @preg_match( '#(' . $fragment . ')#', '' );
		}

		return $results[ $fragment ];
	}

	/**
	 * Normalize exclusion lines into unique regex fragments.
	 *
	 * @param array $patterns Patterns as entered by the user, usually one per settings line.
	 *
	 * @return string[] Regex fragments without delimiters. Empty lines are dropped.
	 * @see   Helper::normalize_exclusion_pattern()
	 * @since 4.0
	 */
	public static function normalize_exclusion_patterns( $patterns ) {
		$fragments = array();

		foreach ( (array) $patterns as $pattern ) {
			$fragment = self::normalize_exclusion_pattern( $pattern );

			if ( '' !== $fragment ) {
				$fragments[] = $fragment;
			}
		}

		return array_values( array_unique( $fragments ) );
	}

	/**
	 * Build one regex that matches any of the given exclusion patterns.
	 *
	 * Each line is normalized first, so a malformed line cannot break the rest of
	 * the list. An empty list returns an empty string instead of `#()#`, which
	 * would match every subject.
	 *
	 * @param array $patterns Pattern fragments, usually one per settings line.
	 *
	 * @return string Complete regex including delimiters, or an empty string when the list is empty.
	 * @since 4.0
	 */
	public static function build_exclusion_regex( $patterns ) {
		$fragments = self::normalize_exclusion_patterns( $patterns );

		if ( empty( $fragments ) ) {
			return '';
		}

		return '#(' . implode( '|', $fragments ) . ')#';
	}

	/**
	 * Whether the subject matches any of the given exclusion patterns.
	 *
	 * @param array  $patterns Pattern fragments.
	 * @param string $subject  URL or HTML tag to test.
	 *
	 * @return bool
	 * @since 4.0
	 */
	public static function matches_exclusion( $patterns, $subject ) {
		$regex = self::build_exclusion_regex( $patterns );

		return '' !== $regex && 1 === preg_match( $regex, (string) $subject );
	}

	/**
	 * Check if the given SRC excluded
	 *
	 * @param string $src URL
	 *
	 * @return bool
	 */
	public static function is_excluded_js( $src ) {
		return self::matches_exclusion( self::get_excluded_js(), $src );
	}

	/**
	 * Check if the given SRC excluded
	 *
	 * @param string $src URL
	 *
	 * @return bool
	 */
	public static function is_excluded_css( $src ) {
		return self::matches_exclusion( self::get_excluded_css(), $src );
	}

	/**
	 * Check if excluded or not from defer
	 *
	 * @param string $tag Script tag
	 *
	 * @return bool
	 */
	public static function is_defer_excluded( $tag ) {
		if ( false !== stripos( $tag, 'data-no-defer' ) ) {
			return true;
		}

		return self::matches_exclusion( self::get_defer_exclusions(), $tag );
	}

	/**
	 * Check if excluded or not from delay
	 *
	 * @param string $tag Script tag
	 *
	 * @return bool
	 */
	public static function is_delay_excluded( $tag ) {
		if ( false !== stripos( $tag, 'data-no-delay' ) ) {
			return true;
		}

		return self::matches_exclusion( self::get_delay_exclusions(), $tag );
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
