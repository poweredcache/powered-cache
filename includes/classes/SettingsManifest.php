<?php
/**
 * Settings schema manifest.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Builds an API/UI friendly settings schema manifest.
 *
 * @since 4.0.0
 */
class SettingsManifest {

	const FORMAT         = 'powered-cache-settings-manifest';
	const FORMAT_VERSION = '1.0';

	/**
	 * Build a settings manifest from the current schema.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 * @param bool  $include_deprecated Whether deprecated fields should be included.
	 *
	 * @return array
	 */
	public static function build( array $context = array(), $include_deprecated = true ) {
		return array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'sections'       => self::sections(),
			'fields'         => self::fields( $context, $include_deprecated ),
		);
	}

	/**
	 * Return ordered settings sections.
	 *
	 * @return array
	 */
	public static function sections() {
		$sections = array(
			'cache'             => array(
				'label'       => 'Cache',
				'description' => 'Control full-page cache, object cache, and browser-facing cache behavior.',
				'order'       => 10,
			),
			'advanced'          => array(
				'label'       => 'Advanced',
				'description' => 'Fine tune exclusions, query strings, cookies, and server configuration.',
				'order'       => 20,
			),
			'file_optimization' => array(
				'label'       => 'File Optimization',
				'description' => 'Reduce render-blocking HTML, CSS, JavaScript, and font overhead.',
				'order'       => 30,
			),
			'media'             => array(
				'label'       => 'Media',
				'description' => 'Optimize images, embeds, and lazy loading behavior.',
				'order'       => 40,
			),
			'cdn'               => array(
				'label'       => 'CDN',
				'description' => 'Route static assets through your CDN hostnames.',
				'order'       => 50,
			),
			'preload'           => array(
				'label'       => 'Preload',
				'description' => 'Warm important URLs and prepare critical resources before visitors arrive.',
				'order'       => 60,
			),
			'database'          => array(
				'label'       => 'Database',
				'description' => 'Clean up database overhead and schedule recurring maintenance.',
				'order'       => 70,
			),
			'integrations'      => array(
				'label'       => 'Integrations',
				'description' => 'Connect external services and WordPress runtime integrations.',
				'order'       => 80,
			),
			'misc'              => array(
				'label'       => 'Tools',
				'description' => 'Review the Optimization Advisor, cache footprint, async cleanup, tracking, and developer mode.',
				'order'       => 90,
			),
		);

		/**
		 * Filter settings sections shown in the admin app and submenu deep links.
		 *
		 * @hook powered_cache_settings_sections
		 *
		 * @param {array} $sections Settings section metadata.
		 *
		 * @return {array} New value.
		 *
		 * @since 4.0.0
		 */
		return apply_filters( 'powered_cache_settings_sections', $sections );
	}

	/**
	 * Return manifest fields keyed by setting name.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 * @param bool  $include_deprecated Whether deprecated fields should be included.
	 *
	 * @return array
	 */
	public static function fields( array $context = array(), $include_deprecated = true ) {
		$fields = array();
		$order  = 10;

		foreach ( SettingsSchema::fields( $context ) as $key => $field ) {
			if ( ! $include_deprecated && ! empty( $field['deprecated'] ) ) {
				continue;
			}

			$fields[ $key ] = self::field( $key, $field, $order, $context );
			$order         += 10;
		}

		return $fields;
	}

	/**
	 * Return one manifest field.
	 *
	 * @param string $key Setting key.
	 * @param array  $field Schema field.
	 * @param int    $order Field display order.
	 * @param array  $context Runtime context used by dynamic defaults.
	 *
	 * @return array
	 */
	private static function field( $key, array $field, $order, array $context ) {
		$metadata = self::field_metadata( $key, $field );
		$editable = SettingsSchema::can_edit( $key, $context );
		$manifest = array(
			'key'           => $key,
			'label'         => $metadata['label'],
			'description'   => $metadata['description'],
			'type'          => $field['type'],
			'control'       => $metadata['control'] ? $metadata['control'] : self::control( $field ),
			'control_label' => $metadata['control_label'],
			'default'       => $field['default'],
			'section'       => $field['section'],
			'group'         => $metadata['group'],
			'order'         => $order,
			'sanitizer'     => $field['sanitizer'],
			'premium'       => (bool) $field['premium'],
			'editable'      => $editable,
			'locked'        => ! $editable,
			'lock_reason'   => SettingsSchema::lock_reason( $key, $context ),
			'dependencies'  => array_values( $field['dependencies'] ),
			'enum'          => array_values( $field['enum'] ),
			'enum_labels'   => self::enum_labels( $field['enum'] ),
			'deprecated'    => (bool) $field['deprecated'],
		);

		foreach ( array( 'docs_fragment', 'docs_path', 'docs_url', 'max', 'min', 'options', 'placeholder', 'visible_when', 'zone_key', 'zone_options' ) as $metadata_key ) {
			if ( isset( $metadata[ $metadata_key ] ) ) {
				$manifest[ $metadata_key ] = $metadata[ $metadata_key ];
			}
		}

		if ( $manifest['premium'] ) {
			$manifest['upgrade'] = array(
				'label'       => 'Upgrade to Premium',
				'description' => $metadata['upgrade_description'],
			);
		}

		return $manifest;
	}

	/**
	 * Return UI control type for a schema field.
	 *
	 * @param array $field Schema field.
	 *
	 * @return string
	 */
	private static function control( array $field ) {
		if ( SettingsSchema::TYPE_BOOLEAN === $field['type'] ) {
			return 'toggle';
		}

		if ( SettingsSchema::TYPE_ENUM === $field['type'] ) {
			return 'select';
		}

		if ( SettingsSchema::TYPE_INTEGER === $field['type'] ) {
			return 'number';
		}

		if ( SettingsSchema::TYPE_ARRAY === $field['type'] ) {
			return 'list';
		}

		if ( SettingsSchema::SANITIZE_TEXTAREA === $field['sanitizer'] ) {
			return 'textarea';
		}

		return 'text';
	}

	/**
	 * Return field labels and descriptions for UI consumers.
	 *
	 * @param string $key Setting key.
	 * @param array  $field Schema field.
	 *
	 * @return array
	 */
	private static function field_metadata( $key, array $field ) {
		$metadata = array(
			'label'               => self::label_from_key( $key ),
			'control'             => '',
			'control_label'       => '',
			'description'         => '',
			'group'               => $field['section'],
			'upgrade_description' => 'Unlock this optimization in Powered Cache Premium.',
		);

		$overrides = self::metadata_overrides();

		if ( isset( $overrides[ $key ] ) ) {
			$metadata = array_merge( $metadata, $overrides[ $key ] );
		}

		if ( 'object_cache' === $key && array( 'off' ) === array_values( $field['enum'] ) ) {
			$metadata['description'] = 'No supported persistent object cache PHP extension was detected on this server.';
		}

		return $metadata;
	}

	/**
	 * Return field metadata overrides keyed by setting key.
	 *
	 * @return array
	 */
	private static function metadata_overrides() {
		return array(
			'enable_page_cache'              => array(
				'label'         => 'Page Cache',
				'control_label' => 'Serve cached pages',
				'description'   => 'Serve cached HTML for faster repeat and anonymous visits.',
				'group'         => 'Core cache',
				'docs_path'     => 'page-caching',
			),
			'object_cache'                   => array(
				'label'       => 'Object Cache',
				'description' => 'Use a persistent object cache backend for dynamic WordPress data.',
				'group'       => 'Core cache',
				'docs_path'   => 'object-caching',
			),
			'cache_mobile'                   => array(
				'label'         => 'Mobile Cache',
				'control_label' => 'Cache mobile visits',
				'description'   => 'Cache visits from mobile devices.',
				'group'         => 'Core cache',
			),
			'cache_mobile_separate_file'     => array(
				'label'         => 'Separate Mobile Cache',
				'control_label' => 'Use a separate mobile cache',
				'description'   => 'Create mobile-specific cache files when the site serves different mobile markup.',
				'group'         => 'Core cache',
			),
			'loggedin_user_cache'            => array(
				'label'         => 'Logged-in User Cache',
				'control_label' => 'Cache logged-in visits',
				'description'   => 'Serve cached pages to logged-in users only when the site output is safe to share.',
				'group'         => 'Core cache',
			),
			'gzip_compression'               => array(
				'label'         => 'Gzip Compression',
				'control_label' => 'Serve compressed files',
				'description'   => 'Serve compressed cache files when supported by the server.',
				'group'         => 'Delivery',
			),
			'cache_timeout'                  => array(
				'control'     => 'duration',
				'label'       => 'Cache Lifespan',
				'description' => 'Set how long cached pages stay fresh.',
				'group'       => 'Delivery',
			),
			'auto_configure_htaccess'        => array(
				'label'         => '.htaccess Configuration',
				'control_label' => 'Automatically configure .htaccess',
				'description'   => 'Let Powered Cache write recommended Apache rewrite rules when settings change.',
				'group'         => 'Server configuration',
				'docs_path'     => 'rewrite-file-optimizer',
			),
			'rejected_user_agents'           => array(
				'label'         => 'Rejected User Agents',
				'description'   => 'Never serve cached pages to matching browsers, bots, or crawlers.',
				'group'         => 'Cache exclusions',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'rejected-user-agents',
			),
			'rejected_cookies'               => array(
				'label'         => 'Rejected Cookies',
				'description'   => 'Bypass page cache when a visitor has one of these cookies.',
				'group'         => 'Cache exclusions',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'rejected-cookies',
			),
			'rejected_referrers'             => array(
				'label'         => 'Rejected Referrers',
				'description'   => 'Bypass page cache when traffic comes from matching referrer URLs.',
				'group'         => 'Cache exclusions',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'rejected-referrers',
			),
			'vary_cookies'                   => array(
				'label'         => 'Vary Cookies',
				'description'   => 'Create separate cache variants when these cookies are present.',
				'group'         => 'Cache variants',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'vary-cookies',
			),
			'rejected_uri'                   => array(
				'label'         => 'Never Cache URLs',
				'description'   => 'Exclude matching URLs, paths, or regex patterns from page cache.',
				'group'         => 'Cache exclusions',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'ignored-pages',
			),
			'ignored_query_strings'          => array(
				'label'         => 'Ignored Query Strings',
				'description'   => 'Ignore matching query parameters and serve the standard cached page.',
				'group'         => 'Query strings',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'ignored-query-strings',
			),
			'cache_query_strings'            => array(
				'label'         => 'Cache Query Strings',
				'description'   => 'Create separate cache files for the listed query parameters.',
				'group'         => 'Query strings',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'cache-query-strings',
			),
			'purge_additional_pages'         => array(
				'label'         => 'Purge Additional Pages',
				'description'   => 'Clear extra related URLs when content changes.',
				'group'         => 'Purge behavior',
				'docs_path'     => '/advanced-options/',
				'docs_fragment' => 'purge-additional-pages',
			),
			'minify_html'                    => array(
				'label'       => 'Minify HTML',
				'description' => 'Remove unnecessary whitespace from generated HTML.',
				'group'       => 'HTML',
			),
			'minify_html_dom_optimization'   => array(
				'label'         => 'HTML DOM Optimization',
				'control_label' => 'Optimize HTML output',
				'description'   => 'Apply safer HTML output cleanup after HTML minification is enabled.',
				'group'         => 'HTML',
			),
			'combine_google_fonts'           => array(
				'label'         => 'Combine Google Fonts',
				'control_label' => 'Combine Google Fonts requests',
				'description'   => 'Reduce multiple Google Fonts stylesheet requests into a single request when possible.',
				'group'         => 'Fonts',
			),
			'swap_google_fonts_display'      => array(
				'label'         => 'Font Display Swap',
				'control_label' => 'Use display swap for Google Fonts',
				'description'   => 'Add display swap behavior so text can render while web fonts load.',
				'group'         => 'Fonts',
			),
			'use_bunny_fonts'                => array(
				'label'         => 'Bunny Fonts',
				'control_label' => 'Serve Google Fonts through Bunny Fonts',
				'description'   => 'Use Bunny Fonts as a privacy-friendly drop-in source for Google Fonts.',
				'group'         => 'Fonts',
			),
			'minify_css'                     => array(
				'label'       => 'Minify CSS',
				'description' => 'Reduce CSS file size before delivery.',
				'group'       => 'CSS',
			),
			'combine_css'                    => array(
				'label'       => 'Combine CSS',
				'description' => 'Combine CSS files when it improves delivery on the site.',
				'group'       => 'CSS',
			),
			'critical_css'                   => array(
				'label'               => 'Critical CSS',
				'description'         => 'Generate and inline above-the-fold CSS for important templates.',
				'group'               => 'CSS',
				'docs_path'           => 'critical-css',
				'upgrade_description' => 'Premium can generate Critical CSS automatically for key templates and posts.',
			),
			'critical_css_additional_files'  => array(
				'label'       => 'Critical CSS Additional Files',
				'description' => 'Include extra stylesheet URLs when Critical CSS is generated.',
				'group'       => 'CSS',
				'docs_path'   => 'critical-css',
			),
			'critical_css_excluded_files'    => array(
				'label'       => 'Critical CSS Excluded Files',
				'description' => 'Skip matching stylesheet URLs during Critical CSS generation.',
				'group'       => 'CSS',
				'docs_path'   => 'critical-css',
			),
			'critical_css_appended_content'  => array(
				'label'       => 'Append to Critical CSS',
				'description' => 'Append CSS that must always be included with generated Critical CSS.',
				'group'       => 'CSS',
				'docs_path'   => 'critical-css',
			),
			'critical_css_fallback'          => array(
				'label'       => 'Fallback Critical CSS',
				'description' => 'Use this CSS when generated Critical CSS is unavailable for a page.',
				'group'       => 'CSS',
				'docs_path'   => 'critical-css',
			),
			'excluded_css_files'             => array(
				'label'       => 'Excluded CSS Files',
				'description' => 'Keep matching CSS files out of CSS minify or combine processing.',
				'group'       => 'CSS',
			),
			'remove_unused_css'              => array(
				'label'               => 'Remove Unused CSS',
				'description'         => 'Generate lean CSS payloads by removing rules unused on the page.',
				'group'               => 'CSS',
				'docs_path'           => 'remove-unused-css',
				'upgrade_description' => 'Premium can generate used CSS and reduce page weight without manual cleanup.',
			),
			'ucss_safelist'                  => array(
				'label'       => 'Unused CSS Safelist',
				'description' => 'Always keep matching selectors when unused CSS is generated.',
				'group'       => 'CSS',
				'docs_path'   => 'remove-unused-css',
			),
			'ucss_excluded_files'            => array(
				'label'       => 'Unused CSS Excluded Files',
				'description' => 'Skip matching stylesheets during unused CSS generation.',
				'group'       => 'CSS',
				'docs_path'   => 'remove-unused-css',
			),
			'css_optimization_disabled_sources' => array(
				'control' => 'hidden',
				'label'   => 'Paused CSS Compatibility Sources',
				'group'   => 'CSS',
			),
			'minify_js'                      => array(
				'label'       => 'Minify JavaScript',
				'description' => 'Reduce JavaScript file size before delivery.',
				'group'       => 'JavaScript',
			),
			'combine_js'                     => array(
				'label'       => 'Combine JavaScript',
				'description' => 'Combine JavaScript files when it improves delivery on the site.',
				'group'       => 'JavaScript',
			),
			'excluded_js_files'              => array(
				'label'       => 'Excluded JavaScript Files',
				'description' => 'Keep matching scripts out of JavaScript minify or combine processing.',
				'group'       => 'JavaScript',
			),
			'js_defer'                       => array(
				'label'       => 'Defer JavaScript',
				'description' => 'Load JavaScript without blocking initial page rendering.',
				'group'       => 'JavaScript',
			),
			'js_defer_exclusions'            => array(
				'label'       => 'Defer JavaScript Exclusions',
				'description' => 'Keep matching scripts in their original loading order.',
				'group'       => 'JavaScript',
			),
			'js_delay'                       => array(
				'label'       => 'Delay JavaScript',
				'description' => 'Delay selected scripts until user interaction or timeout.',
				'group'       => 'JavaScript',
			),
			'js_delay_exclusions'            => array(
				'label'       => 'Delay JavaScript Exclusions',
				'description' => 'Allow matching scripts to run without waiting for interaction.',
				'group'       => 'JavaScript',
			),
			'js_delay_timeout'               => array(
				'label'       => 'Delay Timeout',
				'description' => 'Set the fallback delay before delayed scripts are allowed to run.',
				'group'       => 'JavaScript',
				'min'         => 0,
			),
			'rewrite_file_optimizer'         => array(
				'label'         => 'File Optimizer Rewrite Rules',
				'control_label' => 'Write optimizer rewrite rules',
				'description'   => 'Let Powered Cache write Apache rewrite rules for optimized CSS and JavaScript files.',
				'group'         => 'Server configuration',
				'docs_path'     => 'rewrite-file-optimizer',
			),
			'enable_image_optimization'      => array(
				'label'               => 'Image Optimization',
				'description'         => 'Optimize images on demand through the Powered Cache image delivery service.',
				'group'               => 'Images',
				'docs_path'           => 'image-optimization',
				'upgrade_description' => 'Premium adds on-the-fly WebP/AVIF image optimization backed by fast CDN delivery.',
			),
			'image_optimizer_preferred_format' => array(
				'label'               => 'Image Format Preference',
				'control'             => 'select',
				'description'         => 'Choose whether optimized images should prefer the automatic AVIF-first flow or WebP.',
				'group'               => 'Images',
				'options'             => array(
					''     => 'Automatic (AVIF when supported)',
					'webp' => 'Prefer WebP',
				),
				'upgrade_description' => 'Premium can serve optimized images in modern formats through the delivery network.',
			),
			'add_missing_image_dimensions'   => array(
				'label'               => 'Automatic Image Dimensions',
				'description'         => 'Add missing width and height attributes to improve layout stability.',
				'group'               => 'Images',
				'docs_path'           => 'image-dimensions',
				'upgrade_description' => 'Premium can add missing image dimensions automatically to improve CLS.',
			),
			'enable_lazy_load'               => array(
				'label'         => 'Lazy Load',
				'control_label' => 'Delay offscreen media',
				'description'   => 'Delay images and embeds until they are close to the viewport.',
				'group'         => 'Lazy loading',
				'docs_path'     => 'enable-lazy-load',
			),
			'lazy_load_post_content'         => array(
				'label'         => 'Post Content',
				'control_label' => 'Lazy load post content media',
				'description'   => 'Apply lazy loading to images and embeds inside post content.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_images'               => array(
				'label'         => 'Images',
				'control_label' => 'Lazy load images',
				'description'   => 'Delay eligible image loading until images approach the viewport.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_iframes'              => array(
				'label'         => 'Iframes',
				'control_label' => 'Lazy load iframes',
				'description'   => 'Delay eligible iframe loading until embeds approach the viewport.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_widgets'              => array(
				'label'         => 'Widgets',
				'control_label' => 'Lazy load widget media',
				'description'   => 'Apply lazy loading to eligible images and embeds inside widgets.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_post_thumbnail'       => array(
				'label'         => 'Post Thumbnails',
				'control_label' => 'Lazy load featured images',
				'description'   => 'Delay eligible featured images when they are not needed immediately.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_avatars'              => array(
				'label'         => 'Avatars',
				'control_label' => 'Lazy load avatars',
				'description'   => 'Delay comment and profile avatar images until they are near the viewport.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_youtube'              => array(
				'label'         => 'YouTube Embeds',
				'control_label' => 'Replace YouTube embeds with thumbnails',
				'description'   => 'Replace YouTube iframes with lightweight thumbnails until visitors interact.',
				'group'         => 'Lazy loading',
			),
			'lazy_load_skip_first_nth_img'   => array(
				'label'       => 'Skip First Images',
				'description' => 'Avoid lazy loading the first images so above-the-fold media can remain fast.',
				'group'       => 'Lazy loading',
				'min'         => 0,
				'max'         => 30,
			),
			'lazy_load_exclusions'           => array(
				'label'       => 'Lazy Load Exclusions',
				'description' => 'Exclude matching images, iframes, classes, filenames, or domains from lazy loading.',
				'group'       => 'Lazy loading',
				'docs_path'   => 'enable-lazy-load',
			),
			'disable_wp_lazy_load'           => array(
				'label'         => 'WordPress Native Lazy Load',
				'control_label' => 'Disable WordPress native lazy load',
				'description'   => 'Turn off WordPress native lazy loading when Powered Cache should manage it instead.',
				'group'         => 'WordPress media',
				'docs_path'     => 'disable-wordpress-lazy-load',
			),
			'disable_wp_embeds'              => array(
				'label'         => 'WordPress Embeds',
				'control_label' => 'Disable WordPress embeds',
				'description'   => 'Prevent WordPress URLs from turning into heavier embed iframes.',
				'group'         => 'WordPress media',
				'docs_path'     => 'disable-wordpress-embeds',
			),
			'disable_emoji_scripts'          => array(
				'label'         => 'Emoji Scripts',
				'control_label' => 'Remove emoji scripts',
				'description'   => 'Remove WordPress emoji detection scripts without removing emoji characters.',
				'group'         => 'WordPress media',
				'docs_path'     => 'remove-emoji-scripts',
			),
			'enable_cdn'                     => array(
				'label'         => 'CDN Delivery',
				'control_label' => 'Rewrite asset URLs',
				'description'   => 'Rewrite static asset URLs to configured CDN hostnames.',
				'group'         => 'CDN',
				'docs_path'     => 'cdn-integration',
			),
			'cdn_hostname'                   => array(
				'label'        => 'CDN Hostnames',
				'control'      => 'cdn_zones',
				'description'  => 'Map each CDN hostname to all assets or to a specific file type.',
				'group'        => 'CDN',
				'docs_path'    => 'cdn-integration',
				'zone_key'     => 'cdn_zone',
				'zone_options' => self::cdn_zone_options(),
			),
			'cdn_zone'                       => array(
				'label'       => 'CDN Zone',
				'control'     => 'hidden',
				'description' => 'CDN zone mappings are managed with hostnames.',
				'group'       => 'CDN',
			),
			'cdn_rejected_files'             => array(
				'label'       => 'CDN Excluded Files',
				'description' => 'Keep matching full URLs or absolute paths on the origin instead of the CDN.',
				'group'       => 'CDN',
				'docs_path'   => 'cdn-integration',
			),
			'enable_cache_preload'           => array(
				'label'         => 'Cache Preload',
				'control_label' => 'Warm cache automatically',
				'description'   => 'Warm selected URLs before visitors request them.',
				'group'         => 'Preload',
				'docs_path'     => 'cache-preload',
			),
			'preload_homepage'               => array(
				'label'         => 'Preload Homepage',
				'control_label' => 'Include the homepage',
				'description'   => 'Warm the homepage when cache preloading runs.',
				'group'         => 'Preload',
			),
			'preload_public_posts'           => array(
				'label'         => 'Preload Public Posts',
				'control_label' => 'Include public posts',
				'description'   => 'Warm public post, page, and custom post type URLs.',
				'group'         => 'Preload',
			),
			'preload_public_tax'             => array(
				'label'         => 'Preload Public Taxonomies',
				'control_label' => 'Include public archives',
				'description'   => 'Warm public category, tag, and taxonomy archive URLs.',
				'group'         => 'Preload',
			),
			'enable_sitemap_preload'         => array(
				'label'               => 'Sitemap Preload',
				'control_label'       => 'Read URLs from sitemaps',
				'description'         => 'Use sitemap URLs as an additional preload source.',
				'group'               => 'Preload',
				'docs_path'           => 'sitemap-preload',
				'upgrade_description' => 'Premium can discover and warm URLs from XML sitemaps automatically.',
			),
			'preload_request_interval'       => array(
				'label'       => 'Preload Request Interval',
				'description' => 'Set the delay between preload requests to reduce server pressure.',
				'group'       => 'Preload',
				'min'         => 1,
			),
			'preload_sitemap'                => array(
				'label'       => 'Sitemap URLs',
				'description' => 'Add XML sitemap URLs that should feed the preload queue.',
				'group'       => 'Preload',
				'docs_path'   => 'sitemap-preload',
			),
			'prefetch_dns'                   => array(
				'label'       => 'DNS Prefetch',
				'description' => 'Resolve external hostnames earlier so later resource requests can start faster.',
				'group'       => 'Resource hints',
				'docs_path'   => 'prefetch-dns',
			),
			'preconnect_resource'            => array(
				'label'       => 'Preconnect',
				'description' => 'Open early connections for the most important third-party origins.',
				'group'       => 'Resource hints',
				'docs_path'   => 'preconnect-resources',
			),
			'preload_fonts'                  => array(
				'label'       => 'Preload Fonts',
				'description' => 'Load critical font files earlier to reduce late text rendering shifts.',
				'group'       => 'Resource hints',
				'docs_path'   => 'preload-fonts',
				'placeholder' => "https://example.com/wp-content/themes/theme/assets/fonts/inter.woff2\n/wp-content/uploads/fonts/brand.woff",
			),
			'enable_lcp_optimization'        => array(
				'label'               => 'LCP Optimization',
				'description'         => 'Detect and prioritize the likely Largest Contentful Paint resource.',
				'group'               => 'Critical resources',
				'docs_path'           => 'lcp-optimization',
				'upgrade_description' => 'Premium can prioritize critical LCP images and resources automatically.',
			),
			'prefetch_links'                 => array(
				'label'               => 'Link Prefetching',
				'control_label'       => 'Prefetch in-viewport links',
				'description'         => 'Speed up likely next page views during browser idle time.',
				'group'               => 'Critical resources',
				'upgrade_description' => 'Premium can prefetch likely next pages so repeat navigation feels instant.',
			),
			'enable_scheduled_db_cleanup'    => array(
				'label'               => 'Scheduled Cleanups',
				'control_label'       => 'Clean database on schedule',
				'description'         => 'Run selected database cleanup tasks on a schedule.',
				'group'               => 'Scheduling',
				'upgrade_description' => 'Premium can automatically clean selected database overhead on a recurring schedule.',
			),
			'scheduled_db_cleanup_frequency' => array(
				'label'               => 'Cleanup Frequency',
				'description'         => 'Choose how often scheduled database cleanups should run.',
				'group'               => 'Scheduling',
				'upgrade_description' => 'Premium can run automatic database cleanups hourly, daily, weekly, or monthly.',
			),
			'db_cleanup_post_revisions'      => array(
				'label'         => 'Post Revisions',
				'control_label' => 'Clean post revisions',
				'description'   => 'Remove saved post revisions during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_auto_drafts'         => array(
				'label'         => 'Auto Drafts',
				'control_label' => 'Clean auto drafts',
				'description'   => 'Remove automatically created draft posts during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_trashed_posts'       => array(
				'label'         => 'Trashed Posts',
				'control_label' => 'Clean trashed posts',
				'description'   => 'Remove trashed posts and pages during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_spam_comments'       => array(
				'label'         => 'Spam Comments',
				'control_label' => 'Clean spam comments',
				'description'   => 'Remove spam comments during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_trashed_comments'    => array(
				'label'         => 'Trashed Comments',
				'control_label' => 'Clean trashed comments',
				'description'   => 'Remove trashed comments during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_expired_transients'  => array(
				'label'         => 'Expired Transients',
				'control_label' => 'Clean expired transients',
				'description'   => 'Remove expired transient data during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_all_transients'      => array(
				'label'         => 'All Transients',
				'control_label' => 'Clean all transients',
				'description'   => 'Remove all transient data during database cleanup.',
				'group'         => 'Cleanup tasks',
			),
			'db_cleanup_optimize_tables'     => array(
				'label'         => 'Optimize Tables',
				'control_label' => 'Optimize database tables',
				'description'   => 'Run table optimization for supported database tables.',
				'group'         => 'Cleanup tasks',
			),
			'enable_cloudflare'              => array(
				'label'         => 'Cloudflare',
				'control_label' => 'Purge Cloudflare cache',
				'description'   => 'Purge Cloudflare when Powered Cache clears site cache.',
				'group'         => 'CDN and proxy',
				'docs_path'     => 'cloudflare',
			),
			'cloudflare_api_token'           => array(
				'label'       => 'Cloudflare API Token',
				'description' => 'Use a scoped API token for Cloudflare cache purge requests.',
				'group'       => 'CDN and proxy',
				'docs_url'    => 'https://dash.cloudflare.com/profile/api-tokens',
			),
			'cloudflare_email'               => array(
				'label'       => 'Cloudflare Email',
				'description' => 'Legacy Cloudflare account email used with a global API key.',
				'group'       => 'CDN and proxy',
			),
			'cloudflare_api_key'             => array(
				'label'       => 'Cloudflare API Key',
				'description' => 'Legacy Cloudflare global API key. Prefer an API token when possible.',
				'group'       => 'CDN and proxy',
			),
			'cloudflare_zone'                => array(
				'label'       => 'Cloudflare Zone ID',
				'description' => 'The Cloudflare zone that should be purged when cache is cleared.',
				'group'       => 'CDN and proxy',
			),
			'enable_heartbeat'               => array(
				'label'         => 'Heartbeat Control',
				'control_label' => 'Manage Heartbeat activity',
				'description'   => 'Adjust WordPress Heartbeat behavior in admin, editor, and frontend contexts.',
				'group'         => 'WordPress runtime',
			),
			'heartbeat_dashboard_status'     => array(
				'label'       => 'Dashboard Heartbeat',
				'description' => 'Choose how Heartbeat should behave on WordPress dashboard screens.',
				'group'       => 'Heartbeat',
			),
			'heartbeat_dashboard_interval'   => array_merge(
				self::heartbeat_interval_metadata( 'heartbeat_dashboard_status' ),
				array(
					'label'       => 'Dashboard Interval',
					'description' => 'Set the dashboard Heartbeat interval in seconds.',
				)
			),
			'heartbeat_editor_status'        => array(
				'label'       => 'Editor Heartbeat',
				'description' => 'Choose how Heartbeat should behave in the post editor.',
				'group'       => 'Heartbeat',
			),
			'heartbeat_editor_interval'      => array_merge(
				self::heartbeat_interval_metadata( 'heartbeat_editor_status' ),
				array(
					'label'       => 'Editor Interval',
					'description' => 'Set the editor Heartbeat interval in seconds.',
				)
			),
			'heartbeat_frontend_status'      => array(
				'label'       => 'Frontend Heartbeat',
				'description' => 'Choose how Heartbeat should behave for frontend visits.',
				'group'       => 'Heartbeat',
			),
			'heartbeat_frontend_interval'    => array_merge(
				self::heartbeat_interval_metadata( 'heartbeat_frontend_status' ),
				array(
					'label'       => 'Frontend Interval',
					'description' => 'Set the frontend Heartbeat interval in seconds.',
				)
			),
			'enable_varnish'                 => array(
				'label'               => 'Varnish',
				'control_label'       => 'Purge Varnish cache',
				'description'         => 'Purge Varnish when cache is cleared.',
				'group'               => 'Reverse proxy',
				'upgrade_description' => 'Premium can purge external Varnish caches whenever Powered Cache clears page cache.',
			),
			'varnish_ip'                     => array(
				'label'       => 'Varnish IP Addresses',
				'description' => 'Add Varnish server IP addresses that should receive purge requests.',
				'group'       => 'Reverse proxy',
			),
			'cache_footprint'                => array(
				'label'         => 'Cache Footprint',
				'control_label' => 'Track cache footprint',
				'description'   => 'Track cache size so the dashboard can report cache footprint accurately.',
				'group'         => 'Tools',
			),
			'async_cache_cleaning'           => array(
				'label'         => 'Async Cache Cleaning',
				'control_label' => 'Clean cache asynchronously',
				'description'   => 'Process cache cleanup in the background when supported by the site.',
				'group'         => 'Tools',
			),
			'dev_mode'                       => array(
				'label'         => 'Development Mode',
				'control_label' => 'Bypass cache temporarily',
				'description'   => 'Temporarily bypass cache behavior while working on the site.',
				'group'         => 'Developer tools',
			),
			'enable_google_tracking'         => array(
				'label'               => 'Google Tracking Optimization',
				'control_label'       => 'Optimize Google tracking scripts',
				'description'         => 'Apply Premium delivery optimizations for supported Google tracking scripts.',
				'group'               => 'Tracking',
				'upgrade_description' => 'Premium can optimize supported Google tracking scripts for better frontend performance.',
			),
			'enable_fb_tracking'             => array(
				'label'               => 'Meta Pixel Optimization',
				'control_label'       => 'Optimize Meta Pixel scripts',
				'description'         => 'Apply Premium delivery optimizations for supported Meta Pixel scripts.',
				'group'               => 'Tracking',
				'upgrade_description' => 'Premium can optimize supported Meta Pixel scripts for better frontend performance.',
			),
		);
	}

	/**
	 * Return shared Heartbeat interval field metadata.
	 *
	 * @param string $status_key Status setting key.
	 *
	 * @return array
	 */
	private static function heartbeat_interval_metadata( $status_key ) {
		return array(
			'group'        => 'Heartbeat',
			'min'          => 15,
			'max'          => 120,
			'visible_when' => array(
				array(
					'key'   => $status_key,
					'value' => 'modify',
				),
			),
		);
	}

	/**
	 * Return CDN zone options for the settings app.
	 *
	 * @return array
	 */
	private static function cdn_zone_options() {
		if ( function_exists( '\PoweredCache\Utils\cdn_zones' ) ) {
			return \PoweredCache\Utils\cdn_zones();
		}

		return array(
			'all'   => 'All files',
			'image' => 'Images',
			'js'    => 'JavaScript',
			'css'   => 'CSS',
		);
	}

	/**
	 * Build enum labels keyed by enum value.
	 *
	 * @param array $values Enum values.
	 *
	 * @return array
	 */
	private static function enum_labels( array $values ) {
		$labels = array();

		foreach ( $values as $value ) {
			$labels[ $value ] = self::label_from_key( $value );
		}

		return $labels;
	}

	/**
	 * Convert a schema key to a readable label.
	 *
	 * @param string $key Schema key.
	 *
	 * @return string
	 */
	private static function label_from_key( $key ) {
		$key = str_replace( array( '_', '-' ), ' ', (string) $key );

		return ucwords( $key );
	}
}
