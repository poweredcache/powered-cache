<?php
/**
 * CDN functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use \DOMDocument as DOMDocument;
use function PoweredCache\Utils\cdn_addresses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDN
 */
class CDN {

	/**
	 * Return an instance of the current class
	 *
	 * @return CDN
	 * @since 1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks
	 *
	 * @since 1.0
	 */
	public function setup() {
		$settings = \PoweredCache\Utils\get_settings();

		if ( ! $settings['enable_cdn'] ) {
			return;
		}

		add_action( 'plugins_loaded', [ $this, 'cdn_setup' ] );

	}

	/**
	 * Setup CDN
	 */
	public function cdn_setup() {
		/**
		 * Filters CDN integration
		 *
		 * @hook   powered_cache_cdn_disable
		 *
		 * @param  {boolean} False by default.
		 *
		 * @return {boolean} New value.
		 * @since  2.1
		 */
		$disable_cdn = apply_filters( 'powered_cache_cdn_disable', false );

		if ( $disable_cdn ) {
			return;
		}

		add_action( 'setup_theme', [ $this, 'start_buffer' ] );
		add_filter( 'powered_cache_fo_optimized_url', array( $this, 'cdn_optimizer_url' ), 9999, 2 );

		/**
		 * Fires after setup CDN hooks.
		 *
		 * @hook  powered_cache_cdn_setup
		 *
		 * @since 1.0
		 */
		do_action( 'powered_cache_cdn_setup' );
	}

	/**
	 * Start output buffering
	 *
	 * @since 2.2
	 */
	public function start_buffer() {
		ob_start( 'self::end_buffering' );
	}

	/**
	 * Replace origin URLs with CDN.
	 *
	 * @param string $contents Output buffer.
	 * @param int    $phase    Bitmask of PHP_OUTPUT_HANDLER_* constants.
	 *
	 * @return string|string[]|null
	 * @since 2.2
	 */
	private static function end_buffering( $contents, $phase ) {
		if ( $phase & PHP_OUTPUT_HANDLER_FINAL || $phase & PHP_OUTPUT_HANDLER_END ) {

			if ( ! self::skip_cdn_integration() ) {
				$rewritten_contents = self::rewriter( $contents );

				return $rewritten_contents;
			}
		}

		return $contents;
	}

	/**
	 * Whether integrate or not integrate CDN.
	 *
	 * @return bool
	 * @since 2.2
	 */
	private static function skip_cdn_integration() {
		// check request method
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		// check conditional tags
		if ( is_admin() || is_trackback() || is_robots() || is_preview() ) {
			return true;
		}

		return false;
	}

	/**
	 * Rewrite contents
	 *
	 * @param string $contents HTML Output.
	 *
	 * @return string|string[]|null
	 * @since 2.2
	 */
	public static function rewriter( $contents ) {
		// check rewrite requirements
		if ( ! is_string( $contents ) || empty( self::get_file_extensions() ) ) {
			return $contents;
		}

		$included_file_extensions_regex = quotemeta( implode( '|', self::get_file_extensions() ) );
		$urls_regex                     = '#(?:(?:[\"\'\s=>,;]|url\()\K|^)[^\"\'\s(=>,;]+(' . $included_file_extensions_regex . ')(\?[^\/?\\\"\'\s)>,]+)?(?:(?=\/?[?\\\"\'\s)>,&])|$)#i';
		$rewritten_contents             = preg_replace_callback( $urls_regex, 'self::rewrite_url', $contents );

		return $rewritten_contents;
	}

