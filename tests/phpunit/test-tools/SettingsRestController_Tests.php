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
				'times'  => 2,
				'return' => function ( $namespace, $route, $args ) use ( &$routes ) {
					$this->assertSame( SettingsRestController::REST_NAMESPACE, $namespace );

					$routes[ $route ] = $args;

					$this->assertSame( 'GET', $args['methods'] );

					return true;
				},
			)
		);

		$controller->register_routes();

		$this->assertSame( array( $controller, 'get_settings' ), $routes['/settings']['callback'] );
		$this->assertSame( array( $controller, 'get_manifest' ), $routes['/settings/manifest']['callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings']['permission_callback'] );
		$this->assertSame( array( $controller, 'can_read_manifest' ), $routes['/settings/manifest']['permission_callback'] );
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
				'times'  => 1,
				'args'   => array( \PoweredCache\Constants\SETTING_OPTION, array() ),
				'return' => array(
					'critical_css'              => true,
					'enable_image_optimization' => true,
					'cloudflare_email'          => 'admin@example.test',
					'cloudflare_api_key'        => 'secret-key',
					'cloudflare_api_token'      => 'secret-token',
				),
			)
		);

		$controller = new SettingsRestController();
		$response   = $controller->get_settings();

		$this->assertSame( SettingsRestController::STATE_FORMAT, $response['format'] );
		$this->assertSame( SettingsManifest::FORMAT_VERSION, $response['format_version'] );
		$this->assertTrue( $response['settings']['critical_css'] );
		$this->assertTrue( $response['settings']['enable_image_optimization'] );
		$this->assertSame( '', $response['settings']['cloudflare_email'] );
		$this->assertSame( '', $response['settings']['cloudflare_api_key'] );
		$this->assertSame( '', $response['settings']['cloudflare_api_token'] );

		unset( $GLOBALS['is_apache'] );
	}
}
