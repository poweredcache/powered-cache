const { __, sprintf } = wp.i18n;
const { createRoot, render, useEffect, useMemo, useState } = wp.element;
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

const sectionParam = 'section';

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

const asArray = (value) => {
	if (Array.isArray(value)) {
		return value;
	}

	if (undefined === value || value === null) {
		return [];
	}

	return [value];
};

const orderedSectionKeys = (manifestResponse) =>
	Object.entries(manifestResponse.sections || {})
		.sort(([, first], [, second]) => first.order - second.order)
		.map(([sectionKey]) => sectionKey);

const requestedSection = () => {
	const url = new URL(window.location.href);

	return url.searchParams.get(sectionParam) || '';
};

const urlSection = (href) => {
	try {
		const url = new URL(href, window.location.href);

		if (url.searchParams.get('page') !== 'powered-cache') {
			return null;
		}

		return url.searchParams.get(sectionParam) || '';
	} catch (error) {
		return null;
	}
};

const resolveActiveSection = (manifestResponse) => {
	const sectionKeys = orderedSectionKeys(manifestResponse);
	const requested = requestedSection();

	if (sectionKeys.includes(requested)) {
		return requested;
	}

	return sectionKeys[0] || '';
};

const resolveSectionFromUrl = (manifestResponse, href) => {
	const section = urlSection(href);

	if (section === null) {
		return '';
	}

	const sectionKeys = orderedSectionKeys(manifestResponse);

	if (sectionKeys.includes(section)) {
		return section;
	}

	return sectionKeys[0] || '';
};

const syncSectionUrl = (sectionKey, replace = false) => {
	if (!sectionKey || !window.history) {
		return;
	}

	const url = new URL(window.location.href);

	if (url.searchParams.get(sectionParam) === sectionKey) {
		return;
	}

	url.searchParams.set(sectionParam, sectionKey);
	window.history[replace ? 'replaceState' : 'pushState'](
		{
			...(window.history.state || {}),
			poweredCacheSection: sectionKey,
		},
		'',
		url.toString(),
	);
};

