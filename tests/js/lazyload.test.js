describe('lazy-load runtime initialization', () => {
	let observe;

	const setDocumentReadyState = (readyState) => {
		Object.defineProperty(document, 'readyState', {
			configurable: true,
			value: readyState,
		});
	};

	const loadRuntime = () => {
		jest.isolateModules(() => {
			// Re-run the side-effect-only browser runtime for each ready-state scenario.
			// eslint-disable-next-line global-require
			require('../../assets/js/frontend/lazyload');
		});
	};

	beforeEach(() => {
		jest.resetModules();
		// Swap in a fresh body so MutationObservers from earlier runtime instances stop receiving events.
		document.documentElement.replaceChild(document.createElement('body'), document.body);
		window.PCLL_options = {};
		observe = jest.fn();
		window.IntersectionObserver = jest.fn(() => ({
			observe,
			unobserve: jest.fn(),
		}));
	});

	afterEach(() => {
		delete document.readyState;
		delete window.IntersectionObserver;
		jest.clearAllTimers();
		jest.restoreAllMocks();
	});

	test('waits for DOMContentLoaded while the document is loading', () => {
		setDocumentReadyState('loading');

		loadRuntime();

		expect(window.IntersectionObserver).not.toHaveBeenCalled();

		document.dispatchEvent(new Event('DOMContentLoaded'));
		document.dispatchEvent(new Event('DOMContentLoaded'));

		expect(window.IntersectionObserver).toHaveBeenCalledTimes(1);
	});

	test('initializes immediately when the document is already complete', () => {
		setDocumentReadyState('complete');
		window.PCLL_options.immediate_load_count = 0;
		document.body.innerHTML =
			'<img class="lazy lazy-hidden" data-lazy-type="image" data-lazy-src="image.jpg">';
		const image = document.querySelector('img');

		loadRuntime();

		expect(window.IntersectionObserver).toHaveBeenCalledTimes(1);
		expect(window.IntersectionObserver).toHaveBeenCalledWith(expect.any(Function), {
			rootMargin: '200px 0px',
			threshold: 0,
		});
		expect(observe).toHaveBeenCalledWith(image);
	});

	test('uses the event-based fallback when IntersectionObserver is unavailable', () => {
		setDocumentReadyState('complete');
		delete window.IntersectionObserver;
		const addEventListener = jest.spyOn(window, 'addEventListener');

		loadRuntime();

		expect(addEventListener).toHaveBeenCalledWith('scroll', expect.any(Function), false);
		expect(addEventListener).toHaveBeenCalledWith('resize', expect.any(Function), false);
	});

	test('rescans dynamically added images at most once per animation frame', async () => {
		setDocumentReadyState('complete');
		window.PCLL_options.immediate_load_count = 0;
		const frames = [];
		const requestAnimationFrame = jest
			.spyOn(window, 'requestAnimationFrame')
			.mockImplementation((callback) => frames.push(callback));

		loadRuntime();

		const first = document.createElement('img');
		first.className = 'lazy lazy-hidden';
		document.body.appendChild(first);
		// MutationObserver callbacks are delivered as microtasks.
		await Promise.resolve();

		const second = document.createElement('img');
		second.className = 'lazy lazy-hidden';
		document.body.appendChild(second);
		await Promise.resolve();

		expect(requestAnimationFrame).toHaveBeenCalledTimes(1);
		expect(observe).not.toHaveBeenCalledWith(first);

		frames.forEach((callback) => callback());

		expect(observe).toHaveBeenCalledWith(first);
		expect(observe).toHaveBeenCalledWith(second);
	});
});
