<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Trang "Google Ads" trong menu XD Analytics: kết nối OAuth 1 lần, sau đó dùng để
 * test kết nối API và (ở bước sau) thao tác chặn IP ngay trong Nhật ký chuyển đổi.
 */
class Vinhxd_Ads_Settings {

	public static function init() {
		add_action( 'admin_post_vinhxd_ads_oauth_callback', array( __CLASS__, 'handle_oauth_callback' ) );
		add_action( 'admin_post_vinhxd_ads_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	public static function get_client() {
		$client_id     = get_option( 'vinhxd_ads_client_id', '' );
		$client_secret = get_option( 'vinhxd_ads_client_secret', '' );
		$refresh_token = get_option( 'vinhxd_ads_refresh_token', '' );
		return new Vinhxd_Ads_Client( $client_id, $client_secret, $refresh_token );
	}

	/** Lưu Client ID / Client Secret */
	public static function handle_save_credentials() {
		if ( ! isset( $_POST['vinhxd_ads_nonce'] ) || ! wp_verify_nonce( $_POST['vinhxd_ads_nonce'], 'vinhxd_ads_save' ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		update_option( 'vinhxd_ads_client_id', sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ), false );
		// Ô Client Secret không còn in giá trị cũ ra HTML; để trống = giữ nguyên secret đã lưu
		$new_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );
		if ( $new_secret !== '' ) update_option( 'vinhxd_ads_client_secret', $new_secret, false );

		echo '<div class="notice notice-success"><p>Đã lưu Client ID / Client Secret. Giờ bạn có thể bấm "Kết nối với Google Ads" bên dưới.</p></div>';
	}

	/** Bước redirect Google gọi về sau khi người dùng đồng ý cấp quyền */
	public static function handle_oauth_callback() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );

