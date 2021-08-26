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
	const disableCache = meta.powered_cache_disable_cache || false;
	const disableLazyLoad = meta.powered_cache_disable_lazyload || false;

	if (!('powered_cache_disable_cache' in meta) && !('powered_cache_disable_lazyload' in meta)) {
		return null; // nothing to control
	}

	return (
		<PluginDocumentSettingPanel
			icon="superhero"
			title={__('Powered Cache', 'powered-cache')}
			className="powered-cache-panel"
		>
			{'powered_cache_disable_cache' in meta && (
				<CheckboxControl
					label={__("Don't cache this page", 'powered-cache')}
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
					label={__('Skip lazy loading for this post', 'powered-cache')}
					checked={disableLazyLoad}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { powered_cache_disable_lazyload: !disableLazyLoad },
						});
					}}
				/>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('powered-cache-post-meta', { render: PoweredCacheMetaBox });
