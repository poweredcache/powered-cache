const { __ } = wp.i18n;
const { PluginDocumentSettingPanel } = wp.editPost;
const { CheckboxControl } = wp.components;
const { dispatch, useSelect } = wp.data;
const { registerPlugin } = wp.plugins;

/**
 * PoweredCacheMetaBox
 *
 * @return PluginDocumentSettingPanel
 */
const PoweredCacheMetaBox = () => {
	const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta'));

	if (!meta) {
		return null;
	}

	if (
		!('powered_cache_disable_cache' in meta) &&
		!('powered_cache_disable_lazyload' in meta) &&
		!('powered_cache_disable_css_optimization' in meta) &&
		!('powered_cache_disable_js_optimization' in meta) &&
		!('powered_cache_disable_critical_css' in meta) &&
		!('powered_cache_specific_critical_css' in meta)
	) {
		return null; // nothing to control
	}

	const disableCache = meta.powered_cache_disable_cache || false;
	const disableLazyLoad = meta.powered_cache_disable_lazyload || false;
	const disableCSSOptimization = meta.powered_cache_disable_css_optimization || false;
	const disableJSOptimization = meta.powered_cache_disable_js_optimization || false;
	const disableCritical = meta.powered_cache_disable_critical_css || false;
	const specificCritical = meta.powered_cache_specific_critical_css || false;

	return (
		<PluginDocumentSettingPanel
			icon="superhero"
			title={__('Powered Cache', 'powered-cache')}
			className="powered-cache-panel"
		>
			{'powered_cache_disable_cache' in meta && (
				<CheckboxControl
					label={__("Don't cache this post", 'powered-cache')}
					checked={disableCache}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { powered_cache_disable_cache: !disableCache },
						});
					}}
				/>
			)}

			{'powered_cache_disable_lazyload' in meta && (
				<CheckboxControl
					label={__('Disable lazy loading for this post', 'powered-cache')}
					checked={disableLazyLoad}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { powered_cache_disable_lazyload: !disableLazyLoad },
						});
					}}
				/>
			)}

			{'powered_cache_disable_css_optimization' in meta && (
				<CheckboxControl
					label={__('Disable CSS optimization', 'powered-cache')}
					checked={disableCSSOptimization}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								powered_cache_disable_css_optimization: !disableCSSOptimization,
							},
						});
					}}
				/>
			)}

			{'powered_cache_disable_js_optimization' in meta && (
				<CheckboxControl
					label={__('Disable JS optimization', 'powered-cache')}
					checked={disableJSOptimization}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { powered_cache_disable_js_optimization: !disableJSOptimization },
						});
					}}
				/>
			)}

			{'powered_cache_disable_critical_css' in meta && !specificCritical && (
				<CheckboxControl
					label={__('Disable Critical CSS for this post', 'powered-cache')}
					checked={disableCritical}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								powered_cache_disable_critical_css: !disableCritical,
							},
						});
					}}
				/>
			)}

			{'powered_cache_specific_critical_css' in meta && !disableCritical && (
				<CheckboxControl
					label={__('Generate specific Critical CSS', 'powered-cache')}
					checked={specificCritical}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								powered_cache_specific_critical_css: !specificCritical,
							},
						});
					}}
				/>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('powered-cache-post-meta', { render: PoweredCacheMetaBox });
