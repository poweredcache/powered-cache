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
	 * It registers the manifest REST route.
	 */
	public function test_register_routes_registers_manifest_endpoint() {
		$controller = new SettingsRestController();

		\WP_Mock::userFunction(
			'register_rest_route',
			array(
				'times'  => 1,
				'return' => function ( $namespace, $route, $args ) use ( $controller ) {
					$this->assertSame( SettingsRestController::REST_NAMESPACE, $namespace );
					$this->assertSame( '/settings/manifest', $route );
					$this->assertSame( 'GET', $args['methods'] );
					$this->assertSame( array( $controller, 'get_manifest' ), $args['callback'] );
					$this->assertSame( array( $controller, 'can_read_manifest' ), $args['permission_callback'] );

					return true;
				},
			)
		);

		$controller->register_routes();
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
}
