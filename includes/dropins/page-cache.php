<?php
/**
 * Forked from https://github.com/tlovett1/simple-cache/
 */

defined( 'ABSPATH' ) || exit;

$powered_cache_start_time = microtime( true );

// Don't cache robots.txt or htacesss
if ( strpos( $_SERVER['REQUEST_URI'], 'robots.txt' ) !== false || strpos( $_SERVER['REQUEST_URI'], '.htaccess' ) !== false ) {
	powered_cache_add_cache_miss_header( "Uncacheable file" );

	return;
}

// Don't cache non-GET requests
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
	powered_cache_add_cache_miss_header( "Invalid request method" );

	return;
}

if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
	$_SERVER['HTTP_USER_AGENT'] = '';
}

// Don't cache wp-admin
if ( is_admin() ) {
	return;
}

$file_extension = $_SERVER['REQUEST_URI'];
$file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $file_extension );
$file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

// Don't cache disallowed extensions. Prevents wp-cron.php, xmlrpc.php, etc.
if ( ! preg_match( '#index\.php$#i', $_SERVER['REQUEST_URI'] ) && in_array( $file_extension, array( 'php', 'xml', 'xsl' ), true ) ) {
	powered_cache_add_cache_miss_header( "Disallowed file extension" );

	return;
}

if ( ! $GLOBALS['powered_cache_options']['enable_page_cache'] ) {
	powered_cache_add_cache_miss_header( "Page Caching is not enabled" );

	return;
}

if ( isset( $_GET['nopoweredcache'] ) && $_GET['nopoweredcache'] ) {
	powered_cache_add_cache_miss_header( "Passing nopoweredcache with the query" );

	return;
}

// Don't cache page with these user agents
if ( isset( $powered_cache_rejected_user_agents ) && ! empty( $powered_cache_rejected_user_agents ) ) {
	$rejected_user_agents = implode( '|', $powered_cache_rejected_user_agents );
	if ( ! empty( $rejected_user_agents ) && isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '#(' . $rejected_user_agents . ')#', $_SERVER['HTTP_USER_AGENT'] ) ) {
		powered_cache_add_cache_miss_header( "Rejected user agent" );

		return;
	}
}

// dont cache mobile
if ( empty( $GLOBALS['powered_cache_options']['cache_mobile'] ) ) {
	global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes;

	$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_browsers ) ), ' ' );
	$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_prefixes ) ), ' ' );
	// Don't cache if mobile detection is activated
	if ( ( preg_match( '#^.*(' . $mobile_browsers . ').*#i', $_SERVER['HTTP_USER_AGENT'] ) || preg_match( '#^(' . $mobile_prefixes . ').*#i', substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) ) ) ) {
		powered_cache_add_cache_miss_header( "Mobile request" );

		return;
	}
}


if ( ! empty( $_COOKIE ) ) {
	$wp_cookies = array( 'wordpressuser_', 'wordpresspass_', 'wordpress_sec_', 'wordpress_logged_in_' );

	//Don't cache if logged in
	if ( ! isset( $GLOBALS['powered_cache_options']['loggedin_user_cache'] ) || false === $GLOBALS['powered_cache_options']['loggedin_user_cache'] ) {
		// check logged-in cookie
		foreach ( $_COOKIE as $key => $value ) {
			foreach ( $wp_cookies as $cookie ) {
				if ( strpos( $key, $cookie ) !== false ) {
					// Logged in!
					powered_cache_add_cache_miss_header( "User logged-in" );

					return;
				}
			}
		}
	}


	if ( ! empty( $_COOKIE['powered_cache_commented_posts'] ) ) {
		foreach ( $_COOKIE['powered_cache_commented_posts'] as $path ) {
			if ( rtrim( $path, '/' ) === rtrim( $_SERVER['REQUEST_URI'], '/' ) ) {
				// User commented on this post
				powered_cache_add_cache_miss_header( "User commented" );

				return;
			}
		}
	}

	// don't cache specific cookie
	if ( isset( $powered_cache_rejected_cookies ) && ! empty( $powered_cache_rejected_cookies ) ) {
		$rejected_cookies = array_diff( $powered_cache_rejected_cookies, $wp_cookies ); // use diff in case caching for logged-in user
		$rejected_cookies = implode( '|', $rejected_cookies );
		if ( preg_match( '#(' . $rejected_cookies . ')#', var_export( $_COOKIE, true ) ) ) {
			powered_cache_add_cache_miss_header( "Rejected cookie" );

			return;
		}
	}
}

