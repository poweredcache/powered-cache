<?php
/**
 * Admin Notices
 *
 * @package PoweredCache
 */

namespace PoweredCache\Admin\Notices;

use function PoweredCache\Utils\can_configure_htaccess;
use function PoweredCache\Utils\can_configure_object_cache;
use function PoweredCache\Utils\can_control_all_settings;
use function PoweredCache\Utils\get_object_cache_dropins;
use function PoweredCache\Utils\is_premium;
use const PoweredCache\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	if ( POWERED_CACHE_IS_NETWORK ) {
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_plugin_compatability_notices' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_advanced_cache_notices' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_object_cache_notices' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_htaccess_notice' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_purge_cache_plugin_notice' );
	} else {
		add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_plugin_compatability_notices' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_advanced_cache_notices' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_object_cache_notices' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_htaccess_notice' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_purge_cache_plugin_notice' );
	}

	add_action( 'activated_plugin', __NAMESPACE__ . '\\observe_plugin_changes', 10, 2 );
	add_action( 'deactivated_plugin', __NAMESPACE__ . '\\observe_plugin_changes', 10, 2 );
	add_action( 'admin_post_powered_cache_dismiss_notice', __NAMESPACE__ . '\\dismiss_notice' );
}

/**
 * Display incompatible plugins
 *
 * @since 1.0
 */
function maybe_display_plugin_compatability_notices() {
	$settings = \PoweredCache\Utils\get_settings();

	$plugins = array(
		'hummingbird-performance'           => 'hummingbird-performance/wp-hummingbird.php',
		'wp-rocket'                         => 'wp-rocket/wp-rocket.php',
		'w3-total-cache'                    => 'w3-total-cache/w3-total-cache.php',
		'wp-super-cache'                    => 'wp-super-cache/wp-cache.php',
		'hyper-cache'                       => 'hyper-cache/plugin.php',
		'hyper-cache-extended'              => 'hyper-cache-extended/plugin.php',
		'wp-fast-cache'                     => 'wp-fast-cache/wp-fast-cache.php',
		'flexicache'                        => 'flexicache/wp-plugin.php',
		'wp-fastest-cache'                  => 'wp-fastest-cache/wpFastestCache.php',
		'wp-http-compression'               => 'wp-http-compression/wp-http-compression.php',
		'wordpress-gzip-compression'        => 'wordpress-gzip-compression/ezgz.php',
		'gzip-ninja-speed-compression'      => 'gzip-ninja-speed-compression/gzip-ninja-speed.php',
		'speed-booster-pack'                => 'speed-booster-pack/speed-booster-pack.php',
		'wp-performance-score-booster'      => 'wp-performance-score-booster/wp-performance-score-booster.php',
		'check-and-enable-gzip-compression' => 'check-and-enable-gzip-compression/richards-toolbox.php',
		'swift-performance-lite'            => 'swift-performance-lite/performance.php',
		'swift-performance'                 => 'swift-performance/performance.php',
		'litespeed-cache'                   => 'litespeed-cache/litespeed-cache.php',
		'wp-optimize'                       => 'wp-optimize/wp-optimize.php',
	);

	if ( $settings['prefetch_links'] && is_premium() ) {
		$plugins['quicklink']    = 'quicklink/quicklink.php';
		$plugins['flying-pages'] = 'flying-pages/flying-pages.php';
		$plugins['instant-page'] = 'instant-page/instantpage.php';
	}

	$callback = POWERED_CACHE_IS_NETWORK ? 'is_plugin_active_for_network' : 'is_plugin_active';

	$plugins = array_filter( $plugins, $callback );

	if ( 0 >= count( $plugins ) ) {
		return;
	}
	?>

	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<div class="error">
			<p><?php esc_html_e( 'The following plugins are not compatible with Powered Cache and may cause unintended results:', 'powered-cache' ); ?></p>
			<ul class="incompatible-plugin-list">
				<?php
				foreach ( $plugins as $plugin ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin );
					echo '<li>' . esc_attr( $plugin_data['Name'] ) . '</span> <a href="' . esc_url_raw( wp_nonce_url( admin_url( 'admin-post.php?action=deactivate_plugin&plugin=' . rawurlencode( $plugin ) ), 'deactivate_plugin' ) ) . '" class="button-secondary">' . esc_html__( 'Deactivate', 'powered-cache' ) . '</a></li>'; // phpcs:ignore WordPressVIPMinimum.Security.ProperEscapingFunction.notAttrEscAttr
				}
				?>
			</ul>
		</div>
	<?php endif; ?>

	<?php
}


/**
 * Show notices about page cache
 *
 * @since 1.0
 */
