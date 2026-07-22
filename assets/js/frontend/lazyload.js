/* eslint-disable no-unused-vars, radix */

window.PCLL_options = window.PCLL_options || {};

const PCLL = (function () {
	const PCLL = {
		_lastCheckTs: 0,
		_checkDebounceTimeoutRunning: false,
		_earlyLoadedCount: 0,
		_observer: null,

		init() {
			PCLL.threshold = PCLL.getOptionIntValue('threshold', 200);
			PCLL.recheckDelay = PCLL.getOptionIntValue('recheck_delay', 250);
			PCLL.debounce = PCLL.getOptionIntValue('debounce', 50);
			PCLL.immediateLoadCount = PCLL.getOptionIntValue('immediate_load_count', 3);
			PCLL.lazyLoadYouTube();

			if ('IntersectionObserver' in window) {
				PCLL.initObserver();
			} else {
				PCLL.initFallback();
			}

			return PCLL;
		},

		initObserver() {
			PCLL._observer = new IntersectionObserver(PCLL.onIntersection, {
				rootMargin: `${PCLL.threshold}px 0px`,
				threshold: 0,
			});
			PCLL.observe();

			window.addEventListener('load', PCLL.observe, false);

			const body = document.getElementsByTagName('body').item(0);
			if (body) {
				body.addEventListener('post-load', PCLL.observe, false);
			}
		},

		initFallback() {
			PCLL.checkRecurring();
			window.addEventListener('load', PCLL.check, false);
			window.addEventListener('scroll', PCLL.check, false);
			window.addEventListener('resize', PCLL.check, false);

			const body = document.getElementsByTagName('body').item(0);
			if (body) {
				body.addEventListener('post-load', PCLL.check, false);
			}
		},

		observe() {
			const els = document.getElementsByClassName('lazy-hidden');

			[].forEach.call(els, function (el) {
				if (el.getAttribute('data-lazy-observed') === '1') {
					return;
				}

				if (PCLL.shouldLoadEarly(el)) {
					PCLL.showImmediately(el);
					return;
				}

				el.setAttribute('data-lazy-observed', '1');
				PCLL._observer.observe(el);
			});
		},

		onIntersection(entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting || entry.intersectionRatio > 0) {
					PCLL._observer.unobserve(entry.target);
					PCLL.show(entry.target);
				}
			});
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

			[].forEach.call(els, function (el) {
				const elemRect = el.getBoundingClientRect();

				if (!elemRect.width || !elemRect.height) {
					return;
				}

				if (PCLL.shouldLoadEarly(el)) {
					PCLL.showImmediately(el);
					updated = true;
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

		shouldLoadEarly(el) {
			const elemRect = el.getBoundingClientRect();

			if (!elemRect.width || !elemRect.height) {
				return false;
			}

			if (PCLL._earlyLoadedCount >= PCLL.immediateLoadCount) {
				return false;
			}

			PCLL._earlyLoadedCount++;
			return true;
		},

		show(el) {
			PCLL.reveal(el, 'lazy-loaded');
		},

		showImmediately(el) {
			if (el.hasAttribute('loading')) {
				el.removeAttribute('loading');
			}

			PCLL.reveal(el, 'lazy-load-direct');
		},

		reveal(el, loadedClass) {
			if (el.getAttribute('data-lazy-loaded') === '1') {
				return;
			}

			const type = el.getAttribute('data-lazy-type');
			el.setAttribute('data-lazy-loaded', '1');
			el.className = el.className.replace(/(?:^|\s)lazy-hidden(?!\S)/g, '');

			if (type === 'image') {
				PCLL.revealImage(el, loadedClass);
			} else if (type === 'iframe') {
				PCLL.revealIframe(el);
			} else if (type === 'background') {
				PCLL.revealBackground(el, loadedClass);
			}
		},

		revealImage(el, loadedClass) {
			el.addEventListener(
				'load',
				function () {
					el.className += ` ${loadedClass}`;
					PCLL.customEvent(el, 'lazyloaded');
				},
				false,
			);

			if (el.getAttribute('data-lazy-srcset') != null) {
				el.setAttribute('srcset', el.getAttribute('data-lazy-srcset'));
			}
			if (el.getAttribute('data-lazy-sizes') != null) {
				el.setAttribute('sizes', el.getAttribute('data-lazy-sizes'));
			}
			el.setAttribute('src', el.getAttribute('data-lazy-src'));
		},

		revealIframe(el) {
			const source = el.getAttribute('data-lazy-src');
			const div = document.createElement('div');

			div.innerHTML = source;

			if (div.firstChild) {
				el.parentNode.replaceChild(div.firstChild, el);
			}
		},

		revealBackground(el, loadedClass) {
			const style = el.getAttribute('data-lazy-style');

			if (style) {
				el.setAttribute('style', style);
			}

			el.className += ` ${loadedClass}`;
			PCLL.customEvent(el, 'lazyloaded');
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

		lazyLoadYouTube() {
			const lazyloadYoutube = document.querySelectorAll('.pcll-youtube-player');

			lazyloadYoutube.forEach(function (div) {
				div.addEventListener('click', function () {
					const iframe = document.createElement('iframe');
					iframe.setAttribute('frameborder', '0');
					iframe.setAttribute('allowfullscreen', '');
					iframe.setAttribute('allow', 'autoplay');

					const baseSrc = this.getAttribute('data-src');
					const separator = baseSrc.includes('?') ? '&' : '?';
					const videoSrc = `${baseSrc}${separator}autoplay=1&feature=oembed`;

					iframe.setAttribute('src', videoSrc);
					iframe.style.width = '100%';
					iframe.style.height = `${this.offsetHeight}px`;
					this.innerHTML = '';
					this.appendChild(iframe);
				});
			});
		},

		getOptionIntValue(name, defaultValue) {
			if (typeof window.PCLL_options[name] !== 'undefined') {
				return parseInt(window.PCLL_options[name]);
			}
			return defaultValue;
		},
	};
	return PCLL;
})();

let pcllInitialized = false;

function initializePCLL() {
	if (pcllInitialized) {
		return;
	}

	pcllInitialized = true;
	PCLL.init();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializePCLL, { once: true });
} else {
	initializePCLL();
}
