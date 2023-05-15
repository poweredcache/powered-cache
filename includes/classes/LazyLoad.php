<?php
/**
 * LazyLoad
 *
 * Most of the code borrowed from https://github.com/Angrycreative/bj-lazy-load
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\POST_META_DISABLE_LAZYLOAD_KEY;

// phpcs:disable WordPressVIPMinimum.Security.ProperEscapingFunction.hrefSrcEscUrl

/**
 * Class LazyLoad
 */
class LazyLoad {

	/**
	 * Hold plugin settings
	 *
	 * @var array $settings
	 */
	private $settings;

	/**
	 * Return an instance of the current class
	 *
	 * @return LazyLoad
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
	 * Setup routine
	 */
	public function setup() {
		$this->settings = \PoweredCache\Utils\get_settings();

		if ( $this->settings['enable_lazy_load'] ) {
			add_action( 'wp', [ $this, 'init' ], 9999 ); // run this as late as possible
			add_action( 'powered_cache_lazy_load_compat', [ $this, 'compat' ] );
			add_action( 'powered_cache_lazy_load_run_filter', [ $this, 'maybe_disable_through_meta' ] );
			add_filter( 'powered_cache_delayed_js_skip', [ $this, 'maybe_delayed_js_skip' ], 10, 2 );
		}

		/**
		 * Filter to disable native lazyload support.
		 *
		 * @hook   powered_cache_disable_native_lazyload
		 *
		 * @param  {boolean} $status true to disable native lazy load.
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_disable_native_lazyload', $this->settings['disable_wp_lazy_load'] ) ) {
			add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		}

	}

	/**
	 * Initialize the setup
	 */
	public function init() {
		/* We do not touch the feeds */
		if ( is_admin() || is_feed() || is_preview() ) {
			return;
		}

		/**
		 * Fires before filtering lazy-load hooks.
		 *
		 * @hook  powered_cache_lazy_load_compat
		 *
		 * @since 1.0
		 */
		do_action( 'powered_cache_lazy_load_compat' );

		/**
		 * Filters lazy-load status.
		 *
		 * @hook   powered_cache_lazy_load_enabled
		 *
		 * @param  {boolean} $enable true to enable
		 *
		 * @return {boolean} New value
		 * @since  1.0
		 */
		$enabled = apply_filters( 'powered_cache_lazy_load_enabled', true );

		if ( $enabled ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			$this->setup_filtering();
		}
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'PCLL', POWERED_CACHE_URL . 'dist/js/lazyload.js', null, POWERED_CACHE_VERSION, true );

		/**
		 * Filters screen threshold value.
		 *
		 * @hook   powered_cache_lazy_load_threshold
		 *
		 * @param  {int} $threshold Screen display threshold.
		 *
		 * @return {int} New value
		 * @since  1.0
		 */
		$threshold = apply_filters( 'powered_cache_lazy_load_threshold', 200 );

		if ( 200 !== (int) $threshold ) {
			wp_localize_script( 'PCLL', 'PCLL_options', array( 'threshold' => $threshold ) );
		}
	}

