<?php
/**
 * File optimizer (minify, contact) related functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Dependencies\voku\helper\HtmlMin;
use const PoweredCache\Constants\POST_META_DISABLE_CSS_OPTIMIZATION;
use const PoweredCache\Constants\POST_META_DISABLE_JS_DEFER;
use const PoweredCache\Constants\POST_META_DISABLE_JS_DELAY;
use const PoweredCache\Constants\POST_META_DISABLE_JS_OPTIMIZATION;
use PoweredCache\Optimizer\CSS;
use PoweredCache\Optimizer\Helper;
use PoweredCache\Optimizer\JS;
use function PoweredCache\Utils\get_cache_dir;
use function PoweredCache\Utils\remove_dir;


/**
 * Class FileOptimizer
 */
class FileOptimizer {
	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	public $settings = [];

	/**
	 * Do optimizations for wp-admin?
	 *
	 * @var bool
	 */
	public $optimize_dashboard;


	/**
	 * Return an instance of the current class
	 *
	 * @return FileOptimizer
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
	 * Setup routine
	 */
	public function setup() {
		$this->settings = \PoweredCache\Utils\get_settings();

		// Check request method
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ! in_array( $_SERVER['REQUEST_METHOD'], [ 'GET', 'HEAD' ], true ) ) {
			return true;
		}