function maybe_display_advanced_cache_notices() {
	/**
	 * Determine whether show or not show advanced cache related notices
	 * eg: Varnish users don't need to turning on page cache.
	 *
	 * @hook   powered_cache_disable_advanced_cache_notices
	 *
	 * @param  {boolean} $status false
	 *
	 * @return {boolean} New value
	 * @since  1.2
	 */
	if ( apply_filters( 'powered_cache_disable_advanced_cache_notices', false ) ) {
		return;
	}

	$settings = \PoweredCache\Utils\get_settings();

	if ( ! $settings['enable_page_cache'] ) {

		$settings_page = POWERED_CACHE_IS_NETWORK ? network_admin_url( 'admin.php?page=powered-cache#basic-options' ) : admin_url( 'admin.php?page=powered-cache#basic-options' );

		/* translators: %s: Powered Cache settings page URL */
		$message = sprintf( __( '<strong>Powered Cache:</strong> Page caching needs to be activated in order to speed up your website. Please activate it on <a href="%s">settings page</a>', 'powered-cache' ), esc_url( $settings_page ) );
		?>
		<div class="notice notice-warning">
			<p>
				<?php echo wp_kses_post( $message ); ?>
			</p>
		</div>
		<?php
		return;
	}

	if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
		$modify_time = filemtime( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
		if ( time() - absint( $modify_time ) < 10 ) {
			/**
			 * Modify drop-ins and can take ~5 seconds to place the configuration and it might cause misinformation to the user
			 * So, suppress the error message here when doing something related to settings.
			 *
			 * This wouldn't be an issue if there is no immediate redirection right after saving the settings. However, in that case
			 * the admin bar items won't be properly utilized unless refreshing the page
			 */
			return;
		}
	}

	$err = array();

	if ( ! defined( 'WP_CACHE' ) || true !== WP_CACHE ) {
		/* translators: %s: WP_CACHE definition*/
		$err['wp_cache'] = sprintf( __( '<code>%s</code> is not found in wp-config.php.', 'powered-cache' ), 'define("WP_CACHE", true);' );
	}

	if ( defined( 'WP_CACHE' ) && WP_CACHE && ( ! defined( 'POWERED_CACHE_PAGE_CACHING' ) || true !== POWERED_CACHE_PAGE_CACHING ) ) {
		/* translators: %s: advanced-cache.php drop-in path */
		$err['powered_cache_page_cache'] = sprintf( __( '<code>%s</code> file was edited or deleted. You can recreate the correct configuration files by saving Powered Cache settings.', 'powered-cache' ), basename( WP_CONTENT_DIR ) . '/advanced-cache.php' );
	}

	if ( defined( 'POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM' ) && POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM ) {
		/* translators: %s: page-cache.php drop-in path */
		$err['powered_cache_page_cache_has_problem'] = sprintf( __( 'Powered Cache could not access dropin. Please check <code>%s</code> exist and accessible on your server.', 'powered-cache' ), POWERED_CACHE_DROPIN_DIR . 'page-cache.php' );
	}

	// everything ok
	if ( empty( $err ) ) {
		return;
	}

	// dont show when settings just saved
	if ( did_action( 'powered_cache_settings_saved' ) ) {
		return;
	}

	$capability = POWERED_CACHE_IS_NETWORK ? 'manage_network' : 'manage_options';

	if ( ! current_user_can( $capability ) ) {
		return;
	}
	?>
	<div class="error">
		<p>
			<strong><?php esc_html_e( 'Page Cache is not working, because:', 'powered-cache' ); ?></strong>
		</p>
		<?php foreach ( $err as $error_msg ) : ?>
			<p><?php echo wp_kses_post( $error_msg ); ?></p>
		<?php endforeach; ?>
	</div>
	<?php

}


/**
 * Display object cache broken msg
 *
 * @since 1.0
 */
function maybe_display_object_cache_notices() {
	$settings = \PoweredCache\Utils\get_settings();

	$object_cache_backends = get_object_cache_dropins();
	$object_cache_driver   = $settings['object_cache'];
	$object_cache_dropin   = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';

	if ( ! can_configure_object_cache() ) {
		return;
	}

	// dont show when settings just saved
	if ( did_action( 'powered_cache_settings_saved' ) ) {
		return;
	}

	if ( file_exists( $object_cache_dropin ) ) {
		$modify_time = filemtime( $object_cache_dropin );
		if ( time() - absint( $modify_time ) < 10 ) { // just created
			return;
		}
	}

	// first check object cache file exist
	if ( isset( $object_cache_backends[ $object_cache_driver ] ) && ! file_exists( $object_cache_dropin ) ) {
		/* translators: %s: object cache dropin path */
		$message = sprintf( __( 'The object cache file seems missing. Please check <code>%s</code> exist, writable and accessible on your server.', 'powered-cache' ), $object_cache_dropin );
		?>
		<div class="error">
			<p><strong><?php esc_html_e( 'Powered Cache:', 'powered-cache' ); ?></strong>
				<?php echo wp_kses_post( $message ); ?>
			</p>
		</div>
		<?php
		return;
	}

	if ( defined( 'POWERED_OBJECT_CACHE_HAS_PROBLEM' ) && POWERED_OBJECT_CACHE_HAS_PROBLEM ) {
		$broken_file = '';

		if ( isset( $object_cache_backends[ $object_cache_driver ] ) ) {
			$broken_file = $object_cache_backends[ $object_cache_driver ];
		}
		/* translators: %s: object cache dropin path */
		$message = sprintf( __( 'The object cache file couldn\'t be loaded. Please check <code>%s</code> exist and accessible on your server.', 'powered-cache' ), $broken_file );
		?>
		<div class="error">
			<p><strong><?php esc_html_e( 'Powered Cache:', 'powered-cache' ); ?></strong>
				<?php echo wp_kses_post( $message ); ?>
			</p>
		</div>
		<?php
	}

}

