<?php
/**
 * Settings REST controller tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Settings REST controller test case.
 */
class SettingsRestController_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'constants.php',
		'utils.php',
	);

	/**
	 * It registers the settings REST routes.
	 */
	public function test_register_routes_registers_settings_endpoints() {
		$controller = new SettingsRestController();
		$routes     = array();

		\WP_Mock::userFunction(
			'register_rest_route',
			array(
				'times'  => 3,
				'return' => function ( $namespace, $route, $args ) use ( &$routes ) {
					$this->assertSame( SettingsRestController::REST_NAMESPACE, $namespace );

					$routes[ $route ] = $args;

					return true;
				},
			)
		);

		$controller->register_routes();

		$this->assertSame( 'GET', $routes['/settings'][0]['methods'] );
		$this->assertSame( 'POST', $routes['/settings'][1]['methods'] );
		$this->assertSame( array( $controller, 'get_settings' ), $routes['/settings'][0]['callback'] );
		$this->assertSame( array( $controller, 'update_settings' ), $routes['/settings'][1]['callback'] );
		$this->assertSame( array( $controller, 'get_manifest' ), $routes['/settings/manifest']['callback'] );
		$this->assertSame( 'POST', $routes['/settings/recommended']['methods'] );
		$this->assertSame( array( $controller, 'apply_recommended_settings' ), $routes['/settings/recommended']['callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings'][0]['permission_callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings'][1]['permission_callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings/manifest']['permission_callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings/recommended']['permission_callback'] );
	}

	/**
	 * It uses settings capability checks for manifest reads.
	 */
	public function test_can_read_manifest_uses_settings_capability_rules() {
		$controller = new SettingsRestController();

		\WP_Mock::userFunction(
			'is_multisite',
			array(
				'times'  => 1,
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => array( 'manage_options' ),
				'return' => true,
			)
		);

		$this->assertTrue( $controller->can_read_manifest() );
	}

	/**
	 * It returns the new UI manifest without deprecated fields.
	 */
	public function test_get_manifest_returns_current_schema_manifest() {
		global $is_apache;

		$is_apache = true;

		$controller = new SettingsRestController();
		$manifest   = $controller->get_manifest();

		$this->assertSame( SettingsManifest::FORMAT, $manifest['format'] );
		$this->assertTrue( $manifest['fields']['auto_configure_htaccess']['default'] );
		$this->assertArrayHasKey( 'critical_css', $manifest['fields'] );
		$this->assertTrue( $manifest['fields']['critical_css']['premium'] );
		$this->assertArrayNotHasKey( 'js_execution_method', $manifest['fields'] );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It returns current settings without exposing sensitive values.
	 */
	public function test_get_settings_returns_redacted_settings_state() {
		global $is_apache;

		$is_apache = false;

		$defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $defaults )->reply( $defaults );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 4,
				'return' => function ( $option, $default = false ) {
					if ( 'active_plugins' === $option ) {
						return array();
					}

					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertSame( array(), $default );

					return array(
						'critical_css'              => true,
						'enable_image_optimization' => true,
						'cloudflare_email'          => 'admin@example.test',
						'cloudflare_api_key'        => 'secret-key',
						'cloudflare_api_token'      => 'secret-token',
						'sucuri_api_key'            => 'sucuri-key',
						'sucuri_api_secret'         => 'sucuri-secret',
					);
				},
			)
		);

		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 3,
				'args'   => array( 'active_sitewide_plugins', array() ),
				'return' => array(),
			)
		);

		$controller = new SettingsRestController();
		$response   = $controller->get_settings();

		$this->assertSame( SettingsRestController::STATE_FORMAT, $response['format'] );
		$this->assertSame( SettingsManifest::FORMAT_VERSION, $response['format_version'] );
		$this->assertArrayHasKey( 'validation', $response );
		$this->assertTrue( $response['validation']['valid'] );
		$this->assertTrue( $response['settings']['critical_css'] );
		$this->assertTrue( $response['settings']['enable_image_optimization'] );
		$this->assertSame( '', $response['settings']['cloudflare_email'] );
		$this->assertSame( '', $response['settings']['cloudflare_api_key'] );
		$this->assertSame( '', $response['settings']['cloudflare_api_token'] );
		$this->assertSame( '', $response['settings']['sucuri_api_key'] );
		$this->assertSame( '', $response['settings']['sucuri_api_secret'] );
		$this->assertArrayHasKey( 'setup_profile', $response );
		$this->assertSame( 0, $response['setup_profile']['plugin_count'] );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It returns guided setup recommendations from active plugins.
	 */
	public function test_get_settings_returns_setup_profile_from_active_plugins() {
		global $is_apache;

		$is_apache = false;

		$defaults = SettingsSchema::defaults( array( 'is_apache' => false ) );

		\WP_Mock::onFilter( 'powered_cache_default_settings' )->with( $defaults )->reply( $defaults );

		\WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 4,
				'return' => function ( $option, $default = false ) {
					if ( 'active_plugins' === $option ) {
						return array(
							'woocommerce/woocommerce.php',
							'sitepress-multilingual-cms/sitepress.php',
							'jetformbuilder/jet-form-builder.php',
						);
					}

					$this->assertSame( \PoweredCache\Constants\SETTING_OPTION, $option );
					$this->assertSame( array(), $default );

					return array(
						'enable_page_cache' => false,
						'object_cache'       => 'off',
					);
				},
			)
		);

		\WP_Mock::userFunction(
			'get_site_option',
			array(
				'times'  => 3,
				'args'   => array( 'active_sitewide_plugins', array() ),
				'return' => array(),
			)
		);

		$controller = new SettingsRestController();
		$response   = $controller->get_settings();

		$detected_keys       = array_column( $response['setup_profile']['detected'], 'key' );
		$recommendation_keys = array_column( $response['setup_profile']['recommendations'], 'key' );

		$this->assertSame( 3, $response['setup_profile']['plugin_count'] );
		$this->assertContains( 'woocommerce', $detected_keys );
		$this->assertContains( 'multilingual', $detected_keys );
		$this->assertContains( 'forms', $detected_keys );
		$this->assertContains( 'page_cache', $recommendation_keys );
		$this->assertContains( 'woocommerce_safeguards', $recommendation_keys );
		$this->assertContains( 'multilingual_preload', $recommendation_keys );

		unset( $GLOBALS['is_apache'] );
	}

	/**
	 * It preserves redacted sensitive values in partial update payloads.
	 */
	public function test_prepare_update_settings_preserves_redacted_sensitive_values() {
		$controller = new SettingsRestController();
		$current    = array(
			'enable_page_cache'    => true,
			'cloudflare_email'     => 'admin@example.test',
			'cloudflare_api_key'   => 'encrypted-key',
			'cloudflare_api_token' => 'encrypted-token',
		);

		$settings = $controller->prepare_update_settings(
			array(
				'enable_page_cache'    => false,
				'cloudflare_email'     => '',
				'cloudflare_api_key'   => '',
				'cloudflare_api_token' => '',
			),
			$current
		);

		$this->assertFalse( $settings['enable_page_cache'] );
		$this->assertSame( 'admin@example.test', $settings['cloudflare_email'] );
		$this->assertSame( 'encrypted-key', $settings['cloudflare_api_key'] );
		$this->assertSame( 'encrypted-token', $settings['cloudflare_api_token'] );
	}

	/**
	 * It preserves locked Premium values in Free partial update payloads.
	 */
	public function test_prepare_update_settings_preserves_locked_premium_values() {
		$controller = new SettingsRestController();
		$current    = array(
			'enable_page_cache'         => true,
			'enable_image_optimization' => false,
			'critical_css'              => true,
		);

		$settings = $controller->prepare_update_settings(
			array(
				'enable_page_cache'         => false,
				'enable_image_optimization' => true,
				'critical_css'              => false,
			),
			$current
		);

		$this->assertFalse( $settings['enable_page_cache'] );
		$this->assertFalse( $settings['enable_image_optimization'] );
		$this->assertTrue( $settings['critical_css'] );
	}

	/**
	 * It allows callers to explicitly clear sensitive values with null.
	 */
	public function test_prepare_update_settings_clears_sensitive_values_with_null() {
		$controller = new SettingsRestController();
		$current    = array(
			'cloudflare_email'     => 'admin@example.test',
			'cloudflare_api_key'   => 'encrypted-key',
			'cloudflare_api_token' => 'encrypted-token',
		);

		$settings = $controller->prepare_update_settings(
			array(
				'cloudflare_email'     => null,
				'cloudflare_api_key'   => null,
				'cloudflare_api_token' => null,
			),
			$current
		);

		$this->assertSame( '', $settings['cloudflare_email'] );
		$this->assertSame( '', $settings['cloudflare_api_key'] );
		$this->assertSame( '', $settings['cloudflare_api_token'] );
	}

	/**
	 * It encrypts new Cloudflare secret values before storage.
	 */
	public function test_prepare_update_settings_encrypts_new_cloudflare_secrets() {
		$controller = new SettingsRestController();

		$settings = $controller->prepare_update_settings(
			array(
				'cloudflare_api_key'   => 'new-key',
				'cloudflare_api_token' => 'new-token',
			),
			array()
		);

		$encryption = new Encryption();

		$this->assertSame( 'new-key', $encryption->decrypt( $settings['cloudflare_api_key'] ) );
		$this->assertSame( 'new-token', $encryption->decrypt( $settings['cloudflare_api_token'] ) );
	}

	/**
	 * It falls back to request params when no JSON payload is available.
	 */
	public function test_get_request_payload_falls_back_to_request_params() {
		$controller = new SettingsRestController();
		$request    = new class() {
			/**
			 * Return JSON params.
			 *
			 * @return null
			 */
			public function get_json_params() {
				return null;
			}

			/**
			 * Return request params.
			 *
			 * @return array
			 */
			public function get_params() {
				return array(
					'enable_page_cache' => false,
				);
			}
		};

		$method = new \ReflectionMethod( SettingsRestController::class, 'get_request_payload' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame(
			array(
				'enable_page_cache' => false,
			),
			$method->invoke( $controller, $request )
		);
	}
}
