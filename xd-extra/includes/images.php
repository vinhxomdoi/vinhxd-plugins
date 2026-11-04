<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * Tắt / thêm kích thước ảnh
 * ---------------------------------------------------------------- */
add_filter( 'intermediate_image_sizes_advanced', function ( $sizes ) {
	foreach ( (array) get_option( 'xd_disabled_image_sizes', [] ) as $size ) unset( $sizes[ $size ] );
	return $sizes;
} );

add_action( 'init', function () {
	foreach ( (array) get_option( 'xd_custom_image_sizes', [] ) as $s ) {
		if ( ! empty( $s['name'] ) && ! empty( $s['width'] ) && ! empty( $s['height'] ) ) {
			add_image_size( $s['name'], (int) $s['width'], (int) $s['height'], ! empty( $s['crop'] ) );
		}
	}
} );

/* ------------------------------------------------------------------
 * Nén & đổi WebP khi upload
 * ---------------------------------------------------------------- */
function xd_webp_on() {
	return get_option( 'xd_convert_webp', '0' ) === '1';
}

// Plugin tự resize theo "Giới hạn kích thước", không cần WP tạo thêm bản -scaled.
add_filter( 'big_image_size_threshold', function ( $threshold ) {
	return xd_webp_on() ? false : $threshold;
} );

add_filter( 'wp_handle_upload',   'xd_process_image_upload', 10, 2 );
add_filter( 'wp_handle_sideload', 'xd_process_image_upload', 10, 2 );

function xd_process_image_upload( $upload, $context = null ) {
	if ( ! xd_webp_on() || empty( $upload['type'] ) || empty( $upload['file'] ) ) return $upload;
	if ( ! in_array( $upload['type'], [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) return $upload;

	$file = $upload['file'];
	if ( ! file_exists( $file ) ) return $upload;
	if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) return $upload;

	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) return $upload;

	$threshold = max( 400, (int) get_option( 'xd_img_threshold', 1680 ) );
	$quality   = min( 100, max( 1, (int) get_option( 'xd_img_quality', 82 ) ) );
	$size      = $editor->get_size();
	$resized   = false;
	if ( ! empty( $size['width'] ) && max( (int) $size['width'], (int) $size['height'] ) > $threshold ) {
		if ( is_wp_error( $editor->resize( $threshold, $threshold, false ) ) ) return $upload;
		$resized = true;
	}

	// Ảnh vốn là WebP và không cần thu nhỏ: giữ nguyên, tránh nén lại làm giảm chất lượng.
	if ( $upload['type'] === 'image/webp' && ! $resized ) return $upload;

	$editor->set_quality( $quality );
	$dir = dirname( $file );

	if ( $upload['type'] === 'image/webp' ) {
		$editor->save( $file, 'image/webp' ); // thu nhỏ tại chỗ, lỗi thì giữ file gốc
		return $upload;
	}

	// Tên file .webp không trùng file có sẵn (tránh ghi đè ảnh cũ cùng tên).
	$new_name = pathinfo( $file, PATHINFO_FILENAME ) . '.webp';
	if ( file_exists( trailingslashit( $dir ) . $new_name ) ) $new_name = wp_unique_filename( $dir, $new_name );
	$new_path = trailingslashit( $dir ) . $new_name;
	$saved    = $editor->save( $new_path, 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) return $upload;

	if ( $saved['path'] !== $file ) wp_delete_file( $file );

	$upload['file'] = $saved['path'];
	$upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . wp_basename( $saved['path'] );
	$upload['type'] = 'image/webp';
	return $upload;
}

add_filter( 'image_editor_output_format', function ( $formats ) {
	if ( ! xd_webp_on() ) return $formats;
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
} );

add_filter( 'wp_editor_set_quality', function ( $quality ) {
	return xd_webp_on() ? min( 100, max( 1, (int) get_option( 'xd_img_quality', 82 ) ) ) : $quality;
} );

/* ------------------------------------------------------------------
 * Trang quản trị: XD Extra → Kích Thước Ảnh
 * ---------------------------------------------------------------- */
