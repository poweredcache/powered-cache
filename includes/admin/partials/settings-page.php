<?php
/**
 * Settings Page Template
 *
 * @package PoweredCache
 */

namespace PoweredCache\Admin\Partials\SettingsPage;

use function PoweredCache\Utils\can_configure_htaccess;
use function PoweredCache\Utils\can_configure_object_cache;
use function PoweredCache\Utils\can_control_all_settings;
use function PoweredCache\Utils\cdn_zones;
use function PoweredCache\Utils\get_available_object_caches;
use function PoweredCache\Utils\get_doc_url;
use function PoweredCache\Utils\get_timeout_with_interval;
use function PoweredCache\Utils\is_premium;
use function PoweredCache\Utils\js_execution_methods;
use function PoweredCache\Utils\sanitize_css;
use function PoweredCache\Utils\scheduled_cleanup_frequency_options;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPressVIPMinimum.Security.ProperEscapingFunction.htmlAttrNotByEscHTML
// phpcs:disable WordPressVIPMinimum.Security.ProperEscapingFunction.notAttrEscAttr

$settings = \PoweredCache\Utils\get_settings();

?>

<main class="sui-wrap">
	<div class="sui-header">
		<h1 class="sui-header-title">
			<img width="32" alt="<?php esc_html_e( 'Powered Cache Icon', 'powered-cache' ); ?>"
			     src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iMzJweCIgaWQ9IkxheWVyXzEiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMyIDMyOyIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMzIgMzIiIHdpZHRoPSIzMnB4IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQgMzM2KSI+PHBhdGggZD0iTS0xMTcuMTc2LTMzNC4wNjNoLTkuMzUzTC0xMzguMTExLTMyMGg5LjMwNGwtMTEuNzQ1LDE0LjA2M2wyNS4xMDUtMTguMzE2aC05LjgwOUwtMTE3LjE3Ni0zMzQuMDYzeiIvPjwvZz48L3N2Zz4=">
			<?php esc_html_e( 'Powered Cache', 'powered-cache' ); ?>
		</h1>

		<!-- Float element to Right -->
		<div class="sui-actions-right">
			<a href="<?php echo esc_url( get_doc_url( '/' ) ); ?>" target="_blank" class="sui-button sui-hidden-sm sui-button-blue">
				<i class="sui-icon-academy" aria-hidden="true"></i>
				<?php esc_html_e( 'Documentation', 'powered-cache' ); ?>
			</a>
		</div>
	</div>
	<?php \PoweredCache\Utils\settings_errors(); ?>

	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'powered_cache_update_settings', 'powered_cache_settings_nonce' ); ?>
		<section class="sui-row-with-sidenav">

			<!-- Navigation -->
			<div class="sui-sidenav" role="navigation">

				<ul class="sui-vertical-tabs">

					<li class="sui-vertical-tab current">
						<a href="#basic-options" role="button" data-tab="basic-options"><?php esc_html_e( 'Basic Options', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#advanced-options" role="button" data-tab="advanced-options"><?php esc_html_e( 'Advanced Options', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#file-optimization" role="button" data-tab="file-optimization"><?php esc_html_e( 'File Optimization', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#media-optimization" role="button" data-tab="media-optimization"><?php esc_html_e( 'Media Optimization', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#cdn-integration" role="button" data-tab="cdn-integration"><?php esc_html_e( 'CDN Integration', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#preload" role="button" data-tab="preload"><?php esc_html_e( 'Preload', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#db-optimization" role="button" data-tab="db-optimization"><?php esc_html_e( 'Database', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#extensions" role="button" data-tab="extensions"><?php esc_html_e( 'Extensions', 'powered-cache' ); ?></a>
					</li>

					<li class="sui-vertical-tab">
						<a href="#misc-settings" role="button" data-tab="misc-settings"><?php esc_html_e( 'Misc', 'powered-cache' ); ?></a>
					</li>

					<?php
					/**
					 * Fires after settings nav. Used for injecting new settings tabs..
					 *
					 * @hook  powered_cache_admin_page_after_settings_nav
					 *
					 * @since 2.0
					 */
					do_action( 'powered_cache_admin_page_after_settings_nav' );
					?>

				</ul>

				<button type="submit" name="powered_cache_form_action" class="sui-button sui-margin-bottom sui-hidden-sm sui-button-blue" value="save_settings"><?php esc_html_e( 'Save Changes', 'powered-cache' ); ?></button>
			</div>

			<!-- TAB: Regular -->
			<div class="sui-box" id="basic-options" data-tab="basic-options" style="">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'Basic Options', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body">

					<!-- Page Cache settings -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Page Cache', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="enable_page_cache" class="sui-toggle">
									<input
											type="checkbox"
											id="enable_page_cache"
											name="enable_page_cache"
											aria-labelledby="enable_page_cache_label"
											aria-describedby="enable_page_cache_description"
										<?php checked( 1, $settings['enable_page_cache'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="enable_page_cache_label" class="sui-toggle-label"><?php esc_html_e( 'Enable page cache', 'powered-cache' ); ?></span>
								</label>
								<span id="enable_page_cache_description" class="sui-description"><?php esc_html_e( 'Enable page caching that will reduce the response time of your site.', 'powered-cache' ); ?>
									<a href="<?php echo esc_url( get_doc_url( 'page-caching' ) ); ?>" target="_blank"><?php esc_html_e( 'Learn More', 'powered-cache' ); ?></a>
								</span>
							</div>
						</div>
					</div>

					<?php if ( can_configure_object_cache() ) : ?>
						<!-- Object Caching settings -->
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Object Cache', 'powered-cache' ); ?></span>
								<span class="sui-description"><?php esc_html_e( 'Speed up dynamic pageviews.', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<select id="object_cache" name="object_cache">
										<option value="off" <?php selected( $settings['object_cache'], 'off' ); ?>><?php esc_html_e( 'Off', 'powered-cache' ); ?></option>
										<?php foreach ( get_available_object_caches() as $object_cache_backend ) : ?>
											<option <?php selected( $settings['object_cache'], $object_cache_backend ); ?> value="<?php echo esc_attr( $object_cache_backend ); ?>"><?php echo esc_attr( ucfirst( $object_cache_backend ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<span id="object_cache_description" class="sui-description"><?php esc_html_e( 'You will also need to configure the object cache backend.', 'powered-cache' ); ?><a href="<?php echo esc_url( get_doc_url( 'object-caching' ) ); ?>" target="_blank"><?php esc_html_e( 'Learn More', 'powered-cache' ); ?></a></span>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- Mobile Cache settings -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Mobile Cache', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'Cache for mobile devices', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="cache_mobile" class="sui-toggle">
									<input type="checkbox"
									       value="1"
									       name="cache_mobile"
									       id="cache_mobile"
									       aria-controls="cache_mobile_separate_file_controls"
										<?php checked( 1, $settings['cache_mobile'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="toggle-6-label" class="sui-toggle-label"><?php esc_html_e( 'Enable caching for mobile devices.', 'powered-cache' ); ?></span>
								</label>

								<div style=" <?php echo( ! $settings['cache_mobile'] ? 'display:none' : '' ); ?>" tabindex="0" id="cache_mobile_separate_file_controls">
									<div class="sui-form-field">
										<label for="cache_mobile_separate_file" class="sui-toggle">
											<input type="checkbox"
											       value="1"
											       name="cache_mobile_separate_file"
											       id="cache_mobile_separate_file"
												<?php checked( 1, $settings['cache_mobile_separate_file'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="cache_mobile_separate_file_label" class="sui-toggle-label"><?php esc_html_e( 'Use separate cache file for mobile.', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>

							</div>
						</div>
					</div>

					<!-- Logged-in cache settings -->
					<div class="sui-box-settings-row">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Logged in user cache', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'It creates a separate cache for each users.', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="loggedin_user_cache" class="sui-toggle">
									<input type="checkbox"
									       value="1"
									       name="loggedin_user_cache"
									       id="loggedin_user_cache"
										<?php checked( 1, $settings['loggedin_user_cache'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="loggedin_user_cache_label" class="sui-toggle-label"><?php esc_html_e( 'Enable caching for logged-in users', 'powered-cache' ); ?></span>
									<span class="sui-description"><?php esc_html_e( 'This feature is useful when you have user-specific or restricted content on your website', 'powered-cache' ); ?></span>
								</label>
							</div>
						</div>
					</div>

					<?php if ( function_exists( 'gzencode' ) ) : ?>
						<!-- Gzip compression settings -->
						<div class="sui-box-settings-row">

							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Gzip Compression', 'powered-cache' ); ?></span>
							</div>
							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<label for="gzip_compression" class="sui-toggle">
										<input type="checkbox"
										       value="1"
										       name="gzip_compression"
										       id="gzip_compression"
										       aria-labelledby="gzip_compression_label"
											<?php checked( 1, $settings['gzip_compression'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="gzip_compression_label" class="sui-toggle-label"><?php esc_html_e( 'Enable gzip compression', 'powered-cache' ); ?></span>
										<span class="sui-description"><?php esc_html_e( 'The pages will be gzip-compressed at the PHP level when this option enabled. However, we highly recommended gzip compression on a web server, our pre-defined configurations for .htaccess and Nginx configurations come with gzip compression.', 'powered-cache' ); ?></span>
									</label>
								</div>
							</div>

						</div>
					<?php endif; ?>

					<!-- Cache Timeout -->
					<?php list( $cache_timeout, $selected_interval ) = get_timeout_with_interval( $settings['cache_timeout'] ); ?>
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Cache Timeout', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'The lifespan of the cached page. Expired cache purges with WP Cron.', 'powered-cache' ); ?></span>
						</div>
						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<input
										name="cache_timeout"
										id="cache_timeout"
										class="sui-form-control sui-field-has-suffix"
										min="1"
										type="number"
										value="<?php echo absint( $cache_timeout ); ?>"
								/>
								<span class="sui-field-suffix" style="margin-left:0;">
									<div class="sui-form-field sui-input-md">
										<select id="cache_timeout_interval" name="cache_timeout_interval" class="sui-form-control">
											<option <?php selected( 'MINUTE', $selected_interval ); ?> value="MINUTE"><?php echo esc_html__( 'Minute', 'powered-cache' ); ?></option>
											<option <?php selected( 'HOUR', $selected_interval ); ?> value="HOUR"><?php echo esc_html__( 'Hour', 'powered-cache' ); ?></option>
											<option <?php selected( 'DAY', $selected_interval ); ?> value="DAY"><?php echo esc_html__( 'Day', 'powered-cache' ); ?></option>
										</select>
									</div>
								</span>
							</div>
						</div>
					</div>

				</div>

				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>

			</div>

			<!-- TAB: Advanced Options -->
			<div class="sui-box" id="advanced-options" data-tab="advanced-options" style="display: none">
				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'Advanced Options', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body">

					<?php if ( can_configure_htaccess() ) : ?>
						<!-- .htaccess settings -->
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( '.htaccess Configuration', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<label for="auto_configure_htaccess" class="sui-toggle">
										<input
												type="checkbox"
												id="auto_configure_htaccess"
												name="auto_configure_htaccess"
												aria-labelledby="auto_configure_htaccess_label"
											<?php checked( 1, $settings['auto_configure_htaccess'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="auto_configure_htaccess_label" class="sui-toggle-label"><?php esc_html_e( 'Automatically configure .htaccess', 'powered-cache' ); ?></span>
									</label>
									<span class="sui-description"><?php esc_html_e( 'You are using a .htaccess compatible server. Powered Cache automatically configures .htaccess to giving the best performance when this option is enabled. Highly recommend keeping this option enabled unless you are keeping .htaccess under a version control system.', 'powered-cache' ); ?></span>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- Rejected user agents -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Rejected User Agents', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">

								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="rejected_user_agents" class="sui-label"><i><?php esc_html_e( 'Enter rejected user agents (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)"
												id="rejected_user_agents"
												name="rejected_user_agents"
												class="sui-form-control"
												aria-describedby="rejected_user_agents_description"
												rows="5"
										><?php echo esc_textarea( $settings['rejected_user_agents'] ); ?></textarea>
										<span id="rejected_user_agents_description" class="sui-description">
											<?php esc_html_e( 'Never send cached results for these user agents.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'rejected-user-agents' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Rejected cookies -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label" id="rejected_cookies_label"><?php esc_html_e( 'Rejected Cookies', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="rejected_cookies" class="sui-label"><i><?php esc_html_e( 'Enter rejected cookies (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="wordpress_"
												id="rejected_cookies"
												name="rejected_cookies"
												class="sui-form-control"
												aria-labelledby="rejected_cookies_label"
												aria-describedby="rejected_cookies_description"
												rows="5"
										><?php echo esc_textarea( $settings['rejected_cookies'] ); ?></textarea>
										<span id="rejected_cookies_description" class="sui-description"><?php esc_html_e( 'Never cache pages that use the specified cookies.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'rejected-cookies' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Cookie Vary -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label" id="rejected_cookies_label"><?php esc_html_e( 'Vary Cookies', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="vary_cookies" class="sui-label"><i><?php esc_html_e( 'Enter vary cookies (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="(Eg: cookie_notice_accepted)"
												id="vary_cookies"
												name="vary_cookies"
												class="sui-form-control"
												aria-labelledby="vary_cookies_label"
												aria-describedby="vary_cookies_description"
												rows="5"
										><?php echo esc_textarea( $settings['vary_cookies'] ); ?></textarea>
										<span id="vary_cookies_description" class="sui-description"><?php esc_html_e( 'Separate cache will be generated based on the cookie match.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'vary-cookies' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Never cache the following pages -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Never cache the following pages', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">

								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="rejected_uri" class="sui-label"><i><?php esc_html_e( 'Enter pages that will never get cached (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="/example-page"
												id="rejected_uri"
												name="rejected_uri"
												class="sui-form-control"
												aria-describedby="rejected_uri_description"
												rows="5"
										><?php echo esc_textarea( $settings['rejected_uri'] ); ?></textarea>
										<span id="rejected_uri_description" class="sui-description"><?php esc_html_e( 'Ignore the specified pages / directories. Supports regex.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'ignored-pages' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Cache query strings -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Cache Query Strings', 'powered-cache' ); ?></span>
						</div>
						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="cache_query_strings" class="sui-label"><i><?php esc_html_e( 'Enter query strings for cache (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												id="cache_query_strings"
												name="cache_query_strings"
												class="sui-form-control"
												aria-describedby="cache_query_strings_description"
												rows="5"
										><?php echo esc_textarea( $settings['cache_query_strings'] ); ?></textarea>
										<span id="cache_query_strings_description" class="sui-description">
											<?php esc_html_e( 'Powered Cache will create seperate caching file for the value of these query strings.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'cache-query-strings' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Ignored query strings -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Ignored Query Strings', 'powered-cache' ); ?></span>
						</div>
						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="ignored_query_strings" class="sui-label"><i><?php esc_html_e( 'Enter allowed query parameter (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="utm_"
												id="ignored_query_strings"
												name="ignored_query_strings"
												class="sui-form-control"
												aria-describedby="ignored_query_strings_description"
												rows="5"
										><?php echo esc_textarea( $settings['ignored_query_strings'] ); ?></textarea>
										<span id="ignored_query_strings_description" class="sui-description">
											<?php esc_html_e( 'Powered Cache will ignore these query string and serve the standard cache file. Tracking parameters such as utm_* ignored by default.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'ignored-query-strings' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Purge Additional Pages -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Purge Additional Pages', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="purge_additional_pages" class="sui-label"><i><?php esc_html_e( 'Enter additional pages will purged (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="/sample-page"
												id="purge_additional_pages"
												name="purge_additional_pages"
												class="sui-form-control"
												aria-describedby="purge_additional_pages_description"
												rows="5"
										><?php echo esc_textarea( $settings['purge_additional_pages'] ); ?></textarea>
										<span id="purge_additional_pages_description" class="sui-description">
											<?php esc_html_e( 'Powered Cache is smart enough to purge only necessary pages during the post changes, however sometimes particular pages need to purge. (eg. the pages that use custom shortcode)', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/advanced-options/', 'purge-additional-pages' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- TAB: File Optimization -->
			<div class="sui-box" id="file-optimization" data-tab="file-optimization" style="display: none;">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'File Optimization', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body sui-upsell-items">
					<?php
					/**
					 * Fires before file optimization section
					 *
					 * @hook  powered_cache_admin_page_before_file_optimization
					 *
					 * @since 2.0
					 */
					do_action( 'powered_cache_admin_page_before_file_optimization' );
					?>
					<!-- Basic Settings -->
					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_basic_settings_classes', 'sui-box-settings-row ' ) ); ?>">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Basic Settings', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="minify_html" class="sui-toggle">
									<input
											type="checkbox"
											id="minify_html"
											name="minify_html"
											aria-labelledby="minify_html_label"
											value="1"
										<?php checked( 1, $settings['minify_html'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="minify_html_label" class="sui-toggle-label"><?php esc_html_e( 'Minify HTML', 'powered-cache' ); ?></span>
									<span id="minify_html_label_description" class="sui-description"><?php esc_html_e( 'Removes all whitespace characters from the HTML output, minimizing the HTML size.', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div class="sui-form-field">
								<label for="combine_google_fonts" class="sui-toggle">
									<input
											type="checkbox"
											id="combine_google_fonts"
											name="combine_google_fonts"
											aria-labelledby="combine_google_fonts_label"
											aria-controls="swap_google_fonts_display_control"
											value="1"
										<?php checked( 1, $settings['combine_google_fonts'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="combine_google_fonts_label" class="sui-toggle-label"><?php esc_html_e( 'Combine Google Fonts', 'powered-cache' ); ?></span>
									<span id="combine-google-fonts-description" class="sui-description">
										<?php esc_html_e( 'Combines all Google Fonts URLs into a single URL and optimizes loading of that URL.', 'powered-cache' ); ?>
										(<a href="<?php echo esc_url( get_doc_url( '/combine-google-fonts/' ) ); ?>" target="_blank">?</a>)
									</span>
								</label>
							</div>

							<div style=" <?php echo( ! $settings['combine_google_fonts'] ? 'display:none' : '' ); ?>" tabindex="0" id="swap_google_fonts_display_control">
								<div class="sui-form-field">
									<label for="swap_google_fonts_display" class="sui-toggle">
										<input type="checkbox"
										       value="1"
										       name="swap_google_fonts_display"
										       id="swap_google_fonts_display"
											<?php checked( 1, $settings['swap_google_fonts_display'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="swap_google_fonts_display_label" class="sui-toggle-label"><?php esc_html_e( 'Swap Google Fonts Display', 'powered-cache' ); ?></span>
										<span id="swap_google_fonts_display_description" class="sui-description">
											<?php esc_html_e( 'Ensure text remains visible during webfont load.', 'powered-cache' ); ?>
										</span>
									</label>
									<br>
								</div>
							</div>
							<div class="sui-form-field">
								<label for="use_bunny_fonts" class="sui-toggle">
									<input type="checkbox"
									       value="1"
									       name="use_bunny_fonts"
									       id="use_bunny_fonts"
										<?php checked( 1, $settings['use_bunny_fonts'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="use_bunny_fonts_label" class="sui-toggle-label"><?php esc_html_e( 'Use Bunny Fonts', 'powered-cache' ); ?></span>
									<span id="use_bunny_fonts_description" class="sui-description">
										<?php esc_html_e( 'Use Bunny Fonts as drop-in replacement for Google Fonts', 'powered-cache' ); ?>
									</span>
								</label>
							</div>


						</div>
					</div>

					<!-- CSS Files -->
					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_css_classes', 'sui-box-settings-row ' ) ); ?>">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'CSS Optimization', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/css-optimization/' ) ); ?>" target="_blank">?</a>)
							</span>
						</div>

						<div class="sui-box-settings-col-2">

							<div class="sui-form-field">
								<label for="minify_css" class="sui-toggle">
									<input
											type="checkbox"
											id="minify_css"
											name="minify_css"
											aria-labelledby="minify_css_label"
											value="1"
										<?php checked( 1, $settings['minify_css'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="minify_css_label" class="sui-toggle-label"><?php esc_html_e( 'Minify CSS' ); ?></span>
									<span id="minify_css_description" class="sui-description"><?php esc_html_e( 'Minify CSS files', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div class="sui-form-field">
								<label for="combine_css" class="sui-toggle">
									<input
											type="checkbox"
											id="combine_css"
											name="combine_css"
											aria-labelledby="combine_css_label"
											value="1"
										<?php checked( 1, $settings['combine_css'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="combine_css_label" class="sui-toggle-label"><?php esc_html_e( 'Combine CSS files' ); ?></span>
									<span id="combine_css_description" class="sui-description"><?php esc_html_e( 'Combine CSS files to reduce HTTP requests', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="excluded_css_files" class="sui-label"><i><?php esc_html_e( 'CSS files to exclude (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="e.g /wp-content/themes/example/style/custom.css"
												id="excluded_css_files"
												name="excluded_css_files"
												class="sui-form-control"
												aria-labelledby="label-unique-id"
												aria-describedby="excluded_css_files_description"
												rows="5"
										><?php echo esc_textarea( $settings['excluded_css_files'] ); ?></textarea>
										<span id="excluded_css_files_description" class="sui-description">
											<?php esc_html_e( 'Listed files will not get minified or combined', 'powered-cache' ); ?>
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Critical CSS -->
						<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_critical_css_classes', 'sui-box-settings-row ' ) ); ?> <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Critical CSS', 'powered-cache' ); ?>
									(<a href="<?php echo esc_url( get_doc_url( '/critical-css/' ) ); ?>" target="_blank">?</a>)
									<?php if ( ! is_premium() ) : ?>
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="sui-description"><?php esc_html_e( 'Extract & Inline Critical-path CSS from HTML', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">

							<div class="sui-form-field">
								<label for="critical_css" class="sui-toggle">
									<input
											type="checkbox"
											id="critical_css"
											name="critical_css"
											aria-labelledby="critical_css_label"
											aria-controls="critical_css_fallback_controls"
											value="1"
										<?php checked( 1, $settings['critical_css'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="critical_css_label" class="sui-toggle-label"><?php esc_html_e( ' Critical CSS' ); ?></span>
									<span id="critical_css_description" class="sui-description"><?php esc_html_e( 'Critical CSS is a technique that extracts the CSS above the fold to display the page as quickly as possible.', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div style=" <?php echo( ! $settings['critical_css'] ? 'display:none' : '' ); ?>" tabindex="0" id="critical_css_fallback_controls">
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="additional_critical_css_files" class="sui-label"><i><?php esc_html_e( 'Additonal files to critical (one per line)', 'powered-cache' ); ?></i></label>
											<textarea
													id="critical_css_additional_files"
													name="critical_css_additional_files"
													class="sui-form-control"
													aria-labelledby="label-unique-id"
													aria-describedby="critical_css_additional_files_description"
													rows="5"
											><?php echo  esc_textarea( $settings['critical_css_additional_files'] ); // phpcs:ignore ?></textarea>
											<span id="critical_css_additional_files_description" class="sui-description">
												<?php esc_html_e( 'Critical CSS uses resources found in the head section of the page. If you need to include additional resources, add them here.', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="critical_css_excluded_files" class="sui-label"><i><?php esc_html_e( 'Excluded files from critical (one per line)', 'powered-cache' ); ?></i></label>
											<textarea
													id="critical_css_excluded_files"
													name="critical_css_excluded_files"
													class="sui-form-control"
													aria-labelledby="label-unique-id"
													aria-describedby="critical_css_excluded_files_description"
													rows="5"
											><?php echo esc_textarea( $settings['critical_css_excluded_files'] ); // phpcs:ignore ?></textarea>
											<span id="critical_css_excluded_files_description" class="sui-description">
												<?php esc_html_e( 'Ignore these files during the Critical CSS generation.', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="critical_css_appended_content" class="sui-label"><i><?php esc_html_e( 'Append to Critical CSS', 'powered-cache' ); ?></i></label>
											<textarea
													id="critical_css_appended_content"
													name="critical_css_appended_content"
													class="sui-form-control"
													aria-labelledby="label-unique-id"
													aria-describedby="critical_css_appended_content_description"
													rows="5"
											><?php echo wp_unslash( sanitize_css( $settings['critical_css_appended_content'] ) ); // phpcs:ignore ?></textarea>
											<span id="critical_css_appended_content_description" class="sui-description">
												<?php esc_html_e( 'Extend Critical CSS by appending custom CSS here.', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="critical_css_fallback" class="sui-label"><i><?php esc_html_e( 'Fallback Critical CSS ', 'powered-cache' ); ?></i></label>
											<textarea
													id="critical_css_fallback"
													name="critical_css_fallback"
													class="sui-form-control"
													aria-labelledby="label-unique-id"
													aria-describedby="critical_css_fallback_description"
													rows="5"
											><?php echo wp_unslash( sanitize_css( $settings['critical_css_fallback'] ) ); // phpcs:ignore ?></textarea>
											<span id="critical_css_fallback_description" class="sui-description">
												<?php esc_html_e( 'The fallback CSS if auto-generated CSS is incomplete.', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>

							</div>
						</div>
					</div>


					<!-- Unused CSS -->
					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_ucss_classes', 'sui-box-settings-row ' ) ); ?> <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Unused CSS', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/remove-unused-css/' ) ); ?>" target="_blank">?</a>)
								<?php if ( ! is_premium() ) : ?>
									<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="sui-description"><?php esc_html_e( 'Remove Unused CSS', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="remove_unused_css" class="sui-toggle">
									<input
										type="checkbox"
										id="remove_unused_css"
										name="remove_unused_css"
										aria-labelledby="remove_unused_css_label"
										aria-controls="remove_unused_css_safelist"
										value="1"
										<?php checked( 1, $settings['remove_unused_css'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="remove_unused_css_label" class="sui-toggle-label"><?php esc_html_e( 'Remove Unused CSS' ); ?></span>
									<span id="remove_unused_css_description" class="sui-description"><?php esc_html_e( 'It reduces page size by removing all CSS and stylesheets that are not used while keeping only the used CSS.', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div style=" <?php echo( ! $settings['remove_unused_css'] ? 'display:none' : '' ); ?>" tabindex="0" id="remove_unused_css_safelist">
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="ucss_safelist" class="sui-label"><i><?php esc_html_e( 'Safelist', 'powered-cache' ); ?></i></label>
											<textarea
												id="ucss_safelist"
												name="ucss_safelist"
												class="sui-form-control"
												aria-labelledby="label-unique-id"
												aria-describedby="ucss_safelist_description"
												rows="5"
											><?php echo  esc_textarea( $settings['ucss_safelist'] ); // phpcs:ignore ?></textarea>
											<span id="ucss_safelist_description" class="sui-description">
												<?php esc_html_e( 'Specify CSS selectors that should not be removed. (one per line)', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>
								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="ucss_excluded_files" class="sui-label"><i><?php esc_html_e( 'Excluded files', 'powered-cache' ); ?></i></label>
											<textarea
												id="ucss_excluded_files"
												name="ucss_excluded_files"
												class="sui-form-control"
												aria-labelledby="label-unique-id"
												aria-describedby="ucss_excluded_files_description"
												rows="5"
											><?php echo  esc_textarea( $settings['ucss_excluded_files'] ); // phpcs:ignore ?></textarea>
											<span id="ucss_excluded_files_description" class="sui-description">
												<?php esc_html_e( 'Specify CSS files that should be ignored during the UCSS generation process.  (one per line)', 'powered-cache' ); ?>
											</span>
										</div>
									</div>
								</div>
							</div>

						</div>
					</div>


					<!-- JS Files -->
					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_js_classes', 'sui-box-settings-row ' ) ); ?>">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'JavaScript Optimization', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/js-optimization/' ) ); ?>" target="_blank">?</a>)
							</span>
						</div>

						<div class="sui-box-settings-col-2">

							<div class="sui-form-field">
								<label for="minify_js" class="sui-toggle">
									<input
											type="checkbox"
											id="minify_js"
											name="minify_js"
											aria-labelledby="minify_js_label"
											value="1"
										<?php checked( 1, $settings['minify_js'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="minify_js_label" class="sui-toggle-label"><?php esc_html_e( 'Minify JavaScript Files', 'powered-cache' ); ?></span>
									<span id="minify_js_description" class="sui-description"><?php esc_html_e( 'Removes whitespace and comments to reduce file size', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div class="sui-form-field">
								<label for="combine_js" class="sui-toggle">
									<input
											type="checkbox"
											id="combine_js"
											name="combine_js"
											aria-labelledby="combine_js_label"
											value="1"
										<?php checked( 1, $settings['combine_js'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="combine_js_label" class="sui-toggle-label"><?php esc_html_e( 'Combine JavaScript Files', 'powered-cache' ); ?></span>
									<span id="combine_js_description" class="sui-description"><?php esc_html_e( 'Combines JS files into fewer files to reduce HTTP requests.', 'powered-cache' ); ?></span>
								</label>
							</div>

							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="excluded_js_files" class="sui-label"><i><?php esc_html_e( 'JavaScript files to exclude (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="e.g /wp-content/themes/example/js/custom.js"
												id="excluded_js_files"
												name="excluded_js_files"
												class="sui-form-control"
												aria-labelledby="label-unique-id"
												aria-describedby="excluded_js_files_description"
												rows="5"
										><?php echo esc_textarea( $settings['excluded_js_files'] ); ?></textarea>
										<span id="excluded_js_files_description" class="sui-description">
											<?php esc_html_e( 'Listed files will not get minified or combined', 'powered-cache' ); ?>
										</span>
									</div>
								</div>
							</div>

						</div>
					</div>

					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_fo_js_classes', 'sui-box-settings-row ' ) ); ?>">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label" id="js_execution_method_label">
								<?php esc_html_e( 'JavaScript Execution', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/js-execution/' ) ); ?>" target="_blank">?</a>)
							</span>
							<span class="sui-description"></span>
						</div>
						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<select id="js_execution_method" name="js_execution_method" aria-labelledby="js_execution_method_label" aria-describedby="js_execution_method_description">
									<?php foreach ( js_execution_methods() as $method => $name ) : ?>
										<option <?php selected( $settings['js_execution_method'], esc_attr( $method ) ); ?> value="<?php echo esc_attr( $method ); ?>"><?php echo esc_attr( $name ); ?></option>
									<?php endforeach; ?>
								</select>

								<span id="js_execution_method_description" class="sui-description"><?php esc_html_e( 'It determines how browsers execute the JS scripts.', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-form-field">
								<label for="js_execution_optimized_only" class="sui-toggle">
									<input
											type="checkbox"
											id="js_execution_optimized_only"
											name="js_execution_optimized_only"
											aria-labelledby="js_execution_optimized_only_label"
											value="1"
										<?php checked( 1, $settings['js_execution_optimized_only'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="js_execution_optimized_only_label" class="sui-toggle-label"><?php esc_html_e( 'Use execution method for the optimized scripts only', 'powered-cache' ); ?></span>
									<span id="js_execution_optimized_only_description" class="sui-description"><?php esc_html_e( 'When this option is turned off all JS scripts will be executed in the same way.', 'powered-cache' ); ?></span>
								</label>
							</div>

						</div>

					</div>
				</div>

				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- TAB: Media -->
			<div class="sui-box" id="media-optimization" data-tab="media-optimization" style="display: none;">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'Media Optimization', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body">
					<?php do_action( 'powered_cache_admin_page_before_media_optimization' ); ?>
					<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Image Optimization', 'powered-cache' ); ?>
								<?php if ( ! is_premium() ) : ?>
									<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
								<?php else : ?>
									<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Beta', 'powered-cache' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="enable_image_optimization" class="sui-toggle">
									<input
											type="checkbox"
											id="enable_image_optimization"
											name="enable_image_optimization"
											aria-labelledby="enable_image_optimization_label"
											aria-describedby="enable_image_optimization_description"
											value="1"
										<?php checked( 1, $settings['enable_image_optimization'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="enable_image_optimization_label" class="sui-toggle-label"><?php esc_html_e( 'Enable Image Optimization Service', 'powered-cache' ); ?></span>
									<span id="enable_image_optimization_description" class="sui-description"><?php esc_html_e( 'Powered Cache image optimization service instantly optimizes images from our global network of servers. The images will be served in WebP format when browsers support the WebP.', 'powered-cache' ); ?>
										(<a href="<?php echo esc_url( get_doc_url( '/image-optimization/' ) ); ?>" target="_blank">?</a>)
									</span>
								</label>
							</div>
						</div>
					</div>

					<!-- Lazy Load settings -->

					<div class="<?php echo esc_attr( apply_filters( 'powered_cache_admin_page_lazy_load_settings_classes', 'sui-box-settings-row' ) ); ?>">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Lazy Load', 'powered-cache' ); ?></span>
							<span class="sui-description">
								<?php esc_html_e( 'Loads images and iframes only when visible to the user.', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/enable-lazy-load/' ) ); ?>" target="_blank">?</a>)
							</span>
						</div>

						<div class="sui-box-settings-col-2">

							<div class="sui-form-field">
								<div class="sui-row ">
									<label for="enable_lazy_load" class="sui-toggle">
										<input
												type="checkbox"
												id="enable_lazy_load"
												name="enable_lazy_load"
												aria-labelledby="enable_lazy_load_label"
												aria-controls="lazy-load-details"
												value="1"
											<?php checked( 1, $settings['enable_lazy_load'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="enable_lazy_load_label" class="sui-toggle-label"><?php esc_html_e( 'Enable Lazy Load', 'powered-cache' ); ?></span>
									</label>
								</div>

								<div style=" <?php echo( ! $settings['enable_lazy_load'] ? 'display:none' : '' ); ?>" tabindex="0" id="lazy-load-details" class="sui-toggle-content sui-border-frame">

									<div class="sui-form-field">
										<div class="sui-row">
											<label for="lazy_load_post_content" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_post_content"
														name="lazy_load_post_content"
														value="1"
													<?php checked( 1, $settings['lazy_load_post_content'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_post_content_label"><?php esc_html_e( 'Enable for post content.', 'powered-cache' ); ?></span>
											</label>
										</div>

										<div class="sui-row">
											<label for="lazy_load_images" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_images"
														name="lazy_load_images"
														value="1"
													<?php checked( 1, $settings['lazy_load_images'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_images_label"><?php esc_html_e( 'Enable for images.', 'powered-cache' ); ?></span>
											</label>
										</div>

										<div class="sui-row">
											<label for="lazy_load_iframes" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_iframes"
														name="lazy_load_iframes"
														value="1"
													<?php checked( 1, $settings['lazy_load_iframes'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_iframes_label"><?php esc_html_e( 'Enable for iframes.', 'powered-cache' ); ?></span>
											</label>
										</div>
										<div class="sui-row">
											<label for="lazy_load_widgets" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_widgets"
														name="lazy_load_widgets"
														value="1"
													<?php checked( 1, $settings['lazy_load_widgets'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_widgets_label"><?php esc_html_e( 'Enable for widgets.', 'powered-cache' ); ?></span>
											</label>
										</div>
										<div class="sui-row">
											<label for="lazy_load_post_thumbnail" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_post_thumbnail"
														name="lazy_load_post_thumbnail"
														value="1"
													<?php checked( 1, $settings['lazy_load_post_thumbnail'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_post_thumbnail_label"><?php esc_html_e( 'Enable for post thumbnails.', 'powered-cache' ); ?></span>
											</label>
										</div>
										<div class="sui-row">
											<label for="lazy_load_avatars" class="sui-checkbox">

												<input
														type="checkbox"
														id="lazy_load_avatars"
														name="lazy_load_avatars"
														value="1"
													<?php checked( 1, $settings['lazy_load_avatars'] ); ?>
												>

												<span aria-hidden="true"></span>
												<span id="lazy_load_avatars_label"><?php esc_html_e( 'Enable for avatars.', 'powered-cache' ); ?></span>
											</label>
										</div>
									</div>

								</div>

								<div class="sui-row">
									<label for="disable_wp_lazy_load" class="sui-toggle">
										<input
												type="checkbox"
												id="disable_wp_lazy_load"
												name="disable_wp_lazy_load"
												aria-labelledby="disable_wp_lazy_load_label"
												value="1"
											<?php checked( 1, $settings['disable_wp_lazy_load'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="disable_wp_lazy_load_label" class="sui-toggle-label"><?php esc_html_e( 'Disable WordPress Native Lazy Load', 'powered-cache' ); ?></span>
									</label>
								</div>
							</div>
						</div>

					</div>

					<div class="sui-box-settings-row">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Embeds', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="disable_wp_embeds" class="sui-toggle">
									<input
											type="checkbox"
											id="disable_wp_embeds"
											name="disable_wp_embeds"
											aria-labelledby="disable_wp_embeds_label"
											aria-describedby="disable_wp_embeds_description"
											value="1"
										<?php checked( 1, $settings['disable_wp_embeds'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="disable_wp_embeds_label" class="sui-toggle-label"><?php esc_html_e( 'Disable WordPress Embeds', 'powered-cache' ); ?></span>
									<span id="disable_wp_embeds_description" class="sui-description"><?php esc_html_e( 'Disables embedding posts from WordPress-based websites (including your own) which converts URLs into heavy iframes.', 'powered-cache' ); ?>
										(<a href="<?php echo esc_url( get_doc_url( '/disable-wordpress-embeds/' ) ); ?>" target="_blank">?</a>)
									</span>
								</label>
							</div>
						</div>
					</div>

					<div class="sui-box-settings-row">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Emoji', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="disable_emoji_scripts" class="sui-toggle">
									<input
											type="checkbox"
											id="disable_emoji_scripts"
											name="disable_emoji_scripts"
											aria-labelledby="disable_emoji_scripts_label"
											aria-describedby="disable_emoji_scripts_description"
											value="1"
										<?php checked( 1, $settings['disable_emoji_scripts'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="disable_emoji_scripts_label" class="sui-toggle-label"><?php esc_html_e( 'Remove Emoji Scripts', 'powered-cache' ); ?></span>
									<span id="disable_emoji_scripts_description" class="sui-description">
										<?php esc_html_e( 'Removes the unnecessary emoji scripts from your website front-end. Doesn\'t remove emojis, don\'t worry.', 'powered-cache' ); ?>
										(<a href="<?php echo esc_url( get_doc_url( '/remove-emoji-scripts/' ) ); ?>" target="_blank">?</a>)
									</span>
								</label>
							</div>
						</div>
					</div>

				</div>
				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- TAB: CDN -->
			<div class="sui-box" id="cdn-integration" data-tab="cdn-integration" style="display: none;">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'CDN Integration', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body">
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'CDN', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="enable_cdn" class="sui-toggle">
									<input
											type="checkbox"
											id="enable_cdn"
											name="enable_cdn"
											aria-labelledby="enable_cdn_label"
											aria-describedby="enable_cdn_description"
											value="1"
										<?php checked( 1, $settings['enable_cdn'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="enable_cdn_label" class="sui-toggle-label"><?php esc_html_e( 'Enable CDN Integration', 'powered-cache' ); ?></span>
									<span id="enable_cdn_description" class="sui-description"><?php esc_html_e( 'Please make sure that your CDN is properly setup before enabling this feature ', 'powered-cache' ); ?>
										<a href="<?php echo esc_url( get_doc_url( '/cdn-integration/' ) ); ?>" target="_blank">(?)</a></span>
								</label>
							</div>
						</div>

					</div>

					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'CDN Hostnames', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'Enter your CNAME(s)', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div id="cdn-zones" class="sui-form-field">
								<?php
								if ( empty( $settings['cdn_hostname'] ) ) {
									$settings['cdn_hostname'] = [ '' ];
								}

								if ( empty( $settings['cdn_zone'] ) ) {
									$settings['cdn_zone'] = [ '' ];
								}
								?>

								<?php foreach ( $settings['cdn_hostname'] as $key => $cdn ) : ?>
									<div id="cdn-zone-<?php echo absint( $key ); ?>" class="cdn-zone sui-form-field">
										<input id="cdn_hostname" value="<?php echo esc_attr( $cdn ); ?>" name="cdn_hostname[]" style="width: 300px" placeholder="cdn.example.org" class="cdn_hostname sui-form-control sui-input-md sui-field-has-suffix" aria-labelledby="label-unique-id">
										<span><?php esc_html_e( 'for', 'powered-cache' ); ?></span>
										<span class="sui-field-suffix" style="width: 120px">
											<div class="sui-form-field sui-input-md">
												<select id="cdn_zone" name="cdn_zone[]" class="sui-form-control cdn_zone">
													<?php foreach ( cdn_zones() as $zone => $zone_name ) : ?>
														<option <?php selected( $settings['cdn_zone'][ $key ], $zone ); ?> value="<?php echo esc_attr( $zone ); ?>"><?php echo esc_attr( $zone_name ); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</span>
										<button role="button" type="button" class="remove_cdn_hostname sui-button-icon <?php echo( 0 === $key ? 'sui-hidden-important' : '' ); ?>">
											<span class="sui-icon-close" aria-hidden="true"></span>
										</button>
									</div>
								<?php endforeach; ?>
							</div>

							<button role="button" type="button" class="add_cdn_hostname sui-button sui-button-blue sui-button-icon-right">
								<?php esc_html_e( 'Add Hostname', 'powered-cache' ); ?><i class="sui-icon-plus" aria-hidden="true"></i>
							</button>

						</div>
					</div>

					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span id="cdn_rejected_files_label" class="sui-settings-label"><?php esc_html_e( 'Rejected Files', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">

								<div class="sui-col-md-8">

									<div class="sui-form-field">
											<textarea
													placeholder="e.g /wp-content/themes/example/js/custom.js"
													id="cdn_rejected_files"
													name="cdn_rejected_files"
													class="sui-form-control"
													aria-labelledby="cdn_rejected_files_label"
													aria-describedby="cdn_rejected_files_description"
													rows="5"
											><?php echo esc_textarea( $settings['cdn_rejected_files'] ); ?></textarea>
										<span id="cdn_rejected_files_description" class="sui-description">
											<?php esc_html_e( 'One URL per line. It can be full URL or absolute path.', 'powered-cache' ); ?>
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- TAB: Preload -->
			<div class="sui-box" id="preload" data-tab="preload" style="display: none;">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'Preload', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body sui-upsell-items">
					<div id="preload_page_cache_warning_message" class="sui-notice sui-notice-warning" style="<?php echo( $settings['enable_page_cache'] ? 'display:none;' : '' ); ?> padding:10px 20px;margin-bottom:0;">

						<div class="sui-notice-content">
							<div class="sui-notice-message">
								<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
								<p><?php esc_html_e( 'It seems page caching is not activated yet. Page caching needs to be enabled in order to get the advantage of preloading features!', 'powered-cache' ); ?></p>
							</div>
						</div>
					</div>

					<div class="sui-box-settings-row">

						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Cache Preload', 'powered-cache' ); ?></span>
							<span class="sui-description">
								<?php esc_html_e( 'Preloading will visit pages based on the settings and generate cache, just like any other visitor to the site.', 'powered-cache' ); ?>
								(<a href="<?php echo esc_url( get_doc_url( '/enable-preloading/' ) ); ?>" target="_blank">?</a>)
							</span>
						</div>

						<div class="sui-box-settings-col-2">

							<div class="sui-form-field">
								<div class="sui-row">
									<label for="enable_cache_preload" class="sui-toggle">
										<input
												type="checkbox"
												id="enable_cache_preload"
												name="enable_cache_preload"
												aria-labelledby="enable_cache_preload_label"
												aria-describedby="enable_cache_preload_description"
												aria-controls="enable_cache_preload_description_details"
												value="1"
											<?php checked( 1, $settings['enable_cache_preload'] ); ?>
										>

										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="enable_cache_preload_label" class="sui-toggle-label"><?php esc_html_e( 'Enable Preloading', 'powered-cache' ); ?></span>
										<span id="enable_cache_preload_description" class="sui-description"><?php esc_html_e( 'Activate preloading.', 'powered-cache' ); ?></span>
									</label>
								</div>

								<div style=" <?php echo( ! $settings['enable_cache_preload'] ? 'display:none' : '' ); ?>" tabindex="0" id="enable_cache_preload_description_details">

									<div class="sui-form-field">

										<div class="sui-row">
											<label for="preload_homepage" class="sui-toggle">
												<input
														type="checkbox"
														id="preload_homepage"
														name="preload_homepage"
														aria-labelledby="preload_homepage_label"
														aria-describedby="preload_homepage_description"
														value="1"
													<?php checked( 1, $settings['preload_homepage'] ); ?>
												>
												<span class="sui-toggle-slider" aria-hidden="true"></span>
												<span id="preload_homepage_label" class="sui-toggle-label"><?php esc_html_e( 'Enable for homepage', 'powered-cache' ); ?></span>
												<span id="preload_homepage_description" class="sui-description"><?php esc_html_e( 'Preloads homepage.', 'powered-cache' ); ?></span>
											</label>
										</div>

										<div class="sui-row">
											<label for="preload_public_posts" class="sui-toggle">
												<input
														type="checkbox"
														id="preload_public_posts"
														name="preload_public_posts"
														aria-labelledby="preload_public_posts_label"
														aria-describedby="preload_public_posts_description"
														value="1"
													<?php checked( 1, $settings['preload_public_posts'] ); ?>
												>
												<span class="sui-toggle-slider" aria-hidden="true"></span>
												<span id="preload_public_posts_label" class="sui-toggle-label"><?php esc_html_e( 'Enable for posts', 'powered-cache' ); ?></span>
												<span id="preload_public_posts_description" class="sui-description"><?php esc_html_e( 'Individual post pages will be preloaded. The public post types are supported.', 'powered-cache' ); ?></span>
											</label>
										</div>

										<div class="sui-row">
											<label for="preload_public_tax" class="sui-toggle">
												<input
														type="checkbox"
														id="preload_public_tax"
														name="preload_public_tax"
														aria-labelledby="preload_public_tax_label"
														aria-describedby="preload_public_tax_description"
														value="1"
													<?php checked( 1, $settings['preload_public_tax'] ); ?>
												>
												<span class="sui-toggle-slider" aria-hidden="true"></span>
												<span id="preload_public_tax_label" class="sui-toggle-label"><?php esc_html_e( 'Enable for public taxonomies', 'powered-cache' ); ?></span>
												<span id="preload_public_tax_description" class="sui-description"><?php esc_html_e( 'Preload archive pages of taxonomies. (tags, category etc..)', 'powered-cache' ); ?></span>
											</label>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>

					<!-- Sitemap preloading settings sui-disabled add to box -->
					<div id="enable_sitemap_preload_wrapper" style=" <?php echo( ! $settings['enable_cache_preload'] ? 'display:none' : '' ); ?>" tabindex="0">
						<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Sitemap Preloading', 'powered-cache' ); ?>
									<?php if ( ! is_premium() ) : ?>
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="sui-description"><?php esc_html_e( 'Preloads sitemaps and the URLs placed in sitemaps.', 'powered-cache' ); ?></span>

							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-row">
									<label for="enable_sitemap_preload" class="sui-toggle">
										<input
												type="checkbox"
												id="enable_sitemap_preload"
												name="enable_sitemap_preload"
												aria-labelledby="enable_sitemap_preload_label"
												aria-describedby="enable_sitemap_preload_description"
												aria-controls="sitemap-preload-details"
												value="1"
											<?php checked( 1, $settings['enable_sitemap_preload'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="enable_sitemap_preload_label" class="sui-toggle-label"><?php esc_html_e( 'Enable Sitemap Preloading', 'powered-cache' ); ?></span>
										<span id="enable_sitemap_preload_description" class="sui-description"><?php esc_html_e( 'We automatically detect sitemaps generated by Yoast SEO, All-in-one-SEO, Rank Math SEO, SEOPress.', 'powered-cache' ); ?></span>
									</label>
								</div>

								<div class="sui-row">
									<div class="sui-col-md-8">
										<div class="sui-form-field">
											<label for="preload_sitemap" class="sui-label"><i><?php esc_html_e( 'Enter sitemap URLs (one per line)', 'powered-cache' ); ?></i></label>
											<textarea
													placeholder="http://example.com/sitemap.xml"
													id="preload_sitemap"
													name="preload_sitemap"
													class="sui-form-control"
													aria-describedby="preload_sitemap_description"
													rows="7"
											><?php echo esc_textarea( $settings['preload_sitemap'] ); ?></textarea>
											<span id="preload_sitemap_description" class="sui-description">
												<?php esc_html_e( 'Preload the urls in listed sitemaps.', 'powered-cache' ); ?>
												(<a href="<?php echo esc_url( get_doc_url( '/sitemap-preloading/' ) ); ?>" target="_blank">?</a>)
											</span>
										</div>

									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- DNS Prefetch settings -->
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Prefetch DNS', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'DNS-prefetch is an attempt to resolve domain names before resources get requested.', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="prefetch_dns" class="sui-label"><i><?php esc_html_e( 'Enter external hosts to be prefetched (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="//fonts.googleapis.com"
												id="prefetch_dns"
												name="prefetch_dns"
												class="sui-form-control"
												aria-describedby="prefetch_dns_description"
												rows="7"
										><?php echo esc_textarea( $settings['prefetch_dns'] ); ?></textarea>
										<span id="prefetch_dns_description" class="sui-description">
											<?php esc_html_e( 'DNS-prefetch would reduce DNS lookup time.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/prefetch-dns/' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>

					</div>
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Preconnect', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'Preconnect is used to indicate an origin that will be used to fetch required resources. It initializes an early connection, which includes the DNS lookup, TCP handshake, and optional TLS negotiation.', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-row">
								<div class="sui-col-md-8">
									<div class="sui-form-field">
										<label for="preconnect_resource" class="sui-label"><i><?php esc_html_e( 'Enter external hosts to be preconnected (one per line)', 'powered-cache' ); ?></i></label>
										<textarea
												placeholder="https://fonts.googleapis.com"
												id="preconnect_resource"
												name="preconnect_resource"
												class="sui-form-control"
												aria-describedby="preconnect_resource_description"
												rows="7"
										><?php echo esc_textarea( $settings['preconnect_resource'] ); ?></textarea>
										<span id="preconnect_resource_description" class="sui-description">
											<?php esc_html_e( 'The preconnect hint is best used for only the most critical connections.', 'powered-cache' ); ?>
											(<a href="<?php echo esc_url( get_doc_url( '/preconnect-resources/' ) ); ?>" target="_blank">?</a>)
										</span>
									</div>
								</div>
							</div>
						</div>


					</div>




				</div>
				<div class="sui-box-footer">
					<div class="sui-actions-left">
						<button type="submit" name="powered_cache_form_action" value="save_settings" class="sui-button sui-button-blue">
							<i class="sui-icon-save" aria-hidden="true"></i>
							<?php esc_html_e( 'Update settings', 'powered-cache' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- TAB: Dashed -->
			<div id="db-optimization" data-tab="db-optimization" style="display: none;">
				<div class="box-advanced-db sui-box">
					<?php $db_info = \PoweredCache\Async\DatabaseOptimizer::get_db_cleanup_counts(); ?>
					<div class="sui-box-header">
						<h2 class="sui-box-title"><?php esc_html_e( 'Database Optimization', 'powered-cache' ); ?></h2>
					</div>

					<div class="sui-box-body">

						<?php if ( POWERED_CACHE_IS_NETWORK && wp_is_large_network() ) : ?>
							<div class="sui-notice sui-notice-red">
								<div class="sui-notice-content">
									<div class="sui-notice-message">
										<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
										<p><?php esc_html_e( 'It seems Powered Cache has been enabled on a large multisite network. Cleanup counts might be slightly different from than actual value due to the volume of the sites in the network.', 'powered-cache' ); ?></p>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Post Cleanup', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_post_revisions" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_post_revisions"
													name="db_cleanup_post_revisions"
													aria-labelledby="db_cleanup_post_revisions_label"
													aria-describedby="db_cleanup_post_revisions_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_post_revisions'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_post_revisions_label" class="sui-toggle-label"><?php esc_html_e( 'Post Revisions', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Delete post revisions.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_post_revisions_description" class="sui-description"><?php printf( esc_html__( '%s revisions in database', 'powered-cache' ), absint( $db_info['db_cleanup_post_revisions'] ) ); ?></span>
										</label>
									</div>
								</div>

								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_auto_drafts" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_auto_drafts"
													name="db_cleanup_auto_drafts"
													aria-labelledby="db_cleanup_auto_drafts_label"
													aria-describedby="db_cleanup_auto_drafts_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_auto_drafts'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_auto_drafts_label" class="sui-toggle-label"><?php esc_html_e( 'Auto Drafts', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Delete auto-draft posts.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_auto_drafts_description" class="sui-description"><?php printf( esc_html__( '%s auto-draft in database', 'powered-cache' ), absint( $db_info['db_cleanup_auto_drafts'] ) ); ?></span>
										</label>
									</div>
								</div>

								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_trashed_posts" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_trashed_posts"
													name="db_cleanup_trashed_posts"
													aria-labelledby="db_cleanup_trashed_posts_label"
													aria-describedby="db_cleanup_trashed_posts_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_trashed_posts'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_trashed_posts_label" class="sui-toggle-label"><?php esc_html_e( 'Trashed Posts', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Permanently delete trashed posts.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_trashed_posts_description" class="sui-description"><?php printf( esc_html__( '%s trashed post in database', 'powered-cache' ), absint( $db_info['db_cleanup_trashed_posts'] ) ); ?></span>
										</label>
									</div>
								</div>

							</div>
						</div>
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Comments Cleanup', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_spam_comments" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_spam_comments"
													name="db_cleanup_spam_comments"
													aria-labelledby="db_cleanup_spam_comments_label"
													aria-describedby="db_cleanup_spam_comments_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_spam_comments'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_spam_comments_label" class="sui-toggle-label"><?php esc_html_e( 'Spam Comments', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Comments marked as spam that haven\'t been deleted yet.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_spam_comments_description" class="sui-description"><?php printf( esc_html__( '%s spam comment in database', 'powered-cache' ), absint( $db_info['db_cleanup_spam_comments'] ) ); ?></span>
										</label>
									</div>
								</div>
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_trashed_comments" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_trashed_comments"
													name="db_cleanup_trashed_comments"
													aria-labelledby="db_cleanup_trashed_comments_label"
													aria-describedby="db_cleanup_trashed_comments_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_trashed_comments'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_trashed_comments_label" class="sui-toggle-label"><?php esc_html_e( 'Trashed Comments', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Permanently delete trashed comments.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_trashed_comments_description" class="sui-description"><?php printf( esc_html__( '%s trashed comment in database', 'powered-cache' ), absint( $db_info['db_cleanup_trashed_comments'] ) ); ?></span>
										</label>
									</div>
								</div>
							</div>
						</div>
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Transients Cleanup', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_expired_transients" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_expired_transients"
													name="db_cleanup_expired_transients"
													aria-labelledby="db_cleanup_expired_transients_label"
													aria-describedby="db_cleanup_expired_transients_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_expired_transients'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_expired_transients_label" class="sui-toggle-label"><?php esc_html_e( 'Expired Transients', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Permanently delete expired transients.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_expired_transients_description" class="sui-description"><?php printf( esc_html__( '%s expired transient in database', 'powered-cache' ), absint( $db_info['db_cleanup_expired_transients'] ) ); ?></span>
										</label>
									</div>

								</div>
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_all_transients" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_all_transients"
													name="db_cleanup_all_transients"
													aria-labelledby="db_cleanup_all_transients_label"
													aria-describedby="db_cleanup_all_transients_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_all_transients'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_all_transients_label" class="sui-toggle-label"><?php esc_html_e( 'All Transients', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Permanently delete all transients.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_all_transients_description" class="sui-description"><?php printf( esc_html__( '%s transient in database', 'powered-cache' ), absint( $db_info['db_cleanup_all_transients'] ) ); ?></span>
										</label>
									</div>
								</div>

							</div>
						</div>
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Database Optimize', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-row">
									<div class="sui-form-field">
										<label for="db_cleanup_optimize_tables" class="sui-toggle">
											<input
													type="checkbox"
													id="db_cleanup_optimize_tables"
													name="db_cleanup_optimize_tables"
													aria-labelledby="db_cleanup_optimize_tables_label"
													aria-describedby="db_cleanup_optimize_tables_description"
													value="1"
												<?php checked( 1, $settings['db_cleanup_optimize_tables'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="db_cleanup_optimize_tables_label" class="sui-toggle-label"><?php esc_html_e( 'Optimize Tables', 'powered-cache' ); ?>
												<span class="sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Reduces overhead of database tables.', 'powered-cache' ); ?>">
													<i class="sui-icon-info" aria-hidden="true"></i>
												</span>
											</span>
											<span id="db_cleanup_optimize_tables_description" class="sui-description"><?php printf( esc_html__( '%s tables to optimize', 'powered-cache' ), absint( $db_info['db_cleanup_optimize_tables'] ) ); ?></span>
										</label>
									</div>
								</div>

							</div>
						</div>
						<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Schedule Cleanups', 'powered-cache' ); ?>
									<?php if ( ! is_premium() ) : ?>
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="sui-description">
									<?php esc_html_e( 'Schedule Powered Cache to automatically clean your database daily, weekly or monthly.', 'powered-cache' ); ?>
								</span>
							</div><!-- end col-third -->

							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<label for="enable_scheduled_db_cleanup" class="sui-toggle">
										<input
												type="checkbox"
												id="enable_scheduled_db_cleanup"
												name="enable_scheduled_db_cleanup"
												aria-labelledby="enable_scheduled_db_cleanup_label"
												value="1"
												aria-controls="scheduled_db_cleanup_details"
											<?php checked( 1, $settings['enable_scheduled_db_cleanup'] ); ?>
										>
										<span class="sui-toggle-slider" aria-hidden="true"></span>
										<span id="scheduled_cleanup-label" class="sui-toggle-label"><?php esc_html_e( 'Enabled scheduled cleanups', 'powered-cache' ); ?></span>
									</label>

									<div id="scheduled_db_cleanup_details" class="sui-border-frame with-padding schedule-box" style="<?php echo( ! $settings['enable_scheduled_db_cleanup'] ? 'display:none' : '' ); ?>">
										<div class="sui-form-field">

											<label class="sui-label" for="scheduled_db_cleanup_frequency"><?php esc_html_e( 'Frequency', 'powered-cache' ); ?></label>

											<select id="scheduled_db_cleanup_frequency" name="scheduled_db_cleanup_frequency" class="sui-select">
												<?php foreach ( scheduled_cleanup_frequency_options() as $frequency => $frequency_name ) : ?>
													<option <?php selected( $settings['scheduled_db_cleanup_frequency'], $frequency ); ?> value="<?php echo esc_attr( $frequency ); ?>"><?php echo esc_html( $frequency_name ); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
								</div>
							</div>
						</div>
						<?php if ( ! is_premium() ) : ?>
							<div class="sui-box-settings-row sui-upsell-row">
								<div class="sui-upsell-notice">
									<p>
										<?php esc_html_e( 'Regular cleanups of your database ensures you’re regularly removing extra bloat which can slow down your host server. Upgrade to Premium to unlock this feature today!', 'powered-cache' ); ?>
										<br>
										<a href="https://poweredcache.com/?utm_source=poweredcache&amp;utm_medium=plugin&amp;utm_campaign=dbcleanup_schedule_upgrade_button" target="_blank">
											<?php esc_html_e( 'Learn More', 'powered-cache' ); ?>
										</a>
									</p>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div class="sui-box-footer">

						<div class="sui-actions-left">
							<button type="submit" name="powered_cache_form_action" value="save_settings_and_optimize" class="sui-button sui-button-blue" id="powered-cache-save-settings-db-optimize">
								<i class="sui-icon-save" aria-hidden="true"></i>
								<?php esc_html_e( 'Save settings and Optimize', 'powered-cache' ); ?>
							</button>
							<span class="sui-tag sui-tag-yellow"><?php esc_html_e( 'Tip: Make sure you have a current backup before running a cleanup.', 'powered-cache' ); ?></span>

						</div>

					</div>
				</div>
			</div>

			<!-- TAB: Dashed -->
			<div id="extensions" data-tab="extensions" style="display: none;">
				<div class="sui-row">
					<div class="sui-col-lg-6">
						<div id="extension-cloudflare" class="powered-cache-extension-box sui-box">
							<div class="sui-box-header">
								<h3 class="sui-box-title"><?php esc_html_e( 'Cloudflare', 'powered-cache' ); ?></h3>
								<div class="sui-actions-right">
									<div class="sui-form-field">
										<label for="enable_cloudflare" class="sui-toggle">
											<input
													type="checkbox"
													id="enable_cloudflare"
													name="enable_cloudflare"
													aria-labelledby="enable_cloudflare_label"
													value="1"
													aria-controls="cloudflare-details"
												<?php checked( 1, $settings['enable_cloudflare'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="enable_cloudflare_label" class="sui-toggle-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>
							</div><!-- end sui-box-title -->

							<div class="sui-box-body">
								<p><?php esc_html_e( 'Cloudflare extension for PoweredCache. It allows to purge Cloudflare cache within WordPress.', 'powered-cache' ); ?></p>
								<div id="cloudflare-details" style="<?php echo( ! $settings['enable_cloudflare'] ? 'display:none' : '' ); ?>">
									<div class="sui-form-field">
										<label for="cloudflare-api-token" id="cloudflare-api-token-label" class="sui-label"><?php esc_html_e( 'API Token', 'powered-cache' ); ?></label>
										<input
												name="cloudflare_api_token"
												value="<?php echo esc_attr( $settings['cloudflare_api_token'] ); ?>"
												id="cloudflare-api-token"
												class="sui-form-control"
										/>
										<span id="cloudflare_api_token_description" class="sui-description">
											<?php esc_html_e( 'Recommended authentication method.', 'powered-cache' ); ?>
											<a href="<?php echo esc_url( 'https://dash.cloudflare.com/profile/api-tokens' ); ?>" rel="noopener" target="_blank">
												<?php esc_html_e( 'Create a new token', 'powered-cache' ); ?>
											</a>
											<?php esc_html_e( 'Or you can enter Cloudflare email and API Key.', 'powered-cache' ); ?>
										</span>
									</div>


										<div id="cloudflare-api-details" class="sui-row" style="<?php echo( ! empty( $settings['cloudflare_api_token'] ) ? 'display:none' : '' ); ?>">
											<div class="sui-col">
												<div class="sui-form-field">
													<label for="cloudflare-email" id="cloudflare-email-label" class="sui-label"><?php esc_html_e( 'Cloudflare Email', 'powered-cache' ); ?></label>
													<input
															placeholder="john@example.com"
															name="cloudflare_email"
															value="<?php echo esc_attr( $settings['cloudflare_email'] ); ?>"
															id="cloudflare-email"
															class="sui-form-control"
													/>
												</div>
											</div>

											<div class="sui-col">
												<div class="sui-form-field">
													<label for="cloudflare-api-key" id="cloudflare-api-key-label" class="sui-label"><?php esc_html_e( 'API Key', 'powered-cache' ); ?></label>
													<input
															name="cloudflare_api_key"
															value="<?php echo esc_attr( $settings['cloudflare_api_key'] ); ?>"
															id="cloudflare-api-key"
															class="sui-form-control"
															type="password"
													/>
												</div>
											</div>
										</div>


									<div class="sui-form-field">
										<label for="cloudflare-zone" id="cloudflare-zone-label" class="sui-label"><?php esc_html_e( 'Zone ID', 'powered-cache' ); ?></label>

										<input
												name="cloudflare_zone"
												value="<?php echo esc_attr( $settings['cloudflare_zone'] ); ?>"
												id="cloudflare-zone"
												class="sui-form-control"
										/>
									</div>
								</div>
							</div><!-- end box_content_class -->
						</div>
					</div>
					<div class="sui-col-lg-6">
						<div id="extension-heartbeat" class="powered-cache-extension-box sui-box">
							<div class="sui-box-header">
								<h3 class="sui-box-title"><?php esc_html_e( 'Heartbeat', 'powered-cache' ); ?></h3>
								<div class="sui-actions-right">
									<div class="sui-form-field">
										<label for="enable_heartbeat" class="sui-toggle">
											<input
													type="checkbox"
													id="enable_heartbeat"
													name="enable_heartbeat"
													aria-labelledby="enable_heartbeat_label"
													value="1"
													aria-controls="heartbeat-details"
												<?php checked( 1, $settings['enable_heartbeat'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="enable_heartbeat_label" class="sui-toggle-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>
							</div><!-- end sui-box-title -->

							<div class="sui-box-body">
								<p><?php esc_html_e( 'Heartbeat extension allows you to manage the frequency of the WordPress Heartbeat API.', 'powered-cache' ); ?></p>
								<div id="heartbeat-details" style="<?php echo( ! $settings['enable_heartbeat'] ? 'display:none' : '' ); ?>">
									<h4><?php esc_html_e( 'Dashboard', 'powered-cache' ); ?></h4>

									<!-- start dashboard heartbeat -->
									<div class="sui-form-field" role="radiogroup">
										<label for="heartbeat-dashboard-enable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_dashboard_status"
													id="heartbeat-dashboard-enable"
													value="enable"
													class="heartbeat_radio"
													aria-controls="heartbeat-dashboard-interval-control"
												<?php checked( 'enable', $settings['heartbeat_dashboard_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-dashboard-enable-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-dashboard-disable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_dashboard_status"
													id="heartbeat-dashboard-disable"
													value="disable"
													class="heartbeat_radio"
													aria-controls="heartbeat-dashboard-interval-control"
												<?php checked( 'disable', $settings['heartbeat_dashboard_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-dashboard-disable-label"><?php esc_html_e( 'Disable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-dashboard-modify" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_dashboard_status"
													id="heartbeat-dashboard-modify"
													value="modify"
													aria-controls="heartbeat-dashboard-interval-control"
													class="heartbeat_radio"
												<?php checked( 'modify', $settings['heartbeat_dashboard_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-dashboard-modify-label"><?php esc_html_e( 'Modify', 'powered-cache' ); ?></span>
										</label>
									</div>
									<div id="heartbeat-dashboard-interval-control" style="<?php echo( ! ( 'modify' === $settings['heartbeat_dashboard_status'] ) ? 'display:none' : '' ); ?>">
										<label for="heartbeat-dashboard" id="heartbeat-dashboard-label" class="sui-label"><?php esc_html_e( 'Heartbeat Interval for Dashboard', 'powered-cache' ); ?></label>
										<div class="sui-form-field">
											<input
													name="heartbeat_dashboard_interval"
													value="<?php echo esc_attr( $settings['heartbeat_dashboard_interval'] ); ?>"
													id="heartbeat_dashboard_interval"
													class="sui-form-control"
													type="number"
													min="15"
													max="120"
											/>
										</div>
									</div>
									<!-- end dashboard heartbeat -->

									<h4><?php esc_html_e( 'Post Editor', 'powered-cache' ); ?></h4>

									<!-- start post editor heartbeat -->
									<div class="sui-form-field" role="radiogroup">
										<label for="heartbeat-editor-enable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_editor_status"
													id="heartbeat-editor-enable"
													value="enable"
													class="heartbeat_radio"
													aria-controls="heartbeat-editor-interval-control"
												<?php checked( 'enable', $settings['heartbeat_editor_status'] ); ?>

											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-editor-enable-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-editor-disable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_editor_status"
													id="heartbeat-editor-disable"
													value="disable"
													class="heartbeat_radio"
													aria-controls="heartbeat-editor-interval-control"
												<?php checked( 'disable', $settings['heartbeat_editor_status'] ); ?>

											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-editor-disable-label"><?php esc_html_e( 'Disable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-editor-modify" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_editor_status"
													id="heartbeat-editor-modify"
													value="modify"
													aria-controls="heartbeat-editor-interval-control"
													class="heartbeat_radio"
												<?php checked( 'modify', $settings['heartbeat_editor_status'] ); ?>

											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-editor-modify-label"><?php esc_html_e( 'Modify', 'powered-cache' ); ?></span>
										</label>
									</div>
									<div id="heartbeat-editor-interval-control" style="<?php echo( ! ( 'modify' === $settings['heartbeat_editor_status'] ) ? 'display:none' : '' ); ?>">
										<label for="heartbeat-editor" id="heartbeat-editor-label" class="sui-label"><?php esc_html_e( 'Heartbeat Interval for Post Editor', 'powered-cache' ); ?></label>
										<div class="sui-form-field">
											<input
													name="heartbeat_editor_interval"
													value="<?php echo esc_attr( $settings['heartbeat_editor_interval'] ); ?>"
													id="heartbeat_editor_interval"
													class="sui-form-control"
													type="number"
													min="15"
													max="120"
											/>
										</div>
									</div>
									<!-- end post editor heartbeat -->

									<h4><?php esc_html_e( 'Frontend', 'powered-cache' ); ?></h4>

									<!-- start frontend heartbeat -->
									<div class="sui-form-field" role="radiogroup">
										<label for="heartbeat-frontend-enable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_frontend_status"
													id="heartbeat-frontend-enable"
													value="enable"
													class="heartbeat_radio"
													aria-controls="heartbeat-frontend-interval-control"
												<?php checked( 'enable', $settings['heartbeat_frontend_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-frontend-enable-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-frontend-disable" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_frontend_status"
													id="heartbeat-frontend-disable"
													value="disable"
													class="heartbeat_radio"
													aria-controls="heartbeat-frontend-interval-control"
												<?php checked( 'disable', $settings['heartbeat_frontend_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-frontend-disable-label"><?php esc_html_e( 'Disable', 'powered-cache' ); ?></span>
										</label>

										<label for="heartbeat-frontend-modify" class="sui-radio">
											<input
													type="radio"
													name="heartbeat_frontend_status"
													id="heartbeat-frontend-modify"
													value="modify"
													aria-controls="heartbeat-frontend-interval-control"
													class="heartbeat_radio"
												<?php checked( 'modify', $settings['heartbeat_frontend_status'] ); ?>
											/>
											<span aria-hidden="true"></span>
											<span id="heartbeat-frontend-modify-label"><?php esc_html_e( 'Modify', 'powered-cache' ); ?></span>
										</label>
									</div>

									<div id="heartbeat-frontend-interval-control" style="<?php echo( ! ( 'modify' === $settings['heartbeat_frontend_status'] ) ? 'display:none' : '' ); ?>">
										<label for="heartbeat-frontend" id="heartbeat-frontend-label" class="sui-label"><?php esc_html_e( 'Heartbeat Interval for Dashboard', 'powered-cache' ); ?></label>
										<div class="sui-form-field">
											<input
													name="heartbeat_frontend_interval"
													value="<?php echo esc_attr( $settings['heartbeat_frontend_interval'] ); ?>"
													id="heartbeat_frontend_interval"
													class="sui-form-control"
													type="number"
													min="15"
													max="120"
											/>
										</div>
									</div>
									<!-- end frontend heartbeat -->

								</div>
							</div><!-- end box_content_class -->
						</div>
					</div>
				</div>
				<div class="sui-row">
					<div class="sui-col-lg-6">
						<div id="extension-varnish" class="powered-cache-extension-box sui-box">

							<div class="sui-box-header">
								<h3 class="sui-box-title"><?php esc_html_e( 'Varnish', 'powered-cache' ); ?></h3>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-actions-left">
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									</div>
								<?php endif; ?>
								<div class="sui-actions-right">
									<div class="sui-form-field">
										<label for="enable_varnish" class="sui-toggle">
											<input
												<?php echo( ! is_premium() ? 'disabled="disabled"' : '' ); ?>
													type="checkbox"
													id="enable_varnish"
													name="enable_varnish"
													aria-labelledby="enable_varnish_label"
													value="1"
													aria-controls="varnish_details"
												<?php checked( 1, $settings['enable_varnish'] ); ?>
											>

											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="enable_varnish_label" class="sui-toggle-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>
							</div><!-- end sui-box-title -->

							<div class="sui-box-body sui-upsell-items">
								<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
									<div class="sui-box-settings-col">
										<p><?php esc_html_e( 'Purge Varnish cache.It\'s recommended when you are using the Varnish server.', 'powered-cache' ); ?></p>

										<div id="varnish_details" style="<?php echo( ! $settings['enable_varnish'] ? 'display:none' : '' ); ?>">
											<div class="sui-form-field">

												<label for="varnish_ip" id="varnish-ip-label" class="sui-label"><?php esc_html_e( 'Varnish IP', 'powered-cache' ); ?></label>

												<input
														placeholder="127.0.0.1"
														id="varnish_ip"
														name="varnish_ip"
														value="<?php echo esc_attr( $settings['varnish_ip'] ); ?>"
														class="sui-form-control"
												/>
											</div>
										</div>
									</div>

								</div>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-box-settings-row sui-upsell-row">
										<div class="sui-upsell-notice" style="padding-left: 0;">
											<p><?php esc_html_e( 'With our premium version of Powered Cache you can use Varnish extension and unlock some other speedbooster features.', 'powered-cache' ); ?><br>
												<a href="https://poweredcache.com/" rel="noopener" target="_blank" class="sui-button sui-button-purple" style="margin-top: 10px;color:#fff;"><?php esc_html_e( 'Try Premium today', 'powered-cache' ); ?></a>
											</p>
										</div>
									</div>
								<?php endif; ?>
							</div><!-- end box_content_class -->

						</div><!-- end box-dashboard-performance-disabled -->
					</div>

					<div class="sui-col-lg-6">
						<div id="extension-ga" class="powered-cache-extension-box sui-box">
							<div class="sui-box-header">
								<h3 class="sui-box-title"><?php esc_html_e( 'Google Tracking', 'powered-cache' ); ?></h3>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-actions-left">
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									</div>
								<?php endif; ?>
								<div class="sui-actions-right">
									<div class="sui-form-field">
										<label for="enable_google_tracking" class="sui-toggle">
											<input
												<?php echo( ! is_premium() ? 'disabled="disabled"' : '' ); ?>
													type="checkbox"
													id="enable_google_tracking"
													name="enable_google_tracking"
													aria-labelledby="enable_google_tracking_label"
													value="1"
												<?php checked( 1, $settings['enable_google_tracking'] ); ?>
											>
											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="enable_cloudflare_label" class="sui-toggle-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>
							</div><!-- end sui-box-title -->

							<div class="sui-box-body sui-upsell-items">
								<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
									<div class="sui-box-settings-col">
										<p><?php esc_html_e( 'Powered Cache will host Google scripts on your server to help satisfy the PageSpeed recommendation for leverage browser caching.', 'powered-cache' ); ?></p>
									</div>

								</div>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-box-settings-row sui-upsell-row">
										<div class="sui-upsell-notice" style="padding-left: 0;">
											<p><?php esc_html_e( 'With our premium version of Powered Cache you can use this extension and unlock some other speedbooster features.', 'powered-cache' ); ?><br>
												<a href="https://poweredcache.com/" rel="noopener" target="_blank" class="sui-button sui-button-purple" style="margin-top: 10px;color:#fff;"><?php esc_html_e( 'Try Premium today', 'powered-cache' ); ?></a>
											</p>
										</div>
									</div>
								<?php endif; ?>
							</div><!-- end box_content_class -->

						</div>
					</div>

				</div>
				<div class="sui-row">
					<div class="sui-col-lg-6">
						<div id="extension-fb-pixel" class="powered-cache-extension-box sui-box">

							<div class="sui-box-header">
								<h3 class="sui-box-title"><?php esc_html_e( 'Facebook Tracking', 'powered-cache' ); ?></h3>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-actions-left">
										<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Premium', 'powered-cache' ); ?></span>
									</div>
								<?php endif; ?>
								<div class="sui-actions-right">
									<div class="sui-form-field">
										<label for="enable_fb_tracking" class="sui-toggle">
											<input
												<?php echo( ! is_premium() ? 'disabled="disabled"' : '' ); ?>
													type="checkbox"
													id="enable_fb_tracking"
													name="enable_fb_tracking"
													aria-labelledby="enable_fb_tracking_label"
													value="1"
												<?php checked( 1, $settings['enable_fb_tracking'] ); ?>
											>

											<span class="sui-toggle-slider" aria-hidden="true"></span>
											<span id="enable_fb_tracking_label" class="sui-toggle-label"><?php esc_html_e( 'Enable', 'powered-cache' ); ?></span>
										</label>
									</div>
								</div>
							</div><!-- end sui-box-title -->

							<div class="sui-box-body sui-upsell-items">
								<div class="sui-box-settings-row <?php echo( ! is_premium() ? 'sui-disabled' : '' ); ?>">
									<div class="sui-box-settings-col">
										<p><?php esc_html_e( 'Powered Cache will host FB js on your server to help satisfy the PageSpeed recommendation for leverage browser caching.', 'powered-cache' ); ?></p>
									</div>

								</div>
								<?php if ( ! is_premium() ) : ?>
									<div class="sui-box-settings-row sui-upsell-row">
										<div class="sui-upsell-notice" style="padding-left: 0;">
											<p><?php esc_html_e( 'With our premium version of Powered Cache you can use this extension and unlock some other speedbooster features.', 'powered-cache' ); ?><br>
												<a href="https://poweredcache.com/" rel="noopener" target="_blank"  class="sui-button sui-button-purple" style="margin-top: 10px;color:#fff;"><?php esc_html_e( 'Try Premium today', 'powered-cache' ); ?></a>
											</p>
										</div>
									</div>
								<?php endif; ?>
							</div><!-- end box_content_class -->

						</div><!-- end box-dashboard-performance-disabled -->
					</div>
				</div>

			</div>

			<!-- TAB: Dashed -->
			<div class="sui-box" id="misc-settings" data-tab="misc-settings" style="display: none;">

				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php esc_html_e( 'Misc Settings', 'powered-cache' ); ?></h2>
				</div>

				<div class="sui-box-body">
					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Cache Footprint', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="cache_footprint" class="sui-toggle">
									<input
											type="checkbox"
											id="cache_footprint"
											name="cache_footprint"
											aria-labelledby="cache_footprint_label"
											aria-describedby="enable_cache_footprint_description"
											value="1"
										<?php checked( 1, $settings['cache_footprint'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="enable_cache_footprint_label" class="sui-toggle-label"><?php esc_html_e( 'Show caching footprints in the HTML output.', 'powered-cache' ); ?></span>
									<span id="enable_cache_footprint_description" class="sui-description"><?php esc_html_e( 'Adds helpful informations to cached output.', 'powered-cache' ); ?></span>
								</label>
							</div>
						</div>
					</div>

					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Async Cache Cleaning', 'powered-cache' ); ?>
								<span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Experimental', 'powered-cache' ); ?></span>
							</span>

							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<label for="async_cache_cleaning" class="sui-toggle">
									<input
											type="checkbox"
											id="async_cache_cleaning"
											name="async_cache_cleaning"
											aria-labelledby="async_cache_cleaning_label"
											aria-describedby="enable_async_cache_cleaning_description"
											value="1"
										<?php checked( 1, $settings['async_cache_cleaning'] ); ?>
									>
									<span class="sui-toggle-slider" aria-hidden="true"></span>
									<span id="enable_async_cache_cleaning_label" class="sui-toggle-label"><?php esc_html_e( 'Enable async cache clean-up.', 'powered-cache' ); ?></span>
									<span id="enable_async_cache_cleaning_description" class="sui-description"><?php esc_html_e( 'On large sites, it might take a longer time to perform cache purging actions. This option allows performing clean-up tasks in async background processes.', 'powered-cache' ); ?>
										<i>(<?php esc_html_e( 'This is an experimental feature, use it wisely. It might conflict with preloading functionality since both features work in the background.', 'powered-cache' ); ?>)</i>
									</span>
								</label>
							</div>
						</div>
					</div>


					<?php if ( can_control_all_settings() ) : ?>
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Download Configuration', 'powered-cache' ); ?></span>
								<span class="sui-description"></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<a href="<?php echo esc_url_raw( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_download_rewrite_settings&server=apache' ), 'powered_cache_download_rewrite' ) ); ?>" value="download_htaccess_configuration" class="sui-button sui-button-ghost sui-button-blue"><?php esc_html_e( '.htaccess configuration', 'powered-cache' ); ?></a>
									<a href="<?php echo esc_url_raw( wp_nonce_url( admin_url( 'admin-post.php?action=powered_cache_download_rewrite_settings&server=nginx' ), 'powered_cache_download_rewrite' ) ); ?>" value="download_nginx_configuration" class="sui-button sui-button-ghost sui-button-blue"><?php esc_html_e( 'nginx configuration', 'powered-cache' ); ?></a>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Reset All Settings', 'powered-cache' ); ?></span>
							<span class="sui-description"><?php esc_html_e( 'Fabric reset to plugin configuration', 'powered-cache' ); ?></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<button role="submit" name="powered_cache_form_action" value="reset_settings" class="sui-button sui-button-ghost sui-button-blue">
									<?php esc_html_e( 'Reset Settings', 'powered-cache' ); ?>
								</button>

							</div>
						</div>
					</div>
					<?php if ( can_control_all_settings() ) : ?>
						<div class="sui-box-settings-row">
							<div class="sui-box-settings-col-1">
								<span class="sui-settings-label"><?php esc_html_e( 'Diagnostic', 'powered-cache' ); ?></span>
								<span class="sui-description"><?php esc_html_e( 'Configuration checker for caching', 'powered-cache' ); ?></span>
							</div>

							<div class="sui-box-settings-col-2">
								<div class="sui-form-field">
									<button role="button" value="run_diognastic" data-modal-open="pcmodal--powered-cache-diagnostic" class="sui-button sui-button-ghost sui-button-blue" data-esc-close="true" data-modal-mask="true"><?php esc_html_e( 'Run Diagnostic', 'powered-cache' ); ?></button>
								</div>
							</div>
						</div>
					<?php endif; ?>


					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Export', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<button type="submit" role="button" name="powered_cache_form_action" value="export_settings" class="sui-button sui-button-ghost sui-button-blue"><?php esc_html_e( 'Download Settings', 'powered-cache' ); ?></button>
							</div>
						</div>
					</div>

					<div class="sui-box-settings-row">
						<div class="sui-box-settings-col-1">
							<span class="sui-settings-label"><?php esc_html_e( 'Import', 'powered-cache' ); ?></span>
							<span class="sui-description"></span>
						</div>

						<div class="sui-box-settings-col-2">
							<div class="sui-form-field">
								<div class="sui-upload" id="powered-cache-import-upload-wrap">
									<input id="powered-cache-import-file-input" class="powered-cache-file-input" name="import_file" type="file" value="" readonly="readonly" accept=".json">
									<label class="sui-upload-button" type="button" for="powered-cache-import-file-input">
										<span class="sui-icon-upload-cloud" aria-hidden="true"></span>
										<?php esc_html_e( 'Upload file', 'powered-cache' ); ?>
									</label>
									<div class="sui-upload-file">
										<span id="powered-cache-import-file-name"></span>
										<button type="button" id="powered-cache-import-remove-file" aria-label="Remove file">
											<span class="sui-icon-close" aria-hidden="true"></span>
										</button>
									</div>

									<button role="button" id="powered-cache-import-btn" name="powered_cache_form_action" value="import_settings" style="margin-left: 10px;" class="sui-button sui-button-ghost sui-button-blue" disabled>
										<?php esc_html_e( 'Upload and Import', 'powered-cache' ); ?>
									</button>

								</div>
								<span class="sui-description" style="margin-top: 10px;"><?php esc_html_e( 'Choose a JSON(.json) file to import the configuration.', 'powered-cache' ); ?></span>
							</div>

						</div>

					</div>
				</div>

			</div>

			<?php do_action( 'powered_cache_admin_page_after_settings_section' ); ?>

		</section>
	</form>

	<!-- ELEMENT: The Brand -->
	<div class="sui-footer">
		<?php
		echo wp_kses_post(
			sprintf(
				__( 'Made with <i class="sui-icon-heart"></i> by <a href="%s" rel="noopener" target="_blank">PoweredCache</a>', 'powered-cache' ),
				'https://poweredcache.com/'
			)
		);
		?>
	</div>

	<footer>
		<!-- ELEMENT: Navigation -->
		<ul class="sui-footer-nav">
			<li><a href="https://poweredcache.com/faq/" target="_blank"><?php esc_html_e( 'FAQ', 'powered-cache' ); ?></a></li>
			<li><a href="https://poweredcache.com/blog/" target="_blank"><?php esc_html_e( 'Blog', 'powered-cache' ); ?></a></li>
			<li><a href="https://poweredcache.com/changelog/" target="_blank"><?php esc_html_e( 'Changelog', 'powered-cache' ); ?></a></li>
			<li><a href="https://poweredcache.com/support/" target="_blank"><?php esc_html_e( 'Support', 'powered-cache' ); ?></a></li>
		</ul>

		<!-- ELEMENT: Social Media -->
		<ul class="sui-footer-social">
			<li><a href="https://www.facebook.com/poweredcache" target="_blank">
					<i class="sui-icon-social-facebook" aria-hidden="true"></i>
					<span class="sui-screen-reader-text"><?php esc_html_e( 'Facebook', 'powered-cache' ); ?></span>
				</a></li>
			<li><a href="https://twitter.com/poweredcache" target="_blank">
					<i class="sui-icon-social-twitter" aria-hidden="true"></i></a>
				<span class="sui-screen-reader-text"><?php esc_html_e( 'Twitter', 'powered-cache' ); ?></span>
			</li>
		</ul>
	</footer>

	<?php require 'modals.php'; ?>
</main>
