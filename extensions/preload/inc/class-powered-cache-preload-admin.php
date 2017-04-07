<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Powered_Cache_Preload_Admin' ) ):

	class Powered_Cache_Preload_Admin extends Powered_Cache_Extension_Admin_Base {
		public $options;
		public $fields;
		public $interval_options;


		function __construct() {
			$this->interval_options = array(
				'0'       => __( 'No delay', 'powered-cache' ),
				'100000'  => __( '100ms', 'powered-cache' ),
				'250000'  => __( '250ms', 'powered-cache' ),
				'500000'  => __( '500ms', 'powered-cache' ),
				'750000'  => __( '750ms', 'powered-cache' ),
				'1000000' => __( '1s', 'powered-cache' ),
				'2000000' => __( '2s', 'powered-cache' ),
				'3000000' => __( '3s', 'powered-cache' ),
			);

			$this->fields = array(
				'post_count' => array(
					'default'   => 1000,
					'sanitizer' => 'intval',
				),
				'interval' => array(
					'default'   => 60,
					'sanitizer' => 'intval',
				),
				'taxonomies'      => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'homepage'   => array(
					'default'   => true,
					'sanitizer' => 'boolval',
				),
				'bot' => array(
					'default'   => false,
					'sanitizer' => 'boolval',
				),
				'bot_interval' => array(
					'default'   => '500000',
					'sanitizer' => 'intval',
				),
				'bot_automatic_crawl' => array(
					'default'   => false,
					'sanitizer' => 'boolval',
				),
				'sitemap_integration' => array(
					'default'   => false,
					'sanitizer' => 'boolval',
				),
				'sitemaps'   => array(
					'default'   => '',
					'sanitizer' => 'wp_kses_post',
				),
			);

			parent::__construct( array(
				'extension_id'   => 'preload',
				'extension_name' => __( 'Preload', 'powered-cache' ),
			) );

			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			add_action( 'admin_post_powered-cache-do-preload', array( $this, 'update_preloader_status' ) );

			$this->setup();
		}

		public function settings_page() {
			$settings_file[] = realpath( dirname( __FILE__ ) ) . '/settings.php';
			$settings_file[] = realpath( dirname( __FILE__ ) ) . '/premium-settings.php';
			parent::settings_template( $settings_file );
		}



		public function admin_notice() {
			if ( true !== powered_cache_get_option( 'enable_page_caching' ) ) {
				?>
				<div id="setting-error-settings_updated" class="error notice">
					<p><?php printf( __( '<b>%s:</b> You need enable page cache for preload feature.', 'powered-cache' ), __( 'Powered Cache', 'powered-cache' ) ) ?></strong></p>
				</div>
				<?php
			}
		}


		/**
		 * Is the preload process running?
		 * Probably we should check runtime option
		 */
		public function is_running() {
			if ( get_option( 'powered_cache_preload_runtime_option' ) ) {
				return true;
			}

			return false;
		}


		public function preload_cache_button() {

			$action = ( $this->is_running() ? 'stop' : 'start' );
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=powered-cache-do-preload&preload-status=' . $action ), 'powered-cache-do-preload' );

			$text = ( $this->is_running() ? esc_html__( 'Stop Preload', 'powered-cache' ) : esc_html__( 'Start Preload', 'powered-cache' ) );
			$html = '<a href="' . esc_url( $url ) . '" class="button" >' . $text . '</a>';

			return $html;
		}

		public function update_preloader_status() {
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'powered-cache-do-preload' ) ) {
				wp_nonce_ays( '' );
			}

			if ( isset( $_REQUEST['preload-status'] ) && 'start' === $_REQUEST['preload-status'] ) {
				Powered_Cache_Preload_Process::factory()->schedule_events();
				Powered_Cache_Admin_Helper::set_flash_message( __( 'Preload starting in 10 seconds.', 'powered-cache' ) );
			} else {
				Powered_Cache_Preload_Process::factory()->unschedule_events();
				Powered_Cache_Admin_Helper::set_flash_message( __( 'Preloading process stopped', 'powered-cache' ) );
			}

			wp_safe_redirect( wp_get_referer() );
			die();
		}

		/**
		 * Return an instance of the current class
		 *
		 * @since 1.0
		 * @return Powered_Cache_Preload_Admin
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