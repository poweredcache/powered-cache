const { __ } = wp.i18n;
const { render, useEffect, useMemo, useState } = wp.element;
const { Button, CheckboxControl, Notice, SelectControl, Spinner, TextControl, TextareaControl } =
	wp.components;
const { apiFetch } = wp;

const appConfig = window.poweredCacheSettingsApp || {};

if (appConfig.restNonce && apiFetch.createNonceMiddleware) {
	apiFetch.use(apiFetch.createNonceMiddleware(appConfig.restNonce));
}

/**
 * Return the plugin REST path.
 *
 * @param {string} path Route path.
 *
 * @returns {string} REST path.
 */
const route = (path) => `/${appConfig.namespace || 'powered-cache/v1'}${path}`;

/**
 * Build a readable fallback label.
 *
 * @param {string} key Setting key.
 *
 * @returns {string} Label.
 */
const labelFromKey = (key) =>
	key
		.replace(/[_-]/g, ' ')
		.replace(/\w\S*/g, (word) => word.charAt(0).toUpperCase() + word.slice(1));

/**
 * Convert a control value into a settings value.
 *
 * @param {*}      value Field value.
 * @param {object} field Manifest field.
 *
 * @returns {*} Settings value.
 */
const normalizeControlValue = (value, field) => {
	if (field.control === 'number') {
		const numberValue = parseInt(value, 10);
		return Number.isNaN(numberValue) ? 0 : numberValue;
	}

	if (field.control === 'list') {
		return String(value)
			.split('\n')
			.map((item) => item.trim())
			.filter(Boolean);
	}

	return value;
};

/**
 * Convert a settings value into a display value.
 *
 * @param {*}      value Field value.
 * @param {object} field Manifest field.
 *
 * @returns {*} Display value.
 */
const displayValue = (value, field) => {
	if (field.control === 'list' && Array.isArray(value)) {
		return value.join('\n');
	}

	if (field.control === 'number') {
		return undefined === value || value === null ? '' : String(value);
	}

	return undefined === value || value === null ? '' : value;
};

const LockedField = ({ field }) => (
	<div className="pc-settings-field pc-settings-field--locked">
		<div className="pc-settings-field__main">
			<div className="pc-settings-field__heading">
				<span className="pc-settings-field__label">{field.label}</span>
				<span className="pc-settings-badge">{__('Premium', 'powered-cache')}</span>
			</div>
			{field.description && (
				<p className="pc-settings-field__description">{field.description}</p>
			)}
			{field.upgrade && field.upgrade.description && (
				<p className="pc-settings-field__upgrade">{field.upgrade.description}</p>
			)}
		</div>
		<div className="pc-settings-field__control">
			<Button
				href={appConfig.upgradeUrl || 'https://poweredcache.com/'}
				target="_blank"
				variant="primary"
			>
				{field.upgrade ? field.upgrade.label : __('Upgrade', 'powered-cache')}
			</Button>
		</div>
	</div>
);

