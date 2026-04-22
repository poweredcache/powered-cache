<?php
if ( ! defined( 'PROJECT' ) ) {
	define( 'PROJECT', __DIR__ . '/../includes/' );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/powered-cache-wp-content' );
}

if ( ! defined( 'POWERED_CACHE_DIR' ) ) {
	define( 'POWERED_CACHE_DIR', __DIR__ . '/' );
}

// Place any additional bootstrapping requirements here for PHP Unit.
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', 'lang_dir' );
}
if ( ! defined( 'POWERED_CACHE_PATH' ) ) {
	define( 'POWERED_CACHE_PATH', 'path' );
}
if ( ! defined( 'POWERED_CACHE_URL' ) ) {
	define( 'POWERED_CACHE_URL', 'https://example.test/wp-content/plugins/powered-cache/' );
}
if ( ! defined( 'POWERED_CACHE_VERSION' ) ) {
	define( 'POWERED_CACHE_VERSION', 'test-version' );
}
if ( ! defined( 'POWERED_CACHE_DROPIN_DIR' ) ) {
	define( 'POWERED_CACHE_DROPIN_DIR', dirname( __DIR__ ) . '/includes/dropins/' );
}
if ( ! defined( 'POWERED_CACHE_IS_NETWORK' ) ) {
	define( 'POWERED_CACHE_IS_NETWORK', false );
}

if ( ! file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	throw new PHPUnit_Framework_Exception(
		'ERROR' . PHP_EOL . PHP_EOL .
		'You must use Composer to install the test suite\'s dependencies!' . PHP_EOL
	);
}

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../tests/phpunit/test-tools/TestCase.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
WP_Mock::tearDown();