		add_action( 'plugins_loaded', [ $this, 'setup_file_optimizer' ] );

	}

	/**
	 * Setup file optimizer module
	 */
	public function setup_file_optimizer() {
		/**
		 * Filters whether apply or not apply file optimizations on wp-admin.
		 *
		 * @hook   powered_cache_fo_dashboard
		 *
		 * @param  {boolean} true to enable optimizations for dashboard.
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		$this->optimize_dashboard = apply_filters( 'powered_cache_fo_dashboard', false );

		add_action( 'powered_cache_purge_all_cache', [ $this, 'maybe_purge_fo_cache' ] );

		/**
		 * Don't optimize wp-admin by default
		 * This might worth to add as option?
		 */

		if ( is_admin() && ! $this->optimize_dashboard ) {
			return;
		}

		/**
		 * Don't optimize in customizer preview
		 */
		if ( ! empty( $_GET['customize_changeset_uuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			\PoweredCache\Utils\log( 'Do not run file optimizer in customizer preview' );

			return;
		}

		/**
		 * Filters FileOptimizer integration
		 *
		 * @hook   powered_cache_fo_disable
		 *
		 * @param  {boolean} False by default.
		 *
		 * @return {boolean} New value.
		 * @since  2.2
		 */
		$disable_file_optimizer = apply_filters( 'powered_cache_fo_disable', false );

		if ( $disable_file_optimizer ) {
			return;
		}

		add_action( 'init', [ $this, 'setup_css_combine' ] );
		add_action( 'init', [ $this, 'setup_js_combine' ] );
		add_filter( 'script_loader_tag', [ $this, 'js_minify' ], 10, 3 );
		add_filter( 'style_loader_tag', [ $this, 'css_minify' ], 10, 4 );
		add_filter( 'powered_cache_fo_script_loader_tag', [ $this, 'change_js_execute_method' ] );
		add_filter( 'script_loader_tag', [ $this, 'change_js_execute_method' ], 99 );
		add_action( 'after_setup_theme', [ $this, 'maybe_start_buffer' ], 999 );
		add_action( 'template_redirect', [ $this, 'maybe_suppress_optimizations' ] );

		if ( ! $this->settings['combine_js'] ) {
			add_filter( 'powered_cache_fo_js_do_concat', '__return_false' );
		}

		if ( ! $this->settings['combine_css'] ) {
			add_filter( 'powered_cache_fo_css_do_concat', '__return_false' );
		}

		if ( $this->settings['combine_google_fonts'] ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'combine_google_fonts' ], 99 );
		}
	}

	/**
	 * Process output buffer
	 *
	 * @return void
	 */
	public function maybe_start_buffer() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		if ( strpos( $request_uri, 'robots.txt' ) !== false || strpos( $request_uri, '.htaccess' ) !== false ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $request_uri );
		$file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

		if ( ! preg_match( '#index\.php$#i', $request_uri ) && in_array( $file_extension, array( 'php', 'xml', 'xsl' ), true ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		ob_start( [ $this, 'process_buffer' ] );
	}

	/**
	 * Process output buffer
	 *
	 * @param string $html Output buffer
	 *
	 * @return string|string[]|null
	 */
	public function process_buffer( $html ) {
		$html = $this->maybe_defer_inline_scripts( $html );
		$html = $this->maybe_delay_scripts( $html );
		$html = $this->maybe_replace_google_fonts_with_bunny_fonts( $html );
		$html = $this->maybe_minify_html( $html );

		return $html;
	}

	/**
	 * Replace Google Fonts with Bunny drop-in replacement's
	 *
	 * @param string $html Output buffer
	 *
	 * @return array|mixed|string|string[]
	 * @since 3.0
	 */
	public function maybe_replace_google_fonts_with_bunny_fonts( $html ) {
		if ( ! $this->settings['use_bunny_fonts'] ) {
			return $html;
		}

		$html = str_replace(
			[
				'https://fonts.googleapis.com',
				'http://fonts.googleapis.com',
				'//fonts.googleapis.com',
			],
			'https://fonts.bunny.net',
			$html
		);

		return $html;
	}

	/**
	 * Maybe suppress optimizations based on the post meta options
	 *
	 * @since 2.1
	 */
	public function maybe_suppress_optimizations() {
		if ( is_singular() ) {
			$disable_css_optimization = (bool) get_post_meta( get_the_ID(), POST_META_DISABLE_CSS_OPTIMIZATION, true );
			$disable_js_optimization  = (bool) get_post_meta( get_the_ID(), POST_META_DISABLE_JS_OPTIMIZATION, true );
			$disable_js_defer         = (bool) get_post_meta( get_the_ID(), POST_META_DISABLE_JS_DEFER, true );
			$disable_js_delay         = (bool) get_post_meta( get_the_ID(), POST_META_DISABLE_JS_DELAY, true );

			if ( $disable_css_optimization ) {
				add_filter( 'powered_cache_fo_css_do_concat', '__return_false' );
				add_filter( 'powered_cache_fo_disable_css_minify', '__return_true' );
			}

			if ( $disable_js_optimization ) {
				add_filter( 'powered_cache_fo_js_do_concat', '__return_false' );
				add_filter( 'powered_cache_fo_disable_js_minify', '__return_true' );
			}

			if ( $disable_js_defer ) {
				add_filter( 'powered_cache_disable_js_defer', '__return_true' );
				add_filter( 'powered_cache_disable_js_defer_inline', '__return_true' );
			}

			if ( $disable_js_delay ) {
				add_filter( 'powered_cache_delayed_js_skip', '__return_true' );
			}
		}
	}

	/**
	 * Purge FO cache
	 *
	 * @since 2.0
	 */
	public function maybe_purge_fo_cache() {
		if ( file_exists( POWERED_CACHE_FO_CACHE_DIR ) ) {
			remove_dir( POWERED_CACHE_FO_CACHE_DIR );
		}
	}

	/**
	 * Minify given HTML output
	 *
	 * @param string $buffer HTML
	 *
	 * @return string|string[]|null
	 */
	public function maybe_minify_html( $buffer ) {
		if ( ! $this->settings['minify_html'] ) {
			return $buffer;
		}

		if ( false === stripos( $buffer, '<html' ) && false === stripos( $buffer, '<!DOCTYPE html' ) ) {
			return $buffer;
		}

		/**
		 * Filters whether disable or not disable HTML minification.
		 *
		 * @hook   powered_cache_fo_disable_html_minify
		 *
		 * @param  {boolean} true to disable html minify
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_html_minify', false ) ) {
			return $buffer;
		}

		$html_min = new HtmlMin();
		$html_min->doOptimizeViaHtmlDomParser( $this->settings['minify_html_dom_optimization'] );
		$html_min->doRemoveOmittedQuotes( false );
		$html_min->doRemoveOmittedHtmlTags( false );
		$html_min->overwriteSpecialScriptTags( [ 'x-tmpl-mustache', 'text/template' ] );
		$buffer = $html_min->minify( $buffer );

		if ( ! $this->settings['minify_html_dom_optimization'] ) {
			// Remove line breaks and spaces between tags
			$buffer = preg_replace( '/>\s+</', '><', $buffer );
			// Remove comments
			$buffer = preg_replace( '/<!--(.|\s)*?-->/', '', $buffer );
		}

		return $buffer;
	}


	/**
	 * Add JS execution method to script tag
	 *
	 * @param string $tag <script... tag
	 *
	 * @return mixed
	 */
	public function change_js_execute_method( $tag ) {
		$is_defer = $this->settings['js_defer'];

		if ( ! $is_defer ) {
			return $tag;
		}

		if ( preg_match( '/<script\s+[^>]*\b(?:async|defer)\b[^>]*>/i', $tag ) ) {
			return $tag;
		}

		if ( Helper::is_defer_excluded( $tag ) ) {
			return $tag;
		}

		/**
		 * Filters whether disable or not disable Defer
		 *
		 * @hook   powered_cache_disable_js_defer
		 *
		 * @param  {boolean} true to disable defer
		 *
		 * @return {boolean} New value.
		 * @since  3.2
		 */
		if ( apply_filters( 'powered_cache_disable_js_defer', false, $tag ) ) {
			return $tag;
		}

		$search  = '<script ';
		$replace = '<script defer="defer" ';
		$tag     = str_replace( $search, $replace, $tag );

		return $tag;
	}

	/**
	 * Minify CSS item
	 *
	 * @param string $tag    style tag <link...
	 * @param string $handle style handle name
	 * @param string $href   Resource URL
	 * @param string $media  CSS media
	 *
	 * @return mixed
	 */
	public function css_minify( $tag, $handle, $href, $media ) {
		if ( ! $this->settings['minify_css'] ) {
			return $tag;
		}

		/**
		 * Filters whether disable or not disable CSS minification.
		 *
		 * @hook   powered_cache_fo_disable_css_minify
		 *
		 * @param  {boolean} true to disable CSS minify
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_css_minify', false ) ) {
			return $tag;
		}

		// only minify static .css
		if ( false === strpos( $href, '.css' ) ) {
			return $tag;
		}

		// already minified
		if ( false !== strpos( $href, '.min' ) ) {
			return $tag;
		}

		// only minify local hosted
		if ( ! Helper::is_internal_url( $href, home_url() ) ) {
			return $tag;
		}

		// excluded explicitly
		if ( Helper::is_excluded_css( $href ) ) {
			return $tag;
		}

		$realpath      = Helper::realpath( $href, home_url() );
		$path          = substr( $realpath, strlen( ABSPATH ) - 1 );
		$optimized_url = Helper::get_optimized_url( $path, true );
		$new_tag       = str_replace( $href, $optimized_url, $tag );

		return $new_tag;
	}

	/**
	 * Minify JS file
	 *
	 * @param string $tag    script tag <script...
	 * @param string $handle JS handle
	 * @param string $src    Resource URL
	 *
	 * @return mixed
	 */
	public function js_minify( $tag, $handle, $src ) {
		if ( ! $this->settings['minify_js'] ) {
			return $tag;
		}

		/**
		 * Filters whether disable or not disable JS minification.
		 *
		 * @hook   powered_cache_fo_disable_js_minify
		 *
		 * @param  {boolean} true to disable JS minify
		 * @param  {string} $tag    script tag <script...
		 * @param  {string} $handle JS handle
		 * @param  {string} $src    Resource URL
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_js_minify', false, $tag, $handle, $src ) ) {
			return $tag;
		}

		// only minify static .css
		if ( false === strpos( $src, '.js' ) ) {
			return $tag;
		}

		// already minified
		if ( false !== strpos( $src, '.min' ) ) {
			return $tag;
		}

		// only minify local hosted
		if ( ! Helper::is_internal_url( $src, home_url() ) ) {
			return $tag;
		}

		// excluded explicitly
		if ( Helper::is_excluded_js( $src ) ) {
			return $tag;
		}

		$realpath      = Helper::realpath( $src, home_url() );
		$path          = substr( $realpath, strlen( ABSPATH ) - 1 );
		$optimized_url = Helper::get_optimized_url( $path, true );
		$new_tag       = str_replace( $src, $optimized_url, $tag );

		return $new_tag;
	}


	/**
	 * Setup JS concat
	 */
	public function setup_js_combine() {
		if ( ! $this->settings['combine_js'] ) {
			return;
		}

		/**
		 * Filters whether disable or not disable JS combine
		 *
		 * @hook   powered_cache_fo_disable_js_combine
		 *
		 * @param  {boolean} true to disable JS combine
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_js_combine', false ) ) {
			return;
		}

		global $wp_scripts;

		$wp_scripts = new JS( $wp_scripts );
		/**
		 * Filters whether allow or not allow gzip compression for combined file names.
		 *
		 * @hook   powered_cache_fo_allow_gzip_compression
		 *
		 * @param  {boolean} true to enable gzip compression on filenames
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		$wp_scripts->allow_gzip_compression = apply_filters( 'powered_cache_fo_allow_gzip_compression', true );
		$wp_scripts->do_minify              = $this->settings['minify_js'];

	}

	/**
	 * Setup CSS concat
	 */
	public function setup_css_combine() {
		if ( ! $this->settings['combine_css'] ) {
			return;
		}

		/**
		 * Filters whether disable or not disable CSS combine
		 *
		 * @hook   powered_cache_fo_disable_css_combine
		 *
		 * @param  {boolean} true to disable CSS combine
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_css_combine', false ) ) {
			return;
		}

		global $wp_styles;

		$wp_styles                         = new CSS( $wp_styles );
		$wp_styles->allow_gzip_compression = apply_filters( 'powered_cache_fo_allow_gzip_compression', true );
		$wp_styles->do_minify              = $this->settings['minify_css'];
		$wp_styles->enable_cdn             = $this->settings['enable_cdn'];

	}

	/**
	 * Combine google fonts
	 * Derived from https://gist.github.com/eugenealegiojo/dbdd620a998458aa2eb1f124b2f0b18e
	 */
	public function combine_google_fonts() {
		global $wp_styles;

		// Check for any enqueued `fonts.googleapis.com` from themes or plugins
		if ( isset( $wp_styles->queue ) ) {
			$google_fonts_domain   = '//fonts.googleapis.com/css';
			$enqueued_google_fonts = array();
			$families              = array();
			$subsets               = array();
			$font_args             = array();
			$font_display          = '';

			// Collect all enqueued google fonts
			foreach ( $wp_styles->queue as $key => $handle ) {
				if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
					continue;
				}

				$style_src = $wp_styles->registered[ $handle ]->src;

				if ( false !== strpos( $style_src, 'fonts.googleapis.com/css' ) || false !== strpos( $style_src, 'fonts.bunny.net/css' ) ) {
					$url = wp_parse_url( $style_src );

					if ( is_string( $url['query'] ) ) {
						parse_str( $url['query'], $parsed_url );

						if ( isset( $parsed_url['family'] ) ) {
							// Collect all subsets
							if ( isset( $parsed_url['subset'] ) ) {
								$subsets[] = rawurlencode( trim( $parsed_url['subset'] ) );
							}

							$font_families = explode( '|', $parsed_url['family'] );
							foreach ( $font_families as $parsed_font ) {
								$get_font = explode( ':', $parsed_font );

								// Extract the font data
								if ( isset( $get_font[0] ) && ! empty( $get_font[0] ) ) {
									$family  = $get_font[0];
									$weights = isset( $get_font[1] ) && ! empty( $get_font[1] ) ? explode( ',', $get_font[1] ) : array();

									// Combine weights if family has been enqueued
									if ( isset( $enqueued_google_fonts[ $family ] ) && $weights !== $enqueued_google_fonts[ $family ]['weights'] ) {
										$combined_weights                            = array_merge( $weights, $enqueued_google_fonts[ $family ]['weights'] );
										$enqueued_google_fonts[ $family ]['weights'] = array_unique( $combined_weights );
									} else {
										$enqueued_google_fonts[ $family ] = array(
											'handle'  => $handle,
											'family'  => $family,
											'weights' => $weights,
										);

										if ( isset( $parsed_url['display'] ) ) {
											$font_display = $parsed_url['display'];
										}

										// Remove enqueued google font style, so we would only have one HTTP request.
										wp_dequeue_style( $handle );
									}
								}
							}
						}
					}
				}
			}

			// Combine all queued fonts
			if ( count( $enqueued_google_fonts ) > 0 ) {
				foreach ( $enqueued_google_fonts as $family => $data ) {
					// Collect all family and weights
					if ( ! empty( $data['weights'] ) ) {
						$families[] = $family . ':' . implode( ',', $data['weights'] );
					} else {
						$families[] = $family;
					}
				}

				if ( ! empty( $families ) ) {
					$font_args['family'] = implode( '|', $families );

					if ( ! empty( $subsets ) ) {
						$font_args['subset'] = implode( ',', $subsets );
					}

					/**
					 * Force font display: swap
					 *
					 * @since 2.2
					 */
					if ( $this->settings['swap_google_fonts_display'] ) {
						$font_display = 'swap';
					}

					/**
					 * Filters font display attribute of the google fonts.
					 *
					 * @hook   powered_cache_fo_google_font_display
					 *
					 * @param  {string} $font_display font display attribute.
					 *
					 * @return {string} New value.
					 * @since  2.0
					 */
					$font_display = apply_filters( 'powered_cache_fo_google_font_display', $font_display );

					if ( ! empty( $font_display ) ) {
						$font_args['display'] = $font_display;
					}

					/**
					 * Filters google font's domain
					 *
					 * @hook   powered_cache_fo_google_fonts_domain
					 *
					 * @param  {string} $font_display font display attribute.
					 *
					 * @return {string} New value.
					 * @since  3.0
					 */
					$fonts_domain = apply_filters( 'powered_cache_fo_google_fonts_domain', $google_fonts_domain );

					$src = esc_url_raw( add_query_arg( $font_args, $fonts_domain ) );

					// Enqueue google fonts into one URL request
					wp_enqueue_style( // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
						'pc-google-fonts-' . md5( $src ),
						$src,
						array()
					);

					unset( $enqueued_google_fonts );
				}
			}
		}
	}

	/**
	 * DelayedJS execution
	 * Similar logic with image lazy-loading but this time for scripts.
	 *
	 * @param string $html HTML Buffer
	 *
	 * @return string
	 * @since 3.0
	 */
	public function maybe_delay_scripts( $html ) {
		if ( ! $this->settings['js_delay'] ) {
			return $html;
		}

		$pattern = '/<script([^>]*)>(.*?)<\/script>/si';

		preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return $html;
		}

		$delayed_script_count = 0;

		foreach ( $matches as $match ) {
			$script     = $match[0];
			$attributes = $match[1];
			$content    = $match[2];

			$is_delay_skipped = function_exists( 'is_user_logged_in' ) && is_user_logged_in();

			/**
			 * Whether skip or not skip js for delay
			 *
			 * @hook   powered_cache_delayed_js_skip
			 *
			 * @param  {bool} Depends on logged-in status by default.
			 *
			 * @return {bool} New value.
			 * @since  3.0
			 */
			if ( apply_filters( 'powered_cache_delayed_js_skip', $is_delay_skipped, $script, $attributes, $content ) ) {
				continue;
			}

			if ( Helper::is_delay_excluded( $script ) ) {
				continue;
			}

			if ( ! preg_match( '/type=/', $script ) || preg_match( '/type=[\'"]text\/javascript[\'"]/', $script ) ) {
				$new_script = $script;

				// Check if script has a src attribute
				if ( strpos( $attributes, 'src=' ) !== false ) {
					$new_script = preg_replace( '/<script([^>]*)>/', '<script type="pc-delayed-js"$1>', $new_script );
				} else {
					$new_script = preg_replace( '/<script([^>]*)>/', '<script type="pc-delayed-js"$1>', $new_script );
				}

				$html = str_replace( $script, $new_script, $html );
				$delayed_script_count ++;
			}
		}

		if ( 0 === $delayed_script_count ) {
			return $html;
		}

		/**
		 * Filter delay time for JS execution fallback
		 *
		 * @hook   powered_cache_delayed_js_timeout
		 *
		 * @param  {int} $delay_in_ms Delay time in milliseconds
		 *
		 * @return {int} New value.
		 * @since  3.0
		 */
		$delay_timeout  = apply_filters( 'powered_cache_delayed_js_timeout', $this->settings['js_delay_timeout'] );
		$script_path    = POWERED_CACHE_PATH . 'dist/js/script-loader.js';
		$script_content = file_get_contents( $script_path ); // phpcs:ignore

		if ( ! $script_content ) {
			return $html;
		}

		$head_pos = strpos( $html, '</head>' );

		if ( false === $head_pos ) { // bail if no head tag
			return $html;
		}

		$delay_js_script_content = '<script id="powered-cache-delayed-js">' . $script_content . '</script>' . PHP_EOL;

		/**
		 * Delayed JS script content
		 *
		 * @hook          powered_cache_delayed_js_script_content
		 *
		 * @param         {string} $delay_js_script_content Delayed JS script content
		 * @param         {string} $script_path Script path
		 * @param         {string} $html HTML buffer
		 * @param         {int} $delay_timeout Delay time in milliseconds
		 *
		 * @return        {string} New value.
		 * @since         3.4
		 */
		$delay_js_script_content = apply_filters( 'powered_cache_delayed_js_script_content', $delay_js_script_content, $script_path, $html, $delay_timeout );

		$html = substr_replace( $html, $delay_js_script_content, $head_pos, 0 );

		$script_loader  = PHP_EOL . '<script id="powered-cache-delayed-script-loader">' . PHP_EOL;
		$script_loader .= 'console.log("[Powered Cache] - Script(s) will be loaded with delay or interaction");' . PHP_EOL;
		$script_loader .= 'window.PCScriptLoaderTimeout=' . absint( $delay_timeout ) . ';' . PHP_EOL;

		$script_loader .= 'Defer.all(\'script[type="pc-delayed-js"]\', 0, true);' . PHP_EOL;

		if ( absint( $delay_timeout ) > 0 ) {
			$script_loader .= 'Defer.all(\'script[type="pc-delayed-js"]\', window.PCScriptLoaderTimeout, false);' . PHP_EOL;
		}
		$script_loader .= '</script>' . PHP_EOL;

		/**
		 * Delayed JS script loader content
		 *
		 * @hook                 powered_cache_delayed_js_script_loader
		 *
		 * @param                {string} $script_loader Delayed JS script loader content
		 * @param                {string} $html HTML buffer
		 * @param                {int} $delay_timeout Delay time in milliseconds
		 *
		 * @return               {string} New value.
		 * @since                3.4
		 */
		$script_loader = apply_filters( 'powered_cache_delayed_js_script_loader', $script_loader, $html, $delay_timeout );

		$body_pos = strpos( $html, '</body>' );

		if ( false !== $body_pos ) {
			$html = substr_replace( $html, $script_loader, $body_pos, 0 );
		} else {
			$html_pos = strpos( $html, '</html>' );
			if ( false !== $html_pos ) {
				$html = substr_replace( $html, $script_loader, $html_pos, 0 );
			}
		}

		return $html;
	}

	/**
	 * Defer jQuery depended inline scripts
	 *
	 * @param string $html Output buffer
	 *
	 * @return array|mixed|string|string[]|null
	 * @since 3.2
	 */
	public function maybe_defer_inline_scripts( $html ) {
		if ( ! $this->settings['js_defer'] ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/(<script[^>]*>)(.*?)<\/script>/s',
			function ( $matches ) {
				$script_open_tag = $matches[1];
				$script_content  = $matches[2];

				if ( ! $this->should_defer_script( $script_content, $script_open_tag ) ) {
					return $matches[0]; // Return the script unchanged
				}

				/**
				 * Filters whether disable or not disable inline defer
				 *
				 * @hook   powered_cache_disable_js_defer_inline
				 *
				 * @param  {boolean} true to disable defer
				 *
				 * @return {boolean} New value.
				 * @since  3.2
				 */
				if ( apply_filters( 'powered_cache_disable_js_defer_inline', false, $script_content, $script_open_tag ) ) {
					return $matches[0]; // Return the script unchanged
				}

				return $script_open_tag . 'window.addEventListener("DOMContentLoaded", function() {(function($) {' . $script_content . '})(jQuery);});</script>';
			},
			$html
		);

		return $html;
	}

	/**
	 * Determine whether defer or not defer inline script
	 *
	 * @param string $script_content Script content
	 * @param string $script_attributes Script attributes
	 *
	 * @return bool
	 * @since 3.2
	 */
	private function should_defer_script( $script_content, $script_attributes ) {
		// Ignore scripts with a 'src' attribute (external scripts)
		if ( false !== strpos( $script_attributes, 'src=' ) ) {
			return false;
		}

		// ignore ld+json
		if ( false !== strpos( $script_attributes, 'type="application/ld+json"' ) ) {
			return false;
		}

		// Check if the script contains 'DOMContentLoaded' or 'document.write'
		if ( false !== strpos( $script_content, 'DOMContentLoaded' ) || false !== strpos( $script_content, 'document.write' ) ) {
			return false;
		}

		// Check if the script does NOT contain jQuery-related functions
		if ( false === strpos( $script_content, 'jQuery(' ) && false === strpos( $script_content, '$.(' ) && false === strpos( $script_content, '$(' ) ) {
			return false;
		}

		return true;
	}


}
