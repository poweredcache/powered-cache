<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>
		<?php printf( '%s %s', $this->extension_name, __( 'Settings', 'powered-cache' ) ); ?>
	</h1>


	<div id="powered-cache-admin-page-wrapper" class="powered-cache-settings-page">

		<div id="post-body">
			<!-- main content -->
			<div id="post-body-content">
				<form method="post" action="" enctype="multipart/form-data" class="powered-cache-form">
					<div class="meta-box-sortables">
						<div class="postbox">
							<div class="inside">

								<?php wp_nonce_field( 'powered_cache_update_settings', 'powered_cache_settings_nonce' ); ?>
								<input type="hidden" name="action" value="powered_cache_update_extension_settings" />
								<input type="hidden" name="extension" value="<?php echo esc_attr( $this->extension_id ); ?>" />
								<input type="hidden" name="wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ); ?>'" />

								<?php
								if ( isset( $this->settings_files ) && is_array( $this->settings_files ) ) {
									foreach ( $this->settings_files as $file ) {
										if ( file_exists( $file ) ) {
											include $file;
										}
									}
								}
								?>


							</div>
						</div>
					</div>
					<div class="save">
						<?php submit_button( __( 'Save Changes', 'powered-cache' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
			<!-- post-body-content -->

		</div>

		<br class="clear">
	</div>
	<!-- #poststuff -->

</div> <!-- .wrap -->