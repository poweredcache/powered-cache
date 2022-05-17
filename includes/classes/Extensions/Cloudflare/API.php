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
	 * Bearer Token
	 *
	 * @link https://api.cloudflare.com/#getting-started-requests
	 * @var string $api_token
	 */
	private $api_token;

	/**
	 * API constructor.
	 *
	 * @param string $email     CF Email
	 * @param string $api_key   CF API Key
	 * @param string $api_token Bearer Token
	 */
	public function __construct( $email = '', $api_key = '', $api_token = '' ) {
		$this->email     = $email;
		$this->api_key   = $api_key;
		$this->api_token = $api_token;
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
				'Content-Type' => 'application/json',
			),
			'sslverify' => false,
		);

		if ( ! empty( $this->api_token ) ) {
			$args['headers']['Authorization'] = "Bearer {$this->api_token}";
		} else {
			$args['headers']['X-Auth-Email'] = $this->email;
			$args['headers']['X-Auth-Key']   = $this->api_key;
		}

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
	 * @param string $email     CF Email
	 * @param string $api_key   CF API Key
	 * @param string $api_token CF API Token
	 *
	 * @return API
	 */
	public static function factory( $email, $api_key, $api_token ) {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self( $email, $api_key, $api_token );
		}

		return $instance;
	}

}
