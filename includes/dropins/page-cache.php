<?php
/**
 * File based caching drop-in based on Taylor Lovett's Simple Cache
 * @link https://github.com/tlovett1/simple-cache/blob/master/inc/dropins/file-based-page-cache.php
 */
defined( 'ABSPATH' ) || exit;

$powered_cache_start_time = microtime( true );

// Don't cache robots.txt or htacesss
if ( strpos( $_SERVER['REQUEST_URI'], 'robots.txt' ) !== false || strpos( $_SERVER['REQUEST_URI'], '.htaccess' ) !== false ) {
	return;
}

// Don't cache non-GET requests
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
	return;
}

// Don't cache wp-admin
if ( is_admin() ) {
	return;
}



$file_extension = $_SERVER['REQUEST_URI'];
$file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $file_extension );
$file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

// Don't cache disallowed extensions. Prevents wp-cron.php, xmlrpc.php, etc.
if ( ! preg_match( '#index\.php$#i', $_SERVER['REQUEST_URI'] ) && in_array( $file_extension, array( 'php', 'xml', 'xsl' ) ) ) {
	return;
}

if ( ! $GLOBALS['powered_cache_options']['enable_page_caching'] ) {
	return;
}

// Don't cache page with these user agents
if ( ! empty( $GLOBALS['powered_cache_options']['rejected_user_agents'] ) ) {
	$rejected_user_agents = preg_split( '#(\r\n|\r|\n)#', $GLOBALS['powered_cache_options']['rejected_user_agents'] );
	$rejected_user_agents = implode( '|', $rejected_user_agents );
	if ( ! empty( $rejected_user_agents ) && isset( $_SERVER['HTTP_USER_AGENT'] )  && preg_match( '#(' . $rejected_user_agents . ')#', $_SERVER['HTTP_USER_AGENT'] ) ) {
		return;
	}
}


// Don't cache SSL
if ( powered_cache_is_ssl() && ( ! isset( $GLOBALS['powered_cache_options']['ssl_cache'] ) || false === $GLOBALS['powered_cache_options']['ssl_cache'] ) ) {
	return;
}


// dont cache mobile
if ( ! isset( $GLOBALS['powered_cache_options']['cache_mobile'] ) || true !== $GLOBALS['powered_cache_options']['cache_mobile'] ) {
	global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

	$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_browsers ) ), ' ' );
	$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_prefixes ) ), ' ' );
	// Don't cache if mobile detection is activated
	if ( (preg_match( '#^.*(' . $mobile_browsers . ').*#i', $_SERVER['HTTP_USER_AGENT'] ) || preg_match( '#^(' . $mobile_prefixes . ').*#i', substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) ) ) ) {
		return '';
	}
}


if ( ! empty( $_COOKIE ) ) {
	//Don't cache if logged in
	if ( ! isset( $GLOBALS['powered_cache_options']['loggedin_user_cache'] ) || false === $GLOBALS['powered_cache_options']['loggedin_user_cache'] ) {
		$wp_cookies = array( 'wordpressuser_', 'wordpresspass_', 'wordpress_sec_', 'wordpress_logged_in_' );

		// check logged-in cookie
		foreach ( $_COOKIE as $key => $value ) {
			foreach ( $wp_cookies as $cookie ) {
				if ( strpos( $key, $cookie ) !== false ) {
					// Logged in!
					return;
				}
			}
		}
	}


	if ( ! empty( $_COOKIE['powered_cache_commented_posts'] ) ) {
		foreach ( $_COOKIE['powered_cache_commented_posts'] as $path ) {
			if ( rtrim( $path, '/' ) === rtrim( $_SERVER['REQUEST_URI'], '/' ) ) {
				// User commented on this post
				return;
			}
		}
	}

	// don't cache specific cookie
	if ( ! empty( $GLOBALS['powered_cache_options']['rejected_cookies'] ) ) {
		$rejected_cookies = preg_split( '#(\r\n|\r|\n)#', $GLOBALS['powered_cache_options']['rejected_cookies'] );
		$rejected_cookies = implode( '|', $rejected_cookies );
		if ( preg_match( '#(' . $rejected_cookies . ')#', var_export( $_COOKIE, true ) ) ) {
			return;
		}
	}
} // End if().