const syncAdminMenuSection = (sectionKey, manifestResponse) => {
	if (!sectionKey || !manifestResponse || !document.querySelector) {
		return;
	}

	const submenuLinks = Array.from(
		document.querySelectorAll('#toplevel_page_powered-cache .wp-submenu a'),
	);
	const poweredCacheLinks = submenuLinks.length
		? submenuLinks
		: Array.from(document.querySelectorAll('#adminmenu a[href*="page=powered-cache"]'));
	let activeLink = null;

	poweredCacheLinks.forEach((link) => {
		const listItem = link.closest('li');
		let linkSection = '';

		linkSection = resolveSectionFromUrl(manifestResponse, link.href);

		link.classList.remove('current');

		if (listItem) {
			listItem.classList.remove('current');
		}

		if (linkSection === sectionKey) {
			activeLink = link;
		}
	});

	if (activeLink) {
		activeLink.classList.add('current');

		if (activeLink.closest('li')) {
			activeLink.closest('li').classList.add('current');
		}
	}
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

const selectOptions = (field) => {
	if (field.options) {
		return Object.entries(field.options).map(([value, label]) => ({
			label,
			value,
		}));
	}

	return (field.enum || []).map((option) => ({
		label: (field.enum_labels && field.enum_labels[option]) || labelFromKey(option),
		value: option,
	}));
};

const fieldDocsUrl = (field) => {
	if (field.docs_url) {
		return field.docs_url;
	}

	if (!field.docs_path) {
		return '';
	}

	const fallbackBase = 'https://docs.poweredcache.com/';

	try {
		const docsUrl = new URL(appConfig.docsUrl || fallbackBase, fallbackBase);
		const basePath = docsUrl.pathname.replace(/\/$/, '');
		const fieldPath = String(field.docs_path).replace(/^\/+/, '');

		docsUrl.pathname = `${basePath}/${fieldPath}`.replace(/\/{2,}/g, '/');
		docsUrl.hash = field.docs_fragment ? `#${field.docs_fragment}` : '';

		return docsUrl.toString();
	} catch {
		const fieldPath = String(field.docs_path).replace(/^\/+/, '');
		const fragment = field.docs_fragment ? `#${field.docs_fragment}` : '';

		return `${fallbackBase}${fieldPath}${fragment}`;
	}
};

const FieldDescription = ({ field, id }) => {
	const docsUrl = fieldDocsUrl(field);

	if (!field.description && !docsUrl) {
		return null;
	}

	return (
		<p id={id} className="pc-settings-field__description">
			{field.description}
			{docsUrl && (
				<>
					{' '}
					<a
						className="pc-settings-field__docs-link"
						href={docsUrl}
						rel="noopener noreferrer"
						target="_blank"
					>
						{__('Learn more', 'powered-cache')}
					</a>
				</>
			)}
		</p>
	);
};

const conditionMatches = (condition, settings) => {
	if (!condition || !condition.key) {
		return true;
	}

	const actualValue = settings[condition.key];
	const expectedValue = condition.value;

	switch (condition.operator || 'equals') {
		case 'not_equals':
			return actualValue !== expectedValue;
		case 'in':
			return Array.isArray(expectedValue) && expectedValue.includes(actualValue);
		case 'equals':
		default:
			return actualValue === expectedValue;
	}
};

const fieldVisible = (field, settings) =>
	(field.visible_when || []).every((condition) => conditionMatches(condition, settings));

const upgradeLabel = (field) =>
	sprintf(
		/* translators: %s: setting label. */
		__('Unlock %s', 'powered-cache'),
		field.label,
	);

const objectCacheNeedsAlloptionsCheck = (backend) =>
	['memcache', 'memcached'].includes(String(backend || ''));

const textFromHtml = (html) => {
	const element = document.createElement('div');

	element.innerHTML = html || '';

	return element.textContent || element.innerText || '';
};

const MetricCard = ({ label, value, description, tone = 'neutral' }) => (
	<div className={`pc-settings-metric pc-settings-metric--${tone}`}>
		<span>{label}</span>
		<strong>{value}</strong>
		{description && <p>{description}</p>}
	</div>
);

const SystemStatusPanel = ({ onSelectSection = () => {}, systemStatus = {} }) => {
	const checks = Array.isArray(systemStatus.checks) ? systemStatus.checks : [];

	if (!checks.length) {
		return null;
	}

	const counts = systemStatus.counts || {};
	const summaryStatus = systemStatus.status || 'good';
	const statusLabel = {
		good: __('Ready', 'powered-cache'),
		warning: __('Needs attention', 'powered-cache'),
		info: __('Review', 'powered-cache'),
		idle: __('Idle', 'powered-cache'),
	};
	const visibleChecks = [...checks].sort((first, second) => {
		const weight = {
			warning: 0,
			info: 1,
			idle: 2,
			good: 3,
		};

		return (weight[first.status] ?? 4) - (weight[second.status] ?? 4);
	});

	return (
		<section className={`pc-settings-system pc-settings-system--${summaryStatus}`}>
			<div className="pc-settings-system__header">
				<div>
					<span className="pc-settings-badge">{__('System check', 'powered-cache')}</span>
					<h2>{__('Optimization Advisor', 'powered-cache')}</h2>
					<p>
						{__(
							'Verify the runtime pieces that keep cache, preload, and background optimization jobs reliable.',
							'powered-cache',
						)}
					</p>
				</div>
				<div className="pc-settings-system__summary">
					<span
						className={`pc-settings-status-pill pc-settings-status-pill--${summaryStatus}`}
					>
						{statusLabel[summaryStatus] || statusLabel.info}
					</span>
					<span>
						{sprintf(
							/* translators: 1: warning count, 2: ready count. */
							__('%1$d attention, %2$d ready', 'powered-cache'),
							Number(counts.warning || 0),
							Number(counts.good || 0),
						)}
					</span>
				</div>
			</div>
			<div className="pc-settings-system__grid">
				{visibleChecks.map((check) => (
					<div
						className={`pc-settings-system-check pc-settings-system-check--${check.status}`}
						key={check.code}
					>
						<div className="pc-settings-system-check__title">
							<strong>{check.label || labelFromKey(check.code)}</strong>
							<span
								className={`pc-settings-status-pill pc-settings-status-pill--${check.status}`}
							>
								{statusLabel[check.status] || statusLabel.info}
							</span>
						</div>
						<p className="pc-settings-system-check__message">{check.message}</p>
						{check.description && <p>{check.description}</p>}
						{check.detail && (
							<code className="pc-settings-system-check__detail">{check.detail}</code>
						)}
						{check.section && (
							<Button
								className="pc-settings-system-check__action"
								onClick={() => onSelectSection(check.section)}
								type="button"
								variant="link"
							>
								{__('Review setting', 'powered-cache')}
							</Button>
						)}
					</div>
				))}
			</div>
		</section>
	);
};

const metricValue = (value, fallback = __('Not available', 'powered-cache')) => {
	if (value === null || value === undefined || value === '') {
		return fallback;
	}

	return value;
};

const formatBytes = (value, fallback = __('Not available', 'powered-cache')) => {
	const bytes = Number(value || 0);

	if (!bytes) {
		return fallback;
	}

	if (bytes < 1024) {
		return sprintf(
			/* translators: %d: byte count. */
			__('%d B', 'powered-cache'),
			bytes,
		);
	}

	if (bytes < 1024 * 1024) {
		return sprintf(
			/* translators: %s: kilobyte count. */
			__('%s KB', 'powered-cache'),
			(bytes / 1024).toFixed(1),
		);
	}

	return sprintf(
		/* translators: %s: megabyte count. */
		__('%s MB', 'powered-cache'),
		(bytes / 1024 / 1024).toFixed(1),
	);
};

const queueMetricValue = (service) => {
	const value = metricValue(service.queueCount, 0);

	return service.queueCapped ? `${value}+` : value;
};

const cssCompatibilityRuleCount = (compatibility = {}) =>
	Object.values(compatibility.counts || {}).reduce(
		(total, count) => total + Number(count || 0),
		0,
	);

const cssCompatibilitySourceSummary = (compatibility = {}) => {
	const sources = compatibility.sources || [];
	const labels = sources
		.map((source) => source.label)
		.filter(Boolean)
		.slice(0, 3);

	if (!labels.length) {
		return '';
	}

	const remaining = Math.max(0, sources.length - labels.length);

	return remaining
		? sprintf(
				/* translators: 1: comma-separated source labels, 2: remaining source count. */
				__('%1$s, and %2$d more', 'powered-cache'),
				labels.join(', '),
				remaining,
			)
		: labels.join(', ');
};

const formatTimestamp = (timestamp) => {
	const value = parseInt(timestamp, 10);

	if (!value) {
		return __('Not run yet', 'powered-cache');
	}

	return new Date(value * 1000).toLocaleString();
};

const ImageDeliveryPanel = ({ imageDelivery = {} }) => {
	const stats = imageDelivery.stats || {};
	const hasStats = !!stats.available;
	const domain = imageDelivery.domain || 'https://img.poweredcache.net';
	const preferredFormat = imageDelivery.preferredFormat
		? imageDelivery.preferredFormat.toUpperCase()
		: __('Auto', 'powered-cache');

	return (
		<section
			className={`pc-settings-delivery-card pc-settings-delivery-card--${
				imageDelivery.enabled ? 'enabled' : 'idle'
			}`}
		>
			<div className="pc-settings-delivery-card__header">
				<div>
					<div className="pc-settings-delivery-card__heading">
						<span className="pc-settings-badge">
							{__('Premium delivery', 'powered-cache')}
						</span>
					</div>
					<h3>{__('Image Delivery', 'powered-cache')}</h3>
					<p>
						{__(
							'Serve optimized WebP/AVIF images through the Powered Cache delivery network while keeping the workflow inside your existing media settings.',
							'powered-cache',
						)}
					</p>
				</div>
				<div className="pc-settings-delivery-card__actions">
					<span
						className={`pc-settings-status-pill pc-settings-status-pill--${
							imageDelivery.enabled ? 'enabled' : 'idle'
						}`}
					>
						{imageDelivery.enabled
							? __('Delivering optimized images', 'powered-cache')
							: __('Ready to enable', 'powered-cache')}
					</span>
					{imageDelivery.enabled && imageDelivery.purgeUrl && (
						<Button href={imageDelivery.purgeUrl} variant="secondary">
							{__('Purge Image Cache', 'powered-cache')}
						</Button>
					)}
				</div>
			</div>
			<div className="pc-settings-delivery-card__details">
				<div>
					<span>{__('Service', 'powered-cache')}</span>
					<strong>
						{imageDelivery.enabled
							? __('Enabled', 'powered-cache')
							: __('Disabled', 'powered-cache')}
					</strong>
					<p>
						{imageDelivery.enabled
							? __(
									'Image requests are routed through the optimizer.',
									'powered-cache',
								)
							: __(
									'Enable image optimization below when the site is ready.',
									'powered-cache',
								)}
					</p>
				</div>
				<div>
					<span>{__('Delivery Domain', 'powered-cache')}</span>
					<strong>{domain.replace(/^https?:\/\//, '')}</strong>
					<p>{__('Managed delivery endpoint for optimized images.', 'powered-cache')}</p>
				</div>
				<div>
					<span>{__('Preferred Format', 'powered-cache')}</span>
					<strong>{preferredFormat}</strong>
					<p>
						{__(
							'The best supported output format is selected automatically.',
							'powered-cache',
						)}
					</p>
				</div>
				<div>
					<span>{__('Optimized Images', 'powered-cache')}</span>
					<strong>
						{hasStats
							? metricValue(stats.optimizedImages)
							: __('Collecting data', 'powered-cache')}
					</strong>
					{hasStats && (
						<p>
							{sprintf(
								/* translators: 1: bandwidth saved, 2: cache hit rate. */
								__('Saved %1$s. CDN hit rate %2$s.', 'powered-cache'),
								metricValue(stats.bandwidthSaved),
								metricValue(stats.cacheHitRate),
							)}
						</p>
					)}
					{!hasStats && (
						<p>
							{__(
								'Usage stats will appear after the delivery backend reports aggregated data.',
								'powered-cache',
							)}
						</p>
					)}
				</div>
			</div>
		</section>
	);
};

const CssOptimizationPanel = ({
	cssOptimization = {},
	docsUrl = '#',
	generatingService = '',
	onGenerate = null,
	settings = {},
}) => {
	const services = Object.entries(cssOptimization.services || {});

	if (!services.length) {
		return null;
	}

	const canGenerate = Boolean(cssOptimization.generatePath && onGenerate);
	const compatibility = cssOptimization.compatibility || {};
	const compatibilitySourceCount = (compatibility.sources || []).length;
	const compatibilityRuleCount = cssCompatibilityRuleCount(compatibility);
	const compatibilitySources = cssCompatibilitySourceSummary(compatibility);
	const stateLabel = {
		good: __('Healthy', 'powered-cache'),
		processing: __('Processing', 'powered-cache'),
		warning: __('Needs attention', 'powered-cache'),
		unavailable: __('Unavailable', 'powered-cache'),
		idle: __('Waiting for first run', 'powered-cache'),
	};

	return (
		<section className="pc-settings-service-health">
			<div className="pc-settings-service-health__header">
				<div>
					<span className="pc-settings-badge">
						{__('Optimization services', 'powered-cache')}
					</span>
					<h3>{__('CSS Optimization Status', 'powered-cache')}</h3>
					<p>
						{__(
							'Track the latest Critical CSS and Used CSS generation results without storing page HTML or CSS payloads.',
							'powered-cache',
						)}
					</p>
					{!!compatibilitySourceCount && !!compatibilityRuleCount && (
						<>
							<p className="pc-settings-service-health__compatibility">
								{sprintf(
									/* translators: 1: number of compatibility rules, 2: number of active sources. */
									__(
										'Applying %1$d compatibility rules from %2$d active source(s).',
										'powered-cache',
									),
									compatibilityRuleCount,
									compatibilitySourceCount,
								)}
							</p>
							{compatibilitySources && (
								<p className="pc-settings-service-health__sources">
									{sprintf(
										/* translators: %s: compatibility source labels. */
										__('Active sources: %s', 'powered-cache'),
										compatibilitySources,
									)}
								</p>
							)}
						</>
					)}
				</div>
				<Button href={docsUrl} target="_blank" variant="secondary">
					{__('Troubleshooting', 'powered-cache')}
				</Button>
			</div>
			<div className="pc-settings-service-health__grid">
				{services.map(([serviceKey, service]) => {
					const serviceState =
						service.available === false ? 'unavailable' : service.state || 'idle';
					const settingEnabled = service.setting
						? Boolean(settings[service.setting])
						: true;
					const isGenerateDisabled =
						!canGenerate ||
						service.available === false ||
						service.enabled === false ||
						!settingEnabled ||
						serviceState === 'processing' ||
						Number(service.queueCount || 0) > 0 ||
						generatingService === serviceKey;
					const queueMessage =
						service.queueCount > 0
							? sprintf(
									/* translators: 1: pending queue count, 2: running queue count. */
									__(
										'%1$d queued item(s), %2$d currently running.',
										'powered-cache',
									),
									Number(service.pendingCount || 0),
									Number(service.runningCount || 0),
								)
							: '';
					const serviceMessage =
						service.unavailableMessage ||
						(service.enabled === false || !settingEnabled
							? service.disabledMessage ||
								__(
									'Enable and save the related setting before regenerating.',
									'powered-cache',
								)
							: queueMessage || service.lastMessage);
					let generateLabel = __('Regenerate', 'powered-cache');

					if (serviceState === 'processing' || Number(service.queueCount || 0) > 0) {
						generateLabel = __('Generating', 'powered-cache');
					} else if (serviceState === 'warning' || service.isStale) {
						generateLabel = __('Retry generation', 'powered-cache');
					}

					return (
						<div
							className={`pc-settings-service-card pc-settings-service-card--${serviceState}`}
							key={serviceKey}
						>
							<div className="pc-settings-service-card__title">
								<strong>{service.label || labelFromKey(serviceKey)}</strong>
								<span
									className={`pc-settings-status-pill pc-settings-status-pill--${serviceState}`}
								>
									{stateLabel[serviceState] || stateLabel.idle}
								</span>
							</div>
							<dl>
								<div>
									<dt>{__('Last run', 'powered-cache')}</dt>
									<dd>{formatTimestamp(service.lastRun)}</dd>
								</div>
								<div>
									<dt>{__('Successful runs', 'powered-cache')}</dt>
									<dd>{metricValue(service.successCount, 0)}</dd>
								</div>
								<div>
									<dt>{__('Errors', 'powered-cache')}</dt>
									<dd>{metricValue(service.errorCount, 0)}</dd>
								</div>
								<div>
									<dt>{__('Queued', 'powered-cache')}</dt>
									<dd>{queueMetricValue(service)}</dd>
								</div>
								<div>
									<dt>{__('HTTP', 'powered-cache')}</dt>
									<dd>
										{metricValue(
											Number(service.lastHttpStatus || 0)
												? service.lastHttpStatus
												: '',
										)}
									</dd>
								</div>
								<div>
									<dt>{__('CSS size', 'powered-cache')}</dt>
									<dd>{formatBytes(service.lastOutputBytes)}</dd>
								</div>
							</dl>
							{serviceMessage && (
								<p className="pc-settings-service-card__message">
									{serviceMessage}
								</p>
							)}
							{service.lastUrl && (
								<p className="pc-settings-service-card__url">{service.lastUrl}</p>
							)}
							<div className="pc-settings-service-card__actions">
								<Button
									disabled={isGenerateDisabled}
									isBusy={generatingService === serviceKey}
									onClick={(event) => {
										event.preventDefault();

										if (!isGenerateDisabled) {
											onGenerate(serviceKey, service);
										}
									}}
									type="button"
									variant="secondary"
								>
									{generateLabel}
								</Button>
							</div>
						</div>
					);
				})}
			</div>
		</section>
	);
};

const validationIssues = (validation) =>
	validation && Array.isArray(validation.issues) ? validation.issues : [];

const validationIssueCount = (validation) => validationIssues(validation).length;

const issuesByKey = (validation) =>
	validationIssues(validation).reduce((groups, issue) => {
		if (!groups[issue.key]) {
			groups[issue.key] = [];
		}

		groups[issue.key].push(issue);

		return groups;
	}, {});

const ValidationIssueList = ({ issues = [] }) => {
	if (!issues.length) {
		return null;
	}

	return (
		<div className="pc-settings-field__issues">
			{issues.map((issue) => (
				<p
					className={`pc-settings-field__issue pc-settings-field__issue--${issue.severity}`}
					key={`${issue.key}-${issue.code}`}
				>
					{issue.message}
				</p>
			))}
		</div>
	);
};

const ValidationSummary = ({ fields = {}, onSelectSection = () => {}, validation }) => {
	const totalIssues = validationIssueCount(validation);

	if (!totalIssues) {
		return null;
	}

	const issues = validationIssues(validation);
	const visibleIssues = issues.slice(0, 4);
	const remainingIssues = totalIssues - visibleIssues.length;
	const counts = validation.counts || {};
	const errors = counts.error || 0;
	const warnings = counts.warning || 0;
	let status = 'info';

	if (errors) {
		status = 'error';
	} else if (warnings) {
		status = 'warning';
	}

	const message = errors
		? sprintf(
				/* translators: %d: number of settings. */
				__('%d settings need attention before they can be used reliably.', 'powered-cache'),
				errors,
			)
		: sprintf(
				/* translators: %d: number of settings. */
				__('%d settings have compatibility notes.', 'powered-cache'),
				totalIssues,
			);

	return (
		<div className="pc-settings-validation-summary">
			<Notice status={status} isDismissible={false}>
				<strong>{__('Settings check', 'powered-cache')}</strong>
				<span>{message}</span>
				<ul className="pc-settings-validation-summary__issues">
					{visibleIssues.map((issue) => {
						const field = fields[issue.key] || {};
						const label = field.label || labelFromKey(issue.key || issue.code);

						return (
							<li
								className={`pc-settings-validation-summary__issue pc-settings-validation-summary__issue--${issue.severity}`}
								key={`${issue.key}-${issue.code}`}
							>
								{field.section ? (
									<button
										className="pc-settings-validation-summary__action"
										onClick={() => onSelectSection(field.section)}
										type="button"
									>
										{label}
									</button>
								) : (
									<strong>{label}</strong>
								)}
								<span>{issue.message}</span>
							</li>
						);
					})}
					{!!remainingIssues && (
						<li className="pc-settings-validation-summary__more">
							{sprintf(
								/* translators: %d: number of settings. */
								__(
									'%d more checks are shown next to their settings.',
									'powered-cache',
								),
								remainingIssues,
							)}
						</li>
					)}
				</ul>
			</Notice>
		</div>
	);
};

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
					options={selectOptions(field)}
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
					placeholder={field.placeholder || ''}
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

const LockedField = ({ field, issues = [] }) => (
	<div className="pc-settings-field pc-settings-field--locked" aria-label={field.label}>
		<div className="pc-settings-field__main">
			<div className="pc-settings-field__heading">
				<span className="pc-settings-field__label">{field.label}</span>
				<span className="pc-settings-badge">{__('Premium', 'powered-cache')}</span>
			</div>
			<FieldDescription field={field} />
			{field.upgrade && field.upgrade.description && (
				<p className="pc-settings-field__upgrade">{field.upgrade.description}</p>
			)}
			<ValidationIssueList issues={issues} />
		</div>
		<div className="pc-settings-field__control">
			<LockedControlPreview field={field} />
		</div>
	</div>
);

const CdnHostnamesControl = ({ disabled, field, settings, onChange }) => {
	const zoneKey = field.zone_key || 'cdn_zone';
	const hostnames = asArray(settings[field.key]);
	const zones = asArray(settings[zoneKey]);
	const zoneOptions = Object.entries(
		field.zone_options || {
			all: __('All files', 'powered-cache'),
			image: __('Images', 'powered-cache'),
			js: __('JavaScript', 'powered-cache'),
			css: __('CSS', 'powered-cache'),
		},
	);
	const rowCount = Math.max(hostnames.length, zones.length, 1);
	const rows = [];

	for (let index = 0; index < rowCount; index++) {
		rows.push({
			id: `${field.key}-${index}`,
			hostname: hostnames[index] || '',
			zone: zones[index] || 'all',
		});
	}

	const updateRows = (nextRows) => {
		const normalizedRows = nextRows.length ? nextRows : [{ hostname: '', zone: 'all' }];

		onChange(
			field.key,
			normalizedRows.map((row) => row.hostname),
		);
		onChange(
			zoneKey,
			normalizedRows.map((row) => row.zone || 'all'),
		);
	};

	const updateRow = (index, key, nextValue) => {
		updateRows(
			rows.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: nextValue } : row)),
		);
	};

	return (
		<div className="pc-settings-cdn-routes">
			<div className="pc-settings-cdn-routes__header" aria-hidden="true">
				<span>{__('Hostname', 'powered-cache')}</span>
				<span>{__('Applies to', 'powered-cache')}</span>
			</div>
			<div className="pc-settings-cdn-routes__rows">
				{rows.map((row, index) => (
					<div className="pc-settings-cdn-route" key={row.id}>
						<input
							aria-label={sprintf(__('CDN hostname %d', 'powered-cache'), index + 1)}
							className="components-text-control__input pc-settings-cdn-route__hostname"
							disabled={disabled}
							onChange={(event) => {
								updateRow(index, 'hostname', event.target.value);
							}}
							placeholder="cdn.example.com"
							type="text"
							value={row.hostname}
						/>
						<select
							aria-label={sprintf(__('CDN scope %d', 'powered-cache'), index + 1)}
							className="components-select-control__input pc-settings-cdn-route__zone"
							disabled={disabled}
							onChange={(event) => {
								updateRow(index, 'zone', event.target.value);
							}}
							value={row.zone}
						>
							{zoneOptions.map(([value, label]) => (
								<option key={value} value={value}>
									{label}
								</option>
							))}
						</select>
						<Button
							disabled={disabled || rows.length < 2}
							isDestructive
							onClick={() => {
								updateRows([...rows.slice(0, index), ...rows.slice(index + 1)]);
							}}
							variant="secondary"
						>
							{__('Remove', 'powered-cache')}
						</Button>
					</div>
				))}
			</div>
			<Button
				disabled={disabled}
				onClick={() => {
					updateRows([...rows, { hostname: '', zone: 'all' }]);
				}}
				variant="secondary"
			>
				{__('Add Hostname', 'powered-cache')}
			</Button>
		</div>
	);
};

