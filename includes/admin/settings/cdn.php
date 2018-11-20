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

?>

<div class="meta-box-sortables ui-sortable">

	<div class="postbox">
		<div class="inside">

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="powered_cache_cdn_status"><?php _e( 'CDN', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_cdn_status" name="powered_cache_settings[cdn_status]" <?php checked( powered_cache_get_option( 'cdn_status' ), 1 ); ?> value="1"><?php _e( 'Enable CDN Integration', 'powered-cache' ); ?></label>
						<br>
						<span class="description"><?php _e('please make sure that your CDN is properly setup before enabling this feature','powered-cache'); ?></span>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/CDN-Setup">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cdn_ssl_disable"><?php _e( 'SSL', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="cdn_ssl_disable" name="powered_cache_settings[cdn_ssl_disable]" <?php checked( powered_cache_get_option( 'cdn_ssl_disable' ), 1 ); ?> value="1"><?php _e( 'Disable CDN to avoid "mixed content" errors', 'powered-cache' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_cdn_hostname_primary"><?php _e( 'CDN Hostname(s)', 'powered-cache' ); ?></label></th>
					<td>
						<?php
							$cdn_zones = powered_cache_get_option( 'cdn_zone', array('') );
							$hostnames = powered_cache_get_option( 'cdn_hostname', array('') );
						?>

						<div id="cdn-zones">
							<?php foreach ( $hostnames as $key => $cdn ): ?>
								<div class="cdn-zone <?php echo( 0 == $key ? 'primary-cdn-zone' : '' ); ?>">
									<label><input value="<?php echo esc_url( $cdn ); ?>" type="text" id="powered_cache_cdn_hostname_primary" size="50" name="powered_cache_settings[cdn_hostname][]"></label> <?php _e( 'for', 'powered-cache' ); ?>
									<select name="powered_cache_settings[cdn_zone][]">
										<?php foreach ( Powered_Cache_Admin_Helper::cdn_zones() as $zone => $zone_name ): ?>
											<option <?php selected( $cdn_zones[ $key ], $zone ); ?> value="<?php echo esc_attr( $zone ); ?>"><?php echo esc_attr( $zone_name ); ?></option>
										<?php endforeach; ?>
									</select>

									<?php if ( 0 !== $key ): ?>
										<span class="dashicons dashicons-no remove-cdn-item" onclick="powered_cache_remove_cdn_item();"> </span>
									<?php endif;?>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" onclick="powered_cache_clone_cdn_item();" class="button-secondary hide-if-no-js"><?php _e('Add Hostname','powered-cache'); ?></button>
					</td>
				</tr>


				<tr>
					<th scope="row"><label for="powered_cache_cdn_rejected_files"><?php _e( 'Rejected files', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_cdn_rejected_files" name="powered_cache_settings[cdn_rejected_files]" cols="50" rows="5"><?php echo powered_cache_get_option( 'cdn_rejected_files' ); ?></textarea><br>
						<span class="description"><?php _e( 'One URL per line. It can be full URL or absolute path.', 'powered-cache' ); ?></span>
					</td>
				</tr>

				</tbody>
				</table>


		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