	/**
	 * Rewrite the matched url.
	 *
	 * @param array $matches Matched part of content.
	 *
	 * @return mixed|string
	 * @since 2.2
	 */
	private static function rewrite_url( $matches ) {
		$file_url      = $matches[0];
		$site_hostname = ( ! empty( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : wp_parse_url( home_url(), PHP_URL_HOST ); // phpcs:ignore

		/**
		 * Filters site hostname(s).
		 *
		 * @hook   powered_cache_cdn_site_hostnames
		 *
		 * @param  {array} Site hostnames for CDN replacement.
		 *
		 * @return {array} New value.
		 *
		 * @since  2.2
		 */
		$site_hostnames = (array) apply_filters( 'powered_cache_cdn_site_hostnames', array( $site_hostname ) );

		$zone         = self::get_zone_by_ext( $matches[1] );
		$cdn_hostname = self::get_best_possible_cdn_host( $zone );
		if ( empty( $cdn_hostname ) ) {
			return $file_url;
		}

		// if excluded or already using CDN hostname
		if ( self::is_excluded( $file_url ) || false !== stripos( $file_url, $cdn_hostname ) ) {
			return $file_url;
		}

		// rewrite full URL (e.g. https://www.example.com/wp..., https:\/\/www.example.com\/wp..., or //www.example.com/wp...)
		foreach ( $site_hostnames as $site_hostname ) {
			if ( stripos( $file_url, '//' . $site_hostname ) !== false || stripos( $file_url, '\/\/' . $site_hostname ) !== false ) {
				return substr_replace( $file_url, $cdn_hostname, stripos( $file_url, $site_hostname ), strlen( $site_hostname ) );
			}
		}

		/**
		 * Filters whether relative urls needs to rewritten or not
		 *
		 * @hook   powered_cache_cdn_rewrite_relative_urls
		 *
		 * @param  {boolean} true to automatic update.
		 *
		 * @return {boolean} New value.
		 *
		 * @since  2.2
		 */
		if ( apply_filters( 'powered_cache_cdn_rewrite_relative_urls', true ) ) { // rewrite relative URLs hook
			// rewrite relative URL (e.g. /wp-content/uploads/example.jpg)
			if ( strpos( $file_url, '//' ) !== 0 && strpos( $file_url, '/' ) === 0 ) {
				return '//' . $cdn_hostname . $file_url;
			}

			// rewrite escaped relative URL (e.g. \/wp-content\/uploads\/example.jpg)
			if ( strpos( $file_url, '\/\/' ) !== 0 && strpos( $file_url, '\/' ) === 0 ) {
				return '\/\/' . $cdn_hostname . $file_url;
			}
		}

		return $file_url;
	}

	/**
	 * Check whether given url excluded or not.
	 *
	 * @param string $file_url File URL.
	 *
	 * @return bool
	 * @since 2.2
	 */
	private static function is_excluded( $file_url ) {
		$settings = \PoweredCache\Utils\get_settings();

		// rejected file
		if ( ! empty( $settings['cdn_rejected_files'] ) ) {
			$cdn_rejected_files = preg_split( '#(\r\n|\r|\n)#', $settings['cdn_rejected_files'], - 1, PREG_SPLIT_NO_EMPTY );
			$cdn_rejected_files = implode( '|', $cdn_rejected_files );

			if ( preg_match( '#(' . $cdn_rejected_files . ')#', $file_url ) ) {
				return true;
			}
		}

		// don't replace for base64 encoded images
		if ( false !== strpos( $file_url, 'data:image' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * CDN hostname replacement for optimized URLs
	 *
	 * @param string $optimized_url Optimized assets URL
	 * @param string $path          rel path of the files
	 *
	 * @return mixed
	 */
	public function cdn_optimizer_url( $optimized_url, $path ) {
		if ( '-' === $path[0] ) {
			$path = @gzuncompress( base64_decode( substr( $path, 1 ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		$zone = 'all';
		if ( $path && false !== strpos( $path, '.css' ) ) {
			$zone = 'css';
		} elseif ( $path && false !== strpos( $path, '.js' ) ) {
			$zone = 'js';
		}

		$cdn_hostname = self::get_best_possible_cdn_host( $zone );

		if ( empty( $cdn_hostname ) ) {
			return $optimized_url;
		}

		$cdn_url = '//' . $cdn_hostname;

		$optimized_url = str_replace( home_url(), $cdn_url, $optimized_url );

		return $optimized_url;
	}


	/**
	 * try to catch best cdn address to given zone
	 *
	 * @param string $zone keys of Powered_Cache_Admin_Helper::cdn_zones
	 *
	 * @return mixed  string | false
	 */
	public static function get_best_possible_cdn_host( $zone = 'all' ) {
		global $powered_cache_cdn_addresses;

		if ( ! isset( $powered_cache_cdn_addresses ) ) {
			$powered_cache_cdn_addresses = cdn_addresses();
		}

		if ( isset( $powered_cache_cdn_addresses[ $zone ] ) && is_array( $powered_cache_cdn_addresses[ $zone ] ) ) {
			// if we have multiple host for the same resource get randomly
			$random_key = array_rand( $powered_cache_cdn_addresses[ $zone ] );

			return $powered_cache_cdn_addresses[ $zone ][ $random_key ];
		}

		// fallback to primary host
		if ( isset( $powered_cache_cdn_addresses['all'] ) && is_array( $powered_cache_cdn_addresses['all'] ) ) {
			$random_key = array_rand( $powered_cache_cdn_addresses['all'] );

			return $powered_cache_cdn_addresses['all'][ $random_key ];
		}

		return false;
	}

	/**
	 * Get CDN zone by given extension.
	 *
	 * @param string $ext File extensions. Eg: .jpg, .gif, .mp3...
	 *
	 * @return string
	 * @since 2.2
	 */
	private static function get_zone_by_ext( $ext ) {
		$zone = 'all';

		/* documented in get_file_extensions */
		$image_extensions = apply_filters( 'powered_cache_cdn_image_extensions', array( 'jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico', 'webp', 'avif', 'svg' ) );
		$image_extensions = array_map(
			function ( $ext ) {
				return '.' . $ext;
			},
			$image_extensions
		);

		if ( in_array( $ext, $image_extensions, true ) ) {
			$zone = 'image';
		} elseif ( '.css' === $ext ) {
			$zone = 'css';
		} elseif ( '.js' === $ext ) {
			$zone = 'js';
		}

		return $zone;
	}

	/**
	 * Get the list of supported file extensions for CDN integration.
	 *
	 * @return array|mixed|void
	 * @since 2.2
	 */
	public static function get_file_extensions() {
		/**
		 * Filters supported image extensions.
		 *
		 * @hook       powered_cache_cdn_image_extensions
		 *
		 * @param      {array} $image_extensions Supported image extensions.
		 *
		 * @return     {array} New value.
		 * @deprecated since 2.2. Use powered_cache_cdn_extensions instead.
		 * @since      1.0
		 */
		$image_extensions = apply_filters( 'powered_cache_cdn_image_extensions', array( 'jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico', 'webp', 'avif', 'svg' ) );

		$extensions = [ 'css', 'js', 'pdf', 'mp3', 'mp4', 'woff2', 'woff', 'ttf', 'otf' ];

		$file_extensions = array_map(
			function ( $ext ) {
				return '.' . $ext;
			},
			array_merge( $image_extensions, $extensions )
		);

		/**
		 * Filters supported file extensions.
		 *
		 * @hook       powered_cache_cdn_extensions
		 *
		 * @param      {array} $file_extensions Supported file extensions.
		 *
		 * @return     {array} New value.
		 * @since      2.2
		 */
		$file_extensions = apply_filters( 'powered_cache_cdn_extensions', $file_extensions );

		return $file_extensions;
	}

}