const SettingsField = ({ field, issues = [], settings, onChange }) => {
	if (field.control === 'hidden') {
		return null;
	}

	if (!fieldVisible(field, settings)) {
		return null;
	}

	const isLocked = !!field.locked || (field.premium && !appConfig.isPremium);
	const isDependencyMet = (field.dependencies || []).every(
		(dependency) => !!settings[dependency],
	);
	const value = settings[field.key];
	const fieldId = `powered-cache-setting-${field.key}`;
	const descriptionId = `${fieldId}-description`;

	if (isLocked) {
		return <LockedField field={field} issues={issues} />;
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
					options={selectOptions(field)}
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
					placeholder={field.placeholder || ''}
					value={displayValue(value, field)}
				/>
			);
			break;
		case 'duration': {
			const duration = getDurationParts(value);
			const amountId = `${fieldId}-amount`;
			const unitId = `${fieldId}-unit`;

			control = (
				<div className="pc-settings-duration-control">
					<div className="pc-settings-duration-control__field">
						<label className="pc-settings-duration-control__label" htmlFor={amountId}>
							{field.label}
						</label>
						<input
							{...controlProps}
							aria-describedby={descriptionId}
							className="pc-settings-duration-control__input"
							id={amountId}
							min="0"
							onChange={(event) => {
								updateValue(durationToMinutes(event.target.value, duration.unit));
							}}
							type="number"
							value={String(duration.amount)}
						/>
					</div>
					<div className="pc-settings-duration-control__field">
						<label className="pc-settings-duration-control__label" htmlFor={unitId}>
							{__('Unit', 'powered-cache')}
						</label>
						<select
							{...controlProps}
							className="pc-settings-duration-control__input"
							id={unitId}
							onChange={(event) => {
								updateValue(durationToMinutes(duration.amount, event.target.value));
							}}
							value={duration.unit}
						>
							{durationUnits.map((durationUnit) => (
								<option key={durationUnit.value} value={durationUnit.value}>
									{durationUnit.label}
								</option>
							))}
						</select>
					</div>
				</div>
			);
			break;
		}
		case 'cdn_zones':
			control = (
				<CdnHostnamesControl
					disabled={!isDependencyMet}
					field={field}
					onChange={onChange}
					settings={settings}
				/>
			);
			break;
		case 'number':
			control = (
				<TextControl
					{...controlProps}
					aria-describedby={descriptionId}
					label={field.label}
					max={undefined === field.max ? undefined : String(field.max)}
					min={undefined === field.min ? undefined : String(field.min)}
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
				<FieldDescription field={field} id={descriptionId} />
				{!isDependencyMet && (
					<p className="pc-settings-field__dependency">
						{__('Enable the parent setting to edit this option.', 'powered-cache')}
					</p>
				)}
				<ValidationIssueList issues={issues} />
			</div>
			<div className="pc-settings-field__control">{control}</div>
		</div>
	);
};

