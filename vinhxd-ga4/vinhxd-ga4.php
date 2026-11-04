<?php
/**
 * Plugin Name: XD Analytics
 * Plugin URI: https://vinhxd.com/plugin
 * Description: Dashboard xem báo cáo Google Analytics (Top trang, Nguồn traffic, Link ra ngoài), widget wp-admin, theo dõi link lỗi 404, shortcode hiển thị lượt xem. Theo dõi chuyên sâu vào CPT. Thiết lập và theo dõi chuyển đổi (click/submit)
 * Version: 1.14.1
 * Author: Vĩnh XD
 * Author URI: https://vinhxd.com
 * Text Domain: vinhxd-ga4
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VINHXD_GA4_VERSION', '1.14.1' );
define( 'VINHXD_GA4_PATH', plugin_dir_path( __FILE__ ) );
define( 'VINHXD_GA4_URL', plugin_dir_url( __FILE__ ) );

require_once VINHXD_GA4_PATH . 'includes/class-ga4-client.php';
require_once VINHXD_GA4_PATH . 'includes/class-admin-settings.php';
require_once VINHXD_GA4_PATH . 'includes/class-conversion-tracking.php';
require_once VINHXD_GA4_PATH . 'includes/class-internal-tracking.php';
require_once VINHXD_GA4_PATH . 'includes/class-404-monitor.php';
require_once VINHXD_GA4_PATH . 'includes/class-dashboard.php';
require_once VINHXD_GA4_PATH . 'includes/class-post-views.php';
require_once VINHXD_GA4_PATH . 'includes/class-shortcodes.php';
require_once VINHXD_GA4_PATH . 'includes/class-post-metabox.php';
require_once VINHXD_GA4_PATH . 'includes/class-ads-client.php';
require_once VINHXD_GA4_PATH . 'includes/class-ads-settings.php';

/**
 * Đăng ký menu admin
 */
function vinhxd_ga4_admin_menu() {
	// Menu chính riêng cho Nhật ký chuyển đổi - đặt vị trí cao hơn (trên) XD Analytics
	// vì đây là dữ liệu đa số người dùng cần xem hàng ngày
	add_menu_page(
		'Chuyển Đổi',
		'Chuyển Đổi',
		'manage_options',
		'vinhxd-ga4-logs',
		array( 'Vinhxd_GA4_Internal_Tracking', 'render_admin_page' ),
		'dashicons-visibility',
		79
	);

	add_menu_page(
		'XD Analytics',
		'XD Analytics',
		'manage_options',
		'vinhxd-ga4-dashboard',
		array( 'Vinhxd_GA4_Dashboard', 'render' ),
		'dashicons-chart-area',
		80
	);

	add_submenu_page( 'vinhxd-ga4-dashboard', 'Tổng quan', 'Tổng quan', 'manage_options', 'vinhxd-ga4-dashboard', array( 'Vinhxd_GA4_Dashboard', 'render' ) );
	add_submenu_page( 'vinhxd-ga4-dashboard', 'Data theo ngày', 'Data theo ngày', 'manage_options', 'vinhxd-ga4-daily', array( 'Vinhxd_GA4_Dashboard', 'render_daily' ) );
	add_submenu_page( 'vinhxd-ga4-dashboard', 'Google Ads', 'Google Ads', 'manage_options', 'vinhxd-ga4-ads', array( 'Vinhxd_Ads_Settings', 'render_page' ) );
	add_submenu_page( 'vinhxd-ga4-dashboard', 'Chuyển đổi', 'Chuyển đổi', 'manage_options', 'vinhxd-ga4-conversions', array( 'Vinhxd_GA4_Conversion_Tracking', 'render_admin_page' ) );
	add_submenu_page( 'vinhxd-ga4-dashboard', 'Lỗi 404', 'Lỗi 404', 'manage_options', 'vinhxd-ga4-404', array( 'Vinhxd_GA4_404_Monitor', 'render_admin_page' ) );
	add_submenu_page( 'vinhxd-ga4-dashboard', 'Cài đặt', 'Cài đặt', 'manage_options', 'vinhxd-ga4-settings', array( 'Vinhxd_GA4_Admin_Settings', 'render_page' ) );

	// Ẩn "Lỗi 404" khỏi menu hiển thị, nhưng trang vẫn hoạt động bình thường khi truy cập
	// qua link trực tiếp (VD link "Xem toàn bộ danh sách lỗi 404" ở trang Data theo ngày)
	remove_submenu_page( 'vinhxd-ga4-dashboard', 'vinhxd-ga4-404' );
}
add_action( 'admin_menu', 'vinhxd_ga4_admin_menu' );

