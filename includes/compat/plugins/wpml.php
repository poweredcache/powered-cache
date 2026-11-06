<?php
/**
 * Compat with WPML
 *
 * @package PoweredCache\Compat
 * @link    https://wpml.org/
 */

namespace PoweredCache\Compat\WPML;

use PoweredCache\Async\CachePurger;
use function PoweredCache\Utils\get_page_cache_dir;
use function PoweredCache\Utils\remove_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\SitePress' ) ) {

	add_action( 'powered_cache_create_config_file', __NAMESPACE__ . '\\generate_configuration_for_domains', 10, 3 );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\admin_bar_purge_cache_menu' );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\admin_bar_preload_cache_menu' );
	add_action( 'admin_post_powered_cache_purge_page_cache_for_lang', __NAMESPACE__ . '\\purge_page_cache' );
	add_filter( 'populate_preload_queue_urls', __NAMESPACE__ . '\\add_language_urls_to_queue' );
	add_filter( 'powered_cache_post_related_urls', __NAMESPACE__ . '\\add_post_language_urls', 10, 2 );
	add_action( 'wpml_after_save_post', __NAMESPACE__ . '\\purge_translated_post' );
	add_action( 'wpml_pro_translation_completed', __NAMESPACE__ . '\\purge_translated_post' );

	/**
	 * Resolve translated slugs, falling back to language conversion for non-object URLs.
	 *
	 * @param string $url            Original URL.
	 * @param string $language_code  Target language code.
	 * @param bool   $allow_fallback Whether unchanged URLs may use ordinary language conversion.
	 *
	 * @return string
	 */
	function resolve_language_url( $url, $language_code, $allow_fallback = true ) {
		global $wpml_url_converter;

		$translated_url = apply_filters( 'wpml_permalink', $url, $language_code, true );

		// Full resolution leaves home and archive URLs unchanged when no object is found.
		if ( $allow_fallback && untrailingslashit( (string) $translated_url ) === untrailingslashit( $url ) ) {
			$translated_url = apply_filters( 'wpml_permalink', $url, $language_code, false );

			// Editor REST requests can make WPML detect every URL's language from the referrer.
			// Its configured strategy converts the actual URL without changing request state.
			if ( untrailingslashit( (string) $translated_url ) === untrailingslashit( $url ) && is_object( $wpml_url_converter ) && is_callable( array( $wpml_url_converter, 'get_strategy' ) ) ) {
				$strategy = $wpml_url_converter->get_strategy();
				if ( is_object( $strategy ) && is_callable( array( $strategy, 'convert_url_string' ) ) ) {
					$converted_url = $strategy->convert_url_string( $url, $language_code );
					if ( is_string( $converted_url ) && '' !== $converted_url ) {
						$translated_url = esc_url_raw( $converted_url );
					}
				}
			}
		}

		return $translated_url;
	}

	/**
	 * Collect the saved post's taxonomy URLs without editor-language ID adjustment.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $language_code Post language code.
	 *
	 * @return array
	 */
	function get_post_taxonomy_urls( $post_id, $language_code ) {
		$taxonomies = array_filter(
			get_object_taxonomies( get_post_type( $post_id ), 'objects' ),
			function ( $taxonomy ) {
				return $taxonomy->public;
			}
		);
		if ( ! $taxonomies ) {
			return [];
		}

		$keep_term_ids = static function () {
			return true;
		};
		add_filter( 'wpml_disable_term_adjust_id', $keep_term_ids, PHP_INT_MAX );
		try {
			$terms = wp_get_object_terms(
				$post_id,
				array_keys( $taxonomies ),
				array(
					'suppress_filters' => true,
					'wpml_skip_filters' => true,
					'lang'              => 'all',
				)
			);
			if ( is_wp_error( $terms ) ) {
				return [];
			}

			$urls = [];
			foreach ( $terms as $term ) {
				$term_id  = apply_filters( 'wpml_object_id', $term->term_id, $term->taxonomy, true, $language_code );
				if ( ! $term_id ) {
					continue;
				}
				$term_ids = [ $term_id ];
				if ( $taxonomies[ $term->taxonomy ]->hierarchical ) {
					$term_ids = array_merge( $term_ids, get_ancestors( $term_id, $term->taxonomy, 'taxonomy' ) );
				}
				foreach ( $term_ids as $related_id ) {
					$url = get_term_link( (int) $related_id, $term->taxonomy );
					if ( ! is_wp_error( $url ) ) {
						$urls[] = resolve_language_url( $url, $language_code );
					}
				}
			}
			return $urls;
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $keep_term_ids, PHP_INT_MAX );
		}
	}

	/**
	 * Include the saved post's language archives and its other translations in purges.
	 *
	 * @param array $urls    Related URLs.
	 * @param int   $post_id Post ID.
	 *
	 * @return array
	 */
	function add_post_language_urls( $urls, $post_id = 0 ) {
		if ( ! $post_id ) {
			return $urls;
		}

		$language = apply_filters( 'wpml_post_language_details', null, $post_id );

		if ( is_array( $language ) && ! empty( $language['language_code'] ) ) {
			foreach ( $urls as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$urls[] = resolve_language_url( $url, $language['language_code'] );
				}
			}
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$urls = array_merge( $urls, get_post_taxonomy_urls( $post_id, $language['language_code'] ) );
				// WPML also adjusts the posts-page option to the editor's language.
				if ( 'page' === get_option( 'show_on_front' ) ) {
					$posts_page_id = get_option( 'page_for_posts' );
					if ( $posts_page_id ) {
						$posts_page_id = apply_filters( 'wpml_object_id', $posts_page_id, 'page', true, $language['language_code'] );
						if ( $posts_page_id ) {
							$urls[] = resolve_language_url( get_permalink( $posts_page_id ), $language['language_code'] );
						}
					}
				}
			}
		}

		$element_type = 'post_' . get_post_type( $post_id );
		$trid         = apply_filters( 'wpml_element_trid', null, $post_id, $element_type );

		if ( $trid ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid, $element_type );
			foreach ( (array) $translations as $translation ) {
				if ( empty( $translation->element_id ) || (int) $translation->element_id === (int) $post_id || 'publish' !== get_post_status( $translation->element_id ) ) {
					continue;
				}

				$url = get_permalink( $translation->element_id );
				if ( $url && ! empty( $translation->language_code ) ) {
					$urls[] = resolve_language_url( $url, $translation->language_code );
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Purge after WPML has attached a newly saved translation to its language group.
	 *
	 * @param int $post_id Saved translation ID.
	 */
	function purge_translated_post( $post_id ) {
		$settings = \PoweredCache\Utils\get_settings();
		if ( $settings['enable_page_cache'] && 'publish' === get_post_status( $post_id ) ) {
			\PoweredCache\AdvancedCache::factory()->purge_post( $post_id );
		}
	}

	/**
	 * Create separate configurations when WPML used in domain mapping mode
	 *
	 * @param string $config_file        Configuration path
	 * @param string $config_file_string Configuration content
	 * @param bool   $network_wide       whether powered cache activated network wide or not
	 *
	 * @since 2.2.2
	 */
	function generate_configuration_for_domains( $config_file, $config_file_string, $network_wide ) {
		global $sitepress;

		if ( $network_wide ) {
			return;
		}

		if ( ! defined( 'WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN' ) ) {
			return;
		}

		// Reuse WPML's instance: constructing another one registers duplicate query filters.
		if ( ! $sitepress ) {
			return;
		}

		$is_per_domain = WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $sitepress->get_setting( 'language_negotiation_type' );
		if ( ! $is_per_domain ) {
			return;
		}

		$domains    = (array) $sitepress->get_setting( 'language_domains' );
		$config_dir = WP_CONTENT_DIR . '/pc-config/';

		foreach ( $domains as $lang_code => $domain ) {
			$config_name = 'config-' . $domain . '.php';
			$config_file = $config_dir . $config_name;
			if ( ! file_exists( $config_file ) ) {
				file_put_contents( $config_file, $config_file_string ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			}
		}
	}


	/**
	 * Add purge button on admin bar
	 *
	 * @param object $wp_admin_bar Admin bar object
	 *
	 * @since 2.4
	 */
	function admin_bar_purge_cache_menu( $wp_admin_bar ) {
		global $sitepress;

		if ( current_user_can( 'manage_options' ) ) {

			if ( ! $sitepress ) {
				$sitepress = new \SitePress();
			}

			// 3 subdomain

			$negotiation_type = (int) $sitepress->get_setting( 'language_negotiation_type' );

			// 1: subdomain example.com/en
			// 2: domain mapping: example.org
			if ( ! in_array( $negotiation_type, [ 1, 2 ], true ) ) {
				return;
			}

			if ( ! method_exists( $sitepress, 'get_active_languages' ) ) {
				return;
			}

			$active_languages = $sitepress->get_active_languages();

			if ( ! $active_languages ) {
				return;
			}

			foreach ( $sitepress->get_active_languages() as $lang ) {
				$flag_url = $sitepress->get_flag_image( $lang['code'], [], '', [ 'icl_als_iclflag' ] );
				$wp_admin_bar->add_menu(
					[
						'parent' => 'advanced-cache-purge',
						'id'     => 'purge-all-' . $lang['code'],
						'title'  => $flag_url . '&nbsp;' . $lang['display_name'],
						'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache_for_lang&lang_code=' . $lang['code'] ), 'powered_cache_purge_page_cache_for_lang' ),
					]
				);

			}

			if ( count( $active_languages ) > 1 ) {
				$wp_admin_bar->add_menu(
					[
						'parent' => 'advanced-cache-purge',
						'id'     => 'purge-all',
						'title'  => '<img class="ab-icon" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png"> &nbsp;' . esc_html__( 'All languages', 'powered-cache' ),
						'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache' ), 'powered_cache_purge_page_cache' ),
					]
				);
			}
		}
	}

	/**
	 * Add preload menu items
	 *
	 * @param object $wp_admin_bar \WP_Admin_Bar
	 *
	 * @since 2.4
	 */
	function admin_bar_preload_cache_menu( $wp_admin_bar ) {
		global $sitepress;

		if ( POWERED_CACHE_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $sitepress ) {
			$sitepress = new \SitePress();
		}

		if ( ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return;
		}

		$active_languages = $sitepress->get_active_languages();

		if ( ! $active_languages ) {
			return;
		}

		foreach ( $sitepress->get_active_languages() as $lang ) {
			$flag_url = $sitepress->get_flag_image( $lang['code'], [], '', [ 'icl_als_iclflag' ] );
			$wp_admin_bar->add_menu(
				[
					'parent' => 'preload-cache',
					'id'     => 'preload-cache-' . $lang['code'],
					'title'  => $flag_url . '&nbsp;' . $lang['display_name'],
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_preload_cache&lang_code=' . $lang['code'] ), 'powered_cache_preload_cache' ),
				]
			);
		}

		if ( count( $active_languages ) > 1 ) {
			$wp_admin_bar->add_menu(
				[
					'parent' => 'preload-cache',
					'id'     => 'preload-all-languages',
					'title'  => '<img class="ab-icon" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png"> &nbsp;' . esc_html__( 'All languages', 'powered-cache' ),
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_preload_cache&lang_code=all' ), 'powered_cache_preload_cache' ),
				]
			);
		}

	}

	/**
	 * Purge language cache directory
	 *
	 * @since 2.4
	 */
	function purge_page_cache() {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'powered_cache_purge_page_cache_for_lang' ) ) {
			wp_nonce_ays( '' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			$lang = isset( $_GET['lang_code'] ) && 'all' !== $_GET['lang_code'] ? sanitize_key( $_GET['lang_code'] ) : '';

			$settings     = \PoweredCache\Utils\get_settings();
			$cache_purger = CachePurger::factory();

			if ( $settings['async_cache_cleaning'] ) {
				$cache_purger->push_to_queue(
					[
						'call'      => 'clean_site_cache_for_language',
						'lang_code' => $lang,
					]
				);
				$cache_purger->save()->dispatch();
			} else {
				clean_site_cache_for_language( $lang );
			}

			$lang_name    = $GLOBALS['sitepress']->get_display_language_name( $lang );
			$redirect_url = add_query_arg(
				[
					'pc_action' => 'flush_lang_cache',
					'language'  => rawurlencode( $lang_name ),
				],
				wp_get_referer()
			);

		} else {
			$redirect_url = add_query_arg( 'pc_action', 'flush_page_cache_err_permission', wp_get_referer() );
		}

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}


	/**
	 * Purge page cache for specific locale
	 *
	 * @param string $lang lang code
	 *
	 * @since 2.4
	 */
	function clean_site_cache_for_language( $lang ) {
		if ( ! $GLOBALS['sitepress'] || ! $lang ) {
			return;
		}

		if ( ! method_exists( $GLOBALS['sitepress'], 'language_url' ) ) {
			return;
		}

		$base_dir        = get_page_cache_dir();
		$language_url    = $GLOBALS['sitepress']->language_url( $lang );
		$site_url_parsed = wp_parse_url( $language_url );

		$site_path = $site_url_parsed['host'];

		if ( ! empty( $site_url_parsed['path'] ) ) {
			$site_path .= $site_url_parsed['path'];
		}

		$site_cache_dir = trailingslashit( $base_dir . $site_path );

		remove_dir( $site_cache_dir );
	}

	/**
	 * Modify preload queue for the language(s)
	 *
	 * @param array $urls Preload Urls in original language
	 *
	 * @return array $urls The list of preload urls
	 * @since 2.4
	 */
	function add_language_urls_to_queue( $urls ) {
		global $sitepress;

		if ( ! $sitepress ) {
			$sitepress = new \SitePress();
		}

		$lang_urls = [];
		$home_url  = untrailingslashit( home_url( '/' ) );
		// Only homes need the preload fallback; missing post/term translations must not get invented URLs.
		// preload particular language
		if ( isset( $_GET['lang_code'] ) && 'all' !== $_GET['lang_code'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( $urls as $url ) {
				$lang_urls[] = resolve_language_url( $url, sanitize_key( $_GET['lang_code'] ), untrailingslashit( $url ) === $home_url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			return array_values( array_unique( array_filter( $lang_urls ) ) );
		}

		if ( ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return $urls;
		}

		// preload all languages
		foreach ( $sitepress->get_active_languages() as $lang ) {
			foreach ( $urls as $url ) {
				$lang_urls[] = resolve_language_url( $url, $lang['code'], untrailingslashit( $url ) === $home_url );
			}
		}
		$urls = array_merge( $urls, $lang_urls );

		return array_values( array_unique( array_filter( $urls ) ) );
	}
}