const SettingsGroup = ({ groupName, fields, issuesBySetting, settings, onChange }) => (
	<div className="pc-settings-group">
		<h3>{groupName}</h3>
		<div className="pc-settings-group__body">
			{fields.map((field) => (
				<SettingsField
					field={field}
					issues={issuesBySetting[field.key] || []}
					key={field.key}
					onChange={onChange}
					settings={settings}
				/>
			))}
		</div>
	</div>
);

const SettingsSection = ({
	sectionKey,
	section,
	fields,
	issuesBySetting = {},
	settings,
	onChange,
}) => {
	const visibleFields = fields.filter(
		(field) => field.control !== 'hidden' && fieldVisible(field, settings),
	);
	const groups = visibleFields.reduce((fieldGroups, field) => {
		const groupName = field.group || section.label;

		if (!fieldGroups[groupName]) {
			fieldGroups[groupName] = [];
		}

		fieldGroups[groupName].push(field);

		return fieldGroups;
	}, {});

	const premiumCount = visibleFields.filter((field) => field.premium).length;
	const issueCount = visibleFields.reduce(
		(count, field) => count + (issuesBySetting[field.key] || []).length,
		0,
	);

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
						{visibleFields.length} {__('settings', 'powered-cache')}
					</span>
					{!!premiumCount && (
						<span>
							{premiumCount} {__('Premium', 'powered-cache')}
						</span>
					)}
					{!!issueCount && (
						<span>
							{issueCount} {__('checks', 'powered-cache')}
						</span>
					)}
				</div>
			</div>
			<div className="pc-settings-section__body">
				{Object.entries(groups).map(([groupName, groupFields]) => (
					<SettingsGroup
						fields={groupFields}
						groupName={groupName}
						issuesBySetting={issuesBySetting}
						key={groupName}
						onChange={onChange}
						settings={settings}
					/>
				))}
			</div>
		</section>
	);
};

