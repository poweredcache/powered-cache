const { __, sprintf } = wp.i18n;
const { render, useEffect, useMemo, useState } = wp.element;
const { Button, Notice, SelectControl, Spinner, TextControl, TextareaControl, ToggleControl } =
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

const durationUnits = [
	{
		label: __('Weeks', 'powered-cache'),
		value: 'week',
		minutes: 10080,
	},
	{
		label: __('Days', 'powered-cache'),
		value: 'day',
		minutes: 1440,
	},
	{
		label: __('Hours', 'powered-cache'),
		value: 'hour',
		minutes: 60,
	},
	{
		label: __('Minutes', 'powered-cache'),
		value: 'minute',
		minutes: 1,
	},
];

/**
 * Convert stored minutes into the largest clean duration unit.
 *
 * @param {*} minutes Stored minutes.
 *
 * @returns {object} Duration parts.
 */
const getDurationParts = (minutes) => {
	const totalMinutes = parseInt(minutes, 10);
	const safeMinutes = Number.isNaN(totalMinutes) || totalMinutes < 0 ? 0 : totalMinutes;
	const unit =
		durationUnits.find(
			(durationUnit) => safeMinutes > 0 && safeMinutes % durationUnit.minutes === 0,
		) || durationUnits[durationUnits.length - 1];

	return {
		amount: safeMinutes > 0 ? safeMinutes / unit.minutes : 0,
		unit: unit.value,
	};
};

/**
 * Convert duration control values back to minutes for storage.
 *
 * @param {*}      amount Duration amount.
 * @param {string} unit   Duration unit.
 *
 * @returns {number} Minutes.
 */
const durationToMinutes = (amount, unit) => {
	const durationAmount = parseInt(amount, 10);
	const safeAmount = Number.isNaN(durationAmount) || durationAmount < 0 ? 0 : durationAmount;
	const durationUnit =
		durationUnits.find((availableUnit) => availableUnit.value === unit) ||
		durationUnits[durationUnits.length - 1];

	return safeAmount * durationUnit.minutes;
};

const noop = () => {};

const toggleLabel = (field) =>
	field.control_label ||
	sprintf(
		/* translators: %s: setting label. */
		__('Enable %s', 'powered-cache'),
		field.label,
	);

const upgradeLabel = (field) =>
	sprintf(
		/* translators: %s: setting label. */
		__('Unlock %s', 'powered-cache'),
		field.label,
	);

const MetricCard = ({ label, value, description, tone = 'neutral' }) => (
	<div className={`pc-settings-metric pc-settings-metric--${tone}`}>
		<span>{label}</span>
		<strong>{value}</strong>
		{description && <p>{description}</p>}
	</div>
);

const LockedControlPreview = ({ field }) => {
	const previewValue = displayValue(field.default, field);
	const controlProps = {
		disabled: true,
	};
	let control;

	switch (field.control) {
		case 'toggle':
			control = (
				<ToggleControl
					{...controlProps}
					checked={!!field.default}
					label={toggleLabel(field)}
					onChange={noop}
				/>
			);
			break;
		case 'select':
			control = (
				<SelectControl
					{...controlProps}
					label={field.label}
					onChange={noop}
					options={(field.enum || []).map((option) => ({
						label:
							(field.enum_labels && field.enum_labels[option]) ||
							labelFromKey(option),
						value: option,
					}))}
					value={previewValue}
				/>
			);
			break;
		case 'textarea':
		case 'list':
			control = (
				<TextareaControl
					{...controlProps}
					label={field.label}
					onChange={noop}
					value={previewValue}
				/>
			);
			break;
		case 'duration': {
			const duration = getDurationParts(field.default);

			control = (
				<div className="pc-settings-duration-control">
					<TextControl
						{...controlProps}
						label={field.label}
						min="0"
						onChange={noop}
						type="number"
						value={String(duration.amount)}
					/>
					<SelectControl
						{...controlProps}
						label={__('Unit', 'powered-cache')}
						onChange={noop}
						options={durationUnits.map((durationUnit) => ({
							label: durationUnit.label,
							value: durationUnit.value,
						}))}
						value={duration.unit}
					/>
				</div>
			);
			break;
		}
		case 'number':
			control = (
				<TextControl
					{...controlProps}
					label={field.label}
					onChange={noop}
					type="number"
					value={previewValue}
				/>
			);
			break;
		case 'text':
		default:
			control = (
				<TextControl
					{...controlProps}
					label={field.label}
					onChange={noop}
					value={previewValue}
				/>
			);
			break;
	}

	return (
		<div className="pc-settings-locked-control">
			<div className="pc-settings-locked-control__preview">{control}</div>
			<div className="pc-settings-locked-control__overlay">
				<div className="pc-settings-locked-control__panel">
					<span>{__('Available in Premium', 'powered-cache')}</span>
					<strong>{upgradeLabel(field)}</strong>
					<p>
						{__(
							'Upgrade to enable this control and the full Premium optimization toolkit.',
							'powered-cache',
						)}
					</p>
					<Button
						href={appConfig.upgradeUrl || 'https://poweredcache.com/'}
						target="_blank"
						variant="primary"
					>
						{field.upgrade ? field.upgrade.label : __('Upgrade', 'powered-cache')}
					</Button>
				</div>
			</div>
		</div>
	);
};

