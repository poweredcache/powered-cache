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
				'description' => 'Manage cache footprint, async cleanup, tracking, and developer mode.',
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
		$policy = SettingsCapabilityPolicy::factory( null, $context );

		foreach ( SettingsSchema::fields( $context ) as $key => $field ) {
			if ( ! $include_deprecated && ! empty( $field['deprecated'] ) ) {
				continue;
			}

			$fields[ $key ] = self::field( $key, $field, $order, $policy );
			$order         += 10;
		}

		return $fields;
	}

	/**
	 * Return one manifest field.
	 *
	 * @param string                   $key Setting key.
	 * @param array                    $field Schema field.
	 * @param int                      $order Field display order.
	 * @param SettingsCapabilityPolicy $policy Settings capability policy.
	 *
	 * @return array
	 */
	private static function field( $key, array $field, $order, SettingsCapabilityPolicy $policy ) {
		$metadata = self::field_metadata( $key, $field );
		$editable = $policy->can_edit( $key );
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
			'lock_reason'   => $policy->lock_reason( $key ),
			'dependencies'  => array_values( $field['dependencies'] ),
			'enum'          => array_values( $field['enum'] ),
			'enum_labels'   => self::enum_labels( $field['enum'] ),
			'deprecated'    => (bool) $field['deprecated'],
		);

		foreach ( array( 'max', 'min', 'options', 'visible_when', 'zone_key', 'zone_options' ) as $metadata_key ) {
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

		$overrides = array(
			'enable_page_cache'              => array(
				'label'         => 'Page Cache',
				'control_label' => 'Serve cached pages',
				'description'   => 'Serve cached HTML for faster repeat and anonymous visits.',
				'group'         => 'Core cache',
			),
			'object_cache'                   => array(
				'label'       => 'Object Cache',
				'description' => 'Use a persistent object cache backend for dynamic WordPress data.',
				'group'       => 'Core cache',
			),
			'cache_mobile'                   => array(
				'label'         => 'Mobile Cache',
				'control_label' => 'Cache mobile visits',
				'description'   => 'Cache visits from mobile devices.',
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
			'minify_html'                    => array(
				'label'       => 'Minify HTML',
				'description' => 'Remove unnecessary whitespace from generated HTML.',
				'group'       => 'HTML',
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
				'upgrade_description' => 'Premium can generate Critical CSS automatically for key templates and posts.',
			),
			'remove_unused_css'              => array(
				'label'               => 'Remove Unused CSS',
				'description'         => 'Generate lean CSS payloads by removing rules unused on the page.',
				'group'               => 'CSS',
				'upgrade_description' => 'Premium can generate used CSS and reduce page weight without manual cleanup.',
			),
			'minify_js'                      => array(
				'label'       => 'Minify JavaScript',
				'description' => 'Reduce JavaScript file size before delivery.',
				'group'       => 'JavaScript',
			),
			'js_defer'                       => array(
				'label'       => 'Defer JavaScript',
				'description' => 'Load JavaScript without blocking initial page rendering.',
				'group'       => 'JavaScript',
			),
			'js_delay'                       => array(
				'label'       => 'Delay JavaScript',
				'description' => 'Delay selected scripts until user interaction or timeout.',
				'group'       => 'JavaScript',
			),
			'enable_image_optimization'      => array(
				'label'               => 'Image Optimization',
				'description'         => 'Optimize images on demand through the Powered Cache image delivery service.',
				'group'               => 'Images',
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
				'upgrade_description' => 'Premium can add missing image dimensions automatically to improve CLS.',
			),
			'enable_lazy_load'               => array(
				'label'         => 'Lazy Load',
				'control_label' => 'Delay offscreen media',
				'description'   => 'Delay images and embeds until they are close to the viewport.',
				'group'         => 'Lazy loading',
			),
			'enable_cdn'                     => array(
				'label'         => 'CDN Delivery',
				'control_label' => 'Rewrite asset URLs',
				'description'   => 'Rewrite static asset URLs to configured CDN hostnames.',
				'group'         => 'CDN',
			),
			'cdn_hostname'                   => array(
				'label'        => 'CDN Hostnames',
				'control'      => 'cdn_zones',
				'description'  => 'Map each CDN hostname to all assets or to a specific file type.',
				'group'        => 'CDN',
				'zone_key'     => 'cdn_zone',
				'zone_options' => self::cdn_zone_options(),
			),
			'cdn_zone'                       => array(
				'label'       => 'CDN Zone',
				'control'     => 'hidden',
				'description' => 'CDN zone mappings are managed with hostnames.',
				'group'       => 'CDN',
			),
			'enable_cache_preload'           => array(
				'label'         => 'Cache Preload',
				'control_label' => 'Warm cache automatically',
				'description'   => 'Warm selected URLs before visitors request them.',
				'group'         => 'Preload',
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
				'upgrade_description' => 'Premium can discover and warm URLs from XML sitemaps automatically.',
			),
			'enable_lcp_optimization'        => array(
				'label'               => 'LCP Optimization',
				'description'         => 'Detect and prioritize the likely Largest Contentful Paint resource.',
				'group'               => 'Critical resources',
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
			'enable_cloudflare'              => array(
				'label'         => 'Cloudflare',
				'control_label' => 'Purge Cloudflare cache',
				'description'   => 'Purge Cloudflare when Powered Cache clears site cache.',
				'group'         => 'CDN and proxy',
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
			'dev_mode'                       => array(
				'label'         => 'Development Mode',
				'control_label' => 'Bypass cache temporarily',
				'description'   => 'Temporarily bypass cache behavior while working on the site.',
				'group'         => 'Developer tools',
			),
		);

		if ( isset( $overrides[ $key ] ) ) {
			$metadata = array_merge( $metadata, $overrides[ $key ] );
		}

		return $metadata;
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
