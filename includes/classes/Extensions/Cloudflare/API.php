<?php
/**
 * Cloudflare API functionalities
 *
 * @package PoweredCache\Extensions\Cloudflare
 */

namespace PoweredCache\Extensions\Cloudflare;

/**
 * Class API
 */
class API {

	/**
	 * CF req. endpoint
	 *
	 * @var string
	 */
	private $end_point = 'https://api.cloudflare.com/client/v4';

	/**
	 * CF API Key
	 *
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * CF Email
	 *
	 * @var string $email
	 */
	private $email;

	/**
	 * API constructor.
	 *
	 * @param string $email   CF Email
	 * @param string $api_key CF API Key
	 */
	public function __construct( $email, $api_key ) {
		$this->email   = $email;
		$this->api_key = $api_key;
	}

	/**
	 * GET CF zones
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
	 * Purge given zone
	 *
	 * @param string $zone_id Zone ID
	 * @param string $args    deletion args
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
	 * Make remote req.
	 *
	 * @param string $url  URL
	 * @param string $type req type
	 * @param array  $data data
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
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @param string $email   CF Email
	 * @param string $api_key CF API Key
	 *
	 * @return API
	 */
	public static function factory( $email, $api_key ) {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self( $email, $api_key );
		}

		return $instance;
	}

}
