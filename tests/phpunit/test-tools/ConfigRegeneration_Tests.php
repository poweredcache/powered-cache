<?php
/**
 * Configuration regeneration tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Config double that reads wp-config.php from a temporary path.
 */
class Config_Regeneration_Testable extends Config {

	/**
	 * Temporary wp-config.php path, or false when missing.
	 *
	 * @var string|false
	 */
	public $wp_config_path = false;

	/**
	 * Return the temporary wp-config.php path.
	 *
	 * @return bool|string
	 */
	public function find_wp_config_file() {
		return $this->wp_config_path;
	}
}

/**
 * Configuration regeneration test case.
 */
class ConfigRegeneration_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'utils.php',
		'classes/Config.php',
	);

	/**
	 * Files created by a test.
	 *
	 * @var array
	 */
	private $created_files = array();

	/**
	 * Prepare the temporary wp-content directory.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! is_dir( WP_CONTENT_DIR ) ) {
			mkdir( WP_CONTENT_DIR, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}

		\WP_Mock::userFunction( 'is_multisite', array( 'return' => false ) );
		\WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		\WP_Mock::userFunction(
			'untrailingslashit',
			array(
				'return' => function ( $path ) {
					return rtrim( $path, '/\\' );
				},
			)
		);
	}

	/**
	 * Remove files created by the test.
	 */
	public function tearDown(): void {
		foreach ( $this->created_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		parent::tearDown();
	}

	/**
	 * Write a temporary file and remember it for cleanup.
	 *
	 * @param string $path    File path.
	 * @param string $content File content.
	 *
	 * @return string
	 */
	private function write_file( $path, $content ) {
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->created_files[] = $path;

		return $path;
	}

	/**
	 * Build a config double with a temporary wp-config.php.
	 *
	 * @param string|null $contents wp-config.php contents, or null for a missing file.
	 *
	 * @return Config_Regeneration_Testable
	 */
	private function config( $contents ) {
		$config = new Config_Regeneration_Testable();

		if ( null !== $contents ) {
			$config->wp_config_path = $this->write_file( WP_CONTENT_DIR . '/wp-config-test.php', $contents );
		}

		return $config;
	}

	/**
	 * Write a Powered Cache advanced-cache.php drop-in.
	 *
	 * @return void
	 */
	private function write_advanced_cache() {
		$this->write_file( WP_CONTENT_DIR . '/advanced-cache.php', "<?php\ndefine( 'POWERED_CACHE_PAGE_CACHING', true );\n" );
	}

	/**
	 * It reports missing WP_CACHE and drop-in for an enabled page cache.
	 */
	public function test_reports_missing_page_cache_configuration() {
		$this->write_file( WP_CONTENT_DIR . '/advanced-cache.php', "<?php\n// Another cache plugin.\n" );

		$problems = $this->config( null )->get_configuration_problems( array( 'enable_page_cache' => true ) );

		$this->assertSame( array( 'wp_config', 'advanced_cache' ), $problems );
	}

	/**
	 * It accepts a valid WP_CACHE definition and Powered Cache drop-in.
	 */
	public function test_accepts_valid_page_cache_configuration() {
		$this->write_advanced_cache();

		$config = $this->config( "<?php\ndefine( 'WP_CACHE', true ); // Powered Cache\ndefine( 'DB_NAME', 'wp' );\n" );

		$this->assertSame( array(), $config->get_configuration_problems( array( 'enable_page_cache' => true ) ) );
	}

	/**
	 * It ignores commented out or disabled WP_CACHE definitions.
	 */
	public function test_rejects_commented_or_false_wp_cache() {
		$this->write_advanced_cache();

		$commented = $this->config( "<?php\n// define( 'WP_CACHE', true );\n" );
		$this->assertSame( array( 'wp_config' ), $commented->get_configuration_problems( array( 'enable_page_cache' => true ) ) );

		$disabled = $this->config( "<?php\ndefine( \"WP_CACHE\", false );\n" );
		$this->assertSame( array( 'wp_config' ), $disabled->get_configuration_problems( array( 'enable_page_cache' => true ) ) );
	}

	/**
	 * It skips page cache checks when page cache is disabled.
	 */
	public function test_skips_page_cache_checks_when_disabled() {
		$this->assertSame( array(), $this->config( null )->get_configuration_problems( array( 'enable_page_cache' => false ) ) );
	}

	/**
	 * It reports a missing object cache drop-in only when object cache is in use.
	 */
	public function test_reports_missing_object_cache_dropin() {
		$object_cache = WP_CONTENT_DIR . '/object-cache.php';
		$config       = $this->config( null );

		if ( file_exists( $object_cache ) ) {
			unlink( $object_cache ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		$this->assertSame( array( 'object_cache' ), $config->get_configuration_problems( array( 'object_cache' => 'redis' ) ) );
		$this->assertSame( array(), $config->get_configuration_problems( array( 'object_cache' => 'off' ) ) );
		$this->assertSame(
			array(),
			$config->get_configuration_problems(
				array(
					'object_cache' => 'redis',
					'dev_mode'     => true,
				)
			)
		);

		$this->write_file( $object_cache, "<?php\ndefine( 'POWERED_OBJECT_CACHE', true );\n" );

		$this->assertSame( array(), $config->get_configuration_problems( array( 'object_cache' => 'redis' ) ) );
	}
}
