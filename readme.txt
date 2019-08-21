=== Powered Cache ===

Contributors:  skopco, m_uysl
Tags: cache, caching, powered cache, cdn, super cache, fastest cache, total cache
Requires at least:  4.5
Tested up to:  5.2
Stable tag:  1.2.6
License: GPLv2 (or later)
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://poweredcache.com/donate/
Requires PHP: 5.2.4

Comprehensive caching and performance plugin for WordPress.

== Description ==

Powered Cache is a comprehensive caching and performance plugin for WordPress. It comes with built-in extensions that improve your website's performance.

__Plugin Website__: [poweredcache.com](https://poweredcache.com)

= Features =

- Simple and easily configurable. You can import and export settings via one click.
- Page Caching
- Object Caching (redis, memcached, memcache, apcu)
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

Built-in extensions (aka add-ons) come with Powered Cache to provide more functionality.

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
- Get benefits from our bots like regular cron checks, preloading, etc...
- WP-CLI commands ready to save your time
- We are providing top-notch premium support to premium users
- No Ads on plugin page

[youtube https://www.youtube.com/watch?v=4tHfHfWNVF0]

= Contributing & Bug Report =
Bug reports and pull requests are welcome on [Github](https://github.com/skopco/powered-cache). Some of our features are premium only, please consider before sending PR.

= Documentation =
Our documentation can be found on [GitHub](https://github.com/skopco/powered-cache/wiki)

== Installation ==

=== From within WordPress ===
1. Visit 'Plugins > Add New'
2. Search for 'Powered Cache'
3. Activate Powered Cache from your Plugins page.
4. That's all.

=== Manually ===
1. Upload the `powered-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Powered Cache plugin through the 'Plugins' menu in WordPress
3. That's all.

== Frequently Asked Questions ==

= Is it compatible with multisite? =
Yes, it works with subdirectory/subdomain setups.

= Is it compatible with PHP 7? =
Yes, it's compatible with PHP7+

= What is the built-in extension? =
We designed Powered Cache is a complete optimization solution for WordPress. However, we believe that your system should be tailored to your needs without the added weight of unwanted functionality. We strive to perfect this balance with our built-in extensions.

= What about mobile caching? =
We support mobile devices and user agents, if your template is not responsive you can use mobile caching with a separate file. It all works.

= How to get premium version of plugin? =
You can buy from [poweredcache.com](https://poweredcache.com/)

= Is it compatible with Cloudflare? =
Yes, definitely!

= Is it compatible with Jetpack? =
Yes, we don't get any problems with Jetpack.

= Is it compatible with ecommerce plugins? =
We didn't test all of them, but principally it must be worked, you consider excluding dynamic pages from the page cache. (like checkout page)


== Screenshots ==
1. Basic Options
2. Advanced Options
3. CDN configuration
4. Extensions page


== Changelog ==

= 1.2.6 =
 - fix: missing curly brackets for Nginx config
 - handle RedisException property to prevent WSOD with wrong credentials. Props [adiloztaser][https://github.com/adiloztaser]
 - CF extension: fetch up to 1000 zones


Detailed changelog located in [changelog page](https://poweredcache.com/changelog)

== Upgrade notice ==

= 1.2 =
 - Default `WP_CACHE_KEY_SALT` has been changed, be careful about upgrading

If you get unexpected results after upgrade or migrating to new hosting, please check WordPress drop-in caching files which are located in `wp-content/advanced-cache.php` and `wp-content/object-cache.php`