// Don't cache rejected pages
if ( ! empty( $powered_cache_rejected_uri ) ) {
	foreach ( (array) $powered_cache_rejected_uri as $exception ) {
		if ( preg_match( '#^[\s]*$#', $exception ) ) {
			continue;
		}

		// full url exception
		if ( preg_match( '#^https?://#', $exception ) ) {
			$exception = parse_url( $exception, PHP_URL_PATH );
		}

		if ( empty( $exception ) ) {
			continue;
		}

		if ( preg_match( '#^(' . $exception . ')$#', $_SERVER['REQUEST_URI'] ) ) {
			powered_cache_add_cache_miss_header( "Rejected page" );

			return;
		}
	}
}


if ( ! empty( $_GET ) ) {
	if ( ! isset( $powered_cache_ignored_query_strings ) ) {
		$powered_cache_ignored_query_strings = [];
	}

	$query_params = array_diff_key( $_GET, array_flip( $powered_cache_ignored_query_strings ) );

	if ( ! isset( $powered_cache_cache_query_strings ) ) {
		$powered_cache_cache_query_strings = [];
	}

	// don't cache when there is not allowed query parameter exists
	if ( ! empty( $query_params ) && ! array_intersect_key( $_GET, array_flip( $powered_cache_cache_query_strings ) ) ) {
		powered_cache_add_cache_miss_header( "Disallowed query parameter exists" );

		return;
	}
}

powered_cache_serve_cache();

ob_start( 'powered_cache_page_buffer' );

/**
 * Cache output before it goes to the browser
 *
 * @param string $buffer
 * @param int    $flags
 *
 * @return string
 * @since  1.0
 */
