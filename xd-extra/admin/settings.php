<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'XD Extra', 'XD Extra', 'manage_options', 'xd-extra-settings', 'xd_extra_settings_page', 'dashicons-admin-generic', 110 );
	add_submenu_page( 'xd-extra-settings', 'Cài đặt', 'Cài đặt', 'manage_options', 'xd-extra-settings', 'xd_extra_settings_page' );
	add_submenu_page( 'xd-extra-settings', 'Nút Liên Hệ', 'Nút Liên Hệ', 'manage_options', 'xd-contact-buttons', 'xd_contact_buttons_page' );
	add_submenu_page( 'xd-extra-settings', 'Kích Thước Ảnh', 'Kích Thước Ảnh', 'manage_options', 'xd-image-sizes', 'xd_image_sizes_page' );
	add_submenu_page( 'xd-extra-settings', 'Design – Màu sắc & Font', 'Design', 'manage_options', 'xd-design', 'xd_design_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook === 'toplevel_page_xd-extra-settings' ) wp_enqueue_media();
} );

add_action( 'admin_init', function () {
	$checkbox = function ( $v ) { return $v === '1' || $v === 1 || $v === true ? '1' : '0'; };
	$int_or_empty = function ( $v ) { $v = trim( (string) $v ); return $v === '' ? '' : (string) max( 0, (int) $v ); };
	$hex = function ( $v ) { return sanitize_hex_color( $v ) ?: ''; };

	register_setting( 'xd_extra_group', 'xd_cs_status',        [ 'sanitize_callback' => $checkbox ] );
	register_setting( 'xd_extra_group', 'xd_cs_content',       [ 'sanitize_callback' => 'wp_kses_post' ] );
	register_setting( 'xd_extra_group', 'xd_totop_btn',        [ 'sanitize_callback' => $checkbox ] );
	register_setting( 'xd_extra_group', 'xd_totop_color',      [ 'sanitize_callback' => $hex ] );
	register_setting( 'xd_extra_group', 'xd_totop_color2',     [ 'sanitize_callback' => $hex ] );
	register_setting( 'xd_extra_group', 'xd_totop_pos',        [ 'sanitize_callback' => function ( $v ) { return $v === 'left' ? 'left' : 'right'; } ] );
	register_setting( 'xd_extra_group', 'xd_totop_bottom',     [ 'sanitize_callback' => function ( $v ) { return (string) max( 0, (int) $v ); } ] );
	register_setting( 'xd_extra_group', 'xd_totop_bottom_m',   [ 'sanitize_callback' => $int_or_empty ] );
	register_setting( 'xd_extra_group', 'xd_favicon_id',       [ 'sanitize_callback' => 'absint' ] );
	register_setting( 'xd_extra_group', 'xd_tpl_single_post',  [ 'sanitize_callback' => $checkbox ] );
	register_setting( 'xd_extra_group', 'xd_tpl_archive_post', [ 'sanitize_callback' => $checkbox ] );
	register_setting( 'xd_extra_group', 'xd_paste_as_text',    [ 'sanitize_callback' => $checkbox ] );
	register_setting( 'xd_extra_group', 'xd_pcss_enabled',     [ 'sanitize_callback' => $checkbox ] );
} );

