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
<?php $extentions = Powered_Cache_Extensions::factory()->get_extentions(); ?>

<div id="extensions" class="powered-cache-extension-page powered-cache-settings-tab">
	<?php foreach ( $extentions as $extension_id => $extention ) : ?>
		<div class="powered-cache-extension"><h3 class="powered-cache-extension-title"><?php echo esc_attr( $extention['Name'] ); ?></h3>
			<a href="<?php echo esc_url( $extention['ExtensionURI'] ); ?>" title="<?php echo esc_attr( $extention['Name'] ); ?>">
				<img width="320" height="160" src="<?php echo esc_url( $extention['ExtensionImage'] ); ?>"></a>

			<p><?php echo esc_html__( $extention['Description'], 'powered-cache' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></p>

			<p></p>
			<?php if ( 'true' === $extention['Premium'] && ! powered_cache_is_premium() ) : ?>
				<span class="extension-upgrade-msg"><?php echo esc_html__( 'Premium only', 'powered-cache' ); ?></span>
				<span class="upgrade-extension" style="float:right;"> <?php echo Powered_Cache_Admin_Helper::upgrade_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php else : ?>
				<?php echo Powered_Cache_Admin_Helper::plugin_button( $extension_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>

	<?php endforeach; ?>

</div>
<!-- .postbox -->
