<?php
/**
 * Core plugin functionality.
 *
 * @package PoweredCache
 */

namespace PoweredCache\Core;

use PoweredCache\Async\CachePreloader;
use PoweredCache\Async\CachePurger;
use PoweredCache\Async\DatabaseOptimizer;
use PoweredCache\CompatibilityRules;
use PoweredCache\Config;
use PoweredCache\SettingsRestController;
use const PoweredCache\Constants\DB_CLEANUP_COUNT_CACHE_KEY;
use const PoweredCache\Constants\MENU_SLUG;
use PoweredCache\Optimizer\JS;
use \WP_Error as WP_Error;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\\i18n' );
	add_action( 'init', __NAMESPACE__ . '\\init' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_scripts' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_styles' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\block_editor_assets' );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_async_process' );
	CompatibilityRules::factory()->setup();
	SettingsRestController::factory();

	// Hook to allow async or defer on asset loading.
	add_filter( 'script_loader_tag', __NAMESPACE__ . '\\script_loader_tag', 10, 2 );

	/**
	 * Fires after powered cache loaded
	 *
	 * @hook  powered_cache_loaded
	 *
	 * @since 2.0
	 */
	do_action( 'powered_cache_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'powered-cache' ); // This filter is documented in /wp-includes/l10n.php.
	load_textdomain( 'powered-cache', WP_LANG_DIR . '/powered-cache/powered-cache-' . $locale . '.mo' );
	load_plugin_textdomain( 'powered-cache', false, plugin_basename( POWERED_CACHE_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @return void
 */
function init() {
	/**
	 * Fires during init
	 *
	 * @hook  powered_cache_init
	 *
	 * @since 2.0
	 */
	do_action( 'powered_cache_init' );
}

/**
 * Activate the plugin
 *  `POWERED_CACHE_IS_NETWORK` useless on networkwide activation at first
 *
 * @param bool $network_wide Whether network-wide configuration or not
 *
 * @return void
 */
function activate( $network_wide ) {
	$settings = \PoweredCache\Utils\get_settings( $network_wide );
	Config::factory()->save_configuration( $settings, $network_wide );
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @param bool $network_wide Whether network-wide configuration or not
 *
 * @return void
 */
function deactivate( $network_wide ) {
	Config::factory()->clean_up();

	// cancel async jobs
	$cache_preloader = CachePreloader::factory();
	$cache_preloader->cancel_process();
	$db_optimizer = DatabaseOptimizer::factory();
	$db_optimizer->cancel_process();

	// cleanup transients
	delete_site_transient( DB_CLEANUP_COUNT_CACHE_KEY );
	delete_transient( DB_CLEANUP_COUNT_CACHE_KEY );
}


/**
 * The list of knows contexts for enqueuing scripts/styles.
 *
 * @return array
 */
function get_enqueue_contexts() {
	return [ 'admin', 'frontend', 'shared', 'classic-editor' ];
}

/**
 * Generate an URL to a script, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $script  Script file name (no .js extension)
 * @param string $context Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string|WP_Error URL
 */
function script_url( $script, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in PoweredCache script loader.' );
	}

	return POWERED_CACHE_URL . "dist/js/{$script}.js";

}

/**
 * Generate an URL to a stylesheet, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $stylesheet Stylesheet file name (no .css extension)
 * @param string $context    Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string URL
 */
function style_url( $stylesheet, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in PoweredCache stylesheet loader.' );
	}

	return POWERED_CACHE_URL . "dist/css/{$stylesheet}.css";

}

/**
 * Return generated asset metadata for a dist file.
 *
 * @param string $asset Asset file name without extension.
 * @param string $type  Asset type, either js or css.
 *
 * @return array
 */
function asset_metadata( $asset, $type ) {
	$path = POWERED_CACHE_PATH . "dist/{$type}/{$asset}.asset.php";

	if ( file_exists( $path ) ) {
		$metadata = require $path;

		if ( is_array( $metadata ) ) {
			return array(
				'dependencies' => isset( $metadata['dependencies'] ) && is_array( $metadata['dependencies'] ) ? $metadata['dependencies'] : array(),
				'version'      => ! empty( $metadata['version'] ) ? $metadata['version'] : POWERED_CACHE_VERSION,
			);
		}
	}

	return array(
		'dependencies' => array(),
		'version'      => POWERED_CACHE_VERSION,
	);
}

/**
 * Enqueue scripts for admin.
 *
 * @param string $hook Current hook.
 *
 * @return void
 */
function admin_scripts( $hook ) {

	$classic_editor_hooks = [ 'post-new.php', 'post.php' ];

	if ( in_array( $hook, $classic_editor_hooks, true ) ) {
		$classic_editor_asset = asset_metadata( 'classic-editor', 'js' );

		wp_enqueue_script(
			'powered-cache-classic-editor',
			script_url( 'classic-editor', 'classic-editor' ),
			array_values( array_unique( array_merge( [ 'jquery' ], $classic_editor_asset['dependencies'] ) ) ),
			$classic_editor_asset['version'],
			true
		);
	}

	if ( empty( $_GET['page'] ) || MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$admin_asset = asset_metadata( 'admin', 'js' );

	wp_enqueue_script(
		'powered-cache-admin',
		script_url( 'admin', 'admin' ),
		array_values(
			array_unique(
				array_merge(
					[
						'jquery',
						'lodash',
						'wp-api-fetch',
						'wp-components',
						'wp-element',
						'wp-i18n',
					],
					$admin_asset['dependencies']
				)
			)
		),
		$admin_asset['version'],
		true
	);

	$settings_app_config = [
		'namespace'       => SettingsRestController::REST_NAMESPACE,
		'restRoot'        => esc_url_raw( rest_url() ),
		'restNonce'       => wp_create_nonce( 'wp_rest' ),
		'settingsFormUrl' => self_admin_url( 'admin.php?page=' . MENU_SLUG . '&section=misc' ),
		'settingsNonce'   => wp_create_nonce( 'powered_cache_update_settings' ),
		'purgeAllUrl'     => wp_nonce_url( self_admin_url( 'admin-post.php?action=powered_cache_purge_all_cache' ), 'powered_cache_purge_all_cache' ),
		'isPremium'       => \PoweredCache\Utils\is_premium(),
		'upgradeUrl'      => 'https://poweredcache.com/?utm_source=poweredcache&utm_medium=plugin&utm_campaign=settings_upgrade',
		'docsUrl'         => \PoweredCache\Utils\get_doc_url( '/' ),
	];

	/**
	 * Filter settings app bootstrap configuration.
	 *
	 * @hook powered_cache_settings_app_config
	 *
	 * @param {array} $settings_app_config Settings app bootstrap configuration.
	 *
	 * @return {array} New value.
	 *
	 * @since 4.0.0
	 */
	$settings_app_config = apply_filters( 'powered_cache_settings_app_config', $settings_app_config );

	wp_add_inline_script(
		'powered-cache-admin',
		'window.poweredCacheSettingsApp = ' . wp_json_encode( $settings_app_config ) . ';',
		'before'
	);

	wp_set_script_translations(
		'powered-cache-admin',
		'powered-cache',
		plugin_dir_path( POWERED_CACHE_PLUGIN_FILE ) . 'languages'
	);

}

/**
 * Enqueue Block Editor assets
 *
 * @since 2.0
 */
function block_editor_assets() {

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	/**
	 * Min. WP 5.3 required for block editor plugin due to useSelect
	 * Likely the older version of react didn't support hooks.
	 * The post meta-box works with compat mode vice-versa...
	 */
	if ( version_compare( get_bloginfo( 'version' ), '5.3', '>=' ) ) {
		$editor_asset = asset_metadata( 'editor', 'js' );

		wp_register_script(
			'powered-cache-editor',
			script_url( 'editor', 'admin' ),
			array_values(
				array_unique(
					array_merge(
						[
							'jquery',
							'lodash',
							'wp-i18n',
							'wp-edit-post',
							'wp-components',
							'wp-compose',
							'wp-data',
							'wp-edit-post',
							'wp-element',
							'wp-plugins',
						],
						$editor_asset['dependencies']
					)
				)
			),
			$editor_asset['version'],
			true
		);

		wp_enqueue_script( 'powered-cache-editor' );

		wp_set_script_translations(
			'powered-cache-editor',
			'powered-cache',
			plugin_dir_path( POWERED_CACHE_PLUGIN_FILE ) . 'languages'
		);

	}
}

/**
 * Enqueue styles for admin.
 *
 * @return void
 */
function admin_styles() {
	// load on the powered cache page only
	if ( empty( $_GET['page'] ) || MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$admin_style_asset = asset_metadata( 'admin-style', 'css' );

	wp_enqueue_style(
		'powered-cache-admin',
		style_url( 'admin-style', 'admin' ),
		$admin_style_asset['dependencies'],
		$admin_style_asset['version']
	);

}

/**
 * Add async/defer attributes to enqueued scripts that have the specified script_execution flag.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 *
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 *
 * @return string
 */
function script_loader_tag( $tag, $handle ) {
	$script_execution = wp_scripts()->get_data( $handle, 'script_execution' );

	if ( ! $script_execution ) {
		return $tag;
	}

	if ( 'async' !== $script_execution && 'defer' !== $script_execution ) {
		return $tag;
	}

	// Abort adding async/defer for scripts that have this script as a dependency. _doing_it_wrong()?
	foreach ( wp_scripts()->registered as $script ) {
		if ( in_array( $handle, $script->deps, true ) ) {
			return $tag;
		}
	}

	// Add the attribute if it hasn't already been added.
	if ( ! preg_match( ":\s$script_execution(=|>|\s):", $tag ) ) {
		$tag = preg_replace( ':(?=></script>):', " $script_execution", $tag, 1 );
	}

	return $tag;
}

/**
 * Invoke async classes
 */
function register_async_process() {
	DatabaseOptimizer::factory();
	CachePurger::factory();
}
