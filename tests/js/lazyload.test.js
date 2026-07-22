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
		document.body.innerHTML = '';
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
});