function powered_cache_page_buffer( $buffer, $flags ) {
	global $powered_cache_start_time, $post;

	if ( strlen( $buffer ) < 255 ) {
		return $buffer;
	}

	// maybe we shouldn't cache template file has this constant
	// dont check DONOTCACHEPAGE strictly some plugins define string instead bool flag
	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
		\PoweredCache\Utils\log( sprintf( 'DONOTCACHEPAGE DEFINED on %s', $_SERVER['REQUEST_URI'] ) );
		powered_cache_add_cache_miss_header( "DONOTCACHEPAGE defined" );

		return $buffer;
	}

	// Don't cache password protected posts
	if ( ! empty( $post->post_password ) ) {
		powered_cache_add_cache_miss_header( "Ppassword protected posts are not cached" );

		return $buffer;
	}

	// Don't cache 404 results
	if ( function_exists( 'is_404' ) && is_404() ) {
		powered_cache_add_cache_miss_header( "404 pages are not cached" );

		return $buffer;
	}

	// Don't cache search results
	if ( function_exists( 'is_search' ) && is_search() ) {
		powered_cache_add_cache_miss_header( "Search result page is not cached" );

		return $buffer;
	}

	/**
	 * Filter whether to enable page cache for this request
	 *
	 * @hook  powered_cache_page_cache_enable
	 *
	 * @param {boolean} $enable true for caching
	 *
	 * @since 1.0
	 */
	if ( true !== apply_filters( 'powered_cache_page_cache_enable', true ) ) {
		powered_cache_add_cache_miss_header( "Page Cache not enabled for this post" );

		return $buffer;
	}

	// only cache when http ok
	if ( 200 !== http_response_code() ) {
		powered_cache_add_cache_miss_header( "Response code is not 200" );

		return $buffer;
	}

	if ( ! function_exists( '\PoweredCache\Utils\get_cache_dir' ) ) {
		return $buffer;
	}

	// Make sure we can read/write files and that proper folders exist
	if ( ! file_exists( untrailingslashit( \PoweredCache\Utils\get_cache_dir() ) ) ) {
		if ( ! @mkdir( untrailingslashit( \PoweredCache\Utils\get_cache_dir() ) ) ) {
			// Can not cache!
			powered_cache_add_cache_miss_header( "The cache directory does not exist for storing cached output" );

			return $buffer;
		}
	}

	if ( ! file_exists( \PoweredCache\Utils\get_page_cache_dir() ) ) {
		if ( ! @mkdir( \PoweredCache\Utils\get_page_cache_dir() ) ) {
			// Can not cache!
			powered_cache_add_cache_miss_header( "The page cache directory does not exist for storing cached output" );

			return $buffer;
		}
	}

	/**
	 * Filters HTML buffer
	 *
	 * @hook   powered_cache_page_caching_buffer
	 *
	 * @param  {string} $buffer Output buffer.
	 *
	 * @return {string} New value.
	 * @since  1.0
	 */
	$buffer = apply_filters( 'powered_cache_page_caching_buffer', $buffer );

	$url_path = powered_cache_get_url_path();

	$dirs = explode( '/', $url_path );

	$path = untrailingslashit( \PoweredCache\Utils\get_page_cache_dir() );

	foreach ( $dirs as $dir ) {
		if ( ! empty( $dir ) ) {
			$path .= '/' . $dir;

			if ( ! file_exists( $path ) ) {
				if ( ! @mkdir( $path ) ) {
					// Can not cache!
					return $buffer;
				}
			}
		}
	}

	$modified_time   = time(); // Make sure modified time is consistent
	$generation_time = number_format( microtime( true ) - $powered_cache_start_time, 3 );

	$home_url = get_home_url();
	// prevent mixed content
	if ( ! is_ssl() && 'https' === strtolower( parse_url( $home_url, PHP_URL_SCHEME ) ) ) {
		$https_home_url = $home_url;
		$http_home_url  = str_replace( 'https://', 'http://', $https_home_url );
		$buffer         = str_replace( esc_url( $http_home_url ), esc_url( $https_home_url ), $buffer );
	}

	if ( array_key_exists( 'cache_footprint', $GLOBALS['powered_cache_options'] ) && true === $GLOBALS['powered_cache_options']['cache_footprint'] ) {
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
	$meta_file      = '<?php exit; ?>' . PHP_EOL;

	$meta_params = array(); // holds to metadata for cached file

	$response_headers = \PoweredCache\Utils\get_response_headers();

	foreach ( (array) $response_headers as $key => $value ) {
		$meta_params['headers'][ $key ] = "$key: $value";
	}

	/**
	 * Filters meta parameters.
	 *
	 * @hook   powered_cache_page_cache_meta_params
	 *
	 * @param  {array} $meta_params Meta parameters.
	 * @param  {array} $response_headers Supported response header list.
	 *
	 * @return {array} New value.
	 * @since  1.2
	 */
	$meta_params        = apply_filters( 'powered_cache_page_cache_meta_params', $meta_params, $response_headers );
	$meta_file_contents = $meta_file . json_encode( $meta_params );

	/**
	 * Filters meta file contents.
	 *
	 * @hook   powered_cache_page_cache_meta_info
	 *
	 * @param  {string} $meta_file_contents The content of the meta file.*
	 *
	 * @return {array} New value.
	 * @since  1.2
	 */
	$meta_file_contents = apply_filters( 'powered_cache_page_cache_meta_info', $meta_file_contents );

	file_put_contents( $path . '/' . $meta_file_name, $meta_file_contents );
	touch( $path . '/' . $meta_file_name, $modified_time );

	if ( ! empty( $meta_params['headers']['Content-Type'] ) ) {
		$index_name = powered_cache_index_file( $meta_params['headers']['Content-Type'] );
	} else {
		$index_name = powered_cache_index_file();
	}

	if ( $GLOBALS['powered_cache_options']['gzip_compression'] && function_exists( 'gzencode' ) ) {
		file_put_contents( $path . '/' . $index_name, gzencode( $buffer, 3 ) );
		touch( $path . '/' . $index_name, $modified_time );
	} else {
		file_put_contents( $path . '/' . $index_name, $buffer );
		touch( $path . '/' . $index_name, $modified_time );
	}

	/**
	 * Fires after caching a page.
	 *
	 * @hook  powered_cache_page_cached
	 *
	 * @param {string} $buffer HTML Output.
	 *
	 * @since 1.0
	 */
	do_action( 'powered_cache_page_cached', $buffer );

	header( 'Cache-Control: no-cache' ); // Check back every time to see if re-download is necessary

	header( 'X-Powered-Cache: MISS' );

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

	$meta_file = $path . '/meta.php';

	$header_params = [];
	$content_type  = 'text/html';

	if ( @file_exists( $meta_file ) ) {
		$meta_contents = trim( file_get_contents( $meta_file ) );
		$meta_contents = str_replace( '<?php exit; ?>', '', $meta_contents );
		$meta_params   = json_decode( trim( $meta_contents ), true );
		$header_params = $meta_params['headers'];
	}

	if ( ! empty( $header_params['Content-Type'] ) ) {
		$content_type = $header_params['Content-Type'];
	}


	$file_name = powered_cache_index_file( $content_type );
	$file_path = $path . $file_name;

	// check file exists?
	if ( ! file_exists( $file_path ) ) {
		return;
	}

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
		header( 'X-Cache-Enabled: true' );
		header( sprintf( "age: %d",  time() - filemtime( $file_path ) ) );

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
 * @return string
 * @since  1.0
 * @since  2.0 $request_uri without query string
 */
function powered_cache_get_url_path() {
	$host        = ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );
	$request_uri = explode( '?', $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = reset( $request_uri );
	$request_uri = preg_replace( '/(\/+)/', '/', $request_uri );
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
 * @return string
 * @since 1.2 `$content_type`
 * @since 1.0
 */
function powered_cache_index_file( $content_type = 'text/html' ) {
	global $powered_cache_mobile_browsers, $powered_cache_mobile_prefixes, $powered_cache_vary_cookies, $powered_cache_cache_query_strings;

	$file_name = 'index';

	if ( is_ssl() ) {
		$file_name .= '-https';
	}

	// separate file for mobile cache
	if ( ! empty( $GLOBALS['powered_cache_options']['cache_mobile'] ) && ! empty( $GLOBALS['powered_cache_options']['cache_mobile_separate_file'] ) ) {
		$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_browsers ) ), ' ' );
		$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', $powered_cache_mobile_prefixes ) ), ' ' );

		// Don't cache if mobile detection is activated
		if ( ( preg_match( '#^.*(' . $mobile_browsers . ').*#i', $_SERVER['HTTP_USER_AGENT'] ) || preg_match( '#^(' . $mobile_prefixes . ').*#i', substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) ) ) ) {
			$file_name .= '-mobile';
		}
	}

	if ( ! empty( $GLOBALS['powered_cache_options']['loggedin_user_cache'] ) ) {
		$usr_cookie = powered_cache_get_user_cookie();
		if ( false !== $usr_cookie ) {
			$cookie_info = explode( '|', $usr_cookie );

			// user specific cache dir
			$file_name .= '_' . $cookie_info[0] . '-' . $cookie_info[1];
		}
	}

	// change filename based on vary cookies
	if ( ! empty( $powered_cache_vary_cookies ) ) {
		$cookie_file_name = '';
		foreach ( $powered_cache_vary_cookies as $key => $vary_cookie ) {
			if ( is_array( $vary_cookie ) ) {
				if ( ! empty( $_COOKIE[ $key ] ) ) {
					foreach ( $vary_cookie as $vary_sub_cookie ) {
						if ( isset( $_COOKIE[ $key ][ $vary_sub_cookie ] ) ) {
							$cookie_value     = preg_replace( '/[^A-Za-z0-9. -]/', '', $_COOKIE[ $key ][ $vary_sub_cookie ] );
							$cookie_file_name .= strtolower( $key . $vary_sub_cookie . $cookie_value );
						}
					}
				}

				continue;
			}

			if ( isset( $_COOKIE[ $vary_cookie ] ) && ! empty( $_COOKIE[ $vary_cookie ] ) ) {
				$cookie_value     = preg_replace( '/[^A-Za-z0-9. -]/', '', $_COOKIE[ $vary_cookie ] );
				$cookie_file_name .= strtolower( $vary_cookie . $cookie_value );
			}
		}

		if ( ! empty( $cookie_file_name ) ) {
			/**
			 * Hashing for no particular reason
			 * preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $cookie_file_name ) )
			 * can create a messy filename
			 */
			$file_name .= '-' . sha1( $cookie_file_name );
		}

	}

	// change filename for provided cache query string
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		parse_str( $_SERVER['QUERY_STRING'], $query_string );
		$qs_variable = '';
		sort( $powered_cache_cache_query_strings );
		foreach ( $powered_cache_cache_query_strings as $query_parameter ) {
			if ( isset( $query_string[ $query_parameter ] ) ) {
				$qs_variable .= '_' . $query_parameter;
				$qs_variable .= is_array( $query_string[ $query_parameter ] ) ? implode( '|', $query_string[ $query_parameter ] ) : $query_string[ $query_parameter ];
			}
		}

		if ( ! empty( $qs_variable ) ) {
			$qs_variable = 'query_' . $qs_variable;
			$file_name   .= '_' . sha1( $qs_variable );
		}
	}


	/**
	 * Content-Type is not always text/html (like feed, wp-json etc..)
	 * Adding hash by simply escaping from rewrite matches in htaccess or nginx
	 * Different types of content should be serve via PHP, in order to restore header info
	 */
	if ( false === strpos( $content_type, 'text/html' ) ) {
		$file_name .= '-' . substr( sha1( $content_type ), 0, 6 );
	}


	$file_name .= '.html';

	if ( function_exists( 'gzencode' ) && $GLOBALS['powered_cache_options']['gzip_compression'] ) {
		$file_name .= '.gz';
	}

	return $file_name;
}

/**
 * Add cache miss header and reason
 *
 * @param string $reason Cache Miss info
 *
 * @since 2.2
 */
function powered_cache_add_cache_miss_header( $reason ) {
	if ( headers_sent() ) {
		return;
	}

	header( 'X-Powered-Cache: MISS' );

	if ( ( defined( 'POWERED_CACHE_ENABLE_LOG' ) && POWERED_CACHE_ENABLE_LOG )
	     || ( defined( 'POWERED_CACHE_MISS_REASON' ) && POWERED_CACHE_MISS_REASON )
	) {
		header( "X-Powered-Cache-Miss-Reason: $reason" );
	}
}
