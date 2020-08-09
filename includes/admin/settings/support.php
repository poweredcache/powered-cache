<?php
/**
 * Settings page template
 *
 * @package    PoweredCache
 * @subpackage PoweredCache/Settings
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div id="support" class="powered-cache-settings-tab meta-box-sortables ui-sortable">

	<div class="postbox">

		<div class="inside">
			<h1><?php esc_html_e( 'PoweredCache Support', 'powered-cache' ); ?></h1>
			<hr>

			<div id="powered-cache-premium-support">

				<div id="powered-cache-support-general-info">
					<?php
					echo wp_kses(
						sprintf(
							__(
								'Welcome to Powered Cache support line. Direct support exclusive to paid users. If you are free user, please use community support on <a target="_blank" href="%s" rel="noopener">WordPress.org</a> forums.',
								'powered-cache'
							),
							'https://wordpress.org/support/plugin/powered-cache'
						),
						array(
							'a' => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
							),
						)
					);
					?>
				</div>
				<div id="form-message-placeholder"></div>

				<table class="form-table" id="support-form-table">
					<?php powered_cache_maybe_require_premium_html(); ?>

					<tbody>
					<tr>
						<th scope="row" style="vertical-align: middle"><label for="support_subject"><?php esc_html_e( 'Subject', 'powered-cache' ); ?></label></th>
						<td><label><input required="required" type="text" placeholder="<?php esc_html_e( 'What is the problem with?', 'powered-cache' ); ?>" name="support_subject" id="support_subject" size="71" /></label></td>
					</tr>

					<tr>
						<th scope="row" style="vertical-align: middle"><label for="support_email"><?php esc_html_e( 'E-mail', 'powered-cache' ); ?></label></th>
						<td><label><input required="required" type="text" value="<?php esc_attr( get_bloginfo( 'admin_email' ) ); ?>" name="support_email" id="support_email" size="71"/></label></td>
					</tr>

					<tr>
						<th scope="row" style="vertical-align: middle"><label for="powered_cache_caching_status"><?php esc_html_e( 'Description', 'powered-cache' ); ?></label></th>
						<td>
							<label>
								<textarea id="support_description"  name="support_description" cols="70" rows="10">
<?php esc_html_e( 'What did you do?', 'powered-cache' ); ?>

<?php esc_html_e( '(If possible, provide a recipe for reproducing the error.)', 'powered-cache' ); ?>



<?php esc_html_e( 'What did you expect to see?', 'powered-cache' ); ?>


<?php esc_html_e( 'What did you see instead?', 'powered-cache' ); ?>

								</textarea>
							</label>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="checkbox" value="1" name="support-terms" id="support-terms" />
							<label for="support-terms">
								<small>
									<?php
									echo wp_kses(
										sprintf( __( 'I accept the <a href="%s" target="_blank" rel="noopener">Privacy Policy </a> by submitting this form.', 'powered-cache' ), 'https://poweredcache.com/privacy/' ),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
												'rel'    => array(),
											),
										)
									);
									?>
									<br>
									<?php esc_html_e( 'This form will be sent site related information. (WordPress version, PHP version, active plugin etc...)', 'powered-cache' ); ?>
								</small>
							</label>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<button id="powered-cache-support-btn" class="button button-secondary" type="button" ><?php esc_html_e( 'Open new support ticket', 'powered-cache' ); ?></button>
						</td>
					</tr>
					</tbody>
				</table>

			</div>
		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
