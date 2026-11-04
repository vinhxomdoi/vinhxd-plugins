<?php
/**
 * Plugin Name: Popup XD
 * Plugin URI:  https://vinhxd.com/plugin
 * Description: Trình tạo popup linh hoạt dùng template Elementor hoặc trình soạn thảo WordPress, kèm điều kiện kích hoạt, phạm vi hiển thị và nút mở popup qua shortcode.
 * Version:     1.3.0
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * Author:      Vĩnh XD
 * Author URI:  https://vinhxd.com
 * Text Domain: popup-xd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POPUPXD_VERSION', '1.3.0' );
define( 'POPUPXD_FILE', __FILE__ );
define( 'POPUPXD_PATH', plugin_dir_path( __FILE__ ) );
define( 'POPUPXD_URL', plugin_dir_url( __FILE__ ) );
define( 'POPUPXD_CPT', 'popupxd' );
define( 'POPUPXD_META_PREFIX', '_pxd_' );

require_once POPUPXD_PATH . 'includes/class-popupxd-helpers.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-cpt.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-metaboxes.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-admin.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-elementor.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-frontend.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-shortcode.php';
require_once POPUPXD_PATH . 'includes/class-popupxd-preview.php';

/**
 * Main plugin orchestrator.
 */
final class PopupXD {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', function () {
			load_plugin_textdomain( 'popup-xd', false, dirname( plugin_basename( POPUPXD_FILE ) ) . '/languages' );
		} );
		new PopupXD_CPT();
		new PopupXD_Metaboxes();
		new PopupXD_Admin();
		new PopupXD_Elementor();
		new PopupXD_Frontend();
		new PopupXD_Shortcode();
		new PopupXD_Preview();
	}
}

function popupxd() {
	return PopupXD::instance();
}

add_action( 'plugins_loaded', 'popupxd' );

register_activation_hook( __FILE__, function () {
	require_once POPUPXD_PATH . 'includes/class-popupxd-cpt.php';
	$cpt = new PopupXD_CPT();
	$cpt->register_cpt();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