const LicenseSection = ({ section, premiumInfo }) => {
	const licenseForm = premiumInfo.licenseForm || {};
	const actionValue = licenseForm.licenseActive ? 'deactivate_license' : 'activate_license';

	return (
		<section id="pc-settings-section-license" className="pc-settings-section">
			<div className="pc-settings-section__header">
				<div>
					<h2>{section.label || __('License', 'powered-cache')}</h2>
					<p>
						{section.description ||
							__('Manage Premium license activation for this site.', 'powered-cache')}
					</p>
				</div>
				<div
					className="pc-settings-section__meta"
					aria-label={__('License summary', 'powered-cache')}
				>
					<span>
						{licenseForm.licenseActive
							? __('Active', 'powered-cache')
							: __('Inactive', 'powered-cache')}
					</span>
				</div>
			</div>
			<div className="pc-settings-section__body">
				<div className="pc-settings-group">
					<h3>{__('Activation', 'powered-cache')}</h3>
					<div className="pc-settings-group__body">
						<form
							action={licenseForm.actionUrl || window.location.href}
							className="pc-settings-license-form"
							method="post"
						>
							<input
								name="powered_cache_settings_nonce"
								type="hidden"
								value={licenseForm.nonce || ''}
							/>
							<div className="pc-settings-field">
								<div className="pc-settings-field__main">
									<span className="pc-settings-field__label">
										{__('License Key', 'powered-cache')}
									</span>
									<p
										className="pc-settings-field__description"
										id="powered-cache-license-description"
									>
										{licenseForm.message ||
											premiumInfo.licenseMessage ||
											__(
												'Enter your Premium license key to receive updates and unlock licensed services.',
												'powered-cache',
											)}
									</p>
								</div>
								<div className="pc-settings-field__control">
									<TextControl
										aria-describedby="powered-cache-license-description"
										autoComplete="off"
										defaultValue={licenseForm.key || ''}
										label={__('License Key', 'powered-cache')}
										name="license_key"
									/>
									<Button
										name="powered_cache_form_action"
										type="submit"
										value={actionValue}
										variant={
											licenseForm.licenseActive ? 'secondary' : 'primary'
										}
									>
										{licenseForm.licenseActive
											? __('Deactivate License', 'powered-cache')
											: __('Activate License', 'powered-cache')}
									</Button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</section>
	);
};

