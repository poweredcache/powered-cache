/**
 * Delay JS Script Loader
 */

class PCScriptLoader {
	constructor(delay) {
		this.loadDelay = delay;
		this.loadTimer = null;
		this.scriptsLoaded = false;
		this.triggerEvents = [
			'mouseover',
			'click',
			'keydown',
			'wheel',
			'touchmove',
			'touchstart',
			'touchend',
		];
		this.userEventHandler = this.triggerLoader.bind(this);
		this.init();
	}

	init() {
		const self = this;
		for (const event of this.triggerEvents) {
			window.addEventListener(event, this.userEventHandler, { passive: true });
		}
		if (this.loadDelay > 0) {
			this.loadTimer = setTimeout(() => {
				self.loadScripts();
			}, this.loadDelay);
		}
	}

	triggerLoader() {
		if (this.scriptsLoaded) return;
		this.loadScripts();
		clearTimeout(this.loadTimer);
		for (const event of this.triggerEvents) {
			window.removeEventListener(event, this.userEventHandler, { passive: true });
		}
	}

	loadScripts() {
		this.scriptsLoaded = true;
		this.loadScriptsWithType("data-type='lazy'");
		this.loadScriptsWithType('defer', true);
		console.log('Script(s) loaded with delay or interaction');
	}

	loadScriptsWithType(selector, defer = false) {
		const scripts = document.querySelectorAll(`script[${selector}]`);
		const loadScript = (script) => {
			const src = script.getAttribute('data-src');
			if (src === null) {
				return;
			}
			script.setAttribute('src', src);
			script.removeAttribute('data-src');
			script.setAttribute('data-lazy-loaded', 'true');
		};

		if (defer) {
			setTimeout(() => {
				scripts.forEach(loadScript);
			}, 0);
		} else {
			scripts.forEach(loadScript);
		}
	}
}

const timeout = window.PCScriptLoaderTimeout || 0;
window.PCScriptLoader = new PCScriptLoader(timeout);
