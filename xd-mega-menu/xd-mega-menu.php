<?php
/**
 * Plugin Name: XD Mega Menu
 * Description: Mega menu dựa trên WP Menus (Giao diện > Menu). Mỗi menu item có thể đánh dấu là Mega Menu, đặt chiều rộng và nội dung (Elementor template hoặc shortcode). Widget Elementor "XD Menu" để render menu + style.
 * Version: 3.3
 * Author: VinhXD
 * Text Domain: xd-mega-menu
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XD_MM_VERSION', '3.3' );
define( 'XD_MM_FILE', __FILE__ );
define( 'XD_MM_PATH', plugin_dir_path( __FILE__ ) );
define( 'XD_MM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Một số theme (block theme / Hello Biz đi kèm Elementor v4) không khai báo
 * add_theme_support('menus') nên trang "Giao diện > Menu" bị WP ẩn khỏi
 * sidebar admin. Bật lại để admin vẫn quản lý menu qua màn hình quen thuộc.
 */
add_action( 'after_setup_theme', function () {
	if ( ! current_theme_supports( 'menus' ) ) {
		add_theme_support( 'menus' );
	}
}, 20 );

// Cache (phiên bản hoá) + shortcode [xd_xe_grid].
require_once XD_MM_PATH . 'includes/cache.php';

// Field tuỳ chỉnh cho menu item – chỉ cần ở admin (nav-menus.php, admin-ajax).
if ( is_admin() ) {
	require_once XD_MM_PATH . 'includes/admin-menu-fields.php';
}

/**
 * Chuẩn hoá chiều rộng mega menu: "full", số (tự thêm px) hoặc số + px|%|vw|rem|em.
 * Giá trị khác trả về chuỗi rỗng. Dùng khi lưu (admin) và khi render (dữ liệu cũ).
 */
function xd_mm_sanitize_width( $value ) {
	$value = strtolower( trim( (string) $value ) );
	if ( 'full' === $value ) {
		return 'full';
	}
	if ( preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
		return $value . 'px';
	}
	if ( preg_match( '/^\d+(\.\d+)?(px|%|vw|rem|em)$/', $value ) ) {
		return $value;
	}
	return '';
}

/**
 * Version asset =phiên bản plugin + filemtime để trình duyệt/CDN nhận file mới.
 */
function xd_mm_asset_ver( $rel ) {
	$file = XD_MM_PATH . $rel;
	return XD_MM_VERSION . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
}

/**
 * Đăng ký CSS/JS. Chỉ register – Elementor tự enqueue ở trang có widget
 * (get_style_depends / get_script_depends), shortcode tự enqueue CSS của nó.
 */
function xd_mm_register_assets() {
	if ( wp_style_is( 'xd-mega-menu', 'registered' ) ) {
		return;
	}

	wp_register_style( 'xd-mega-menu', XD_MM_URL . 'assets/widgets/xd-menu.css', [], xd_mm_asset_ver( 'assets/widgets/xd-menu.css' ) );
	wp_register_style( 'xd-xe-grid', XD_MM_URL . 'assets/widgets/xd-xe-grid.css', [], xd_mm_asset_ver( 'assets/widgets/xd-xe-grid.css' ) );

	// Không phụ thuộc jQuery. Từ WP 6.3 tham số mảng cho phép defer; bản cũ hơn coi như in_footer = true.
	wp_register_script( 'xd-mega-menu', XD_MM_URL . 'assets/widgets/xd-menu.js', [], xd_mm_asset_ver( 'assets/widgets/xd-menu.js' ), [
		'in_footer' => true,
		'strategy'  => 'defer',
	] );
}
add_action( 'wp_enqueue_scripts', 'xd_mm_register_assets', 5 );
add_action( 'elementor/frontend/after_register_styles', 'xd_mm_register_assets' );
add_action( 'elementor/frontend/after_register_scripts', 'xd_mm_register_assets' );

// Đăng ký widget Elementor "XD Menu".
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once XD_MM_PATH . 'includes/class-xd-menu-widget.php';
	$widgets_manager->register( new \XD_Menu_Widget() );
} );

/**
 * Nâng cấp từ bản cũ (chạy 1 lần khi đổi phiên bản):
 * - Xoá transient kiểu cũ (v3.1 trở về trước) còn sót trong wp_options.
 * - Tăng phiên bản cache.
 * - Xoá cache CSS của Elementor để các control mới (biến CSS mobile, icon…) được sinh lại.
 */
add_action( 'wp_loaded', function () {
	$installed = get_option( 'xd_mm_version' );
	if ( $installed === XD_MM_VERSION ) {
		return;
	}

	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_xd\_mm\_%'
		    OR option_name LIKE '\_transient\_timeout\_xd\_mm\_%'"
	);

	// Bản 3.1 trở về trước không lưu xd_mm_version → option trống cũng coi là nâng cấp.
	if ( did_action( 'elementor/loaded' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	xd_mm_bump_cache();
	update_option( 'xd_mm_version', XD_MM_VERSION, true );
} );
