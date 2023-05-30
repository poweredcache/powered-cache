<?php
/**
 * File optimizer (minify, contact) related functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Dependencies\voku\helper\HtmlMin;
use const PoweredCache\Constants\POST_META_DISABLE_CSS_OPTIMIZATION;
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
		add_action( 'after_setup_theme', [ $this, 'process_buffer' ], 999 );
		add_action( 'template_redirect', [ $this, 'maybe_suppress_optimizations' ] );

		if ( ! $this->settings['combine_js'] ) {
			add_filter( 'powered_cache_fo_js_do_concat', '__return_false' );
		}

		if ( ! $this->settings['combine_css'] ) {
			add_filter( 'powered_cache_fo_css_do_concat', '__return_false' );
		}

		if ( ! $this->settings['js_execution_optimized_only'] ) {
			add_filter( 'script_loader_tag', [ $this, 'change_js_execute_method' ], 99 );
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
	public function process_buffer() {
		if ( strpos( $_SERVER['REQUEST_URI'], 'robots.txt' ) !== false || strpos( $_SERVER['REQUEST_URI'], '.htaccess' ) !== false ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$file_extension = $_SERVER['REQUEST_URI'];
		$file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $file_extension );
		$file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

		if ( ! preg_match( '#index\.php$#i', $_SERVER['REQUEST_URI'] ) && in_array( $file_extension, array( 'php', 'xml', 'xsl' ), true ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		ob_start( [ $this, 'maybe_delay_scripts' ] );
		ob_start( [ $this, 'maybe_replace_google_fonts_with_bunny_fonts' ] );
		ob_start( [ $this, 'maybe_minify_html' ] );
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

			if ( $disable_css_optimization ) {
				add_filter( 'powered_cache_fo_css_do_concat', '__return_false' );
				add_filter( 'powered_cache_fo_disable_css_minify', '__return_true' );
			}

			if ( $disable_js_optimization ) {
				add_filter( 'powered_cache_fo_js_do_concat', '__return_false' );
				add_filter( 'powered_cache_fo_disable_js_minify', '__return_true' );
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
		$html_min->overwriteSpecialScriptTags( [ 'x-tmpl-mustache', 'text/template' ] );
		$buffer = $html_min->minify( $buffer );

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
		$js_execution = $this->settings['js_execution_method'];
		if ( ! $js_execution ) {
			return $tag;
		}

		if ( 'blocking' === $js_execution ) {
			return $tag;
		}

		// @since 3.0 - rewrite dom for delayed option
		if ( 'delayed' === $js_execution ) {
			return $tag;
		}

		if ( 'async' === $js_execution ) {
			$js_attr = 'async="async"';
		} elseif ( 'defer' === $js_execution ) {
			$js_attr = 'defer="defer"';
		}

		if ( false === stripos( $tag, $js_attr ) ) {
			$search  = '<script ';
			$replace = sprintf( '<script %s ', $js_attr );
			$tag     = str_replace( $search, $replace, $tag );
		}

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
		 *
		 * @return {boolean} New value.
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_fo_disable_js_minify', false ) ) {
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
		if ( 'delayed' !== $this->settings['js_execution_method'] ) {
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

			// when it's enabled for optimized scripts only
			if ( $this->settings['js_execution_optimized_only'] && false === strpos( $script, 'file-optimizer.php' ) ) {
				continue;
			}

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

			if ( strpos( $script, 'type=' ) === false || strpos( $script, "type='text/javascript'" ) !== false ) {
				$new_script = $script;

				// Check if script has a src attribute
				if ( strpos( $attributes, 'src=' ) !== false ) {
					$new_script = preg_replace( "/src=[\"\']([^\"\']*)[\"\']/", 'data-src="$1"', $new_script );
					$new_script = preg_replace( '/<script([^>]*)>/', '<script data-type="lazy"$1>', $new_script );
				} else {
					$new_content = 'data:text/javascript;base64,' . base64_encode( $content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					$new_script  = preg_replace( '/<script([^>]*)>/', "<script data-src=\"$new_content\" data-type=\"lazy\"$1>", $new_script ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
					$new_script  = preg_replace( '/<script([^>]*)>(.*?)<\/script>/si', '<script data-type="lazy"$1></script>', $new_script );
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
		 * @param  {int} $delay_in_ms Delay time in microsecond
		 *
		 * @return {int} New value.
		 * @since  3.0
		 */
		$delay_timeout = apply_filters( 'powered_cache_delayed_js_timeout', 5000 );

		$html .= '<script id="powered-cache-delayed-js">';
		$html .= 'const scriptLoader={loadDelay:' . absint( $delay_timeout ) . ',loadTimer:null,userInteractionEvents:["mouseover","click","keydown","wheel","touchmove","touchstart"],init(){for(const e of this.userInteractionEvents)window.addEventListener(e,this.triggerLoader,{passive:!0});this.loadTimer=setTimeout(this.loadScripts,this.loadDelay)},triggerLoader(){scriptLoader.loadScripts(),clearTimeout(scriptLoader.loadTimer);for(const e of scriptLoader.userInteractionEvents)window.removeEventListener(e,scriptLoader.triggerLoader,{passive:!0})},loadScripts(){document.querySelectorAll("script[data-type=\'lazy\']").forEach((e=>{e.setAttribute("src",e.getAttribute("data-src"))})),console.log("Script(s) loaded with delay")}};scriptLoader.init();';
		$html .= '</script>';

		return $html;
	}


}
