<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'pc_flash_messages' );

/**
 * Display flash messages
 *
 * @since 1.0
 */
function pc_flash_messages() {
	PC_Admin_Helper::get_flash_message();
}


add_action( 'admin_notices', 'pc_plugin_compatability_notices' );

/**
 * Display incompatible plugins
 *
 * @since 1.0
 */
function pc_plugin_compatability_notices() {

	$plugins = array(
		'w3-total-cache'                             => 'w3-total-cache/w3-total-cache.php',
		'wp-super-cache'                             => 'wp-super-cache/wp-cache.php',
		'quick-cache'                                => 'quick-cache/quick-cache.php',
		'hyper-cache'                                => 'hyper-cache/plugin.php',
		'hyper-cache-extended'                       => 'hyper-cache-extended/plugin.php',
		'wp-fast-cache'                              => 'wp-fast-cache/wp-fast-cache.php',
		'flexicache'                                 => 'flexicache/wp-plugin.php',
		'wp-fastest-cache'                           => 'wp-fastest-cache/wpFastestCache.php',
		'lite-cache'                                 => 'lite-cache/plugin.php',
		'gator-cache'                                => 'gator-cache/gator-cache.php',
		'wp-http-compression'                        => 'wp-http-compression/wp-http-compression.php',
		'wordpress-gzip-compression'                 => 'wordpress-gzip-compression/ezgz.php',
		'gzip-ninja-speed-compression'               => 'gzip-ninja-speed-compression/gzip-ninja-speed.php',
		'speed-booster-pack'                         => 'speed-booster-pack/speed-booster-pack.php',
		'wp-performance-score-booster'               => 'wp-performance-score-booster/wp-performance-score-booster.php',
		'remove-query-strings-from-static-resources' => 'remove-query-strings-from-static-resources/remove-query-strings.php',
		'query-strings-remover'                      => 'query-strings-remover/query-strings-remover.php',
		'wp-ffpc'                                    => 'wp-ffpc/wp-ffpc.php',
		'far-future-expiry-header'                   => 'far-future-expiry-header/far-future-expiration.php',
		'combine-css'                                => 'combine-css/combine-css.php',
		'super-static-cache'                         => 'super-static-cache/super-static-cache.php',
		'wpcompressor'                               => 'wpcompressor/wpcompressor.php',
		'check-and-enable-gzip-compression' 		 => 'check-and-enable-gzip-compression/richards-toolbox.php',
		'leverage-browser-caching-ninjas'   		 => 'leverage-browser-caching-ninjas/leverage-browser-caching-ninja.php',
		'force-gzip'								 => 'force-gzip/force-gzip.php'
	);


	$plugins = array_filter( $plugins, 'is_plugin_active' );

	if ( count( $plugins ) > 0 && current_user_can( apply_filters( 'pc_cap', 'manage_options' ) ) ) { ?>
		<div class="error">
			<h2><?php _e( 'Powered Cache', 'powered-cache' ); ?></h2>

			<p><?php printf( __( 'The following plugins are not compatible with  <b>%s</b> and will cause unintended results:', 'powered-cache' ), __( 'Powered Cache', 'powered-cache' ) ); ?></p>
			<ul class="incompatible-plugin-list">
				<?php
				foreach ( $plugins as $plugin ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin );
					echo '<li>' . $plugin_data['Name'] . '</span> <a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=deactivate_plugin&plugin=' . urlencode( $plugin ) ), 'deactivate_plugin' ) . '" class="button-secondary">' . __( 'Deactivate', 'powered-cache' ) . '</a></li>';
				}
				?>
			</ul>
		</div>
		<?php
	}
}

add_action( 'admin_notices', 'pc_advanced_cache_notices' );

/**
 * Show notices about page cache
 *
 * @since 1.0
 */
function pc_advanced_cache_notices() {

	if ( pc_is_saving_options() ) {
		return;
	}

	if ( true !== pc_get_option( 'enable_page_caching' ) ) {
		return;
	}

	$err = array();

	if ( ! defined( 'WP_CACHE' ) || true !== WP_CACHE ) {
		$err['wp_cache'] = sprintf( __( '<code>%s</code> is not in wp-config.php.', 'powered-cache' ), 'define("WP_CACHE", true);' );
	}

	if ( ! defined( 'POWERED_PAGE_CACHE' ) || true !== POWERED_PAGE_CACHE ) {
		$err['pc_page_cache'] = sprintf( __( '<code>%s</code> file was edited or deleted. You can re-create correct configuration files by saving settings.', 'powered-cache' ), basename( WP_CONTENT_DIR ) . '/advanced-cache.php' );
	}


	if ( defined( 'POWERED_PAGE_CACHE_HAS_PROBLEM' ) && true === POWERED_PAGE_CACHE_HAS_PROBLEM ) {
		$err['pc_page_cache_has_problem'] = sprintf( __( 'Powered Cache could not access dropin. Please check <code>%s</code> exist and accessible on your server.', 'powered-cache' ), PC_DROPIN_DIR . 'page-cache.php' );
	}

	// everything ok
	if ( empty( $err ) ) {
		return;
	}

	?>
	<div class="error">
		<h2><?php _e( 'Powered Cache', 'powered-cache' ); ?></h2>
		<strong><?php echo __( 'Page Caching feature could not work because:', 'powered-cache' ); ?></strong>

		<?php foreach ( $err as $error_msg ): ?>
			<p><?php echo $error_msg; ?></p>
		<?php endforeach; ?>
	</div>
	<?php


}

add_action( 'admin_notices', 'pc_object_cache_notices' );

/**
 * Display object cache broken msg
 *
 * @since 1.0
 */
function pc_object_cache_notices() {
	if ( defined( 'POWERED_OBJECT_CACHE_HAS_PROBLEM' ) && true === POWERED_OBJECT_CACHE_HAS_PROBLEM ) {
		$object_caches = PC_Admin_Helper::object_cache_dropins();
		$broken_file   = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';
		if ( isset( $object_caches[ pc_get_option( 'object_cache' ) ] ) ) {
			$broken_file = $object_caches[ pc_get_option( 'object_cache' ) ];
		}

		$message = sprintf( __( 'Powered Cache could not access object cache backend. Please check <code>%s</code> exist and accessible on your server.', 'powered-cache' ), $broken_file );
		?>
		<div class="error">
			<p><strong><?php echo __( 'Powered Cache:', 'powered-cache' ); ?></strong>
				<?php echo $message; ?>
			</p>
		</div>
		<?php
	}
}
