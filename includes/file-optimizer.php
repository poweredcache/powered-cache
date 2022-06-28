<?php
/**
 * Ported from https://github.com/Automattic/nginx-http-concat
 *
 * Concatenation script inspired by Nginx's ngx_http_concat and Apache's modconcat modules.
 *
 * It follows the same pattern for enabling the concatenation. It uses two ?, like this:
 * http://example.com/??style1.css,style2.css,foo/style3.css
 *
 * If a third ? is present it's treated as version string. Like this:
 * http://example.com/??style1.css,style2.css,foo/style3.css?v=102234
 *
 * It will also replace the relative paths in CSS files with absolute paths.
 *
 * @package PoweredCache
 */

namespace PoweredCache\FileOptimizer;

// phpcs:disable

use PoweredCache\Dependencies\MatthiasMullie\Minify\CSS;
use PoweredCache\Dependencies\MatthiasMullie\Minify\JS;

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
		$base_dir = __DIR__ . '/classes/';

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

/* Config */
$concat_max_files = 150;
$concat_unique    = true;
$concat_types     = array(
	'css' => 'text/css',
	'js'  => 'application/javascript',
);


$current_dir = file_optimizer_normalize_path( realpath( dirname( __DIR__ ) ) );

/* Constants */
// By default determine the document root from this scripts path in the plugins dir (you can hardcode this define)
define( 'CONCAT_FILES_ROOT', substr( $current_dir, 0, strpos( $current_dir, '/wp-content' ) ) );
define( 'POWERED_CACHE_FO_CACHE_DIR', CONCAT_FILES_ROOT . '/wp-content/cache/min/' );
define( 'POWERED_CACHE_FO_DEBUG', false );

if ( ! file_exists( POWERED_CACHE_FO_CACHE_DIR ) ) {
	mkdir( POWERED_CACHE_FO_CACHE_DIR, 0775, true );
}

function concat_http_status_exit( $status ) {
	switch ( $status ) {
		case 200:
			$text = 'OK';
			break;
		case 400:
			$text = 'Bad Request';
			break;
		case 403:
			$text = 'Forbidden';
			break;
		case 404:
			$text = 'Not found';
			break;
		case 500:
			$text = 'Internal Server Error';
			break;
		default:
			$text = '';
	}

	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if ( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol ) {
		$protocol = 'HTTP/1.0';
	}

	@header( "$protocol $status $text", true, $status );
	exit();
}

function concat_get_mtype( $file ) {
	global $concat_types;

	$lastdot_pos = strrpos( $file, '.' );
	if ( false === $lastdot_pos ) {
		return false;
	}

	$ext = substr( $file, $lastdot_pos + 1 );

	return isset( $concat_types[ $ext ] ) ? $concat_types[ $ext ] : false;
}

function concat_get_path( $uri ) {
	if ( ! strlen( $uri ) ) {
		maybe_add_debug_log( sprintf( "File Optimizer 400 - could not retrieve file path for uri %s", $uri ) );
		concat_http_status_exit( 400 );
	}

	if ( false !== strpos( $uri, '..' ) || false !== strpos( $uri, "\0" ) ) {
		maybe_add_debug_log( sprintf( "File Optimizer 400 - could not retrieve file path for uri %s", $uri ) );
		concat_http_status_exit( 400 );
	}

	return CONCAT_FILES_ROOT . ( '/' != $uri[0] ? '/' : '' ) . $uri;
}

function relative_path_replace( $buf, $dirpath ) {
	// url(relative/path/to/file) -> url(/absolute/and/not/relative/path/to/file)
	$buf = preg_replace(
		'/(:?\s*url\s*\()\s*(?:\'|")?\s*([^\/\'"\s\)](?:(?<!data:|http:|https:|[\(\'"]#|%23).)*)[\'"\s]*\)/isU',
		'$1' . ( '/' === $dirpath ? '/' : $dirpath . '/' ) . '$2)',
		$buf
	);

	return $buf;
}

/* Main() */
if ( ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ) ) ) {
	maybe_add_debug_log( sprintf( "File Optimizer 400 - unsupported request method: %s", $_SERVER['REQUEST_METHOD'] ) );
	concat_http_status_exit( 400 );
}

// /_static/??/foo/bar.css,/foo1/bar/baz.css?m=293847g
// or
// /_static/??-eJzTT8vP109KLNJLLi7W0QdyDEE8IK4CiVjn2hpZGluYmKcDABRMDPM=
$args = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
if ( ! $args || false === strpos( $args, '?' ) ) {
	maybe_add_debug_log( sprintf( "File Optimizer 400 - empty query arg or not ? exists" ) );
	concat_http_status_exit( 400 );
}

