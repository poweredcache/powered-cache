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
}
