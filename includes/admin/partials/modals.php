<?php
/**
 * Settings modal(s)
 *
 * @package PoweredCache\Admin
 */

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found

?>
<div class="sui-modal sui-modal-lg">
	<?php wp_nonce_field( 'powered_cache_run_diagnostic', 'powered_cache_run_diagnostic' ); ?>

	<div role="dialog"
		 id="pcmodal--powered-cache-diagnostic"
		 class="sui-modal-content"
		 aria-live="polite"
		 aria-modal="true"
		 aria-labelledby="pcmodal--powered-cache-diagnostic-title"
		 aria-describedby="pcmodal--powered-cache-diagnostic-desc"
	>

		<div class="sui-box">

			<button class="sui-screen-reader-text" data-modal-close=""><?php esc_html_e( 'Close', 'powered-cache' ); ?></button>

			<div class="sui-box-header">

				<h3 id="pcmodal--powered-cache-diagnostic-title" class="sui-box-title"><?php esc_html_e( 'Diagnostic', 'powered-cache' ); ?></h3>

				<button class="sui-button-icon sui-button-float--right" data-modal-close="">
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_html_e( 'Close this modal', 'powered-cache' ); ?></span>
				</button>

			</div>

			<div id="pcmodal--powered-cache-diagnostic-message" class="sui-box-body">

				<p id="pcmodal--powered-cache-diagnostic-desc">
					<?php esc_html_e( 'Welcome to Powered Cache Diagnostic. Running the diagnostic test helps to identify potential issues with the caching. This is only a test. It might take additinal steps to fixing the issues.', 'powered-cache' ); ?>
				</p>

				<div class="sui-border-frame diagnostic-modal-performance">
					<ul id="powered-cache-diagnostic-items">
					</ul>

					<button id="pcmodal--powered-cache-diagnostic-test" class="sui-button sui-button-blue" aria-controls="pcmodal--powered-cache-diagnostic-progress">
						<?php esc_html_e( 'Run Diagnostic Test', 'powered-cache' ); ?>
					</button>
				</div>

			</div>
		</div>

	</div>

</div>
