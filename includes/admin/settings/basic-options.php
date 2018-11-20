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
					<th scope="row"><label for="powered_cache_caching_status"><?php _e( 'Page Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" <?php checked( powered_cache_get_option( 'enable_page_caching' ), 1 ); ?> id="powered_cache_caching_status" name="powered_cache_settings[enable_page_caching]" value="1">
							<?php _e( 'Enable page caching that will reduce the response time of your site.', 'powered-cache' ); ?>
							(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Page-Cache">?</a>)
						</label>
						<?php global $is_apache;
						if ( $is_apache && ( ( is_multisite() && current_user_can( 'manage_network' ) ) || ( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ) ): ?>
							<div id="configure-htaccess-option">
								<label><input type="checkbox" id="cache_configure_htaccess" name="powered_cache_settings[configure_htaccess]" <?php checked( powered_cache_get_option( 'configure_htaccess' ), 1 ); ?> value="1"><?php _e( 'Configure <code>.htaccess</code> file automatically.', 'powered-cache' ); ?></label>
							</div>
						<?php endif; ?>
					</td>
				</tr>

				<?php
				// additional control needed for cache flushing
				if ( ( is_multisite() && current_user_can( 'manage_network' ) ) || ( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ): ?>
				<tr>
					<th scope="row"><label for="powered_cache_object_cache"><?php _e( 'Object Caching', 'powered-cache' ); ?></label></th>
					<td>
						<select name="powered_cache_settings[object_cache]" id="powered_cache_object_cache">
							<option value="off" <?php selected( powered_cache_get_option( 'object_cache' ), 'off' ); ?>><?php _e( 'Off', 'powered-cache' ); ?></option>
							<?php
							foreach($object_cache_methods as $obj_cache):
							?>
								<option <?php selected( powered_cache_get_option( 'object_cache' ), $obj_cache ); ?> value="<?php echo $obj_cache; ?>"><?php echo ucfirst( $obj_cache ); ?></option>
							<?php endforeach;?>
						</select>
						<?php echo __( 'Speed up dynamic pageviews', 'powered-cache' ); ?>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Object-Cache">?</a>)
					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><label for="powered_cache_mobile_cache"><?php _e( 'Mobile Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_mobile_cache"  name="powered_cache_settings[cache_mobile]" <?php checked( powered_cache_get_option( 'cache_mobile' ), 1 ); ?> value="1"><?php _e('Enable caching for mobile devices.','powered-cache'); ?></label>

						<div id="separate-mobile-cache" style="display:<?php echo( '1' == powered_cache_get_option( 'cache_mobile' ) ? '' : 'none;' ) ?>">
							<label><input type="checkbox" id="cache_mobile_separate_file"  name="powered_cache_settings[cache_mobile_separate_file]" <?php checked( powered_cache_get_option( 'cache_mobile_separate_file' ), 1 ); ?> value="1"><?php _e('Use separate cache file for mobile.','powered-cache'); ?></label>
						</div>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Mobile-Cache">?</a>)

					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_loggedin_user_cache"><?php _e( 'Logged in user cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_loggedin_user_cache"  name="powered_cache_settings[loggedin_user_cache]" <?php checked( powered_cache_get_option( 'loggedin_user_cache' ), 1 ); ?> value="1"><?php _e('Show cached page for logged in users','powered-cache'); ?></label>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Logged-in-user-cache">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_ssl_cache"><?php _e( 'SSL Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_ssl_cache"  name="powered_cache_settings[ssl_cache]"  <?php checked( powered_cache_get_option( 'ssl_cache' ), 1 ); ?> value="1"><?php _e('Enable caching for SSL','powered-cache'); ?></label>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Caching-on-https">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_gzip_status"><?php _e( 'Gzip', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_gzip_status"  name="powered_cache_settings[gzip_compression]"  <?php checked( powered_cache_get_option( 'gzip_compression' ), 1 ); ?> value="1"><?php _e('Enable gzip compression','powered-cache'); ?></label>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Enable-gzip-compression">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_cache_timeout"><?php _e( 'Cache Timeout', 'powered-cache' ); ?></label></th>
					<td>
						<?php
						$cache_timeout_in_minutes = powered_cache_get_option( 'cache_timeout' );
						list( $cache_timeout, $selected_interval ) = Powered_Cache_Admin_Helper::get_timeout_interval( $cache_timeout_in_minutes );
						?>
						<label><input size="5" id="powered_cache_cache_timeout" type="text" value="<?php echo $cache_timeout; ?>" name="powered_cache_settings[cache_timeout]"> </label>
						<label>
							<select name="powered_cache_settings[cache_timeout_interval]" id="powered_cache_cache_timeout_interval">
								<option <?php selected( 'MINUTE', $selected_interval ); ?> value="MINUTE"><?php echo __( 'Minute', 'powered-cache' ); ?></option>
								<option <?php selected( 'HOUR', $selected_interval ); ?> value="HOUR"><?php echo __( 'Hour', 'powered-cache' ); ?></option>
								<option <?php selected( 'DAY', $selected_interval ); ?> value="DAY"><?php echo __( 'Day', 'powered-cache' ); ?></option>
							</select>
						</label>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Cache-Timeout">?</a>)
					</td>
				</tr>



				</tbody>
			</table>

		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
