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
		scripts.forEach(script => {
			// Extend the check to include '-extra' as a type of 'before' script
			const isAfter = script.id.endsWith('-after');
			const isBeforeOrExtra = script.id.endsWith('-before') || script.id.endsWith('-extra');

			if (isAfter || isBeforeOrExtra) {
				// Determine the base ID by removing '-after', '-before', or '-extra'
				const baseId = script.id.replace(/-(after|before|extra)$/, '');
				const baseScript = document.getElementById(baseId);

				if (baseScript) {
					if (isBeforeOrExtra) {
						// Execute the inline '-before' or '-extra' script immediately
						this.executeInlineScript(script).then(() => {
							// Then load the associated external script
							this.loadScript(baseScript, defer);
						});
					} else if (isAfter) {
						// Load the base script first
						this.loadScript(baseScript, defer).then(() => {
							// Then execute the inline '-after' script
							this.executeInlineScript(script);
						});
					}
				}
			} else {
				// If there's no dependency, load the script normally
				this.loadScript(script, defer);
			}
		});
	}

	executeInlineScript(script) {
		// Check if the script has already been executed to avoid re-execution
		if (script.getAttribute('data-executed') === 'true') return Promise.resolve();

		return new Promise((resolve, reject) => {
			try {
				eval(script.textContent);
				script.setAttribute('data-executed', 'true');
				resolve();
			} catch (error) {
				console.error('Error executing inline script:', script.id, error);
				reject(error);
			}
		});
	}

	loadScript(script, defer = false) {
		// Check if the script is inline or has already been loaded
		if (!script.src || script.getAttribute('data-lazy-loaded') === 'true') {
			return this.executeInlineScript(script);
		}

		return new Promise((resolve, reject) => {
			const src = script.getAttribute('data-src') || script.getAttribute('src');
			const newScript = document.createElement('script');

			newScript.onload = () => {
				script.setAttribute('data-lazy-loaded', 'true');
				resolve();
			};
			newScript.onerror = () => {
				console.error('Error loading script:', src);
				reject(new Error(`Failed to load script: ${src}`));
			};

			// Copy all attributes from the original script to the new one
			Array.from(script.attributes).forEach(attr => {
				if (attr.name !== 'data-src') { // Skip 'data-src' attribute
					newScript.setAttribute(attr.name, attr.value);
				}
			});

			newScript.setAttribute('src', src);
			document.head.appendChild(newScript);

			// Remove the original script element if it's external
			if (script.parentNode) {
				script.parentNode.removeChild(script);
			}
		});
	}

}

const timeout = window.PCScriptLoaderTimeout || 0;
window.PCScriptLoader = new PCScriptLoader(timeout);