$args = substr( $args, strpos( $args, '?' ) + 1 );
$args = str_replace( [ '&minify=1', '&minify=0' ], '', $args ); // remove minify parameter

// /foo/bar.css,/foo1/bar/baz.css?m=293847g
// or
// -eJzTT8vP109KLNJLLi7W0QdyDEE8IK4CiVjn2hpZGluYmKcDABRMDPM=
if ( '-' == $args[0] ) {
	$args = @gzuncompress( base64_decode( substr( $args, 1 ) ) );

	// Invalid data, abort!
	if ( false === $args ) {
		maybe_add_debug_log( sprintf( "File Optimizer 400 - Invalid Data" ) );
		concat_http_status_exit( 400 );
	}
}

// /foo/bar.css,/foo1/bar/baz.css?m=293847g
$version_string_pos = strpos( $args, '?' );
if ( false !== $version_string_pos ) {
	$args = substr( $args, 0, $version_string_pos );
}

// /foo/bar.css,/foo1/bar/baz.css
$args = explode( ',', $args );
if ( ! $args ) {
	maybe_add_debug_log( sprintf( "File Optimizer 400 - Empty args" ) );
	concat_http_status_exit( 400 );
}

// array('/wp-content/foo/bar.css','//cdn.cname.com/wp-content/foo/bar.css')
// get real path when it masked from cdn
foreach ( $args as $index => $arg ) {
	if ( 0 === stripos( $arg, '//' ) ) {
		$args[ $index ] = parse_url( str_replace( '//', 'http://', $arg ), PHP_URL_PATH );
	}
}

// array( '/foo/bar.css', '/foo1/bar/baz.css' )
if ( 0 == count( $args ) || count( $args ) > $concat_max_files ) {
	maybe_add_debug_log( sprintf( "File Optimizer 400 - Concating too many or zero file: %s files on queue", count( $args ) ) );
	concat_http_status_exit( 400 );
}

// If we're in a subdirectory context, use that as the root.
// We can't assume that the root serves the same content as the subdir.
$subdir_path_prefix = '';
$request_path       = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$_static_index      = strpos( $request_path, '/_static/' );
if ( $_static_index > 0 ) {
	$subdir_path_prefix = substr( $request_path, 0, $_static_index );
}
unset( $request_path, $_static_index );

$last_modified = 0;
$pre_output    = '';
$output        = '';

$do_minify   = (bool) stripos( $_SERVER['REQUEST_URI'], 'minify=1' );
$hash        = sha1( $_SERVER['REQUEST_URI'] );
$latest_file = end( $args );

$cache_file_name = POWERED_CACHE_FO_CACHE_DIR . $hash;
if ( 'application/javascript' == concat_get_mtype( $latest_file ) ) {
	$cache_file_name .= '.js';
} elseif ( 'text/css' == concat_get_mtype( $latest_file ) ) {
	$cache_file_name .= '.css';
}

if ( file_exists( $cache_file_name ) ) {
	$buf = @file_get_contents( $cache_file_name );
	if ( $buf ) {
		$stat = stat( $cache_file_name );

		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $stat['mtime'] ) . ' GMT' );
		header( 'Content-Length: ' . ( strlen( $pre_output ) + strlen( $buf ) ) );
		header( 'Content-Type: ' . concat_get_mtype( $latest_file ) );

		echo $pre_output . $buf;
		exit;
	}
}

