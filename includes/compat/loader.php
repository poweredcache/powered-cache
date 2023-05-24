<?php
/**
 * Compatability related functionalities with 3rd party
 *
 * @package PoweredCache
 */

namespace PoweredCache\Compat;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_compat_files' );

/**
 * Load compatibility files
 * since 2.0
 */
function load_compat_files() {
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/a3-lazy-load.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/advanced-custom-fields.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/autoptimize.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/bj-lazy-load.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/lazy-load.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/rocket-lazy-load.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/jetpack-boost.php';

	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/cookie-law-info.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/cookie-notice.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/eu-cookie-law.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/gdpr.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/cookies-and-content-security-policy.php';

	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/cornerstone-builder.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/wpml.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/woocommerce-multilingual.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/beaver-builder.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/divi.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/elementor.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/woocommerce.php';
	require_once POWERED_CACHE_COMPAT_DIR . 'plugins/phastpress.php';

	if ( is_multisite() && defined( 'SUNRISE' ) && SUNRISE ) {
		require POWERED_CACHE_COMPAT_DIR . 'domain-mapping.php';
	}
}

/**
 * Adds conflict message for 3rd party plugins
 *
 * @param string $plugin_name The plugin name that causes feature conflict
 * @param string $feature     The feature name (eg: lazy load)
 */
function add_conflict_message( $plugin_name, $feature ) {
	?>
	<div class="sui-notice sui-notice-warning sui-padding">

		<div class="sui-notice-content">

			<div class="sui-notice-message">
				<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
				<p>
					<?php
					/* translators: %1$s plugin name,  %2$s conflicted feature name (Eg lazyload) */
					printf( esc_html__( 'It seems %1$s is activated on your site. Powered Cache works perfectly fine with %1$s but you cannot use %2$s functionalities that conflic with %1$s plugin unless you deactivate it.', 'powered-cache' ), esc_html( $plugin_name ), esc_html( $feature ) );
					?>
					<br>
				</p>
			</div>

		</div>

	</div>
	<?php
}
