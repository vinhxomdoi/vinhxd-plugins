<?php
/**
 * Lớp nền cho mọi widget XD.
 *
 * Mỗi widget khai báo:
 *   protected $exd_slug    = 'banner';          // → tên widget exd-banner, file assets/widgets/banner.css|js
 *   protected $exd_style   = array( 'exd-fx' ); // CSS dùng chung thêm (ngoài exd-core + CSS riêng)
 *   protected $exd_scripts = array();           // JS thêm
 *
 * @package Elementor_XD
 */

namespace EXD;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

abstract class Base extends Widget_Base {

	use Controls;

	protected $exd_slug    = '';
	protected $exd_title   = '';
	protected $exd_icon    = 'eicon-apps';
	protected $exd_keys    = array();
	protected $exd_style   = array();
	protected $exd_scripts = array();
	/** Widget có file JS riêng (assets/widgets/<slug>.js)? */
	protected $exd_js      = false;
	/** Nội dung phụ thuộc người xem/URL/dữ liệu ngoài → không cache HTML. */
	protected $exd_dynamic = false;

	public function get_name(): string {
		return 'exd-' . $this->exd_slug;
	}

	public function get_title(): string {
		return $this->exd_title;
	}

	public function get_icon(): string {
		return $this->exd_icon;
	}

	public function get_categories(): array {
		return array( 'elementor-xd' );
	}

	public function get_keywords(): array {
		return array_merge( array( 'xd', 'vinhxd', 'ux' ), $this->exd_keys );
	}

	public function get_custom_help_url(): string {
		return EXD_HOME;
	}

	public function get_style_depends(): array {
		$own = wp_style_is( 'exd-w-' . $this->exd_slug, 'registered' ) || file_exists( EXD_DIR . 'assets/widgets/' . $this->exd_slug . '.css' )
			? array( 'exd-w-' . $this->exd_slug )
			: array();
		return array_values( array_unique( array_merge( array( 'exd-core' ), $this->exd_style, $own ) ) );
	}

	public function get_script_depends(): array {
		if ( ! H::is_preview_request() && ! $this->exd_need_js() ) {
			return array();
		}
		$own = $this->exd_js ? array( 'exd-w-' . $this->exd_slug ) : array();
		return array_values( array_unique( array_merge( $this->exd_scripts, $own ) ) );
	}

	/**
	 * Widget có cần JS với cài đặt hiện tại không? Ghi đè để chỉ nạp JS khi bật tính năng
	 * (Elementor gọi hàm này với settings của từng widget khi lưu trang).
	 */
	protected function exd_need_js(): bool {
		return true;
	}

	/**
	 * Settings để render: giá trị đã xử lý dynamic tag + giá trị gốc của control đang bị ẩn theo điều kiện
	 * (Elementor bỏ các khoá này khỏi get_settings_for_display()).
	 */
	protected function exd_settings(): array {
		return array_merge( (array) $this->get_settings(), (array) $this->get_settings_for_display() );
	}

	/** Đọc 1 setting an toàn (kể cả khi widget chưa có dữ liệu). */
	protected function exd_get( $key, $default = '' ) {
		// Elementor có thể gọi get_*_depends() trên "mẫu" widget chưa có dữ liệu → không dùng get_settings() ở đây.
		if ( $this->is_type_instance() ) {
			return $default;
		}
		$raw = $this->get_raw_data();
		if ( ! is_array( $raw ) || ! isset( $raw['settings'] ) || ! is_array( $raw['settings'] ) ) {
			return $default;
		}
		$v = $raw['settings'][ $key ] ?? null;
		return ( null === $v || '' === $v ) ? $default : $v;
	}

	/** Bỏ thẻ .elementor-widget-container thừa (Elementor 3.24+) → DOM gọn hơn. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	protected function is_dynamic_content(): bool {
		return $this->exd_dynamic;
	}
}
