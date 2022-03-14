<?php
/**
 * Metabox related functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\POST_META_DISABLE_CSS_OPTIMIZATION;
use const PoweredCache\Constants\POST_META_DISABLE_JS_OPTIMIZATION;
use const PoweredCache\Constants\POST_META_SPECIFIC_CRITICAL_CSS_KEY;
use const PoweredCache\Constants\POST_META_DISABLE_CACHE_KEY;
use const PoweredCache\Constants\POST_META_DISABLE_CRITICAL_CSS_KEY;
use const PoweredCache\Constants\POST_META_DISABLE_LAZYLOAD_KEY;

/**
 * Class MetaBox
 */
class MetaBox {

	/**
	 * Placeholder constructor
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return MetaBox
	 * @since 2.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Setup hooks
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_meta_field' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );

		foreach ( self::get_available_post_types() as $post_type ) {
			add_action( 'rest_after_insert_' . $post_type, [ $this, 'maybe_remove_default_meta' ], 10, 2 );
		}
	}

	/**
	 * Get available post types for meta register
	 *
	 * @return array
	 * @since 2.1
	 */
	private static function get_available_post_types() {
		$public_posts_types = get_post_types(
			[
				'public'             => true,
				'publicly_queryable' => true,
			],
			'names',
			'or'
		);

		unset( $public_posts_types['attachment'] );

		return (array) $public_posts_types;
	}

	/**
	 * Add metabox
	 */
	public function add_meta_boxes() {

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$is_block_editor_compatible = false;
		$back_compat                = true;

		if ( version_compare( get_bloginfo( 'version' ), '5.3', '<' ) ) {
			$is_block_editor_compatible = true;
			$back_compat                = false;
		}

		add_meta_box(
			'powered_cache_post_meta',
			esc_html__( 'Powered Cache', 'powered-cache' ),
			[ $this, 'meta_box' ],
			'',
			'side',
			'high',
			[
				'__block_editor_compatible_meta_box' => $is_block_editor_compatible,
				'__back_compat_meta_box'             => $back_compat,
			]
		);
	}