const SettingsToolsActions = () => {
	const formUrl = appConfig.settingsFormUrl || window.location.href;
	const nonce = appConfig.settingsNonce || '';
	const confirmReset = (event) => {
		if (
			// eslint-disable-next-line no-alert
			!window.confirm(
				__(
					'Reset Powered Cache settings to their defaults? This cannot be undone.',
					'powered-cache',
				),
			)
		) {
			event.preventDefault();
		}
	};

	return (
		<div
			className="pc-settings-tools-actions"
			aria-label={__('Settings tools', 'powered-cache')}
		>
			<div className="pc-settings-tool-card">
				<div>
					<strong>{__('Export Settings', 'powered-cache')}</strong>
					<p>
						{__(
							'Download a portable JSON backup without sensitive credentials.',
							'powered-cache',
						)}
					</p>
				</div>
				<form action={formUrl} method="post">
					<input name="powered_cache_settings_nonce" type="hidden" value={nonce} />
					<Button
						name="powered_cache_form_action"
						type="submit"
						value="export_settings"
						variant="secondary"
					>
						{__('Export JSON', 'powered-cache')}
					</Button>
				</form>
			</div>

			<div className="pc-settings-tool-card">
				<div>
					<strong>{__('Import Settings', 'powered-cache')}</strong>
					<p>
						{__(
							'Restore a Powered Cache JSON export while preserving legacy extension data.',
							'powered-cache',
						)}
					</p>
				</div>
				<form action={formUrl} encType="multipart/form-data" method="post">
					<input name="powered_cache_settings_nonce" type="hidden" value={nonce} />
					<input accept="application/json,.json" name="import_file" type="file" />
					<Button
						name="powered_cache_form_action"
						type="submit"
						value="import_settings"
						variant="secondary"
					>
						{__('Import JSON', 'powered-cache')}
					</Button>
				</form>
			</div>

			<div className="pc-settings-tool-card">
				<div>
					<strong>{__('Purge Cache', 'powered-cache')}</strong>
					<p>
						{__(
							'Clear generated cache files and object cache entries.',
							'powered-cache',
						)}
					</p>
				</div>
				<Button href={appConfig.purgeAllUrl || '#'} variant="secondary">
					{__('Purge All Cache', 'powered-cache')}
				</Button>
			</div>

			<div className="pc-settings-tool-card pc-settings-tool-card--danger">
				<div>
					<strong>{__('Reset Settings', 'powered-cache')}</strong>
					<p>
						{__(
							'Restore default settings while keeping the plugin installed.',
							'powered-cache',
						)}
					</p>
				</div>
				<form action={formUrl} method="post" onSubmit={confirmReset}>
					<input name="powered_cache_settings_nonce" type="hidden" value={nonce} />
					<Button
						isDestructive
						name="powered_cache_form_action"
						type="submit"
						value="reset_settings"
						variant="secondary"
					>
						{__('Reset Settings', 'powered-cache')}
					</Button>
				</form>
			</div>
		</div>
	);
};

