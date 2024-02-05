<?php
/**
 * Settings form action buttons
 *
 * @package PoweredCache\Admin
 */

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="sui-actions-left">
	<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
		<i class="sui-icon-save" aria-hidden="true"></i>
		<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
	</button>

	<button type="submit" name="powered_cache_form_action" value="save_settings_and_clear_cache" class="sui-button sui-button-green sui-button-ghost">
		<i class="sui-icon-save" aria-hidden="true"></i>
		<?php esc_html_e( 'Update settings and clear all cache', 'powered-cache' ); ?>
	</button>
</div>
