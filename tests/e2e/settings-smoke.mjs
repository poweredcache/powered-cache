#!/usr/bin/env node

/**
 * Smoke-test the Powered Cache settings app in a real browser.
 *
 * This runner intentionally keeps Playwright optional so the plugin can keep a
 * light default install while CI/local machines opt into browser testing.
 */

const help = `
Powered Cache settings smoke test

Usage:
  npm run e2e:settings

Environment:
  PC_E2E_BASE_URL       WordPress base URL. Default: https://plugindevel.test locally, http://localhost:8889 in CI.
  PC_E2E_PROFILE        Test profile: core, premium, or auto. Default: core
  PC_E2E_USER           WordPress username when the browser is not logged in.
  PC_E2E_PASSWORD       WordPress password when the browser is not logged in.
  PC_E2E_HEADLESS       Set to 0 to show the browser. Default: 1
  PC_E2E_SCREENSHOT_DIR Optional directory for failure screenshots.

Install Playwright when needed:
  npm install --save-dev @playwright/test
  npx playwright install chromium
`;

if (process.argv.includes('--help') || process.argv.includes('-h')) {
	console.log(help.trim());
	process.exit(0);
}

const assert = (condition, message) => {
	if (!condition) {
		throw new Error(message);
	}
};

const defaultBaseUrl = process.env.CI ? 'http://localhost:8889' : 'https://plugindevel.test';
const baseUrl = (process.env.PC_E2E_BASE_URL || defaultBaseUrl).replace(/\/$/, '');
const profile = process.env.PC_E2E_PROFILE || 'core';
const username = process.env.PC_E2E_USER || '';
const password = process.env.PC_E2E_PASSWORD || '';
const headless = process.env.PC_E2E_HEADLESS !== '0';
const screenshotDir = process.env.PC_E2E_SCREENSHOT_DIR || '';

assert(
	['core', 'premium', 'auto'].includes(profile),
	'PC_E2E_PROFILE must be one of: core, premium, auto.',
);

const loadPlaywright = async () => {
	try {
		return await import('@playwright/test');
	} catch (error) {
		if (error && error.code !== 'ERR_MODULE_NOT_FOUND') {
			throw error;
		}
	}

	try {
		return await import('playwright');
	} catch (error) {
		if (error && error.code !== 'ERR_MODULE_NOT_FOUND') {
			throw error;
		}

		throw new Error(
			'Playwright is not installed. Run `npm install --save-dev @playwright/test` and `npx playwright install chromium` first.',
		);
	}
};

const adminUrl = (section) =>
	`${baseUrl}/wp-admin/admin.php?page=powered-cache&section=${encodeURIComponent(
		section,
	)}&e2e=${Date.now()}`;

const loginIfNeeded = async (page) => {
	await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded' });

	if (!page.url().includes('wp-login.php')) {
		return;
	}

	assert(
		username && password,
		'WordPress login is required. Set PC_E2E_USER and PC_E2E_PASSWORD.',
	);

	await page.locator('#user_login').fill(username);
	await page.locator('#user_pass').fill(password);
	await Promise.all([
		page.waitForLoadState('domcontentloaded'),
		page.locator('#wp-submit').click(),
	]);

	assert(!page.url().includes('wp-login.php'), 'WordPress login failed.');
};

const openSection = async (page, section) => {
	await page.goto(adminUrl(section), { waitUntil: 'domcontentloaded' });
	await page.locator('#powered-cache-settings-app .pc-settings-shell').waitFor();
	await page.locator(`#pc-settings-section-${section}`).waitFor();
};

const sectionLinkExists = async (page, section) =>
	page.evaluate(
		(sectionKey) =>
			Boolean(
				document.querySelector(
					`#toplevel_page_powered-cache a[href*="section=${sectionKey}"]`,
				),
			),
		section,
	);

const activeSubmenuText = async (page) =>
	page.evaluate(() => {
		const current =
			document.querySelector('#toplevel_page_powered-cache .wp-submenu a.current') ||
			document.querySelector('#toplevel_page_powered-cache .wp-submenu li.current a');

		return current ? current.textContent.trim() : '';
	});

const assertCacheLifespanControl = async (page) => {
	const sizes = await page.locator('.pc-settings-duration-control__input').evaluateAll((nodes) =>
		nodes.map((node) => {
			const rect = node.getBoundingClientRect();

			return {
				height: Math.round(rect.height),
				width: Math.round(rect.width),
			};
		}),
	);

	assert(sizes.length >= 2, 'Cache Lifespan control inputs were not found.');
	assert(
		Math.abs(sizes[0].height - sizes[1].height) <= 1,
		`Cache Lifespan input/select heights differ: ${sizes[0].height} vs ${sizes[1].height}.`,
	);
	assert(
		Math.abs(sizes[0].width - sizes[1].width) <= 2,
		`Cache Lifespan input/select widths differ: ${sizes[0].width} vs ${sizes[1].width}.`,
	);
};

const assertNoLegacyCssGenerationLinks = async (page) => {
	const legacyLinks = await page
		.locator('a[href*="admin-post.php?action=powered_cache_generate"]')
		.count();

	assert(legacyLinks === 0, 'CSS generation still exposes legacy admin-post links.');
};

const assertLicenseSection = async (page) => {
	await openSection(page, 'license');

	assert(
		page.url().includes('admin.php?page=powered-cache') && page.url().includes('section=license'),
		`License section URL is not tab-compatible: ${page.url()}`,
	);

	const savebarCount = await page.locator('.pc-settings-savebar').count();
	assert(savebarCount === 0, 'License section should not show the sticky Save Settings bar.');

	const brokenLicenseLinks = await page.locator('a[href$="/powered-cache-license"]').count();
	assert(brokenLicenseLinks === 0, 'A broken powered-cache-license URL is present.');
};

const maybeAssertLicenseSection = async (page) => {
	if ('core' === profile) {
		return;
	}

	const hasLicenseSection = await sectionLinkExists(page, 'license');

	if (!hasLicenseSection && 'auto' === profile) {
		console.log('Skipping Premium license smoke checks; license section is not available.');
		return;
	}

	assert(
		hasLicenseSection,
		'Premium smoke profile requested, but the License section is not available.',
	);

	await assertLicenseSection(page);
};

const run = async () => {
	const { chromium } = await loadPlaywright();
	const browser = await chromium.launch({ headless });
	const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

	try {
		await loginIfNeeded(page);

		await openSection(page, 'cache');
		assert((await activeSubmenuText(page)) === 'Cache', 'Cache submenu item is not active.');
		await assertCacheLifespanControl(page);

		await openSection(page, 'file_optimization');
		assert(
			(await activeSubmenuText(page)) === 'File Optimization',
			'File Optimization submenu item is not active.',
		);
		await assertNoLegacyCssGenerationLinks(page);

		await maybeAssertLicenseSection(page);

		console.log('Powered Cache settings smoke test passed.');
	} catch (error) {
		if (screenshotDir) {
			await page.screenshot({
				fullPage: true,
				path: `${screenshotDir.replace(/\/$/, '')}/powered-cache-settings-smoke-failure.png`,
			});
		}

		throw error;
	} finally {
		await browser.close();
	}
};

run().catch((error) => {
	console.error(error.message);
	process.exit(1);
});