const SettingsField = ({ field, settings, onChange }) => {
	const isLocked = field.premium && !appConfig.isPremium;
	const isDependencyMet = (field.dependencies || []).every(
		(dependency) => !!settings[dependency],
	);
	const value = settings[field.key];
	const fieldId = `powered-cache-setting-${field.key}`;
	const descriptionId = `${fieldId}-description`;

	if (isLocked) {
		return <LockedField field={field} />;
	}

	const updateValue = (nextValue) => {
		onChange(field.key, normalizeControlValue(nextValue, field));
	};

	const controlProps = {
		disabled: !isDependencyMet,
	};

	let control;

	switch (field.control) {
		case 'toggle':
			control = (
				<CheckboxControl
					{...controlProps}
					checked={!!value}
					label={field.label}
					onChange={updateValue}
				/>
			);
			break;
		case 'select':
			control = (
				<SelectControl
					{...controlProps}
					aria-describedby={descriptionId}
					label={field.label}
					onChange={updateValue}
					options={(field.enum || []).map((option) => ({
						label:
							(field.enum_labels && field.enum_labels[option]) ||
							labelFromKey(option),
						value: option,
					}))}
					value={displayValue(value, field)}
				/>
			);
			break;
		case 'textarea':
		case 'list':
			control = (
				<TextareaControl
					{...controlProps}
					aria-describedby={descriptionId}
					label={field.label}
					onChange={updateValue}
					value={displayValue(value, field)}
				/>
			);
			break;
		case 'number':
			control = (
				<TextControl
					{...controlProps}
					aria-describedby={descriptionId}
					label={field.label}
					onChange={updateValue}
					type="number"
					value={displayValue(value, field)}
				/>
			);
			break;
		case 'text':
		default:
			control = (
				<TextControl
					{...controlProps}
					aria-describedby={descriptionId}
					label={field.label}
					onChange={updateValue}
					value={displayValue(value, field)}
				/>
			);
			break;
	}

	return (
		<div className="pc-settings-field">
			<div className="pc-settings-field__main">
				<span className="pc-settings-field__label">{field.label}</span>
				{field.description && (
					<p id={descriptionId} className="pc-settings-field__description">
						{field.description}
					</p>
				)}
				{!isDependencyMet && (
					<p className="pc-settings-field__dependency">
						{__('Enable the parent setting to edit this option.', 'powered-cache')}
					</p>
				)}
			</div>
			<div className="pc-settings-field__control">{control}</div>
		</div>
	);
};

const SettingsSection = ({ sectionKey, section, fields, settings, onChange }) => (
	<section id={`pc-settings-section-${sectionKey}`} className="pc-settings-section">
		<div className="pc-settings-section__header">
			<h2>{section.label}</h2>
			{section.description && <p>{section.description}</p>}
		</div>
		<div className="pc-settings-section__body">
			{fields.map((field) => (
				<SettingsField
					field={field}
					key={field.key}
					onChange={onChange}
					settings={settings}
				/>
			))}
		</div>
	</section>
);

