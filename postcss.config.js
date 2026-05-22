/**
 * Strip charset declarations from imported CSS files.
 *
 * Bundled CSS is emitted as UTF-8, and some vendor CSS ships a banner before
 * the charset at-rule, which makes postcss-import warn before bundling.
 *
 * @returns {object} A PostCSS plugin.
 */
const stripImportedCharset = () => ({
	postcssPlugin: 'strip-imported-charset',
	AtRule: {
		charset: (atRule) => atRule.remove(),
	},
});

/**
 * Exports the PostCSS configuration.
 *
 * @param {object} context The PostCSS loader context.
 * @param {string} context.env The current build environment.
 *
 * @returns {object} PostCSS options.
 */
module.exports = ({ env }) => ({
	plugins: {
		'postcss-import': {
			plugins: [stripImportedCharset],
		},
		'postcss-preset-env': {
			stage: 0,
			autoprefixer: {
				grid: true,
			},
		},
		// Minify style on production using cssano.
		cssnano:
			env === 'production'
				? {
						preset: [
							'default',
							{
								autoprefixer: false,
								calc: {
									precision: 8,
								},
								convertValues: true,
								discardComments: {
									removeAll: true,
								},
								mergeLonghand: false,
								zindex: false,
							},
						],
					}
				: false,
	},
});

stripImportedCharset.postcss = true;