		// Chống CSRF: chỉ chấp nhận callback xuất phát từ nút "Kết nối" do chính admin này bấm
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! $state || ! wp_verify_nonce( $state, 'vinhxd_ads_oauth' ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'vinhxd-ga4-ads', 'ads_error' => urlencode( 'Phiên kết nối không hợp lệ hoặc đã hết hạn (state). Vui lòng bấm lại nút "Kết nối với Google Ads".' ) ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'vinhxd-ga4-ads', 'ads_error' => urlencode( sanitize_text_field( $_GET['error'] ) ) ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		if ( ! $code ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vinhxd-ga4-ads&ads_error=missing_code' ) );
			exit;
		}

		$client_id     = get_option( 'vinhxd_ads_client_id', '' );
		$client_secret = get_option( 'vinhxd_ads_client_secret', '' );

		$result = Vinhxd_Ads_Client::exchange_code_for_token( $client_id, $client_secret, $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'vinhxd-ga4-ads', 'ads_error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}

		update_option( 'vinhxd_ads_refresh_token', sanitize_text_field( $result['refresh_token'] ), false );

		wp_safe_redirect( admin_url( 'admin.php?page=vinhxd-ga4-ads&ads_connected=1' ) );
		exit;
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
		check_admin_referer( 'vinhxd_ads_disconnect' );

		delete_option( 'vinhxd_ads_refresh_token' );
		delete_transient( 'vinhxd_ads_accounts_list' );

		wp_safe_redirect( admin_url( 'admin.php?page=vinhxd-ga4-ads&ads_disconnected=1' ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['vinhxd_ads_nonce'] ) ) self::handle_save_credentials();

		$client_id     = esc_attr( get_option( 'vinhxd_ads_client_id', '' ) );
		$client_secret = get_option( 'vinhxd_ads_client_secret', '' );
		$refresh_token = get_option( 'vinhxd_ads_refresh_token', '' );
		$redirect_uri  = Vinhxd_Ads_Client::get_redirect_uri();

		echo '<div class="wrap"><h1>XD Analytics - Google Ads</h1>';
		echo '<p>Kết nối tài khoản Google Ads để có thể chặn IP click tặc ngay từ trang Nhật ký chuyển đổi, không cần mở Google Ads riêng.</p>';

		if ( isset( $_GET['ads_connected'] ) ) {
			echo '<div class="notice notice-success"><p>✅ Đã kết nối thành công với Google Ads!</p></div>';
		}
		if ( isset( $_GET['ads_disconnected'] ) ) {
			echo '<div class="notice notice-success"><p>Đã ngắt kết nối.</p></div>';
		}
		if ( isset( $_GET['ads_error'] ) ) {
			echo '<div class="notice notice-error"><p>Lỗi kết nối: ' . esc_html( urldecode( $_GET['ads_error'] ) ) . '</p></div>';
		}

		// ===== Bước 1: Client ID / Client Secret =====
		echo '<h2 class="title">Bước 1: Thông tin OAuth Client</h2>';
		echo '<table class="form-table"><tr><th colspan="2">';
		echo '<p class="description">Lấy tại Google Cloud Console → APIs &amp; Services → Credentials → Create Credentials → OAuth client ID → chọn loại <strong>Web application</strong>.<br>';
		echo 'Ở mục <strong>Authorized redirect URIs</strong>, thêm chính xác URL này:</p>';
		echo '<code style="display:inline-block; background:#f0f0f1; padding:6px 10px; border-radius:4px; margin:4px 0 12px;">' . esc_html( $redirect_uri ) . '</code>';
		echo '</th></tr></table>';

		echo '<form method="post">';
		wp_nonce_field( 'vinhxd_ads_save', 'vinhxd_ads_nonce' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="client_id">Client ID</label></th><td><input type="text" name="client_id" id="client_id" class="regular-text" value="' . $client_id . '" /></td></tr>';
		echo '<tr><th><label for="client_secret">Client Secret</label></th><td><input type="password" name="client_secret" id="client_secret" class="regular-text" value="" autocomplete="new-password" placeholder="' . ( $client_secret ? '•••••••••••• (đã lưu - để trống nếu không đổi)' : '' ) . '" /></td></tr>';
		echo '</table>';
		submit_button( 'Lưu thông tin' );
		echo '</form>';

		// ===== Bước 2: Kết nối OAuth =====
		echo '<h2 class="title">Bước 2: Kết nối tài khoản Google</h2>';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			echo '<p class="description">Điền và lưu Client ID / Client Secret ở Bước 1 trước.</p>';
		} elseif ( $refresh_token ) {
			echo '<p>✅ <strong>Đã kết nối.</strong></p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
			wp_nonce_field( 'vinhxd_ads_disconnect' );
			echo '<input type="hidden" name="action" value="vinhxd_ads_disconnect" />';
			echo '<button type="submit" class="button" onclick="return confirm(\'Ngắt kết nối Google Ads?\');">Ngắt kết nối</button>';
			echo '</form>';
		} else {
			$auth_url = Vinhxd_Ads_Client::get_authorization_url( $client_id );
			echo '<p><a href="' . esc_url( $auth_url ) . '" class="button button-primary">Kết nối với Google Ads</a></p>';
			echo '<p class="description"><strong>Quan trọng (từ 9/2026 Google bỏ Developer Token):</strong> Google Cloud project chứa OAuth Client này cần được cấp <strong>Explorer access</strong> trở lên cho Google Ads API (Cloud Console → APIs &amp; Services → Google Ads API → Overview) thì mới chặn IP được trên tài khoản Ads thật. Project mới mặc định chỉ có Test access.</p>';
			echo '<p class="description">Đăng nhập bằng tài khoản Google đang có quyền quản trị trên các tài khoản Ads khách hàng. Nếu OAuth consent screen còn ở trạng thái "Testing", nhớ thêm tài khoản này vào danh sách "Test users" trước, và lưu ý refresh token lúc này chỉ sống được 7 ngày (dùng để test, chưa nên dùng thật cho khách hàng).</p>';
		}

		// ===== Bước 3: Test kết nối =====
		echo '<h2 class="title">Bước 3: Test kết nối</h2>';

		if ( ! $refresh_token ) {
			echo '<p class="description">Hoàn tất Bước 2 trước.</p></div>';
			return;
		}

		echo '<form method="get" style="margin-bottom:12px;">';
		echo '<input type="hidden" name="page" value="vinhxd-ga4-ads" />';
		echo '<input type="hidden" name="run_test" value="1" />';
		submit_button( 'Chạy test: liệt kê tài khoản Ads truy cập được', 'secondary', 'submit', false );
		echo '</form>';

		if ( isset( $_GET['run_test'] ) ) {
			try {
				$client = self::get_client();

				if ( ! $client->is_connected() ) {
					echo '<div class="notice notice-error"><p>Chưa kết nối đầy đủ (thiếu Client ID/Secret hoặc refresh token). Kiểm tra lại Bước 1 và Bước 2.</p></div>';
				} else {
					$result = $client->list_accessible_customers();

					if ( is_wp_error( $result ) ) {
						echo '<div class="notice notice-error"><p><strong>Lỗi:</strong> ' . esc_html( $result->get_error_message() ) . '</p></div>';
					} elseif ( ! is_array( $result ) || empty( $result ) ) {
						echo '<div class="notice notice-warning"><p>Kết nối thành công nhưng không thấy tài khoản Ads nào. Kiểm tra lại quyền của tài khoản Google bạn vừa đăng nhập.</p></div>';
					} else {
						echo '<div class="notice notice-success"><p><strong>✅ Kết nối OK!</strong> Tìm thấy ' . count( $result ) . ' tài khoản Ads truy cập được:</p>';
						echo '<ul style="margin-left:20px; list-style:disc;">';
						foreach ( $result as $cid ) {
							$formatted = preg_replace( '/(\d{3})(\d{3})(\d+)/', '$1-$2-$3', $cid );
							echo '<li><code>' . esc_html( $formatted ) . '</code></li>';
						}
						echo '</ul></div>';
					}
				}
			} catch ( Throwable $e ) {
				echo '<div class="notice notice-error"><p><strong>Lỗi PHP không mong đợi:</strong> ' . esc_html( $e->getMessage() ) . ' (dòng ' . (int) $e->getLine() . ')</p></div>';
			}
		}

		// ===== Bước 4: Chọn tài khoản Ads áp dụng cho SITE NÀY =====
		echo '<h2 class="title">Bước 4: Chọn tài khoản Ads cho website này</h2>';
		echo '<p class="description">Mỗi website 1 khách hàng khác nhau, nên cần chỉ rõ nút "Chặn IP" ở Nhật ký chuyển đổi của website này sẽ thao tác trên đúng tài khoản Ads nào.</p>';

		if ( isset( $_POST['vinhxd_ads_customer_nonce'] ) && wp_verify_nonce( $_POST['vinhxd_ads_customer_nonce'], 'vinhxd_ads_save_customer' ) ) {
			update_option( 'vinhxd_ads_customer_id', preg_replace( '/\D/', '', $_POST['ads_customer_id'] ?? '' ) );
			update_option( 'vinhxd_ads_login_customer_id', preg_replace( '/\D/', '', $_POST['ads_login_customer_id'] ?? '' ) );
			echo '<div class="notice notice-success"><p>Đã lưu.</p></div>';
		}

		$current_customer_id = get_option( 'vinhxd_ads_customer_id', '' );
		$current_login_id    = get_option( 'vinhxd_ads_login_customer_id', '' );

		// Lấy danh sách tài khoản để hiện dropdown (cache 30 phút, tránh gọi API mỗi lần vào trang)
		$accounts_cache_key = 'vinhxd_ads_accounts_list';
		$accounts = get_transient( $accounts_cache_key );
		if ( false === $accounts ) {
			$client = self::get_client();
			$accounts = $client->is_connected() ? $client->list_accessible_customers() : array();
			if ( ! is_wp_error( $accounts ) ) set_transient( $accounts_cache_key, $accounts, 30 * MINUTE_IN_SECONDS );
		}
		if ( is_wp_error( $accounts ) ) $accounts = array();

		echo '<form method="post">';
		wp_nonce_field( 'vinhxd_ads_save_customer', 'vinhxd_ads_customer_nonce' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="ads_customer_id">Tài khoản Ads của website này</label></th><td>';
		if ( ! empty( $accounts ) ) {
			echo '<select name="ads_customer_id" id="ads_customer_id">';
			echo '<option value="">— Chọn tài khoản —</option>';
			foreach ( $accounts as $cid ) {
				$formatted = preg_replace( '/(\d{3})(\d{3})(\d+)/', '$1-$2-$3', $cid );
				echo '<option value="' . esc_attr( $cid ) . '" ' . selected( $current_customer_id, $cid, false ) . '>' . esc_html( $formatted ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" name="ads_customer_id" class="regular-text" value="' . esc_attr( $current_customer_id ) . '" placeholder="VD: 1234567890 (không dấu gạch ngang)" />';
			echo '<p class="description">Chưa lấy được danh sách tài khoản - chạy lại Bước 3 trước, hoặc điền tay Customer ID.</p>';
		}
		echo '</td></tr>';

		echo '<tr><th><label for="ads_login_customer_id">Login Customer ID (MCC) - tuỳ chọn</label></th><td>';
		echo '<input type="text" name="ads_login_customer_id" id="ads_login_customer_id" class="regular-text" value="' . esc_attr( $current_login_id ) . '" placeholder="VD: 1234567890" />';
		echo '<p class="description">Chỉ điền nếu tài khoản trên là <strong>tài khoản con dưới 1 tài khoản quản lý (Manager/MCC)</strong>: điền Customer ID của tài khoản MCC đó. Nếu không chắc, cứ để trống bấm lưu trước - nếu lúc chặn IP báo lỗi liên quan tới quyền truy cập, quay lại điền ô này.</p>';
		echo '</td></tr>';
		echo '</table>';
		submit_button( 'Lưu tài khoản cho website này' );
		echo '</form>';

		echo '</div>';
	}
}

Vinhxd_Ads_Settings::init();
