/* global jQuery, PoweredCache, ajaxurl  */
const { __ } = wp.i18n;

(function ($) {
	// Use strict mode

	// Define global PoweredCache object if it does not exist
	if (typeof window.PoweredCache !== 'object') {
		window.PoweredCache = {};
	}

	PoweredCache.pageModals = function () {
		const body = $('body');
		if (!body.hasClass('toplevel_page_powered-cache')) {
			return;
		}

		/**
		 * Modal: diagnosticModal Modal
		 *
		 * Run demo for performance test and close modal at the end.
		 */
		function diagnosticModal() {
			const activate = $('#pcmodal--powered-cache-diagnostic-test');

			activate.on('click', function () {
				const button = $(this);
				const suiBox = button.closest('.sui-box');
				const boxTitle = suiBox.find('.sui-box-title');
				const boxSkip = suiBox.find('.sui-box-header button');
				const diagnosticItems = suiBox.find('#powered-cache-diagnostic-items');
				const nonce = $('#powered_cache_run_diagnostic').val();
				const oldTitle = __('Diagnostic', 'powered-cache');

				jQuery
					.ajax({
						url: ajaxurl,
						method: 'post',
						beforeSend() {
							// Assign new title.
							boxTitle.text(__('Running Diagnostic Tests…', 'powered-cache'));
							// Disable "skip" button.
							boxSkip.attr('disabled', true);
							// clear previous message exists
							diagnosticItems.empty();
						},
						data: {
							nonce,
							action: 'powered_cache_run_diagnostic',
						},
						success(response) {
							if (response.success && response.data.length) {
								response.data.forEach((item) => {
									let icon = null;
									if (item.status) {
										icon =
											'<span class="sui-icon-check" aria-hidden="true"></span>';
									} else {
										icon =
											'<span class="sui-icon-close" aria-hidden="true"></span>';
									}

									diagnosticItems.append(`<li>${icon} ${item.description}</li>`);
								});
							}
						},
					})
					.done(function () {
						// Assign old title.
						boxTitle.text(oldTitle);
						// Enable "skip" button.
						boxSkip.prop("disabled", false);
					});
			});
		}

		function init() {
			diagnosticModal();
		}

		init();
	};

	$('body').ready(function () {
		PoweredCache.pageModals('modals');
	});

	$(document).on('click', '.sui-modal-overlay', function () {
		$('.sui-has-modal').removeClass('sui-has-modal');
	});

})(jQuery);
