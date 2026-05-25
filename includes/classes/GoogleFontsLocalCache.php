<?php
/**
 * Local Google Fonts cache.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use function PoweredCache\Utils\get_cache_dir;

/**
 * Caches Google Fonts stylesheets and font files locally.
 *
 * @since 4.0.0
 */
class GoogleFontsLocalCache {

	const CSS_MAX_BYTES  = 200000;
	const FONT_MAX_BYTES = 3000000;

	/**
	 * Cache a Google Fonts stylesheet and return the local URL.
	 *
	 * @param string $stylesheet_url Remote stylesheet URL.
	 *
	 * @return string
	 */
	public function get_stylesheet_url( $stylesheet_url ) {
		$stylesheet_url = $this->normalize_url( $stylesheet_url );

		if ( '' === $stylesheet_url || ! $this->is_allowed_stylesheet_url( $stylesheet_url ) ) {
			return $stylesheet_url;
		}

		$cache_dir = $this->cache_dir();
		$cache_url = $this->cache_url();

		if ( '' === $cache_dir || '' === $cache_url || ! $this->ensure_dir( $cache_dir ) ) {
			return $stylesheet_url;
		}

		$file = $cache_dir . md5( $stylesheet_url ) . '.css';
		$url  = $cache_url . basename( $file );

		if ( $this->is_fresh( $file, $this->ttl() ) ) {
			return $url;
		}

		$css = $this->remote_get_body( $stylesheet_url, self::CSS_MAX_BYTES );

		if ( '' === $css ) {
			return file_exists( $file ) ? $url : $stylesheet_url;
		}

		$css = $this->rewrite_font_urls( $css, $stylesheet_url );

		if ( ! $this->write_file( $file, $css ) ) {
			return $stylesheet_url;
		}

		return $url;
	}

	/**
	 * Replace Google Fonts stylesheet references in rendered HTML.
	 *
	 * @param string $html Rendered HTML.
	 *
	 * @return string
	 */
	public function replace_stylesheet_urls( $html ) {
		if ( '' === $html || false === stripos( $html, 'fonts.' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/\shref=(["\'])([^"\']*(?:fonts\.googleapis\.com|fonts\.bunny\.net)\/css[^"\']*)\1/i',
			function ( $matches ) {
				return ' href=' . $matches[1] . $this->get_stylesheet_url( $matches[2] ) . $matches[1];
			},
			$html
		);
	}

	/**
	 * Return the cache directory.
	 *
	 * @return string
	 */
	public function cache_dir() {
		return trailingslashit( get_cache_dir() ) . 'powered-cache/google-fonts/';
	}

	/**
	 * Return the web URL for the cache directory.
	 *
	 * @return string
	 */
	public function cache_url() {
		if ( ! defined( 'WP_CONTENT_DIR' ) || ! function_exists( 'content_url' ) ) {
			return '';
		}

		$cache_dir   = $this->normalize_path( $this->cache_dir() );
		$content_dir = trailingslashit( $this->normalize_path( WP_CONTENT_DIR ) );

		if ( 0 !== strpos( $cache_dir, $content_dir ) ) {
			return '';
		}

		$relative_path = ltrim( substr( $cache_dir, strlen( $content_dir ) ), '/' );

		return trailingslashit( content_url( $relative_path ) );
	}

	/**
	 * Rewrite remote font URLs inside a stylesheet to local cached URLs.
	 *
	 * @param string $css           Stylesheet content.
	 * @param string $stylesheet_url Stylesheet URL.
	 *
	 * @return string
	 */
	public function rewrite_font_urls( $css, $stylesheet_url ) {
		return preg_replace_callback(
			'/url\((["\']?)([^)\'"]+)\1\)/i',
			function ( $matches ) use ( $stylesheet_url ) {
				$font_url = $this->absolute_url( trim( $matches[2] ), $stylesheet_url );

				if ( '' === $font_url || ! $this->is_allowed_font_url( $font_url ) ) {
					return $matches[0];
				}

				$local_url = $this->cache_font( $font_url );

				if ( '' === $local_url ) {
					return $matches[0];
				}

				return 'url(' . $matches[1] . $local_url . $matches[1] . ')';
			},
			$css
		);
	}

	/**
	 * Cache a remote font file.
	 *
	 * @param string $font_url Remote font URL.
	 *
	 * @return string
	 */
	private function cache_font( $font_url ) {
		$cache_dir = $this->cache_dir();
		$cache_url = $this->cache_url();

		if ( '' === $cache_dir || '' === $cache_url || ! $this->ensure_dir( $cache_dir ) ) {
			return '';
		}

		$extension = $this->font_extension( $font_url );
		$file      = $cache_dir . md5( $font_url ) . '.' . $extension;
		$url       = $cache_url . basename( $file );

		if ( $this->is_fresh( $file, $this->ttl() ) ) {
			return $url;
		}

		$font = $this->remote_get_body( $font_url, self::FONT_MAX_BYTES );

		if ( '' === $font || ! $this->write_file( $file, $font ) ) {
			return file_exists( $file ) ? $url : '';
		}

		return $url;
	}

	/**
	 * Fetch a remote body from an allowed URL.
	 *
	 * @param string $url       Remote URL.
	 * @param int    $max_bytes Maximum response body size.
	 *
	 * @return string
	 */
	private function remote_get_body( $url, $max_bytes ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return '';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				'user-agent'  => 'Mozilla/5.0 (compatible; Powered Cache Google Fonts Cache)',
			)
		);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return '';
		}

		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;

		if ( 200 > $code || 300 <= $code ) {
			return '';
		}

		$body = function_exists( 'wp_remote_retrieve_body' ) ? wp_remote_retrieve_body( $response ) : '';

		if ( ! is_string( $body ) || '' === $body || strlen( $body ) > $max_bytes ) {
			return '';
		}

		return $body;
	}

