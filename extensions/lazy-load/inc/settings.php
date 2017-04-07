<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<table class="form-table">

	<tbody>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_post_content"><?php _e( 'Content', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_post_content" name="lazyload[post_content]" <?php checked( $this->get_option( 'post_content' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for post content.', 'powered-cache' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_image"><?php _e( 'Images', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_image" name="lazyload[image]" <?php checked( $this->get_option( 'image' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for images.', 'powered-cache' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_iframe"><?php _e( 'Iframe', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_iframe" name="lazyload[iframe]" <?php checked( $this->get_option( 'iframe' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for iframes.', 'powered-cache' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_widget_text"><?php _e( 'Widget', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_widget_text" name="lazyload[widget_text]" <?php checked( $this->get_option( 'widget_text' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for widgets.', 'powered-cache' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_post_thumbnail"><?php _e( 'Post Thumbnail', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_post_thumbnail" name="lazyload[post_thumbnail]" <?php checked( $this->get_option( 'post_thumbnail' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for post thumbnails.', 'powered-cache' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="powered_cache_lazyload_avatar"><?php _e( 'Avatar', 'powered-cache' ); ?></label></th>
		<td>
			<label><input type="checkbox" id="powered_cache_lazyload_avatar" name="lazyload[avatar]" <?php checked( $this->get_option( 'avatar' ), 1 ); ?> value="1" />
				<?php _e( 'Enable for avatars.', 'powered-cache' ); ?></label>
		</td>
	</tr>
	</tbody>
</table>
