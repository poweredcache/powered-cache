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
		$is_apache = isset( $context['is_apache'] ) ? (bool) $context['is_apache'] : false;

		return array(
			'enable_page_cache'                => self::field( self::TYPE_BOOLEAN, true, 'cache', self::SANITIZE_BOOLEAN ),
			'object_cache'                     => self::field( self::TYPE_ENUM, 'off', 'cache', self::SANITIZE_ENUM, false, array(), array( 'off', 'memcache', 'memcached', 'redis', 'apcu' ) ),
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
