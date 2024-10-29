=== Powered Cache – Caching and Optimization for WordPress – Easily Improve PageSpeed & Web Vitals Score ===
Contributors:  poweredcache, wphandle, skopco, m_uysl
Tags: cache, web vitals, performance, page speed, optimize
Requires at least:  5.7
Tested up to:  6.7
Stable tag:  3.5.3
License: GPLv2 (or later)
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://poweredcache.com/donate/
Requires PHP: 7.2.5

Powered Cache is the most powerful caching and performance suite for WordPress. Easily Improve PageSpeed & Web Vitals Score.

== Description ==

Powered Cache is your comprehensive solution for enhancing WordPress site performance, ensuring a swift and seamless user experience.

The Free version provides essential tools to boost your site's speed while the [Premium version](https://poweredcache.com) unlocks a suite of advanced optimization features.

= Features ⚡️ =

__Simple and easily configurable__: Import and export settings effortlessly.

__Page Caching__: Accelerate page loads, trusted by top-tier websites.

__Object Caching__: Speed up dynamic pageviews. It supports Redis, Memcached, Memcache, and APCu.

__Page Cache Rule Management__: Need advanced caching configurations? Got it covered under advanced options. [Details](https://docs.poweredcache.com/advanced-options/)

__File Optimization__: Easily minify and combine CSS, JS files. Eliminate render-blocking resource problems easily. [And more](https://docs.poweredcache.com/category/file-optimization/)

__Database Optimization__: Keep redundant data away from your database.

__Media Optimization__: Enable Lazy Load, replace YouTube videos with thumbnails, control WordPress embeds, remove emoji scripts.

__Combine Google Fonts__: Combine Google Fonts URLs into a single URL and optimize the font loading.

__Rewrite Support__: Automatic .htaccess configuration for the ideal setup. The cached file can be served without executing PHP at all.

__Mobile Support__: Separate cache file for mobile, in case want to use the separate theme for the mobile.

__Logged-in User Cache__: If you have user-specific content or running a membership site, it creates cached results for the logged-in users.

__CDN Integration__: Integrate your website with CDN; you just need to enter CNAME(s) and corresponding zones.

__Cache Preloading__: Creating cached pages in advance. This feature will keep caching layer warm.

__Prefetched DNS__: Resolve domain names before resources get requested. Reduce DNS lookup time.

__Gzip Support__: Compress caching files with gzip.

__Built-in Extensions__: Cloudflare, Heartbeat

__Multisite Support__: You can activate network-wide or site basis. It's compatible with domain mapping.

__Smart Cache Purging__: Only purge the cache that is affected by the content changes.

__Compatible__: Tested and compatible with popular plugins.

__Battle Tested__: Trusted by enterprise-grade websites.


= Built-in Extensions =
Built-in extensions (aka add-ons) shipped with Powered Cache to provide more functionality.

__Cloudflare__: Cloudflare compatibility and functionalities ***Free***
__Heartbeat__: Manage the frequency of the WordPress Heartbeat API. ***Free***
__Varnish__: Varnish cache purging ***Premium only***
__Google Tracking__: Powered Cache will host Google scripts on your server to help satisfy the PageSpeed recommendation. ***Premium only***
__Facebook Tracking__: Powered Cache will host Facebook scripts on your server to help satisfy the PageSpeed recommendation. ***Premium only***

= Premium Features =
Explore the powerful capabilities offered by Powered Cache Premium:

- __Critical CSS & Load CSS Asynchronously:__ Accelerate your page load times by prioritizing essential styles.
- __Remove Unused CSS:__ Smartly scans your website to identify and eliminate unused CSS rules, optimizing performance.
- __Automatic Image Dimension Assignment:__ Adds missing dimensions to images to improve layout stability and speed.
- __Link Prefetching:__ Pre-fetches links to provide a smoother user experience.
- __Image Optimizer:__ On the fly image compression, including AVIF/WebP format conversion, to enhance load speed.
- __Sitemap Preloading:__ Automatically visits URLs listed in your sitemap to pre-generate cache, ensuring faster load times for your visitors.
- __Scheduled Database Cleanups:__ Automates database optimization tasks to maintain peak performance.
- __Varnish Extension:__ Enhance your site's caching capabilities with Varnish support.
- __Google & Facebook Tracking Extensions:__ Host external tracking scripts locally, with automatic updates when the external resource changes.
- __WP-CLI Support:__ Command-line options available to streamline your workflow.
- __Top-Notch Premium Support:__ Get high-quality support for any issues you might encounter.
- __Ad-Free Plugin Interface:__ Enjoy an uncluttered, ad-free plugin admin page.

By upgrading to Powered Cache Premium you also get access to one-on-one help from our knowledgeable support team and our extensive documentation site.

**[Learn more about Powered Cache Premium](https://poweredcache.com/)**

= Contributing & Bug Report =
Bug reports and pull requests are welcome on [Github](https://github.com/poweredcache/powered-cache). Some of our features are premium only, please consider before sending PR.

= Documentation =

__Documentation site__: [https://docs.poweredcache.com/](https://docs.poweredcache.com/)

__Developer Docs__: [https://poweredcache.github.io/docs/](https://poweredcache.github.io/docs/)  (***Hook reference***)


== Installation ==

= From within WordPress =
1. Visit 'Plugins > Add New'
2. Search for 'Powered Cache'
3. Activate Powered Cache from your Plugins page.
4. That's all.

= Manually =
1. Upload the `powered-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Powered Cache plugin through the 'Plugins' menu in WordPress
3. That's all.

== Frequently Asked Questions ==

= How do I know my site is being cached? =
The easiest way is to make sure your site is being cached. Open your site in incognito mode of your browser and right-click on the page. If you see `<!-- Cache served by PoweredCache -->` information that means you are getting a cached result.

= Is it compatible with multisite? =
Yes, it's 100% compatible with multisite. You can activate it on the network-wide or per-site basis.

= Is it compatible with domain mapping? =
Yes, it's compatible with Mercator and native domain mapping feature with WordPress core.

= What is the built-in extension? =
We designed Powered Cache is a complete optimization solution for WordPress. However, we believe that your system should be tailored to your needs without the added weight of unwanted functionality. We strive to perfect this balance with our built-in extensions.

= What about mobile caching? =
We support mobile devices and user agents, if your template is not responsive you can use mobile caching with a separate file. It all works.

= How to get premium version of plugin? =
You can buy from [poweredcache.com](https://poweredcache.com/)

= Is it compatible with Cloudflare? =
Yes, you just need to enable Cloudflare extension. [Learn More](https://docs.poweredcache.com/cloudflare/)

= Is it compatible with Jetpack? =
Yes, we don't get any problems with Jetpack.

= Is it compatible with WPML? =
Yes, it's compatible with [WPML](https://wpml.org/).


= Is it compatible with ecommerce plugins? =
Yes, you can use with WooCommerce, Easy Digital Downloads and BigCommerce. If you are using any other eCommerce plugin, just make sure dynamic pages are excluded from the cache.

= How can I disable caching for a particular page? =
You can disable caching from the meta box in the post editing page or enter pages in the "Never cache the following pages" under the "Advanced Options" section.

= Can I export/import settings? =
Yes, you can find export/import options in the "Misc" sections of the settings page.

= Does it cache API requests? =
Yes, it caches GET requests on API. If you have query parameters on the request, you will need to allow query parameter in the"Cache query strings" under the "Advanced Options" section.

= Is it compatible with PHP 8+ =
Yes, it’s compatible with PHP 8+


== Screenshots ==
1. Basic Options
2. Advanced Options
3. File Optimization
4. Media Optimization
5. CDN Integration
6. Preload Options
7. Database Optimization
8. Extensions


== Changelog ==

= 3.5.3 (October 29, 2024) =
- [Added] AMP compatibility for delay js.
- [Added] `powered_cache_cache_dir_htaccess_file_content` filter to modify the cache directory .htaccess content.
- [Updated] Cache key for logged-in user cache. (Advised to purge cache if you are using logged-in user cache).
- [Improved] Cookie control for cache skipping.
- Tested with Upcoming WP 6.7
- Various dependency updates.

= 3.5.2 (October 01, 2024) =
- [Added] Clear Cache for Me compatibility.
- [Updated] Minifier libraries.
- Various dependency updates.

= 3.5.1 (August 14, 2024) =
- [Added] Logging for CF cache purge.
- [Improved] CF cache purge.
- Dependency updates.

= 3.5 (July 16, 2024) =
- [Added] Image Optimizer purge feature.
- Dependency updates.
- Tested with WP 6.6

= 3.4.5 (May 14, 2024) =
- [Improved] HTML minification without dom optimization.
- [Fixed] Resolved PHP warnings caused by accessing superglobals.
- Dependency updates.

= 3.4.4 (April 29, 2024) =
- [Fixed] Resolved an issue with comment cache not purging correctly.
- [Added] Support for purging paginated comments.

= 3.4.3 (April 22, 2024) =
- [Fixed] Cloudflare cache purge issue.
- [Updated] Background job processing.
- Dependency updates.

= 3.4.2 (February 20, 2024) =
- [Added] Term cache clearing upon term changes.
- [Added] Compatibility with ShortPixel Adaptive Images when delay JS is enabled.
- [Added] Date archive cache clearing upon post updates.
- [Improved] Term archive purging upon post updates.

= 3.4.1 (February 13, 2024) =
- [Fix] YouTube video positioning when iframe is replaced with thumbnail.

= 3.4 (February 07, 2024) =
- [Added] Lazyload - Replace YouTube videos with thumbnails.
- [Added] Introduced an option to exclude specific images and iframes from being lazy-loaded.
- [Added] Add warning message for memcached-based object cache backends if alloptions size is too large.
- [Added] Preloader request interval setting to control the preloader request rate.
- [Added] Preloader feedback message to understand the preloader status.
- [Added] Preloader cache footprint to understand a page generated by the preloader.
- [Added] A secondary button for cache clearance upon settings update. Props [@emreerkan](https://github.com/emreerkan)
- [Added] New option to control delay js timeout.
- [Improvement] Performance improvements for Delayed JS execution, introducing a more sophisticated dependency resolution mechanism to prevent possible JS errors.
- [Improvement] Page cache purging strategy.
- [Improvement] Preloader performance improvement and avoid unnecessary requests.
- [Security] Cloudflare extension security improvements. (Encrypted API Key/Token)
- [Security] Added verification of Cloudflare IP to accurately update `REMOTE_ADDR` when behind Cloudflare proxy.
- [Fix] CDN dropdrop width.
- [Fix] Lazyload compatibility fix with core image blocks lightbox feature.
- [Fix] Do not delay interactivity and image block scripts.
- Dependency updates.

= 3.3.3 (January 02, 2024) =
- [Fix] Typos. Props [@szepeviktor](https://github.com/szepeviktor).
- [Added] WebP option as preferred image format.
- [Added] Windows specific warning for File Optimizer rewrite.

= 3.3.2 (December 03, 2023) =
- [Fix] Improved comment cookie handling to prevent serving cached pages to users with a comment cookie. Props [@jumbo](https://profiles.wordpress.org/jumbo/).
- [Fix] Ensured empty lines in wp-config.php are not removed upon WP_CACHE definition. Props [@jumbo](https://profiles.wordpress.org/jumbo/).
- [Fix] CDN integration now skipped for Block Editor requests. Props [@jumbo](https://profiles.wordpress.org/jumbo/).
- [Improvement] Added support for custom wp-content structure in File Optimizer.
- [Improvement] Added File Optimizer rewrite rules for Nginx.
- [Misc] Dependency updates.

= 3.3.1 (November 01, 2023) =
- [Fix] Save file optimizer rewrite setting.

= 3.3 (November 01, 2023) =
- [Fix] Fix partially supported deprecated callable for PHP 8.2
- [Added] Add WPS Hide Login compat.
- [Added] Rewrite support for File Optimizer.
- [Added] DOM Optimization option for HTML Minification.
- Dependency updates.
- Tested with WP 6.4

= 3.2.1 (September 05, 2023) =
- [Fix] Dynamic property deprecations for PHP 8.2
- [Fix] Deprecated variable format for PHP 8.2
- [Added] Pass $css_url with `powered_cache_fo_css_do_concat` filter.

= 3.2 (August 31, 2023) =
- [Refactored] Improved JS optimization and execution options for better performance.
- [Updated] Enhanced Delay JS feature and removed the default timeout.
- [Added] Introduced post-level controls for delay and defer JS options.
- [Added] New feature to automatically add missing image dimensions.
- [Added] Added a link prefetching option to improve user experience.
- [Added] Compatibility with Bricks Builder introduced.
- [Added] New global admin notice to prompt cache purging upon plugin activation or deactivation.
- [Fix] File optimizer no longer runs in Customizer preview; now compatible with Colibri Page Builder.
- [Fix] Ensure conditional tags on the page for page cache drop-in.
- [Fix] Disabled DOM parser optimization for HTML minification.
- [Fix] Resolved a glitch in the Diagnostic modal.
- [Fix] Fixed DOM violations on the Settings page.

= 3.1.1 (August 1, 2023) =
- Added some clarifications about AVIF support

= 3.1 (July 20, 2023) =
- Moved the image optimizer from beta to the stable release. [Learn More] (https://poweredcache.com/features/image-optimizer/)
- New Feature: Introduced the ability to bypass the first nth images with lazy load.
- General enhancements made to the Lazy Load feature.
- Resolved: Deprecated jQuery functions.
- Updated: Minify package.
- Updated: Background processing package.
- Tested with WP 6.3.

= 3.0.5 (May 30, 2023) =
- Fix: PhastPress cache purge callback
- Fix: Memcached drop-in `set_multiple` warning
- ensure html tag exists before running HTML minifier

= 3.0.4 (May 25, 2023) =
- DelayedJS bugfix
- Added new compatibility with PhastPress

= 3.0.3 (May 22, 2023) =
- Fix: Html Minification bugfix

= 3.0.2 (May 21, 2023) =
- Fix: Html Minification error below PHP 8.1
- Added: x-cache-enabled and age headers
- Added: sorting for cache query strings
- nginx configuration tweaking

= 3.0.1 (May 16, 2023) =
- Fix: Malformed robots.txt due to FileOptimizer
- Fix: Don't execute lazyload script as delayed.
- Adding clarity to the JS execution field.

= 3.0 (May 15, 2023) =
- Added: Bunny Fonts as replacement of Google Fonts
- Added: global `nopoweredcache` parameter to skip optimizations
- Added: Cache query strings to generate separate cache based on query and value
- Added: WooCommerce compat for geolocation with page cache support.
- Added: Delayed JS execution.
- Added: Remove Unused CSS feature.
- Added: New HTML minification library.
- Updated: Background processing library.
- Bump required PHP version to 7.2
- Object cache drop-in updates: supporting *multiple and wp_cache_supports
- Refactored: Accepted query strings renamed as ignored query strings.
- Various small improvements and fixes.

Detailed changelog located in [changelog page](https://poweredcache.com/changelog)

== Upgrade notice ==

= 3.3.2 =
 - Contains a bug fix related to CDN integration. If you enabled CDN integration, please [search-replace](https://developer.wordpress.org/cli/commands/search-replace/) command to update your CDN hostname(s) with the original URL in the database.

= 2.0 =
 - Requirements have been updated.
 - Includes some breaking changes. If you have been using the plugin functions or hooks, consider testing it before the upgrade.


= 1.2 =
 - Default `WP_CACHE_KEY_SALT` has been changed, be careful about upgrading

If you get unexpected results after upgrade or migrating to new hosting, please check WordPress drop-in caching files which are located in `wp-content/advanced-cache.php` and `wp-content/object-cache.php`
