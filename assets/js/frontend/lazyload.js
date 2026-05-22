/* eslint-disable no-unused-vars, radix */
// eslint-disable-next-line no-use-before-define
// eslint-disable-next-line camelcase

window.PCLL_options = window.PCLL_options || {};

const PCLL = (function () {
	const PCLL = {
		_lastCheckTs: 0,
		_checkDebounceTimeoutRunning: false,
		_earlyLoadedCount: 0,

		init() {
			PCLL.threshold = PCLL.getOptionIntValue('threshold', 200);
			PCLL.recheckDelay = PCLL.getOptionIntValue('recheck_delay', 250);
			PCLL.debounce = PCLL.getOptionIntValue('debounce', 50);
			PCLL.immediateLoadCount = PCLL.getOptionIntValue('immediate_load_count', 3);
			PCLL.checkRecurring();
			PCLL.lazyLoadYouTube();
			return PCLL;
		},

		check(fromDebounceTimeout) {
			const tstamp = performance.now();
			let updated;
			if (fromDebounceTimeout === true) {
				PCLL._checkDebounceTimeoutRunning = false;
			}
			if (tstamp < PCLL._lastCheckTs + PCLL.debounce) {
				if (!PCLL._checkDebounceTimeoutRunning) {
					PCLL._checkDebounceTimeoutRunning = true;
					setTimeout(function () {
						PCLL.check(true);
					}, PCLL.debounce);
				}
				return;
			}
			PCLL._lastCheckTs = tstamp;

			const winH = document.documentElement.clientHeight || document.body.clientHeight;
			const els = document.getElementsByClassName('lazy-hidden');
			updated = false;

			[].forEach.call(els, function (el, index, array) {
				const elemRect = el.getBoundingClientRect();

				// do not lazy-load images that are hidden with display:none or have a width/height of 0
				if (!elemRect.width || !elemRect.height) {
					return;
				}

				// directly load the first nth images
				if (PCLL._earlyLoadedCount <= PCLL.immediateLoadCount) {
					PCLL._earlyLoadedCount++;
					PCLL.showImmediately(el);
				}

				if (winH - elemRect.top + PCLL.threshold > 0) {
					PCLL.show(el);
					updated = true;
				}
			});

			if (updated) {
				PCLL.check();
			}
		},

		checkRecurring() {
			PCLL.check();
			setTimeout(PCLL.checkRecurring, PCLL.recheckDelay);
		},

		show(el) {
			const type = el.getAttribute('data-lazy-type');
			let s;
			let div;
			let iframe;
			el.className = el.className.replace(/(?:^|\s)lazy-hidden(?!\S)/g, '');
			el.addEventListener(
				'load',
				function () {
					el.className += ' lazy-loaded';
					PCLL.customEvent(el, 'lazyloaded');
				},
				false,
			);

			if (type === 'image') {
				if (el.getAttribute('data-lazy-srcset') != null) {
					el.setAttribute('srcset', el.getAttribute('data-lazy-srcset'));
				}
				if (el.getAttribute('data-lazy-sizes') != null) {
					el.setAttribute('sizes', el.getAttribute('data-lazy-sizes'));
				}
				el.setAttribute('src', el.getAttribute('data-lazy-src'));
			} else if (type === 'iframe') {
				s = el.getAttribute('data-lazy-src');
				div = document.createElement('div');

				div.innerHTML = s;
				iframe = div.firstChild;
				el.parentNode.replaceChild(iframe, el);
			}
		},

		showImmediately(el) {
			// This function is similar to the "show" function but without lazy loading
			const type = el.getAttribute('data-lazy-type');
			let s;
			let div;
			let iframe;
			el.className = el.className.replace(/(?:^|\s)lazy-hidden(?!\S)/g, '');
			el.addEventListener(
				'load',
				function () {
					el.className += ' lazy-load-direct';
					PCLL.customEvent(el, 'lazyloaded');
				},
				false,
			);

			// Remove native lazyload if present
			if (el.hasAttribute('loading')) {
				el.removeAttribute('loading');
			}

			if (type === 'image') {
				if (el.getAttribute('data-lazy-srcset') != null) {
					el.setAttribute('srcset', el.getAttribute('data-lazy-srcset'));
				}
				if (el.getAttribute('data-lazy-sizes') != null) {
					el.setAttribute('sizes', el.getAttribute('data-lazy-sizes'));
				}
				el.setAttribute('src', el.getAttribute('data-lazy-src'));
			} else if (type === 'iframe') {
				s = el.getAttribute('data-lazy-src');
				div = document.createElement('div');

				div.innerHTML = s;
				iframe = div.firstChild;
				el.parentNode.replaceChild(iframe, el);
			}
		},

		customEvent(el, eventName) {
			let event;

			if (document.createEvent) {
				event = document.createEvent('HTMLEvents');
				event.initEvent(eventName, true, true);
			} else {
				event = document.createEventObject();
				event.eventType = eventName;
			}

			event.eventName = eventName;

			if (document.createEvent) {
				el.dispatchEvent(event);
			} else {
				el.fireEvent(`on${event.eventType}`, event);
			}
		},

		lazyLoadYouTube(el) {
			const lazyloadYoutube = document.querySelectorAll('.pcll-youtube-player');

			lazyloadYoutube.forEach(function (div) {
				div.addEventListener('click', function () {
					const iframe = document.createElement('iframe');
					iframe.setAttribute('frameborder', '0');
					iframe.setAttribute('allowfullscreen', '');
					iframe.setAttribute('allow', 'autoplay'); // Explicitly allow autoplay

					// Construct the iframe src with autoplay and feature parameters
					const baseSrc = this.getAttribute('data-src');
					const separator = baseSrc.includes('?') ? '&' : '?'; // Determine the correct separator
					const videoSrc = `${baseSrc}${separator}autoplay=1&feature=oembed`; // Append autoplay=1 and feature=oembed

					iframe.setAttribute('src', videoSrc);
					iframe.style.width = '100%';
					iframe.style.height = `${this.offsetHeight}px`;
					this.innerHTML = '';
					this.appendChild(iframe);
				});
			});
		},

		getOptionIntValue(name, defaultValue) {
			// eslint-disable-next-line camelcase
			if (typeof window.PCLL_options[name] !== 'undefined') {
				// eslint-disable-next-line camelcase
				return parseInt(window.PCLL_options[name]);
			}
			return defaultValue;
		},
	};
	return PCLL.init();
})();

window.addEventListener('load', PCLL.check, false);
window.addEventListener('scroll', PCLL.check, false);
window.addEventListener('resize', PCLL.check, false);
document.getElementsByTagName('body').item(0).addEventListener('post-load', PCLL.check, false);
