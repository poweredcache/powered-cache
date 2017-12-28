<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Powered_Cache_Admin_Actions {

	/**
	 * Save settings to database and file
	 *
	 * @since 1.0
	 */
	public static function update_settings() {
		global $powered_cache_options, $powered_cache_fs, $is_apache;

		$old_options = $powered_cache_options;

		$new_options = array();
		$_post = ( isset( $_POST['powered_cache_settings'] ) ? $_POST['powered_cache_settings'] : array() );

		if ( ! isset( $_REQUEST['section'] ) ) {
			$_REQUEST['section'] = 'basic-options';
		}

		switch ( $_REQUEST['section'] ) {
			case 'basic-options':
			default:
				$new_options['enable_page_caching']        = ( isset( $_post['enable_page_caching'] ) && 1 == $_post['enable_page_caching'] ? true : false );
				$new_options['configure_htaccess']         = ( isset( $_post['configure_htaccess'] ) && 1 == $_post['configure_htaccess'] ? true : false );
				$new_options['object_cache']               = ( isset( $_post['object_cache'] ) ? sanitize_text_field( $_post['object_cache'] ) : 'off' );
				$new_options['cache_mobile']               = ( isset( $_post['cache_mobile'] ) && 1 == $_post['cache_mobile'] ? true : false );
				$new_options['cache_mobile_separate_file'] = ( true === $new_options['cache_mobile'] && ( isset( $_post['cache_mobile_separate_file'] ) && 1 == $_post['cache_mobile_separate_file'] ) ? true : false );
				$new_options['loggedin_user_cache']        = ( isset( $_post['loggedin_user_cache'] ) && 1 == $_post['loggedin_user_cache'] ? true : false );
				$new_options['ssl_cache']                  = ( isset( $_post['ssl_cache'] ) && 1 == $_post['ssl_cache'] ? true : false );
				$new_options['gzip_compression']           = ( isset( $_post['gzip_compression'] ) && 1 == $_post['gzip_compression'] ? true : false );


				// this option only valid on apache
				if ( ! $is_apache ) {
					unset( $new_options['configure_htaccess'] );
				}

				if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
					unset( $new_options['configure_htaccess'] );
				}

				/**
				 * Calculate timeout with given interval, saving as minutes.
				 *
				 * @since 1.1
				 */
				$cache_timeout = ( isset( $_post['cache_timeout'] ) ? intval( $_post['cache_timeout'] ) : 1440 );

				if ( $cache_timeout > 0 && isset( $_post['cache_timeout_interval'] ) ) {
					switch ( $_post['cache_timeout_interval'] ) {
						case 'DAY':
							$cache_timeout = $cache_timeout * 1440;
							break;
						case 'HOUR':
							$cache_timeout = $cache_timeout * 60;
							break;
						case 'MINUTE':
						default:
							$cache_timeout = $cache_timeout * 1;
					}
				}

				$new_options['cache_timeout'] = $cache_timeout;

				break;
			case 'advanced-options':
				$new_options['remove_query_string']        = ( isset( $_post['remove_query_string'] ) && 1 == $_post['remove_query_string'] ? true : false );
				$new_options['rejected_user_agents']   = ( strlen( trim( $_post['rejected_user_agents'] ) ) > 0 ? wp_kses_post( $_post['rejected_user_agents'] ) : '' );
				$new_options['rejected_cookies']       = ( strlen( trim( $_post['rejected_cookies'] ) ) > 0 ? wp_kses_post( $_post['rejected_cookies'] ) : '' );
				$new_options['rejected_uri']           = ( strlen( trim( $_post['rejected_uri'] ) ) > 0 ? wp_kses_post( $_post['rejected_uri'] ) : '' );
				$new_options['accepted_query_strings'] = ( strlen( trim( $_post['accepted_query_strings'] ) ) > 0 ? wp_kses_post( $_post['accepted_query_strings'] ) : '' );
				$new_options['purge_additional_pages'] = ( strlen( trim( $_post['purge_additional_pages'] ) ) > 0 ? wp_kses_post( $_post['purge_additional_pages'] ) : '' );
				break;
			case 'cdn':
				$new_options['cdn_status']     = ( isset( $_post['cdn_status'] ) && 1 == $_post['cdn_status'] ? true : false );
				$new_options['cdn_ssl_disable'] = ( isset( $_post['cdn_ssl_disable'] ) && 1 == $_post['cdn_ssl_disable'] ? true : false );

				// prepare hostname + zone pair
				if ( ! empty( $_post['cdn_hostname'] ) && is_array( $_post['cdn_hostname'] ) ) {
					foreach ( $_post['cdn_hostname'] as $cdn_key => $hostname ) {
						if ( ! empty( $hostname ) && isset( $_post['cdn_zone'][ $cdn_key ] ) && ! empty( $_post['cdn_zone'][ $cdn_key ] ) ) {
							$new_options['cdn_hostname'][ $cdn_key ] = esc_url_raw( $hostname );
							$new_options['cdn_zone'][ $cdn_key ]     = sanitize_text_field( $_post['cdn_zone'][ $cdn_key ] );
						}
					}
				}

				$new_options['cdn_rejected_files'] = ( strlen( trim( $_post['cdn_rejected_files'] ) ) > 0 ? wp_kses_post( $_post['cdn_rejected_files'] ) : '' );

				break;
			case 'extensions':
				$extension = sanitize_text_field( $_REQUEST['extension'] );

				if ( ! empty( $extension ) && isset( $_REQUEST['status'] ) ) {
					if ( 'activate' === $_REQUEST['status'] ) {
						Powered_Cache_Extensions::factory()->activate( $extension );

						$msg = __( 'Extension activated', 'powered-cache' );
						Powered_Cache_Admin_Helper::set_flash_message( $msg );
					} elseif ( 'deactivate' === $_REQUEST['status'] ) {
						Powered_Cache_Extensions::factory()->deactivate( $extension );

						$msg = __( 'Extension deactivated', 'powered-cache' );
						Powered_Cache_Admin_Helper::set_flash_message( $msg );
					}

					self::exit_with_redirect();
				}

				break;
			/**
			 * Handle misc options
			 * Some of misc actions might work as GET request.So we don't need to handle here.
			 *
			 * @since 1.0
			 */
			case 'misc':
				if ( isset( $_POST['do-import'] ) && ! empty( $_POST['do-import'] ) ) {
					if ( ! function_exists( 'wp_handle_upload' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/file.php' );
					}
					$uploadedfile = $_FILES['powered_cache_import'];

					$import_file = wp_handle_upload( $uploadedfile, array( 'action' => 'powered_cache_update_settings', 'mimes' => array( 'txt' => 'text/plain' ) ) );

					if ( $import_file && ! isset( $import_file['error'] ) ) {
						$imported_options = $powered_cache_fs->get_contents( $import_file['file'] );
						$imported_options = unserialize( $imported_options );
						$imported_options['cache_location'] = powered_cache_get_cache_dir();
						$powered_cache_fs->delete( $import_file['file'] );
						update_option( 'powered_cache_settings', $imported_options );
						$new_options = get_option( 'powered_cache_settings' );
					} else {
						Powered_Cache_Admin_Helper::set_flash_message( $import_file['error'], 'error' );
					}

				} else {
					$new_options['show_cache_message'] = ( isset( $_post['show_cache_message'] ) && 1 == $_post['show_cache_message'] ? true : false );
				}
		}

		if ( powered_cache_save_settings( $old_options, $new_options ) ) {
			$msg = __( 'Options updated', 'powered-cache' );
			Powered_Cache_Admin_Helper::set_flash_message( $msg );

			self::exit_with_redirect();
		}

		// false message
		$msg = __( 'Something went wrong, we could not save settings to file. Please make sure the configuration file writable.', 'powered-cache' );
		Powered_Cache_Admin_Helper::set_flash_message( $msg, 'error' );

		self::exit_with_redirect();
	}


	/**
	 * Reset settings
	 *
	 * @since 1.0
	 */
	public static function reset_settings() {
		// purge cache stuff
		powered_cache_clean_site_cache_dir();
		delete_option( 'powered_cache_settings' );

		global $powered_cache_options;
		$powered_cache_options = array();

		powered_cache_save_settings( $powered_cache_options, powered_cache_get_settings() );

		$msg = __( 'All settings cleaned!', 'powered-cache' );
		Powered_Cache_Admin_Helper::set_flash_message( $msg );

		self::exit_with_redirect();
	}

	/**
	 * Exports settings
	 *
	 * @since 1.0
	 */
	public static function export_settings() {
		$filename = sprintf( 'powered-cache-settings-%s-%s.txt', date( 'Y-m-d' ), uniqid() );
		$options  = serialize( get_option( 'powered_cache_settings' ) );
		nocache_headers();
		@header( 'Content-Type: text/plain' );
		@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		@header( 'Content-Transfer-Encoding: binary' );
		@header( 'Content-Length: ' . strlen( $options ) );
		@header( 'Connection: close' );
		echo $options;
		exit;
	}


	/**
	 * We use redirect after action occurred
	 *
	 * @since 1.0
	 */
	public static function exit_with_redirect() {

		$referer = wp_get_raw_referer();
		if ( false === $referer ) {
			$referer = admin_url( 'admin.php?page=powered-cache' );
		}

		wp_safe_redirect( esc_url_raw( add_query_arg( 'pc_options', 'updated', $referer ) ) );
		exit;
	}



}