	/**
	 * Set up filtering for certain content
	 */
	protected function setup_filtering() {

		/**
		 * Filters whether apply or not apply lazyload for images.
		 *
		 * @hook   powered_cache_lazy_load_images
		 *
		 * @param  {boolean} true to lazyload for images
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_images', $this->settings['lazy_load_images'] ) ) {
			add_filter( 'powered_cache_lazy_load_filter', array( __CLASS__, 'filter_images' ) );
		}

		/**
		 * Filters whether apply or not apply lazyload for iframes.
		 *
		 * @hook   powered_cache_lazy_load_iframes
		 *
		 * @param  {boolean} true to apply lazyload for iframes
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_iframes', $this->settings['lazy_load_iframes'] ) ) {
			add_filter( 'powered_cache_lazy_load_filter', array( __CLASS__, 'filter_iframes' ) );
		}

		/**
		 * Filters whether apply or not apply lazyload for post_content.
		 *
		 * @hook   powered_cache_lazy_load_post_content
		 *
		 * @param  {boolean} true to apply lazyload for post_content
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_post_content', $this->settings['lazy_load_post_content'] ) ) {
			add_filter( 'the_content', array( __CLASS__, 'filter' ), 200 );
		}

		/**
		 * Filters whether apply or not apply lazyload for widgets.
		 *
		 * @hook   powered_cache_lazy_load_widget_text
		 *
		 * @param  {boolean} true to apply lazyload for widgets
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_widget_text', $this->settings['lazy_load_widgets'] ) ) {
			add_filter( 'widget_text', array( __CLASS__, 'filter' ), 200 );
		}

		/**
		 * Filters whether apply or not apply lazyload for thumbnails.
		 *
		 * @hook   powered_cache_lazy_load_post_thumbnail
		 *
		 * @param  {boolean} true to apply lazyload for thumbnails
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_post_thumbnail', $this->settings['lazy_load_post_thumbnail'] ) ) {
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'filter' ), 200 );
		}

		/**
		 * Filters whether apply or not apply lazyload for avatars.
		 *
		 * @hook   powered_cache_lazy_load_avatar
		 *
		 * @param  {boolean} true to apply lazyload for avatars
		 *
		 * @return {boolean} New value.
		 * @since  1.0
		 */
		if ( true === apply_filters( 'powered_cache_lazy_load_avatar', $this->settings['lazy_load_avatars'] ) ) {
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
		/**
		 * Filters lazy-load run filter.
		 *
		 * @hook   powered_cache_lazy_load_run_filter
		 *
		 * @param  {boolean} $run_filter true to enable
		 *
		 * @return {boolean} New value
		 * @since  1.0
		 */
		$run_filter = apply_filters( 'powered_cache_lazy_load_run_filter', true );
		if ( ! $run_filter ) {
			return $content;
		}

		/**
		 * Filters the content
		 *
		 * @hook   powered_cache_lazy_load_filter
		 *
		 * @param string $content The HTML string to filter
		 *
		 * @since  1.0
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

		/**
		 * Filters the content
		 *
		 * @hook   powered_cache_lazy_load_filter
		 *
		 * @param string $content The HTML string to filter
		 *
		 * @since  1.0
		 */
		$placeholder_url = apply_filters( 'powered_cache_lazy_load_placeholder_url', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

		$match_content = self::get_content_haystack( $content );

		$matches = array();
		preg_match_all( '/<img[\s\r\n]+.*?>/is', $match_content, $matches );

		$search  = array();
		$replace = array();

		foreach ( $matches[0] as $img_html ) {

			// don't to the replacement if the image is a data-uri
			if ( ! preg_match( "/src=['\"]data:image/is", $img_html ) ) {

				$placeholder_url_used = $placeholder_url;

				// replace the src and add the data-src attribute
				$replace_html = preg_replace( '/<img(.*?)src=/is', '<img$1src="' . esc_attr( $placeholder_url_used ) . '" data-lazy-type="image" data-lazy-src=', $img_html );

				// also replace the srcset (responsive images)
				$replace_html = str_replace( 'srcset', 'data-lazy-srcset', $replace_html );

				// add the lazy class to the img element
				if ( preg_match( '/class=["\']/i', $replace_html ) ) {
					$replace_html = preg_replace( '/class=(["\'])(.*?)["\']/is', 'class=$1lazy lazy-hidden $2$1', $replace_html );
				} else {
					$replace_html = preg_replace( '/<img/is', '<img class="lazy lazy-hidden"', $replace_html );
				}

				$replace_html .= '<noscript>' . $img_html . '</noscript>';

				array_push( $search, $img_html );
				array_push( $replace, $replace_html );
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
		/**
		 * Filters the lazyload placeholder URL.
		 *
		 * @hook   powered_cache_lazy_load_placeholder_url
		 *
		 * @param string $image default placeholder url. Base64 string as default.
		 *
		 * @since  1.0
		 */
		$placeholder_url = apply_filters( 'powered_cache_lazy_load_placeholder_url', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

		$match_content = self::get_content_haystack( $content );

		$matches = array();
		preg_match_all( '|<iframe\s+.*?</iframe>|si', $match_content, $matches );

		$search  = array();
		$replace = array();

		foreach ( $matches[0] as $iframe_html ) {

			// Don't mess with the Gravity Forms ajax iframe
			if ( strpos( $iframe_html, 'gform_ajax_frame' ) ) {
				continue;
			}

			$replace_html = '<img src="' . esc_attr( $placeholder_url ) . '"  class="lazy lazy-hidden" data-lazy-type="iframe" data-lazy-src="' . esc_attr( $iframe_html ) . '" alt="">';

			$replace_html .= '<noscript>' . $iframe_html . '</noscript>';

			array_push( $search, $iframe_html );
			array_push( $replace, $replace_html );
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
	protected static function get_content_haystack( $content ) {
		$content = self::remove_noscript( $content );
		$content = self::remove_skip_classes_elements( $content );

		return $content;
	}

	/**
	 * Remove <noscript> elements from HTML string
	 *
	 * @param string $content The HTML string
	 *
	 * @return string The HTML string without <noscript> elements
	 * @author sigginet
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

		$skip_classes = self::get_skip_classes( 'html' );

		/**
		 * http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
		 * We can’t do this, but we still do it.
		 */
		$skip_classes_quoted = array_map( 'preg_quote', $skip_classes );
		$skip_classes_ored   = implode( '|', $skip_classes_quoted );

		$regex = '/<\s*\w*\s*class\s*=\s*[\'"]?(|.*\s)?' . $skip_classes_ored . '(|\s.*)?[\'"]?.*?>/isU';

		return preg_replace( $regex, '', $content );
	}


	/**
	 * Get the skip classes
	 *
	 * @param string $content_type The content type (image/iframe etc)
	 *
	 * @return array An array of strings with the class names
	 */
	protected static function get_skip_classes( $content_type ) {
		/**
		 * Filters the class names to skip
		 *
		 * @hook   powered_cache_lazy_load_skip_classes
		 *
		 * @param  {array}  $skip_classes The current classes to skip
		 * @param  {string} $content_type The current content type
		 *
		 * @return {array}  New value.
		 * @since  1.0
		 */
		$skip_classes = apply_filters( 'powered_cache_lazy_load_skip_classes', array( 'lazy' ), $content_type );

		return $skip_classes;
	}

	/**
	 * Manage 3rd party compat cases
	 */
	public function compat() {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}

		if ( function_exists( 'bp_is_my_profile' ) && bp_is_my_profile() ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}

		if ( function_exists( 'mopr_get_option' ) && WP_CONTENT_DIR . mopr_get_option( 'mobile_theme_root', 1 ) === get_theme_root() ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}

		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' ) ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}

		if ( 1 === intval( get_query_var( 'print' ) ) || 1 === intval( get_query_var( 'printpage' ) ) ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}

		if ( function_exists( 'bnc_wptouch_is_mobile' ) || defined( 'WPTOUCH_VERSION' ) ) {
			add_filter( 'powered_cache_lazy_load_enabled', '__return_false' );
		}
	}

	/**
	 * Maybe disable lazyloading for a particular post
	 *
	 * @param bool $status Lazyload filter status
	 *
	 * @return bool
	 * @since 2.0
	 */
	public function maybe_disable_through_meta( $status ) {

		if ( in_the_loop() && get_post_meta( get_the_ID(), POST_META_DISABLE_LAZYLOAD_KEY, true ) ) {
			$status = false;
		}

		return $status;
	}

	/**
	 * Skip lazyload for delayed script
	 *
	 * @param boolean $is_delay_skipped Whether skip or not skip delayed JS
	 * @param string  $script           script
	 *
	 * @return boolean
	 */
	public function maybe_delayed_js_skip( $is_delay_skipped, $script ) {
		if ( false !== stripos( $script, POWERED_CACHE_URL . 'dist/js/lazyload.js' ) ) {
			return true;
		}

		return $is_delay_skipped;
	}

}

