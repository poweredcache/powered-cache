<?php
/**
 * Misc Settings page template
 *
 * @package PoweredCache
 * @subpackage PoweredCache/Settings
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div id="misc" class="powered-cache-settings-tab meta-box-sortables ui-sortable">

	<div class="postbox">
		<div class="inside">
			<?php if ( isset( $_GET['action'] ) && 'run-diagnostic' === $_GET['action'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php
				$checks = Powered_Cache_Admin_Helper::diagnostic_info();

				foreach ( $checks as $check_key => $result ) {
					if ( $result['status'] ) {
						echo '<span class="dashicons dashicons-yes"></span>';
					} else {
						echo '<span class="dashicons dashicons-no"></span>';
					}
					echo esc_attr( $result['description'] );
					echo '<br>';
				}
				?>
			<?php else : ?>
			<table class="form-table">
				<tbody>
				<?php if ( powered_cache_is_premium() ) : ?>
					<tr>
						<th scope="row"><label for="powered_cache_cache_message"><?php esc_html_e( 'Cache footprint', 'powered-cache' ); ?></label></th>
						<td>
							<label><input type="checkbox" id="powered_cache_cache_message" name="powered_cache_settings[show_cache_message]" <?php checked( powered_cache_get_option( 'show_cache_message' ), 1 ); ?> value="1"><?php esc_html_e( 'Show caching footprints in the HTML output.', 'powered-cache' ); ?></label>
						</td>
					</tr>
				<?php endif; ?>

				<?php
				// additional control needed for cache flushing
				if ( ( is_multisite() && current_user_can( 'manage_network' ) ) || ( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ) :
					?>
					<tr>
						<th scope="row"><label for="powered_cache_clear_cache"><?php esc_html_e( 'Clear Cache', 'powered-cache' ); ?></label></th>
						<td>
							<?php echo Powered_Cache_Admin_Helper::flush_cache_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<br>
							<span class="description"><?php esc_html_e( 'Clear all cache, including object cache', 'powered-cache' ); ?></span>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><label for="powered_cache_rewrite"><?php esc_html_e( 'Download Configuration', 'powered-cache' ); ?></label></th>
					<td>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_download_rewrite_settings&server=apache' ), 'powered_cache_download_rewrite' ) ); ?>" class="button-secondary">
						<?php esc_html_e( '.htaccess configuration', 'powered-cache' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_download_rewrite_settings&server=nginx' ), 'powered_cache_download_rewrite' ) ); ?>" class="button-secondary">
						<?php esc_html_e( 'nginx configuration', 'powered-cache' ); ?>
						</a>
						<br>
						<span class="description"><?php esc_html_e( 'Download configuration file for your server', 'powered-cache' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_clear_cache"><?php esc_html_e( 'Reset All Settings', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::reset_settings_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_clear_cache"><?php esc_html_e( 'Diagnostic', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::diagnostic_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
						<span class="description"><?php esc_html_e( 'If you get trouble, perform the diagnostic checks', 'powered-cache' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_export_settings"><?php esc_html_e( 'Export', 'powered-cache' ); ?></label></th>
					<td>
						<?php echo Powered_Cache_Admin_Helper::export_settings_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="powered_cache_import_settings"><?php esc_html_e( 'Import', 'powered-cache' ); ?></label></th>
					<td>
						<input type="file" id="powered_cache_import_settings" name="powered_cache_import">
						<br>
						<input type="submit" name="do-import" id="do-import" class="button" value="<?php esc_html_e( 'Upload and import', 'powered-cache' ); ?>">
					</td>
				</tr>


				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
