<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Lazy_Load_Admin' ) ):

	class Powered_Cache_Lazy_Load_Admin extends Powered_Cache_Extension_Admin_Base {
		public $fields;

		function __construct() {
			$this->fields = array(
				'post_content'   => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'image'          => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'iframe'         => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'widget_text'    => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'post_thumbnail' => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'avatar'         => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
			);

			parent::__construct( array(
				'extension_id'   => 'lazyload',
				'extension_name' => __( 'Lazy Load', 'powered-cache' ),
			) );


			$this->setup();
		}

		public function settings_page() {
			$settings_file[] = realpath( dirname( __FILE__ ) ) . '/settings.php';
			parent::settings_template( $settings_file );
		}


		/**
		 * Return an instance of the current class
		 *
		 * @since 1.0
		 * @return Powered_Cache_Lazy_Load_Admin
		 */
		public static function factory() {
			static $instance = false;

			if ( ! $instance ) {
				$instance = new self();
			}

			return $instance;
		}
	}
endif;