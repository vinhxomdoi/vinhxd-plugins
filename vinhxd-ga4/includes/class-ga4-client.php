<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Client gọi GA4 Data API bằng Service Account (JWT tự ký, dùng OpenSSL có sẵn trong PHP).
 */
class Vinhxd_GA4_Client {

	private $client_email;
	private $private_key;
	private $property_id;

	public function __construct( $service_account_json, $property_id ) {
		$creds = json_decode( $service_account_json, true );
		$this->client_email = isset( $creds['client_email'] ) ? $creds['client_email'] : '';
		$this->private_key  = isset( $creds['private_key'] ) ? $creds['private_key'] : '';
		$this->property_id  = $property_id;
	}

	public function is_configured() {
		return ! empty( $this->client_email ) && ! empty( $this->private_key ) && ! empty( $this->property_id );
	}

	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function generate_jwt() {
		$header = wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) );
		$now = time();
		$claim = wp_json_encode( array(
			'iss'   => $this->client_email,
			'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		) );

		$segments = array( $this->base64url_encode( $header ), $this->base64url_encode( $claim ) );
		$signing_input = implode( '.', $segments );

		$key = openssl_pkey_get_private( $this->private_key );
		if ( ! $key ) {
			return new WP_Error( 'vinhxd_ga4_invalid_key', 'Private key trong Service Account JSON không hợp lệ.' );
		}

		$signature = '';
		openssl_sign( $signing_input, $signature, $key, 'SHA256' );
		$segments[] = $this->base64url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Lấy access token, cache bằng transient 55 phút để tránh xin token liên tục
	 */
	public function get_access_token() {
		$cache_key = 'vinhxd_ga4_token_' . md5( $this->client_email . $this->property_id );
		$cached = get_transient( $cache_key );
		if ( $cached ) return $cached;

		$jwt = $this->generate_jwt();
		if ( is_wp_error( $jwt ) ) return $jwt;

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : 'Không lấy được access token từ Google.';
			return new WP_Error( 'vinhxd_ga4_token_error', $msg );
		}

		set_transient( $cache_key, $body['access_token'], 3300 );
		return $body['access_token'];
	}

	/**
	 * Gọi runReport của GA4 Data API
	 * @param array $args body gửi lên API (dimensions, metrics, dateRanges, ...)
	 * @return array|WP_Error kết quả JSON đã decode
	 */
	public function run_report( $args ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'vinhxd_ga4_not_configured', 'Chưa cấu hình đủ Property ID / Service Account JSON.' );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) return $token;

		$url = "https://analyticsdata.googleapis.com/v1beta/properties/{$this->property_id}:runReport";

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $args ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Lỗi không xác định từ GA4 API (mã ' . $code . ').';
			return new WP_Error( 'vinhxd_ga4_api_error', $msg );
		}

		return $body;
	}
}
