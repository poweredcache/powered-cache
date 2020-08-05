<?php
/**
 * Cloudflare API
 *
 * @package PoweredCache
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Powered_Cache_Cloudflare_Api
 */
class Powered_Cache_Cloudflare_Api {

	/**
	 * Cloudlfare API endpoint
	 *
	 * @var string $end_point
	 */
	private $end_point = 'https://api.cloudflare.com/client/v4';

	/**
	 * API Key
	 *
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * Cloudflare email address.
	 *
	 * @var string $email
	 */
	private $email;

	/**
	 * Powered_Cache_Cloudflare_Api constructor.
	 *
	 * @param string $email   Cloudflare email address.
	 * @param string $api_key Cloudflare API key.
	 */
	public function __construct( $email, $api_key ) {
		$this->email   = $email;
		$this->api_key = $api_key;
	}

	/**
	 * Get Cloudflare zones
	 *
	 * @return mixed|string
	 */
	public function get_zones() {
		$endpoint = $this->end_point . '/zones';

		$result = $this->remote_request(
			$endpoint,
			'GET',
			array(
				'page'     => 1,
				'per_page' => 1000,
			)
		);

		return $result;
	}

	/**
	 * Make purge request to CF
	 *
	 * @param string       $zone_id CF zone id
	 * @param string|array $args    purge arg.
	 *
	 * @return mixed|string
	 */
	public function purge( $zone_id, $args = 'all' ) {

		if ( 'all' === $args ) {
			$data['purge_everything'] = true;
		}

		if ( is_array( $args ) && array_key_exists( 'files', $args ) ) {
			$data['files'] = $args['files'];
		}
		if ( is_array( $args ) && array_key_exists( 'tags', $args ) ) {
			$data['files'] = $args['tags'];
		}

		$endpoint = $this->end_point . '/zones/' . $zone_id . '/purge_cache';

		$result = $this->remote_request( $endpoint, 'DELETE' );

		return $result;
	}

	/**
	 * Make an API call
	 *
	 * @param string $url  target url
	 * @param string $type request type
	 * @param array  $data request body
	 *
	 * @return mixed|string
	 */
	private function remote_request( $url, $type = 'GET', $data = array() ) {
		$args = array(
			'method'    => $type,
			'timeout'   => 5,
			'headers'   => array(
				'X-Auth-Email' => $this->email,
				'X-Auth-Key'   => $this->api_key,
				'Content-Type' => 'application/json',
			),
			'sslverify' => false,
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		} else {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}
	}

	/**
	 * Singleton
	 *
	 * @param string $email   CF email
	 * @param string $api_key CF API key
	 *
	 * @return bool|Powered_Cache_Cloudflare_Api
	 */
	public static function factory( $email, $api_key ) {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self( $email, $api_key );
		}

		return $instance;
	}

}
