<?php
/**
 * Compatibility rules tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Compatibility rules test case.
 */
class CompatibilityRules_Tests extends TestCase {

	/**
	 * It loads the bundled compatibility registry.
	 */
	public function test_bundled_registry_loads_default_rules() {
		$rules = CompatibilityRules::factory();

		$this->assertContains( 'wp-includes/js/dist/interactivity.min.js', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'wp-includes/blocks/image/view.min.js', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'www.google.com/recaptcha', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'js.stripe.com', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'selectors.core.image.lightboxObjectFit', $rules->rules( 'lazy_load_exclusions' ) );
	}

	/**
	 * It loads bundled plugin compatibility packs.
	 */
	public function test_bundled_registry_loads_plugin_compatibility_packs() {
		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'woocommerce/woocommerce.php', 'contact-form-7/wp-contact-form-7.php' ) );

		$rules = CompatibilityRules::factory();

		$this->assertContains( 'wc-cart-fragments', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'wc-checkout', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'wpcf7', $rules->rules( 'delay_exclusions' ) );
		$this->assertContains( 'recaptcha', $rules->rules( 'delay_exclusions' ) );
	}

	/**
	 * It exposes bundled plugin compatibility notes.
	 */
	public function test_bundled_registry_loads_plugin_compatibility_notes() {
		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'elementor/elementor.php' ) );

		$rules = CompatibilityRules::factory();

		$this->assertSame(
			array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'compatibility_rule_applied',
					'message'  => 'Powered Cache automatically keeps WordPress core interactivity scripts out of delayed JavaScript execution.',
				),
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'elementor_delay_guard',
					'message'  => 'Elementor frontend scripts are protected from delayed JavaScript to keep widgets responsive.',
				),
			),
			$rules->settings_issues( array( 'js_delay' => true ) )
		);
	}

	/**
	 * It exposes bundled option-aware compatibility notes.
	 */
	public function test_bundled_registry_reports_autoptimize_option_conflicts() {
		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'autoptimize/autoptimize.php' ) );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 4,
				'return' => function ( $option ) {
					if ( 'active_plugins' === $option ) {
						return array();
					}

					if ( in_array( $option, array( 'autoptimize_html', 'autoptimize_css', 'autoptimize_js' ), true ) ) {
						return 'on';
					}

					return '';
				},
			)
		);

		$rules  = CompatibilityRules::factory();
		$issues = $rules->settings_issues(
			array(
				'minify_html' => true,
				'minify_css'  => true,
				'minify_js'   => true,
			)
		);

		$this->assertSame( 3, count( $issues ) );
		$this->assertSame( 'autoptimize_html_active', $issues[0]['code'] );
		$this->assertSame( 'autoptimize_css_active', $issues[1]['code'] );
		$this->assertSame( 'autoptimize_js_active', $issues[2]['code'] );
	}

	/**
	 * It exposes bundled page cache conflict notes for active cache plugins.
	 */
	public function test_bundled_registry_reports_external_page_cache_conflicts() {
		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'litespeed-cache/litespeed-cache.php' ) );

		$rules  = CompatibilityRules::factory();
		$issues = $rules->settings_issues( array( 'enable_page_cache' => true ) );

		$this->assertSame(
			array(
				array(
					'key'      => 'enable_page_cache',
					'severity' => 'warning',
					'code'     => 'external_page_cache_plugin_active',
					'message'  => 'LiteSpeed Cache is active. Avoid running two full-page cache layers unless one of them is intentionally disabled.',
				),
			),
			$issues
		);
	}

	/**
	 * It ignores unknown registry buckets.
	 */
	public function test_unknown_rule_bucket_returns_empty_list() {
		$rules = CompatibilityRules::factory();

		$this->assertSame( array(), $rules->rules( 'missing_bucket' ) );
	}

	/**
	 * It appends default rules without duplicating existing exclusions.
	 */
	public function test_filter_callbacks_append_unique_rules() {
		$rules = CompatibilityRules::factory();

		$delay_exclusions = $rules->add_delay_exclusions(
			array(
				'custom-script',
				'wp-includes/js/dist/interactivity.min.js',
			)
		);

		$this->assertSame(
			array(
				'custom-script',
				'wp-includes/js/dist/interactivity.min.js',
				'wp-includes/blocks/image/view.min.js',
				'www.google.com/recaptcha',
				'www.gstatic.com/recaptcha',
				'grecaptcha',
				'challenges.cloudflare.com/turnstile',
				'cf-turnstile',
				'hcaptcha.com/1/api.js',
				'hcaptcha',
				'js.stripe.com',
				'paypal.com/sdk/js',
				'maps.googleapis.com/maps/api/js',
				'maps.google.com/maps-api-v3',
			),
			$delay_exclusions
		);

		$this->assertSame(
			array(
				'selectors.core.image.lightboxObjectFit',
			),
			$rules->add_lazy_load_exclusions( array() )
		);
	}

	/**
	 * It normalizes registry rules before exposing them.
	 */
	public function test_registry_rules_are_normalized() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'         => CompatibilityRules::FORMAT,
					'format_version' => CompatibilityRules::FORMAT_VERSION,
					'rules'          => array(
						'delay_exclusions' => array(
							' custom-script ',
							'custom-script',
							'',
							array( 'bad' ),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'custom-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It allows local registry replacement through a filter.
	 */
	public function test_registry_can_be_replaced_through_filter() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		$base = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'bundled-script' ),
			),
		);
		$replacement = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'replacement-script' ),
			),
		);

		file_put_contents( $file, json_encode( $base ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_registry' )->with( $base, $file )->reply( $replacement );

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'replacement-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It merges a valid remote registry into the bundled registry.
	 */
	public function test_remote_registry_is_merged_when_available() {
		$file   = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		$base   = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'bundled-script' ),
			),
		);
		$remote = array(
			'format'          => CompatibilityRules::FORMAT,
			'format_version'  => CompatibilityRules::FORMAT_VERSION,
			'rules'           => array(
				'delay_exclusions' => array( 'remote-script' ),
			),
			'settings_issues' => array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'remote_guard',
					'message'  => 'Remote compatibility guard.',
					'when'     => array(
						'setting' => 'js_delay',
						'value'   => true,
					),
				),
			),
		);

		file_put_contents( $file, json_encode( $base ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_remote_url' )
			->with( '' )
			->reply( 'https://poweredcache.com/compatibility-rules.json' );

		\WP_Mock::userFunction(
			'get_site_transient',
			array(
				'times'  => 1,
				'args'   => array( CompatibilityRules::REMOTE_CACHE_KEY ),
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'wp_remote_get',
			array(
				'times'  => 1,
				'args'   => array(
					'https://poweredcache.com/compatibility-rules.json',
					array(
						'timeout'     => 3,
						'redirection' => 2,
						'headers'     => array(
							'Accept' => 'application/json',
						),
					),
				),
				'return' => array( 'body' => json_encode( $remote ) ),
			)
		);

		\WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
		\WP_Mock::userFunction( 'wp_remote_retrieve_response_code', array( 'return' => 200 ) );
		\WP_Mock::userFunction( 'wp_remote_retrieve_body', array( 'return' => json_encode( $remote ) ) );
		\WP_Mock::userFunction(
			'set_site_transient',
			array(
				'times'  => 1,
				'args'   => array( CompatibilityRules::REMOTE_CACHE_KEY, $remote, 86400 ),
				'return' => true,
			)
		);

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'bundled-script', 'remote-script' ), $rules->rules( 'delay_exclusions' ) );
		$this->assertSame(
			array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'remote_guard',
					'message'  => 'Remote compatibility guard.',
				),
			),
			$rules->settings_issues( array( 'js_delay' => true ) )
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It uses a cached remote registry without making an HTTP request.
	 */
	public function test_cached_remote_registry_is_used_without_http_request() {
		$file   = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		$base   = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'bundled-script' ),
			),
		);
		$cached = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'cached-script' ),
			),
		);

		file_put_contents( $file, json_encode( $base ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_remote_url' )
			->with( '' )
			->reply( 'https://poweredcache.com/compatibility-rules.json' );

		\WP_Mock::userFunction(
			'get_site_transient',
			array(
				'times'  => 1,
				'args'   => array( CompatibilityRules::REMOTE_CACHE_KEY ),
				'return' => $cached,
			)
		);

		\WP_Mock::userFunction( 'wp_remote_get', array( 'times' => 0 ) );

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'bundled-script', 'cached-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It ignores remote registries from untrusted hosts.
	 */
	public function test_remote_registry_requires_allowed_https_host() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		$base = array(
			'format'         => CompatibilityRules::FORMAT,
			'format_version' => CompatibilityRules::FORMAT_VERSION,
			'rules'          => array(
				'delay_exclusions' => array( 'bundled-script' ),
			),
		);

		file_put_contents( $file, json_encode( $base ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_remote_url' )
			->with( '' )
			->reply( 'http://attacker.test/compatibility-rules.json' );

		\WP_Mock::userFunction( 'wp_remote_get', array( 'times' => 0 ) );
		\WP_Mock::userFunction( 'get_site_transient', array( 'times' => 0 ) );

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'bundled-script' ), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It applies plugin conditional rules only for active plugins.
	 */
	public function test_plugin_conditional_rules_apply_for_active_plugins() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'            => CompatibilityRules::FORMAT,
					'format_version'    => CompatibilityRules::FORMAT_VERSION,
					'rules'             => array(
						'delay_exclusions' => array( 'bundled-script' ),
					),
					'conditional_rules' => array(
						'plugins' => array(
							'active-plugin/active-plugin.php'     => array(
								'delay_exclusions' => array( ' active-script ', 'active-script' ),
							),
							'inactive-plugin/inactive-plugin.php' => array(
								'delay_exclusions' => array( 'inactive-script' ),
							),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'active-plugin/active-plugin.php' ) );

		$rules = new CompatibilityRules( $file );

		$this->assertSame(
			array(
				'bundled-script',
				'active-script',
			),
			$rules->rules( 'delay_exclusions' )
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It exposes matching settings issues from the registry.
	 */
	public function test_settings_issues_match_current_settings() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'          => CompatibilityRules::FORMAT,
					'format_version'  => CompatibilityRules::FORMAT_VERSION,
					'rules'           => array(),
					'settings_issues' => array(
						array(
							'key'      => 'js_delay',
							'severity' => 'info',
							'code'     => 'compatibility_rule_applied',
							'message'  => 'Compatibility guard active.',
							'when'     => array(
								'setting' => 'js_delay',
								'value'   => true,
							),
						),
						array(
							'key'      => 'enable_lazy_load',
							'severity' => 'warning',
							'code'     => 'not_active',
							'message'  => 'Should not show.',
							'when'     => array(
								'setting' => 'enable_lazy_load',
								'value'   => true,
							),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame(
			array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'compatibility_rule_applied',
					'message'  => 'Compatibility guard active.',
				),
			),
			$rules->settings_issues(
				array(
					'js_delay'         => 1,
					'enable_lazy_load' => false,
				)
			)
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It exposes plugin conditional settings issues for active plugins.
	 */
	public function test_plugin_conditional_settings_issues_apply_for_active_plugins() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'            => CompatibilityRules::FORMAT,
					'format_version'    => CompatibilityRules::FORMAT_VERSION,
					'rules'             => array(),
					'settings_issues'   => array(
						array(
							'key'      => 'js_delay',
							'severity' => 'info',
							'code'     => 'duplicate_guard',
							'message'  => 'Base message wins.',
							'when'     => array(
								'setting' => 'js_delay',
								'value'   => true,
							),
						),
					),
					'conditional_rules' => array(
						'plugins' => array(
							'active-plugin/active-plugin.php' => array(
								'settings_issues' => array(
									array(
										'key'      => 'js_delay',
										'severity' => 'info',
										'code'     => 'duplicate_guard',
										'message'  => 'Duplicate message.',
										'when'     => array(
											'setting' => 'js_delay',
											'value'   => true,
										),
									),
									array(
										'key'      => 'js_delay',
										'severity' => 'warning',
										'code'     => 'active_plugin_guard',
										'message'  => 'Active plugin guard.',
										'when'     => array(
											'setting' => 'js_delay',
											'value'   => true,
										),
									),
								),
							),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'active-plugin/active-plugin.php' ) );

		$rules = new CompatibilityRules( $file );

		$this->assertSame(
			array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'duplicate_guard',
					'message'  => 'Base message wins.',
				),
				array(
					'key'      => 'js_delay',
					'severity' => 'warning',
					'code'     => 'active_plugin_guard',
					'message'  => 'Active plugin guard.',
				),
			),
			$rules->settings_issues( array( 'js_delay' => true ) )
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It matches settings issues with option-backed conditions.
	 */
	public function test_settings_issues_can_match_option_conditions() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'            => CompatibilityRules::FORMAT,
					'format_version'    => CompatibilityRules::FORMAT_VERSION,
					'rules'             => array(),
					'conditional_rules' => array(
						'plugins' => array(
							'active-plugin/active-plugin.php' => array(
								'settings_issues' => array(
									array(
										'key'      => 'minify_css',
										'severity' => 'warning',
										'code'     => 'external_optimizer_active',
										'message'  => 'External optimizer controls CSS minification.',
										'when'     => array(
											'setting' => 'minify_css',
											'value'   => true,
											'options' => array(
												array(
													'name'  => 'external_css_optimizer',
													'value' => 'on',
												),
											),
										),
									),
									array(
										'key'      => 'minify_js',
										'severity' => 'warning',
										'code'     => 'external_optimizer_inactive',
										'message'  => 'Should not show.',
										'when'     => array(
											'setting' => 'minify_js',
											'value'   => true,
											'options' => array(
												array(
													'name'  => 'external_js_optimizer',
													'value' => 'on',
												),
											),
										),
									),
								),
							),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'active-plugin/active-plugin.php' ) );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 3,
				'return' => function ( $option ) {
					if ( 'active_plugins' === $option ) {
						return array();
					}

					if ( 'external_css_optimizer' === $option ) {
						return 'on';
					}

					if ( 'external_js_optimizer' === $option ) {
						return 'off';
					}

					return '';
				},
			)
		);

		$rules = new CompatibilityRules( $file );

		$this->assertSame(
			array(
				array(
					'key'      => 'minify_css',
					'severity' => 'warning',
					'code'     => 'external_optimizer_active',
					'message'  => 'External optimizer controls CSS minification.',
				),
			),
			$rules->settings_issues(
				array(
					'minify_css' => true,
					'minify_js'  => true,
				)
			)
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It reuses the active plugin list within one registry instance.
	 */
	public function test_active_plugins_are_resolved_once_per_registry_instance() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'            => CompatibilityRules::FORMAT,
					'format_version'    => CompatibilityRules::FORMAT_VERSION,
					'rules'             => array(),
					'conditional_rules' => array(
						'plugins' => array(
							'active-plugin/active-plugin.php' => array(
								'delay_exclusions' => array( 'active-delay-script' ),
								'settings_issues'  => array(
									array(
										'key'      => 'js_delay',
										'severity' => 'info',
										'code'     => 'active_plugin_guard',
										'message'  => 'Active plugin guard.',
										'when'     => array(
											'setting' => 'js_delay',
											'value'   => true,
										),
									),
								),
							),
						),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		\WP_Mock::onFilter( 'powered_cache_compatibility_rules_active_plugins' )
			->with( array() )
			->reply( array( 'active-plugin/active-plugin.php' ) );

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array( 'active-delay-script' ), $rules->rules( 'delay_exclusions' ) );
		$this->assertSame(
			array(
				array(
					'key'      => 'js_delay',
					'severity' => 'info',
					'code'     => 'active_plugin_guard',
					'message'  => 'Active plugin guard.',
				),
			),
			$rules->settings_issues( array( 'js_delay' => true ) )
		);

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It fails closed when the registry payload is invalid.
	 */
	public function test_invalid_registry_payload_returns_no_rules() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents( $file, '{"format":"invalid","rules":{"delay_exclusions":["x"]}}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array(), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * It fails closed when the registry version is unsupported.
	 */
	public function test_unsupported_registry_version_returns_no_rules() {
		$file = tempnam( sys_get_temp_dir(), 'pc-rules-' );
		file_put_contents(
			$file,
			json_encode(
				array(
					'format'         => CompatibilityRules::FORMAT,
					'format_version' => '99.0',
					'rules'          => array(
						'delay_exclusions' => array( 'x' ),
					),
				)
			)
		); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$rules = new CompatibilityRules( $file );

		$this->assertSame( array(), $rules->rules( 'delay_exclusions' ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
