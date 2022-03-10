<?php
/**
 * Configurator Class of the plugin
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use function PoweredCache\Utils\can_configure_htaccess;
use function PoweredCache\Utils\can_configure_object_cache;
use function PoweredCache\Utils\get_cache_dir;
use function PoweredCache\Utils\get_object_cache_dropins;
use function PoweredCache\Utils\mobile_browsers;
use function PoweredCache\Utils\mobile_prefixes;
use function PoweredCache\Utils\permalink_structure_has_trailingslash;
use function PoweredCache\Utils\remove_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

/**
 * Class Config
 */
class Config {
	/**
	 * placeholder
	 *
	 * @since 1.0
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return Config
	 * @since 2.0 removed WP_Filesystem_Direct deps
	 * @since 1.1 initialize WP_Filesystem_Direct
	 * @since 1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}


	/**
	 * Setup object-cache.php
	 *
	 * @param string $backend Persistent object cache backend. (memcached, redis etc..)
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function setup_object_cache( $backend = 'off' ) {
		$file = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';

		/**
		 * Since object cache has impact on the entire network
		 * It only allowed by the network admin
		 */
		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return false;
		}

		if ( 'off' === $backend && file_exists( $file ) && false !== strpos( file_get_contents( $file ), 'POWERED_OBJECT_CACHE' ) ) {
			/**
			 * Remove object-cache.php file only when the created file belongs to PoweredCache
			 */
			unlink( $file );

			return true;
		}

		if ( 'off' === $backend ) {
			return true;
		}

		$file_string = $this->object_cache_file_content( $backend );

		if ( ! file_put_contents( $file, $file_string, LOCK_EX ) ) {
			return false;
		}

		return true;
	}

	/**
	 * object-cache.php contents
	 *
	 * @param string $backend Persistent object cache backend
	 *
	 * @return mixed|void
	 * @since 1.0
	 * @see   Powered_Cache_Admin_Helper::object_cache_dropins
	 *
	 * @since 1.1 supports `POWERED_CACHE_OBJECT_CACHE_DROPIN`
	 */
	public function object_cache_file_content( $backend ) {
		$string  = '<?php ' . "\n";
		$string .= "defined( 'ABSPATH' ) || exit;" . PHP_EOL;
		$string .= "define( 'POWERED_OBJECT_CACHE', true );" . PHP_EOL;
		$string .= "if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {" . PHP_EOL;
		$string .= "\t" . "define( 'WP_CACHE_KEY_SALT', DB_NAME );" . PHP_EOL;
		$string .= '}' . PHP_EOL;

		$object_caches = get_object_cache_dropins();

		$string .= 'if ( defined( \'POWERED_CACHE_OBJECT_CACHE_DROPIN\') && @file_exists( POWERED_CACHE_OBJECT_CACHE_DROPIN ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( POWERED_CACHE_OBJECT_CACHE_DROPIN );' . PHP_EOL;
		$string .= '} elseif ( @file_exists( \'' . $object_caches[ $backend ] . '\' ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( \'' . $object_caches[ $backend ] . '\' );' . PHP_EOL;
		$string .= '} else {' . PHP_EOL;
		$string .= "\t" . 'define( \'POWERED_OBJECT_CACHE_HAS_PROBLEM\', true );' . PHP_EOL;
		$string .= '}';

		/**
		 * Filters object-cache.php file contents.
		 *
		 * @hook   powered_cache_object_cache_file_content
		 *
		 * @param  {string} $string The content of the object-cache.php file
		 *
		 * @return {string} New value.
		 *
		 * @since  1.0
		 */
		return apply_filters( 'powered_cache_object_cache_file_content', $string );
	}

	/**
	 * Generate advanced-cache.php and define WP_CACHE
	 *
	 * @param bool $status status of the page caching
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function setup_page_cache( $status ) {

		/**
		 * Forcing multisite settings always true
		 */
		if ( is_multisite() && ! POWERED_CACHE_IS_NETWORK ) {
			$status = true;
		}

		$this->generate_advanced_cache_file();
		$this->define_wp_cache( $status );

		if ( can_configure_htaccess() ) {
			$this->configure_htaccess( $status );
		}

		$this->protect_cache_dir();

		return true;
	}


	/**
	 * Generates advanced-cache.php
	 *
	 * @return bool
	 * @since 1.1 is_multisite control added
	 * @since 1.0
	 */
	public function generate_advanced_cache_file() {
		$file     = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		$settings = \PoweredCache\Utils\get_settings();

		$file_string = '';

		/**
		 * multisite setups should always have `advanced-cache.php` file
		 */
		if ( true === $settings['enable_page_cache'] || is_multisite() ) {
			$file_string = $this->advanced_cache_file_content();
		}

		if ( ! file_put_contents( $file, $file_string ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Prepare advanced-cache.php contents
	 *
	 * @return mixed|void
	 * @since 1.1 supports `POWERED_CACHE_ADVANCED_CACHE_DROPIN`
	 * @since 1.0
	 */
	public function advanced_cache_file_content() {
		$string  = '<?php ' . PHP_EOL;
		$string .= "defined( 'ABSPATH' ) || exit;" . PHP_EOL;
		$string .= "define( 'POWERED_CACHE_PAGE_CACHING', true );" . PHP_EOL . PHP_EOL;
		// lookup order 1) network-wide , 2) subdomain specific (if any) 3) domain specific
		$string .= "\$config_locations[] = WP_CONTENT_DIR . '/pc-config/config-network.php';" . PHP_EOL;
		$string .= "if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {" . PHP_EOL;
		$string .= "\t" . "\$request_uri = explode( '/', ltrim( \$_SERVER['REQUEST_URI'], '/' ) );" . PHP_EOL;
		$string .= "\t" . 'if ( ! empty( $request_uri[0] ) ) {' . PHP_EOL;
		$string .= "\t" . "\t" . "\$config_locations[] = WP_CONTENT_DIR . '/pc-config/config-' . \$_SERVER['HTTP_HOST'] . '-' . \$request_uri[0] . '.php';" . PHP_EOL;
		$string .= "\t" . '}' . PHP_EOL;
		$string .= '}' . PHP_EOL;

		$string .= "\$config_locations[] = WP_CONTENT_DIR . '/pc-config/config-' . \$_SERVER['HTTP_HOST'] . '.php';" . PHP_EOL . PHP_EOL;

		$string .= 'foreach ( $config_locations as $config_file ) {' . PHP_EOL;
		$string .= "\t" . 'if ( @file_exists( $config_file ) ) {' . PHP_EOL;
		$string .= "\t" . "\t" . 'include( $config_file );' . PHP_EOL;
		$string .= "\t" . "\t" . 'break;' . PHP_EOL;
		$string .= "\t" . '}' . PHP_EOL;
		$string .= '}' . PHP_EOL . PHP_EOL;

		$string .= "if ( ! isset( \$GLOBALS['powered_cache_options'] ) ) {" . PHP_EOL;
		$string .= "\t" . 'return;' . PHP_EOL;
		$string .= '}' . PHP_EOL . PHP_EOL;

		$string .= 'if ( defined( \'POWERED_CACHE_ADVANCED_CACHE_DROPIN\') && @file_exists( POWERED_CACHE_ADVANCED_CACHE_DROPIN ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( POWERED_CACHE_ADVANCED_CACHE_DROPIN );' . PHP_EOL;
		$string .= '} elseif ( @file_exists( \'' . POWERED_CACHE_DROPIN_DIR . 'page-cache.php' . '\' ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( \'' . POWERED_CACHE_DROPIN_DIR . 'page-cache.php' . '\' );' . PHP_EOL;
		$string .= '} else {' . PHP_EOL;
		$string .= "\t" . 'define( \'POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM\', true );' . PHP_EOL;
		$string .= '}';

		/**
		 * Filters advanced-cache.php file contents.
		 *
		 * @hook   powered_cache_advanced_cache_file_content
		 *
		 * @param  {string} $string The content of the advanced-cache.php file
		 *
		 * @return {string} New value.
		 *
		 * @since  1.0
		 */
		return apply_filters( 'powered_cache_advanced_cache_file_content', $string );
	}


	/**
	 * Define WP_CACHE constant
	 *
	 * @param bool $status The status of the caching
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function define_wp_cache( $status ) {
		$config_path = $this->find_wp_config_file();

		if ( ! $config_path ) {
			return false;
		}

		if ( defined( 'WP_CACHE' ) && WP_CACHE === $status ) {
			return true;
		}

		$config_file_string = file_get_contents( $config_path );

		// Config file is empty. Maybe couldn't read it?
		if ( empty( $config_file_string ) ) {
			return false;
		}

		$config_file = preg_split( "#(\r\n|\r|\n)#", $config_file_string );
		$line_key    = false;

		foreach ( $config_file as $key => $line ) {
			if ( ! preg_match( '/^\s*define\(\s*(\'|")([A-Z_]+)(\'|")(.*)/', $line, $match ) ) {
				continue;
			}

			if ( 'WP_CACHE' === $match[2] ) {
				$line_key = $key;
			}
		}

		if ( false !== $line_key ) {
			unset( $config_file[ $line_key ] );
		}

		$status_string = ( $status ) ? 'true' : 'false';

		array_shift( $config_file );
		array_unshift( $config_file, '<?php', "define( 'WP_CACHE', $status_string ); // Powered Cache" );

		foreach ( $config_file as $key => $line ) {
			if ( '' === $line ) {
				unset( $config_file[ $key ] );
			}
		}

		if ( ! file_put_contents( $config_path, implode( PHP_EOL, $config_file ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * seeking wp-config file
	 *
	 * @return bool|string
	 * @since 1.0
	 */
	public function find_wp_config_file() {
		$file = '/wp-config.php';

		for ( $i = 1; $i <= 3; $i ++ ) {
			if ( $i > 1 ) {
				$file = '/..' . $file;
			}

			if ( file_exists( untrailingslashit( ABSPATH ) . $file ) ) {
				$config_path = untrailingslashit( ABSPATH ) . $file;
				break;
			}
		}

		if ( ! isset( $config_path ) ) {
			return false;
		}

		return $config_path;
	}

	/**
	 * Create .htaccess file based on current setting preferences
	 *
	 * @param bool $enable Configure .htaccess automatically when it's true
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function configure_htaccess( $enable = true ) {
		if ( ! function_exists( '\get_home_path' ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
		}

		$htaccess_file = get_home_path() . '.htaccess';
		$settings      = \PoweredCache\Utils\get_settings();

		/**
		 * Filters whether automatically update or not update .htaccess file
		 *
		 * @hook   powered_cache_auto_htaccess_update
		 *
		 * @param  {boolean} true to automatic update.
		 *
		 * @return {boolean} New value.
		 *
		 * @since  1.1.1
		 */
		if ( true !== apply_filters( 'powered_cache_auto_htaccess_update', true ) ) {
			return false;
		}

		/**
		 * Apache users can control automatic configuration
		 *
		 * @since 1.2
		 */
		$automatic_configuration = $settings['auto_configure_htaccess'];

		if ( is_multisite() && ! POWERED_CACHE_IS_NETWORK ) {
			$automatic_configuration = false; // individual sites shouldn't use .htaccess on multisite
		}

		if ( ! $automatic_configuration ) {
			return false;
		}

		if ( is_writable( $htaccess_file ) ) {
			$contents = file_get_contents( $htaccess_file );

			// clean up
			$contents = preg_replace( '/# BEGIN POWERED CACHE(.*)# END POWERED CACHE\s*?/isU', '', $contents );

			if ( false === $enable ) {
				return file_put_contents( $htaccess_file, $contents );
			}

			$rules    = $this->htaccess_rules();
			$contents = $rules . $contents;

			// Update the .htacces file
			if ( ! file_put_contents( $htaccess_file, $contents ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Prepares .htaccess rules for the caching
	 *
	 * @return string $rules
	 * @since 1.1
	 */
	public function htaccess_rules() {
		$rules    = '';
		$settings = \PoweredCache\Utils\get_settings();

		/**
		 * Filters base htaccess rules
		 *
		 * @hook   powered_cache_pre_htaccess
		 *
		 * @param  {string} empty htaccesss rules by default
		 *
		 * @return {boolean} New value.
		 *
		 * @since  1.1
		 */
		$rules .= apply_filters( 'powered_cache_pre_htaccess', '' );

		$rules .= '# BEGIN POWERED CACHE' . PHP_EOL;

		/**
		 * Filters whether doing the configuration for browser cache or not
		 *
		 * @hook   powered_cache_browser_cache
		 *
		 * @param  {boolean} true for creating .htaccess rules for browser cache
		 *
		 * @return {boolean} New value.
		 *
		 * @since  1.1
		 */
		if ( apply_filters( 'powered_cache_browser_cache', true ) ) {
			$wp_mime_types = wp_get_mime_types();
			$mime_types    = array_flip( $wp_mime_types );
			// mimes
			$rules .= '<IfModule mod_mime.c>' . PHP_EOL;
			foreach ( $mime_types as $mime_type => $ext ) {
				$ext_str = '.' . str_replace( '|', ' .', $ext );
				$rules  .= '    AddType ' . $mime_type . ' ' . $ext_str . PHP_EOL;
			}
			$rules .= '</IfModule>' . PHP_EOL;

			// set expire time

			$rules .= '<IfModule mod_expires.c>' . PHP_EOL;
			$rules .= '    ExpiresActive On' . PHP_EOL;
			$rules .= '    ExpiresByType  text/html            "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/richtext        "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/plain           "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xsd             "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xsl             "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xml             "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/cache-manifest  "access plus 0 seconds"' . PHP_EOL;

			foreach ( $mime_types as $mime_type => $ext ) {

				if ( in_array( $mime_type, array( 'text/html', 'text/richtext', 'text/plain', 'text/xsd', 'text/xsl', 'text/xml', 'text/cache-manifest' ), true ) ) {
					continue;
				}

				$expiry_time = $this->get_browser_cache_lifespan( $mime_type );

				$rules .= '    ExpiresByType ' . $mime_type . '                 "' . $expiry_time . '"' . PHP_EOL;
			}

			$font_types = [
				'application/x-font-ttf',
				'application/x-font-woff ',
				'application/x-font-woff2',
				'font/opentype',
				'application/vnd.ms-fontobject',
				'application/font-sfnt',
				'image/svg+xml',
			];

			foreach ( $font_types as $mime_type ) {
				$expiry_time = $this->get_browser_cache_lifespan( $mime_type );

				$rules .= '    ExpiresByType ' . $mime_type . '                 "' . $expiry_time . '"' . PHP_EOL;
			}

			$rules .= '</IfModule>' . PHP_EOL;
		}

		// Add cors rules
		if ( $settings['enable_cdn'] ) {

			/**
			 * Add CORS configuration
			 *
			 * @link  https://developer.mozilla.org/en-US/docs/Web/HTML/CORS_enabled_image
			 * @since 2.1
			 */
			$rules .= '<IfModule mod_setenvif.c>' . PHP_EOL;
			$rules .= '  <IfModule mod_headers.c>' . PHP_EOL;
			$rules .= '    <FilesMatch "\.(avifs?|bmp|cur|gif|ico|jpe?g|jxl|a?png|svgz?|webp)$">' . PHP_EOL;
			$rules .= '      SetEnvIf Origin ":" IS_CORS' . PHP_EOL;
			$rules .= '      Header set Access-Control-Allow-Origin "*" env=IS_CORS' . PHP_EOL;
			$rules .= '    </FilesMatch>' . PHP_EOL;
			$rules .= '  </IfModule>' . PHP_EOL;
			$rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

			// configure fonts
			$rules .= '<FilesMatch "\.(ttf|ttc|otf|eot|woff|woff2|font.css)$">' . PHP_EOL;
			$rules .= '  <IfModule mod_headers.c>' . PHP_EOL;
			$rules .= '    Header set Access-Control-Allow-Origin "*"' . PHP_EOL;
			$rules .= '  </IfModule>' . PHP_EOL;
			$rules .= '</FilesMatch>' . PHP_EOL . PHP_EOL;
		}

		// gzip
		$rules .= '<IfModule mod_deflate.c>' . PHP_EOL;
		$rules .= '  <IfModule mod_headers.c>' . PHP_EOL;
		$rules .= '    Header set  X-Powered-By "Powered Cache"' . PHP_EOL;
		$rules .= '    Header append Vary User-Agent env=!dont-vary' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '    AddOutputFilterByType DEFLATE text/css text/x-component application/x-javascript application/javascript text/javascript text/x-js text/html text/richtext image/svg+xml text/plain text/xsd text/xsl text/xml image/bmp application/java application/msword application/vnd.ms-fontobject application/x-msdownload image/x-icon application/json application/vnd.ms-access application/vnd.ms-project application/x-font-otf application/vnd.ms-opentype application/vnd.oasis.opendocument.database application/vnd.oasis.opendocument.chart application/vnd.oasis.opendocument.formula application/vnd.oasis.opendocument.graphics application/vnd.oasis.opendocument.presentation application/vnd.oasis.opendocument.spreadsheet application/vnd.oasis.opendocument.text audio/ogg application/pdf application/vnd.ms-powerpoint application/x-shockwave-flash image/tiff application/x-font-ttf application/vnd.ms-opentype audio/wav application/vnd.ms-write application/font-woff application/font-woff2 application/vnd.ms-excel'
		          . PHP_EOL; // phpcs:ignore
		$rules .= '  <IfModule mod_mime.c>' . PHP_EOL;
		$rules .= '    AddOutputFilter DEFLATE js css htm html xml' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;

		$rules .= '</IfModule>' . PHP_EOL;

		// remove etag
		$rules .= '<IfModule mod_headers.c>' . PHP_EOL;
		$rules .= 'Header unset ETag' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

		/**
		 * Filters whether doing the configuration for htaccess rewrite cache or not
		 *
		 * @hook   powered_cache_mod_rewrite
		 *
		 * @param  {boolean} true for creating .htaccess rewrite rules.
		 *
		 * @return {boolean} New value.
		 *
		 * @since  2.0
		 */
		if ( apply_filters( 'powered_cache_mod_rewrite', true ) ) { // rewrite

			// add gzip type for .html.gz format
			if ( $settings['gzip_compression'] ) {
				$rules .= '<IfModule mod_mime.c>' . PHP_EOL;
				$rules .= '    AddType text/html .html.gz' . PHP_EOL;
				$rules .= '    AddEncoding gzip .gz' . PHP_EOL;
				$rules .= '</IfModule>' . PHP_EOL;
				$rules .= '<IfModule mod_setenvif.c>' . PHP_EOL;
				$rules .= '    SetEnvIfNoCase Request_URI \.html.gz$ no-gzip' . PHP_EOL;
				$rules .= '</IfModule>' . PHP_EOL;
			}

			$env_powered_cache_ua  = '';
			$env_powered_cache_ssl = '';
			$env_powered_cache_enc = '';

			$rewrite_base = wp_parse_url( home_url() );
			$rewrite_base = isset( $rewrite_base['path'] ) ? trailingslashit( $rewrite_base['path'] ) : '/';

			$rules .= '<IfModule mod_rewrite.c>' . PHP_EOL;
			$rules .= '    RewriteEngine On' . PHP_EOL;
			$rules .= '    RewriteBase ' . $rewrite_base . PHP_EOL;
			$rules .= '    AddDefaultCharset UTF-8 ' . PHP_EOL;

			if ( true === $settings['cache_mobile'] && true === $settings['cache_mobile_separate_file'] ) {
				$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_browsers() ) ), ' ' );
				$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_prefixes() ) ), ' ' );
				// mobile env set
				$rules               .= '    RewriteCond %{HTTP_USER_AGENT} (' . $mobile_browsers . ') [NC]' . PHP_EOL;
				$rules               .= '    RewriteRule .* - [E=PC_UA:-mobile]' . PHP_EOL;
				$rules               .= '    RewriteCond %{HTTP_USER_AGENT} ^(' . $mobile_prefixes . ') [NC]' . PHP_EOL;
				$rules               .= '    RewriteRule .* - [E=PC_UA:-mobile]' . PHP_EOL;
				$env_powered_cache_ua = '%{ENV:PC_UA}';
			}

			$rules                .= '    RewriteCond %{HTTPS} on [OR]' . PHP_EOL;
			$rules                .= '    RewriteCond %{SERVER_PORT} ^443$ [OR]' . PHP_EOL;
			$rules                .= '    RewriteCond %{HTTP:X-Forwarded-Proto} https' . PHP_EOL;
			$rules                .= '    RewriteRule .* - [E=PC_SSL:-https]' . PHP_EOL;
			$env_powered_cache_ssl = '%{ENV:PC_SSL}';

			if ( true === $settings['gzip_compression'] ) {
				$rules                .= '    RewriteCond %{HTTP:Accept-Encoding} gzip' . PHP_EOL;
				$rules                .= '    RewriteRule .* - [E=PC_ENC:.gz]' . PHP_EOL;
				$env_powered_cache_enc = '%{ENV:PC_ENC}';
			}

			$rules .= '    RewriteCond %{REQUEST_METHOD} !=POST' . PHP_EOL;
			$rules .= '    RewriteCond %{QUERY_STRING} =""' . PHP_EOL;

			if ( permalink_structure_has_trailingslash() ) {
				$rules .= '    RewriteCond %{REQUEST_URI} !^.*[^/]$' . PHP_EOL;
				$rules .= '    RewriteCond %{REQUEST_URI} !^.*//.*$' . PHP_EOL;
			}

			// Get root base
			$site_root = wp_parse_url( site_url() );
			$site_root = isset( $site_root['path'] ) ? trailingslashit( $site_root['path'] ) : '';

			// reject user agent
			$rejected_user_agents = (array) AdvancedCache::get_rejected_user_agents();
			if ( ! empty( $rejected_user_agents ) ) {
				$rules .= '    RewriteCond %{HTTP_USER_AGENT} !^(' . implode( '|', $rejected_user_agents ) . ').* [NC]' . PHP_EOL;
			}

			// rejected cookies
			$rejected_cookies = AdvancedCache::get_rejected_cookies();
			if ( ! empty( $rejected_cookies ) ) {
				$rules .= '    RewriteCond %{HTTP:Cookie} !(' . implode( '|', $rejected_cookies ) . ') [NC]' . PHP_EOL;
			}

			// dont cache fbexternal
			$rules .= '    RewriteCond %{HTTP_USER_AGENT} !^(facebookexternalhit).* [NC]' . PHP_EOL;

			$cache_location = get_cache_dir();
			$cache_location = untrailingslashit( $cache_location ) . '/powered-cache/';
			if ( strpos( ABSPATH, $cache_location ) === false ) {
				$cache_path = str_replace( $_SERVER['DOCUMENT_ROOT'], '', $cache_location ); // clean doc root
			} else {
				$cache_path = $site_root . str_replace( ABSPATH, '', $cache_location );
			}

			/**
			 * Filters whether running on 1and1_hosting or not
			 *
			 * @hook   powered_cache_maybe_1and1_hosting
			 *
			 * @param  {boolean} $status true if  /kunden/homepage directory exists
			 *
			 * @return {boolean} New value.
			 *
			 * @since  1.1
			 */
			if ( apply_filters( 'powered_cache_maybe_1and1_hosting', ( 0 === strpos( $_SERVER['DOCUMENT_ROOT'], '/kunden/homepage/' ) ) ) ) {
				$rules .= '    RewriteCond "' . str_replace( '/kunden/homepage/', '/', $cache_location ) . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" -f' . PHP_EOL;
			} else {
				$rules .= '    RewriteCond "%{DOCUMENT_ROOT}/' . ltrim( $cache_path, '/' ) . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" -f' . PHP_EOL;
			}
			$rules .= '    RewriteRule .* "' . $cache_path . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" [L]' . PHP_EOL;

			if ( $settings['gzip_compression'] ) {
				$rules .= '    # prevent mod_deflate double gzip' . PHP_EOL;
				$rules .= '    RewriteRule \.html\.gz$ - [T=text/html,E=no-gzip:1]' . PHP_EOL;
			}

			$rules .= '</IfModule>' . PHP_EOL;
		}

		/**
		 * Filters post htaccess rules
		 *
		 * @hook   powered_cache_after_htaccess
		 *
		 * @param  {string} empty htaccesss rules by default
		 *
		 * @return {boolean} New value.
		 *
		 * @since  2.0
		 */
		$rules .= apply_filters( 'powered_cache_after_htaccess', '' );

		$rules .= '# END POWERED CACHE' . PHP_EOL;

		return $rules;
	}

	/**
	 * Make sure directory listing disabled
	 *
	 * @return bool
	 * @since 1.2
	 */
	public function protect_cache_dir() {

		if ( ! file_exists( get_cache_dir() ) ) {
			mkdir( get_cache_dir() );
		}

		$file = get_cache_dir() . '.htaccess';

		$file_string = 'Options -Indexes';

		if ( ! file_put_contents( $file, $file_string ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare config name
	 *
	 * @param bool $is_network Whether network-wide config name or not
	 *
	 * @return string configuration file name
	 * @since 2.0
	 */
	public function get_config_filename( $is_network ) {
		if ( $is_network ) {
			return 'config-network.php';
		} else {
			$url_parts   = wp_parse_url( home_url() );
			$config_name = 'config-' . $url_parts['host'];

			if ( is_multisite() && ! is_subdomain_install() ) {
				if ( ! is_main_site( get_current_blog_id() ) && ! empty( $url_parts['path'] ) ) {
					$subdir_name  = ltrim( $url_parts['path'], '/' );
					$config_name .= '-' . $subdir_name;
				}
			}

			$config_name .= '.php';

			return $config_name;
		}
	}

	/**
	 * Save settings to file
	 *
	 * @param array $configuration plugin settings
	 * @param bool  $network_wide  Whether network-wide configuration or not
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function save_to_file( $configuration, $network_wide = false ) {
		$config_dir       = WP_CONTENT_DIR . '/pc-config';
		$config_file_name = $this->get_config_filename( $network_wide );

		if ( ! file_exists( $config_dir ) ) {
			mkdir( $config_dir );
		}

		$config_file = trailingslashit( $config_dir ) . $config_file_name;

		if ( ! file_exists( $config_file ) ) {
			touch( $config_file );
		}

		$configuration['cache_location'] = get_cache_dir();

		$config_file_string = '<?php' . PHP_EOL . "defined( 'ABSPATH' ) || exit;" . PHP_EOL . PHP_EOL;

		$config_file_string .= "\$GLOBALS['powered_cache_options'] = " . var_export( $configuration, true ) . ';' . PHP_EOL . PHP_EOL;

		// mobile cache varibales
		$config_file_string .= '$powered_cache_mobile_browsers = ' . var_export( mobile_browsers(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_mobile_prefixes = ' . var_export( mobile_prefixes(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_rejected_user_agents = ' . var_export( AdvancedCache::get_rejected_user_agents(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_rejected_cookies = ' . var_export( AdvancedCache::get_rejected_cookies(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_vary_cookies = ' . var_export( AdvancedCache::get_vary_cookies(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_rejected_uri = ' . var_export( AdvancedCache::get_rejected_uri(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$powered_cache_accepted_query_strings = ' . var_export( AdvancedCache::get_accepted_query_strings(), true ) . ';' . PHP_EOL;

		if ( permalink_structure_has_trailingslash() ) {
			$config_file_string .= '$powered_cache_slash_check = true;' . PHP_EOL;
		} else {
			$config_file_string .= '$powered_cache_slash_check = false;' . PHP_EOL;
		}

		/**
		 * Fires before writing configuration file.
		 *
		 * @hook  powered_cache_create_config_file
		 *
		 * @param {string} $config_file The path of the configuration file.
		 * @param {string} $config_file_string The contents of the configurations.
		 * @param {bool} $network_wide Whether network-wide configuration or not.
		 *
		 * @since 2.0
		 */
		do_action( 'powered_cache_create_config_file', $config_file, $config_file_string, $network_wide );

		if ( ! file_put_contents( $config_file, $config_file_string ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Prepares nginx configuration
	 *
	 * @return string $contents nginx rules
	 * @since 1.2 trailingslash rule added
	 *
	 * @since 1.1
	 */
	public function nginx_rules() {
		$settings = \PoweredCache\Utils\get_settings();

		$contents  = '';
		$contents .= '##### POWERED CACHE CONF #####' . PHP_EOL;
		$contents .= 'set $cache_uri $request_uri;' . PHP_EOL;
		$contents .= 'set $pc_ssl "";' . PHP_EOL;
		$contents .= 'set $pc_enc "";' . PHP_EOL;
		$contents .= 'set $pc_ua "";' . PHP_EOL . PHP_EOL;

		// post
		$contents .= '# POST requests and urls with a query string should always go to PHP' . PHP_EOL;
		$contents .= 'if ($request_method = POST) {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= 'location = /favicon.ico { log_not_found off; access_log off; }' . PHP_EOL;
		$contents .= 'location = /robots.txt { try_files $uri $uri/ /index.php?$args; log_not_found off; access_log off; }' . PHP_EOL . PHP_EOL;

		// query string
		$contents .= 'if ($query_string != "") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		/**
		 * Documented in htaccess config
		 */
		if ( apply_filters( 'powered_cache_browser_cache', true ) ) {
			$contents .= 'location ~* .(jpg|jpeg|png|gif|ico|css|js|svg|eot|woff|woff2|ttf|otf)$ {' . PHP_EOL;
			$contents .= '  expires 6M;' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		// https
		$contents .= '# HTTPS' . PHP_EOL;
		$contents .= 'if ($https = "on") {' . PHP_EOL;
		$contents .= '  set $pc_ssl "-https";' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= '# Don\'t cache uris containing the following segments' . PHP_EOL;
		$contents .= 'if ($request_uri ~* "(/wp-admin/|/xmlrpc.php|/wp-(app|cron|login|register|mail).php|wp-.*.php|/feed/|index.php|wp-comments-popup.php|wp-links-opml.php|wp-locations.php|sitemap(_index)?.xml|[a-z0-9_-]+-sitemap([0-9]+)?.xml)") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$rejected_user_agents = (array) AdvancedCache::get_rejected_user_agents();

		$contents .= '# Don\'t use the cache for rejected agents' . PHP_EOL;
		$contents .= 'if ($http_user_agent ~* "(' . implode( '|', $rejected_user_agents ) . '")) {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$rejected_cookies = (array) AdvancedCache::get_rejected_cookies();

		$contents .= '# Don\'t use the cache for logged in users or recent commenters' . PHP_EOL;
		$contents .= 'if ($http_cookie ~* "wordpress_[a-f0-9]+|' . implode( '|', $rejected_cookies ) . '") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= 'if ($http_x_wap_profile) {' . PHP_EOL;
		$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		if ( true === $settings['cache_mobile'] && true === $settings['cache_mobile_separate_file'] ) {
			$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_browsers() ) ), ' ' );
			$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_prefixes() ) ), ' ' );

			$contents .= 'if ($http_user_agent ~* (' . $mobile_browsers . ')) {' . PHP_EOL;
			$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;

			$contents .= 'if ($http_user_agent ~* (' . $mobile_prefixes . ')) {' . PHP_EOL;
			$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		$cache_suffix = 'html';

		if ( true === $settings['gzip_compression'] ) {
			$cache_suffix .= '.gz';
		}

		$contents .= 'location / {' . PHP_EOL;
		/**
		 * Documented in htaccess config
		 */
		if ( apply_filters( 'powered_cache_mod_rewrite', true ) ) { // rewrite
			$contents .= '  add_header X-Powered-Cache nginx;' . PHP_EOL;
			$contents .= '  try_files /wp-content/cache/powered-cache/$http_host/$cache_uri/index${pc_ssl}${pc_ua}.' . $cache_suffix . ' $uri $uri/ /index.php?$args;' . PHP_EOL;

		} else {
			$contents .= '  try_files $uri $uri/ /index.php?$args;' . PHP_EOL;
		}

		$contents .= '}' . PHP_EOL . PHP_EOL;

		if ( permalink_structure_has_trailingslash() ) {
			$contents .= '# add trailingslash rule' . PHP_EOL;
			$contents .= 'rewrite ^([^.]*[^/])$ $1/ permanent;' . PHP_EOL;
		}

		return $contents;
	}

	/**
	 * Downloads configuration files
	 *
	 * @param string $server type supports apache and nginx
	 *
	 * @since 1.1
	 */
	public function download_rewrite_rules( $server ) {

		$rules    = '';
		$filename = 'conf';

		if ( 'apache' === $server ) {
			$rules    = $this->htaccess_rules();
			$filename = '.htaccess_powered_cache';
		}

		if ( 'nginx' === $server ) {
			$rules    = $this->nginx_rules();
			$filename = 'poweredcache.conf';
		}

		nocache_headers();
		@header( 'Content-Type: text/plain' );
		@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		@header( 'Content-Transfer-Encoding: binary' );
		@header( 'Content-Length: ' . strlen( $rules ) );
		@header( 'Connection: close' );
		echo $rules; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Make the caching configurations with given options
	 *
	 * @param array $settings     Plugin settings
	 * @param bool  $network_wide Whether network-wide configuration or not
	 *
	 * @since 2.0
	 */
	public function save_configuration( $settings, $network_wide = false ) {

		if ( can_configure_object_cache() ) {
			$this->setup_object_cache( $settings['object_cache'] );
		}

		$this->setup_page_cache( $settings['enable_page_cache'] );
		$private_settings = [ 'cloudflare_email', 'cloudflare_api_key', 'cloudflare_zone' ];

		foreach ( $private_settings as $setting_key ) {
			unset( $settings[ $setting_key ] );
		}

		$this->save_to_file( $settings, $network_wide );
	}

	/**
	 * Clean-up all the configurations and cache related footprints
	 */
	public function clean_up() {
		$object_cache_dropin = untrailingslashit( WP_CONTENT_DIR ) . '/object-cache.php';
		if ( file_exists( $object_cache_dropin ) && false !== strpos( file_get_contents( $object_cache_dropin ), 'POWERED_OBJECT_CACHE' ) ) {
			unlink( $object_cache_dropin );
		}

		$advanced_cache_dropin = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		if ( file_exists( $advanced_cache_dropin ) ) {
			unlink( $advanced_cache_dropin );
		}

		$this->define_wp_cache( false );
		$this->configure_htaccess( false );
		$this->protect_cache_dir();

		if ( ! file_exists( get_cache_dir() ) ) {
			remove_dir( get_cache_dir() );
		}

		$config_dir = WP_CONTENT_DIR . '/pc-config';

		if ( is_multisite() ) {
			$config_file_name = $this->get_config_filename( POWERED_CACHE_IS_NETWORK );
			$config_file      = trailingslashit( $config_dir ) . $config_file_name;
			if ( file_exists( $config_file ) ) {
				unlink( $config_file );
			}
		} else { // remove entire configuration directory on single site setup
			remove_dir( $config_dir );
		}

		/**
		 * Fires after cleanup all configurations
		 *
		 * @hook  powered_cache_after_clean_up
		 *
		 * @since 2.0
		 */
		do_action( 'powered_cache_after_clean_up' );

	}

	/**
	 * Apache allows both format like A2592000 => "access plus 1 month"
	 * A => access, M => Modified
	 *
	 * @param string $mime_type Mimetype
	 *
	 * @return string cache lifespan for apache
	 * @see   http://httpd.apache.org/docs/current/mod/mod_expires.html
	 * @since 2.0
	 */
	public function get_browser_cache_lifespan( $mime_type ) {
		switch ( $mime_type ) {
			case 'text/css':
			case 'application/javascript':
				/**
				 * Filters TTL for CSS/JS files.
				 *
				 * @hook   powered_cache_browser_cache_assets_lifespan
				 *
				 * @param  {string} $expiry_time .htaccess lifespan
				 *
				 * @return {string} New value.
				 *
				 * @since  1.1
				 */
				$expiry_time = apply_filters( 'powered_cache_browser_cache_assets_lifespan', 'access plus 1 year' );
				break;
			case 'image/jpeg':
			case 'image/gif':
			case 'image/png':
			case 'image/bmp':
			case 'image/tiff':
			case 'image/webp':
			case 'image/heic':
				$expiry_time = 'access plus 6 months';
				break;
			case 'image/x-icon':
				$expiry_time = 'access plus 1 week'; // favicon
				break;
			default:
				/**
				 * Filters default TTL for browser cache lifespan.
				 *
				 * @hook   powered_cache_browser_cache_default_lifespan
				 *
				 * @param  {string} $expiry_time .htaccess lifespan
				 *
				 * @return {string} New value.
				 *
				 * @since  1.1
				 */
				$expiry_time = apply_filters( 'powered_cache_browser_cache_default_lifespan', 'access plus 1 month' );
		}

		/**
		 * Filters TTL for browser cache lifespan.
		 *
		 * @hook   powered_cache_browser_cache_lifespan
		 *
		 * @param  {string} $expiry_time .htaccess lifespan
		 * @param  {string} $mime_type mime type
		 *
		 * @return {string} New value.
		 *
		 * @since  2.0
		 */
		$expiry_time = apply_filters( 'powered_cache_browser_cache_lifespan', $expiry_time, $mime_type );

		return $expiry_time;
	}


}