	/**
	 * Display metabox output for classic editor
	 *
	 * @param Object $post \WP_Post
	 */
	public function meta_box( $post ) {
		$settings          = \PoweredCache\Utils\get_settings();
		$is_cache_disabled = (bool) get_post_meta( $post->ID, POST_META_DISABLE_CACHE_KEY, true );

		if ( ! $settings['enable_page_cache'] && ! $settings['enable_lazy_load'] ) {
			return; // nothing to control post specific
		}
		?>
		<div id="powered-cache-meta-box">
			<?php wp_nonce_field( 'powered_cache_post_meta', 'powered_cache_post_meta_nonce' ); ?>
			<?php if ( $settings['enable_page_cache'] ) : ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Powered Cache Status', 'powered-cache' ); ?></legend>
					<input <?php checked( $is_cache_disabled, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_CACHE_KEY ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_CACHE_KEY ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_CACHE_KEY ); ?>"><?php esc_html_e( 'Don\'t cache this post', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>
			<?php if ( $settings['enable_lazy_load'] ) : ?>
				<?php $is_lazyload_disabled = (bool) get_post_meta( $post->ID, POST_META_DISABLE_LAZYLOAD_KEY, true ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Disable Lazy Load', 'powered-cache' ); ?></legend>
					<input <?php checked( $is_lazyload_disabled, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>"><?php esc_html_e( 'Disable lazy loading for this post', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>
			<?php if ( $settings['minify_css'] || $settings['combine_css'] ) : ?>
				<?php $is_css_optimization_disabled = (bool) get_post_meta( $post->ID, POST_META_DISABLE_CSS_OPTIMIZATION, true ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Disable CSS optimization (minify/concat) for this post', 'powered-cache' ); ?></legend>
					<input <?php checked( $is_css_optimization_disabled, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_CSS_OPTIMIZATION ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_CSS_OPTIMIZATION ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_CSS_OPTIMIZATION ); ?>"><?php esc_html_e( 'Disable CSS optimization (minify/concat) for this post', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>
			<?php if ( $settings['minify_js'] || $settings['combine_js'] ) : ?>
				<?php $is_js_optimization_disabled = (bool) get_post_meta( $post->ID, POST_META_DISABLE_JS_OPTIMIZATION, true ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Disable JS optimization (minify/concat) for this post', 'powered-cache' ); ?></legend>
					<input <?php checked( $is_js_optimization_disabled, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_JS_OPTIMIZATION ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_JS_OPTIMIZATION ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_JS_OPTIMIZATION ); ?>"><?php esc_html_e( 'Disable JS optimization (minify/concat) for this post', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>


			<?php if ( $settings['critical_css'] ) : ?>
				<?php $disable_critical = (bool) get_post_meta( $post->ID, POST_META_DISABLE_CRITICAL_CSS_KEY, true ); ?>
				<?php $generate_post_specific_critical = (bool) get_post_meta( $post->ID, POST_META_SPECIFIC_CRITICAL_CSS_KEY, true ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Disable Critical CSS on this post', 'powered-cache' ); ?></legend>
					<input <?php disabled( $generate_post_specific_critical, true ); ?> <?php checked( $disable_critical, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_CRITICAL_CSS_KEY ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_CRITICAL_CSS_KEY ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_CRITICAL_CSS_KEY ); ?>"><?php esc_html_e( 'Disable Critical CSS on this post', 'powered-cache' ); ?></label>
				</fieldset>

				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Generate specific Critical CSS', 'powered-cache' ); ?></legend>
					<input <?php disabled( $disable_critical, true ); ?> <?php checked( $generate_post_specific_critical, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_SPECIFIC_CRITICAL_CSS_KEY ); ?>" name="<?php echo esc_attr( POST_META_SPECIFIC_CRITICAL_CSS_KEY ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_SPECIFIC_CRITICAL_CSS_KEY ); ?>"><?php esc_html_e( 'Generate specific Critical CSS', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>

		</div>
		<?php
	}


	/**
	 * Register meta fields for block editor
	 */
	public function register_meta_field() {
		$settings = \PoweredCache\Utils\get_settings();

		foreach ( self::get_available_post_types() as $post_type ) {
			if ( $settings['enable_page_cache'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_CACHE_KEY,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);
			}

			if ( $settings['enable_lazy_load'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_LAZYLOAD_KEY,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);
			}

			if ( $settings['critical_css'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_CRITICAL_CSS_KEY,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'default'       => false,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);

				register_post_meta(
					$post_type,
					POST_META_SPECIFIC_CRITICAL_CSS_KEY,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'default'       => false,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);
			}

			if ( $settings['minify_css'] || $settings['combine_css'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_CSS_OPTIMIZATION,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'default'       => false,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);
			}

			if ( $settings['minify_js'] || $settings['combine_js'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_JS_OPTIMIZATION,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'default'       => false,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_others_posts' );
						},
					]
				);
			}
		}

	}

	/**
	 * Save meta info
	 *
	 * @param int    $post_id Post ID
	 * @param Object $post    \WP_Post
	 */
	public function save_post_meta( $post_id, $post ) {
		$post_id = absint( $post_id );

		if ( empty( $post_id ) || empty( $post ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// saved in a separate request in the block editor
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Check user has permission to edit.
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		if ( empty( $_POST['powered_cache_post_meta_nonce'] ) ) {
			return;
		}

		// nonce check
		if ( ! wp_verify_nonce( wp_unslash( $_POST['powered_cache_post_meta_nonce'] ), 'powered_cache_post_meta' ) ) {
			return;
		}

		$meta_keys = self::get_meta_keys();

		foreach ( $meta_keys as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				$value = (bool) $_POST[ $meta_key ];
				update_post_meta( $post_id, $meta_key, $value );
			} else {
				// don't need to store default value in meta
				delete_post_meta( $post_id, $meta_key );
			}
		}

	}

	/**
	 * Get powered cache meta keys
	 *
	 * @return array
	 * @since 2.1
	 */
	private static function get_meta_keys() {
		return [
			POST_META_DISABLE_CACHE_KEY,
			POST_META_DISABLE_LAZYLOAD_KEY,
			POST_META_DISABLE_CRITICAL_CSS_KEY,
			POST_META_SPECIFIC_CRITICAL_CSS_KEY,
			POST_META_DISABLE_CSS_OPTIMIZATION,
			POST_META_DISABLE_JS_OPTIMIZATION,
		];
	}

	/**
	 * Don't keep postmeta with default value. Block editor saves them?
	 *
	 * @param Object $post    \WP_Post
	 * @param Object $request \WP_REST_Request
	 *
	 * @since 2.1
	 */
	public function maybe_remove_default_meta( $post, $request ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$powered_cache_meta_keys = self::get_meta_keys();

		foreach ( $powered_cache_meta_keys as $meta_key ) {
			$meta_status = (bool) get_post_meta( $post->ID, $meta_key, true );
			if ( ! $meta_status ) {
				delete_post_meta( $post->ID, $meta_key );
			}
		}
	}


}