const LockedField = ({ field }) => (
	<div className="pc-settings-field pc-settings-field--locked" aria-label={field.label}>
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
			<LockedControlPreview field={field} />
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
				<ToggleControl
					{...controlProps}
					aria-describedby={descriptionId}
					checked={!!value}
					label={toggleLabel(field)}
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
		case 'duration': {
			const duration = getDurationParts(value);

			control = (
				<div className="pc-settings-duration-control">
					<TextControl
						{...controlProps}
						aria-describedby={descriptionId}
						label={field.label}
						min="0"
						onChange={(nextAmount) => {
							updateValue(durationToMinutes(nextAmount, duration.unit));
						}}
						type="number"
						value={String(duration.amount)}
					/>
					<SelectControl
						{...controlProps}
						label={__('Unit', 'powered-cache')}
						onChange={(nextUnit) => {
							updateValue(durationToMinutes(duration.amount, nextUnit));
						}}
						options={durationUnits.map((durationUnit) => ({
							label: durationUnit.label,
							value: durationUnit.value,
						}))}
						value={duration.unit}
					/>
				</div>
			);
			break;
		}
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

const SettingsGroup = ({ groupName, fields, settings, onChange }) => (
	<div className="pc-settings-group">
		<h3>{groupName}</h3>
		<div className="pc-settings-group__body">
			{fields.map((field) => (
				<SettingsField
					field={field}
					key={field.key}
					onChange={onChange}
					settings={settings}
				/>
			))}
		</div>
	</div>
);

const SettingsSection = ({ sectionKey, section, fields, settings, onChange }) => {
	const groups = fields.reduce((fieldGroups, field) => {
		const groupName = field.group || section.label;

		if (!fieldGroups[groupName]) {
			fieldGroups[groupName] = [];
		}

		fieldGroups[groupName].push(field);

		return fieldGroups;
	}, {});

	const premiumCount = fields.filter((field) => field.premium).length;

	return (
		<section id={`pc-settings-section-${sectionKey}`} className="pc-settings-section">
			<div className="pc-settings-section__header">
				<div>
					<h2>{section.label}</h2>
					{section.description && <p>{section.description}</p>}
				</div>
				<div
					className="pc-settings-section__meta"
					aria-label={__('Section summary', 'powered-cache')}
				>
					<span>
						{fields.length} {__('settings', 'powered-cache')}
					</span>
					{!!premiumCount && (
						<span>
							{premiumCount} {__('Premium', 'powered-cache')}
						</span>
					)}
				</div>
			</div>
			<div className="pc-settings-section__body">
				{Object.entries(groups).map(([groupName, groupFields]) => (
					<SettingsGroup
						fields={groupFields}
						groupName={groupName}
						key={groupName}
						onChange={onChange}
						settings={settings}
					/>
				))}
			</div>
		</section>
	);
};

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
	const premiumInfo = appConfig.premium || {};
	const enabledCoreCount = [
		settings.enable_page_cache,
		settings.cache_mobile,
		settings.gzip_compression,
		settings.enable_lazy_load,
		settings.enable_cache_preload,
	].filter(Boolean).length;

	return (
		<div className="pc-settings-shell">
			<header className="pc-settings-header">
				<div className="pc-settings-header__content">
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
					<div className="pc-settings-plan-card">
						<span>{__('Current Plan', 'powered-cache')}</span>
						<strong>
							{appConfig.isPremium
								? __('Premium', 'powered-cache')
								: __('Free', 'powered-cache')}
						</strong>
						<p>
							{appConfig.isPremium
								? premiumInfo.licenseMessage ||
									__(
										'Advanced optimization controls are unlocked.',
										'powered-cache',
									)
								: __(
										'Premium-only controls stay visible so you can see what to unlock next.',
										'powered-cache',
									)}
						</p>
					</div>
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
				<MetricCard
					description={__('HTML cache delivery for anonymous visits.', 'powered-cache')}
					label={__('Page Cache', 'powered-cache')}
					tone={settings.enable_page_cache ? 'good' : 'warning'}
					value={
						settings.enable_page_cache
							? __('Enabled', 'powered-cache')
							: __('Disabled', 'powered-cache')
					}
				/>
				<MetricCard
					description={__(
						'Persistent backend for dynamic WordPress data.',
						'powered-cache',
					)}
					label={__('Object Cache', 'powered-cache')}
					tone={
						settings.object_cache && settings.object_cache !== 'off'
							? 'good'
							: 'neutral'
					}
					value={
						settings.object_cache && settings.object_cache !== 'off'
							? settings.object_cache
							: __('Off', 'powered-cache')
					}
				/>
				<MetricCard
					description={__('Core optimizations currently switched on.', 'powered-cache')}
					label={__('Active Controls', 'powered-cache')}
					tone="good"
					value={`${enabledCoreCount}/5`}
				/>
				<MetricCard
					description={
						appConfig.isPremium
							? premiumInfo.licenseMessage ||
								__(
									'Premium optimizations are available on this site.',
									'powered-cache',
								)
							: __(
									'Locked controls are shown in context across the settings.',
									'powered-cache',
								)
					}
					label={
						appConfig.isPremium
							? __('License', 'powered-cache')
							: __('Premium Features', 'powered-cache')
					}
					tone={appConfig.isPremium && !premiumInfo.licenseActive ? 'warning' : 'premium'}
					value={
						appConfig.isPremium
							? labelFromKey(premiumInfo.licenseStatus || 'unknown')
							: premiumFields.length
					}
				/>
			</div>

			{!appConfig.isPremium && (
				<div className="pc-settings-upgrade-panel">
					<div>
						<span className="pc-settings-badge">{__('Premium', 'powered-cache')}</span>
						<h2>{__('Unlock the high-impact optimization layer', 'powered-cache')}</h2>
						<p>
							{__(
								'Critical CSS, unused CSS cleanup, LCP optimization, and image delivery are shown where they belong so upgrading feels like enabling the next layer rather than learning a different product.',
								'powered-cache',
							)}
						</p>
					</div>
					<Button href={appConfig.upgradeUrl || '#'} target="_blank" variant="primary">
						{__('Compare Premium', 'powered-cache')}
					</Button>
				</div>
			)}

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