const SettingsApp = () => {
	const [manifest, setManifest] = useState(null);
	const [settings, setSettings] = useState({});
	const [initialSettings, setInitialSettings] = useState({});
	const [activeSection, setActiveSection] = useState('');
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);

	useEffect(() => {
		Promise.all([
			apiFetch({ path: route('/settings/manifest') }),
			apiFetch({ path: route('/settings') }),
		])
			.then(([manifestResponse, stateResponse]) => {
				setManifest(manifestResponse);
				setSettings(stateResponse.settings || {});
				setInitialSettings(stateResponse.settings || {});
				setActiveSection(Object.keys(manifestResponse.sections || {})[0] || '');
			})
			.catch(() => {
				setNotice({
					status: 'error',
					message: __('Settings could not be loaded.', 'powered-cache'),
				});
			})
			.finally(() => {
				setIsLoading(false);
			});
	}, []);

	const sections = useMemo(() => {
		if (!manifest) {
			return [];
		}

		return Object.entries(manifest.sections || {}).sort(
			([, first], [, second]) => first.order - second.order,
		);
	}, [manifest]);

	const fieldsBySection = useMemo(() => {
		if (!manifest) {
			return {};
		}

		return Object.values(manifest.fields || {}).reduce((groups, field) => {
			if (!groups[field.section]) {
				groups[field.section] = [];
			}

			groups[field.section].push(field);
			groups[field.section].sort((first, second) => first.order - second.order);

			return groups;
		}, {});
	}, [manifest]);

	const isDirty = JSON.stringify(settings) !== JSON.stringify(initialSettings);

	const updateSetting = (key, value) => {
		setSettings((currentSettings) => ({
			...currentSettings,
			[key]: value,
		}));
	};

	const saveSettings = () => {
		setIsSaving(true);
		setNotice(null);

		apiFetch({
			path: route('/settings'),
			method: 'POST',
			data: {
				settings,
			},
		})
			.then((response) => {
				const nextSettings = response.settings || settings;

				setSettings(nextSettings);
				setInitialSettings(nextSettings);
				setNotice({
					status: 'success',
					message: __('Settings saved.', 'powered-cache'),
				});
			})
			.catch(() => {
				setNotice({
					status: 'error',
					message: __('Settings could not be saved.', 'powered-cache'),
				});
			})
			.finally(() => {
				setIsSaving(false);
			});
	};

	if (isLoading) {
		return (
			<div className="pc-settings-loading-state">
				<Spinner />
				<span>{__('Loading settings...', 'powered-cache')}</span>
			</div>
		);
	}

	if (!manifest) {
		return (
			<Notice status="error" isDismissible={false}>
				{__('Settings manifest is not available.', 'powered-cache')}
			</Notice>
		);
	}

	const activeFields = fieldsBySection[activeSection] || [];
	const activeSectionData = manifest.sections[activeSection] || {};
	const premiumFields = Object.values(manifest.fields || {}).filter((field) => field.premium);

	return (
		<div className="pc-settings-shell">
			<header className="pc-settings-header">
				<div>
					<p className="pc-settings-eyebrow">
						{__('Performance Control Center', 'powered-cache')}
					</p>
					<h1>{__('Powered Cache', 'powered-cache')}</h1>
					<p>
						{__(
							'Configure cache, optimization, media delivery, and integrations from one focused screen.',
							'powered-cache',
						)}
					</p>
				</div>
				<div className="pc-settings-header__actions">
					<Button href={appConfig.docsUrl || '#'} target="_blank" variant="secondary">
						{__('Documentation', 'powered-cache')}
					</Button>
					{!appConfig.isPremium && (
						<Button
							href={appConfig.upgradeUrl || '#'}
							target="_blank"
							variant="primary"
						>
							{__('Upgrade', 'powered-cache')}
						</Button>
					)}
				</div>
			</header>

			{notice && (
				<Notice onRemove={() => setNotice(null)} status={notice.status}>
					{notice.message}
				</Notice>
			)}

			<div
				className="pc-settings-overview"
				aria-label={__('Settings overview', 'powered-cache')}
			>
				<div>
					<span>{__('Page Cache', 'powered-cache')}</span>
					<strong>
						{settings.enable_page_cache
							? __('Enabled', 'powered-cache')
							: __('Disabled', 'powered-cache')}
					</strong>
				</div>
				<div>
					<span>{__('Object Cache', 'powered-cache')}</span>
					<strong>
						{settings.object_cache && settings.object_cache !== 'off'
							? settings.object_cache
							: __('Off', 'powered-cache')}
					</strong>
				</div>
				<div>
					<span>{__('Image Optimization', 'powered-cache')}</span>
					<strong>
						{settings.enable_image_optimization
							? __('Enabled', 'powered-cache')
							: __('Premium', 'powered-cache')}
					</strong>
				</div>
				<div>
					<span>{__('Premium Features', 'powered-cache')}</span>
					<strong>
						{appConfig.isPremium
							? __('Unlocked', 'powered-cache')
							: premiumFields.length}
					</strong>
				</div>
			</div>

			<div className="pc-settings-layout">
				<nav
					className="pc-settings-nav"
					aria-label={__('Settings sections', 'powered-cache')}
				>
					{sections.map(([sectionKey, section]) => (
						<button
							aria-current={sectionKey === activeSection ? 'page' : undefined}
							className={sectionKey === activeSection ? 'is-active' : ''}
							key={sectionKey}
							onClick={() => setActiveSection(sectionKey)}
							type="button"
						>
							{section.label}
						</button>
					))}
				</nav>

				<div className="pc-settings-content">
					<SettingsSection
						fields={activeFields}
						onChange={updateSetting}
						section={activeSectionData}
						sectionKey={activeSection}
						settings={settings}
					/>
				</div>
			</div>

			<footer className="pc-settings-savebar">
				<span>
					{isDirty
						? __('You have unsaved changes.', 'powered-cache')
						: __('All changes saved.', 'powered-cache')}
				</span>
				<Button
					disabled={!isDirty || isSaving}
					isBusy={isSaving}
					onClick={saveSettings}
					variant="primary"
				>
					{__('Save Settings', 'powered-cache')}
				</Button>
			</footer>
		</div>
	);
};

const mountNode = document.getElementById('powered-cache-settings-app');

if (mountNode) {
	render(<SettingsApp />, mountNode);
}
