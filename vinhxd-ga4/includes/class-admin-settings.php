<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vinhxd_GA4_Admin_Settings {

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['vinhxd_ga4_settings_nonce'] ) && wp_verify_nonce( $_POST['vinhxd_ga4_settings_nonce'], 'vinhxd_ga4_save_settings' ) ) {
			update_option( 'vinhxd_ga4_measurement_id', sanitize_text_field( $_POST['measurement_id'] ?? '' ) );
			update_option( 'vinhxd_ga4_property_id', sanitize_text_field( $_POST['property_id'] ?? '' ) );
			update_option( 'vinhxd_ga4_service_account_json', wp_unslash( $_POST['service_account_json'] ?? '' ), false );
			update_option( 'vinhxd_ga4_exclude_admin', isset( $_POST['exclude_admin'] ) ? '1' : '0' );
			update_option( 'vinhxd_ga4_exclude_bots', isset( $_POST['exclude_bots'] ) ? '1' : '0' );
			update_option( 'vinhxd_ga4_manual_ip_exclude', sanitize_textarea_field( wp_unslash( $_POST['manual_ip_exclude'] ?? '' ) ) );
			// Proxy tin cậy: chỉ giữ dòng là IP hoặc CIDR hợp lệ
			$proxy_lines = array();
			foreach ( explode( "\n", (string) wp_unslash( $_POST['trusted_proxies'] ?? '' ) ) as $line ) {
				$line = trim( $line );
				$base = strpos( $line, '/' ) !== false ? explode( '/', $line )[0] : $line;
				if ( $line !== '' && filter_var( $base, FILTER_VALIDATE_IP ) ) $proxy_lines[] = $line;
			}
			update_option( Vinhxd_GA4_Internal_Tracking::TRUSTED_PROXIES_OPTION, implode( "\n", $proxy_lines ) );
			update_option( 'vinhxd_ga4_view_post_types', isset( $_POST['view_post_types'] ) ? array_map( 'sanitize_key', (array) $_POST['view_post_types'] ) : array() );

			delete_transient( 'vinhxd_ga4_token_' . md5( ( json_decode( get_option( 'vinhxd_ga4_service_account_json', '' ), true )['client_email'] ?? '' ) . get_option( 'vinhxd_ga4_property_id', '' ) ) );

			echo '<div class="notice notice-success"><p>Đã lưu cài đặt.</p></div>';
		}

		$measurement_id = esc_attr( get_option( 'vinhxd_ga4_measurement_id', '' ) );
		$property_id    = esc_attr( get_option( 'vinhxd_ga4_property_id', '' ) );
		$sa_json        = esc_textarea( get_option( 'vinhxd_ga4_service_account_json', '' ) );
		$exclude_admin  = get_option( 'vinhxd_ga4_exclude_admin', '0' ) === '1';
		$exclude_bots   = get_option( 'vinhxd_ga4_exclude_bots', '1' ) === '1';
		$manual_ip_exclude = get_option( 'vinhxd_ga4_manual_ip_exclude', '' );
		$trusted_proxies   = get_option( Vinhxd_GA4_Internal_Tracking::TRUSTED_PROXIES_OPTION, '' );
		$detected_ip       = Vinhxd_GA4_Internal_Tracking::get_client_ip();
		$remote_addr       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$xff               = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		?>
		<div class="wrap">
			<h1>XD Analytics - Cài đặt</h1>

			<style>
				.xd-ga-guide { background:#fff; border:1px solid #dcdcde; border-left:4px solid #2271b1; border-radius:4px; padding:16px 20px; margin:16px 0 24px; max-width:900px; }
				.xd-ga-guide > p:first-child { margin-top:0; }
				.xd-ga-guide summary { cursor:pointer; font-weight:600; color:#2271b1; font-size:14px; padding:6px 0; user-select:none; }
				.xd-ga-guide summary:hover { color:#135e96; }
				.xd-ga-steps { counter-reset:xdstep; list-style:none; margin:14px 0 0; padding:0; }
				.xd-ga-steps > li { counter-increment:xdstep; position:relative; padding:0 0 18px 42px; border-left:2px solid #e5e7eb; margin-left:13px; }
				.xd-ga-steps > li:last-child { border-left-color:transparent; padding-bottom:0; }
				.xd-ga-steps > li::before { content:counter(xdstep); position:absolute; left:-15px; top:0; width:28px; height:28px; border-radius:50%; background:#2271b1; color:#fff; font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; }
				.xd-ga-steps h4 { margin:2px 0 6px; font-size:14px; color:#1d2327; }
				.xd-ga-steps p, .xd-ga-steps ul { margin:4px 0; font-size:13.5px; color:#3c434a; line-height:1.6; }
				.xd-ga-steps ul { padding-left:18px; }
				.xd-ga-note { background:#f6f7f7; border-radius:4px; padding:10px 14px; margin-top:8px; font-size:13px; color:#50575e; }
			</style>

			<div class="xd-ga-guide">
				<p><strong>XD Analytics là công cụ tích hợp số liệu từ Google Analytics vào website</strong> — plugin đọc và hiển thị lại dữ liệu, chứ không tự thu thập số liệu truy cập.</p>
				<p style="margin-bottom:0;">Vì vậy website cần được cài Google Analytics trước thì các báo cáo mới có dữ liệu. Nếu website chưa cài, bạn làm theo hướng dẫn bên dưới (chỉ mất khoảng 10 phút, làm một lần duy nhất).</p>

				<details style="margin-top:14px;">
					<summary>📘 Hướng dẫn nhanh: Cài Google Analytics lên website</summary>

					<ol class="xd-ga-steps">
						<li>
							<h4>Tạo tài khoản Google Analytics</h4>
							<p>Truy cập <a href="https://analytics.google.com/" target="_blank" rel="noopener">analytics.google.com</a> và đăng nhập bằng tài khoản Google của bạn.</p>
							<ul>
								<li>Bấm <strong>Bắt đầu đo lường</strong> (hoặc <strong>Quản trị → Tạo → Tài khoản</strong> nếu đã có tài khoản sẵn).</li>
								<li>Đặt tên tài khoản (thường là tên doanh nghiệp), rồi bấm <strong>Tiếp theo</strong>.</li>
							</ul>
						</li>

						<li>
							<h4>Tạo thuộc tính (Property) cho website</h4>
							<ul>
								<li>Đặt tên thuộc tính, chọn múi giờ <strong>Việt Nam</strong> và đơn vị tiền tệ <strong>VND</strong>.</li>
								<li>Điền thông tin doanh nghiệp theo gợi ý của Google, rồi bấm <strong>Tạo</strong> và chấp nhận điều khoản.</li>
							</ul>
						</li>

						<li>
							<h4>Tạo luồng dữ liệu (Data Stream) và lấy mã nhúng</h4>
							<ul>
								<li>Chọn nền tảng <strong>Web</strong>.</li>
								<li>Nhập địa chỉ website (VD: <code><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>) và đặt tên luồng, rồi bấm <strong>Tạo luồng</strong>.</li>
								<li>Màn hình tiếp theo sẽ hiện <strong>Mã đo lường</strong> dạng <code>G-XXXXXXXXXX</code> — ghi lại mã này.</li>
								<li>Bấm <strong>Xem hướng dẫn cài đặt thẻ</strong> → chọn <strong>Tự cài đặt</strong> → copy toàn bộ đoạn mã <code>&lt;script&gt;...&lt;/script&gt;</code> hiện ra.</li>
							</ul>
						</li>

						<li>
							<h4>Chèn mã vào website</h4>
							<p>Website của bạn đã được cài sẵn plugin <strong>WPCode (Code Snippets)</strong> để làm việc này:</p>
							<ul>
								<li>Vào menu <strong>Code Snippets → Header &amp; Footer</strong> ở thanh bên trái trang quản trị.</li>
								<li>Dán đoạn mã vừa copy vào ô <strong>Header</strong>.</li>
								<li>Bấm <strong>Lưu thay đổi</strong>.</li>
							</ul>
							<div class="xd-ga-note">Chỉ dán vào ô <strong>Header</strong> và dán <strong>một lần duy nhất</strong>. Nếu dán trùng nhiều nơi, số liệu sẽ bị đếm gấp đôi.</div>
						</li>

						<li>
							<h4>Kiểm tra đã hoạt động chưa</h4>
							<ul>
								<li>Mở website ở một tab khác (tốt nhất là dùng cửa sổ ẩn danh).</li>
								<li>Quay lại Google Analytics, vào <strong>Báo cáo → Thời gian thực</strong>.</li>
								<li>Nếu thấy có người đang truy cập, nghĩa là đã cài đặt thành công.</li>
							</ul>
						</li>

						<li>
							<h4>Kết nối vào XD Analytics</h4>
							<p>Sau khi Google Analytics đã chạy, điền <strong>Property ID</strong> và <strong>Service Account JSON</strong> ở phần bên dưới để plugin đọc được báo cáo và hiển thị ngay trong trang quản trị.</p>
							<div class="xd-ga-note">Google Analytics cần khoảng 24–48 giờ để có đủ dữ liệu cho các báo cáo chi tiết. Trong thời gian đầu, một số bảng có thể còn trống — đây là điều bình thường.</div>
						</li>
					</ol>
				</details>
			</div>

			<?php if ( isset( $_GET['synced'] ) ) : ?>
				<div class="notice notice-success"><p>Đã đồng bộ lượt xem từ GA4 vào cột "Lượt xem" cho các loại nội dung đã chọn. Vào Bài viết / CPT tương ứng để xem.</p></div>
			<?php endif; ?>

			<div class="notice notice-info" style="padding:12px;">
				<p><strong>Cột "Lượt xem"</strong> tự động đồng bộ mỗi 6 tiếng qua WP-Cron. Bạn cũng có thể đồng bộ ngay thủ công:</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'vinhxd_ga4_manual_sync' ); ?>
					<input type="hidden" name="action" value="vinhxd_ga4_manual_sync" />
					<?php submit_button( 'Đồng bộ lượt xem ngay', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'vinhxd_ga4_save_settings', 'vinhxd_ga4_settings_nonce' ); ?>

				<h2 class="title">Cấu hình chung</h2>
				<table class="form-table">
					<tr>
						<th><label for="measurement_id">Measurement ID (G-XXXXXXX)</label></th>
						<td>
							<input type="text" name="measurement_id" id="measurement_id" class="regular-text" value="<?php echo $measurement_id; ?>" placeholder="G-XXXXXXX" />
							<p class="description">Dùng để tắt tracking cho Admin/Editor (không dùng để chèn gtag.js vì bạn đã nhúng sẵn qua code snippet).</p>
						</td>
					</tr>
					<tr>
						<th><label>Loại trừ tracking Admin/Editor</label></th>
						<td>
							<label><input type="checkbox" name="exclude_admin" value="1" <?php checked( $exclude_admin ); ?> /> Không tracking khi tài khoản đăng nhập có quyền Administrator hoặc Editor</label>
						</td>
					</tr>
					<tr>
						<th><label>Loại trừ Bot / Crawler</label></th>
						<td>
							<label><input type="checkbox" name="exclude_bots" value="1" <?php checked( $exclude_bots ); ?> /> Không ghi vào Nhật ký chuyển đổi nếu là bot/crawler</label>
							<p class="description">Nhận diện qua User-Agent: Googlebot, Bingbot, Ahrefs, Semrush, các công cụ đo tốc độ, script tự động... Chỉ ảnh hưởng tới nhật ký nội bộ, không ảnh hưởng số liệu Google Analytics (GA4 tự lọc bot riêng). Riêng <strong>Google AdsBot</strong> và <strong>Chrome Prefetch Proxy</strong> (googlezip.net - Chrome tự tải trước trang khi xem kết quả tìm kiếm) được tự động nhận diện và loại trừ, không cần cấu hình gì thêm.</p>
						</td>
					</tr>
					<tr>
						<th><label for="manual_ip_exclude">Danh sách IP loại trừ thủ công</label></th>
						<td>
							<textarea name="manual_ip_exclude" id="manual_ip_exclude" rows="5" class="large-text code" placeholder="VD:&#10;123.45.67.89&#10;123.45.67.0/24"><?php echo esc_textarea( $manual_ip_exclude ); ?></textarea>
							<p class="description">Mỗi dòng 1 địa chỉ IP hoặc 1 dải CIDR. Các IP trong danh sách này sẽ không được ghi vào Nhật ký chuyển đổi (áp dụng cho mọi loại: Visit, Call, Zalo, Form, Click). Dùng khi bạn muốn tự loại 1 IP/dải cụ thể ngoài các trường hợp tự động ở trên - VD IP văn phòng của chính bạn, hoặc 1 dịch vụ nào đó bạn phát hiện gây nhiễu log mà chưa được tự động nhận diện.</p>
						</td>
					</tr>
					<tr>
						<th><label for="trusted_proxies">Proxy tin cậy (nâng cao)</label></th>
						<td>
							<div style="background:#f6f7f7; border-radius:4px; padding:10px 14px; margin-bottom:8px; font-size:13px;">
								IP của bạn mà plugin nhận diện: <strong><code><?php echo esc_html( $detected_ip ?: '(không xác định)' ); ?></code></strong><br>
								<span style="color:#646970;">REMOTE_ADDR: <code><?php echo esc_html( $remote_addr ); ?></code><?php if ( $xff ) : ?> · X-Forwarded-For: <code><?php echo esc_html( $xff ); ?></code><?php endif; ?></span>
							</div>
							<textarea name="trusted_proxies" id="trusted_proxies" rows="3" class="large-text code" placeholder="VD:&#10;203.0.113.10&#10;203.0.113.0/24"><?php echo esc_textarea( $trusted_proxies ); ?></textarea>
							<p class="description">Để chống giả mạo IP, plugin chỉ tin header <code>CF-Connecting-IP</code> khi request đến từ Cloudflare, và chỉ tin <code>X-Forwarded-For</code> khi request đến từ IP nội bộ (reverse proxy cùng máy chủ) hoặc IP khai báo ở đây. <strong>Thường để trống.</strong> Chỉ điền khi IP nhận diện phía trên KHÁC IP thật của bạn (tra ở <a href="https://whatismyipaddress.com/" target="_blank" rel="noopener">whatismyipaddress.com</a>) - khi đó điền IP REMOTE_ADDR của load balancer/CDN mà hosting đang dùng.</p>
						</td>
					</tr>
					<tr>
						<th><label>IP Anonymization</label></th>
						<td>
							<p class="description">GA4 (khác Universal Analytics cũ) <strong>mặc định đã ẩn/không lưu IP</strong> cho mọi property, Google không cho tắt tính năng này nữa nên không cần bật/tắt thủ công.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Kết nối GA4 Data API (để hiển thị báo cáo ở Tổng quan)</h2>
				<table class="form-table">
					<tr>
						<th><label for="property_id">GA4 Property ID</label></th>
						<td>
							<input type="text" name="property_id" id="property_id" class="regular-text" value="<?php echo $property_id; ?>" placeholder="VD: 123456789" />
							<p class="description">Lấy tại GA4 &rarr; Admin &rarr; Property Settings (là số, khác Measurement ID).</p>
						</td>
					</tr>
					<tr>
						<th><label for="service_account_json">Service Account JSON</label></th>
						<td>
							<textarea name="service_account_json" id="service_account_json" rows="8" class="large-text code" placeholder='{"client_email": "...", "private_key": "..."}'><?php echo $sa_json; ?></textarea>
							<p class="description">
								Các bước lấy file này:<br>
								1. Vào <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> → tạo project (nếu chưa có) → bật <strong>Google Analytics Data API</strong>.<br>
								2. Vào IAM &amp; Admin → Service Accounts → Create Service Account → tạo Key dạng JSON → tải file JSON về.<br>
								3. Mở file JSON, copy toàn bộ nội dung dán vào ô trên.<br>
								4. Vào GA4 &rarr; Admin &rarr; Property Access Management → Add user → dán <strong>client_email</strong> có trong file JSON, cấp quyền <strong>Viewer</strong>.
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Cột "Lượt xem" áp dụng cho loại nội dung nào?</h2>
				<table class="form-table">
					<tr>
						<th><label>Chọn Post Type</label></th>
						<td>
							<?php
							$selected_types = Vinhxd_GA4_Post_Views::get_post_types();
							$public_types = get_post_types( array( 'public' => true ), 'objects' );
							unset( $public_types['attachment'] );
							foreach ( $public_types as $slug => $obj ) :
							?>
								<label style="display:block; margin-bottom:4px;">
									<input type="checkbox" name="view_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_types, true ) ); ?> />
									<?php echo esc_html( $obj->labels->name ); ?> <code><?php echo esc_html( $slug ); ?></code>
								</label>
							<?php endforeach; ?>
							<p class="description">Web nào không có loại nội dung tương ứng thì đơn giản là không tick, không ảnh hưởng gì. Sau khi đổi lựa chọn, bấm "Đồng bộ lượt xem ngay" bên trên để cập nhật lại.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>

			<h2 class="title">Shortcode có sẵn</h2>
			<table class="widefat" style="max-width:900px;">
				<tbody>
					<tr>
						<td style="width:220px;"><code>[post_views_xd]</code></td>
						<td>Hiện lượt xem (toàn thời gian) của bài đang xem. Thuộc tính: <code>post_id</code>, <code>label</code> (mặc định "lượt xem"), <code>show_label</code> (yes/no), <code>class</code>.</td>
					</tr>
					<tr>
						<td><code>[top_posts_xd]</code></td>
						<td>
							Danh sách bài xem nhiều nhất. Thuộc tính: <code>post_type</code> (mặc định "post", nhiều loại cách nhau dấu phẩy), <code>count</code> (mặc định 5), <code>taxonomy</code>, <code>term</code>, <code>show_views</code> (yes/no).<br>
							VD: <code>[top_posts_xd post_type="xe" count="10"]</code>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="description">Lưu ý: cả 2 shortcode đọc dữ liệu đã đồng bộ sẵn (không gọi GA4 lúc tải trang), nên post type dùng trong shortcode cần được tick ở mục "Cột Lượt xem áp dụng cho loại nội dung nào?" phía trên và đã từng đồng bộ ít nhất 1 lần.</p>
		</div>
		<?php
	}
}
