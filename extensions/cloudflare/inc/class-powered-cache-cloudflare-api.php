<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Powered_Cache_Cloudflare_Api {

	private $end_point = 'https://api.cloudflare.com/client/v4';
	private $api_key;
	private $email;

	public function __construct( $email, $api_key ) {
		$this->email   = $email;
		$this->api_key = $api_key;
	}


	public function get_zones() {
		$endpoint = $this->end_point . '/zones';

		$result = $this->remote_request( $endpoint, array( 'page' => 1, 'per_page' => 1000 ) );

		return $result;
	}


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

		$result = $this->remote_request( $endpoint, 'DELETE'  );
		return $result;
	}


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
			$args['body'] = json_encode( $data );
		}


		$response = wp_remote_request( $url, $args);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		} else {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}
	}


	public static function factory( $email, $api_key ) {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self( $email, $api_key );
		}

		return $instance;
	}

}