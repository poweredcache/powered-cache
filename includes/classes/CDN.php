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

		/**
		 * Filters CDN integration
		 *
		 * @hook   powered_cache_cdn_disable
		 *
		 * @param  {boolean} False by default.
		 *
		 * @return {array} New value.
		 * @since  2.1
		 */
		$disable_cdn = apply_filters( 'powered_cache_cdn_disable', false );

		if ( $disable_cdn ) {
			return;
		}

		add_filter( 'wp_get_attachment_url', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'stylesheet_uri', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'smilies_src', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'bp_core_fetch_avatar_url', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'style_loader_src', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'script_loader_src', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'powered_cache_cdn_assets', array( $this, 'cdn_url' ), 9999 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'srcset_url' ), 9999 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'attachment_image_src' ), 9999 );
		add_filter( 'the_content', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'post_thumbnail_html', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'get_avatar', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'bp_core_fetch_avatar', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'widget_text', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'media_image', array( $this, 'cdn_images' ), 9999 );
		add_filter( 'powered_cache_page_caching_buffer', array( $this, 'cdn_images' ), 9999 );
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

		$cdn_url = self::get_best_possible_cdn_host( $zone );

		$optimized_url = str_replace( home_url(), $cdn_url, $optimized_url );

		return $optimized_url;
	}

	/**
	 * Replace CDN url for srcset
	 *
	 * @param array $sources Source files
	 *
	 * @return mixed
	 * @since 1.2
	 */
	public function srcset_url( $sources ) {
		foreach ( $sources as $key => $source ) {
			$sources[ $key ]['url'] = $this->cdn_url( $source['url'] );
		}

		return $sources;
	}

	/**
	 * Replace given url with the CDN host
	 *
	 * @param string $url URL
	 *
	 * @return string
	 */
	public function cdn_url( $url ) {
		if ( is_admin() || is_preview() ) {
			return $url;
		}

		return $this->maybe_cdn_replace( $url );
	}


	/**
	 * Replace URL for wp_get_attachment_image_src function
	 *
	 * @param array|false $image Either array with src, width & height, icon src, or false
	 *
	 * @return array $image
	 * @since 1.2.4
	 */
	public function attachment_image_src( $image ) {
		if ( is_admin() || is_preview() ) {
			return $image;
		}

		if ( ! (bool) $image ) {
			return $image;
		}

		$cdn_url = self::get_best_possible_cdn_host( 'image' );
		// no host found
		if ( false === $cdn_url ) {
			return $image;
		}

		$image[0] = str_replace( home_url(), $cdn_url, $image[0] );

		return $image;
	}

	/**
	 * Replace images with CDN host
	 *
	 * @param string $content Content
	 *
	 * @return mixed
	 */
	public function cdn_images( $content ) {
		if ( is_admin() || is_preview() || empty( $content ) ) {
			return $content;
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}

		$document = new DOMDocument();
		@$document->loadHTML( $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$images = $document->getElementsByTagName( 'img' );

		$img_url = array();
		foreach ( $images as $img ) {
			if ( $img->hasAttribute( 'src' ) ) {
				$img_url[] = $img->getAttribute( 'src' );
			}

			if ( $img->hasAttribute( 'srcset' ) ) {
				$imgset = explode( ',', $img->getAttribute( 'srcset' ) );
				foreach ( $imgset as $src_item ) {
					$imgsrc = explode( ' ', trim( $src_item ) );
					// first item is url, second width
					$img_url[] = $imgsrc[0];
				}
			}
		}

		$img_url = array_unique( $img_url );
		$cdn_url = array();

		foreach ( $img_url as $replace_url ) {
			$cdn_url[] = $this->maybe_cdn_replace( $replace_url );
		}

		return str_replace( $img_url, $cdn_url, $content );
	}


	/**
	 * this method decide to url replacement.
	 * rejected files & external resources will be ignored
	 *
	 * @param string $url URL
	 *
	 * @return string cdn url
	 * @since 1.0
	 */
	public static function maybe_cdn_replace( $url ) {
		$settings = \PoweredCache\Utils\get_settings();

		// rejected file
		if ( ! empty( $settings['cdn_rejected_files'] ) ) {
			$cdn_rejected_files = preg_split( '#(\r\n|\r|\n)#', $settings['cdn_rejected_files'], - 1, PREG_SPLIT_NO_EMPTY );
			$cdn_rejected_files = implode( '|', $cdn_rejected_files );

			if ( preg_match( '#(' . $cdn_rejected_files . ')#', $url ) ) {
				return $url;
			}
		}

		// external resource
		if ( false === strpos( $url, home_url() ) ) {
			return $url;
		}

		// don't replace for base64 encoded images
		if ( false !== strpos( $url, 'data:image' ) ) {
			return $url;
		}

		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$ext      = explode( '.', $url_path );
		$ext      = strtolower( end( $ext ) );

		$zone = 'all';

		/**
		 * Filters supported image extensions.
		 *
		 * @hook   powered_cache_cdn_image_extensions
		 *
		 * @param  {array} $image_extensions Supported image extensions.
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		$image_extensions = apply_filters( 'powered_cache_cdn_image_extensions', array( 'jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico', 'webp' ) );

		if ( in_array( $ext, $image_extensions, true ) ) {
			$zone = 'image';
		} elseif ( 'css' === $ext ) {
			$zone = 'css';
		} elseif ( 'js' === $ext ) {
			$zone = 'js';
		}

		$cdn_url = self::get_best_possible_cdn_host( $zone );

		// no host found
		if ( false === $cdn_url ) {
			return $url;
		}

		return str_replace( home_url(), $cdn_url, $url );
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

}

