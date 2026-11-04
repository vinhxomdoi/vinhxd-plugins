<?php
/**
 * Khởi động plugin: danh mục widget, đăng ký asset, đăng ký widget, trang cài đặt.
 *
 * @package Elementor_XD
 */

namespace EXD;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	const OPTION = 'exd_disabled_widgets';

	/**
	 * Danh sách widget: slug => [class, tên hiển thị].
	 * Slug = tên file widgets/<slug>.php, tên widget Elementor = exd-<slug>.
	 */
	public static function widgets(): array {
		return array(
			'testimonial' => array( 'Testimonial', 'Khách hàng XD' ),
			'team'        => array( 'Team', 'Thành viên XD' ),
			'message'     => array( 'Message', 'Hộp thông báo XD' ),
			'lightbox'    => array( 'Lightbox', 'Cửa sổ nổi XD' ),
			'image-box'   => array( 'Image_Box', 'Hộp ảnh XD' ),
			'icon-box'    => array( 'Icon_Box', 'Icon Box XD' ),
			'banner'      => array( 'Banner', 'Banner XD' ),
			'banner-grid' => array( 'Banner_Grid', 'Banner Grids XD' ),
			'slider'      => array( 'Slider', 'Slider XD' ),
			'video'       => array( 'Video_Button', 'Nút video XD' ),
			'title'       => array( 'Title', 'Tiêu đề XD' ),
			'posts'       => array( 'Posts', 'Bài viết XD' ),
			'scroll-to'   => array( 'Scroll_To', 'Scroll To XD' ),
		);
	}

	public static function boot() {
		if ( is_admin() ) {
			require_once EXD_DIR . 'inc/admin.php';
			Admin::init();
		}

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_elementor' ) );
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );

		// Đăng ký (không nạp) asset: Elementor chỉ nạp ở trang có widget qua get_style_depends()/get_script_depends().
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_css' ) );
	}

	public static function notice_missing_elementor() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Elementor XD</strong> cần plugin <strong>Elementor</strong> đang bật để hoạt động.</p></div>';
	}

	public static function category( $manager ) {
		$manager->add_category(
			'elementor-xd',
			array(
				'title' => 'Elementor XD',
				'icon'  => 'eicon-apps',
			)
		);
	}

	public static function disabled(): array {
		$v = get_option( self::OPTION, array() );
		return is_array( $v ) ? $v : array();
	}

	public static function register_widgets( $manager ) {
		require_once EXD_DIR . 'inc/helpers.php';
		require_once EXD_DIR . 'inc/parts.php';
		require_once EXD_DIR . 'inc/controls.php';
		require_once EXD_DIR . 'inc/base.php';

		$off = self::disabled();
		foreach ( self::widgets() as $slug => $w ) {
			if ( in_array( $slug, $off, true ) ) {
				continue;
			}
			$file = EXD_DIR . 'widgets/' . $slug . '.php';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			require_once $file;
			$class = '\\EXD\\Widgets\\' . $w[0];
			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}

	/** Version = phiên bản plugin + filemtime để tự xoá cache khi sửa file. */
	private static function ver( $rel ) {
		$path = EXD_DIR . $rel;
		return EXD_VERSION . '.' . ( file_exists( $path ) ? filemtime( $path ) : '0' );
	}

	public static function register_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$css = array(
			// handle => [file, deps]
			'exd-core'  => array( 'assets/core.css', array() ),
			'exd-fx'    => array( 'assets/fx.css', array( 'exd-core' ) ),
			'exd-modal' => array( 'assets/modal.css', array( 'exd-core' ) ),
		);
		$js = array(
			'exd-core'  => array( 'assets/core.js', array() ),
			'exd-modal' => array( 'assets/modal.js', array( 'exd-core' ) ),
		);

		// Mỗi widget: assets/widgets/<slug>.css|js → handle exd-w-<slug>.
		foreach ( glob( EXD_DIR . 'assets/widgets/*.{css,js}', GLOB_BRACE ) ?: array() as $file ) {
			$ext  = pathinfo( $file, PATHINFO_EXTENSION );
			$slug = basename( $file, '.' . $ext );
			$rel  = 'assets/widgets/' . basename( $file );
			if ( 'css' === $ext ) {
				$css[ 'exd-w-' . $slug ] = array( $rel, array( 'exd-core' ) );
			} else {
				$deps                   = 'lightbox' === $slug ? array( 'exd-core', 'exd-modal' ) : array( 'exd-core' );
				$js[ 'exd-w-' . $slug ] = array( $rel, $deps );
			}
		}

		foreach ( $css as $handle => $c ) {
			wp_register_style( $handle, EXD_URL . $c[0], $c[1], self::ver( $c[0] ) );
		}
		foreach ( $js as $handle => $j ) {
			wp_register_script(
				$handle,
				EXD_URL . $j[0],
				$j[1],
				self::ver( $j[0] ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/** Đánh dấu nhỏ "XD" cho các widget của plugin trong khung Elementor. */
	public static function editor_css() {
		$css = '#elementor-panel-category-elementor-xd .elementor-element .icon{position:relative}'
			. '#elementor-panel-category-elementor-xd .elementor-element .icon:after{content:"XD";position:absolute;top:-6px;right:-14px;font:700 8px/1 sans-serif;letter-spacing:.3px;padding:2px 3px;border-radius:3px;background:#2563eb;color:#fff}';
		wp_add_inline_style( 'elementor-editor', $css );
	}
}
