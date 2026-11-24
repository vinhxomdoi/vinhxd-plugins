<?php
/**
 * Plugin Name: XD Bảng Giá Xe SEO
 * Description: Bảng giá xe dạng table chuẩn SEO (ăn gợi ý Google Featured Snippets), tự động lấy dữ liệu & công thức tính ưu đãi lên đến, giá sau ưu đãi và giá lăn bánh theo từng phiên bản từ VinhXD Ô tô.
 * Version: 1.0.0
 * Author: VinhXD Ecosystem
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: xd-bang-gia-seo
 */

defined( 'ABSPATH' ) || exit;

define( 'XD_BGSEO_VERSION', '1.0.0' );
define( 'XD_BGSEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'XD_BGSEO_URL', plugin_dir_url( __FILE__ ) );
define( 'XD_BGSEO_OPT', 'xd_bgseo_options' );

require_once XD_BGSEO_DIR . 'inc/class-calc.php';
require_once XD_BGSEO_DIR . 'inc/class-shortcodes.php';
require_once XD_BGSEO_DIR . 'inc/class-admin.php';

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'VinhXD_Pricing' ) ) {
		add_action( 'admin_notices', function () {
			if ( ! current_user_can( 'activate_plugins' ) ) return;
			echo '<div class="notice notice-warning"><p><strong>XD Bảng Giá Xe SEO:</strong> Cần kích hoạt plugin <strong>VinhXD Ô tô</strong> để bảng giá tự động lấy dữ liệu xe và công thức tính ưu đãi.</p></div>';
		} );
		return;
	}

	XD_BGSEO_Shortcodes::init();
	if ( is_admin() ) {
		XD_BGSEO_Admin::init();
	}
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style(
		'xd-banggia-seo',
		XD_BGSEO_URL . 'assets/xd-banggia-seo.css',
		array(),
		XD_BGSEO_VERSION
	);
} );

register_activation_hook( __FILE__, function () {
	if ( get_option( XD_BGSEO_OPT ) === false ) {
		add_option( XD_BGSEO_OPT, XD_BGSEO_Calc::defaults(), '', true );
	}
	if ( function_exists( 'vinhxd_cache_flush' ) ) {
		vinhxd_cache_flush();
	}
} );