function xd_image_sizes_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) return null;

	// Xóa kích thước: link GET có nonce (không dùng form lồng nhau như bản cũ).
	if ( ! empty( $_GET['xd_del_size'] ) ) {
		check_admin_referer( 'xd_del_size' );
		$del    = sanitize_key( wp_unslash( $_GET['xd_del_size'] ) );
		$custom = array_values( array_filter( (array) get_option( 'xd_custom_image_sizes', [] ), function ( $s ) use ( $del ) { return $s['name'] !== $del; } ) );
		update_option( 'xd_custom_image_sizes', $custom );
		remove_image_size( $del ); // cập nhật danh sách ngay trong lần tải này
		return 'size_deleted';
	}

	if ( empty( $_POST['xd_img_action'] ) ) return null;
	check_admin_referer( 'xd_image_sizes_nonce' );
	$action = sanitize_key( wp_unslash( $_POST['xd_img_action'] ) );
	if ( $action === 'compress' ) {
		update_option( 'xd_convert_webp', isset( $_POST['xd_convert_webp'] ) ? '1' : '0' );
		update_option( 'xd_img_quality', min( 100, max( 1, (int) ( $_POST['xd_img_quality'] ?? 82 ) ) ) );
		update_option( 'xd_img_threshold', min( 5000, max( 400, (int) ( $_POST['xd_img_threshold'] ?? 1680 ) ) ) );
		return 'saved';
	}
	if ( $action === 'sizes' ) {
		$sizes = isset( $_POST['xd_disable_sizes'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['xd_disable_sizes'] ) ) : [];
		update_option( 'xd_disabled_image_sizes', $sizes );
		return 'saved';
	}
	if ( $action === 'add' ) {
		$name = sanitize_key( wp_unslash( $_POST['xd_size_name'] ?? '' ) );
		$w    = (int) ( $_POST['xd_size_width'] ?? 0 );
		$h    = (int) ( $_POST['xd_size_height'] ?? 0 );
		if ( ! $name || $w <= 0 || $h <= 0 ) return 'invalid';
		if ( in_array( $name, get_intermediate_image_sizes(), true ) ) return 'duplicate';
		$custom   = (array) get_option( 'xd_custom_image_sizes', [] );
		$custom[] = [ 'name' => $name, 'width' => $w, 'height' => $h, 'crop' => empty( $_POST['xd_size_crop'] ) ? 0 : 1 ];
		update_option( 'xd_custom_image_sizes', $custom );
		add_image_size( $name, $w, $h, ! empty( $_POST['xd_size_crop'] ) );
		return 'size_added';
	}
	return null;
}

