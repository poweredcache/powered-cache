<?php
/**
 * Action Scheduler package tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Action Scheduler package test case.
 */
class ActionSchedulerPackage_Tests extends TestCase {

	/**
	 * It bundles Action Scheduler as a nested library, not as a standalone plugin.
	 */
	public function test_action_scheduler_is_nested_under_package_directory() {
		$entrypoint = PROJECT . 'package/woocommerce/action-scheduler/action-scheduler.php';

		$this->assertFileExists( $entrypoint );
		$this->assertStringContainsString( '/includes/package/woocommerce/action-scheduler/action-scheduler.php', $entrypoint );
		$this->assertFileDoesNotExist( PROJECT . '../action-scheduler.php' );
	}

	/**
	 * It keeps the upstream entrypoint metadata and version registration intact.
	 */
	public function test_action_scheduler_entrypoint_keeps_upstream_loader_contract() {
		$entrypoint = PROJECT . 'package/woocommerce/action-scheduler/action-scheduler.php';
		$contents   = file_get_contents( $entrypoint );

		$this->assertStringContainsString( 'Plugin Name: Action Scheduler', $contents );
		$this->assertMatchesRegularExpression( '/Version:\s*\d+\.\d+\.\d+/', $contents );
		$this->assertStringContainsString( "add_action( 'plugins_loaded', 'action_scheduler_register_", $contents );
		$this->assertStringContainsString( "add_action( 'plugins_loaded', array( 'ActionScheduler_Versions', 'initialize_latest_version' ), 1, 0 );", $contents );
	}

	/**
	 * It loads Action Scheduler before plugin bootstrap hooks can use it.
	 */
	public function test_action_scheduler_is_loaded_before_plugin_bootstrap() {
		$main_plugin_file = PROJECT . '../powered-cache.php';
		$contents         = file_get_contents( $main_plugin_file );

		$package_position   = strpos( $contents, "require_once POWERED_CACHE_PACKAGE_DIR . 'woocommerce/action-scheduler/action-scheduler.php';" );
		$bootstrap_position = strpos( $contents, 'Core\\setup();' );

		$this->assertIsInt( $package_position );
		$this->assertIsInt( $bootstrap_position );
		$this->assertLessThan( $bootstrap_position, $package_position );
	}
}
