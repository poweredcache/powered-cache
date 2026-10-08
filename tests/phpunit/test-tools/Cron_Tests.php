<?php
/**
 * Cron schedule tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Cron test case.
 */
class Cron_Tests extends TestCase {

	/**
	 * Set up the cache timeout and a custom purge interval.
	 */
	public function setUp(): void {
		parent::setUp();

		\WP_Mock::userFunction(
			'PoweredCache\Utils\get_settings',
			array(
				'times'  => 1,
				'args'   => array(),
				'return' => array( 'cache_timeout' => 30 ),
			)
		);

		\WP_Mock::onFilter( 'powered_cache_cache_purge_interval' )->with( 1800 )->reply( 900 );
	}

	/**
	 * Early requests retain the schedule without triggering translation loading.
	 */
	public function test_cron_schedules_before_init_does_not_load_translations() {
		\WP_Mock::userFunction(
			'did_action',
			array(
				'times'  => 1,
				'args'   => array( 'init' ),
				'return' => 0,
			)
		);

		\WP_Mock::userFunction( 'esc_html__', array( 'times' => 0 ) );

		$existing_schedule = array(
			'interval' => 3600,
			'display'  => 'Hourly',
		);
		$cron              = new Cron();
		$schedules         = $cron->cron_schedules( array( 'hourly' => $existing_schedule ) );

		$this->assertSame( $existing_schedule, $schedules['hourly'] );
		$this->assertSame( 900, $schedules['powered_cache']['interval'] );
		$this->assertSame( 'Powered Cache Purge Interval', $schedules['powered_cache']['display'] );
	}

	/**
	 * Requests once init has started use the translated schedule label.
	 */
	public function test_cron_schedules_after_init_translates_the_label() {
		\WP_Mock::userFunction(
			'did_action',
			array(
				'times'  => 1,
				'args'   => array( 'init' ),
				'return' => 1,
			)
		);

		\WP_Mock::userFunction(
			'esc_html__',
			array(
				'times'  => 1,
				'args'   => array( 'Powered Cache Purge Interval', 'powered-cache' ),
				'return' => 'Translated purge interval',
			)
		);

		$cron      = new Cron();
		$schedules = $cron->cron_schedules( array() );

		$this->assertSame( 900, $schedules['powered_cache']['interval'] );
		$this->assertSame( 'Translated purge interval', $schedules['powered_cache']['display'] );
	}
}
