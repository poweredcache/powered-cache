/* eslint-disable no-unused-vars, radix */
// eslint-disable-next-line no-use-before-define
const PCLL_options = PCLL_options || {};

const PCLL = (function () {
	const PCLL = {
		_lastCheckTs: 0,
		_checkDebounceTimeoutRunning: false,

		init() {
			PCLL.threshold = PCLL.getOptionIntValue('threshold', 200);
			PCLL.recheckDelay = PCLL.getOptionIntValue('recheck_delay', 250);
			PCLL.debounce = PCLL.getOptionIntValue('debounce', 50);
			PCLL.checkRecurring();
			return PCLL;
		},

		check(fromDebounceTimeout) {
			let updated;
			if (fromDebounceTimeout === true) {
				PCLL._checkDebounceTimeoutRunning = false;
			}
			const tstamp = performance.now();
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
			updated = false;
			const els = document.getElementsByClassName('lazy-hidden');

			[].forEach.call(els, function (el, index, array) {
				const elemRect = el.getBoundingClientRect();

				// do not lazy-load images that are hidden with display:none or have a width/height of 0
				if (!elemRect.width || !elemRect.height) {
					return;
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

			const type = el.getAttribute('data-lazy-type');

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

		getOptionIntValue(name, defaultValue) {
			// eslint-disable-next-line camelcase
			if (typeof PCLL_options[name] !== 'undefined') {
				// eslint-disable-next-line camelcase
				return parseInt(PCLL_options[name], 0);
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
