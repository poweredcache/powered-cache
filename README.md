Powered Cache
=============

![Support Level](https://img.shields.io/badge/support-active-green.svg) [![Release Version](https://img.shields.io/wordpress/plugin/v/powered-cache?label=Release%20Version)](https://github.com/poweredcache/powered-cache/releases) ![WordPress tested up to version](https://img.shields.io/wordpress/plugin/tested/powered-cache?label=WordPress) ![Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/powered-cache?label=PHP)

The most powerful caching and performance suite for WordPress. Easily Improve PageSpeed & Web Vitals Score.

__Plugin Website__: [poweredcache.com](https://poweredcache.com)  
__Docs__: [poweredcache.com](https://poweredcache.com)  
__Developer Docs__: [https://poweredcache.github.io/docs/](https://poweredcache.github.io/docs/)

### Features

- __Simple and easily configurable__: You can import and export settings with a single click.

- __Page Caching__: Lightning-fast pages. (Trusted by enterprise grade websites)

- __Object Caching__: Speedup dynamic pageviews. It supports Redis, Memcached, Memcache and APCu.

- __Page Cache Rule Management__: Need advanced caching configurations? Got it covered under advanced options. [Details](https://docs.poweredcache.com/advanced-options/)

- __File Optimization__: Easily minify and combine CSS, JS files.

- __Database Optimization__: Keep redundant data away from your database.

- __Media Optimization__: Enable Lazy Load, control WordPress embeds, remove emoji scripts.

- __Combine Google Fonts__: Combine Google Fonts URLs into a single URL and optimize the font loading.

- __Rewrite Support__: Automatic .htaccess configuration for ideal setup. The cached file can be served without executing PHP at all.

- __Mobile Support__: Separate cache file for mobile, in case want to use the separate theme for the mobile.

- __Logged-in User Cache__: If you have user-specific content or running a membership site, it creates cache for user.

- __CDN Integration__: Integrate your website with CDN, you just need to enter CNAME(s) and corresponding zones.

- __Cache Preloading__: Creating cached pages in advance. This feature will keep caching layer warm.

- __Prefetched DNS__: Resolve domain names before resources get requested. Reduce DNS lookup time.

- __Gzip Support__: Compress caching files with gzip.

- __Built-in Extensions__: Cloudflare, Heartbeat

- __Multisite Support__: You can activate network-wide or site basis. It's compatible with domain mapping.

- __Smart Cache Purging__: Only purge the cache that is affected by the content changes.

- __Compatible__: Tested and compatible for popular plugins.

- __Battle Tested__: Trusted by enterprise-grade websites.

### Built-in extensions

Built-in extensions (aka add-ons) shipped with Powered Cache to provide more functionality.

- __Cloudflare__: Cloudflare compatibility and functionalities ***Free***

- __Heartbeat__: Manage the frequency of the WordPress Heartbeat API. ***Free***

- __Varnish__: Varnish cache purging ***Premium only***

- __Google Tracking__: Powered Cache will host Google scripts on your server to help satisfy the PageSpeed recommendation. ***Premium only***

- __Facebook Tracking__: Powered Cache will host Google scripts on your server to help satisfy the PageSpeed recommendation. ***Premium only***


### Premium Features

Here is a list of the amazing features included in Powered Cache Premium:

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

## Contributing & Bug Report
Bug reports and pull requests are welcome on [Github](https://github.com/poweredcache/powered-cache). Some of our features are premium only, please consider before sending PR.

## Documentation
__Documentation site__: [https://docs.poweredcache.com/](https://docs.poweredcache.com/)
__Developer Docs :__ [https://poweredcache.github.io/docs/](https://poweredcache.github.io/docs/)  (***Hook referance***)


## Setup
1. Upload the `powered-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Powered Cache plugin through the 'Plugins' menu in WordPress
3. That's all.

## Credits

We have used code or ideas from the following projects:

* [Simple Cache](https://github.com/tlovett1/simple-cache) for page cache drop-in.
* [WP Background Processing](https://github.com/deliciousbrains/wp-background-processing) for performing async tasks.
* [bj-lazy-load](https://github.com/Angrycreative/bj-lazy-load) for lazy load feature.
* [minify](https://github.com/matthiasmullie/minify) for concatenation and minification
* [nginx-http-concat](https://github.com/Automattic/nginx-http-concat) for concatenation.
* [varnish-http-purge](https://github.com/Ipstenu/varnish-http-purge) for varnish extension
* [Memcached Object Cache](https://wordpress.org/plugins/memcached/) for memcache drop-in.
* [Memcached Redux](https://github.com/Ipstenu/memcached-redux/) for memcached drop-in.
* [WP Redis](https://wordpress.org/plugins/wp-redis/) for redis drop-in.
* [APCu](https://github.com/l3rady/WordPress-APCu-Object-Cache) for APCu drop-in.
* [10up Toolkit](https://github.com/10up/10up-toolkit) for building tools.
* [Shared UI](https://github.com/wpmudev/shared-ui) for admin UI.
* [Mozart](https://github.com/coenjacobs/mozart) for wrapping dependencies.
* [CDN Enabler](https://github.com/keycdn/cdn-enabler) for some CDN functionalities.
* [HtmlMin](https://github.com/voku/HtmlMin) for HTML minification.

