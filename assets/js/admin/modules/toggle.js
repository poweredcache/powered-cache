/* global jQuery, PoweredCache  */
/* eslint-disable */

(function ($) {
	// Use strict mode

	// Define global PoweredCache object if it does not exist
	if (typeof window.PoweredCache !== 'object') {
		window.PoweredCache = {};
	}

	PoweredCache.pageToggles = function (page) {
		const body = $('body');
		if (!body.hasClass('toplevel_page_powered-cache')) {
			return;
		}

		function showSettings(element) {
			const settings = $(`#${element.attr('aria-controls')}`);

			element.on('change', function () {
				if (element.is(':checked')) {
					settings.show();
				} else {
					settings.hide();
				}
			});
		}

		function init() {
			const toggles = $('.sui-toggle input[type="checkbox"]');

			toggles.each(function () {
				const toggle = $(this);

				if (undefined !== toggle.attr('aria-controls')) {
					showSettings(toggle);
				}
			});
		}

		init();

		return this;
	};

	$('body').ready(function () {
		PoweredCache.pageToggles('toggles');
	});
})(jQuery);

/* eslint-enable */
