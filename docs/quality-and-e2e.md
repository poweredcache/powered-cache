# Quality and E2E Checks

Powered Cache 4.0 keeps the settings app covered by unit checks, linting, build verification, and a small browser smoke test.

## Local PHP checks

```bash
/Users/mustafauysal/Library/Application\ Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php vendor/bin/phpunit
/Users/mustafauysal/Library/Application\ Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php vendor/bin/phpcs powered-cache.php uninstall.php ./includes -s
```

## Local frontend checks

```bash
npm run lint-js
npm run build
```

## Settings smoke test

The settings smoke test is intentionally optional. It lets local and CI environments opt into browser testing without forcing Playwright into every development install.

```bash
npm install --save-dev @playwright/test
npx playwright install chromium
npm run e2e:settings
```

Useful environment variables:

```bash
PC_E2E_BASE_URL=https://plugindevel.test
PC_E2E_USER=admin
PC_E2E_PASSWORD=password
PC_E2E_HEADLESS=0
PC_E2E_SCREENSHOT_DIR=/tmp
```

The smoke test verifies that the settings app renders key sections, keeps WordPress admin submenu highlighting in sync, keeps Cache Lifespan controls visually aligned, avoids legacy CSS generation links, and keeps License inside the tab-compatible settings URL.

## CI scope

The default CI workflow runs PHP unit tests and PHPCS across supported PHP versions. It also runs the frontend lint/build checks and validates the settings smoke runner entrypoint. A full browser E2E CI job should be added when the workflow has a stable WordPress fixture with an authenticated admin user.