// Deal with optional cache exceptions
if ( ! empty( $GLOBALS['powered_cache_options']['rejected_uri'] ) ) {
	$exceptions = preg_split( '#(\r\n|\r|\n)#', $GLOBALS['powered_cache_options']['rejected_uri'] );

	foreach ( $exceptions as $exception ) {
		if ( preg_match( '#^[\s]*$#', $exception ) ) {
			continue;
		}

		// full url exception
		if ( preg_match( '#^https?://#', $exception ) ) {
			$exception = parse_url( $exception, PHP_URL_PATH );
		}

		if ( preg_match( '#^(' . $exception . ')$#', $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
	}
}


$accepted_query_strings = array();
// cache url with allowed query string
if ( ! empty( $GLOBALS['powered_cache_options']['accepted_query_strings'] ) ) {
	$accepted_query_strings = preg_split( '#(\r\n|\r|\n)#', $GLOBALS['powered_cache_options']['accepted_query_strings'] );
}

if ( ! empty( $_GET ) && isset( $accepted_query_strings ) && is_array( $accepted_query_strings ) && ! array_intersect( array_keys( $_GET ), $accepted_query_strings ) ) {
	return;
}

powered_cache_serve_cache();

ob_start( 'powered_cache_page_buffer' );

/**
 * Cache output before it goes to the browser
 *
 * @param  string $buffer
 * @param  int $flags
 * @since  1.0
 * @return string
 */
function powered_cache_page_buffer( $buffer, $flags ) {
	global $powered_cache_start_time, $post;

	if ( strlen( $buffer ) < 255 ) {
		return $buffer;
	}

	// Don't cache search, 404, or password protected
	if ( is_404() || is_search() || ! empty( $post->post_password ) ) {
		return $buffer;
	}

	// maybe we shouldn't cache template file has this constant
	if ( defined( 'DONOTCACHEPAGE' ) && true === DONOTCACHEPAGE ) {
		return $buffer;
	}

	// plugins might want to use filter
	if ( true !== apply_filters( 'powered_cache_page_cache_enable', true ) ) {
		return $buffer;
	}

	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
	}

	if ( ! defined( 'FS_CHMOD_FILE' ) ) {
		define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
	}

	include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	$filesystem = new WP_Filesystem_Direct( new StdClass() );

	if ( ! function_exists( 'powered_cache_get_cache_dir' ) ) {
		return $buffer;
	}

	// Make sure we can read/write files and that proper folders exist
	if ( ! $filesystem->exists( untrailingslashit( powered_cache_get_cache_dir() ) ) ) {
		if ( ! $filesystem->mkdir( untrailingslashit( powered_cache_get_cache_dir() ) ) ) {
			// Can not cache!
			return $buffer;
		}
	}

	if ( ! $filesystem->exists( powered_cache_get_page_cache_dir() ) ) {
		if ( ! $filesystem->mkdir( powered_cache_get_page_cache_dir() ) ) {
			// Can not cache!
			return $buffer;
		}
	}

	$buffer = apply_filters( 'powered_cache_page_caching_buffer', $buffer );

	$url_path = powered_cache_get_url_path();

	$dirs = explode( '/', $url_path );

	$path = untrailingslashit( powered_cache_get_page_cache_dir() );

	foreach ( $dirs as $dir ) {
		if ( ! empty( $dir ) ) {
			$path .= '/' . $dir;

			if ( ! $filesystem->exists( $path ) ) {
				if ( ! $filesystem->mkdir( $path ) ) {
					// Can not cache!
					return $buffer;
				}
			}
		}
	}


	$modified_time   = time(); // Make sure modified time is consistent
	$generation_time = number_format( microtime( true ) - $powered_cache_start_time, 3 );

	// Prevent mixed content when there's an http request but the site URL uses https
	// @see https://github.com/tlovett1/simple-cache/issues/67
	$home_url = get_home_url();
	if ( ! is_ssl() && 'https' === strtolower( parse_url( $home_url, PHP_URL_SCHEME ) ) ) {
		$https_home_url = $home_url;
		$http_home_url  = str_replace( 'https://', 'http://', $https_home_url );
		$buffer         = str_replace( esc_url( $http_home_url ), esc_url( $https_home_url ), $buffer );
	}

	if ( array_key_exists( 'show_cache_message', $GLOBALS['powered_cache_options'] ) && true === $GLOBALS['powered_cache_options']['show_cache_message'] ) {
		if ( preg_match( '#</html>#i', $buffer ) ) {
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Cache served by PoweredCache -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- If you like fast websites like this, visit: https://poweredcache.com -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Last modified: " . gmdate( 'D, d M Y H:i:s', $modified_time ) . " GMT -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Dynamic page generated in $generation_time -->";
			$buffer .= PHP_EOL;
		}
	}


	$meta_file_name = 'meta.php';
	$meta_file = '<?php exit; ?>' . PHP_EOL;

	$meta_params = array(); // holds to metadata for cached file

	$response_headers = powered_cache_get_response_headers();

	foreach ( (array) $response_headers as $key => $value ) {
		$meta_params['headers'][ $key ] = "$key: $value";
	}

	$meta_params = apply_filters( 'powered_cache_page_cache_meta_params', $meta_params, $response_headers );
	$meta_file_contents = $meta_file . serialize( $meta_params );

	$meta_file_contents = apply_filters( 'powered_cache_page_cache_meta_info', $meta_file_contents );

	$filesystem->put_contents( $path . '/' . $meta_file_name, $meta_file_contents, FS_CHMOD_FILE );
	$filesystem->touch( $path . '/' . $meta_file_name, $modified_time );

	if ( isset( $meta_params['headers']['Content-Type'] ) ) {
		$index_name = powered_cache_index_file( $meta_params['headers']['Content-Type'] );
	} else {
		$index_name = powered_cache_index_file();
	}

	if ( $GLOBALS['powered_cache_options']['gzip_compression'] && function_exists( 'gzencode' ) ) {
		$filesystem->put_contents( $path . '/' . $index_name, gzencode( $buffer, 3 ), FS_CHMOD_FILE );
		$filesystem->touch( $path . '/' . $index_name, $modified_time );
	} else {
		$filesystem->put_contents( $path . '/' . $index_name, $buffer, FS_CHMOD_FILE );
		$filesystem->touch( $path . '/' . $index_name, $modified_time );
	}

	do_action( 'powered_cache_page_cached', $buffer );

	header( 'Cache-Control: no-cache' ); // Check back every time to see if re-download is necessary

	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified_time ) . ' GMT' );

	if ( function_exists( 'ob_gzhandler' ) && $GLOBALS['powered_cache_options']['gzip_compression'] ) {
		return ob_gzhandler( $buffer, $flags );
	} else {
		return $buffer;
	}
}


