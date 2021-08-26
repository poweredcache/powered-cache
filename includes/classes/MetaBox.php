<?php
/**
 * Metabox related functionalities
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use const PoweredCache\Constants\POST_META_DISABLE_CACHE_KEY;
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
	}

	/**
	 * Add metabox
	 */
	public function add_meta_boxes() {
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
			'post',
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
					<label for="<?php echo esc_attr( POST_META_DISABLE_CACHE_KEY ); ?>"><?php esc_html_e( 'Don\'t cache this page', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>
			<?php if ( $settings['enable_lazy_load'] ) : ?>
				<?php $is_lazyload_disabled = (bool) get_post_meta( $post->ID, POST_META_DISABLE_LAZYLOAD_KEY, true ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Disable Lazy Load', 'powered-cache' ); ?></legend>
					<input <?php checked( $is_lazyload_disabled, true ); ?> type="checkbox" id="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>" name="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>" value="1">
					<label for="<?php echo esc_attr( POST_META_DISABLE_LAZYLOAD_KEY ); ?>"><?php esc_html_e( 'Skip lazy loading for this post', 'powered-cache' ); ?></label>
				</fieldset>
			<?php endif; ?>
		</div>
		<?php
	}


	/**
	 * Register meta field for block editor
	 */
	public function register_meta_field() {
		$settings           = \PoweredCache\Utils\get_settings();
		$public_posts_types = get_post_types(
			[
				'public'             => true,
				'publicly_queryable' => true,
			],
			'names',
			'or'
		);

		foreach ( (array) $public_posts_types as $post_type ) {
			if ( $settings['enable_page_cache'] ) {
				register_post_meta(
					$post_type,
					POST_META_DISABLE_CACHE_KEY,
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'auth_callback' => function () {
							return current_user_can( 'edit_posts' );
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
							return current_user_can( 'edit_posts' );
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
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['powered_cache_post_meta_nonce'] ) ) {
			return;
		}

		// nonce check
		if ( ! wp_verify_nonce( wp_unslash( $_POST['powered_cache_post_meta_nonce'] ), 'powered_cache_post_meta' ) ) {
			return;
		}

		if ( isset( $_POST[ POST_META_DISABLE_CACHE_KEY ] ) ) {
			$cache_status = (bool) $_POST[ POST_META_DISABLE_CACHE_KEY ];
			update_post_meta( $post_id, POST_META_DISABLE_CACHE_KEY, $cache_status );
		} else {
			// don't need to store default value in meta
			delete_post_meta( $post_id, POST_META_DISABLE_CACHE_KEY );
		}

		if ( isset( $_POST[ POST_META_DISABLE_LAZYLOAD_KEY ] ) ) {
			$lazyload_status = (bool) $_POST[ POST_META_DISABLE_LAZYLOAD_KEY ];
			update_post_meta( $post_id, POST_META_DISABLE_LAZYLOAD_KEY, $lazyload_status );
		} else {
			// don't need to store default value in meta
			delete_post_meta( $post_id, POST_META_DISABLE_LAZYLOAD_KEY );
		}

	}


}
