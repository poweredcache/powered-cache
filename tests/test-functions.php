<?php

class Powered_Cache_Function_Test extends WP_UnitTestCase {

	public function test_enable_page_cache() {
		$new_settings = $default_settings = Powered_Cache_Config::factory()->default_settings();

		$new_settings['enable_page_caching'] = true;
		powered_cache_save_settings( $default_settings, $new_settings );
		$enable = powered_cache_get_option( 'enable_page_caching' );
		$this->assertTrue( $enable );
		$new_settings['enable_page_caching'] = false;
		powered_cache_save_settings( $default_settings, $new_settings );
		$disable = powered_cache_get_option( 'enable_page_caching' );

		$this->assertFalse( $disable );
	}

}
