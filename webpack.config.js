const defaultConfig = require('10up-toolkit/config/webpack.config');

const KNOWN_SHARED_UI_ASSETS = /^(css\/admin-style\.css|fonts\/wpmudev-plugin-icons\.svg)$/;
const SHARED_UI_ENTRYPOINT_BUDGET = 700 * 1024;

/**
 * Apply project-specific build budgets while keeping the 10up defaults.
 *
 * @param {object} config The default 10up Toolkit webpack configuration.
 *
 * @returns {object} The project webpack configuration.
 */
const configureWebpack = (config) => {
	const defaultAssetFilter =
		config.performance && typeof config.performance.assetFilter === 'function'
			? config.performance.assetFilter
			: () => true;

	return {
		...config,
		performance: {
			...config.performance,
			maxEntrypointSize: SHARED_UI_ENTRYPOINT_BUDGET,
			assetFilter: (assetFilename) =>
				!KNOWN_SHARED_UI_ASSETS.test(assetFilename) && defaultAssetFilter(assetFilename),
		},
	};
};

module.exports = Array.isArray(defaultConfig)
	? defaultConfig.map(configureWebpack)
	: configureWebpack(defaultConfig);
