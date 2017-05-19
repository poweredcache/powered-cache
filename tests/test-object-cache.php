<?php

class Powered_Cache_Object_Cache_Test extends WP_UnitTestCase {


	public function setUp() {
		require_once POWERED_CACHE_ADMIN_DIR . 'class-powered-cache-admin-helper.php';

		$available_object_cache = Powered_Cache_Admin_Helper::available_object_caches();
		if ( empty( $available_object_cache ) ) {
			$this->markTestSkipped( 'There is no persistent object cache backend! Test skipping...' );

			return;
		}

		$new_settings = $default_settings = Powered_Cache_Config::factory()->default_settings();
		$new_settings['object_cache'] = $available_object_cache[0];
		powered_cache_save_settings( $default_settings, $new_settings );
	}


	public function test_wp_cache() {
		$this->assertTrue( wp_cache_add( 'foo', 'bar', 'baz' ) );
		$this->assertEquals( 'bar', wp_cache_get( 'foo', 'baz' ) );
		$this->assertTrue( wp_cache_delete( 'foo', 'baz' ) );
		$this->assertFalse( wp_cache_get( 'foo', 'baz' ) );
	}


	public function tearDown() {
		wp_cache_flush();
	}

}