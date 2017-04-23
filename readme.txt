=== Powered Cache ===

Contributors:  skopco, m_uysl
Tags: cache, caching, powered cache, cdn, performance, optimisation, SEO
Requires at least:  4.1
Tested up to:  4.7
Stable tag:  1.0.1
License: GPLv2 (or later)
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://poweredcache.com/donate/

Comprehensive caching and performance plugin for WordPress.

== Description ==

Powered Cache is a comprehensive caching and performance plugin for WordPress. It comes with build-in extensions that improve your website's performance.

__Plugin Website__: [poweredcache.com](https://poweredcache.com)

= Features =

- Simple and easily configurable. You can import and export settings via one click.
- Page Caching
- Object Caching (redis, memcached, memcache)
- Support mod_rewrite (automatic .htaccess rules)
- Mobile support (separate cache file for mobile)
- Logged-in user cache
- SSL support
- CDN support
- Cache Preloading
- Page cache rule management
- Gzip support
- Built-in extensions: Lazy Load, Preloader, Cloudflare
- Multisite support
- Smart cache purging (automatic cache purging on post update/publish)

= Built-in extensions =

Build-in extensions (aka add-ons) are comes with Powered Cache to provide more functionality.

[__Cloudflare__](https://poweredcache.com/extensions/cloudflare) – Cloudflare compatibility and functionalities ***Free***
[__Lazy Load__](https://poweredcache.com/extensions/lazy-load/) – Loads images and iframes only when visible to the user ***Free***
[__Preload__](https://poweredcache.com/extensions/preload/) – Preload posts before actual user request page  ***Free***
[__Varnish__](https://poweredcache.com/extensions/varnish/) – Varnish cache purging ***Premium only***
[__Minifier__](https://poweredcache.com/extensions/minifier/) – Reduce size of HTML,CSS,JS files by compressing and concatenating them. ***Premium only***
[__Remote Cron__](https://poweredcache.com/extensions/remote-cron/) – Trigger WordPress cron remotely ***Premium only***


> <strong>Premium Support</strong><br>
> We don't always provide active support on the WordPress.org forums. Premium (directly) support is available to people who bought the [Powered Cache Premium](https://poweredcache.com/) only.


= Premium Features =
- All current and future premium extensions
- Get benefits from our bots like reqular cron check, preloading etc...
- WP-CLI commands ready to save your time
- We are providing top-notch premium support to premium users
- No Ads on plugin page

[youtube https://www.youtube.com/watch?v=4tHfHfWNVF0]

= Contributing & Bug Report =
Bug reports and pull requests are welcome on [Github](https://github.com/skopco/powered-cache). Some of our features are premium only, please consider before sending PR.

= Documentation =
Our knowledge base is available on [docs.poweredcache.com](http://docs.poweredcache.com/)

== Installation ==

=== From within WordPress ===
1. Visit 'Plugins > Add New'
2. Search for 'Powered Cache'
3. Activate Powered Cache from your Plugins page.
4. Go to "after activation" below.

=== Manually ===
1. Upload the `powered-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Powered Cache plugin through the 'Plugins' menu in WordPress
3. That's all.

== Frequently Asked Questions ==

= Is it compatible with multisite? =
Yes, it works with subdirectory/subdomain setups.

= What is the built-in extension? =
We designed Powered Cache is a complete optimization solution for WordPress. However, we believe that your system should be tailored to your needs without the added weight of unwanted functionality. We strive to perfect this balance with our built-in extensions.

= What about mobile caching? =
We support mobile devices and user agents, if your template is not responsive you can use mobile caching with separate file. It all works.

= How to get premium version of plugin? =
You can buy from [poweredcache.com](https://poweredcache.com/)

= Is it compatible with Cloudflare? =
Yes, definitely!

= Is it compatible with Jetpack? =
Yes, we don't get any problems with Jetpack.

= Is it compatible with ecommerce plugins? =
We didn't test all of them but principally it must be worked, you consider excluding dynamic pages from page cache. (like checkout page)


== Screenshots ==
1. Basic Options
2. Advanced Options
3. CDN configuration
4. Extensions page


== Changelog ==

= 1.0.1 =
 - gzip encoding buffer fix
 - rejected_uri regex fix
 - notice fix, (due to enabling page cache)

Detailed changelog located in [changelog page](https://poweredcache.com/changelog)

== Upgrade notice ==

If you get unexpected results after upgrade or migrating to new hosting, please check WordPress drop-in caching files which are located in `wp-content/advanced-cache.php` and `wp-content/object-cache.php`
