<?php

class Powered_Cache_Function_Test extends WP_UnitTestCase {

	private $dummy_file;
	private $temp_dir;

	public function setUp() {
		$this->temp_dir   = trailingslashit( sys_get_temp_dir() );
		$this->dummy_file = $this->temp_dir . 'test.txt';

		if ( file_exists( $this->dummy_file ) ) {
			unlink( $this->dummy_file );
		}
	}

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


	public function test_powered_cache_fs() {
		global $powered_cache_fs;

		$this->assertFalse( $powered_cache_fs->exists( $this->dummy_file ) );
		$this->assertTrue( $powered_cache_fs->put_contents( $this->dummy_file, 'Hello World!' ) );
		$this->assertEquals( 'Hello World!', $powered_cache_fs->get_contents( $this->dummy_file ) );
	}


	public function tearDown() {
		if ( file_exists( $this->dummy_file ) ) {
			unlink( $this->dummy_file );
		}
	}

}
