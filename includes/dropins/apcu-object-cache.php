<?php

/**
 * APCu drop-in, based on https://wordpress.org/plugins/apcu/
 */

if ( ! function_exists( 'apcu_add' ) ) {
	return;
}

if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', DB_NAME );
}


function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->add( $key, $data, $group, (int) $expire );
}

function wp_cache_close() {
	return true;
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->decr( $key, $offset, $group );
}

function wp_cache_delete( $key, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}

function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	global $wp_object_cache;

	return $wp_object_cache->get( $key, $group, $force, $found );
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->incr( $key, $offset, $group );
}

function wp_cache_init() {
	$GLOBALS['wp_object_cache'] = new APCu_Object_Cache();
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, (int) $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->set( $key, $data, $group, (int) $expire );
}

function wp_cache_switch_to_blog( $blog_id ) {
	global $wp_object_cache;

	$wp_object_cache->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->wp_cache_add_non_persistent_groups( $groups );
}

function wp_cache_reset() {
	global $wp_object_cache;

	$wp_object_cache->reset();
}


class APCu_Object_Cache {

	private $prefix = '';
	private $local_cache = array();
	private $global_groups = array();
	private $non_persistent_groups = array();
	private $multisite = false;
	private $blog_prefix = '';


	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @access public
	 * @var int
	 */
	public $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @access public
	 * @var int
	 */
	public $cache_misses = 0;

	public function __construct() {
		global $table_prefix;

		$this->multisite   = is_multisite();
		$this->blog_prefix = $this->multisite ? get_current_blog_id() . ':' : '';
		$this->prefix      = DB_HOST . '.' . DB_NAME . '.' . $table_prefix;
	}

	private function get_group( $group ) {
		return empty( $group ) ? 'default' : $group;
	}

	private function get_key( $group, $key ) {

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$prefix = $this->prefix . '.' . $group . '.' . $this->blog_prefix . ':' . $key;
		} else {
			$prefix = $this->prefix . '.' . $group . '.' . $key;
		}

		return preg_replace( '/\s+/', '', WP_CACHE_KEY_SALT . "$prefix" );
	}

	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}
		if ( isset( $this->local_cache[ $group ][ $key ] ) ) {
			return false;
		}
		// FIXME: Somehow apcu_add does not return false if key already exists
		if ( ! isset( $this->non_persistent_groups[ $group ] ) && apcu_exists( $key ) ) {
			return false;
		}

		if ( is_object( $data ) ) {
			$this->local_cache[ $group ][ $key ] = clone $data;
		} else {
			$this->local_cache[ $group ][ $key ] = $data;
		}

		if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
			return apcu_add( $key, $data, (int) $expire );
		}

		return true;
	}

	public function add_global_groups( $groups ) {
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				$this->global_groups[ $group ] = true;
			}
		} else {
			$this->global_groups[ $groups ] = true;
		}
	}

	public function wp_cache_add_non_persistent_groups( $groups ) {
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				$this->non_persistent_groups[ $group ] = true;
			}
		} else {
			$this->non_persistent_groups[ $groups ] = true;
		}
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
		if ( $offset < 0 ) {
			return $this->incr( $key, abs( $offset ), $group );
		}

		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( isset( $this->local_cache[ $group ][ $key ] ) && $this->local_cache[ $group ][ $key ] - $offset >= 0 ) {
			$this->local_cache[ $group ][ $key ] -= $offset;
		} else {
			$this->local_cache[ $group ][ $key ] = 0;
		}

		if ( isset( $this->non_persistent_groups[ $group ] ) ) {
			return $this->local_cache[ $group ][ $key ];
		} else {
			$value = apcu_dec( $key, $offset );
			if ( $value < 0 ) {
				apcu_store( $key, 0 );

				return 0;
			}

			return $value;
		}
	}

	public function delete( $key, $group = 'default', $force = false ) {
		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		unset( $this->local_cache[ $group ][ $key ] );
		if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
			return apcu_delete( $key );
		}

		return true;
	}

	public function flush() {
		$this->local_cache = array();
		// TODO: only clear our own entries
		apcu_clear_cache();

		return true;
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( ! $force && isset( $this->local_cache[ $group ][ $key ] ) ) {
			$found            = true;
			$this->cache_hits += 1;
			if ( is_object( $this->local_cache[ $group ][ $key ] ) ) {
				return clone $this->local_cache[ $group ][ $key ];
			} else {
				return $this->local_cache[ $group ][ $key ];
			}
		} elseif ( isset( $this->non_persistent_groups[ $group ] ) ) {
			$found              = false;
			$this->cache_misses += 1;

			return false;
		} else {
			$value = apcu_fetch( $key, $found );
			if ( $found ) {
				if ( $force ) {
					$this->local_cache[ $group ][ $key ] = $value;
				}
				$this->cache_hits += 1;

				return $value;
			} else {
				$this->cache_misses += 1;

				return false;
			}
		}
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
		if ( $offset < 0 ) {
			return $this->decr( $key, abs( $offset ), $group );
		}

		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( isset( $this->local_cache[ $group ][ $key ] ) && $this->local_cache[ $group ][ $key ] + $offset >= 0 ) {
			$this->local_cache[ $group ][ $key ] += $offset;
		} else {
			$this->local_cache[ $group ][ $key ] = 0;
		}

		if ( isset( $this->non_persistent_groups[ $group ] ) ) {
			return $this->local_cache[ $group ][ $key ];
		} else {
			$value = apcu_inc( $key, $offset );
			if ( $value < 0 ) {
				apcu_store( $key, 0 );

				return 0;
			}

			return $value;
		}
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( isset( $this->non_persistent_groups[ $group ] ) ) {
			if ( ! isset( $this->local_cache[ $group ][ $key ] ) ) {
				return false;
			}
		} else {
			if ( ! isset( $this->local_cache[ $group ][ $key ] ) && ! apcu_exists( $key ) ) {
				return false;
			}
			apcu_store( $key, $data, (int) $expire );
		}

		if ( is_object( $data ) ) {
			$this->local_cache[ $group ][ $key ] = clone $data;
		} else {
			$this->local_cache[ $group ][ $key ] = $data;
		}

		return true;
	}

	public function reset() {
		// This function is deprecated as of WordPress 3.5
		// Be safe and flush the cache if this function is still used
		$this->flush();
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$group = $this->get_group( $group );
		$key   = $this->get_key( $group, $key );

		if ( is_object( $data ) ) {
			$this->local_cache[ $group ][ $key ] = clone $data;
		} else {
			$this->local_cache[ $group ][ $key ] = $data;
		}

		if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
			return apcu_store( $key, $data, (int) $expire );
		}

		return true;
	}

	public function stats() {
		// Only implemented because the default cache class provides this.
		// This method is never called.
		echo '';
	}

	public function switch_to_blog( $blog_id ) {
		$this->blog_prefix = $this->multisite ? $blog_id . ':' : '';
	}

}
