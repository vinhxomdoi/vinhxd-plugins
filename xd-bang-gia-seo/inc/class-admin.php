<?php
/**
 * Trang cài đặt "Bảng Giá SEO" nằm ngay trong menu Xe (cạnh Cài đặt của VinhXD Ô tô).
 */

defined( 'ABSPATH' ) || exit;

final class XD_BGSEO_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_xd_bgseo_save', array( __CLASS__, 'save' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=xe',
			'Bảng Giá Xe SEO',
			'Bảng Giá SEO',
			'manage_options',
			'xd_banggia_seo',
			array( __CLASS__, 'render' )
		);
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'xd_bgseo_save_nonce' );

		$old = XD_BGSEO_Calc::settings();
		$mode = sanitize_key( $_POST['mode'] ?? 'tong' );
		if ( ! in_array( $mode, array( 'tong', 'max', 'mac_dinh' ), true ) ) $mode = 'tong';

		$orderby = sanitize_key( $_POST['orderby'] ?? 'price_asc' );
		if ( ! in_array( $orderby, array( 'price_asc', 'price_desc', 'menu_order', 'title' ), true ) ) $orderby = 'price_asc';

		$new = array(
			'mode'             => $mode,
			'exclude_nhom'     => sanitize_text_field( wp_unslash( $_POST['exclude_nhom'] ?? '' ) ),
			'skip_ended_title' => ! empty( $_POST['skip_ended_title'] ) ? '1' : '0',
			'orderby'          => $orderby,
			'show_img'         => ! empty( $_POST['show_img'] ) ? '1' : '0',
			'show_uudai'       => ! empty( $_POST['show_uudai'] ) ? '1' : '0',
			'show_after'       => ! empty( $_POST['show_after'] ) ? '1' : '0',
			'show_lanbanh'     => ! empty( $_POST['show_lanbanh'] ) ? '1' : '0',
			'show_schema'      => ! empty( $_POST['show_schema'] ) ? '1' : '0',
			'show_note'        => ! empty( $_POST['show_note'] ) ? '1' : '0',
			'show_detail_tip'  => ! empty( $_POST['show_detail_tip'] ) ? '1' : '0',
			'settings_ver'     => (int) ( $old['settings_ver'] ?? 1 ) + 1,
		);

		update_option( XD_BGSEO_OPT, $new, true );
		if ( function_exists( 'vinhxd_cache_flush' ) ) vinhxd_cache_flush();
		if ( function_exists( 'litespeed_purge_all' ) ) litespeed_purge_all();
		do_action( 'litespeed_purge_all' );

		wp_safe_redirect( add_query_arg( array( 'post_type' => 'xe', 'page' => 'xd_banggia_seo', 'updated' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function render() {
		$s = XD_BGSEO_Calc::settings();
		?>
		<div class="wrap">
			<h1>XD Bảng Giá Xe SEO (Tương thích VinhXD Ô tô)</h1>
			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt và làm mới cache bảng giá.</p></div>
			<?php endif; ?>

			<p>Plugin tự động lấy giá từng phiên bản, khuyến mãi đang chạy và biểu phí lăn bánh trực tiếp từ <strong>VinhXD Ô tô</strong>. Khi Hub đồng bộ giá hoặc bạn sửa xe/khuyến mãi, bảng giá ngoài trang sẽ tự cập nhật.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:860px;background:#fff;padding:20px 24px;border:1px solid #dcdcde;border-radius:8px;margin-top:16px;">
				<input type="hidden" name="action" value="xd_bgseo_save">
				<?php wp_nonce_field( 'xd_bgseo_save_nonce' ); ?>

				<h2 style="margin-top:0;">1. Công thức tính "Ưu đãi lên đến"</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="xd_mode">Cách tính ưu đãi</label></th>
						<td>
							<select name="mode" id="xd_mode">
								<option value="tong" <?php selected( $s['mode'], 'tong' ); ?>>Tổng ưu đãi tối đa theo nhóm (cùng nhóm lấy cao nhất, khác nhóm cộng dồn + % sau ưu đãi)</option>
								<option value="max" <?php selected( $s['mode'], 'max' ); ?>>Cao nhất giữa 1 chương trình &amp; nhóm mặc định (giống thẻ xe VinhXD Ô tô 3.10.3)</option>
								<option value="mac_dinh" <?php selected( $s['mode'], 'mac_dinh' ); ?>>Chỉ tính các ưu đãi tick sẵn mặc định</option>
							</select>
							<p class="description">Mọi công thức đều tự lọc theo phiên bản (<code>_xdh_scope</code>), mức riêng từng xe (<code>muc-rieng</code>) và tự loại bỏ chương trình đã hết hạn.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="xd_exclude_nhom">Loại trừ nhóm ưu đãi</label></th>
						<td>
							<input type="text" name="exclude_nhom" id="xd_exclude_nhom" class="regular-text" value="<?php echo esc_attr( $s['exclude_nhom'] ); ?>" placeholder="VD: congvu">
							<p class="description">Nhập mã <code>nhom</code> muốn loại khỏi bảng giá (ngăn cách bằng dấu phẩy). Để trống nếu muốn dùng toàn bộ nhóm giống trang chi tiết xe.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Tự lọc KM hết hạn không có ngày</th>
						<td>
							<label><input type="checkbox" name="skip_ended_title" value="1" <?php checked( $s['skip_ended_title'], '1' ); ?>> Tự bỏ qua chương trình có chữ <em>"đã hết"</em> / <em>"hết hạn"</em> trong tên</label>
						</td>
					</tr>
				</table>

				<h2>2. Hiển thị mặc định</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="xd_orderby">Thứ tự dòng xe</label></th>
						<td>
							<select name="orderby" id="xd_orderby">
								<option value="price_asc" <?php selected( $s['orderby'], 'price_asc' ); ?>>Giá tăng dần (thấp đến cao)</option>
								<option value="price_desc" <?php selected( $s['orderby'], 'price_desc' ); ?>>Giá giảm dần (cao đến thấp)</option>
								<option value="menu_order" <?php selected( $s['orderby'], 'menu_order' ); ?>>Theo Thứ tự menu (Menu Order)</option>
								<option value="title" <?php selected( $s['orderby'], 'title' ); ?>>Theo tên A–Z</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Các cột &amp; tùy chọn</th>
						<td>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_img" value="1" <?php checked( $s['show_img'], '1' ); ?>> Hiện ảnh thu nhỏ mẫu xe</label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_uudai" value="1" <?php checked( $s['show_uudai'], '1' ); ?>> Hiện cột <strong>Ưu đãi lên đến</strong></label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_after" value="1" <?php checked( $s['show_after'], '1' ); ?>> Hiện cột <strong>Giá sau ưu đãi</strong></label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_lanbanh" value="1" <?php checked( $s['show_lanbanh'], '1' ); ?>> Hiện thêm cột <strong>Lăn bánh tạm tính</strong></label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_detail_tip" value="1" <?php checked( $s['show_detail_tip'], '1' ); ?>> Hiện ghi chú chi tiết các gói ưu đãi khi rê chuột vào mức giảm</label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="show_schema" value="1" <?php checked( $s['show_schema'], '1' ); ?>> Xuất Schema <code>ItemList / Product / Offer</code> chuẩn Google SEO</label>
							<label style="display:block;"><input type="checkbox" name="show_note" value="1" <?php checked( $s['show_note'], '1' ); ?>> Hiện dòng ghi chú &amp; ngày tự cập nhật bên dưới bảng</label>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Lưu cài đặt & Làm mới cache' ); ?>
			</form>

			<div style="max-width:860px;background:#fff;padding:20px 24px;border:1px solid #dcdcde;border-radius:8px;margin-top:20px;">
				<h2 style="margin-top:0;">3. Danh sách Shortcode sử dụng</h2>
				<table class="widefat striped">
					<thead><tr><th style="width:45%;">Shortcode</th><th>Chức năng</th></tr></thead>
					<tbody>
						<tr>
							<td><code>[xd_bang_gia_seo group_by_dong="yes"]</code></td>
							<td>Bảng giá toàn bộ xe chia theo từng nhóm dòng xe (Xe Gia Đình, Xe Dịch Vụ...), tự tính ưu đãi lên đến &amp; giá sau ưu đãi cho từng phiên bản.</td>
						</tr>
						<tr>
							<td><code>[xd_bang_gia_seo dong_xe="xe-vinfast" title="Bảng giá xe gia đình tháng {thang}"]</code></td>
							<td>Bảng giá lọc riêng 1 nhóm dòng xe theo slug, tự điền tháng/năm hiện tại.</td>
						</tr>
						<tr>
							<td><code>[xd_bang_gia_seo mode="max" show_lanbanh="yes"]</code></td>
							<td>Ghi đè cách tính ưu đãi hoặc bật thêm cột giá lăn bánh ngay trong shortcode.</td>
						</tr>
						<tr>
							<td><code>[xd_bang_lan_banh_seo]</code></td>
							<td>Bảng giá lăn bánh tự động theo khu vực đăng ký (Hà Nội / TP.HCM / Tỉnh) lấy đúng biểu phí từ VinhXD Ô tô.</td>
						</tr>
						<tr>
							<td><code>[xd_bang_uu_dai_seo]</code></td>
							<td>Bảng tổng hợp các chương trình khuyến mãi đang chạy, tự động ẩn ưu đãi hết hạn.</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
