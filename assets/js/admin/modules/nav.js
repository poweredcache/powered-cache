/* global jQuery, PoweredCache  */
(function ($) {
	// Use strict mode

	// Define global PoweredCache object if it does not exist
	if (typeof window.PoweredCache !== 'object') {
		window.PoweredCache = {};
	}

	PoweredCache.sideNavigation = function (element) {
		const button = $(element);

		function current(el) {
			const button = $(el); // eslint-disable-line no-shadow
			const parent = button.closest('.sui-vertical-tabs');
			const wrapper = button.closest('.sui-row-with-sidenav');
			const content = wrapper.find('> div[data-tab]');
			const dataNav = button.data('tab');
			const dataBox = wrapper.find(`div[data-tab="${dataNav}"]`);
			parent.find('li').removeClass('current');
			button.parent().addClass('current');
			// window.location.hash = el.hash;
			localStorage.setItem('poweredcache_current_nav', el.hash);

			content.hide();
			dataBox.show();
		}

		function init() {
			button.on('click', function (e) {
				current(e.target);
				e.preventDefault();
				e.stopPropagation();
			});

			// auto-focus current section
			if (window.location.hash && $(`a[href="${window.location.hash}"]`).length) {
				$(`a[href^="${window.location.hash}"]`).trigger('click');
			}

			// auto-focus last selection
			const lastnav = localStorage.getItem('poweredcache_current_nav');
			if (lastnav && $(`a[href="${lastnav}"]`).length) {
				$(`a[href^="${lastnav}"]`).trigger('click');
			}
		}

		init();

		return this;
	};

	$('body').ready(function () {
		const button = $('.sui-vertical-tab a');

		PoweredCache.sideNavigation(button);
	});
})(jQuery);
