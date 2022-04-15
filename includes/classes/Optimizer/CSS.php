<?php
/**
 * CSS contact
 *
 * @package PoweredCache\Optimizer
 */

//phpcs:disable Squiz.Commenting.VariableComment.Missing
//phpcs:disable Generic.Commenting.DocComment.MissingShort
//phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
//phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

namespace PoweredCache\Optimizer;

use PoweredCache\CDN;
use \WP_Styles as WP_Styles;

/**
 * Class CSS
 */
class CSS extends WP_Styles {
	private $old_styles;
	public $allow_gzip_compression;
	public $do_minify;
	public $enable_cdn;

	/**
	 * CSS constructor.
	 *
	 * @param object $styles WP_Styles
	 */
	public function __construct( $styles ) {
		if ( empty( $styles ) || ! ( $styles instanceof WP_Styles ) ) {
			$this->old_styles = new WP_Styles();
		} else {
			$this->old_styles = $styles;
		}

		// Unset all the object properties except our private copy of the styles object.
		// We have to unset everything so that the overload methods talk to $this->old_styles->whatever
		// instead of $this->whatever.
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( 'old_styles' === $key ) {
				continue;
			}
			unset( $this->$key );
		}
	}

	/**
	 * Prep items for concat
	 *
	 * @param bool $handles CSS handler
	 * @param bool $group   CSS group
	 *
	 * @return string[]
	 */
	public function do_items( $handles = false, $group = false ) {
		$abspath     = wp_normalize_path( ABSPATH );
		$handles     = false === $handles ? $this->queue : (array) $handles;
		$stylesheets = array();
		/**
		 * Filters site url
		 *
		 * @hook   powered_cache_fo_site_url
		 *
		 * @param  {string} $base_url Site URL.
		 *
		 * @return {string} New value.
		 * @since  2.0
		 */
		$siteurl = apply_filters( 'powered_cache_fo_site_url', $this->base_url );
		$this->all_deps( $handles );

		$stylesheet_group_index = 0;
		foreach ( $this->to_do as $key => $handle ) {
			$obj = $this->registered[ $handle ];

			$obj->src = apply_filters( 'style_loader_src', $obj->src, $obj->handle );

			// Core is kind of broken and returns "true" for src of "colors" handle
			// http://core.trac.wordpress.org/attachment/ticket/16827/colors-hacked-fixed.diff
			// http://core.trac.wordpress.org/ticket/20729
			$css_url = $obj->src;
			if ( 'colors' === $obj->handle && true === $css_url ) {
				$css_url = wp_style_loader_src( $css_url, $obj->handle );
			}

			$css_url_parsed = wp_parse_url( $obj->src );
			$extra          = $obj->extra;

			// Don't concat by default
			$do_concat = false;

			// Only try to concat static css files
			if ( false !== strpos( $css_url_parsed['path'], '.css' ) ) {
				$do_concat = true;
			}

			// Don't try to concat styles which are loaded conditionally (like IE stuff)
			if ( isset( $extra['conditional'] ) ) {
				$do_concat = false;
			}

			// Don't concat rtl stuff for now until concat supports it correctly
			if ( 'rtl' === $this->text_direction && ! empty( $extra['rtl'] ) ) {
				$do_concat = false;
			}

			// Don't try to concat externally hosted scripts
			$is_internal_url = Helper::is_internal_url( $css_url, $siteurl );
			if ( ! $is_internal_url ) {
				$do_concat = false;
			}

			// Concat and canonicalize the paths only for
			// existing scripts that aren't outside $abspath
			$css_realpath = Helper::realpath( $css_url, $siteurl );
			if ( ! $css_realpath || 0 !== strpos( $css_realpath, $abspath ) ) {
				$do_concat = false;
			} else {
				$css_url_parsed['path'] = substr( $css_realpath, strlen( $abspath ) - 1 );
			}

			if ( Helper::is_excluded_css( $css_url ) ) {
				$do_concat = false;
			}

			/**
			 * Allow plugins to disable concatenation of certain stylesheets.
			 *
			 * @hook   powered_cache_fo_css_do_concat
			 *
			 * @param  {boolean} $do_concat Contact status.
			 * @param  {string} $handle Handle of registered CSS item.
			 *
			 * @return {boolean} New value.
			 * @since  2.0
			 */
			$do_concat = apply_filters( 'powered_cache_fo_css_do_concat', $do_concat, $handle );

			if ( true === $do_concat ) {
				$media = $obj->args;
				if ( empty( $media ) ) {
					$media = 'all';
				}
				if ( ! isset( $stylesheets[ $stylesheet_group_index ] ) || ( isset( $stylesheets[ $stylesheet_group_index ] ) && ! is_array( $stylesheets[ $stylesheet_group_index ] ) ) ) {
					$stylesheets[ $stylesheet_group_index ] = array();
				}

				$stylesheets[ $stylesheet_group_index ][ $media ][ $handle ] = $css_url_parsed['path'];
				$this->done[] = $handle;
			} else {
				$stylesheet_group_index ++;
				$stylesheets[ $stylesheet_group_index ]['noconcat'][] = $handle;
				$stylesheet_group_index ++;
			}
			unset( $this->to_do[ $key ] );
		}

		foreach ( $stylesheets as $idx => $stylesheets_group ) {
			foreach ( $stylesheets_group as $media => $css ) {
				if ( 'noconcat' === $media ) {

					foreach ( $css as $handle ) {
						if ( $this->do_item( $handle, $group ) ) {
							$this->done[] = $handle;
						}
					}
					continue;
				} elseif ( count( $css ) > 1 ) {
					$paths    = array_map(
						function ( $url ) use ( $abspath ) {
							return $abspath . $url;
						},
						$css
					);
					$mtime    = max( array_map( 'filemtime', $paths ) );
					$path_str = implode( ',', $css ) . "?m={$mtime}";

					if ( $this->allow_gzip_compression ) {
						$path_64 = base64_encode( gzcompress( $path_str ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						if ( strlen( $path_str ) > ( strlen( $path_64 ) + 1 ) ) {
							$path_str = '-' . $path_64;
						}
					}

					$href = Helper::get_optimized_url( $path_str, $this->do_minify );
				} else {
					if ( $this->do_minify ) {
						$href = Helper::get_optimized_url( current( $css ), $this->do_minify );
					} else {
						$href = $this->cache_bust_mtime( $siteurl . current( $css ), $siteurl );
					}
				}

				$handles = array_keys( $css );
				$css_tag = "<link rel='stylesheet' id='$media-css-$idx' href='$href' type='text/css' media='$media' />\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				/**
				 * Filters combined CSS tag.
				 *
				 * @hook   powered_cache_fo_style_loader_tag
				 *
				 * @param  {string} $css_tag CSS tag.
				 * @param  {array} $handles The list of CSS handles.
				 * @param  {string} $href CSS URL.
				 * @param  {string} $media Media type.
				 *
				 * @return {string} New value.
				 * @since  2.0
				 */
				echo apply_filters( 'powered_cache_fo_style_loader_tag', $css_tag, $handles, $href, $media ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array_map( array( $this, 'print_inline_style' ), array_keys( $css ) );
			}
		}

		return $this->done;
	}

	/**
	 * Bust cache
	 *
	 * @param string $url     CSS URL
	 * @param string $siteurl Site URL
	 *
	 * @return string
	 */
	public function cache_bust_mtime( $url, $siteurl ) {
		if ( strpos( $url, '?m=' ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( ! isset( $parts['path'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$file = Helper::realpath( $url, $siteurl );

		$mtime = false;
		if ( file_exists( $file ) ) {
			$mtime = filemtime( $file );
		}

		if ( ! $mtime ) {
			return $url;
		}

		if ( false === strpos( $url, '?' ) ) {
			$q = '';
		} else {
			list( $url, $q ) = explode( '?', $url, 2 );
			if ( strlen( $q ) ) {
				$q = '&amp;' . $q;
			}
		}

		return "$url?m={$mtime}g{$q}";
	}

	/**
	 * @param $key
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->old_styles->$key );
	}

	/**
	 * @param $key
	 */
	public function __unset( $key ) {
		unset( $this->old_styles->$key );
	}

	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	public function &__get( $key ) {
		return $this->old_styles->$key;
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function __set( $key, $value ) {
		$this->old_styles->$key = $value;
	}
}