/**
 * Optionally serve cache and exit
 *
 * @since 1.0
 */
function powered_cache_serve_cache() {
	global $powered_cache_slash_check;

	$path = rtrim( $GLOBALS['powered_cache_options']['cache_location'], '/' ) . '/powered-cache/' . rtrim( powered_cache_get_url_path(), '/' ) . '/';

	$meta_file = $path.'/meta.php';


	if ( @file_exists( $meta_file ) ) {
		$meta_contents = trim( file_get_contents( $meta_file ) );
		$meta_contents = str_replace( '<?php exit; ?>', '', $meta_contents );
		$meta_params   = unserialize( trim( $meta_contents ) );
		$header_params = $meta_params['headers'];
	}

	$content_type = @$header_params['Content-Type'];

	$file_name = powered_cache_index_file( $content_type );
	$file_path = $path.$file_name;

	$modified_time = (int) @filemtime( $file_path );

	header( 'Cache-Control: no-cache' ); // Check back in an hour

	if ( ! empty( $modified_time ) && ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $modified_time ) {
		  header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
		  exit;
	}

	// trailingslash check
	if ( isset( $powered_cache_slash_check ) && $powered_cache_slash_check ) {
		$current_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! empty( $current_path ) && '/' !== substr( $current_path, - 1 ) ) {
			header( 'X-Powered-Cache: Passing to WordPress' );

			return;
		}
	}

	if ( @file_exists( $file_path ) && @is_readable( $file_path ) ) {

		if ( ! empty( $header_params ) ) {
			foreach ( $header_params as $key => $response_header ) {
				header( $response_header );
			}
		}

		header( 'X-Powered-Cache: PHP' );

		if ( function_exists( 'gzencode' ) && $GLOBALS['powered_cache_options']['gzip_compression'] ) {
			header( 'Content-Encoding: gzip' );
		}

		@readfile( $file_path );

		exit;
	}
}

