<?php
/**
 * Settings setup profile.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Builds guided setup recommendations for the settings UI.
 *
 * @since 4.0.0
 */
class SettingsSetupProfile {

	/**
	 * Runtime context.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param array $context Runtime context.
	 */
	public function __construct( array $context = array() ) {
		$this->context = $context;
	}

	/**
	 * Create an instance.
	 *
	 * @param array $context Runtime context.
	 *
	 * @return SettingsSetupProfile
	 */
	public static function factory( array $context = array() ) {
		return new self( $context );
	}

	/**
	 * Build a lightweight setup profile for guided admin UI recommendations.
	 *
	 * @param array $settings Current settings.
	 *
	 * @return array
	 */
	public function report( array $settings ) {
		$active_plugins  = $this->active_plugins();
		$detected        = array();
		$recommendations = array();

		if ( $this->has_plugin( $active_plugins, array( 'woocommerce/woocommerce.php' ) ) ) {
			$detected[]        = $this->detection( 'woocommerce', 'WooCommerce', 'plugin' );
			$recommendations[] = $this->recommendation(
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
			$detected[]        = $this->detection( 'multilingual', 'Multilingual site', 'site' );
			$recommendations[] = $this->recommendation(
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
			$detected[]        = $this->detection( 'forms', 'Forms', 'plugin' );
			$recommendations[] = $this->recommendation(
				'form_script_safeguards',
				'Test key forms after script optimization',
				'Detected form plugins are protected by compatibility rules, but your main forms should still be checked after JavaScript changes.',
				'file_optimization'
			);
		}

		if ( empty( $settings['enable_page_cache'] ) ) {
			array_unshift(
				$recommendations,
				$this->recommendation(
					'page_cache',
					'Start with page cache',
					'Enable full-page cache first so anonymous visits get the fastest baseline before advanced optimizations are tuned.',
					'cache',
					'high'
				)
			);
		}

		$object_cache_backends = isset( $this->context['object_cache_backends'] ) && is_array( $this->context['object_cache_backends'] ) ? $this->context['object_cache_backends'] : array();
		if ( ! empty( $object_cache_backends ) && ( empty( $settings['object_cache'] ) || 'off' === $settings['object_cache'] ) ) {
			$recommendations[] = $this->recommendation(
				'object_cache',
				'Consider persistent object cache',
				'This server has a supported object cache backend available for dynamic WordPress data.',
				'cache'
			);
		}

		if ( ! empty( $this->context['active_environments'] ) && is_array( $this->context['active_environments'] ) ) {
			$detected[]        = $this->detection( 'hosting_stack', 'Managed hosting signals', 'environment' );
			$recommendations[] = $this->recommendation(
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
				'detected'        => $this->unique_items( $detected ),
				'plugin_count'    => count( $active_plugins ),
				'recommendations' => array_slice( $this->unique_items( $recommendations ), 0, 4 ),
			),
			$settings,
			$this->context
		);

		return is_array( $profile ) ? $profile : array();
	}

	/**
	 * Return active hosting/runtime environment identifiers.
	 *
	 * @return array
	 */
	public static function active_environments() {
		$environments = array();
		$software     = self::server_value( 'SERVER_SOFTWARE' );

		if ( false !== stripos( $software, 'litespeed' ) ) {
			$environments[] = 'server:litespeed';
		}

		if ( false !== stripos( $software, 'nginx' ) ) {
			$environments[] = 'server:nginx';
		}

		if ( false !== stripos( $software, 'apache' ) ) {
			$environments[] = 'server:apache';
		}

		if ( '' !== self::server_value( 'HTTP_CF_RAY' ) || false !== stripos( self::server_value( 'HTTP_CDN_LOOP' ), 'cloudflare' ) ) {
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

		return is_array( $environments ) ? self::normalize_list( $environments ) : array();
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

		return is_array( $plugins ) ? self::normalize_list( $plugins ) : array();
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
	private function detection( $key, $label, $type ) {
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
	private function recommendation( $key, $label, $message, $section, $priority = 'normal' ) {
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
	private function unique_items( array $items ) {
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
	private static function normalize_list( array $values ) {
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
	 * Return a sanitized server value.
	 *
	 * @param string $key Server key.
	 *
	 * @return string
	 */
	private static function server_value( $key ) {
		if ( empty( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return '';
		}

		$value = $_SERVER[ $key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( function_exists( 'wp_unslash' ) ) {
			$value = wp_unslash( $value );
		}

		return trim( (string) $value );
	}
}