const SettingsApp = () => {
	const [manifest, setManifest] = useState(null);
	const [settings, setSettings] = useState({});
	const [initialSettings, setInitialSettings] = useState({});
	const [validation, setValidation] = useState(null);
	const [systemStatus, setSystemStatus] = useState(null);
	const [activeSection, setActiveSection] = useState('');
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [objectCacheNotice, setObjectCacheNotice] = useState(null);
	const [premiumInfo, setPremiumInfo] = useState(appConfig.premium || {});
	const [generatingCssService, setGeneratingCssService] = useState('');

	useEffect(() => {
		Promise.all([
			apiFetch({ path: route('/settings/manifest') }),
			apiFetch({ path: route('/settings') }),
		])
			.then(([manifestResponse, stateResponse]) => {
				setManifest(manifestResponse);
				setSettings(stateResponse.settings || {});
				setInitialSettings(stateResponse.settings || {});
				setValidation(stateResponse.validation || null);
				setSystemStatus(stateResponse.system_status || null);
				setActiveSection(resolveActiveSection(manifestResponse));

				if (
					requestedSection() &&
					!orderedSectionKeys(manifestResponse).includes(requestedSection())
				) {
					syncSectionUrl(resolveActiveSection(manifestResponse), true);
				}
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

	useEffect(() => {
		if (!manifest) {
			return undefined;
		}

		const handlePopState = () => {
			setActiveSection(resolveActiveSection(manifest));
		};

		window.addEventListener('popstate', handlePopState);

		return () => {
			window.removeEventListener('popstate', handlePopState);
		};
	}, [manifest]);

	useEffect(() => {
		if (!manifest) {
			return undefined;
		}

		const adminMenuLinks = Array.from(
			document.querySelectorAll('#adminmenu a[href*="page=powered-cache"]'),
		);
		const handleAdminMenuClick = (event) => {
			const link = event.currentTarget;
			const sectionKey = resolveSectionFromUrl(manifest, link.href);

			if (
				!sectionKey ||
				event.defaultPrevented ||
				event.button !== 0 ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}

			event.preventDefault();
			setActiveSection(sectionKey);
			syncSectionUrl(sectionKey);
		};

		adminMenuLinks.forEach((link) => {
			link.addEventListener('click', handleAdminMenuClick);
		});

		return () => {
			adminMenuLinks.forEach((link) => {
				link.removeEventListener('click', handleAdminMenuClick);
			});
		};
	}, [manifest]);

	useEffect(() => {
		syncAdminMenuSection(activeSection, manifest);
	}, [activeSection, manifest]);

	useEffect(() => {
		if (!objectCacheNeedsAlloptionsCheck(settings.object_cache)) {
			setObjectCacheNotice(null);

			return undefined;
		}

		const abortController = new AbortController();
		const formData = new window.FormData();

		formData.append('action', 'powered_cache_check_alloptions');
		formData.append('nonce', appConfig.settingsNonce || '');

		window
			.fetch(appConfig.ajaxUrl || window.ajaxurl || 'admin-ajax.php', {
				body: formData,
				credentials: 'same-origin',
				method: 'POST',
				signal: abortController.signal,
			})
			.then((response) => response.json())
			.then((response) => {
				if (abortController.signal.aborted) {
					return;
				}

				if (!response.success || !response.data || response.data.status === 'good') {
					setObjectCacheNotice(null);

					return;
				}

				setObjectCacheNotice({
					code: `alloptions_${response.data.status}`,
					key: 'object_cache',
					message:
						textFromHtml(response.data.message) ||
						__(
							'Autoloaded options could affect persistent object cache performance.',
							'powered-cache',
						),
					severity: response.data.status === 'critical' ? 'error' : 'warning',
				});
			})
			.catch((error) => {
				if (error.name === 'AbortError') {
					return;
				}

				setObjectCacheNotice({
					code: 'alloptions_check_failed',
					key: 'object_cache',
					message: __(
						'Autoloaded options could not be checked before enabling this object cache backend.',
						'powered-cache',
					),
					severity: 'warning',
				});
			});

		return () => {
			abortController.abort();
		};
	}, [settings.object_cache]);

	useEffect(() => {
		const cssOptimization = premiumInfo.cssOptimization || {};
		const services = Object.values(cssOptimization.services || {});
		const hasProcessingService = services.some((service) => service.state === 'processing');

		if (!cssOptimization.statusPath || !hasProcessingService) {
			return undefined;
		}

		const abortController = new AbortController();
		const intervalId = window.setInterval(() => {
			apiFetch({
				path: route(cssOptimization.statusPath),
				signal: abortController.signal,
			})
				.then((response) => {
					if (response && response.cssOptimization) {
						setPremiumInfo((currentPremiumInfo) => ({
							...currentPremiumInfo,
							cssOptimization: response.cssOptimization,
						}));
					}
				})
				.catch(() => {});
		}, 10000);

		return () => {
			abortController.abort();
			window.clearInterval(intervalId);
		};
	}, [premiumInfo.cssOptimization]);

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
		setValidation(null);
	};

	const updateActiveSection = (sectionKey) => {
		setActiveSection(sectionKey);
		syncSectionUrl(sectionKey);
	};

	const refreshCssOptimizationStatus = () => {
		const cssOptimization = premiumInfo.cssOptimization || {};

		if (!cssOptimization.statusPath) {
			return Promise.resolve();
		}

		return apiFetch({
			path: route(cssOptimization.statusPath),
		})
			.then((response) => {
				if (response && response.cssOptimization) {
					setPremiumInfo((currentPremiumInfo) => ({
						...currentPremiumInfo,
						cssOptimization: response.cssOptimization,
					}));
				}
			})
			.catch(() => {});
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
				setValidation(response.validation || null);
				setSystemStatus(response.system_status || null);
				setNotice({
					status: 'success',
					message: __('Settings saved.', 'powered-cache'),
				});

				refreshCssOptimizationStatus();
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

	const generateCssOptimization = (serviceKey, service = {}) => {
		const cssOptimization = premiumInfo.cssOptimization || {};

		if (!cssOptimization.generatePath) {
			return;
		}

		setGeneratingCssService(serviceKey);
		setNotice(null);

		apiFetch({
			path: route(cssOptimization.generatePath),
			method: 'POST',
			data: {
				service: serviceKey,
			},
		})
			.then((response) => {
				if (response && response.cssOptimization) {
					setPremiumInfo((currentPremiumInfo) => ({
						...currentPremiumInfo,
						cssOptimization: response.cssOptimization,
					}));
				}

				setNotice({
					status: 'success',
					message:
						(response && response.message) ||
						sprintf(
							/* translators: %s: CSS optimization service label. */
							__('%s generation has started.', 'powered-cache'),
							service.label || labelFromKey(serviceKey),
						),
				});
			})
			.catch((error) => {
				if (error && error.data && error.data.cssOptimization) {
					setPremiumInfo((currentPremiumInfo) => ({
						...currentPremiumInfo,
						cssOptimization: error.data.cssOptimization,
					}));
				}

				setNotice({
					status: 'error',
					message:
						(error && error.message) ||
						sprintf(
							/* translators: %s: CSS optimization service label. */
							__('%s generation could not be started.', 'powered-cache'),
							service.label || labelFromKey(serviceKey),
						),
				});
			})
			.finally(() => {
				setGeneratingCssService('');
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
	const imageDelivery = premiumInfo.imageDelivery || null;
	const cssOptimization = premiumInfo.cssOptimization || null;
	const validationIssuesByKey = issuesByKey(validation);
	const fieldIssuesByKey = {
		...validationIssuesByKey,
		...(objectCacheNotice
			? {
					object_cache: [
						...(validationIssuesByKey.object_cache || []),
						objectCacheNotice,
					],
				}
			: {}),
	};
	const isLicenseSection = activeSection === 'license' && !!premiumInfo.licenseForm;
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
					<div
						className="pc-settings-header__meta"
						aria-label={__('Plan status', 'powered-cache')}
					>
						<span className="pc-settings-status-pill pc-settings-status-pill--plan">
							{appConfig.isPremium
								? __('Premium plan', 'powered-cache')
								: __('Free plan', 'powered-cache')}
						</span>
						<span className="pc-settings-header__license">
							{appConfig.isPremium
								? premiumInfo.licenseMessage ||
									__(
										'Advanced optimization controls are unlocked.',
										'powered-cache',
									)
								: __('Premium controls stay visible in context.', 'powered-cache')}
						</span>
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

			<ValidationSummary
				fields={manifest.fields || {}}
				onSelectSection={updateActiveSection}
				validation={validation}
			/>

			{activeSection === 'cache' && (
				<>
					<div
						className="pc-settings-overview"
						aria-label={__('Settings overview', 'powered-cache')}
					>
						<MetricCard
							description={__(
								'HTML cache delivery for anonymous visits.',
								'powered-cache',
							)}
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
							description={__(
								'Core optimizations currently switched on.',
								'powered-cache',
							)}
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
							tone={
								appConfig.isPremium && !premiumInfo.licenseActive
									? 'warning'
									: 'premium'
							}
							value={
								appConfig.isPremium
									? labelFromKey(premiumInfo.licenseStatus || 'unknown')
									: premiumFields.length
							}
						/>
					</div>
					<SystemStatusPanel
						onSelectSection={updateActiveSection}
						systemStatus={systemStatus}
					/>
				</>
			)}

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

			<div className="pc-settings-layout pc-settings-layout--nav-hidden">
				<nav
					className="pc-settings-nav"
					aria-label={__('Settings sections', 'powered-cache')}
					hidden
				>
					{sections.map(([sectionKey, section]) => (
						<button
							aria-current={sectionKey === activeSection ? 'page' : undefined}
							className={sectionKey === activeSection ? 'is-active' : ''}
							key={sectionKey}
							onClick={() => updateActiveSection(sectionKey)}
							type="button"
						>
							{section.label}
						</button>
					))}
				</nav>

				<div className="pc-settings-content">
					{appConfig.isPremium && imageDelivery && activeSection === 'media' && (
						<ImageDeliveryPanel imageDelivery={imageDelivery} />
					)}
					{appConfig.isPremium &&
						cssOptimization &&
						activeSection === 'file_optimization' && (
							<CssOptimizationPanel
								cssOptimization={cssOptimization}
								docsUrl={appConfig.docsUrl || '#'}
								generatingService={generatingCssService}
								onGenerate={generateCssOptimization}
								settings={settings}
							/>
						)}
					{isLicenseSection ? (
						<LicenseSection section={activeSectionData} premiumInfo={premiumInfo} />
					) : (
						<>
							<SettingsSection
								fields={activeFields}
								issuesBySetting={fieldIssuesByKey}
								onChange={updateSetting}
								section={activeSectionData}
								sectionKey={activeSection}
								settings={settings}
							/>
							{activeSection === 'misc' && <SettingsToolsActions />}
						</>
					)}
				</div>
			</div>

			{!isLicenseSection && (
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
			)}
		</div>
	);
};

const mountNode = document.getElementById('powered-cache-settings-app');

if (mountNode) {
	if (createRoot) {
		createRoot(mountNode).render(<SettingsApp />);
	} else {
		render(<SettingsApp />, mountNode);
	}
}
