<?php
/**
 * XD Guard – lõi chặn plugin backup / migration / clone / file manager.
 *
 * File này được nạp bởi XD Extra, đồng thời được chép thành mu-plugin
 * (wp-content/mu-plugins/xd-guard.php) để:
 *  - chạy TRƯỚC mọi plugin thường, gỡ plugin bị chặn khỏi danh sách nạp;
 *  - vẫn bảo vệ khi XD Extra bị tắt.
 *
 * Tạm cho phép backup (vd. chính bạn cần chuyển host): thêm vào wp-config.php
 *     define( 'XD_ALLOW_BACKUP', true );
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Bọc trong khối điều kiện: file có thể được nạp 2 lần (mu-plugin + XD Extra).
// Hàm khai báo trong if chỉ được tạo khi khối chạy, nên không bị lỗi khai báo trùng.
if ( ! defined( 'XD_GUARD_VERSION' ) ) :

define( 'XD_GUARD_VERSION', '1.0.1' );

function xd_guard_active() {
	if ( defined( 'XD_ALLOW_BACKUP' ) && XD_ALLOW_BACKUP ) return false;
	return get_option( 'xd_anti_migration' ) === '1';
}

/** Từ khoá trong slug / tên plugin bị chặn. */
function xd_guard_keywords() {
	return [
		// Backup / migration / clone
		'migrat', 'duplicator', 'backup', 'back-up', 'clone', 'cloner', 'copy-site', 'site-copier',
		'wp-staging', 'wpvivid', 'updraft', 'xcloner', 'backwpup', 'blogvault', 'jetbackup', 'backupbuddy',
		'prime-mover', 'shipper', 'wp-sync-db', 'wp-migrate', 'all-in-one-wp-migration', 'migrate-guru',
		'snapshot-backup', 'everest-backup', 'solid-backups', 'boldgrid-backup', 'wp-time-capsule',
		'site-export', 'export-site', 'wp-all-export', 'demo-export', 'theme-export',
		// Trình quản lý file / CSDL (tải được toàn bộ mã nguồn hoặc database)
		'file-manager', 'filemanager', 'file_manager', 'wpfm', 'file-explorer', 'filester', 'bit-file-manager',
		'adminer', 'db-manager', 'dbmanager', 'sql-executioner', 'sql-buddy', 'wp-phpmyadmin', 'database-manager',
	];
}

/** Plugin có tên chứa từ khoá nhưng vô hại (nhân bản bài viết, quản lý thư mục media...). */
function xd_guard_harmless() {
	return [ 'duplicate-post', 'post-duplicator', 'duplicate-page', 'duplicate-page-and-post', 'filebird', 'clone-widgets', 'elementor', 'jet-' ];
}

/** Danh sách cho phép do admin nhập (mỗi dòng 1 slug/file). */
function xd_guard_whitelist() {
	$raw = (string) get_option( 'xd_backup_whitelist', 'wp-file-manager' );
	return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', strtolower( $raw ) ) ) );
}

/**
 * @param string $plugin  "thu-muc/file.php" hoặc tên thư mục
 * @param string $name    Tên plugin (header "Plugin Name"), nếu có
 */
function xd_guard_is_blocked( $plugin, $name = '' ) {
	$plugin = strtolower( (string) $plugin );
	$slug   = strpos( $plugin, '/' ) !== false ? substr( $plugin, 0, strpos( $plugin, '/' ) ) : preg_replace( '/\.php$/', '', $plugin );

	// Plugin của XD.
	if ( strpos( $slug, 'xd-' ) === 0 || strpos( $slug, 'vinhxd' ) === 0 || $slug === 'xd-extra' ) return false;

	foreach ( xd_guard_whitelist() as $w ) {
		if ( $w === $plugin || $w === $slug ) return false;
	}
	foreach ( xd_guard_harmless() as $h ) {
		if ( strpos( $slug, $h ) === 0 ) return false;
	}

	$hay = $plugin . ' ' . sanitize_title( (string) $name );
	foreach ( xd_guard_keywords() as $kw ) {
		if ( strpos( $hay, $kw ) !== false ) return true;
	}
	return false;
}

/** Ghi nhật ký (giữ 200 dòng gần nhất). */
function xd_guard_log( $type, $detail ) {
	$log   = get_option( 'xd_guard_log', [] );
	$log   = is_array( $log ) ? $log : [];
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:\.]/', '', $_SERVER['REMOTE_ADDR'] ) : '';
	$user  = function_exists( 'wp_get_current_user' ) && did_action( 'set_current_user' ) ? wp_get_current_user()->user_login : '';
	$log[] = [
		'time'   => current_time( 'mysql' ),
		'type'   => $type,
		'detail' => mb_substr( (string) $detail, 0, 300 ),
		'ip'     => $ip,
		'user'   => $user,
		'ua'     => mb_substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 200 ),
	];
	if ( count( $log ) > 200 ) $log = array_slice( $log, -200 );
	update_option( 'xd_guard_log', $log, false );
}

function xd_guard_die( $msg ) {
	wp_die( '<h1>XD Bảo mật</h1><p>' . esc_html( $msg ) . '</p>', 'XD Bảo mật', [ 'response' => 403, 'back_link' => true ] );
}

/* ------------------------------------------------------------------
 * 1. Không nạp plugin bị chặn (gỡ khỏi active_plugins ngay khi WP đọc)
 * ---------------------------------------------------------------- */
