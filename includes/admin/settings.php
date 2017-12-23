<?php
/**
 *
 * Settings page template
 *
 * @package PoweredCache
 * @subpackage PoweredCache/Settings
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$object_cache_methods = Powered_Cache_Admin_Helper::available_object_caches();
$current_section      = isset( $_GET['section'] ) ? $_GET['section'] : 'basic-options';
$show_submit          = true;

if ( in_array( $current_section, array( 'misc', 'extensions', 'premium', 'support' ) ) ) {
	$show_submit = false;
}
?>
<div class="wrap">
	<h1>
		<img width="22" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iMzJweCIgaWQ9IkxheWVyXzEiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMyIDMyOyIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMzIgMzIiIHdpZHRoPSIzMnB4IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQgMzM2KSI+PHBhdGggZD0iTS0xMTcuMTc2LTMzNC4wNjNoLTkuMzUzTC0xMzguMTExLTMyMGg5LjMwNGwtMTEuNzQ1LDE0LjA2M2wyNS4xMDUtMTguMzE2aC05LjgwOUwtMTE3LjE3Ni0zMzQuMDYzeiIvPjwvZz48L3N2Zz4=">
		<?php _e('Powered Cache','powered-cache'); ?>
	</h1>


<div id="poststuff" class="powered-cache-settings-page">

	<div id="post-body" class="metabox-holder columns-<?php echo( powered_cache_is_premium() ? '1' : '2' ); ?>">

	<h2 class="nav-tab-wrapper powered-cache-settings-nav">
		<?php

			$sections = Powered_Cache_Admin_Helper::admin_sections();
			foreach ($sections as $section => $title ): ?>
				<a
					href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'powered-cache', 'section' => $section ), 'admin.php' ) ) ); ?>"
					title="<?php esc_attr_e( $title, 'powered-cache' ); ?>"
					class="nav-tab <?php echo $current_section == $section ? 'nav-tab-active' : ''; ?>"
					>
					<?php esc_attr_e( $title, 'powered-cache' ); ?>
				</a>
			<?php endforeach; ?>
		</h2>



		<!-- main content -->
		<div id="post-body-content">
			<form  id="powered-cache-settings-form" method="post" action="" enctype="multipart/form-data" class="powered-cache-form">
				<?php wp_nonce_field( 'powered_cache_update_settings', 'powered_cache_settings_nonce' ); ?>
				<input type="hidden" name="action" value="powered_cache_update_settings">
				<input type="hidden" name="wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ); ?>'" />

				<?php
				$page = POWERED_CACHE_ADMIN_DIR . 'settings/' . $current_section . '.php';
				if ( file_exists( $page ) ) {
					do_action( 'powered_cache_before_load_settings_template_' . $current_section );
					include $page;
					do_action( 'powered_cache_after_load_settings_template_' . $current_section );
				}
				?>


				<!-- .meta-box-sortables .ui-sortable -->
				<?php if ( true === $show_submit ): ?>
					<div class="save">
						<?php submit_button( __( 'Save Changes', 'powered-cache' ), 'primary', 'submit', false ); ?>
					</div>
				<?php endif; ?>
			</form>

		</div>
		<!-- post-body-content -->

		<?php if ( ! powered_cache_is_premium() ): ?>
		<!-- sidebar -->
		<div id="postbox-container-1" class="postbox-container">

			<div class="sidebar-box">

				<div class="sidebar-postbox">


					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php _e( "Rate Us!", "powered-cache" ); ?></span> <span class="powered-cache-sidebar-icon dashicons dashicons-heart"></span> </h3>
						<?php echo sprintf(
							__( 'We would like to hear your thoughts about our plugin. Please review on <a target="_blank" href="%s">WordPress.org</a>',
								'powered-cache' ),
							'https://wordpress.org/support/plugin/powered-cache/reviews/'
						); ?>
					</div>
					<br>

					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php _e( "Become a Premium User", "powered-cache" ); ?></span><span class="powered-cache-sidebar-icon dashicons dashicons-awards"></span></h3>
						<?php _e( 'Premium users will reach more goodies', 'powered-cache' ); ?>
						<ul class="premium-benefits">
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'Premium Features', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'Premium Extensions', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'Friendly Bots', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'WP-CLI commands', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'Premium Support', 'powered-cache' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes"></span>
								<?php _e( 'No Ads', 'powered-cache' ); ?>
							</li>
						</ul>
						<a class="get-premium-small" href="https://poweredcache.com"><span><?php echo __( 'Buy Powered Cache Premium', 'powered-cache' ); ?></span></a>

					</div>

					<div class="inside">
						<h3 class="powered-cache-sidebar-title"><span><?php _e( "Support", "powered-cache" ); ?></span><span class="powered-cache-sidebar-icon dashicons dashicons-sos"></span></h3>
						<p>
							<?php _e( 'We are offering direct support to premium users only, free users welcome on WordPress.org forums.', 'powered-cache' ); ?>
							<br>
							<?php echo sprintf( __( 'Please check our <a href="%s">support policy</a>', 'powered-cache' ), 'https://poweredcache.com/support-policy/' ); ?>
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