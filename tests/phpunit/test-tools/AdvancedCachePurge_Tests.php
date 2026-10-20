<?php
/**
 * Advanced cache purge tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Advanced cache purge test case.
 */
class AdvancedCachePurge_Tests extends TestCase {

	/**
	 * Test files loaded before each test.
	 *
	 * @var array
	 */
	protected $testFiles = array( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
		'classes/AdvancedCache.php',
	);

	/**
	 * It purges a published post when an editor updates it without changing status.
	 */
	public function test_published_post_update_triggers_purge() {
		$cache = new class() extends AdvancedCache {
			/**
			 * Purged post IDs.
			 *
			 * @var array
			 */
			public $purged_post_ids = array();

			/**
			 * Record the post that would be purged.
			 *
			 * @param int $post_id Post ID.
			 */
			public function purge_post( $post_id ) {
				$this->purged_post_ids[] = $post_id;
			}
		};

		$cache->purge_on_post_update( 'publish', 'publish', (object) array( 'ID' => 42 ) );

		$this->assertSame( array( 42 ), $cache->purged_post_ids );
	}

	/**
	 * It ignores the last-updated timestamp change WordPress makes after post saves.
	 */
	public function test_site_timestamp_update_does_not_trigger_purge() {
		\WP_Mock::userFunction( 'switch_to_blog', array( 'times' => 0 ) );
		\WP_Mock::userFunction( 'restore_current_blog', array( 'times' => 0 ) );

		$old_site               = $this->get_site_data();
		$new_site               = clone $old_site;
		$new_site->last_updated = '2026-10-20 09:18:24';

		$cache = new AdvancedCache();
		$cache->purge_on_site_update( $new_site, $old_site );

		$this->assertConditionsMet();
	}

	/**
	 * It detects site changes that can affect cached URLs or availability.
	 */
	public function test_cache_relevant_site_update_is_detected() {
		$cache = new class() extends AdvancedCache {
			/**
			 * Expose the site update check for testing.
			 *
			 * @param object $new_site New site object.
			 * @param object $old_site Old site object.
			 *
			 * @return bool
			 */
			public function update_affects_cache( $new_site, $old_site ) {
				return $this->site_update_affects_cache( $new_site, $old_site );
			}
		};

		$old_site         = $this->get_site_data();
		$new_site         = clone $old_site;
		$new_site->domain = 'new.example.com';

		$this->assertTrue( $cache->update_affects_cache( $new_site, $old_site ) );
	}

	/**
	 * Return representative multisite data.
	 *
	 * @return object
	 */
	private function get_site_data() {
		return (object) array(
			'id'           => 68,
			'site_id'      => 1,
			'domain'       => 'example.com',
			'path'         => '/',
			'registered'   => '2020-01-01 00:00:00',
			'last_updated' => '2026-10-20 09:00:00',
			'public'       => 1,
			'archived'     => 0,
			'mature'       => 0,
			'spam'         => 0,
			'deleted'      => 0,
			'lang_id'      => 0,
		);
	}
}
