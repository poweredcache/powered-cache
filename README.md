Powered Cache [![Build Status](https://travis-ci.org/skopco/powered-cache.svg?branch=master)](https://travis-ci.org/skopco/powered-cache)
=============

Comprehensive caching and performance plugin for WordPress.

__Plugin Website__: [poweredcache.com](https://poweredcache.com)  

### Features

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
- Query string removal from CSS & JS resources

### Built-in extensions

Built-in extensions (aka add-ons) come with Powered Cache to provide more functionality.

[__Cloudflare__](https://poweredcache.com/extensions/cloudflare) – Cloudflare compatibility and functionalities ***Free***  
[__Lazy Load__](https://poweredcache.com/extensions/lazy-load/) – Loads images and iframes only when visible to the user ***Free***  
[__Preload__](https://poweredcache.com/extensions/preload/) – Preload posts before actual user request page  ***Free***  
[__Varnish__](https://poweredcache.com/extensions/varnish/) – Varnish cache purging ***Premium only***  
[__Minifier__](https://poweredcache.com/extensions/minifier/) – Reduce size of HTML,CSS,JS files by compressing and concatenating them. ***Premium only***  
[__Remote Cron__](https://poweredcache.com/extensions/remote-cron/) – Trigger WordPress cron remotely ***Premium only***  


> <strong>Premium Support</strong><br>
> We don't always provide active support on the WordPress.org forums. Premium (directly) support is available to people who bought the [Powered Cache Premium](https://poweredcache.com/) only.


### Premium Features   
- All current and future premium extensions
- Get benefits from our bots like regular cron checks, preloading, etc...
- WP-CLI commands ready to save your time
- We are providing top-notch premium support to premium users
- No Ads on plugin page


## Contributing & Bug Report  
Bug reports and pull requests are welcome on [Github](https://github.com/skopco/powered-cache). Some of our features are premium only, please consider before sending PR.

## Documentation  
Our documentation can be found on [GitHub](https://github.com/skopco/powered-cache/wiki)


## Setup  
1. Upload the `powered-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Powered Cache plugin through the 'Plugins' menu in WordPress
3. That's all. 

## Credits

We have used code or ideas from the following projects:

* [Simple Cache](https://github.com/tlovett1/simple-cache) for page cache drop-in.
* [WP Super Cache](https://github.com/Automattic/wp-super-cache) for local preloading concept
* [bj-lazy-load](https://github.com/Angrycreative/bj-lazy-load) for lazy load extension
* [minify](https://github.com/mrclay/minify) for concatenation and minification
* [varnish-http-purge](https://github.com/Ipstenu/varnish-http-purge) for varnish extension
* [Memcached Object Cache](https://wordpress.org/plugins/memcached/) for memcache drop-in.
* [Memcached Redux](https://github.com/Ipstenu/memcached-redux/) for memcached drop-in.
* [WP Redis](https://wordpress.org/plugins/wp-redis/) for redis drop-in.
* [APCu](https://wordpress.org/plugins/apcu/) for APCu drop-in.