/**
 * Notices for the .htaccess
 *
 * @since 1.2
 */
function maybe_display_htaccess_notice() {
	global $is_apache;

	if ( ! $is_apache ) {
		return;
	}

	$settings = \PoweredCache\Utils\get_settings();

	if ( ! $settings['auto_configure_htaccess'] ) {
		return;
	}

	if ( ! can_configure_htaccess() ) {
		return;
	}

	// dont show when settings just saved
	if ( did_action( 'powered_cache_settings_saved' ) ) {
		return;
	}

	$htaccess_file = get_home_path() . '.htaccess';

	if ( file_exists( $htaccess_file ) ) {
		$modify_time = filemtime( $htaccess_file );
		if ( time() - absint( $modify_time ) < 10 ) { // just modified
			return;
		}
	}

	$message = '';

	if ( ! file_exists( $htaccess_file ) ) {
		$message = __( 'The <code>.htaccess</code> couldn\'t be found on your server. Please create a new <code>.htaccess</code> file. (<a href="https://wordpress.org/support/article/htaccess/" target="_blank" rel="noopener">?</a>)', 'powered-cache' );
	} elseif ( ! is_writeable( $htaccess_file ) ) {
		$message = __( 'Oh no! It looks <code>.htaccess</code> file is not writable. Please make sure it is writable by the application server. Your website will be much faster when .htaccess is configured for Powered Cache.', 'powered-cache' );
	}

	if ( empty( $message ) ) {
		return;
	}

	?>

	<div class="error">
		<p><strong><?php esc_html_e( 'Powered Cache:', 'powered-cache' ); ?></strong>
			<?php echo wp_kses_post( $message ); ?>
		</p>
	</div>

	<?php
}

/**
 * Observe new plugin activation/deactivation
 *
 * @param string  $plugin       file
 * @param boolean $network_wide Whether plugin de/activated network wide or not
 *
 * @return void
 * @since 3.2
 */
function observe_plugin_changes( $plugin, $network_wide ) {
	if ( false !== stripos( $plugin, 'powered-cache' ) ) {
		return;
	}

	if ( $network_wide ) {
		set_site_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT, '1' );

		return;
	}

	set_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT, '1' );
}

/**
 * Display cache purging notice upon a new plugin activated/deactivated
 *
 * @return void
 * @since 3.2
 */
function maybe_display_purge_cache_plugin_notice() {
	$has_notice = false;

	if ( POWERED_CACHE_IS_NETWORK && current_user_can( 'manage_network' ) ) {
		$has_notice = get_site_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
		$purge_url  = wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_page_cache_network' ), 'powered_cache_purge_page_cache_network' );
	} elseif ( current_user_can( 'activate_plugins' ) ) {
		$has_notice = get_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
		$purge_url  = wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_purge_all_cache' ), 'powered_cache_purge_all_cache' );
	}

	if ( $has_notice ) {
		$message = __( '<strong>Powered Cache:</strong> One or more plugins have been activated or deactivated; consider clearing the cache if these changes impact your site\'s front end.', 'powered-cache' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo wp_kses_post( $message ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url_raw( $purge_url ); ?>" class="button-primary">
					<?php esc_html_e( 'Purge Cache', 'powered-cache' ); ?>
				</a>
				<a href="<?php echo esc_url_raw( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_dismiss_notice&notice=' . PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT ), 'powered_cache_dismiss_notice' ) ); ?>" class="button-secondary">
					<?php esc_html_e( 'Dismiss this notice', 'powered-cache' ); ?>
				</a>
			</p>
			<a href="<?php echo esc_url_raw( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_dismiss_notice&notice=' . PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT ), 'powered_cache_dismiss_notice' ) ); ?>" type="button" class="notice-dismiss" style="text-decoration:none;">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'powered-cache' ); ?></span>
			</a>
		</div>
		<?php
	}
}

/**
 * Dismis given notice
 *
 * @return void
 * @since 3.2
 */
function dismiss_notice() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'powered_cache_dismiss_notice' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		wp_nonce_ays( '' );
	}

	if ( current_user_can( 'manage_options' ) && ! empty( $_GET['notice'] ) ) {
		$notice = sanitize_text_field( wp_unslash( $_GET['notice'] ) );

		if ( POWERED_CACHE_IS_NETWORK ) {
			delete_site_transient( $notice );
		} else {
			delete_transient( $notice );
		}
	}

	wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
	exit;
}
