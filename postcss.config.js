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
		'postcss-import': {},
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
