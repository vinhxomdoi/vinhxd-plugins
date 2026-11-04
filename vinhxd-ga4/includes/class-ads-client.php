<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Client gọi Google Ads API bằng OAuth 2.0 (authorization code flow, dùng cho tài khoản
 * Gmail cá nhân - không dùng Service Account vì Ads API yêu cầu Domain-Wide Delegation
 * chỉ khả dụng với Google Workspace).
 *
 * Lưu ý quan trọng: kể từ 9/2026 Google Ads API KHÔNG còn yêu cầu "Developer Token" nữa -
 * chỉ cần access token (từ refresh token) là đủ, không cần gửi header developer-token.
 * Nếu Google đổi lại yêu cầu này trong tương lai, thêm header 'developer-token' vào
 * hàm request() bên dưới.
 */
class Vinhxd_Ads_Client {

	const API_VERSION = 'v25';
	const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	const API_BASE     = 'https://googleads.googleapis.com/' . self::API_VERSION;
	const SCOPE        = 'https://www.googleapis.com/auth/adwords';

	private $client_id;
	private $client_secret;
	private $refresh_token;

	public function __construct( $client_id, $client_secret, $refresh_token = '' ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->refresh_token = $refresh_token;
	}

	public function is_configured() {
		return ! empty( $this->client_id ) && ! empty( $this->client_secret );
	}

	public function is_connected() {
		return $this->is_configured() && ! empty( $this->refresh_token );
	}

	/** URL redirect cố định - phải khai báo đúng URL này trong "Authorized redirect URIs" của OAuth Client trên Cloud Console */
	public static function get_redirect_uri() {
		return admin_url( 'admin-post.php?action=vinhxd_ads_oauth_callback' );
	}

	/** Bước 1: dựng URL đưa người dùng sang trang đăng nhập/đồng ý của Google */
	public static function get_authorization_url( $client_id ) {
		$params = array(
			'client_id'              => $client_id,
			'redirect_uri'           => self::get_redirect_uri(),
			'response_type'          => 'code',
			'scope'                  => self::SCOPE,
			'access_type'            => 'offline', // bắt buộc để được cấp refresh_token
			'prompt'                 => 'consent', // bắt buộc để CHẮC CHẮN nhận refresh_token (nếu không Google có thể chỉ trả access_token khi đã từng đồng ý trước đó)
			'include_granted_scopes' => 'true',
			// Chống CSRF: Google trả lại nguyên giá trị này ở callback, plugin kiểm tra trước khi lưu token
			'state'                  => wp_create_nonce( 'vinhxd_ads_oauth' ),
		);
		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/** Bước 2: đổi authorization code (Google redirect về kèm ?code=...) lấy refresh_token */
	public static function exchange_code_for_token( $client_id, $client_secret, $code ) {
		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'redirect_uri'  => self::get_redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['refresh_token'] ) ) {
			$msg = $body['error_description'] ?? ( $body['error'] ?? 'Không nhận được refresh_token từ Google.' );
			return new WP_Error( 'vinhxd_ads_no_refresh_token', $msg . ' (Nếu bạn từng kết nối trước đó và đã thu hồi quyền truy cập trong tài khoản Google, hãy thử lại - lần đầu luôn được cấp refresh_token khi có prompt=consent.)' );
		}

