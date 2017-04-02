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

?>

<div class="meta-box-sortables ui-sortable">

	<div class="postbox">
		<div class="inside">

			<div id="premium-promote">
				<h1><?php _e( 'Powered Cache Premium, unlock more speed', 'powered-cache' ); ?></h1>
				<hr>
				<ul class="premium-benefits">
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'Premium Features:', 'powered-cache' ); ?>
						</strong> <?php _e( 'Access to premium features', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'Premium Extensions:', 'powered-cache' ); ?>
						</strong> <?php _e( 'All current and future premium extensions', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'Friendly Bots:', 'powered-cache' ); ?></strong>
						<?php _e( 'Get benefits from our bots like reqular cron check, preloading etc...', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'WP-CLI:', 'powered-cache' ); ?></strong>
						<?php _e( 'WP-CLI commands ready to save your time', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'Premium Support:', 'powered-cache' ); ?></strong>
						<?php _e( 'We are providing top-notch premium support to premium users', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php _e( 'No Ads', 'powered-cache' ); ?></strong>
					</li>
				</ul>

				<a class="get-premium" href="https://poweredcache.com"><span><?php echo __( 'Buy Powered Cache Premium', 'powered-cache' ); ?></span></a>

			</div>
		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
