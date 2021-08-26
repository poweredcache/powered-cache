<?php
/**
 * Media Optimization
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Class MediaOptimizer
 */
class MediaOptimizer {
	/**
	 * Plugin settings
	 *
	 * @var array $settings
	 */
	private $settings;

	/**
	 * Return an instance of the current class
	 *
	 * @return MediaOptimizer
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

		if ( $this->settings['disable_wp_embeds'] ) {
			add_action( 'init', [ $this, 'disable_embed' ], 9999 );
		}

		if ( $this->settings['disable_emoji_scripts'] ) {
			add_action( 'init', [ $this, 'disable_emoji' ] );
		}

	}

	/**
	 * Disable WP embeds
	 */
	public function disable_embed() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

		add_filter( 'tiny_mce_plugins', [ $this, 'disable_embeds_tinymce' ] );
		add_filter( 'rewrite_rules_array', [ $this, 'disable_embeds_rewrite' ] );
	}

	/**
	 * Remove wpembed from the registered plugins
	 *
	 * @param array $plugins registered tinymce plugins
	 *
	 * @return array
	 */
	public function disable_embeds_tinymce( $plugins ) {
		return array_diff( $plugins, [ 'wpembed' ] );
	}

	/**
	 * Remove embed specific rewrite rules
	 *
	 * @param array $rules rewrite rules
	 *
	 * @return mixed
	 */
	public function disable_embeds_rewrite( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}

		return $rules;
	}

	/**
	 * Disable emoji
	 */
	public function disable_emoji() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', [ $this, 'disable_emoji_tinymce' ] );
		add_filter( 'wp_resource_hints', [ $this, 'disable_emoji_dns_prefetch' ], 10, 2 );
	}

	/**
	 * Remove emoji from plugins
	 *
	 * @param array $plugins tinymce plugins
	 *
	 * @return array
	 */
	public function disable_emoji_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, [ 'wpemoji' ] );
		}

		return [];
	}

	/**
	 * Remove emoji CDN from prefetch
	 *
	 * @param array  $urls          prefetch urls
	 * @param string $relation_type rel type
	 *
	 * @return array
	 */
	public function disable_emoji_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

			$urls = array_diff( $urls, [ $emoji_svg_url ] );
		}

		return $urls;
	}


}
