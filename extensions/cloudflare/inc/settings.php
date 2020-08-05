<?php
/**
 * Cloudflare extension settings template
 *
 * @package PoweredCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<table class="form-table">
	<tbody>

	<tr>
		<th scope="row"><label for="powered_cache_cf_email"><?php esc_html_e( 'Cloudflare Email', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="50" id="powered_cache_cf_email" type="text" value="<?php esc_attr( $this->get_option( 'email' ) ); ?>" name="cloudflare[email]"></label>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="powered_cache_cf_api_key"><?php esc_html_e( 'API Key', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="50" id="powered_cache_cf_api_key" type="text" value="<?php esc_attr( $this->get_option( 'api_key' ) ); ?>" name="cloudflare[api_key]"></label>
		</td>
	</tr>

	<?php if ( $this->get_zones() ) : ?>
		<tr>
			<th scope="row"><label for="powered_cache_cloudflare_domain"><?php esc_html_e( 'Domain', 'powered-cache' ); ?></label></th>
			<td>
				<select name="cloudflare[zone]" id="powered_cache_cloudflare_domain">
					<option value=""><?php echo esc_html__( 'Select domain', 'powered-cache' ); ?></option>
					<?php foreach ( $this->get_option( 'zone_list' ) as $zone ) : ?>
						<option <?php selected( $this->get_option( 'zone' ), $zone->id ); ?> value="<?php echo esc_attr( $zone->id ); ?>"><?php echo esc_attr( $zone->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>

		<?php if ( $this->get_option( 'zone' ) ) : ?>
			<tr>
				<th scope="row"><label for="powered_cache_clear_cache"><?php esc_html_e( 'Clear Cache', 'powered-cache' ); ?></label></th>
				<td>
					<?php echo $this->flush_cache_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<br>
					<span class="description"><?php esc_html_e( 'Deletes all Cloudflare cache', 'powered-cache' ); ?></span>
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>


	</tbody>
</table>

