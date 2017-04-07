<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<div id="preload-bot-settings" style="position: relative">

	<?php powered_cache_maybe_require_premium_html(); ?>


	<h2><?php esc_attr_e( 'Preload Bot', 'powered-cache' ); ?></h2>

	<table class="form-table">
		<tbody>

		<tr>
			<th scope="row"><label for="powered_cache_preload_bot"><?php _e( 'Preload Bot', 'powered-cache' ); ?></label></th>
			<td>
				<label><input type="checkbox" <?php echo( ! powered_cache_is_premium() ? 'disabled="disabled"' : '' ); ?> id="powered_cache_preload_bot" name="preload[bot]" <?php checked( $this->get_option( 'bot' ), 1 ); ?> value="1" /><?php _e( 'Enable preload bot', 'powered-cache' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="powered_cache_preload_bot_interval"><?php _e( 'Bot Interval', 'powered-cache' ); ?></label></th>
			<td>
				<label>
					<select id="powered_cache_preload_bot_interval" name="preload[bot_interval]"  <?php echo( ! powered_cache_is_premium() ? 'disabled="disabled"' : '' ); ?> >
					<?php foreach ( $this->interval_options as $micro_seconds => $val ): ?>
						<option <?php selected( $this->get_option( 'bot_interval' ), $micro_seconds ); ?> value="<?php echo intval( $micro_seconds ); ?>"><?php echo esc_attr( $val ); ?></option>
					<?php endforeach; ?>
					</select>
					<?php _e( 'Interval between requests. If your hosting is not strong enough, you should consider to select higher option.', 'powered-cache' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="powered_cache_preload_bot_automatic_crawl"><?php _e( 'Automatic Crawl', 'powered-cache' ); ?></label></th>
			<td>
				<label><input type="checkbox" <?php echo( ! powered_cache_is_premium() ? 'disabled="disabled"' : '' ); ?> id="powered_cache_preload_bot_automatic_crawl" name="preload[bot_automatic_crawl]" <?php checked( $this->get_option( 'bot_automatic_crawl' ), 1 ); ?> value="1" /><?php _e( 'Automatic ping when new post published.', 'powered-cache' ); ?></label>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="powered_cache_preload_bot_automatic_crawl"><?php _e( 'Sitemap Integration', 'powered-cache' ); ?></label></th>
			<td>
				<label><input type="checkbox" <?php echo( ! powered_cache_is_premium() ? 'disabled="disabled"' : '' ); ?> id="powered_cache_preload_bot_automatic_crawl" name="preload[sitemap_integration]" <?php checked( $this->get_option( 'sitemap_integration' ), 1 ); ?> value="1" /><?php _e( 'Enable sitemaps integration', 'powered-cache' ); ?></label>
			</td>
		</tr>


		<tr>
			<th scope="row"><label for="powered_cache_preload_sitemaps"><?php _e( 'Sitemaps', 'powered-cache' ); ?></label></th>
			<td>
				<textarea id="powered_cache_preload_sitemaps" <?php echo( ! powered_cache_is_premium() ? 'disabled="disabled"' : '' ); ?> name="preload[sitemaps]" cols="70" rows="5"><?php echo $this->get_option( 'sitemaps' ); ?></textarea><br>
				<span class="description"><?php _e( 'Enter XML sitemaps urls', 'powered-cache' ); ?></span>
			</td>
		</tr>


		</tbody>
	</table>

</div>
