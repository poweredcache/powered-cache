<?php
/**
 * JS Optimizer
 * Credits: https://github.com/Automattic/nginx-http-concat/blob/master/jsconcat.php
 *
 * @package PoweredCache\Optimizer
 */

namespace PoweredCache\Optimizer;

use \WP_Scripts as WP_Scripts;

//phpcs:disable Squiz.Commenting.VariableComment.Missing
//phpcs:disable Generic.Commenting.DocComment.MissingShort
//phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
//phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
//phpcs:disable Squiz.Commenting.FunctionComment.MissingParamComment


/**
 * Class JS
 */
class JS extends WP_Scripts {
	private $old_scripts;
	public $allow_gzip_compression;
	public $do_minify;
	public $excluded_files = [];

	/**
	 * JS constructor.
	 *
	 * @param object $scripts \WP_Scripts
	 */
	public function __construct( $scripts ) {
		if ( empty( $scripts ) || ! ( $scripts instanceof WP_Scripts ) ) {
			$this->old_scripts = new WP_Scripts();
		} else {
			$this->old_scripts = $scripts;
		}

		// Unset all the object properties except our private copy of the scripts object.
		// We have to unset everything so that the overload methods talk to $this->old_scripts->whatever
		// instead of $this->whatever.
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( 'old_scripts' === $key ) {
				continue;
			}
			unset( $this->$key );
		}
	}

	/**
	 * Check given handle has inline JS
	 *
	 * @param string $handle JS handle
	 *
	 * @return bool
	 */
	protected function has_inline_content( $handle ) {
		$before_output = $this->get_data( $handle, 'before' );
		if ( ! empty( $before_output ) ) {
			return true;
		}

		$after_output = $this->get_data( $handle, 'after' );
		if ( ! empty( $after_output ) ) {
			return true;
		}

		// JavaScript translations
		$has_translations = ! empty( $this->registered[ $handle ]->textdomain );
		if ( $has_translations ) {
			return true;
		}

		return false;
	}

	/**
	 * Prep items for concat
	 *
	 * @param bool $handles
	 * @param bool $group
	 *
	 * @return array|string[]
	 */
	public function do_items( $handles = false, $group = false ) {
		$abspath     = wp_normalize_path( ABSPATH );
		$handles     = false === $handles ? $this->queue : (array) $handles;
		$javascripts = array();
		$siteurl     = apply_filters( 'powered_cache_fo_site_url', $this->base_url );

		$this->all_deps( $handles );
		$level = 0;

		foreach ( $this->to_do as $key => $handle ) {
			if ( in_array( $handle, $this->done, true ) || ! isset( $this->registered[ $handle ] ) ) {
				continue;
			}

			if ( 0 === $group && $this->groups[ $handle ] > 0 ) {
				$this->in_footer[] = $handle;
				unset( $this->to_do[ $key ] );
				continue;
			}

			if ( ! $this->registered[ $handle ]->src ) { // Defines a group.
				// if there are localized items, echo them
				$this->print_extra_script( $handle );
				$this->done[] = $handle;
				continue;
			}

			if ( false === $group && in_array( $handle, $this->in_footer, true ) ) {
				$this->in_footer = array_diff( $this->in_footer, (array) $handle );
			}

			$obj           = $this->registered[ $handle ];
			$js_url        = $obj->src;
			$js_url_parsed = wp_parse_url( $js_url );
			$extra         = $obj->extra;

			// Don't concat by default
			$do_concat = false;

			// Only try to concat static js files
			if ( false !== strpos( $js_url_parsed['path'], '.js' ) ) {
				$do_concat = true;
			}

			// Don't try to concat externally hosted scripts
			$is_internal_url = Helper::is_internal_url( $js_url, $siteurl );
			if ( ! $is_internal_url ) {
				$do_concat = false;
			}

			// Concat and canonicalize the paths only for
			// existing scripts that aren't outside ABSPATH

			$js_realpath = Helper::realpath( $js_url, $siteurl );
			if ( ! $js_realpath || 0 !== strpos( $js_realpath, $abspath ) ) {
				$do_concat = false;
			} else {
				$js_url_parsed['path'] = substr( $js_realpath, strlen( $abspath ) - 1 );
			}

			if ( $this->has_inline_content( $handle ) ) {
				$do_concat = false;
			}

			// Skip core scripts that use Strict Mode
			if ( 'react' === $handle || 'react-dom' === $handle ) {
				$do_concat = false;
			}

			if ( Helper::is_excluded_js( $js_url ) ) {
				$do_concat = false;
			}

			/**
			 * Allow plugins to disable concatenation of certain scripts.
			 *
			 * @hook   powered_cache_fo_js_do_concat
			 *
			 * @param  {boolean} $do_concat Contact status.
			 * @param  {string} $handle Handle of script.
			 *
			 * @return {boolean} New value.
			 * @since  2.0
			 */
			$do_concat = apply_filters( 'powered_cache_fo_js_do_concat', $do_concat, $handle );

			if ( true === $do_concat ) {
				if ( ! isset( $javascripts[ $level ] ) ) {
					$javascripts[ $level ]['type'] = 'concat';
				}

				$javascripts[ $level ]['paths'][]   = $js_url_parsed['path'];
				$javascripts[ $level ]['handles'][] = $handle;

			} else {
				$level ++;
				$javascripts[ $level ]['type']   = 'do_item';
				$javascripts[ $level ]['handle'] = $handle;
				$level ++;
			}
			unset( $this->to_do[ $key ] );
		}

		if ( empty( $javascripts ) ) {
			return $this->done;
		}

		foreach ( $javascripts as $js_array ) {
			if ( 'do_item' === $js_array['type'] ) {
				if ( $this->do_item( $js_array['handle'], $group ) ) {
					$this->done[] = $js_array['handle'];
				}
			} elseif ( 'concat' === $js_array['type'] ) {
				array_map( array( $this, 'print_extra_script' ), $js_array['handles'] );

				if ( isset( $js_array['paths'] ) && count( $js_array['paths'] ) > 1 ) {
					$paths    = array_map(
						function ( $url ) use ( $abspath ) {
							return $abspath . $url;
						},
						$js_array['paths']
					);
					$mtime    = max( array_map( 'filemtime', $paths ) );
					$path_str = implode( ',', $js_array['paths'] ) . "?m=${mtime}j";

					if ( $this->allow_gzip_compression ) {
						$path_64 = base64_encode( gzcompress( $path_str ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						if ( strlen( $path_str ) > ( strlen( $path_64 ) + 1 ) ) {
							$path_str = '-' . $path_64;
						}
					}

					$href = Helper::get_optimized_url( $path_str, $this->do_minify );
				} elseif ( isset( $js_array['paths'] ) && is_array( $js_array['paths'] ) ) {
					$href = $this->cache_bust_mtime( $siteurl . $js_array['paths'][0], $siteurl );
				}

				$this->done = array_merge( $this->done, $js_array['handles'] );

				// Print before/after scripts from wp_inline_scripts() and concatenated script tag
				if ( isset( $js_array['extras']['before'] ) ) {
					foreach ( $js_array['extras']['before'] as $inline_before ) {
						echo $inline_before; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
				if ( isset( $href ) ) {
					/**
					 * Filters combined script loader tags.
					 *
					 * @hook   powered_cache_fo_script_loader_tag
					 *
					 * @param  {string} $js_tag JS tag.
					 *
					 * @return {string} New value.
					 * @since  2.0
					 */
					echo apply_filters( 'powered_cache_fo_script_loader_tag', "<script type='text/javascript' src='$href'></script>", $href ); // phpcs:ignore
					echo PHP_EOL;
				}
				if ( isset( $js_array['extras']['after'] ) ) {
					foreach ( $js_array['extras']['after'] as $inline_after ) {
						echo $inline_after; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
			}
		}

		/**
		 * Fires after combine JS
		 *
		 * @hook  powered_cache_fo_js_concat_did_items
		 *
		 * @param {array} $javascripts JS resources.
		 *
		 * @since 2.0
		 */
		do_action( 'powered_cache_fo_js_concat_did_items', $javascripts );

		return $this->done;
	}

	/**
	 * Bust cache
	 *
	 * @param string $url     URL
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
		return isset( $this->old_scripts->$key );
	}

	/**
	 * @param $key
	 */
	public function __unset( $key ) {
		unset( $this->old_scripts->$key );
	}

	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	public function &__get( $key ) {
		return $this->old_scripts->$key;
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function __set( $key, $value ) {
		$this->old_scripts->$key = $value;
	}
}
