# Quality and E2E Checks

Powered Cache 4.0 keeps the settings app covered by unit checks, linting, build verification, and a small browser smoke test.

## Development cadence

Do not run every check after every small edit. Use the smallest check that covers the surface you changed, then run the fuller suite before a commit, pull request, or release candidate.

- PHP-only change: run the matching PHPUnit file first, then PHPCS for the touched files.
- Settings schema, validation, or compatibility registry change: run the matching PHPUnit test file and the settings app smoke test when UI behavior can be affected.
- JavaScript or CSS change: run `npm run lint-js` and `npm run build`.
- Release/PR readiness: run the full PHP suite, PHPCS, JS lint, build, and the relevant E2E profile.

## Local PHP checks

Targeted examples:

```bash
/Users/mustafauysal/Library/Application\ Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php vendor/bin/phpunit tests/phpunit/test-tools/SettingsValidator_Tests.php
/Users/mustafauysal/Library/Application\ Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php vendor/bin/phpcs includes/classes/SettingsValidator.php tests/phpunit/test-tools/SettingsValidator_Tests.php -s
```

Full local PHP checks:

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
PC_E2E_PROFILE=core
PC_E2E_USER=admin
PC_E2E_PASSWORD=password
PC_E2E_HEADLESS=0
PC_E2E_SCREENSHOT_DIR=/tmp
```

Profiles:

- `core`: Free-safe settings checks only. This is the default and does not require Premium license UI.
- `premium`: Requires Premium/license settings to be available and fails when they are missing.
- `auto`: Runs Premium/license checks only when the section is present.

The smoke test verifies that the settings app renders key sections, keeps WordPress admin submenu highlighting in sync, keeps Cache Lifespan controls visually aligned, and avoids legacy CSS generation links. Premium/license URL checks run only in the `premium` or `auto` profiles.

For GitHub-hosted CI, prefer an explicit fixture URL such as:

```bash
PC_E2E_BASE_URL=http://localhost:8889
```

The script defaults to `http://localhost:8889` when `CI=true`, and to `https://plugindevel.test` for local development.

## CI scope

The default CI workflow runs PHP unit tests and PHPCS across supported PHP versions. It also runs the frontend lint/build checks and validates the settings smoke runner entrypoint. A full browser E2E CI job should be added when the workflow has a stable WordPress fixture with an authenticated admin user. That job should start with `PC_E2E_PROFILE=core` for the Free plugin and use `PC_E2E_PROFILE=premium` only in a Premium-enabled fixture.
