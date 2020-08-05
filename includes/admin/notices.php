<?php
/**
 * Powered Cache Notices
 *
 * @package PoweredCache
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'powered_cache_flash_messages' );

/**
 * Display flash messages
 *
 * @since 1.0
 */
function powered_cache_flash_messages() {
	Powered_Cache_Admin_Helper::get_flash_message();
}


add_action( 'admin_notices', 'powered_cache_plugin_compatability_notices' );

/**
 * Display incompatible plugins
 *
 * @since 1.0
 */
function powered_cache_plugin_compatability_notices() {

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
		'check-and-enable-gzip-compression'          => 'check-and-enable-gzip-compression/richards-toolbox.php',
		'leverage-browser-caching-ninjas'            => 'leverage-browser-caching-ninjas/leverage-browser-caching-ninja.php',
		'force-gzip'                                 => 'force-gzip/force-gzip.php',
	);

	$plugins = array_filter( $plugins, 'is_plugin_active' );

	if ( count( $plugins ) > 0 && current_user_can( apply_filters( 'powered_cache_cap', 'manage_options' ) ) ) { ?>
		<div class="error">
			<h2><?php esc_html_e( 'Powered Cache', 'powered-cache' ); ?></h2>

			<p>
				<?php
				printf(
					'%s <b>%s</b> %s',
					esc_html__( 'The following plugins are not compatible with', 'powered-cache' ),
					esc_html__( 'Powered Cache', 'powered-cache' ),
					esc_html__( 'and will cause unintended results:', 'powered-cache' )
				);
				?>
			</p>

			<ul class="incompatible-plugin-list">
				<?php
				foreach ( $plugins as $plugin ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin );
					echo '<li>' . esc_attr( $plugin_data['Name'] ) . '</span> <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=deactivate_plugin&plugin=' . rawurlencode( $plugin ) ), 'deactivate_plugin' ) ) . '" class="button-secondary">' . esc_html__( 'Deactivate', 'powered-cache' ) . '</a></li>';
				}
				?>
			</ul>
		</div>
		<?php
	}
}

add_action( 'admin_notices', 'powered_cache_advanced_cache_notices' );

/**
 * Show notices about page cache
 *
 * @since 1.0
 */
function powered_cache_advanced_cache_notices() {

	/**
	 * Users might want to control this notice.
	 * eg: Varnish users don't need to turning on page cache.
	 * Yeah! they can use remove_action as well
	 *
	 * @since 1.2
	 */
	if ( apply_filters( 'powered_cache_disable_advanced_cache_notices', false ) ) {
		return;
	}

	if ( powered_cache_is_saving_options() ) {
		return;
	}

	if ( true !== powered_cache_get_option( 'enable_page_caching' ) ) {
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					'<strong>%1$s:</strong> %2$s <a href="%3$s">%4$s</a>',
					esc_html__( 'Important', 'powered-cache' ),
					esc_html__( 'please enable page caching on the Powered Cache', 'powered-cache' ),
					esc_url( admin_url( 'admin.php?page=powered-cache' ) ),
					esc_html__( 'settings page', 'powered-cache' )
				);
				?>
			</p>
		</div>
		<?php
		return;
	}

	$err = array();

	if ( ! defined( 'WP_CACHE' ) || true !== WP_CACHE ) {
		$err['wp_cache'] = sprintf( __( '<code>%s</code> is not in wp-config.php.', 'powered-cache' ), 'define("WP_CACHE", true);' );
	}

	if ( ! defined( 'POWERED_CACHE_PAGE_CACHING' ) || true !== POWERED_CACHE_PAGE_CACHING ) {
		$err['powered_cache_page_cache'] = sprintf( __( '<code>%s</code> file was edited or deleted. You can re-create correct configuration files by saving settings.', 'powered-cache' ), basename( WP_CONTENT_DIR ) . '/advanced-cache.php' );
	}

	if ( defined( 'POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM' ) && true === POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM ) {
		$err['powered_cache_page_cache_has_problem'] = sprintf( __( 'Powered Cache could not access dropin. Please check <code>%s</code> exist and accessible on your server.', 'powered-cache' ), POWERED_CACHE_DROPIN_DIR . 'page-cache.php' );
	}

	// everything ok
	if ( empty( $err ) ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'powered_cache_cap', 'manage_options' ) ) ) {
		return;
	}
	?>
	<div class="error">
		<h2><?php esc_html_e( 'Powered Cache', 'powered-cache' ); ?></h2>
		<strong><?php esc_html_e( 'Page Caching feature could not work because:', 'powered-cache' ); ?></strong>

		<?php foreach ( $err as $error_msg ) : ?>
			<p><?php echo $error_msg; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endforeach; ?>
	</div>
	<?php

}