/**
 * Get URL path for caching
 *
 * @since  1.0
 * @return string
 */
function powered_cache_get_url_path() {

	$host        = ( isset( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : '';
	$request_uri = preg_replace( '/(\/+)/', '/', $_SERVER['REQUEST_URI'] );
	$request_uri = str_replace( '..', '', preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $request_uri ) );

	return rtrim( $host, '/' ) . $request_uri;
}

function powered_cache_get_user_cookie() {
	if ( empty( $_COOKIE ) ) {
		return false;
	}

	foreach ( $_COOKIE as $c_key => $val ) {
		if ( false !== strpos( $c_key, 'wordpress_logged_in_' ) ) {
			return $val;
		}
	}

	return false;
}


/**
 * Determines cache file names
 *
 * @param string $content_type
 *
 * @since 1.0
 * @since 1.2 `$content_type`
 * @return string
 */
function powered_cache_index_file( $content_type = 'text/html' ) {
	$file_name = 'index';

	if ( powered_cache_is_ssl() ) {
		$file_name .= '-https';
	}

	// separate file for mobile cache
	if ( isset( $GLOBALS['powered_cache_options']['cache_mobile'], $GLOBALS['powered_cache_options']['cache_mobile_separate_file'] )
	     && true === $GLOBALS['powered_cache_options']['cache_mobile']
	     && true === $GLOBALS['powered_cache_options']['cache_mobile_separate_file']
	) {

		global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

		$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_browsers ) ), ' ' );
		$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_prefixes ) ), ' ' );

		// Don't cache if mobile detection is activated
		if ( (preg_match( '#^.*(' . $mobile_browsers . ').*#i', $_SERVER['HTTP_USER_AGENT'] ) || preg_match( '#^(' . $mobile_prefixes . ').*#i', substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) ) ) ) {
			$file_name .= '-mobile';
		}
	}

	if ( isset( $GLOBALS['powered_cache_options']['loggedin_user_cache'] )
	     && true === $GLOBALS['powered_cache_options']['loggedin_user_cache']
	) {
		$usr_cookie = powered_cache_get_user_cookie();
		if ( false !== $usr_cookie ) {
			$cookie_info = explode( '|', $usr_cookie );

			// user specific cache dir
			$file_name .= '_' . $cookie_info[0] . '-' . $cookie_info[1];
		}
	}

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$file_name .= '_' . sha1( $_SERVER['QUERY_STRING'] );
	}

	/**
	 * Content-Type is not always text/html (like feed, wp-json etc..)
	 * We should respect proper format!
	 * Alternatively, we can add multiple level lookup for apache/nginx config
	 * but that makes things much more complicated.
	 */
	if ( false === strpos( $content_type, 'text/html' ) ) {
		$file_name .= '-' . substr( sha1( $content_type ), 0, 6 );
	}

	$file_name .= '.html';

	if ( function_exists( 'gzencode' ) && $GLOBALS['powered_cache_options']['gzip_compression'] ) {
		$file_name .= '_gzip';
	}

	return $file_name;
}


/**
 * is_ssl moved load.php in WP 4.6 but we support WP 4.1+
 *
 * @return bool
 */
function powered_cache_is_ssl() {
	if ( isset( $_SERVER['HTTPS'] ) ) {
		if ( 'on' == strtolower( $_SERVER['HTTPS'] ) ) {
			return true;
		}

		if ( '1' == $_SERVER['HTTPS'] ) {
			return true;
		}
	} elseif ( isset( $_SERVER['SERVER_PORT'] ) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
		return true;
	}

	return false;
}
