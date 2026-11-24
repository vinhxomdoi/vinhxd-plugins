<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ==================================================================
 * ADMIN: ẩn plugin / menu, CSS admin
 * ================================================================ */
add_filter( 'all_plugins', function ( $plugins ) {
	foreach ( (array) get_option( 'xd_hidden_plugins', [] ) as $f ) unset( $plugins[ $f ] );
	return $plugins;
} );

add_action( 'admin_menu', function () {
	add_menu_page( 'XD Hide', 'XD Hide', 'manage_options', 'xd-hide', 'xd_security_settings_page', 'dashicons-shield', 99 );
}, 99 );

add_action( 'admin_menu', function () {
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'xd-hide' ) return;
	foreach ( (array) get_option( 'xd_hidden_menus', [] ) as $slug ) {
		remove_menu_page( $slug );
	}
}, 999 );

add_action( 'admin_head', function () {
	$css = (string) get_option( 'xd_admin_css', '' );
	if ( trim( $css ) !== '' ) echo '<style id="xd-custom-admin-css">' . xd_sanitize_css( $css ) . '</style>';
} );

/* ==================================================================
 * TRANG XD HIDE
 * ================================================================ */
function xd_security_save() {
	if ( ! isset( $_POST['xd_save_security'] ) || ! current_user_can( 'manage_options' ) ) return '';
	check_admin_referer( 'xd_security_save' );
	$p = wp_unslash( $_POST );
	update_option( 'xd_hidden_plugins', array_map( 'sanitize_text_field', (array) ( $p['hidden_plugins'] ?? [] ) ) );
	update_option( 'xd_hidden_menus', array_map( 'sanitize_text_field', (array) ( $p['hidden_menus'] ?? [] ) ) );
	update_option( 'xd_admin_css', xd_sanitize_css( $p['custom_css'] ?? '' ) );
	return 'Đã lưu cấu hình.';
}

function xd_security_settings_page() {
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'hide';
	if ( ! in_array( $tab, [ 'hide', 'shortcodes' ], true ) ) $tab = 'hide';
	$base = admin_url( 'admin.php?page=xd-hide' );
	$tabs = [ 'hide' => '🛡️ Ẩn Plugin & Menu', 'shortcodes' => '🧩 Shortcodes' ];

	echo '<div class="wrap"><h1>🛡️ XD Hide</h1><nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
	foreach ( $tabs as $k => $label ) {
		echo '<a href="' . esc_url( $base . '&tab=' . $k ) . '" class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	if ( $tab === 'shortcodes' ) { xd_security_shortcodes_tab(); echo '</div>'; return; }

	$msg = xd_security_save();
	if ( $msg ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';

	if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$all_plugins = get_plugins();
	// Không hiển thị plugin hệ thống đang ở chế độ ẩn
	if ( function_exists( 'xd_guard_hide_connector' ) && xd_guard_hide_connector() && function_exists( 'xd_guard_connector_file' ) ) {
		unset( $all_plugins[ xd_guard_connector_file() ] );
	}
	$hidden_plugins = (array) get_option( 'xd_hidden_plugins', [] );
	$hidden_menus   = (array) get_option( 'xd_hidden_menus', [] );
	$box            = 'background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:6px;';

	global $menu;
	$all_menus = [];
	foreach ( (array) $menu as $item ) {
		if ( empty( $item[0] ) || empty( $item[2] ) ) continue;
		$label = trim( wp_strip_all_tags( preg_replace( '#<span.*?</span>#s', '', $item[0] ) ) );
		if ( $label ) $all_menus[] = [ 'slug' => $item[2], 'label' => $label ];
	}
	?>
	<form method="post"><?php wp_nonce_field( 'xd_security_save' ); ?>
	<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
		<div style="flex:2;min-width:420px;display:flex;flex-direction:column;gap:20px;">
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">1. Ẩn Plugin</h2>
				<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:40px;">Ẩn</th><th>Tên</th><th>File</th></tr></thead><tbody>
				<?php foreach ( $all_plugins as $file => $d ) : ?>
					<tr><td><input type="checkbox" name="hidden_plugins[]" value="<?php echo esc_attr( $file ); ?>"<?php checked( in_array( $file, $hidden_plugins, true ) ); ?>></td><td><strong><?php echo esc_html( $d['Name'] ); ?></strong></td><td><code style="font-size:11px;"><?php echo esc_html( $file ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">2. Ẩn Menu Admin</h2>
				<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:40px;">Ẩn</th><th>Tên</th><th>Slug</th></tr></thead><tbody>
				<?php foreach ( $all_menus as $m ) : ?>
					<tr>
						<td><input type="checkbox" name="hidden_menus[]" value="<?php echo esc_attr( $m['slug'] ); ?>"<?php checked( in_array( $m['slug'], $hidden_menus, true ) ); ?>></td>
						<td><?php echo esc_html( $m['label'] ); ?><?php if ( $m['slug'] === 'xd-hide' ) : ?> <span style="color:#d63638;font-size:11px;">(ẩn xong truy cập trực tiếp qua <code>admin.php?page=xd-hide</code>)</span><?php endif; ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $m['slug'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
		</div>

		<div style="flex:1;min-width:320px;display:flex;flex-direction:column;gap:20px;">
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">3. CSS Admin</h2>
				<textarea name="custom_css" rows="12" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( get_option( 'xd_admin_css', '' ) ); ?></textarea>
			</div>
			<?php submit_button( 'Lưu cấu hình', 'primary large', 'xd_save_security', false, [ 'style' => 'width:100%;' ] ); ?>
		</div>
	</div>
	</form>
	<?php
	echo '</div>';
}

function xd_security_shortcodes_tab() {
	$rows = [
		[ '[xd_lien_he]', 'Khối liên hệ: bản đồ nền + thông tin + form.', 'form_id="4" tieu_de="" mo_ta="" gio="7:30 – 17:30" ban_do="click|luon|tat" form_tieu_de="" form_mo_ta=""' ],
		[ '[xd_news]', '1 bài lớn + danh sách bài nhỏ.', 'bv="4" cat="slug hoặc ID"' ],
		[ '[xd_uu_dai]', 'Khối ưu đãi trong tháng (trường khuyenmai).', 'form_id="3" popup_id="ID JetPopup"' ],
		[ '[vinhxd id="123"]', 'Chèn template Elementor bất kỳ.', 'id' ],
	];
	echo '<table class="wp-list-table widefat fixed striped" style="max-width:1100px;"><thead><tr><th style="width:170px;">Shortcode</th><th>Mô tả</th><th>Tham số</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><code>' . esc_html( $r[0] ) . '</code></td><td>' . esc_html( $r[1] ) . '</td><td><code style="font-size:11px;">' . esc_html( $r[2] ) . '</code></td></tr>';
	}
	echo '</tbody></table><p class="description">Dữ liệu (tên, địa chỉ, SĐT, Zalo, email, giờ làm việc…) lấy từ Option page <code>thong-tin</code> của JetEngine.</p>';
}
