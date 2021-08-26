<?php
/**
 * Domain mapping compatability.
 *
 * @package PoweredCache\Compat
 */

namespace PoweredCache\Compat;

use function PoweredCache\Utils\delete_page_cache;
use function PoweredCache\Utils\get_page_cache_dir;
use function PoweredCache\Utils\remove_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'mercator.mapping.deleted', __NAMESPACE__ . '\\mapping_cleanup' );
add_action( 'powered_cache_clean_site_cache_dir', __NAMESPACE__ . '\\maybe_clean_mapped_domain_dir' );
add_action( 'powered_cache_advanced_cache_purge_post', __NAMESPACE__ . '\\maybe_purge_on_post_update', 10, 2 );
add_action( 'powered_cache_advanced_cache_purge_on_comment_status_change', __NAMESPACE__ . '\\maybe_purge_on_comment_status_change', 10, 2 );
add_action( 'powered_cache_create_config_file', __NAMESPACE__ . '\\maybe_create_config_files', 10, 3 );
add_action( 'wp_delete_site', __NAMESPACE__ . '\\purge_on_site_delete' ); // works on WP 5.1+
add_action( 'powered_cache_after_clean_up', __NAMESPACE__ . '\\maybe_delete_config' );


/**
 * Delete caching directory and configuration of mapped domain
 * during the mapping delete
 *
 * @param Object $mapping Mapping object
 *
 * @since 2.0
 */
function mapping_cleanup( $mapping ) {
	$domain    = $mapping->get_domain();
	$site_path = wp_parse_url( esc_url( $domain ), PHP_URL_HOST );
	$base_dir  = get_page_cache_dir();

	$site_cache_dir = $base_dir . $site_path;

	if ( file_exists( $site_cache_dir ) ) {
		remove_dir( $site_cache_dir );
	}

	$config_dir       = WP_CONTENT_DIR . '/pc-config';
	$config_file_name = 'config-' . $site_path . '.php';
	$config_file      = trailingslashit( $config_dir ) . $config_file_name;

	if ( file_exists( $config_file ) ) {
		unlink( $config_file );
	}
}

/**
 * Remove mapped domains when purging the site cache
 *
 * @since 2.0
 */
function maybe_clean_mapped_domain_dir() {
	$mapped_domains = get_mapped_domains();
	if ( ! $mapped_domains ) {
		return;
	}

	$base_dir = get_page_cache_dir();

	foreach ( $mapped_domains as $domain ) {
		$site_path      = wp_parse_url( $domain, PHP_URL_HOST );
		$site_cache_dir = $base_dir . $site_path;
		if ( file_exists( $site_cache_dir ) ) {
			remove_dir( $site_cache_dir );
		}
	}
}

/**
 * Delete corresponding URLs from the mapped domain too.
 *
 * @param int   $post_id Post ID.
 * @param array $urls    The list of URLs that deleted.
 *
 * @since 2.0
 */
function maybe_purge_on_post_update( $post_id, $urls ) {
	$mapped_domains = get_mapped_domains();
	if ( ! $mapped_domains ) {
		return;
	}

	foreach ( $mapped_domains as $domain ) {
		$new_purge_urls = str_replace( site_url(), $domain, $urls );
		foreach ( $new_purge_urls as $new_purge_url ) {
			delete_page_cache( $new_purge_url );
		}
	}
}

/**
 * Delete corresponding URL from the mapped domain too.
 *
 * @param int    $post_id  Post ID.
 * @param string $post_url Post ID of the comment.
 */
function maybe_purge_on_comment_status_change( $post_id, $post_url ) {
	$mapped_domains = get_mapped_domains();
	if ( ! $mapped_domains ) {
		return;
	}

	foreach ( $mapped_domains as $domain ) {
		$post_url = str_replace( site_url(), $domain, $post_url );
		delete_page_cache( $post_url );
	}
}


/**
 * Get the list of mapped domains
 *
 * @return array
 * @since 2.0
 */
function get_mapped_domains() {
	$mapped_domains = [];

	if ( class_exists( '\Mercator\Mapping' ) ) {
		$mappings = \Mercator\Mapping::get_by_site( get_current_blog_id() );
		if ( ! is_wp_error( $mappings ) && $mappings ) {
			foreach ( $mappings as $mapping ) {
				if ( $mapping->is_active() ) {
					$mapped_domain    = $mapping->get_domain();
					$mapped_domains[] = esc_url( $mapped_domain );
				}
			}
		}
	}

	return $mapped_domains;
}


/**
 * Crete configuration pages for mapped domains.
 *
 * @param string $config_file        The path of the configuration file.
 * @param string $config_file_string The contents of the configurations.
 * @param bool   $network_wide       Whether network-wide configuration or not.
 *
 * @since 2.0
 */
function maybe_create_config_files( $config_file, $config_file_string, $network_wide ) {
	$config_dir = WP_CONTENT_DIR . '/pc-config';

	// skip on network-wide?
	if ( $network_wide ) {
		return;
	}

	$mapped_domains = get_mapped_domains();

	// don't have any active mapped domains?
	if ( ! $mapped_domains ) {
		return;
	}

	foreach ( $mapped_domains as $domain ) {
		$url_parts        = wp_parse_url( $domain );
		$config_file_name = 'config-' . $url_parts['host'] . '.php';

		$config_file = trailingslashit( $config_dir ) . $config_file_name;
		file_put_contents( $config_file, $config_file_string ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

}

/**
 * Cleanup mapping on site deletion.
 *
 * @param \WP_Site $site_object Site object.
 */
function purge_on_site_delete( $site_object ) {
	if ( class_exists( '\Mercator\Mapping' ) ) {
		$mappings = \Mercator\Mapping::get_by_site( $site_object->id );
		if ( ! is_wp_error( $mappings ) && $mappings ) {
			foreach ( $mappings as $mapping ) {
				mapping_cleanup( $mapping );
			}
		}
	}
}


/**
 * Delete config files for the mapped domains
 */
function maybe_delete_config() {
	$mapped_domains = get_mapped_domains();
	if ( ! $mapped_domains ) {
		return;
	}

	$config_dir = WP_CONTENT_DIR . '/pc-config';

	foreach ( $mapped_domains as $domain ) {
		$url_parts        = wp_parse_url( $domain );
		$config_file_name = 'config-' . $url_parts['host'] . '.php';

		$config_file = trailingslashit( $config_dir ) . $config_file_name;
		if ( file_exists( $config_file ) ) {
			unlink( $config_file );
		}
	}
}