function xd_image_sizes_page() {
	$msg       = xd_image_sizes_handle_save();
	$disabled  = (array) get_option( 'xd_disabled_image_sizes', [] );
	$custom    = (array) get_option( 'xd_custom_image_sizes', [] );
	$custom_names = wp_list_pluck( $custom, 'name' );
	$webp_on   = get_option( 'xd_convert_webp', '0' );
	$quality   = (int) get_option( 'xd_img_quality', 82 );
	$threshold = (int) get_option( 'xd_img_threshold', 1680 );
	$sizes     = wp_get_registered_image_subsizes();
	$card      = 'background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;';
	$td        = 'padding:7px 10px;border-bottom:1px solid #f0f0f0;';
	$notices   = [
		'saved'        => [ 'success', 'Đã lưu!' ],
		'size_added'   => [ 'success', 'Đã thêm kích thước mới!' ],
		'size_deleted' => [ 'success', 'Đã xóa!' ],
		'duplicate'    => [ 'error', 'Tên kích thước đã tồn tại!' ],
		'invalid'      => [ 'error', 'Vui lòng nhập tên, chiều rộng và chiều cao hợp lệ.' ],
	];
	?>
	<div class="wrap"><h1>Quản Lý Kích Thước Ảnh</h1>
	<?php if ( $msg && isset( $notices[ $msg ] ) ) printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notices[ $msg ][0] ), esc_html( $notices[ $msg ][1] ) ); ?>
	<p style="background:#fff8e1;border-left:4px solid #ffc107;padding:10px 14px;margin-bottom:20px;">Tắt kích thước chỉ áp dụng với ảnh upload <strong>mới</strong>. Ảnh cũ cần dùng <em>Regenerate Thumbnails</em>.</p>

	<div style="<?php echo esc_attr( $card ); ?>margin-bottom:20px;max-width:860px;">
		<h2 style="font-size:15px;margin:0 0 4px;">Nén &amp; Convert WebP khi Upload</h2>
		<p style="font-size:12px;color:#888;margin:0 0 14px;">Áp dụng cho ảnh JPEG/PNG upload mới. GIF bỏ qua. Ảnh WebP chỉ được nén lại khi cần thu nhỏ.<?php echo wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ? '' : ' <strong style="color:#d63638;">Máy chủ chưa hỗ trợ WebP (Imagick/GD).</strong>'; ?></p>
		<form method="post"><?php wp_nonce_field( 'xd_image_sizes_nonce' ); ?>
			<input type="hidden" name="xd_img_action" value="compress">
			<table class="form-table" style="margin:0;">
				<tr><th style="width:200px;padding:8px 0;">Bật Convert WebP</th>
					<td><label><input type="checkbox" name="xd_convert_webp" value="1" <?php checked( '1', $webp_on ); ?>> Tự động convert JPEG/PNG sang <strong>WebP</strong> khi upload</label></td></tr>
				<tr><th style="padding:8px 0;">Chất lượng nén</th>
					<td><input type="number" name="xd_img_quality" value="<?php echo esc_attr( $quality ); ?>" min="1" max="100" style="width:70px;"> / 100
						<p class="description">Mặc định 82. Càng thấp file càng nhỏ, chất lượng giảm.</p></td></tr>
				<tr><th style="padding:8px 0;">Giới hạn kích thước (px)</th>
					<td><input type="number" name="xd_img_threshold" value="<?php echo esc_attr( $threshold ); ?>" min="400" max="5000" style="width:90px;"> px
						<p class="description">Cạnh dài tối đa. Ảnh lớn hơn sẽ tự resize xuống. Mặc định 1680px.</p></td></tr>
			</table>
			<?php submit_button( 'Lưu cài đặt nén', 'primary', 'submit', false, [ 'style' => 'margin-top:12px;' ] ); ?>
		</form>
	</div>

	<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
	<div style="flex:2;min-width:400px;<?php echo esc_attr( $card ); ?>">
		<h2 style="font-size:15px;margin:0 0 16px;">Danh Sách Kích Thước</h2>
		<form method="post"><?php wp_nonce_field( 'xd_image_sizes_nonce' ); ?>
		<input type="hidden" name="xd_img_action" value="sizes">
		<table style="width:100%;border-collapse:collapse;font-size:13px;">
			<thead><tr style="background:#f8f9fa;"><th style="padding:8px 10px;text-align:left;">Tắt</th><th style="text-align:left;">Tên</th><th>W</th><th>H</th><th>Crop</th><th>Loại</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $sizes as $name => $info ) :
				$is_dis = in_array( $name, $disabled, true );
				$type   = in_array( $name, [ 'thumbnail', 'medium', 'medium_large', 'large' ], true ) ? 'wp' : ( in_array( $name, $custom_names, true ) ? 'xd' : 'theme' );
				$badges = [ 'wp' => [ '#e3f2fd', '#1565c0', 'WP' ], 'xd' => [ '#e8f5e9', '#2e7d32', 'XD' ], 'theme' => [ '#f3e5f5', '#6a1b9a', 'Theme' ] ];
				$bd     = $badges[ $type ];
			?>
			<tr style="<?php echo $is_dis ? 'opacity:.45;' : ''; ?>">
				<td style="text-align:center;<?php echo esc_attr( $td ); ?>"><input type="checkbox" name="xd_disable_sizes[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( $is_dis ); ?>></td>
				<td style="<?php echo esc_attr( $td ); ?>"><code><?php echo esc_html( $name ); ?></code></td>
				<td style="text-align:center;<?php echo esc_attr( $td ); ?>"><?php echo $info['width'] ? (int) $info['width'] : '∞'; ?></td>
				<td style="text-align:center;<?php echo esc_attr( $td ); ?>"><?php echo $info['height'] ? (int) $info['height'] : '∞'; ?></td>
				<td style="text-align:center;<?php echo esc_attr( $td ); ?>"><?php echo $info['crop'] ? 'crop' : '—'; ?></td>
				<td style="text-align:center;<?php echo esc_attr( $td ); ?>"><span style="background:<?php echo esc_attr( $bd[0] ); ?>;color:<?php echo esc_attr( $bd[1] ); ?>;padding:2px 7px;border-radius:10px;font-size:11px;"><?php echo esc_html( $bd[2] ); ?></span></td>
				<td style="<?php echo esc_attr( $td ); ?>">
					<?php if ( $type === 'xd' ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=xd-image-sizes&xd_del_size=' . rawurlencode( $name ) ), 'xd_del_size' ) ); ?>" style="color:#d63638;" onclick="return confirm('Xóa kích thước <?php echo esc_js( $name ); ?>?')">Xóa</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( 'Lưu danh sách', 'primary', 'submit', false, [ 'style' => 'margin-top:16px;' ] ); ?>
		</form>
	</div>
	<div style="flex:1;min-width:280px;<?php echo esc_attr( $card ); ?>">
		<h2 style="font-size:15px;margin:0 0 16px;">Thêm Kích Thước Mới</h2>
		<form method="post"><?php wp_nonce_field( 'xd_image_sizes_nonce' ); ?>
		<input type="hidden" name="xd_img_action" value="add">
		<table class="form-table" style="margin:0;">
			<tr><th style="padding:8px 0;width:110px;">Tên (slug)</th><td><input type="text" name="xd_size_name" class="regular-text" placeholder="card-thumb" required></td></tr>
			<tr><th style="padding:8px 0;">Width (px)</th><td><input type="number" name="xd_size_width" class="small-text" min="1" placeholder="400" required></td></tr>
			<tr><th style="padding:8px 0;">Height (px)</th><td><input type="number" name="xd_size_height" class="small-text" min="1" placeholder="300" required></td></tr>
			<tr><th style="padding:8px 0;">Crop cứng</th><td><label><input type="checkbox" name="xd_size_crop" value="1"> Bật crop</label></td></tr>
		</table>
		<?php submit_button( 'Thêm', 'primary', 'submit', false, [ 'style' => 'margin-top:8px;' ] ); ?>
		</form>
	</div>
	</div></div>
	<?php
}