add_action( 'admin_notices', 'powered_cache_object_cache_notices' );

/**
 * Display object cache broken msg
 *
 * @since 1.0
 */
function powered_cache_object_cache_notices() {
	$object_cache_backends = Powered_Cache_Admin_Helper::object_cache_dropins();
	$object_cache_driver   = powered_cache_get_option( 'object_cache' );
	$object_cache_dropin   = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';

	if ( ! current_user_can( apply_filters( 'powered_cache_cap', 'manage_options' ) ) ) {
		return;
	}

	// first check object cache file exist
	if ( isset( $object_cache_backends[ $object_cache_driver ] ) && ! file_exists( $object_cache_dropin ) ) {
		?>
		<div class="error">
			<p><strong><?php echo esc_html__( 'Powered Cache:', 'powered-cache' ); ?></strong>
				<?php
				printf(
					'%1$s <code>%2$s</code> %3$s',
					esc_html__( 'Phew! It looks your object cache file missing. Please check', 'powered-cache' ),
					esc_attr( $object_cache_dropin ),
					esc_html__( 'exist, writable and accessible on your server.', 'powered-cache' )
				);
				?>
			</p>
		</div>
		<?php
		return;
	}

	if ( defined( 'POWERED_OBJECT_CACHE_HAS_PROBLEM' ) && true === POWERED_OBJECT_CACHE_HAS_PROBLEM ) {
		if ( isset( $object_cache_backends[ $object_cache_driver ] ) ) {
			$broken_file = $object_cache_backends[ $object_cache_driver ];
		}

		?>
		<div class="error">
			<p><strong><?php echo esc_html__( 'Powered Cache:', 'powered-cache' ); ?></strong>
				<?php
				printf(
					'%1$s <code>%2$s</code> %3$s',
					esc_html__( 'Powered Cache could not access object cache backend. Please check', 'powered-cache' ),
					esc_attr( $broken_file ),
					esc_html__( 'exist and accessible on your server.', 'powered-cache' )
				);
				?>
			</p>
		</div>
		<?php
	}

}

add_action( 'admin_notices', 'powered_cache_maybe_htaccess_warning' );

/**
 * Notices for the .htaccess
 *
 * @since 1.2
 */
function powered_cache_maybe_htaccess_warning() {
	global $is_apache;

	if ( true !== powered_cache_get_option( 'configure_htaccess' ) ) {
		return;
	}

	if ( ! $is_apache ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'powered_cache_cap', 'manage_options' ) ) ) {
		return;
	}

	$htaccess_file = get_home_path() . '.htaccess';
	$message       = '';
	if ( ! file_exists( $htaccess_file ) ) {
		$message = sprintf( __( 'We can\'t find <code>%1$s</code> file on your server. Please create a new <code>.htaccess</code> file. <a href="%2$s">Codex</a> might help!.', 'powered-cache' ), '.htaccess', 'https://codex.wordpress.org/htaccess' );
	} elseif ( ! is_writeable( $htaccess_file ) ) {
		$message = sprintf( __( 'Oh no! It looks your <code>%s</code> file is not writable. Please make sure it is writable by the web server. Your website will much more faster when configured for Powered Cache.', 'powered-cache' ), '.htaccess' );
	}

	if ( empty( $message ) ) {
		return;
	}

	?>

	<div class="error">
		<p><strong><?php echo esc_html__( 'Powered Cache:', 'powered-cache' ); ?></strong>
			<?php echo wp_kses_post( $message ); ?>
		</p>
	</div>

	<?php

}
