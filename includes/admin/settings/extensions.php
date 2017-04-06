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
<?php $extentions = PC_Extensions::factory()->get_extentions(); ?>

<div class="pc-extension-page">
	<?php foreach ( $extentions as $extension_id => $extention ): ?>
		<div class="pc-extension"><h3 class="pc-extension-title"><?php echo $extention['Name']; ?></h3>
			<a href="<?php echo $extention['ExtensionURI']; ?>" title="<?php echo $extention['Name']; ?>">
				<img width="320" height="160" src="<?php echo $extention['ExtensionImage']; ?>"></a>

			<p><?php echo __( $extention['Description'], 'powered-cache' ); ?></p>

			<p></p>
			<?php if ( 'true' === $extention['Premium'] && ! is_powered_cache_premium() ): ?>
				<span class="extension-upgrade-msg"><?php echo __( 'Premium only', 'powered-cache' ); ?></span>
				<span class="upgrade-extension" style="float:right;"> <?php echo PC_Admin_Helper::upgrade_button(); ?></span>
			<?php else: ?>
				<?php echo PC_Admin_Helper::plugin_button( $extension_id ); ?>
			<?php endif; ?>
		</div>

	<?php endforeach; ?>

</div>
<!-- .postbox -->