function xd_extra_settings_page() {
	$box         = 'background:#fff;padding:20px;border:1px solid #ccd0d4;max-width:800px;border-radius:8px;margin-bottom:16px;';
	$favicon_id  = (int) get_option( 'xd_favicon_id', 0 );
	$favicon_url = $favicon_id ? wp_get_attachment_image_url( $favicon_id, 'thumbnail' ) : '';
	$totop_pos   = get_option( 'xd_totop_pos', 'right' );
	?>
	<div class="wrap">
		<h1>Cài đặt XD Extra <span style="font-size:13px;color:#888;font-weight:400;">v<?php echo esc_html( XD_VERSION ); ?></span></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'xd_extra_group' ); ?>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Nút Liên Hệ Cố Định</h2>
				<p class="description">Bật/tắt, màu và vị trí từng nút (Điện thoại, Zalo, Messenger, Nút nhấp nháy) ở trang riêng.</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=xd-contact-buttons' ) ); ?>" class="button">Mở trang Nút Liên Hệ</a>
			</div>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Nút Lên Đầu Trang</h2>
				<table class="form-table">
					<tr><th>Hiển thị</th>
						<td><label><input type="checkbox" name="xd_totop_btn" value="1" <?php checked( '1', get_option( 'xd_totop_btn', '1' ) ); ?>> Hiển thị nút cuộn lên đầu trang</label></td></tr>
					<tr><th>Vị trí</th>
						<td><label><input type="radio" name="xd_totop_pos" value="right" <?php checked( 'right', $totop_pos ); ?>> Góc phải</label>&nbsp;&nbsp;
							<label><input type="radio" name="xd_totop_pos" value="left" <?php checked( 'left', $totop_pos ); ?>> Góc trái</label></td></tr>
					<tr><th>Cách dưới (px)</th>
						<td>Desktop <input type="number" name="xd_totop_bottom" min="0" value="<?php echo esc_attr( get_option( 'xd_totop_bottom', 50 ) ); ?>" style="width:80px;">
							&nbsp;&nbsp;Mobile <input type="number" name="xd_totop_bottom_m" min="0" value="<?php echo esc_attr( get_option( 'xd_totop_bottom_m', '' ) ); ?>" style="width:80px;" placeholder="= desktop"></td></tr>
					<tr><th>Màu</th>
						<td><label><input type="color" name="xd_totop_color" value="<?php echo esc_attr( get_option( 'xd_totop_color', '#238543' ) ?: '#238543' ); ?>" style="width:60px;height:36px;padding:2px;vertical-align:middle;"> Màu ngoài</label>&nbsp;&nbsp;
							<label><input type="color" name="xd_totop_color2" value="<?php echo esc_attr( get_option( 'xd_totop_color2', '#f7c315' ) ?: '#f7c315' ); ?>" style="width:60px;height:36px;padding:2px;vertical-align:middle;"> Màu vòng tròn icon</label></td></tr>
				</table>
			</div>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Favicon</h2>
				<div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
					<div id="xd-favicon-preview" style="width:64px;height:64px;border:2px dashed #ccd0d4;border-radius:6px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;overflow:hidden;">
						<?php if ( $favicon_url ) : ?><img src="<?php echo esc_url( $favicon_url ); ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain;"><?php else : ?><span style="font-size:24px;">🌐</span><?php endif; ?>
					</div>
					<div>
						<input type="hidden" name="xd_favicon_id" id="xd_favicon_id" value="<?php echo esc_attr( $favicon_id ); ?>">
						<button type="button" class="button" id="xd-favicon-select"><?php echo $favicon_id ? 'Đổi ảnh' : 'Chọn ảnh từ thư viện'; ?></button>
						<button type="button" class="button" id="xd-favicon-remove" style="margin-left:6px;color:#d63638;<?php echo $favicon_id ? '' : 'display:none;'; ?>">Xóa</button>
						<p class="description" style="margin-top:8px;">Ảnh vuông, tối thiểu <strong>180×180px</strong> (dùng luôn cho icon iPhone), định dạng <strong>.png / .ico / .svg</strong>.</p>
					</div>
				</div>
			</div>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Templates</h2>
				<p class="description">Template của plugin cho <strong>Bài viết</strong>. CPT (xe, dịch vụ…) do JetThemeCore lo.</p>
				<table class="form-table">
					<tr><th style="width:220px;">Single Bài viết</th>
						<td><label><input type="checkbox" name="xd_tpl_single_post" value="1" <?php checked( '1', get_option( 'xd_tpl_single_post', '1' ) ); ?>> Dùng template bài viết của plugin</label></td></tr>
					<tr><th>Danh mục / Thẻ</th>
						<td><label><input type="checkbox" name="xd_tpl_archive_post" value="1" <?php checked( '1', get_option( 'xd_tpl_archive_post', '1' ) ); ?>> Dùng template lưu trữ của plugin</label></td></tr>
				</table>
			</div>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Tùy Chọn Bổ Sung</h2>
				<table class="form-table">
					<tr><th>Paste as Text</th>
						<td><label><input type="checkbox" name="xd_paste_as_text" value="1" <?php checked( '1', get_option( 'xd_paste_as_text', '0' ) ); ?>> Dán văn bản thuần trong trình soạn thảo cổ điển (bỏ định dạng Word/web)</label></td></tr>
				</table>
				<table class="form-table">
					<tr><th>CSS tùy chỉnh</th>
						<td>
							<label><input type="checkbox" name="xd_pcss_enabled" value="1" <?php checked( '1', get_option( 'xd_pcss_enabled', '1' ) ); ?>> Bật module CSS tùy chỉnh (CSS toàn site + ô "CSS riêng cho trang này" khi sửa bài)</label>
							<?php xd_pcss_status_line(); ?>
						</td></tr>
				</table>
				<p class="description">Chống copy, chặn backup/clone, khoá tên miền… ở trang <a href="<?php echo esc_url( admin_url( 'admin.php?page=xd-hide' ) ); ?>">XD Hide</a>.</p>
			</div>

			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">Chế độ Coming Soon</h2>
				<p class="description">Bật lên sẽ ẩn toàn bộ site với khách (mã 503, không index), tài khoản Quản trị viên vẫn xem bình thường.</p>
				<table class="form-table">
					<tr><th>Bật Coming Soon</th><td><input type="checkbox" name="xd_cs_status" value="1" <?php checked( '1', get_option( 'xd_cs_status' ) ); ?>></td></tr>
					<tr><th>Nội dung hiển thị</th><td><textarea name="xd_cs_content" rows="4" style="width:100%;"><?php echo esc_textarea( get_option( 'xd_cs_content', '' ) ); ?></textarea></td></tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	(function ($) {
		var frame;
		$('#xd-favicon-select').on('click', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({ title: 'Chọn Favicon', button: { text: 'Dùng ảnh này' }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
					$('#xd_favicon_id').val(att.id);
					$('#xd-favicon-preview').empty().append($('<img alt="">').attr('src', url).css({ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }));
					$('#xd-favicon-select').text('Đổi ảnh');
					$('#xd-favicon-remove').show();
				});
			}
			frame.open();
		});
		$('#xd-favicon-remove').on('click', function () {
			$('#xd_favicon_id').val('');
			$('#xd-favicon-preview').html('<span style="font-size:24px;">🌐</span>');
			$('#xd-favicon-select').text('Chọn ảnh từ thư viện');
			$(this).hide();
		});
	})(jQuery);
	</script>
	<?php
}

/** Dòng trạng thái module CSS tùy chỉnh trên trang Cài đặt. */
function xd_pcss_status_line() {
	if ( get_option( 'xd_pcss_enabled', '1' ) !== '1' ) {
		echo '<p class="description">Đang tắt: CSS toàn site và CSS từng trang không được in ra (dữ liệu vẫn giữ nguyên).</p>';
		return;
	}
	if ( defined( 'XD_POST_CSS_LOADED' ) && XD_POST_CSS_LOADED !== 'xd-extra' ) {
		echo '<p class="description" style="color:#b32d2e;">Module đang chạy từ plugin khác (<code>' . esc_html( (string) XD_POST_CSS_LOADED ) . '</code>) vì plugin đó nạp trước; bản của XD Extra đang nhường.</p>';
		return;
	}
	echo '<p class="description">Đang bật. Sửa CSS toàn site ở <a href="' . esc_url( admin_url( 'admin.php?page=xd-custom-css' ) ) . '">Chèn Mã Nhúng → CSS toàn site</a>.</p>';
}
