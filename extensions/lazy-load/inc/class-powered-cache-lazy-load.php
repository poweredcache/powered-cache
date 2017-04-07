<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Lazy_Load' ) ):

	class Powered_Cache_Lazy_Load {

		function __construct() {
			add_action( 'wp', array( $this, 'init' ), 9999 ); // run this as late as possible
		}

		/**
		 * Initialize the setup
		 */
		public function init() {

			/* We do not touch the feeds */
			if ( is_admin() || is_feed() || is_preview() ) {
				return;
			}


			self::compat();
			do_action( 'powered_cache_lazy_load_compat' );


			$enabled = apply_filters( 'powered_cache_lazy_load_enabled', true );

			if ( $enabled ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

				$this->_setup_filtering();
			}
		}


		/**
		 * Load compat script
		 */
		protected function compat() {

			$dirname = trailingslashit( POWERED_CACHE_LAZY_LOAD_DIR ) . 'compat';
			$d       = dir( $dirname );
			if ( $d ) {
				while ( $entry = $d->read() ) {
					if ( '.' != $entry[0] && '.php' == substr( $entry, - 4 ) ) {
						include trailingslashit( $dirname ) . $entry;
					}
				}
			}
		}


		/**
		 * Enqueue scripts
		 */
		public function enqueue_scripts() {

			wp_enqueue_script( 'PCLL', plugins_url( 'lazy-load/assets/js/lazy-load.min.js', POWERED_CACHE_LAZY_LOAD_DIR ), null, false, true );


			$threshold = apply_filters( 'powered_cache_lazy_load_threshold', 200 );

			if ( 200 !== (int) $threshold ) {
				wp_localize_script( 'PCLL', 'PCLL_options', array( 'threshold' => $threshold ) );
			}
		}

		/**
		 * Set up filtering for certain content
		 */
		protected function _setup_filtering() {

			if ( true === apply_filters( 'powered_cache_lazy_load_images', powered_cache_get_extension_option( 'lazyload', 'image', true ) ) ) {
				add_filter( 'powered_cache_lazy_load_filter', array( __CLASS__, 'filter_images' ) );
			}

			if ( true === apply_filters( 'powered_cache_lazy_load_iframes', powered_cache_get_extension_option( 'lazyload', 'iframe', true ) ) ) {
				add_filter( 'powered_cache_lazy_load_filter', array( __CLASS__, 'filter_iframes' ) );
			}

			if ( true === apply_filters( 'powered_cache_lazy_load_post_content', powered_cache_get_extension_option( 'lazyload', 'post_content', true ) ) ) {
				add_filter( 'the_content', array( __CLASS__, 'filter' ), 200 );
			}

			if ( true === apply_filters( 'powered_cache_lazy_load_widget_text', powered_cache_get_extension_option( 'lazyload', 'widget_text', true ) ) ) {
				add_filter( 'widget_text', array( __CLASS__, 'filter' ), 200 );
			}

			if ( true === apply_filters( 'powered_cache_lazy_load_post_thumbnail', powered_cache_get_extension_option( 'lazyload', 'post_thumbnail', true ) ) ) {
				add_filter( 'post_thumbnail_html', array( __CLASS__, 'filter' ), 200 );
			}

			if ( true === apply_filters( 'powered_cache_lazy_load_avatar', powered_cache_get_extension_option( 'lazyload', 'avatar', true ) ) ) {
				add_filter( 'get_avatar', array( __CLASS__, 'filter' ), 200 );
			}

			add_filter( 'powered_cache_lazy_load_html', array( __CLASS__, 'filter' ) );
		}

		/**
		 * Filter HTML content. Replace supported content with placeholders.
		 *
		 * @param string $content The HTML string to filter
		 *
		 * @return string The filtered HTML string
		 */
		public static function filter( $content ) {

			// Last chance to bail out before running the filter
			$run_filter = apply_filters( 'powered_cache_lazy_load_run_filter', true );
			if ( ! $run_filter ) {
				return $content;
			}

			/**
			 * Filter the content
			 *
			 * @param string $content The HTML string to filter
			 */
			$content = apply_filters( 'powered_cache_lazy_load_filter', $content );

			return $content;
		}


		/**
		 * Replace images with placeholders in the content
		 *
		 * @param string $content The HTML to do the filtering on
		 *
		 * @return string The HTML with the images replaced
		 */
		public static function filter_images( $content ) {

			$placeholder_url = apply_filters( 'powered_cache_lazy_load_placeholder_url', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

			$match_content = self::_get_content_haystack( $content );

			$matches = array();
			preg_match_all( '/<img[\s\r\n]+.*?>/is', $match_content, $matches );

			$search  = array();
			$replace = array();

			foreach ( $matches[0] as $imgHTML ) {

				// don't to the replacement if the image is a data-uri
				if ( ! preg_match( "/src=['\"]data:image/is", $imgHTML ) ) {

					$placeholder_url_used = $placeholder_url;

					// replace the src and add the data-src attribute
					$replaceHTML = preg_replace( '/<img(.*?)src=/is', '<img$1src="' . esc_attr( $placeholder_url_used ) . '" data-lazy-type="image" data-lazy-src=', $imgHTML );

					// also replace the srcset (responsive images)
					$replaceHTML = str_replace( 'srcset', 'data-lazy-srcset', $replaceHTML );

					// add the lazy class to the img element
					if ( preg_match( '/class=["\']/i', $replaceHTML ) ) {
						$replaceHTML = preg_replace( '/class=(["\'])(.*?)["\']/is', 'class=$1lazy lazy-hidden $2$1', $replaceHTML );
					} else {
						$replaceHTML = preg_replace( '/<img/is', '<img class="lazy lazy-hidden"', $replaceHTML );
					}

					$replaceHTML .= '<noscript>' . $imgHTML . '</noscript>';

					array_push( $search, $imgHTML );
					array_push( $replace, $replaceHTML );
				}
			}

			$content = str_replace( $search, $replace, $content );

			return $content;

		}

		/**
		 * Replace iframes with placeholders in the content
		 *
		 * @param string $content The HTML to do the filtering on
		 *
		 * @return string The HTML with the iframes replaced
		 */
		public static function filter_iframes( $content ) {

			$placeholder_url = apply_filters( 'powered_cache_lazy_load_placeholder_url', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

			$match_content = self::_get_content_haystack( $content );

			$matches = array();
			preg_match_all( '|<iframe\s+.*?</iframe>|si', $match_content, $matches );

			$search  = array();
			$replace = array();

			foreach ( $matches[0] as $iframeHTML ) {

				// Don't mess with the Gravity Forms ajax iframe
				if ( strpos( $iframeHTML, 'gform_ajax_frame' ) ) {
					continue;
				}

				$replaceHTML = '<img src="' . esc_attr( $placeholder_url ) . '"  class="lazy lazy-hidden" data-lazy-type="iframe" data-lazy-src="' . esc_attr( $iframeHTML ) . '" alt="">';

				$replaceHTML .= '<noscript>' . $iframeHTML . '</noscript>';

				array_push( $search, $iframeHTML );
				array_push( $replace, $replaceHTML );
			}

			$content = str_replace( $search, $replace, $content );

			return $content;

		}

		/**
		 * Remove elements we don’t want to filter from the HTML string
		 * We’re reducing the haystack by removing the hay we know we don’t want to look for needles in
		 *
		 * @param string $content The HTML string
		 *
		 * @return string The HTML string without the unwanted elements
		 */
		protected static function _get_content_haystack( $content ) {
			$content = self::remove_noscript( $content );
			$content = self::remove_skip_classes_elements( $content );

			return $content;
		}

		/**
		 * Remove <noscript> elements from HTML string
		 *
		 * @author sigginet
		 *
		 * @param string $content The HTML string
		 *
		 * @return string The HTML string without <noscript> elements
		 */
		public static function remove_noscript( $content ) {
			return preg_replace( '/<noscript.*?(\/noscript>)/i', '', $content );
		}

		/**
		 * Remove HTML elements with certain classnames (or IDs) from HTML string
		 *
		 * @param string $content The HTML string
		 *
		 * @return string The HTML string without the unwanted elements
		 */
		public static function remove_skip_classes_elements( $content ) {

			$skip_classes = self::_get_skip_classes( 'html' );

			/*
			http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
			We can’t do this, but we still do it.
			*/
			$skip_classes_quoted = array_map( 'preg_quote', $skip_classes );
			$skip_classes_ORed   = implode( '|', $skip_classes_quoted );

			$regex = '/<\s*\w*\s*class\s*=\s*[\'"]?(|.*\s)?' . $skip_classes_ORed . '(|\s.*)?[\'"]?.*?>/isU';

			return preg_replace( $regex, '', $content );
		}


		/**
		 * Get the skip classes
		 *
		 * @param string $content_type The content type (image/iframe etc)
		 *
		 * @return array An array of strings with the class names
		 */
		protected static function _get_skip_classes( $content_type ) {

			/**
			 * Filter the class names to skip
			 *
			 * @param array  $skip_classes The current classes to skip
			 * @param string $content_type The current content type
			 */
			$skip_classes = apply_filters( 'powered_cache_lazy_load_skip_classes', array( 'lazy' ), $content_type );

			return $skip_classes;
		}

	}

endif;