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
					<th scope="row"><label for="powered_cache_cache_message"><?php _e( 'Cache status message', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="powered_cache_cache_message"  name="powered_cache_settings[show_cache_message]" <?php checked( powered_cache_get_option( 'show_cache_message' ), 1 ); ?> value="1"><?php _e('Show cache status message in footer','powered-cache'); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_clear_cache"><?php _e( 'Clear Cache', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::flush_cache_button(); ?>
						<br>
						<span class="description"><?php _e( 'Clear all cache, including object cache', 'powered-cache' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_clear_cache"><?php _e( 'Reset All Settings', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::reset_settings_button(); ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_export_settings"><?php _e( 'Export', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::export_settings_button(); ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_import_settings"><?php _e( 'Import', 'powered-cache' ); ?></label></th>
					<td>
						<input type="file" id="powered_cache_import_settings" name="powered_cache_import">
						<br>
						<input type="submit" name="do-import" id="do-import" class="button" value="<?php _e('Upload and import','powered-cache');?>">
					</td>
				</tr>


				</tbody>
			</table>

		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
