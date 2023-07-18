<?php
/**
 * Htaccress rules
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use function PoweredCache\Utils\get_cache_dir;
use function PoweredCache\Utils\mobile_browsers;
use function PoweredCache\Utils\mobile_prefixes;
use function PoweredCache\Utils\permalink_structure_has_trailingslash;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found

/**
 * Class Htaccess
 */
class Htaccess {
	/**
	 * Plugin settings
	 *
	 * @var $settings
	 */
	private $settings;


	/**
	 * placeholder
	 *
	 * @since 2.5
	 */
	public function __construct() {
		$this->settings = \PoweredCache\Utils\get_settings();
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return Htaccess
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get htaccess rules
	 *
	 * @return string
	 */
	public function htaccess_rules() {
		$rules = '';
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

		$rules .= $this->browser_cache_rules();
		$rules .= $this->cors_rules();
		$rules .= $this->gzip_rules();
		$rules .= $this->etag_rules();
		$rules .= $this->cache_control_rules();
		$rules .= $this->rewrite_rules();

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
	 * Browser cache rules
	 *
	 * @return string
	 */
	public function browser_cache_rules() {
		$rules = '';
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

			$mime_types = [
				'text/css',
				'application/javascript',
				'image/jpeg',
				'image/gif',
				'image/png',
				'image/bmp',
				'image/tiff',
				'image/webp',
				'image/heic',
				'image/svg+xml',
				'font/ttf',
				'font/woff',
				'font/woff2',
				'font/otf',
				'application/vnd.ms-fontobject',
				'image/x-icon',
				'application/atom+xml',
				'application/rss+xml',
			];

			// set expire time
			$rules .= '<IfModule mod_expires.c>' . PHP_EOL;
			$rules .= '    ExpiresActive On' . PHP_EOL;
			$rules .= '    ExpiresByType  text/html                       "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/richtext                   "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/plain                      "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xsd                        "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xsl                        "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/xml                        "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  application/xml                 "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  application/json                "access plus 0 seconds"' . PHP_EOL;
			$rules .= '    ExpiresByType  text/cache-manifest             "access plus 0 seconds"' . PHP_EOL;

			foreach ( $mime_types as $mime_type ) {
				$rules .= sprintf( '    ExpiresByType  %s  "%s"', str_pad( $mime_type, 30, ' ' ), $this->get_browser_cache_lifespan( $mime_type ) ) . PHP_EOL;
			}

			$rules .= '</IfModule>' . PHP_EOL;
		}

		return $rules;
	}

	/**
	 * Cors rules - when CDN integration activated
	 *
	 * @return string
	 */
	public function cors_rules() {
		$rules = '';
		/**
		 * Filters whether add CORS configuration or not
		 *
		 * @hook   powered_cache_htaccess_add_cors
		 *
		 * @param  {boolean} true for creating .htaccess rules for CORS
		 *
		 * @return {boolean} New value.
		 *
		 * @since  2.5
		 */
		if ( apply_filters( 'powered_cache_htaccess_add_cors', $this->settings['enable_cdn'] ) ) {
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

		return $rules;
	}

	/**
	 * Gzip rules
	 *
	 * @return string
	 */
	public function gzip_rules() {
		$rules = '';

		$rules .= '<IfModule filter_module>' . PHP_EOL;
		$rules .= '  <IfModule version.c>' . PHP_EOL;
		$rules .= '    <IfVersion >= 2.4>' . PHP_EOL;
		$rules .= '      FilterDeclare   COMPRESS' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/atom+xml\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/javascript\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/json\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/ld+json\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/manifest+json\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/rss+xml\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/vnd.ms-fontobject\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/xhtml+xml\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'application/xml\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'font/opentype\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'image/svg+xml\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'image/x-icon\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'text/html\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'text/plain\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'text/x-component\'"' . PHP_EOL;
		$rules .= '      FilterProvider  COMPRESS  DEFLATE "%{CONTENT_TYPE} = \'text/xml\'"' . PHP_EOL;
		$rules .= '      FilterChain     COMPRESS' . PHP_EOL;
		$rules .= '      FilterProtocol  COMPRESS  DEFLATE change=yes;byteranges=no' . PHP_EOL;
		$rules .= '    </IfVersion>' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL;

		$rules .= '<IfModule mod_deflate.c>' . PHP_EOL;
		$rules .= '  SetOutputFilter DEFLATE' . PHP_EOL;
		$rules .= '  <IfModule mod_setenvif.c>' . PHP_EOL;
		$rules .= '    <IfModule mod_headers.c>' . PHP_EOL;
		$rules .= '      SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding' . PHP_EOL;
		$rules .= '      RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding' . PHP_EOL;
		$rules .= '    </IfModule>' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '  <IfModule mod_filter.c>' . PHP_EOL;
		$rules .= '    AddOutputFilterByType DEFLATE application/atom+xml \
		                          application/javascript \
		                          application/json \
		                          application/ld+json \
		                          application/manifest+json \
		                          application/rss+xml \
		                          application/vnd.ms-fontobject \
		                          application/x-font-ttf \
		                          application/xhtml+xml \
		                          application/xml \
		                          font/opentype \
		                          image/svg+xml \
		                          image/x-icon \
		                          text/html \
		                          text/plain \
		                          text/css \
		                          text/x-component \
		                          text/xml' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '  <IfModule mod_headers.c>' . PHP_EOL;
		$rules .= '    Header append Vary: Accept-Encoding' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

		return $rules;
	}

	/**
	 * Remove ETag rules
	 *
	 * @link  https://htaccessbook.com/disable-etags/
	 * @return string
	 */
	public function etag_rules() {
		// remove etag
		$rules  = '# Remove ETag' . PHP_EOL;
		$rules  = '<IfModule mod_headers.c>' . PHP_EOL;
		$rules .= 'Header unset ETag' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL;
		$rules .= 'FileETag None' . PHP_EOL . PHP_EOL;

		return $rules;
	}

	/**
	 * Rewrite rules
	 *
	 * @return string
	 */
	public function rewrite_rules() {
		$rules = '';
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
			if ( $this->is_gzip_enabled() ) {
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

			if ( true === $this->settings['cache_mobile'] && true === $this->settings['cache_mobile_separate_file'] ) {
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

			if ( $this->is_gzip_enabled() ) {
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
				$cache_path = str_replace( $_SERVER['DOCUMENT_ROOT'], '', $cache_location ); // phpcs:ignore
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
			if ( apply_filters( 'powered_cache_maybe_1and1_hosting', ( 0 === strpos( $_SERVER['DOCUMENT_ROOT'], '/kunden/homepage/' ) ) ) ) { // phpcs:ignore
				$rules .= '    RewriteCond "' . str_replace( '/kunden/homepage/', '/', $cache_location ) . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" -f' . PHP_EOL;
			} else {
				$rules .= '    RewriteCond "%{DOCUMENT_ROOT}/' . ltrim( $cache_path, '/' ) . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" -f' . PHP_EOL;
			}
			$rules .= '    RewriteRule .* "' . $cache_path . '%{HTTP_HOST}' . '%{REQUEST_URI}/index' . $env_powered_cache_ssl . $env_powered_cache_ua . '.html' . $env_powered_cache_enc . '" [L]' . PHP_EOL;

			if ( $this->is_gzip_enabled() ) {
				$rules .= '    # prevent mod_deflate double gzip' . PHP_EOL;
				$rules .= '    RewriteRule \.html\.gz$ - [T=text/html,E=no-gzip:1]' . PHP_EOL;
			}

			$rules .= '</IfModule>' . PHP_EOL;
		}

		return $rules;
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
			case 'image/svg+xml':
			case 'font/ttf':
			case 'font/woff':
			case 'font/woff2':
			case 'font/otf':
			case 'application/vnd.ms-fontobject':
				$expiry_time = 'access plus 4 month';
				break;
			case 'application/atom+xml':
			case 'application/rss+xml':
				$expiry_time = 'access plus 1 hour';
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


	/**
	 * Check if the gzip option enabled
	 *
	 * @return bool
	 * @since 2.5
	 */
	public function is_gzip_enabled() {
		/**
		 * Filters whether gzip enabled or not for the htaccess rules
		 *
		 * @hook   powered_cache_htaccess_enable_gzip_compression
		 *
		 * @param  {boolean} $status true if gzip option enabled on settings page
		 *
		 * @return {boolean} New value.
		 *
		 * @since  2.5
		 */
		return function_exists( 'gzencode' ) && apply_filters( 'powered_cache_htaccess_enable_gzip_compression', $this->settings['gzip_compression'] );
	}


	/**
	 * Cache-Control rules
	 *
	 * @return string
	 */
	public function cache_control_rules() {
		$rules  = '<FilesMatch "\.(html|htm|html\.gz|rtf|rtx|txt|xsd|xsl|xml)$">' . PHP_EOL;
		$rules .= '  <IfModule mod_headers.c>' . PHP_EOL;
		$rules .= '    Header set X-Powered-By "Powered Cache"' . PHP_EOL;
		$rules .= '    Header unset Pragma' . PHP_EOL;
		$rules .= '    Header append Cache-Control "public"' . PHP_EOL;
		$rules .= '  </IfModule>' . PHP_EOL;
		$rules .= '</FilesMatch>' . PHP_EOL;

		/**
		 * Filters cache control rules
		 *
		 * @hook   powered_cache_htaccess_cache_control_rules
		 *
		 * @param  {string} $rules cache control
		 *
		 * @return {string} New value.
		 *
		 * @since  2.5
		 */
		$rules = apply_filters( 'powered_cache_htaccess_cache_control_rules', $rules );

		return $rules;
	}

}


