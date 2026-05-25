<?php
/**
 * Settings schema.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Read-only schema for existing Powered Cache settings.
 *
 * The schema intentionally describes the current option array without changing
 * storage or save behavior. It is the foundation for 4.0 migrations, REST
 * responses, UI grouping, and recommended mode.
 *
 * @since 4.0.0
 */
class SettingsSchema {

	const TYPE_BOOLEAN = 'boolean';
	const TYPE_INTEGER = 'integer';
	const TYPE_STRING  = 'string';
	const TYPE_ARRAY   = 'array';
	const TYPE_ENUM    = 'enum';

	const SANITIZE_BOOLEAN  = 'boolean';
	const SANITIZE_INTEGER  = 'integer';
	const SANITIZE_TEXT     = 'text';
	const SANITIZE_TEXTAREA = 'textarea';
	const SANITIZE_ARRAY    = 'array';
	const SANITIZE_ENUM     = 'enum';

	/**
	 * Return the schema for all current settings.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields( array $context = array() ) {
		$is_apache                 = isset( $context['is_apache'] ) ? (bool) $context['is_apache'] : false;
		$object_cache_backends     = self::object_cache_backends( $context );
		$object_cache_backend_enum = array_merge( array( 'off' ), $object_cache_backends );

		return array(
			'enable_page_cache'                => self::field( self::TYPE_BOOLEAN, true, 'cache', self::SANITIZE_BOOLEAN ),
			'object_cache'                     => self::field( self::TYPE_ENUM, 'off', 'cache', self::SANITIZE_ENUM, false, array(), $object_cache_backend_enum ),
			'cache_mobile'                     => self::field( self::TYPE_BOOLEAN, true, 'cache', self::SANITIZE_BOOLEAN ),
			'cache_mobile_separate_file'       => self::field( self::TYPE_BOOLEAN, false, 'cache', self::SANITIZE_BOOLEAN, false, array( 'cache_mobile' ) ),
			'loggedin_user_cache'              => self::field( self::TYPE_BOOLEAN, false, 'cache', self::SANITIZE_BOOLEAN ),
			'ssl_cache'                        => self::field( self::TYPE_BOOLEAN, true, 'cache', self::SANITIZE_BOOLEAN, false, array(), array(), true ),
			'gzip_compression'                 => self::field( self::TYPE_BOOLEAN, false, 'cache', self::SANITIZE_BOOLEAN ),
			'cache_timeout'                    => self::field( self::TYPE_INTEGER, 1440, 'cache', self::SANITIZE_INTEGER ),
			'auto_configure_htaccess'          => self::field( self::TYPE_BOOLEAN, $is_apache, 'advanced', self::SANITIZE_BOOLEAN ),
			'rejected_user_agents'             => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'rejected_cookies'                 => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'rejected_referrers'               => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'vary_cookies'                     => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'rejected_uri'                     => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'ignored_query_strings'            => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'cache_query_strings'              => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'purge_additional_pages'           => self::field( self::TYPE_STRING, '', 'advanced', self::SANITIZE_TEXTAREA ),
			'minify_html'                      => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'minify_html_dom_optimization'     => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN, false, array( 'minify_html' ) ),
			'combine_google_fonts'             => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'swap_google_fonts_display'        => self::field( self::TYPE_BOOLEAN, true, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'use_bunny_fonts'                  => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'minify_css'                       => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'combine_css'                      => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'critical_css'                     => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN, true ),
			'critical_css_additional_files'    => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'critical_css' ) ),
			'critical_css_excluded_files'      => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'critical_css' ) ),
			'critical_css_appended_content'    => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'critical_css' ) ),
			'critical_css_fallback'            => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'critical_css' ) ),
			'excluded_css_files'               => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA ),
			'remove_unused_css'                => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN, true ),
			'ucss_safelist'                    => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'remove_unused_css' ) ),
			'ucss_excluded_files'              => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, true, array( 'remove_unused_css' ) ),
			'css_optimization_disabled_sources' => self::field( self::TYPE_ARRAY, array(), 'file_optimization', self::SANITIZE_ARRAY, true ),
			'minify_js'                        => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'combine_js'                       => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'excluded_js_files'                => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA ),
			'js_execution_method'              => self::field( self::TYPE_ENUM, 'blocking', 'file_optimization', self::SANITIZE_ENUM, false, array(), array( 'blocking', 'async', 'defer', 'delay', 'delayed' ), true ),
			'js_defer'                         => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'js_defer_exclusions'              => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, false, array( 'js_defer' ) ),
			'js_delay'                         => self::field( self::TYPE_BOOLEAN, false, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'js_delay_exclusions'              => self::field( self::TYPE_STRING, '', 'file_optimization', self::SANITIZE_TEXTAREA, false, array( 'js_delay' ) ),
			'js_delay_timeout'                 => self::field( self::TYPE_INTEGER, 0, 'file_optimization', self::SANITIZE_INTEGER, false, array( 'js_delay' ) ),
			'js_execution_optimized_only'      => self::field( self::TYPE_BOOLEAN, true, 'file_optimization', self::SANITIZE_BOOLEAN, false, array(), array(), true ),
			'rewrite_file_optimizer'           => self::field( self::TYPE_BOOLEAN, $is_apache, 'file_optimization', self::SANITIZE_BOOLEAN ),
			'enable_image_optimization'        => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN, true ),
			'image_optimizer_preferred_format' => self::field( self::TYPE_STRING, '', 'media', self::SANITIZE_TEXT, true, array( 'enable_image_optimization' ) ),
			'add_missing_image_dimensions'     => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN, true ),
			'enable_lazy_load'                 => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN ),
			'lazy_load_post_content'           => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_images'                 => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_iframes'                => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_widgets'                => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_post_thumbnail'         => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_avatars'                => self::field( self::TYPE_BOOLEAN, true, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_youtube'                => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN, false, array( 'enable_lazy_load' ) ),
			'lazy_load_skip_first_nth_img'     => self::field( self::TYPE_INTEGER, 3, 'media', self::SANITIZE_INTEGER, false, array( 'enable_lazy_load' ) ),
			'lazy_load_exclusions'             => self::field( self::TYPE_STRING, '', 'media', self::SANITIZE_TEXTAREA, false, array( 'enable_lazy_load' ) ),
			'disable_wp_lazy_load'             => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN ),
			'disable_wp_embeds'                => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN ),
			'disable_emoji_scripts'            => self::field( self::TYPE_BOOLEAN, false, 'media', self::SANITIZE_BOOLEAN ),
			'enable_cdn'                       => self::field( self::TYPE_BOOLEAN, false, 'cdn', self::SANITIZE_BOOLEAN ),
			'cdn_hostname'                     => self::field( self::TYPE_ARRAY, array( '' ), 'cdn', self::SANITIZE_ARRAY, false, array( 'enable_cdn' ) ),
			'cdn_zone'                         => self::field( self::TYPE_ARRAY, array( '' ), 'cdn', self::SANITIZE_ARRAY, false, array( 'enable_cdn' ) ),
			'cdn_rejected_files'               => self::field( self::TYPE_STRING, '', 'cdn', self::SANITIZE_TEXTAREA, false, array( 'enable_cdn' ) ),
			'enable_cache_preload'             => self::field( self::TYPE_BOOLEAN, false, 'preload', self::SANITIZE_BOOLEAN ),
			'preload_homepage'                 => self::field( self::TYPE_BOOLEAN, true, 'preload', self::SANITIZE_BOOLEAN, false, array( 'enable_cache_preload' ) ),
			'preload_public_posts'             => self::field( self::TYPE_BOOLEAN, true, 'preload', self::SANITIZE_BOOLEAN, false, array( 'enable_cache_preload' ) ),
			'preload_public_tax'               => self::field( self::TYPE_BOOLEAN, true, 'preload', self::SANITIZE_BOOLEAN, false, array( 'enable_cache_preload' ) ),
			'enable_sitemap_preload'           => self::field( self::TYPE_BOOLEAN, false, 'preload', self::SANITIZE_BOOLEAN, true ),
			'preload_request_interval'         => self::field( self::TYPE_INTEGER, 2, 'preload', self::SANITIZE_INTEGER ),
			'preload_sitemap'                  => self::field( self::TYPE_STRING, '', 'preload', self::SANITIZE_TEXTAREA, true, array( 'enable_sitemap_preload' ) ),
			'prefetch_dns'                     => self::field( self::TYPE_STRING, '', 'preload', self::SANITIZE_TEXTAREA ),
			'preconnect_resource'              => self::field( self::TYPE_STRING, '', 'preload', self::SANITIZE_TEXTAREA ),
			'preload_fonts'                    => self::field( self::TYPE_STRING, '', 'preload', self::SANITIZE_TEXTAREA ),
			'prefetch_links'                   => self::field( self::TYPE_BOOLEAN, false, 'preload', self::SANITIZE_BOOLEAN, true ),
			'enable_lcp_optimization'          => self::field( self::TYPE_BOOLEAN, false, 'preload', self::SANITIZE_BOOLEAN, true ),
			'db_cleanup_post_revisions'        => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_auto_drafts'           => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_trashed_posts'         => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_spam_comments'         => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_trashed_comments'      => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_expired_transients'    => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_all_transients'        => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'db_cleanup_optimize_tables'       => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN ),
			'enable_scheduled_db_cleanup'      => self::field( self::TYPE_BOOLEAN, false, 'database', self::SANITIZE_BOOLEAN, true ),
			'scheduled_db_cleanup_frequency'   => self::field( self::TYPE_ENUM, 'daily', 'database', self::SANITIZE_ENUM, true, array( 'enable_scheduled_db_cleanup' ), array( 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly' ) ),
			'enable_cloudflare'                => self::field( self::TYPE_BOOLEAN, false, 'integrations', self::SANITIZE_BOOLEAN ),
			'cloudflare_api_token'             => self::field( self::TYPE_STRING, '', 'integrations', self::SANITIZE_TEXT, false, array( 'enable_cloudflare' ) ),
			'cloudflare_email'                 => self::field( self::TYPE_STRING, '', 'integrations', self::SANITIZE_TEXT, false, array( 'enable_cloudflare' ) ),
			'cloudflare_api_key'               => self::field( self::TYPE_STRING, '', 'integrations', self::SANITIZE_TEXT, false, array( 'enable_cloudflare' ) ),
			'cloudflare_zone'                  => self::field( self::TYPE_STRING, '', 'integrations', self::SANITIZE_TEXT, false, array( 'enable_cloudflare' ) ),
			'enable_heartbeat'                 => self::field( self::TYPE_BOOLEAN, false, 'integrations', self::SANITIZE_BOOLEAN ),
			'heartbeat_dashboard_status'       => self::field( self::TYPE_ENUM, 'enable', 'integrations', self::SANITIZE_ENUM, false, array( 'enable_heartbeat' ), array( 'enable', 'disable', 'modify' ) ),
			'heartbeat_dashboard_interval'     => self::field( self::TYPE_INTEGER, 60, 'integrations', self::SANITIZE_INTEGER, false, array( 'enable_heartbeat' ) ),
			'heartbeat_editor_status'          => self::field( self::TYPE_ENUM, 'enable', 'integrations', self::SANITIZE_ENUM, false, array( 'enable_heartbeat' ), array( 'enable', 'disable', 'modify' ) ),
			'heartbeat_editor_interval'        => self::field( self::TYPE_INTEGER, 15, 'integrations', self::SANITIZE_INTEGER, false, array( 'enable_heartbeat' ) ),
			'heartbeat_frontend_status'        => self::field( self::TYPE_ENUM, 'enable', 'integrations', self::SANITIZE_ENUM, false, array( 'enable_heartbeat' ), array( 'enable', 'disable', 'modify' ) ),
			'heartbeat_frontend_interval'      => self::field( self::TYPE_INTEGER, 60, 'integrations', self::SANITIZE_INTEGER, false, array( 'enable_heartbeat' ) ),
			'enable_varnish'                   => self::field( self::TYPE_BOOLEAN, false, 'integrations', self::SANITIZE_BOOLEAN, true ),
			'varnish_ip'                       => self::field( self::TYPE_STRING, '', 'integrations', self::SANITIZE_TEXTAREA, true, array( 'enable_varnish' ) ),
			'cache_footprint'                  => self::field( self::TYPE_BOOLEAN, true, 'misc', self::SANITIZE_BOOLEAN ),
			'async_cache_cleaning'             => self::field( self::TYPE_BOOLEAN, false, 'misc', self::SANITIZE_BOOLEAN ),
			'dev_mode'                         => self::field( self::TYPE_BOOLEAN, false, 'misc', self::SANITIZE_BOOLEAN ),
			'enable_google_tracking'           => self::field( self::TYPE_BOOLEAN, false, 'integrations', self::SANITIZE_BOOLEAN, true ),
			'enable_fb_tracking'               => self::field( self::TYPE_BOOLEAN, false, 'integrations', self::SANITIZE_BOOLEAN, true ),
		);
	}

	/**
	 * Return defaults keyed by legacy option name.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults( array $context = array() ) {
		$defaults = array();

		foreach ( self::fields( $context ) as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * Return recommended setup values keyed by legacy option name.
	 *
	 * This intentionally keeps the first recommended profile conservative:
	 * page cache, safe minification, font display behavior, lazy loading, and
	 * preload are enabled while combining and delayed JavaScript remain manual.
	 *
	 * @param array     $context Runtime context used by dynamic defaults.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return array<string,mixed>
	 */
	public static function recommended( array $context = array(), $premium_available = null ) {
		$is_apache   = isset( $context['is_apache'] ) ? (bool) $context['is_apache'] : false;
		$recommended = array(
			'enable_page_cache'            => true,
			'cache_mobile'                 => true,
			'cache_mobile_separate_file'   => false,
			'loggedin_user_cache'          => false,
			'gzip_compression'             => true,
			'cache_timeout'                => 1440,
			'auto_configure_htaccess'      => $is_apache,
			'minify_html'                  => true,
			'minify_html_dom_optimization' => true,
			'combine_google_fonts'         => true,
			'swap_google_fonts_display'    => true,
			'minify_css'                   => true,
			'combine_css'                  => false,
			'minify_js'                    => true,
			'combine_js'                   => false,
			'js_defer'                     => false,
			'js_delay'                     => false,
			'rewrite_file_optimizer'       => $is_apache,
			'enable_lazy_load'             => true,
			'lazy_load_post_content'       => true,
			'lazy_load_images'             => true,
			'lazy_load_iframes'            => true,
			'lazy_load_widgets'            => true,
			'lazy_load_post_thumbnail'     => true,
			'lazy_load_avatars'            => true,
			'lazy_load_youtube'            => true,
			'lazy_load_skip_first_nth_img' => 3,
			'disable_wp_embeds'            => true,
			'disable_emoji_scripts'        => true,
			'enable_cache_preload'         => true,
			'preload_homepage'             => true,
			'preload_public_posts'         => true,
			'preload_public_tax'           => true,
			'preload_request_interval'     => 2,
			'cache_footprint'              => true,
			'async_cache_cleaning'         => true,
		);

		if ( self::premium_available( $premium_available ) ) {
			$recommended['enable_image_optimization']        = true;
			$recommended['image_optimizer_preferred_format'] = '';
		}

		$recommended = self::apply_recommended_safeguards( $recommended, $context );

		/**
		 * Filter recommended settings before they are applied.
		 *
		 * @hook powered_cache_recommended_settings
		 *
		 * @param {array} $recommended       Recommended settings.
		 * @param {array} $context           Runtime context used by dynamic defaults.
		 * @param {bool}  $premium_available Whether Premium fields can be edited.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$recommended = apply_filters( 'powered_cache_recommended_settings', $recommended, $context, self::premium_available( $premium_available ) );

		if ( ! is_array( $recommended ) ) {
			return array();
		}

		return array_intersect_key( $recommended, self::fields( $context ) );
	}

	/**
	 * Keep one-click setup from enabling optimizations that are likely owned by
	 * another active layer.
	 *
	 * @param array $recommended Recommended settings.
	 * @param array $context     Runtime context.
	 *
	 * @return array
	 */
	private static function apply_recommended_safeguards( array $recommended, array $context ) {
		$active_plugins = isset( $context['active_plugins'] ) && is_array( $context['active_plugins'] ) ? $context['active_plugins'] : array();

		if ( self::has_active_plugin(
			$active_plugins,
			array(
				'autoptimize/autoptimize.php',
				'perfmatters/perfmatters.php',
				'phastpress/phastpress.php',
			)
		) ) {
			$recommended['minify_html']                  = false;
			$recommended['minify_html_dom_optimization'] = false;
			$recommended['combine_google_fonts']         = false;
			$recommended['minify_css']                   = false;
			$recommended['minify_js']                    = false;
			$recommended['disable_wp_embeds']            = false;
			$recommended['disable_emoji_scripts']        = false;
		}

		if ( self::has_active_plugin(
			$active_plugins,
			array(
				'a3-lazy-load/a3-lazy-load.php',
				'bj-lazy-load/bj-lazy-load.php',
				'jetpack-boost/jetpack-boost.php',
				'lazy-load/lazy-load.php',
			)
		) ) {
			$recommended['enable_lazy_load']             = false;
			$recommended['lazy_load_post_content']       = false;
			$recommended['lazy_load_images']             = false;
			$recommended['lazy_load_iframes']            = false;
			$recommended['lazy_load_widgets']            = false;
			$recommended['lazy_load_post_thumbnail']     = false;
			$recommended['lazy_load_avatars']            = false;
			$recommended['lazy_load_youtube']            = false;
			$recommended['lazy_load_skip_first_nth_img'] = 3;
		}

		if ( self::has_active_plugin(
			$active_plugins,
			array(
				'breeze/breeze.php',
				'litespeed-cache/litespeed-cache.php',
				'sg-cachepress/sg-cachepress.php',
				'w3-total-cache/w3-total-cache.php',
				'wp-fastest-cache/wpFastestCache.php',
				'wp-super-cache/wp-cache.php',
			)
		) ) {
			$recommended['enable_page_cache']    = false;
			$recommended['enable_cache_preload'] = false;
			$recommended['preload_homepage']     = false;
			$recommended['preload_public_posts'] = false;
			$recommended['preload_public_tax']   = false;
		}

		return $recommended;
	}

	/**
	 * Determine whether one of the given plugin basenames is active.
	 *
	 * @param array $active_plugins Active plugin basenames.
	 * @param array $candidates     Plugin basenames to match.
	 *
	 * @return bool
	 */
	private static function has_active_plugin( array $active_plugins, array $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $active_plugins, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return a single field definition.
	 *
	 * @param string $key Setting key.
	 * @param array  $context Runtime context used by dynamic defaults.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( $key, array $context = array() ) {
		$fields = self::fields( $context );

		return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
	}

	/**
	 * Return all fields belonging to a section.
	 *
	 * @param string $section Section identifier.
	 * @param array  $context Runtime context used by dynamic defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function section( $section, array $context = array() ) {
		return array_filter(
			self::fields( $context ),
			static function ( $field ) use ( $section ) {
				return $section === $field['section'];
			}
		);
	}

	/**
	 * Determine whether a setting key can be edited.
	 *
	 * Unknown keys stay editable for backward compatibility with extensions.
	 *
	 * @param string    $key Setting key.
	 * @param array     $context Runtime context used by dynamic defaults.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return bool
	 */
	public static function can_edit( $key, array $context = array(), $premium_available = null ) {
		$field = self::get( $key, $context );

		if ( ! $field || empty( $field['premium'] ) ) {
			return true;
		}

		return self::premium_available( $premium_available );
	}

	/**
	 * Return why a setting is locked.
	 *
	 * @param string    $key Setting key.
	 * @param array     $context Runtime context used by dynamic defaults.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return string
	 */
	public static function lock_reason( $key, array $context = array(), $premium_available = null ) {
		if ( self::can_edit( $key, $context, $premium_available ) ) {
			return '';
		}

		$field = self::get( $key, $context );

		return $field && ! empty( $field['premium'] ) ? 'premium' : '';
	}

	/**
	 * Preserve locked settings while applying an incoming settings payload.
	 *
	 * @param array     $settings Incoming settings payload.
	 * @param array     $current Current stored settings.
	 * @param array     $context Runtime context used by dynamic defaults.
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return array
	 */
	public static function enforce_editable( array $settings, array $current = array(), array $context = array(), $premium_available = null ) {
		if ( self::premium_available( $premium_available ) ) {
			return $settings;
		}

		foreach ( self::fields( $context ) as $key => $field ) {
			if ( empty( $field['premium'] ) || ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$settings[ $key ] = array_key_exists( $key, $current ) ? $current[ $key ] : $field['default'];
		}

		return $settings;
	}

	/**
	 * Determine whether the current runtime has Premium available.
	 *
	 * @param bool|null $premium_available Whether Premium fields can be edited.
	 *
	 * @return bool
	 */
	private static function premium_available( $premium_available = null ) {
		if ( null !== $premium_available ) {
			return (bool) $premium_available;
		}

		if ( function_exists( '\PoweredCache\Utils\is_premium' ) ) {
			return (bool) \PoweredCache\Utils\is_premium();
		}

		return false;
	}

	/**
	 * Return object cache backends supported by the current PHP runtime.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 *
	 * @return array<string>
	 */
	private static function object_cache_backends( array $context = array() ) {
		if ( isset( $context['object_cache_backends'] ) && is_array( $context['object_cache_backends'] ) ) {
			$backends = $context['object_cache_backends'];
		} elseif ( function_exists( '\PoweredCache\Utils\get_available_object_caches' ) ) {
			$backends = \PoweredCache\Utils\get_available_object_caches();
		} else {
			$backends = array( 'memcache', 'memcached', 'redis', 'apcu' );
		}

		$backends = array_map( 'strval', $backends );
		$backends = array_filter(
			$backends,
			static function ( $backend ) {
				return '' !== $backend && 'off' !== $backend;
			}
		);

		return array_values( array_unique( $backends ) );
	}

	/**
	 * Return schema metadata for one field.
	 *
	 * @param string $type Setting value type.
	 * @param mixed  $default Default value.
	 * @param string $section UI/product section.
	 * @param string $sanitizer Sanitizer identifier.
	 * @param bool   $premium Whether the field controls a Premium feature.
	 * @param array  $dependencies Field keys this setting depends on.
	 * @param array  $enum_values Valid enum values.
	 * @param bool   $deprecated Whether the field is deprecated.
	 *
	 * @return array<string,mixed>
	 */
	private static function field( $type, $default, $section, $sanitizer, $premium = false, array $dependencies = array(), array $enum_values = array(), $deprecated = false ) {
		return array(
			'type'         => $type,
			'default'      => $default,
			'section'      => $section,
			'sanitizer'    => $sanitizer,
			'premium'      => (bool) $premium,
			'dependencies' => $dependencies,
			'enum'         => $enum_values,
			'deprecated'   => (bool) $deprecated,
		);
	}
}
