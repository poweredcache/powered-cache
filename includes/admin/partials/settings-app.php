<?php
/**
 * Settings App Template
 *
 * @package PoweredCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<main class="wrap powered-cache-settings-page">
	<div id="powered-cache-settings-app" class="powered-cache-settings-app">
		<div class="powered-cache-settings-loading" role="status" aria-live="polite">
			<?php esc_html_e( 'Loading Powered Cache settings...', 'powered-cache' ); ?>
		</div>
	</div>
</main>