		return $body; // chứa access_token, refresh_token, expires_in...
	}

	/** Lấy access token hiện hành từ refresh_token đã lưu, cache 55 phút */
	public function get_access_token() {
		if ( ! $this->is_connected() ) {
			return new WP_Error( 'vinhxd_ads_not_connected', 'Chưa kết nối Google Ads (thiếu refresh_token).' );
		}

		$cache_key = 'vinhxd_ads_token_' . md5( $this->client_id . $this->refresh_token );
		$cached = get_transient( $cache_key );
		if ( $cached ) return $cached;

		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'refresh_token' => $this->refresh_token,
				'grant_type'    => 'refresh_token',
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = $body['error_description'] ?? ( $body['error'] ?? 'Không lấy được access_token.' );
			return new WP_Error( 'vinhxd_ads_token_error', $msg );
		}

		// Trừ hao 5 phút so với thời gian thực để tránh dùng token sát hạn
		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 );
		set_transient( $cache_key, $body['access_token'], $ttl );

		return $body['access_token'];
	}

	/** Gọi 1 request bất kỳ tới Google Ads API (REST) */
	private function request( $method, $path, $body = null, $extra_headers = array() ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) return $token;

		$headers = array_merge( array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		), $extra_headers );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);
		if ( $body !== null ) $args['body'] = wp_json_encode( $body );

		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$msg = $data['error']['message'];
				if ( ! empty( $data['error']['details'] ) ) {
					$msg .= ' | Chi tiết: ' . wp_json_encode( $data['error']['details'] );
				}
			} else {
				// Google trả về nội dung không phải JSON (thường gặp khi gọi sai phiên bản API,
				// bị chặn ở tầng gateway, hoặc lỗi mạng phía hosting) - vẫn hiện rõ để dễ debug,
				// thay vì để màn hình trống không rõ nguyên nhân.
				$snippet = substr( trim( wp_strip_all_tags( $raw_body ) ), 0, 300 );
				$msg = 'Lỗi Google Ads API (mã HTTP ' . $code . ')' . ( $snippet ? ': ' . $snippet : ' - phản hồi rỗng, có thể bị chặn ở tầng mạng/hosting.' );
			}
			return new WP_Error( 'vinhxd_ads_api_error', self::explain_error( $msg ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'vinhxd_ads_api_error', 'Phản hồi từ Google không đúng định dạng JSON mong đợi (mã HTTP ' . $code . ').' );
		}

		return $data;
	}

	/** Mã lỗi khi Cloud project mới chỉ có Test access mà gọi vào tài khoản Ads thật */
	const ERR_NOT_APPROVED = 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION';

	public static function is_not_approved_error( $message ) {
		return strpos( (string) $message, self::ERR_NOT_APPROVED ) !== false;
	}

	/**
	 * Thêm hướng dẫn tiếng Việt cho các lỗi hay gặp sau khi Google bỏ Developer Token (9/9/2026):
	 * quyền gọi API giờ gắn với Google Cloud project sở hữu OAuth Client.
	 */
	public static function explain_error( $msg ) {
		if ( self::is_not_approved_error( $msg ) || strpos( $msg, 'ACTION_NOT_PERMITTED' ) !== false ) {
			return 'Google Cloud project chứa OAuth Client của bạn mới chỉ có quyền "Test access" (chỉ dùng được với tài khoản Ads thử nghiệm). '
				. 'Vào Google Cloud Console → APIs & Services → Google Ads API → tab Overview → bấm xin cấp "Explorer access" (duyệt tự động, tối đa 2.880 thao tác/ngày - quá đủ cho việc chặn IP). '
				. 'Lưu ý project cần bật thanh toán (không phải Free Trial) mới được duyệt. Chi tiết gốc: ' . $msg;
		}
		return $msg;
	}

	/**
	 * TEST KẾT NỐI: liệt kê các tài khoản Ads mà tài khoản Google đang đăng nhập có quyền truy cập.
	 * Đây là lệnh gọi đơn giản nhất để xác nhận toàn bộ chuỗi xác thực (client id/secret/refresh token) đúng.
	 */
	public function list_accessible_customers() {
		$result = $this->request( 'GET', '/customers:listAccessibleCustomers' );
		if ( is_wp_error( $result ) ) return $result;

		// resourceNames dạng "customers/1234567890" -> rút gọn lấy phần số (Customer ID)
		$ids = array();
		foreach ( $result['resourceNames'] ?? array() as $rn ) {
			$ids[] = str_replace( 'customers/', '', $rn );
		}
		return $ids;
	}

	/**
	 * Thêm 1 IP vào danh sách loại trừ của 1 chiến dịch (CampaignCriterion, loại IP_BLOCK, negative=true).
	 * $customer_id: Customer ID (chỉ số, không có dấu gạch ngang) của tài khoản Ads khách hàng.
	 * $campaign_resource_name: dạng "customers/123/campaigns/456".
	 * $login_customer_id: Customer ID của tài khoản quản lý (MCC) - cần nếu $customer_id là tài khoản con dưới MCC.
	 */
	public function add_ip_exclusion( $customer_id, $campaign_resource_name, $ip_address, $login_customer_id = '' ) {
		$body = array(
			'operations' => array(
				array(
					'create' => array(
						'campaign' => $campaign_resource_name,
						'negative' => true,
						'ipBlock'  => array( 'ipAddress' => $ip_address ),
					),
				),
			),
		);

		$extra_headers = array();
		if ( $login_customer_id ) $extra_headers['login-customer-id'] = $login_customer_id;

		return $this->request( 'POST', "/customers/{$customer_id}/campaignCriteria:mutate", $body, $extra_headers );
	}

	/**
	 * Chặn 1 IP ở CẤP TÀI KHOẢN (CustomerNegativeCriterion) - đây chính là danh sách hiện trong
	 * Google Ads → Cài đặt tài khoản → "Loại trừ địa chỉ IP". Ưu điểm so với chặn từng chiến dịch:
	 * chỉ 1 lần gọi API, và tự động áp dụng cho cả chiến dịch tạo sau này.
	 */
	public function add_account_ip_exclusion( $customer_id, $ip_address, $login_customer_id = '' ) {
		$body = array(
			'operations' => array(
				array(
					'create' => array(
						'ipBlock' => array( 'ipAddress' => $ip_address ),
					),
				),
			),
		);

		$extra_headers = array();
		if ( $login_customer_id ) $extra_headers['login-customer-id'] = $login_customer_id;

		return $this->request( 'POST', "/customers/{$customer_id}/customerNegativeCriteria:mutate", $body, $extra_headers );
	}

	/**
	 * Chặn 1 IP - dùng cho nút "Chặn IP" trong Nhật ký chuyển đổi.
	 * Ưu tiên chặn ở CẤP TÀI KHOẢN (hiện đúng trong Google Ads → Cài đặt tài khoản → "Loại trừ IP",
	 * tự áp dụng cho mọi chiến dịch kể cả tạo sau này). Nếu tài khoản không hỗ trợ (lỗi từ API),
	 * tự động chuyển sang chặn từng chiến dịch đang hoạt động như phương án dự phòng.
	 * Trả về mảng: ['level'=>'account'|'campaign', 'success'=>N, 'failed'=>[...], 'total'=>N]
	 */
	public function block_ip_on_active_campaigns( $customer_id, $ip_address, $login_customer_id = '' ) {
		$account_result = $this->add_account_ip_exclusion( $customer_id, $ip_address, $login_customer_id );

		if ( ! is_wp_error( $account_result ) ) {
			return array( 'level' => 'account', 'success' => 1, 'failed' => array(), 'total' => 1 );
		}

		// IP đã từng chặn ở cấp tài khoản trước đó -> Google báo trùng, vẫn tính là thành công
		if ( strpos( $account_result->get_error_message(), 'DUPLICATE' ) !== false ) {
			return array( 'level' => 'account', 'success' => 1, 'failed' => array(), 'total' => 1 );
		}

		// Lỗi quyền truy cập API (project chưa được duyệt) thì chặn từng chiến dịch cũng sẽ lỗi y hệt -
		// trả lỗi luôn, tránh tốn hàng chục lệnh gọi API vô ích
		if ( self::is_not_approved_error( $account_result->get_error_message() ) || strpos( $account_result->get_error_message(), 'Google Cloud project' ) === 0 ) {
			return $account_result;
		}

		// Phương án dự phòng: tài khoản không hỗ trợ chặn cấp tài khoản -> chặn từng chiến dịch đang chạy
		$campaigns = $this->list_campaigns( $customer_id, $login_customer_id );
		if ( is_wp_error( $campaigns ) ) return $campaigns;

		if ( empty( $campaigns ) ) {
			return new WP_Error( 'vinhxd_ads_no_campaigns', 'Không chặn được ở cấp tài khoản (' . $account_result->get_error_message() . '), và tài khoản này cũng không có chiến dịch nào đang hoạt động để chặn dự phòng.' );
		}

		$success = 0;
		$failed  = array();

		foreach ( $campaigns as $c ) {
			$result = $this->add_ip_exclusion( $customer_id, $c['resource_name'], $ip_address, $login_customer_id );
			if ( is_wp_error( $result ) ) {
				if ( strpos( $result->get_error_message(), 'DUPLICATE' ) !== false ) {
					$success++;
				} else {
					$failed[] = $c['name'] . ': ' . $result->get_error_message();
				}
			} else {
				$success++;
			}
		}

		return array( 'level' => 'campaign', 'success' => $success, 'failed' => $failed, 'total' => count( $campaigns ) );
	}
	public function list_campaigns( $customer_id, $login_customer_id = '' ) {
		$query = "SELECT campaign.id, campaign.name, campaign.status FROM campaign WHERE campaign.status = 'ENABLED'";
		$extra_headers = array();
		if ( $login_customer_id ) $extra_headers['login-customer-id'] = $login_customer_id;

		$result = $this->request( 'POST', "/customers/{$customer_id}/googleAds:search", array( 'query' => $query ), $extra_headers );
		if ( is_wp_error( $result ) ) return $result;

		$campaigns = array();
		foreach ( $result['results'] ?? array() as $row ) {
			$campaigns[] = array(
				'id'            => $row['campaign']['id'],
				'name'          => $row['campaign']['name'],
				'resource_name' => $row['campaign']['resourceName'],
			);
		}
		return $campaigns;
	}
}
