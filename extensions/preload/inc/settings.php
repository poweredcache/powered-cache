<?php
/**
 * Settings page template
 *
 * @package PoweredCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<h2><?php esc_attr_e( 'Local Preload', 'powered-cache' ); ?></h2>
<table class="form-table">

	<tbody>
	<tr>
		<th scope="row"><label for="powered_cache_preload_post_count"><?php esc_html_e( 'How many posts to preload', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="10" id="powered_cache_preload_post_count" type="text" value="<?php echo absint( $this->get_option( 'post_count' ) ); ?>" name="preload[post_count]"/></label>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="powered_cache_preload_taxonomies"><?php esc_html_e( 'Category/Archive', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_preload_taxonomies" name="preload[taxonomies]" <?php checked( $this->get_option( 'taxonomies' ), 1 ); ?> value="1"/><?php esc_html_e( 'Preload tags, categories and other taxonomies.', 'powered-cache' ); ?></label>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="powered_cache_preload_interval"><?php esc_html_e( 'Preload interval', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="4" id="powered_cache_preload_interval" type="text" value="<?php esc_attr( $this->get_option( 'interval' ) ); ?>" name="preload[interval]"/> <?php esc_html_e( 'minutes', 'powered-cache' ); ?></label>
			<br>
			<span class="description"><?php echo esc_html__( '0 to disable, recommended at least 30 mins', 'powered-cache' ); ?></span>
		</td>
	</tr>


	<tr>
		<th scope="row"><label for="powered_cache_clear_cache"><?php esc_html_e( 'Preload Now', 'powered-cache' ); ?></label></th>
		<td>
			<?php echo $this->preload_cache_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<br>
			<span class="description"><?php esc_html_e( 'Manually start preload process', 'powered-cache' ); ?></span>
		</td>
	</tr>


	</tbody>
</table>
