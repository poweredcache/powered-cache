<?php
/**
 * Settings REST controller.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Exposes settings schema payloads for admin UI consumers.
 *
 * @since 4.0.0
 */
class SettingsRestController {

	const REST_NAMESPACE = 'powered-cache/v1';
	const STATE_FORMAT   = 'powered-cache-settings-state';

	/**
	 * Return an instance of the current class.
	 *
	 * @return SettingsRestController
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks.
	 */
	public function setup() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register settings routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_read_manifest' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_read_manifest' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_manifest' ),
				'permission_callback' => array( $this, 'can_read_manifest' ),
			)
		);
	}

	/**
	 * Determine whether the current user can read the settings manifest.
	 *
	 * @return bool
	 */
	public function can_read_manifest() {
		return \PoweredCache\Utils\can_control_all_settings();
	}

	/**
	 * Return the settings manifest.
	 *
	 * @return array
	 */
	public function get_manifest() {
		return SettingsManifest::build( $this->settings_context(), false );
	}

	/**
	 * Return current settings for admin UI consumers.
	 *
	 * @return array
	 */
	public function get_settings() {
		$context = $this->settings_context();

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			$context
		);
		$settings   = $repository->all();

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsRepository::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory( $context )->report( $settings ),
			'system_status'  => SystemStatus::factory( $settings, $context )->report(),
			'setup_profile'  => $this->setup_profile( $settings, $context ),
		);
	}

	/**
	 * Update settings from REST payload.
	 *
	 * @param mixed $request REST request.
	 *
	 * @return array
	 */
	public function update_settings( $request ) {
		$context = $this->settings_context();

		$repository = SettingsRepository::factory(
			POWERED_CACHE_IS_NETWORK,
			$context
		);

		$old_settings = $repository->all();
		$changes      = SettingsRepository::settings_from_payload( $this->get_request_payload( $request ) );
		$settings     = $this->prepare_update_settings( $changes, $old_settings );
		$save_service = SettingsSaveService::factory( $repository, POWERED_CACHE_IS_NETWORK );
		$settings     = $save_service->save( $settings, $old_settings );

		return array(
			'format'         => self::STATE_FORMAT,
			'format_version' => SettingsManifest::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'settings'       => SettingsRepository::redact_sensitive( $settings ),
			'validation'     => SettingsValidator::factory( $context )->report( $settings ),
			'system_status'  => SystemStatus::factory( $settings, $context )->report(),
			'setup_profile'  => $this->setup_profile( $settings, $context ),
		);
	}

	/**
	 * Prepare partial update payload before storage.
	 *
	 * @param array $changes Changed settings.
	 * @param array $current Current settings.
	 *
	 * @return array
	 */
	public function prepare_update_settings( array $changes, array $current ) {
		$settings = array_merge( $current, $changes );

		foreach ( SettingsRepository::sensitive_keys() as $key ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				continue;
			}

			if ( '' === $changes[ $key ] ) {
				$settings[ $key ] = isset( $current[ $key ] ) ? $current[ $key ] : '';
				continue;
			}

			if ( null === $changes[ $key ] ) {
				$settings[ $key ] = '';
				continue;
			}

			if ( in_array( $key, array( 'cloudflare_api_key', 'cloudflare_api_token' ), true ) ) {
				$settings[ $key ] = ( new Encryption() )->encrypt( $changes[ $key ] );
			}
		}

		return SettingsSchema::enforce_editable( $settings, $current );
	}

	/**
	 * Read request payload.
	 *
	 * @param mixed $request REST request.
	 *
	 * @return array
	 */
	private function get_request_payload( $request ) {
		if ( is_array( $request ) ) {
			return $request;
		}

		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$params = $request->get_json_params();

			if ( is_array( $params ) ) {
				return $params;
			}
		}

		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			$params = $request->get_params();

			return is_array( $params ) ? $params : array();
		}

		return array();
	}

	/**
	 * Build a lightweight setup profile for guided admin UI recommendations.
	 *
	 * @param array $settings Current settings.
	 * @param array $context  Runtime context.
	 *
	 * @return array
	 */
	private function setup_profile( array $settings, array $context ) {
		$active_plugins  = $this->active_plugins();
		$detected        = array();
		$recommendations = array();

		if ( $this->has_plugin( $active_plugins, array( 'woocommerce/woocommerce.php' ) ) ) {
			$detected[]        = $this->setup_detection( 'woocommerce', 'WooCommerce', 'plugin' );
			$recommendations[] = $this->setup_recommendation(
				'woocommerce_safeguards',
				'Review commerce safeguards',
				'Cart, checkout, and account pages are protected automatically. Review exclusions before enabling aggressive JavaScript delay.',
				'advanced',
				'high'
			);
		}

		if ( $this->has_plugin(
			$active_plugins,
			array(
				'sitepress-multilingual-cms/sitepress.php',
				'polylang/polylang.php',
				'translatepress-multilingual/index.php',
				'woocommerce-multilingual/wpml-woocommerce.php',
			)
		) ) {
			$detected[]        = $this->setup_detection( 'multilingual', 'Multilingual site', 'site' );
			$recommendations[] = $this->setup_recommendation(
				'multilingual_preload',
				'Confirm multilingual preload',
				'Preload can cover language variants after your public language URLs are confirmed.',
				'preload'
			);
		}

		if ( $this->has_plugin(
			$active_plugins,
			array(
				'contact-form-7/wp-contact-form-7.php',
				'fluentform/fluentform.php',
				'forminator/forminator.php',
				'gravityforms/gravityforms.php',
				'jetformbuilder/jet-form-builder.php',
				'ninja-forms/ninja-forms.php',
				'wpforms-lite/wpforms.php',
				'wpforms/wpforms.php',
			)
		) ) {
			$detected[]        = $this->setup_detection( 'forms', 'Forms', 'plugin' );
			$recommendations[] = $this->setup_recommendation(
				'form_script_safeguards',
				'Test key forms after script optimization',
				'Detected form plugins are protected by compatibility rules, but your main forms should still be checked after JavaScript changes.',
				'file_optimization'
			);
		}

		if ( empty( $settings['enable_page_cache'] ) ) {
			array_unshift(
				$recommendations,
				$this->setup_recommendation(
					'page_cache',
					'Start with page cache',
					'Enable full-page cache first so anonymous visits get the fastest baseline before advanced optimizations are tuned.',
					'cache',
					'high'
				)
			);
		}

		$object_cache_backends = isset( $context['object_cache_backends'] ) && is_array( $context['object_cache_backends'] ) ? $context['object_cache_backends'] : array();
		if ( ! empty( $object_cache_backends ) && ( empty( $settings['object_cache'] ) || 'off' === $settings['object_cache'] ) ) {
			$recommendations[] = $this->setup_recommendation(
				'object_cache',
				'Consider persistent object cache',
				'This server has a supported object cache backend available for dynamic WordPress data.',
				'cache'
			);
		}

		if ( ! empty( $context['active_environments'] ) && is_array( $context['active_environments'] ) ) {
			$detected[]        = $this->setup_detection( 'hosting_stack', 'Managed hosting signals', 'environment' );
			$recommendations[] = $this->setup_recommendation(
				'hosting_stack',
				'Review hosting cache coordination',
				'Detected hosting or edge-cache signals can require coordinated purge behavior.',
				'misc'
			);
		}

		/**
		 * Filter the guided setup profile returned with settings state.
		 *
		 * @hook powered_cache_settings_setup_profile
		 *
		 * @param {array} $profile  Setup profile.
		 * @param {array} $settings Current settings.
		 * @param {array} $context  Runtime context.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$profile = apply_filters(
			'powered_cache_settings_setup_profile',
			array(
				'detected'        => $this->unique_setup_items( $detected ),
				'plugin_count'    => count( $active_plugins ),
				'recommendations' => array_slice( $this->unique_setup_items( $recommendations ), 0, 4 ),
			),
			$settings,
			$context
		);

		return is_array( $profile ) ? $profile : array();
	}

	/**
	 * Return active plugin basenames.
	 *
	 * @return array
	 */
	private function active_plugins() {
		$plugins = array();

		if ( function_exists( 'get_option' ) ) {
			$option_plugins = get_option( 'active_plugins', array() );

			if ( is_array( $option_plugins ) ) {
				$plugins = array_merge( $plugins, $option_plugins );
			}
		}

		if ( function_exists( 'get_site_option' ) ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			if ( is_array( $network_plugins ) ) {
				$plugins = array_merge( $plugins, array_keys( $network_plugins ) );
			}
		}

		/**
		 * Filter active plugin basenames used by guided setup recommendations.
		 *
		 * @hook powered_cache_settings_setup_active_plugins
		 *
		 * @param {array} $plugins Active plugin basenames.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$plugins = apply_filters( 'powered_cache_settings_setup_active_plugins', $plugins );

		return is_array( $plugins ) ? $this->normalize_list( $plugins ) : array();
	}

	/**
	 * Determine whether one of the plugin basenames is active.
	 *
	 * @param array $active_plugins Active plugin basenames.
	 * @param array $candidates     Plugin basenames to match.
	 *
	 * @return bool
	 */
	private function has_plugin( array $active_plugins, array $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $active_plugins, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a setup detection item.
	 *
	 * @param string $key   Detection key.
	 * @param string $label Detection label.
	 * @param string $type  Detection type.
	 *
	 * @return array
	 */
	private function setup_detection( $key, $label, $type ) {
		return array(
			'key'   => $key,
			'label' => $label,
			'type'  => $type,
		);
	}

	/**
	 * Build a setup recommendation item.
	 *
	 * @param string $key      Recommendation key.
	 * @param string $label    Recommendation label.
	 * @param string $message  Recommendation message.
	 * @param string $section  Settings section key.
	 * @param string $priority Recommendation priority.
	 *
	 * @return array
	 */
	private function setup_recommendation( $key, $label, $message, $section, $priority = 'normal' ) {
		return array(
			'key'      => $key,
			'label'    => $label,
			'message'  => $message,
			'priority' => $priority,
			'section'  => $section,
		);
	}

	/**
	 * Return unique setup items by key.
	 *
	 * @param array $items Setup items.
	 *
	 * @return array
	 */
	private function unique_setup_items( array $items ) {
		$unique = array();
		$seen   = array();

		foreach ( $items as $item ) {
			if ( empty( $item['key'] ) || isset( $seen[ $item['key'] ] ) ) {
				continue;
			}

			$unique[]            = $item;
			$seen[ $item['key'] ] = true;
		}

		return $unique;
	}

	/**
	 * Return normalized unique scalar values.
	 *
	 * @param array $values Values.
	 *
	 * @return array
	 */
	private function normalize_list( array $values ) {
		$normalized = array();

		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' === $value || in_array( $value, $normalized, true ) ) {
				continue;
			}

			$normalized[] = $value;
		}

		return $normalized;
	}

	/**
	 * Return active hosting/runtime environment identifiers.
	 *
	 * @return array
	 */
	private function active_environments() {
		$environments = array();
		$software     = $this->server_value( 'SERVER_SOFTWARE' );

		if ( false !== stripos( $software, 'litespeed' ) ) {
			$environments[] = 'server:litespeed';
		}

		if ( false !== stripos( $software, 'nginx' ) ) {
			$environments[] = 'server:nginx';
		}

		if ( false !== stripos( $software, 'apache' ) ) {
			$environments[] = 'server:apache';
		}

		if ( '' !== $this->server_value( 'HTTP_CF_RAY' ) || false !== stripos( $this->server_value( 'HTTP_CDN_LOOP' ), 'cloudflare' ) ) {
			$environments[] = 'cdn:cloudflare';
		}

		if ( defined( 'KINSTAMU_VERSION' ) ) {
			$environments[] = 'host:kinsta';
		}

		if ( defined( 'WPE_APIKEY' ) || defined( 'WPE_CLUSTER_ID' ) || defined( 'PWP_NAME' ) ) {
			$environments[] = 'host:wp-engine';
		}

		if ( defined( 'PANTHEON_ENVIRONMENT' ) ) {
			$environments[] = 'host:pantheon';
		}

		/**
		 * Filter active hosting/runtime environments used by guided setup recommendations.
		 *
		 * @hook powered_cache_settings_setup_active_environments
		 *
		 * @param {array} $environments Active environment identifiers.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$environments = apply_filters( 'powered_cache_settings_setup_active_environments', $environments );

		return is_array( $environments ) ? $this->normalize_list( $environments ) : array();
	}

	/**
	 * Return a sanitized server value.
	 *
	 * @param string $key Server key.
	 *
	 * @return string
	 */
	private function server_value( $key ) {
		if ( empty( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return '';
		}

		$value = $_SERVER[ $key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( function_exists( 'wp_unslash' ) ) {
			$value = wp_unslash( $value );
		}

		return trim( (string) $value );
	}

	/**
	 * Build runtime context for schema defaults and settings validation.
	 *
	 * @return array
	 */
	private function settings_context() {
		global $is_apache;

		$context = array(
			'is_apache'                => (bool) $is_apache,
			'wp_cache_enabled'         => defined( 'WP_CACHE' ) && true === WP_CACHE,
			'page_cache_loaded'        => defined( 'POWERED_CACHE_PAGE_CACHING' ) && true === POWERED_CACHE_PAGE_CACHING,
			'page_cache_has_problem'   => defined( 'POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM' ) && POWERED_CACHE_PAGE_CACHING_HAS_PROBLEM,
			'object_cache_has_problem' => defined( 'POWERED_OBJECT_CACHE_HAS_PROBLEM' ) && POWERED_OBJECT_CACHE_HAS_PROBLEM,
			'active_environments'      => $this->active_environments(),
		);

		if ( function_exists( '\PoweredCache\Utils\get_available_object_caches' ) ) {
			$context['object_cache_backends'] = \PoweredCache\Utils\get_available_object_caches();
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$context['object_cache_dropin_exists'] = file_exists( rtrim( WP_CONTENT_DIR, '/\\' ) . '/object-cache.php' );
		}

		if ( $context['is_apache'] && function_exists( 'get_home_path' ) ) {
			$htaccess_file                = rtrim( get_home_path(), '/\\' ) . '/.htaccess';
			$context['htaccess_exists']   = file_exists( $htaccess_file );
			$context['htaccess_writable'] = is_writable( $htaccess_file );
		}

		/**
		 * Filter settings validation runtime context.
		 *
		 * @hook powered_cache_settings_validation_context
		 *
		 * @param {array} $context Runtime context.
		 *
		 * @return {array} New value.
		 * @since 4.0.0
		 */
		$context = apply_filters( 'powered_cache_settings_validation_context', $context );

		return is_array( $context ) ? $context : array();
	}
}
