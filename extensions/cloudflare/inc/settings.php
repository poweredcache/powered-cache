<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<table class="form-table">
	<tbody>

	<tr>
		<th scope="row"><label for="pc_cf_email"><?php _e( 'Cloudflare Email', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="50" id="pc_cf_email" type="text" value="<?php esc_attr_e( $this->get_option( 'email' ), 'powered-cache' ); ?>" name="cloudflare[email]"></label>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="pc_cf_api_key"><?php _e( 'API Key', 'powered-cache' ); ?></label></th>
		<td>
			<label><input size="50" id="pc_cf_api_key" type="text" value="<?php esc_attr_e( $this->get_option( 'api_key' ), 'powered-cache' ); ?>" name="cloudflare[api_key]"></label>
		</td>
	</tr>

	<?php if ( $this->get_zones() ): ?>
		<tr>
			<th scope="row"><label for="pc_cloudflare_domain"><?php _e( 'Domain', 'powered-cache' ); ?></label></th>
			<td>
				<select name="cloudflare[zone]" id="pc_cloudflare_domain">
					<?php foreach ( $this->get_option( 'zone_list' ) as $zone ): ?>
						<option <?php selected( $this->get_option( 'zone' ), $zone->id ); ?> value="<?php echo $zone->id; ?>"><?php echo $zone->name; ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	<?php endif; ?>


	<tr>
		<th scope="row"><label for="pc_clear_cache"><?php _e( 'Clear Cache', 'powered-cache' ); ?></label></th>
		<td>
			<?php echo $this->flush_cache_button();?>
			<br>
			<span class="description"><?php _e( 'Deletes all Cloudflare cache', 'powered-cache' ); ?></span>
		</td>
	</tr>


	</tbody>
</table>