foreach ( $args as $uri ) {

	$fullpath = concat_get_path( $uri );

	if ( ! file_exists( $fullpath ) ) {
		maybe_add_debug_log( sprintf( "File Optimizer 404 - Missing file: %s", $fullpath ) );
		concat_http_status_exit( 404 );
	}

	$mime_type = concat_get_mtype( $fullpath );
	if ( ! in_array( $mime_type, $concat_types ) ) {
		maybe_add_debug_log( sprintf( "File Optimizer 400 - Unsupported mime type: %s", $mime_type ) );
		concat_http_status_exit( 400 );
	}

	if ( $concat_unique ) {
		if ( ! isset( $last_mime_type ) ) {
			$last_mime_type = $mime_type;
		}

		if ( $last_mime_type != $mime_type ) {
			maybe_add_debug_log( sprintf( "File Optimizer 400 - Different mime type: last mime %s vs current mime: %s", $last_mime_type, $mime_type ) );
			concat_http_status_exit( 400 );
		}
	}

	$stat = stat( $fullpath );
	if ( false === $stat ) {
		maybe_add_debug_log( sprintf( "File Optimizer 500 - false stat" ) );
		concat_http_status_exit( 500 );
	}

	if ( $stat['mtime'] > $last_modified ) {
		$last_modified = $stat['mtime'];
	}

	$buf = file_get_contents( $fullpath );
	if ( false === $buf ) {
		maybe_add_debug_log( sprintf( "File Optimizer 500 - false buffer" ) );
		concat_http_status_exit( 500 );
	}

	if ( 'text/css' == $mime_type ) {
		$dirpath = $subdir_path_prefix . dirname( $uri );

		// url(relative/path/to/file) -> url(/absolute/and/not/relative/path/to/file)
		$buf = relative_path_replace( $buf, $dirpath );

		// AlphaImageLoader(...src='relative/path/to/file'...) -> AlphaImageLoader(...src='/absolute/path/to/file'...)
		$buf = preg_replace(
			'/(Microsoft.AlphaImageLoader\s*\([^\)]*src=(?:\'|")?)([^\/\'"\s\)](?:(?<!http:|https:).)*)\)/isU',
			'$1' . ( $dirpath == '/' ? '/' : $dirpath . '/' ) . '$2)',
			$buf
		);

		// The @charset rules must be on top of the output
		if ( 0 === strpos( $buf, '@charset' ) ) {
			preg_replace_callback(
				'/(?P<charset_rule>@charset\s+[\'"][^\'"]+[\'"];)/i',
				function ( $match ) {
					global $pre_output;

					if ( 0 === strpos( $pre_output, '@charset' ) ) {
						return '';
					}

					$pre_output = $match[0] . "\n" . $pre_output;

					return '';
				},
				$buf
			);
		}

		// Move the @import rules on top of the concatenated output.
		// Only @charset rule are allowed before them.
		if ( false !== strpos( $buf, '@import' ) ) {
			$buf = preg_replace_callback(
				'/(?P<pre_path>@import\s+(?:url\s*\()?[\'"\s]*)(?P<path>[^\'"\s](?:https?:\/\/.+\/?)?.+?)(?P<post_path>[\'"\s\)]*;)/i',
				function ( $match ) use ( $dirpath ) {
					global $pre_output;

					if ( 0 !== strpos( $match['path'], 'http' ) && '/' != $match['path'][0] ) {
						$pre_output .= $match['pre_path'] . ( $dirpath == '/' ? '/' : $dirpath . '/' ) .
						               $match['path'] . $match['post_path'] . "\n";
					} else {
						$pre_output .= $match[0] . "\n";
					}

					return '';
				},
				$buf
			);
		}
	}

	if ( 'application/javascript' == $mime_type ) {
		$output .= "$buf;\n";
	} else {
		$output .= "$buf";
	}
}

if ( $do_minify && false === stripos( $uri, '.min' ) ) {
	if ( 'application/javascript' == $mime_type ) {
		$js_minify = new JS( $output );
		$output    = $js_minify->minify();
	} elseif ( 'text/css' == $mime_type ) {
		$css_minify = new CSS( $output );
		$output     = $css_minify->minify();
	}
}

header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
header( 'Content-Length: ' . ( strlen( $pre_output ) + strlen( $output ) ) );
header( "Content-Type: $mime_type" );

echo $pre_output . $output;

$cache_file_name = POWERED_CACHE_FO_CACHE_DIR . $hash;
if ( 'application/javascript' == $mime_type ) {
	$cache_file_name .= '.js';
} elseif ( 'text/css' == $mime_type ) {
	$cache_file_name .= '.css';
}

file_put_contents( $cache_file_name, $pre_output . $output );


/**
 * Normalize a filesystem path.
 *
 * On windows systems, replaces backslashes with forward slashes
 * and forces upper-case drive letters.
 * Allows for two leading slashes for Windows network shares, but
 * ensures that all other duplicate slashes are reduced to a single.
 *
 * @param string $path Path to normalize.
 *
 * @return string Normalized path.
 * @see wp_normalize_path
 *
 */
function file_optimizer_normalize_path( $path ) {
	$wrapper = '';

	// Standardise all paths to use '/'.
	$path = str_replace( '\\', '/', $path );

	// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
	$path = preg_replace( '|(?<=.)/+|', '/', $path );

	// Windows paths should uppercase the drive letter.
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}

	return $wrapper . $path;
}

/**
 * Maybe add debug message
 *
 * @param string $message Debug message
 *
 * @since 2.3
 */
function maybe_add_debug_log( $message ) {
	if ( defined( 'POWERED_CACHE_FO_DEBUG' ) && POWERED_CACHE_FO_DEBUG ) {
		error_log( $message );
	}
}
