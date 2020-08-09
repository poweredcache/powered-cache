<?php
/**
 * Settings page template
 *
 * @package    PoweredCache
 * @subpackage PoweredCache/Settings
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$object_cache_methods = Powered_Cache_Admin_Helper::available_object_caches();
$current_section      = isset( $_GET['section'] ) ? $_GET['section'] : 'basic-options'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$show_submit          = true;

if ( in_array( $current_section, array( 'misc', 'extensions', 'premium', 'support' ), true ) ) {
	$show_submit = false;
}
?>
<div class="wrap">
	<h1>
		<img width="22" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iMzJweCIgaWQ9IkxheWVyXzEiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMyIDMyOyIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMzIgMzIiIHdpZHRoPSIzMnB4IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQgMzM2KSI+PHBhdGggZD0iTS0xMTcuMTc2LTMzNC4wNjNoLTkuMzUzTC0xMzguMTExLTMyMGg5LjMwNGwtMTEuNzQ1LDE0LjA2M2wyNS4xMDUtMTguMzE2aC05LjgwOUwtMTE3LjE3Ni0zMzQuMDYzeiIvPjwvZz48L3N2Zz4=">
		<?php esc_html_e( 'Powered Cache', 'powered-cache' ); ?>
	</h1>


<div id="poststuff" class="powered-cache-settings-page">

	<div id="post-body" class="metabox-holder columns-<?php echo( powered_cache_is_premium() ? '1' : '2' ); ?>">


	<h2 class="nav-tab-wrapper powered-cache-settings-nav">
		<?php
		$sections = Powered_Cache_Admin_Helper::admin_sections();
		foreach ( $sections as $section => $title ) {
			printf(
				'<a href="%1$s" title="%2$s" class="nav-tab %3$s" data-target-section="%4$s">%2$s</a>',
				esc_url( admin_url( add_query_arg( array( 'page' => 'powered-cache', ), 'admin.php' ) ) . '#top#' . $section ),
				esc_attr__( $title, 'powered-cache' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				esc_attr( ( $current_section === $section ? 'nav-tab-active' : '' ) ),
				esc_attr( $section )
			);
		}
		?>
	</h2>

		<!-- main content -->
		<div id="post-body-content">
			<form id="powered-cache-settings-form" method="post" action="" enctype="multipart/form-data" class="powered-cache-form">
				<?php wp_nonce_field( 'powered_cache_update_settings', 'powered_cache_settings_nonce' ); ?>
				<input type="hidden" name="action" value="powered_cache_update_settings">
				<input type="hidden" name="wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ); ?>'"/>

				<?php
				$sections = Powered_Cache_Admin_Helper::admin_sections();
				foreach ( $sections as $section => $section_title ) {
					if('support' === $section){
						continue;
					}
					$template = POWERED_CACHE_ADMIN_DIR . 'settings/' . $section . '.php';
					if ( file_exists( $template ) ) {
						include $template;
					}
				}
				?>
				<div class="save">
					<?php submit_button( __( 'Save Changes', 'powered-cache' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<!-- post-body-content -->

		<?php if ( ! powered_cache_is_premium() ) : ?>
		<!-- sidebar -->
		<div id="postbox-container-1" class="postbox-container">

			<div class="sidebar-box">

				<div class="sidebar-postbox">


					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php esc_html_e( 'Rate Us!', 'powered-cache' ); ?></span> <span class="powered-cache-sidebar-icon dashicons dashicons-heart"></span> </h3>
						<?php
						echo wp_kses(
							sprintf(
								__(
									'We would like to hear your thoughts about our plugin. Please review on <a target="_blank" rel="noopener" href="%s">WordPress.org</a>',
									'powered-cache'
								),
								'https://wordpress.org/support/plugin/powered-cache/reviews/'
							),
							array(
								'a' => array(
									'target' => true,
									'rel'    => true,
									'href'   => true,
								),
							)
						);
						?>
					</div>
					<br>

					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php esc_html_e( 'Become a Premium User', 'powered-cache' ); ?></span><span class="powered-cache-sidebar-icon dashicons dashicons-awards"></span></h3>
						<?php esc_html_e( 'Premium users will reach more goodies', 'powered-cache' ); ?>
						<ul class="premium-benefits">
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Premium Features', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Premium Extensions', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Friendly Bots', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'WP-CLI commands', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Premium Support', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'No Ads', 'powered-cache' ); ?>
							</li>
						</ul>
						<a class="get-premium-small" href="https://poweredcache.com"><span><?php echo esc_html__( 'Buy Powered Cache Premium', 'powered-cache' ); ?></span></a>

					</div>

					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php esc_html_e( 'Support', 'powered-cache' ); ?></span><span class="powered-cache-sidebar-icon dashicons dashicons-sos"></span></h3>
						<p>
							<?php esc_html_e( 'We are offering direct support to premium users only, free users welcome on WordPress.org forums.', 'powered-cache' ); ?>
							<br>
							<?php
							echo wp_kses(
								sprintf( __( 'Please check our <a target="_blank" href="%s" rel="noopener">support policy</a>', 'powered-cache' ), 'https://poweredcache.com/support-policy/' ),
								array(
									'a' => array(
										'target' => true,
										'href'   => true,
										'rel'    => true,
									),
								)
							);
							?>
						</p>
					</div>

					<!-- .inside -->

				</div>
				<!-- .postbox -->

			</div>
			<!-- .meta-box-sortables -->

		</div>
		<!-- #postbox-container-1 .postbox-container -->
		<?php endif; ?>

	</div>
	<!-- #post-body .metabox-holder .columns-2 -->

	<br class="clear">
</div>
<!-- #poststuff -->

</div> <!-- .wrap -->
