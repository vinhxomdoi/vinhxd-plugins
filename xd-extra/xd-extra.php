<?php
/**
 * Plugin Name: XD Extra
 * Plugin URI: https://vinhxd.com/plugin
 * Description: Bộ công cụ bổ sung cho website được thiết kế bởi Vĩnh XD.
 * Version: 3.3.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Vĩnh XD
 * Author URI: https://vinhxd.com
 * Update URI: false
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XD_VERSION',    '3.3.0' );
define( 'XD_PLUGIN_FILE', __FILE__ );
define( 'XD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XD_ASSETS_URL', XD_PLUGIN_URL . 'assets/' );
define( 'XD_ASSETS_DIR', XD_PLUGIN_DIR . 'assets/' );
define( 'XD_TEMPLATES',  XD_PLUGIN_DIR . 'templates/' );
define( 'XD_INCLUDES',   XD_PLUGIN_DIR . 'includes/' );

foreach ( [
	'helpers',          // Hàm dùng chung: thông tin, link Zalo/điện thoại, icon SVG, nạp CSS theo shortcode
	'frontend',         // CSS/JS chung, favicon, biến màu & font, nút lên đầu trang, Coming Soon
	'contact-buttons',  // Nút liên hệ nổi (Gọi, Zalo, Messenger, nút nhấp nháy)
	'shortcodes',       // [xd_news] [xd_uu_dai] [xd_lien_he] [vinhxd]
	'templates',        // Template Single/Archive bài viết, Page XD
	'images',           // Kích thước ảnh, nén & đổi WebP
	'inject',           // Chèn mã nhúng (GTM, Pixel...)
	'security',         // XD Hide: ẩn plugin/menu, chống clone/backup
] as $xd_module ) {
	require_once XD_INCLUDES . $xd_module . '.php';
}

// Module "CSS tùy chỉnh" (CSS toàn site + CSS từng trang), bật/tắt ở XD Extra → Cài đặt.
if ( get_option( 'xd_pcss_enabled', '1' ) === '1' ) {
	require_once XD_INCLUDES . 'post-css.php';
}

if ( is_admin() ) {
	require_once XD_PLUGIN_DIR . 'admin/settings.php';
	require_once XD_PLUGIN_DIR . 'admin/design.php';
}

/**
 * Dọn dữ liệu cũ khi nâng cấp (chạy 1 lần mỗi phiên bản).
 */
add_action( 'admin_init', function () {
	$installed = get_option( 'xd_extra_version' );
	if ( $installed === XD_VERSION ) return;

	// 3.2.0: bỏ bộ cập nhật từ vinhxd.com và log chặn login không còn dùng.
	delete_transient( 'xd_update_xd-extra' );
	delete_option( 'xd_login_block_log' );

	// Chống clone đã gom về trang XD Hide; đồng bộ lại mu-plugin bảo vệ nếu đang bật.
	if ( function_exists( 'xd_guard_sync_mu' ) ) xd_guard_sync_mu();

	update_option( 'xd_extra_version', XD_VERSION, false );
} );

register_deactivation_hook( __FILE__, function () {
	// Mu-plugin bảo vệ vẫn giữ lại để chống clone ngay cả khi XD Extra bị tắt.
	delete_transient( 'xd_update_xd-extra' );
} );
