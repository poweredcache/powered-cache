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
					<th scope="row"><label for="pc_caching_status"><?php _e( 'Page Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" <?php checked( pc_get_option( 'enable_page_caching' ), 1 ); ?> id="pc_caching_status" name="pc_settings[enable_page_caching]" value="1">
							<?php _e( 'Enable page caching that will reduce the response time of your site.', 'powered-cache' ); ?>
							(<a target="_blank" href="http://docs.poweredcache.com/article/4-page-cache">?</a>)
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_object_cache"><?php _e( 'Object Caching', 'powered-cache' ); ?></label></th>
					<td>
						<select name="pc_settings[object_cache]" id="pc_object_cache">
							<option value="off" <?php selected( pc_get_option( 'object_cache' ), 'off' ); ?>><?php _e( 'Off', 'powered-cache' ); ?></option>
							<?php
							foreach($object_cache_methods as $obj_cache):
							?>
								<option <?php selected( pc_get_option( 'object_cache' ), $obj_cache ); ?> value="<?php echo $obj_cache; ?>"><?php echo ucfirst( $obj_cache ); ?></option>
							<?php endforeach;?>
						</select>
						<?php echo __( 'Speed up dynamic pageviews', 'powered-cache' ); ?>
						(<a target="_blank" href="http://docs.poweredcache.com/article/6-object-cache">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_mobile_cache"><?php _e( 'Mobile Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="pc_mobile_cache"  name="pc_settings[cache_mobile]" <?php checked( pc_get_option( 'cache_mobile' ), 1 ); ?> value="1"><?php _e('Enable caching for mobile devices.','powered-cache'); ?></label>

						<div id="separate-mobile-cache" style="display:<?php echo( '1' == pc_get_option( 'cache_mobile' ) ? '' : 'none;' ) ?>">
							<label><input type="checkbox" id="cache_mobile_separate_file"  name="pc_settings[cache_mobile_separate_file]" <?php checked( pc_get_option( 'cache_mobile_separate_file' ), 1 ); ?> value="1"><?php _e('Use separate cache file for mobile.','powered-cache'); ?></label>
						</div>
						(<a target="_blank" href="http://docs.poweredcache.com/article/7-mobile-cache">?</a>)

					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_loggedin_user_cache"><?php _e( 'Logged in user cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="pc_loggedin_user_cache"  name="pc_settings[loggedin_user_cache]" <?php checked( pc_get_option( 'loggedin_user_cache' ), 1 ); ?> value="1"><?php _e('Show cached page for logged in users','powered-cache'); ?></label>
						(<a target="_blank" href="http://docs.poweredcache.com/article/8-logged-in-user-cache">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_ssl_cache"><?php _e( 'SSL Cache', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="pc_ssl_cache"  name="pc_settings[ssl_cache]"  <?php checked( pc_get_option( 'ssl_cache' ), 1 ); ?> value="1"><?php _e('Enable caching for SSL','powered-cache'); ?></label>
						(<a target="_blank" href="http://docs.poweredcache.com/article/9-caching-on-https">?</a>)
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_gzip_status"><?php _e( 'Gzip', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="pc_gzip_status"  name="pc_settings[gzip_compression]"  <?php checked( pc_get_option( 'gzip_compression' ), 1 ); ?> value="1"><?php _e('Enable gzip compression','powered-cache'); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="pc_cache_timeout"><?php _e( 'Cache Timeout', 'powered-cache' ); ?></label></th>
					<td>
						<label><input size="5" id="pc_cache_timeout" type="text" value="<?php echo pc_get_option( 'cache_timeout' ); ?>" name="pc_settings[cache_timeout]"> <?php _e( 'minutes', 'powered-cache' ); ?></label>
						(<a target="_blank" href="http://docs.poweredcache.com/article/10-cache-timeout">?</a>)
					</td>
				</tr>



				</tbody>
			</table>

		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
