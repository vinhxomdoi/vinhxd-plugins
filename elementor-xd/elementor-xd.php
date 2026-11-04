<?php
/**
 * Plugin Name:       Elementor XD
 * Plugin URI:        https://vinhxd.com/tai-nguyen/elementor-xd
 * Description:       Bộ widget Elementor gọn, hiện đại, có sẵn nhiều kiểu (preset): Khách hàng, Thành viên, Banner, Slider, Banner Grids, Icon Box, Hộp ảnh, Tiêu đề, Bài viết, Nút video, Cửa sổ nổi, Hộp thông báo, Scroll To. CSS/JS chỉ nạp ở trang có widget.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Vĩnh XD
 * Author URI:        https://vinhxd.com
 * Text Domain:       elementor-xd
 * Update URI:        false
 * Elementor tested up to: 4.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EXD_VERSION' ) ) :

	define( 'EXD_VERSION', '1.0.0' );
	define( 'EXD_FILE', __FILE__ );
	define( 'EXD_DIR', plugin_dir_path( __FILE__ ) );
	define( 'EXD_URL', plugin_dir_url( __FILE__ ) );
	define( 'EXD_HOME', 'https://vinhxd.com/tai-nguyen/elementor-xd' );

	require_once EXD_DIR . 'inc/plugin.php';

	add_action( 'plugins_loaded', array( 'EXD\\Plugin', 'boot' ) );

endif;
