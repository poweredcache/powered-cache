<?php
/**
 *
 * Settings page template
 *
 * @package PoweredCache
 * @subpackage PoweredCache/Settings
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="meta-box-sortables ui-sortable">

	<div class="postbox">
		<div class="inside">
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="powered_cache_remove_query_string"><?php _e( 'Remove query string', 'powered-cache' ); ?></label></th>
					<td>
						<label><input type="checkbox" <?php checked( powered_cache_get_option( 'remove_query_string' ), 1 ); ?> id="powered_cache_remove_query_string" name="powered_cache_settings[remove_query_string]" value="1">
							<?php _e( 'Remove query strings from CSS & JS resources', 'powered-cache' ); ?>
							(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#remove-query-string">?</a>)
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_rejected_user_agents"><?php _e( 'Rejected user agents', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_rejected_user_agents" name="powered_cache_settings[rejected_user_agents]" cols="50" rows="5"><?php echo powered_cache_get_option( 'rejected_user_agents' ); ?></textarea><br>
						<span class="description"><?php _e( 'Never send cache pages for these user agents.', 'powered-cache' ); ?></span>
						(<a target="_blank" target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#rejected-agents">?</a>)
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_rejected_cookies"><?php _e( 'Rejected cookies', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_rejected_cookies" name="powered_cache_settings[rejected_cookies]" cols="50" rows="5"><?php echo powered_cache_get_option( 'rejected_cookies' ); ?></textarea><br>
						<span class="description"><?php _e( 'Never cache pages that use the specified cookies.', 'powered-cache' ); ?></span>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#rejected-cookies">?</a>)
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_rejected_uri"><?php _e( 'Never cache the following pages', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_rejected_uri" name="powered_cache_settings[rejected_uri]" cols="50" rows="5"><?php echo powered_cache_get_option( 'rejected_uri' ); ?></textarea><br>
						<span class="description"><?php _e( 'Ignore the specified pages / directories. Supports regex.', 'powered-cache' ); ?></span>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#rejected-pages">?</a>)
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_accepted_query_strings"><?php _e( 'Accepted query strings', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_accepted_query_strings" name="powered_cache_settings[accepted_query_strings]" cols="50" rows="5"><?php echo powered_cache_get_option( 'accepted_query_strings' ); ?></textarea><br>
						<span class="description"><?php _e( 'Enter GET parameters line by line.', 'powered-cache' ); ?></span>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#allowed-query-strings">?</a>)
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="powered_cache_purge_additional_pages"><?php _e( 'Purge Additional Pages', 'powered-cache' ); ?></label></th>
					<td>
						<textarea id="powered_cache_purge_additional_pages" name="powered_cache_settings[purge_additional_pages]" cols="50" rows="5"><?php echo powered_cache_get_option( 'purge_additional_pages' ); ?></textarea><br>
						<span class="description"><?php _e( 'Enter pages line by line.', 'powered-cache' ); ?></span>
						(<a target="_blank" href="https://github.com/skopco/powered-cache/wiki/Advanced-caching-options#purge-additional-pages">?</a>)
					</td>
				</tr>

				</tbody>
			</table>

		</div>
		<!-- .inside -->

	</div>
	<!-- .postbox -->

</div>
