<?php
/**
 * Plugin Name:       Powered Cache
 * Plugin URI:        https://poweredcache.com
 * Description:       Powered Cache is the most powerful caching and performance suite for WordPress, designed to easily improve your PageSpeed and Web Vitals Score.
 * Version:           3.0.5
 * Requires at least: 5.7
 * Requires PHP:      7.2.5
 * Author:            Powered Cache
 * Author URI:        https://poweredcache.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       powered-cache
 * Domain Path:       /languages
 *
 * @package           PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Extensions\Extensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Useful global constants.
define( 'POWERED_CACHE_VERSION', '3.0.5' );
define( 'POWERED_CACHE_DB_VERSION', '3.0' );
define( 'POWERED_CACHE_PLUGIN_FILE', __FILE__ );
define( 'POWERED_CACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'POWERED_CACHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'POWERED_CACHE_INC', POWERED_CACHE_PATH . 'includes/' );
define( 'POWERED_CACHE_DROPIN_DIR', POWERED_CACHE_INC . 'dropins/' );
define( 'POWERED_CACHE_COMPAT_DIR', POWERED_CACHE_INC . 'compat/' );
define( 'POWERED_CACHE_PACKAGE_DIR', POWERED_CACHE_INC . 'package/' );

if ( ! defined( 'POWERED_CACHE_CACHE_DIR' ) ) {
	define( 'POWERED_CACHE_CACHE_DIR', WP_CONTENT_DIR . '/cache/' );
}

if ( ! defined( 'POWERED_CACHE_FO_CACHE_DIR' ) ) {
	define( 'POWERED_CACHE_FO_CACHE_DIR', POWERED_CACHE_CACHE_DIR . 'min/' );
}

// Require Composer autoloader if it exists.
if ( file_exists( POWERED_CACHE_PATH . 'vendor/autoload.php' ) ) {
	require_once POWERED_CACHE_PATH . 'vendor/autoload.php';
}

// load packages
require_once POWERED_CACHE_PACKAGE_DIR . 'deliciousbrains/wp-background-processing/classes/wp-async-request.php';
require_once POWERED_CACHE_PACKAGE_DIR . 'deliciousbrains/wp-background-processing/classes/wp-background-process.php';

/**
 * PSR-4-ish autoloading
 *
 * @since 2.0
 */
spl_autoload_register(
	function ( $class ) {
		// project-specific namespace prefix.
		$prefix = 'PoweredCache\\';

		// base directory for the namespace prefix.
		$base_dir = __DIR__ . '/includes/classes/';

		// does the class use the namespace prefix?
		$len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Include files.
require_once POWERED_CACHE_INC . 'constants.php';
require_once POWERED_CACHE_INC . 'utils.php';
require_once POWERED_CACHE_INC . 'core.php';
require_once POWERED_CACHE_INC . 'admin/dashboard.php';
require_once POWERED_CACHE_INC . 'admin/notices.php';
require_once POWERED_CACHE_COMPAT_DIR . 'loader.php';

$network_activated = Utils\is_network_wide( POWERED_CACHE_PLUGIN_FILE );
if ( ! defined( 'POWERED_CACHE_IS_NETWORK' ) ) {
	define( 'POWERED_CACHE_IS_NETWORK', $network_activated );
}

if ( Utils\bypass_request() ) {
	return;
}


// Bootstrap.
Core\setup();
Admin\Dashboard\setup();
Admin\Notices\setup();
Install::factory();
AdvancedCache::factory();
ObjectCache::factory();
CDN::factory();
Cron::factory();
Preloader::factory();
FileOptimizer::factory();
LazyLoad::factory();
Preloader::factory();
MetaBox::factory();
Extensions::factory();

// Activation/Deactivation.
register_activation_hook( __FILE__, '\PoweredCache\Core\activate' );
register_deactivation_hook( __FILE__, '\PoweredCache\Core\deactivate' );