function xd_guard_filter_active( $plugins ) {
	if ( ! is_array( $plugins ) || ! xd_guard_active() ) return $plugins;
	return array_values( array_filter( $plugins, function ( $p ) { return ! xd_guard_is_blocked( $p ); } ) );
}
add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );

/** Danh sách plugin đang bật thật trong database (bỏ qua bộ lọc ở trên). */
function xd_guard_raw_active() {
	remove_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	$raw = get_option( 'active_plugins', [] );
	add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	return is_array( $raw ) ? $raw : [];
}

// Trong admin: gỡ hẳn plugin bị chặn khỏi database (xét cả tên plugin), ghi nhật ký.
add_action( 'admin_init', function () {
	if ( ! xd_guard_active() ) return;
	$raw = xd_guard_raw_active();
	if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$all     = get_plugins();
	$dropped = [];
	foreach ( $raw as $p ) {
		if ( xd_guard_is_blocked( $p, $all[ $p ]['Name'] ?? '' ) ) $dropped[] = $p;
	}
	if ( ! $dropped ) return;
	remove_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	update_option( 'active_plugins', array_values( array_diff( $raw, $dropped ) ) );
	add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	foreach ( $dropped as $p ) xd_guard_log( 'plugin', 'Đã tắt: ' . $p );
	add_action( 'admin_notices', function () use ( $dropped ) {
		echo '<div class="notice notice-warning"><p><strong>XD Bảo mật:</strong> đã tắt plugin backup/clone/file manager: <code>' . esc_html( implode( ', ', $dropped ) ) . '</code></p></div>';
	} );
}, 1 );

/* ------------------------------------------------------------------
 * 2. Chặn kích hoạt
 * ---------------------------------------------------------------- */
add_action( 'activate_plugin', function ( $plugin ) {
	if ( ! xd_guard_active() ) return;
	$name = '';
	$file = WP_PLUGIN_DIR . '/' . $plugin;
	if ( is_readable( $file ) && function_exists( 'get_plugin_data' ) ) {
		$data = get_plugin_data( $file, false, false );
		$name = $data['Name'] ?? '';
	}
	if ( xd_guard_is_blocked( $plugin, $name ) ) {
		xd_guard_log( 'activate', 'Chặn kích hoạt: ' . $plugin );
		xd_guard_die( 'Website này không cho phép kích hoạt plugin backup / migration / clone / file manager (' . $plugin . ').' );
	}
}, 1 );

/* ------------------------------------------------------------------
 * 3. Chặn cài đặt (tải từ kho WordPress hoặc upload file zip)
 * ---------------------------------------------------------------- */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source = '', $upgrader = null, $hook_extra = [] ) {
	if ( is_wp_error( $source ) || ! xd_guard_active() ) return $source;
	if ( ! empty( $hook_extra['theme'] ) || ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'theme' ) ) return $source;

	$dir   = untrailingslashit( $source );
	$slug  = basename( $dir );
	$names = [];
	if ( function_exists( 'get_plugin_data' ) ) {
		foreach ( (array) glob( $dir . '/*.php' ) as $php ) {
			$data = get_plugin_data( $php, false, false );
			if ( ! empty( $data['Name'] ) ) $names[] = $data['Name'];
		}
	}
	if ( ! $names && ! is_dir( $dir ) ) return $source;
	// Theme không có header Plugin Name -> bỏ qua.
	if ( ! $names && file_exists( $dir . '/style.css' ) ) return $source;

	if ( xd_guard_is_blocked( $slug, implode( ' ', $names ) ) ) {
		xd_guard_log( 'install', 'Chặn cài: ' . $slug );
		return new WP_Error( 'xd_guard_blocked', 'XD Bảo mật: website này không cho phép cài plugin backup / migration / clone / file manager (' . $slug . ').' );
	}
	return $source;
}, 1, 4 );

// Ẩn khỏi kết quả tìm kiếm ở Plugins → Cài mới.
add_filter( 'plugins_api_result', function ( $res, $action ) {
	if ( ! xd_guard_active() || $action !== 'query_plugins' || ! is_object( $res ) || empty( $res->plugins ) ) return $res;
	$res->plugins = array_values( array_filter( $res->plugins, function ( $p ) {
		$p = (array) $p;
		return ! xd_guard_is_blocked( $p['slug'] ?? '', $p['name'] ?? '' );
	} ) );
	return $res;
}, 99, 2 );

/* ------------------------------------------------------------------
 * 4. Chặn Công cụ → Xuất dữ liệu (export toàn bộ nội dung ra file XML)
 * ---------------------------------------------------------------- */
add_action( 'load-export.php', function () {
	if ( ! xd_guard_active() ) return;
	xd_guard_log( 'export', 'Chặn trang Xuất dữ liệu' );
	xd_guard_die( 'Chức năng xuất dữ liệu đã bị tắt trên website này.' );
} );
add_action( 'export_wp', function () {
	if ( ! xd_guard_active() ) return;
	xd_guard_log( 'export', 'Chặn export_wp' );
	xd_guard_die( 'Chức năng xuất dữ liệu đã bị tắt trên website này.' );
}, 0 );
add_action( 'admin_menu', function () {
	if ( xd_guard_active() ) remove_submenu_page( 'tools.php', 'export.php' );
}, 999 );

// Ẩn danh sách mu-plugin để XD Guard không bị chú ý.
add_filter( 'show_advanced_plugins', function ( $show, $type ) {
	return ( $type === 'mustuse' && xd_guard_active() ) ? false : $show;
}, 10, 2 );

endif; // XD_GUARD_VERSION