	/**
	 * Check whether a stylesheet URL is allowed.
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool
	 */
	private function is_allowed_stylesheet_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );
		$path = parse_url( $url, PHP_URL_PATH );

		return in_array( $host, array( 'fonts.googleapis.com', 'fonts.bunny.net' ), true )
			&& is_string( $path )
			&& 0 === strpos( $path, '/css' );
	}

	/**
	 * Check whether a font URL is allowed.
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool
	 */
	private function is_allowed_font_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );

		return in_array( $host, array( 'fonts.gstatic.com', 'fonts.bunny.net' ), true )
			&& in_array( $this->font_extension( $url ), array( 'woff2', 'woff', 'ttf', 'otf', 'eot' ), true );
	}

	/**
	 * Return a safe font extension from a URL.
	 *
	 * @param string $url Font URL.
	 *
	 * @return string
	 */
	private function font_extension( $url ) {
		$path      = (string) parse_url( $url, PHP_URL_PATH );
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $extension, array( 'woff2', 'woff', 'ttf', 'otf', 'eot' ), true ) ? $extension : 'woff2';
	}

	/**
	 * Convert a possibly relative URL to an absolute URL.
	 *
	 * @param string $url  URL found in CSS.
	 * @param string $base Base stylesheet URL.
	 *
	 * @return string
	 */
	private function absolute_url( $url, $base ) {
		if ( '' === $url || 0 === strpos( $url, 'data:' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return 'https:' . $url;
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		$scheme = parse_url( $base, PHP_URL_SCHEME );
		$host   = parse_url( $base, PHP_URL_HOST );

		if ( ! $scheme || ! $host ) {
			return '';
		}

		return $scheme . '://' . $host . '/' . ltrim( $url, '/' );
	}

	/**
	 * Normalize a URL for safe use.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function normalize_url( $url ) {
		$url = trim( (string) $url );

		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}

	/**
	 * Normalize path separators.
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	private function normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}

	/**
	 * Ensure a cache directory exists.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return bool
	 */
	private function ensure_dir( $dir ) {
		if ( is_dir( $dir ) ) {
			return is_writable( $dir );
		}

		if ( function_exists( 'wp_mkdir_p' ) ) {
			return wp_mkdir_p( $dir );
		}

		return mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	}

	/**
	 * Write cache content to disk.
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 *
	 * @return bool
	 */
	private function write_file( $file, $content ) {
		return false !== file_put_contents( $file, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Check whether a cache file is fresh.
	 *
	 * @param string $file File path.
	 * @param int    $ttl  Time to live in seconds.
	 *
	 * @return bool
	 */
	private function is_fresh( $file, $ttl ) {
		return file_exists( $file ) && ( time() - filemtime( $file ) ) < $ttl;
	}

	/**
	 * Return cache TTL.
	 *
	 * @return int
	 */
	private function ttl() {
		$month = defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 2592000;

		/**
		 * Filter local Google Fonts cache TTL.
		 *
		 * @hook powered_cache_google_fonts_cache_ttl
		 *
		 * @param {int} $ttl Cache lifetime in seconds.
		 *
		 * @return {int} New value.
		 * @since 4.0.0
		 */
		return max( 3600, (int) apply_filters( 'powered_cache_google_fonts_cache_ttl', $month ) );
	}
}