/**
 * Loại trừ tracking cho Admin/Editor.
 * In script tắt GA (window['ga-disable-MEASUREMENT_ID']) SỚM trong wp_head (priority 1)
 * để chạy TRƯỚC đoạn code snippet gtag.js mà bạn đã nhúng riêng.
 */
function vinhxd_ga4_exclude_admin_editor() {
	$exclude = get_option( 'vinhxd_ga4_exclude_admin', '0' );
	$measurement_id = get_option( 'vinhxd_ga4_measurement_id', '' );

	if ( $exclude !== '1' || empty( $measurement_id ) ) return;
	if ( ! is_user_logged_in() ) return;

	$user = wp_get_current_user();
	$target_roles = array( 'administrator', 'editor' );

	if ( array_intersect( $target_roles, (array) $user->roles ) ) {
		echo "<script>window['ga-disable-" . esc_js( $measurement_id ) . "'] = true;</script>\n";
	}
}
add_action( 'wp_head', 'vinhxd_ga4_exclude_admin_editor', 1 );

/**
 * Kích hoạt plugin: set giá trị mặc định
 */
function vinhxd_ga4_activate() {
	add_option( 'vinhxd_ga4_exclude_admin', '0' );
	add_option( 'vinhxd_ga4_conversions', array() );
	add_option( 'vinhxd_ga4_view_post_types', array( 'post', 'page' ) );
	add_option( 'vinhxd_ga4_exclude_bots', '1' );
}
register_activation_hook( __FILE__, 'vinhxd_ga4_activate' );

function vinhxd_ga4_deactivate() {
	wp_clear_scheduled_hook( 'vinhxd_ga4_sync_views_event' );
	wp_clear_scheduled_hook( Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON );
	wp_clear_scheduled_hook( Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON . '_once' );
}
register_deactivation_hook( __FILE__, 'vinhxd_ga4_deactivate' );

/**
 * Nâng cấp dữ liệu 1 lần khi đổi phiên bản (chạy tự động, không cần kích hoạt lại plugin).
 */
function vinhxd_ga4_maybe_upgrade() {
	$db_version = get_option( 'vinhxd_ga4_db_version', '' );
	if ( $db_version === VINHXD_GA4_VERSION ) return;

	// 1) Thông tin bí mật không autoload vào mọi request nữa
	$secret_keys = array( 'vinhxd_ga4_service_account_json', 'vinhxd_ads_client_id', 'vinhxd_ads_client_secret', 'vinhxd_ads_refresh_token' );
	if ( function_exists( 'wp_set_option_autoload_values' ) ) {
		wp_set_option_autoload_values( array_fill_keys( $secret_keys, false ) );
	} else {
		foreach ( $secret_keys as $key ) {
			$val = get_option( $key, null );
			if ( null !== $val ) { delete_option( $key ); add_option( $key, $val, '', 'no' ); }
		}
	}

	// 2) Bỏ cache dải IP Google kiểu cũ (transient) - giờ lưu option và làm mới bằng cron
	delete_transient( 'vinhxd_ga4_google_ip_ranges' );
	Vinhxd_GA4_Internal_Tracking::maybe_schedule_cron();
	if ( ! wp_next_scheduled( Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON . '_once' ) ) {
		wp_schedule_single_event( time(), Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON . '_once' );
	}

	// 3) Đổi file log sang tên ngẫu nhiên + gia cố .htaccess (nếu đã có thư mục log)
	$log_file = Vinhxd_GA4_Internal_Tracking::get_log_file();
	if ( file_exists( dirname( $log_file ) ) ) {
		Vinhxd_GA4_Internal_Tracking::protect_log_dir( trailingslashit( dirname( $log_file ) ) );
	}

	update_option( 'vinhxd_ga4_db_version', VINHXD_GA4_VERSION );
}
add_action( 'init', 'vinhxd_ga4_maybe_upgrade', 5 );
add_action( 'admin_init', array( 'Vinhxd_GA4_Internal_Tracking', 'maybe_schedule_cron' ) );

/**
 * Xử lý nút "Đồng bộ ngay" ở trang Cài đặt
 */
function vinhxd_ga4_handle_manual_sync() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
	check_admin_referer( 'vinhxd_ga4_manual_sync' );

	Vinhxd_GA4_Post_Views::sync_views_from_ga4();

	wp_safe_redirect( add_query_arg( array( 'page' => 'vinhxd-ga4-settings', 'synced' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_vinhxd_ga4_manual_sync', 'vinhxd_ga4_handle_manual_sync' );
