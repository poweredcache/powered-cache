<?php
/**
 * Compat with WPML
 *
 * @package PoweredCache\Compat
 * @link    https://wpml.org/
 */

namespace PoweredCache\Compat\WPML;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\SitePress' ) ) {

	add_action( 'powered_cache_create_config_file', __NAMESPACE__ . '\\generate_configuration_for_domains', 10, 3 );

	/**
	 * Create seperate configurations when WPML used in domain mapping mode
	 *
	 * @param string $config_file        Configuration path
	 * @param string $config_file_string Configuration content
	 * @param bool   $network_wide       whether powered cache activated network wide or not
	 *
	 * @since 2.2.2
	 */
	function generate_configuration_for_domains( $config_file, $config_file_string, $network_wide ) {
		if ( $network_wide ) {
			return;
		}

		if ( ! defined( 'WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN' ) ) {
			return;
		}

		$sitepress     = new \SitePress();
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
}
