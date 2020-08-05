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
				<h1><?php esc_html_e( 'Powered Cache Premium, unlock more speed', 'powered-cache' ); ?></h1>
				<hr>
				<ul class="premium-benefits">
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'Premium Features:', 'powered-cache' ); ?>
						</strong> <?php esc_html_e( 'Access to premium features', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'Premium Extensions:', 'powered-cache' ); ?>
						</strong> <?php esc_html_e( 'All current and future premium extensions', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'Friendly Bots:', 'powered-cache' ); ?></strong>
						<?php esc_html_e( 'Get benefits from our bots like reqular cron check, preloading etc...', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'WP-CLI:', 'powered-cache' ); ?></strong>
						<?php esc_html_e( 'WP-CLI commands ready to save your time', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'Premium Support:', 'powered-cache' ); ?></strong>
						<?php esc_html_e( 'We are providing top-notch premium support to premium users', 'powered-cache' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e( 'No Ads', 'powered-cache' ); ?></strong>
					</li>
				</ul>

				<a class="get-premium" href="https://poweredcache.com"><span><?php esc_html_e( 'Buy Powered Cache Premium', 'powered-cache' ); ?></span></a>

			</div>
		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
