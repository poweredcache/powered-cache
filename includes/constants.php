<?php
/**
 * Constants
 *
 * @package PoweredCache
 */

namespace PoweredCache\Constants;

const MENU_SLUG   = 'powered-cache';
const ICON_BASE64 = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iMzJweCIgaWQ9IkxheWVyXzEiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMyIDMyOyIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMzIgMzIiIHdpZHRoPSIzMnB4IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQgMzM2KSI+PHBhdGggZD0iTS0xMTcuMTc2LTMzNC4wNjNoLTkuMzUzTC0xMzguMTExLTMyMGg5LjMwNGwtMTEuNzQ1LDE0LjA2M2wyNS4xMDUtMTguMzE2aC05LjgwOUwtMTE3LjE3Ni0zMzQuMDYzeiIvPjwvZz48L3N2Zz4=';

const SETTING_OPTION                      = 'powered_cache_settings';
const PURGE_CACHE_CRON_NAME               = 'powered_cache_delete_expired_cache';
const PURGE_FO_CRON_NAME                  = 'powered_cache_delete_expired_min_files'; // file optimizer cron
const POST_META_DISABLE_CACHE_KEY         = 'powered_cache_disable_cache';
const POST_META_DISABLE_LAZYLOAD_KEY      = 'powered_cache_disable_lazyload';
const POST_META_DISABLE_CRITICAL_CSS_KEY  = 'powered_cache_disable_critical_css';
const POST_META_SPECIFIC_CRITICAL_CSS_KEY = 'powered_cache_specific_critical_css';
const POST_META_DISABLE_UCSS_KEY          = 'powered_cache_disable_ucss';
const POST_META_SPECIFIC_UCSS_KEY         = 'powered_cache_specific_ucss';
const POST_META_DISABLE_CSS_OPTIMIZATION  = 'powered_cache_disable_css_optimization';
const POST_META_DISABLE_JS_OPTIMIZATION   = 'powered_cache_disable_js_optimization';
const POST_META_DISABLE_JS_DEFER          = 'powered_cache_disable_js_defer';
const POST_META_DISABLE_JS_DELAY          = 'powered_cache_disable_js_delay';
const DB_VERSION_OPTION_NAME              = 'powered_cache_db_version';
const DB_CLEANUP_COUNT_CACHE_KEY          = 'powered_cache_db_cleanup_counts';
const PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT = 'powered_cache_purge_cache_plugin_notice';
