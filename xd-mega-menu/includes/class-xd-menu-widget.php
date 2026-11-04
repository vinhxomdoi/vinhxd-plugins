<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;

/**
 * Widget Elementor "XD Menu".
 *
 * Lưu ý khi nâng cấp: giữ nguyên ID các control cũ để dữ liệu đã lưu trên site không mất.
 * Các control mobile cũ giờ xuất ra biến CSS (--xd-m-*) thay cho thẻ <style> inline.
 */
class XD_Menu_Widget extends Widget_Base {

	/** Template đang render – chống lặp vô hạn khi template mega lại chứa chính widget này. */
	private static $rendering_templates = [];

	public function get_name() {
		return 'xd_menu';
	}

	public function get_title() {
		return 'XD Menu';
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'menu', 'mega', 'nav', 'xd', 'header' ];
	}

	public function get_style_depends() {
		return [ 'xd-mega-menu' ];
	}

	public function get_script_depends() {
		return [ 'xd-mega-menu' ];
	}

	/**
	 * Nội dung phụ thuộc trang đang xem (mục active) → không cho Element Caching của Elementor cache.
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/** Lấy danh sách menu đã tạo trong Giao diện > Menu. */
	private function get_menus_options() {
		$options = [ '' => '— Chọn menu —' ];
		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ $menu->term_id ] = $menu->name;
		}
		return $options;
	}

	private function dims( $prop ) {
		return $prop . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
	}

	protected function register_controls() {

		/* ================= TAB NỘI DUNG ================= */

		$this->start_controls_section( 'section_content', [
			'label' => 'Nội dung',
		] );

		$this->add_control( 'menu', [
			'label'   => 'Chọn menu',
			'type'    => Controls_Manager::SELECT,
			'options' => $this->get_menus_options(),
			'default' => '',
		] );

		$this->add_control( 'submenu_icon', [
			'label'       => 'Icon mũi tên menu con',
			'type'        => Controls_Manager::ICONS,
			'default'     => [ 'value' => '', 'library' => '' ],
			'skin'        => 'inline',
			'description' => 'Để trống = dùng mũi tên SVG có sẵn (không cần Font Awesome).',
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_mobile_content', [
			'label' => 'Mobile Menu',
		] );

		$this->add_control( 'mobile_breakpoint', [
			'label'   => 'Hiện menu mobile từ',
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'0'    => 'Không dùng (luôn hiển thị menu ngang)',
				'1024' => 'Tablet (≤ 1024px)',
				'767'  => 'Mobile ngang (≤ 767px)',
				'480'  => 'Di động dọc (≤ 480px)',
			],
			'default' => '767',
		] );

		$this->add_control( 'trigger_alignment', [
			'label'     => 'Vị trí nút mở menu',
			'type'      => Controls_Manager::CHOOSE,
			'options'   => [
				'flex-start' => [ 'title' => 'Trái', 'icon' => 'eicon-h-align-left' ],
				'center'     => [ 'title' => 'Giữa', 'icon' => 'eicon-h-align-center' ],
				'flex-end'   => [ 'title' => 'Phải', 'icon' => 'eicon-h-align-right' ],
			],
			'default'   => 'flex-start',
			'condition' => [ 'mobile_breakpoint!' => '0' ],
			'selectors' => [
				'{{WRAPPER}} .xd-menu-trigger' => 'justify-content: {{VALUE}};',
			],
		] );

		$this->add_control( 'trigger_icon_open', [
			'label'     => 'Icon mở menu',
			'type'      => Controls_Manager::ICONS,
			'default'   => [ 'value' => 'fas fa-bars', 'library' => 'fa-solid' ],
			'condition' => [ 'mobile_breakpoint!' => '0' ],
		] );

		$this->add_control( 'trigger_icon_close', [
			'label'     => 'Icon đóng menu',
			'type'      => Controls_Manager::ICONS,
			'default'   => [ 'value' => 'fas fa-times', 'library' => 'fa-solid' ],
			'condition' => [ 'mobile_breakpoint!' => '0' ],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_advanced_content', [
			'label' => 'Nâng cao',
		] );

		$this->add_control( 'mega_cache', [
			'label'        => 'Cache HTML template mega (thử nghiệm)',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Lưu sẵn HTML của template mega cho khách chưa đăng nhập, tự làm mới khi sửa template hoặc Regenerate CSS. Bật xong hãy kiểm tra các widget có JS (slider, tab…) trong mega vẫn chạy đúng.',
		] );

		$this->end_controls_section();

		/* ================= TAB STYLE ================= */

		// ----- Bố cục -----
		$this->start_controls_section( 'section_layout', [
			'label' => 'Bố cục',
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'alignment', [
			'label'     => 'Căn lề menu',
			'type'      => Controls_Manager::CHOOSE,
			'options'   => [
				'flex-start'    => [ 'title' => 'Trái', 'icon' => 'eicon-text-align-left' ],
				'center'        => [ 'title' => 'Giữa', 'icon' => 'eicon-text-align-center' ],
				'flex-end'      => [ 'title' => 'Phải', 'icon' => 'eicon-text-align-right' ],
				'space-between' => [ 'title' => 'Hai bên (dàn đều)', 'icon' => 'eicon-text-align-justify' ],
			],
			'default'   => 'flex-start',
			'selectors' => [
				'{{WRAPPER}} .xd-menu' => 'justify-content: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'item_gap', [
			'label'     => 'Khoảng cách giữa các mục',
			'type'      => Controls_Manager::SLIDER,
			'range'     => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'default'   => [ 'unit' => 'px', 'size' => 0 ],
			'selectors' => [
				'{{WRAPPER}} .xd-menu' => 'gap: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'item_padding', [
			'label'      => 'Padding mỗi mục menu',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-item > a' => $this->dims( 'padding' ),
			],
		] );

		$this->end_controls_section();

		// ----- Chữ & màu -----
		$this->start_controls_section( 'section_colors', [
			'label' => 'Chữ & màu',
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'menu_typography',
			'label'    => 'Typography',
			'selector' => '{{WRAPPER}} .xd-menu-item > a',
		] );

		$this->add_control( 'color_normal', [
			'label'     => 'Màu chữ (bình thường)',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [ '{{WRAPPER}} .xd-menu-item > a' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'color_hover', [
			'label'     => 'Màu chữ khi hover',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#3b82f6',
			'selectors' => [ '{{WRAPPER}} .xd-menu-item > a:hover' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'color_active', [
			'label'     => 'Màu chữ khi active (trang hiện tại / đang mở)',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#3b82f6',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-item.xd-menu-current > a, {{WRAPPER}} .xd-menu-item.xd-menu-open > a' => 'color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ----- Icon mũi tên -----
		$this->start_controls_section( 'section_submenu_icon', [
			'label' => 'Icon mũi tên',
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'submenu_icon_size', [
			'label'      => 'Kích thước',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em' ],
			'range'      => [ 'px' => [ 'min' => 6, 'max' => 40 ], 'em' => [ 'min' => 0.3, 'max' => 3, 'step' => 0.1 ] ],
			'selectors'  => [
				'{{WRAPPER}} .xd-icon-angle' => 'font-size: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'submenu_icon_spacing', [
			'label'      => 'Khoảng cách tới chữ',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-link .xd-icon-angle' => 'margin-left: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->start_controls_tabs( 'submenu_icon_tabs' );

		$this->start_controls_tab( 'submenu_icon_tab_normal', [ 'label' => 'Bình thường' ] );
		$this->add_control( 'submenu_icon_color', [
			'label'     => 'Màu',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .xd-menu-link .xd-icon-angle' => 'color: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'submenu_icon_tab_hover', [ 'label' => 'Hover' ] );
		$this->add_control( 'submenu_icon_hover_color', [
			'label'     => 'Màu',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .xd-menu-link:hover .xd-icon-angle, {{WRAPPER}} .xd-menu-open > .xd-menu-link .xd-icon-angle' => 'color: {{VALUE}};',
			],
		] );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		// ----- Mega menu / dropdown -----
		$this->start_controls_section( 'section_dropdown', [
			'label' => 'Mega menu / Dropdown',
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'mega_default_width', [
			'label'       => 'Chiều rộng mega mặc định',
			'type'        => Controls_Manager::SLIDER,
			'size_units'  => [ 'px', 'vw' ],
			'range'       => [ 'px' => [ 'min' => 200, 'max' => 1920 ], 'vw' => [ 'min' => 10, 'max' => 100 ] ],
			'description' => 'Dùng cho mục mega không đặt chiều rộng trong Giao diện > Menu. Để trống = co theo nội dung.',
			'selectors'   => [
				'{{WRAPPER}} .xd-menu-mega' => '--xd-mega-default-w: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'dropdown_bg', [
			'label'     => 'Màu nền',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-mega' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .xd-sub-menu'  => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'dropdown_padding', [
			'label'      => 'Padding',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'default'    => [ 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-mega' => $this->dims( 'padding' ),
				'{{WRAPPER}} .xd-sub-menu'  => $this->dims( 'padding' ),
			],
		] );

		$this->add_control( 'dropdown_radius', [
			'label'      => 'Bo góc',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-mega' => $this->dims( 'border-radius' ),
				'{{WRAPPER}} .xd-sub-menu'  => $this->dims( 'border-radius' ),
			],
		] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
			'name'     => 'dropdown_shadow',
			'selector' => '{{WRAPPER}} .xd-menu-mega, {{WRAPPER}} .xd-sub-menu',
		] );

		$this->add_control( 'heading_dropdown_items', [
			'label'     => 'Mục menu Dropdown (thường)',
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'dropdown_typography',
			'label'    => 'Typography',
			'selector' => '{{WRAPPER}} .xd-sub-menu .xd-menu-item > a',
		] );

		$this->add_control( 'dropdown_item_color', [
			'label'     => 'Màu chữ',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#333333',
			'selectors' => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item > a' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'dropdown_item_hover_color', [
			'label'     => 'Màu chữ khi hover',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#3b82f6',
			'selectors' => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item > a:hover' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'dropdown_item_hover_bg', [
			'label'     => 'Màu nền khi hover',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#f4f6f8',
			'selectors' => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item > a:hover' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'dropdown_item_active_color', [
			'label'     => 'Màu chữ active (trang hiện tại)',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#3b82f6',
			'selectors' => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item.xd-menu-current > a' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'dropdown_item_active_bg', [
			'label'     => 'Màu nền active',
			'type'      => Controls_Manager::COLOR,
			'default'   => 'transparent',
			'selectors' => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item.xd-menu-current > a' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'dropdown_item_padding', [
			'label'      => 'Padding mục dropdown',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-sub-menu .xd-menu-item > a' => $this->dims( 'padding' ),
			],
		] );

		$this->end_controls_section();

		// ----- Mobile menu -----
		$this->start_controls_section( 'section_mobile_style', [
			'label'     => 'Mobile Menu - Giao diện',
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => [ 'mobile_breakpoint!' => '0' ],
		] );

		$this->add_control( 'heading_trigger', [
			'label' => 'Nút mở menu',
			'type'  => Controls_Manager::HEADING,
		] );

		$this->add_control( 'trigger_icon_size', [
			'label'     => 'Kích thước icon',
			'type'      => Controls_Manager::SLIDER,
			'range'     => [ 'px' => [ 'min' => 10, 'max' => 60 ] ],
			'default'   => [ 'unit' => 'px', 'size' => 24 ],
			'selectors' => [
				'{{WRAPPER}} .xd-menu-trigger-btn i'   => 'font-size: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .xd-menu-trigger-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'trigger_icon_color', [
			'label'     => 'Màu icon',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-trigger-btn i'   => 'color: {{VALUE}};',
				'{{WRAPPER}} .xd-menu-trigger-btn svg' => 'fill: {{VALUE}};',
			],
		] );

		$this->add_control( 'trigger_bg', [
			'label'     => 'Màu nền nút',
			'type'      => Controls_Manager::COLOR,
			'default'   => 'transparent',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-trigger-btn' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'trigger_padding', [
			'label'      => 'Padding nút',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'default'    => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-trigger-btn' => $this->dims( 'padding' ),
			],
		] );

		$this->add_control( 'trigger_radius', [
			'label'      => 'Bo góc nút',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-trigger-btn' => $this->dims( 'border-radius' ),
			],
		] );

		$this->add_control( 'heading_mobile_container', [
			'label'     => 'Khung menu mobile',
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_control( 'mobile_width', [
			'label'      => 'Độ rộng (%)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ '%' ],
			'range'      => [ '%' => [ 'min' => 30, 'max' => 100 ] ],
			'default'    => [ 'unit' => '%', 'size' => 80 ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-width: {{SIZE}}%;',
			],
		] );

		$this->add_control( 'mobile_bg', [
			'label'     => 'Màu nền',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-bg: {{VALUE}};',
			],
		] );

		// Trước v3.3 control này áp cả desktop (lỗi). Giờ chỉ áp trong khung mobile.
		$this->add_responsive_control( 'mobile_container_padding', [
			'label'      => 'Padding khung',
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .xd-menu-nav' => $this->dims( '--xd-m-pad' ),
			],
		] );

		$this->add_control( 'mobile_item_color', [
			'label'     => 'Màu chữ mục menu',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'mobile_toggle_color', [
			'label'     => 'Màu nút mũi tên (mở menu con)',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-toggle-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'heading_mobile_item_border', [
			'label'     => 'Đường kẻ giữa các mục',
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_control( 'mobile_item_border_style', [
			'label'     => 'Kiểu đường kẻ',
			'type'      => Controls_Manager::SELECT,
			'options'   => [
				'none'   => 'Không dùng',
				'solid'  => 'Nét liền (Solid)',
				'dashed' => 'Nét đứt (Dashed)',
				'dotted' => 'Chấm (Dotted)',
			],
			'default'   => 'none',
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-bd-style: {{VALUE}};',
			],
		] );

		$this->add_control( 'mobile_item_border_width', [
			'label'     => 'Độ dày (px)',
			'type'      => Controls_Manager::SLIDER,
			'range'     => [ 'px' => [ 'min' => 1, 'max' => 10 ] ],
			'default'   => [ 'unit' => 'px', 'size' => 1 ],
			'condition' => [ 'mobile_item_border_style!' => 'none' ],
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-bd-w: {{SIZE}}px;',
			],
		] );

		$this->add_control( 'mobile_item_border_color', [
			'label'     => 'Màu đường kẻ',
			'type'      => Controls_Manager::COLOR,
			'default'   => '#eeeeee',
			'condition' => [ 'mobile_item_border_style!' => 'none' ],
			'selectors' => [
				'{{WRAPPER}} .xd-menu-nav' => '--xd-m-bd-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'heading_mobile_back', [
			'label'     => 'Thanh tiêu đề menu con (nút quay lại)',
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'mobile_back_typography',
			'selector' => '{{WRAPPER}} .xd-mobile-back-btn',
		] );

		$this->add_control( 'mobile_back_color', [
			'label'     => 'Màu chữ',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .xd-mobile-back-btn' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'mobile_back_bg', [
			'label'     => 'Màu nền',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .xd-mobile-back-btn' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'mobile_back_border_color', [
			'label'     => 'Màu đường kẻ dưới',
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .xd-mobile-back-btn' => 'border-bottom-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();
	}

	/* ================= RENDER ================= */

	/** Icon mũi tên (SVG có sẵn hoặc icon chọn trong control). */
	private function get_angle_icon_html( $settings ) {
		if ( ! empty( $settings['submenu_icon']['value'] ) ) {
			ob_start();
			Icons_Manager::render_icon( $settings['submenu_icon'], [ 'aria-hidden' => 'true' ] );
			return ob_get_clean();
		}
		return '<svg class="xd-svg-stroke" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	private function get_back_icon_html() {
		return '<svg class="xd-svg-stroke" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * HTML nội dung mega của 1 menu item.
	 */
	private function get_mega_html( $template_id, $content, $use_cache ) {
		$template_id = absint( $template_id );

		if ( $template_id ) {
			if ( isset( self::$rendering_templates[ $template_id ] ) || ! class_exists( '\Elementor\Plugin' ) ) {
				return '';
			}

			$elementor  = \Elementor\Plugin::$instance;
			$is_editing = ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() )
				|| ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() );

			$can_cache = $use_cache && ! $is_editing && ! is_user_logged_in();
			$cache_key = '';

			if ( $can_cache ) {
				$modified  = get_post_field( 'post_modified_gmt', $template_id );
				$cache_key = 'xd_mm_t' . xd_mm_cache_ver() . '_' . $template_id . '_' . md5( $modified . '|' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' ) . '|' . ( defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' ) . '|' . XD_MM_VERSION );
				$cached    = get_transient( $cache_key );
				if ( is_array( $cached ) && isset( $cached['html'] ) ) {
					// Nạp lại CSS/JS mà template và các widget bên trong cần.
					$post_css = '\Elementor\Core\Files\CSS\Post';
					if ( class_exists( $post_css ) ) {
						$post_css::create( $template_id )->enqueue();
					}
					foreach ( (array) $cached['styles'] as $h ) {
						if ( wp_style_is( $h, 'registered' ) ) {
							wp_enqueue_style( $h );
						} elseif ( class_exists( $post_css ) && preg_match( '/^elementor-post-(\d+)$/', $h, $m ) ) {
							// File CSS của template lồng bên trong – Elementor chỉ đăng ký khi enqueue.
							$post_css::create( (int) $m[1] )->enqueue();
						}
					}
					foreach ( (array) $cached['scripts'] as $h ) {
						if ( wp_script_is( $h, 'registered' ) ) {
							wp_enqueue_script( $h );
						}
					}
					return $cached['html'];
				}
			}

			$styles_before  = wp_styles()->queue;
			$scripts_before = wp_scripts()->queue;

			self::$rendering_templates[ $template_id ] = true;
			$html = $elementor->frontend->get_builder_content_for_display( $template_id, true );
			unset( self::$rendering_templates[ $template_id ] );

			if ( $can_cache && '' !== trim( (string) $html ) ) {
				set_transient( $cache_key, [
					'html'    => $html,
					'styles'  => array_values( array_diff( wp_styles()->queue, $styles_before ) ),
					'scripts' => array_values( array_diff( wp_scripts()->queue, $scripts_before ) ),
				], 12 * HOUR_IN_SECONDS );
			}

			return (string) $html;
		}

		if ( '' !== trim( (string) $content ) ) {
			return do_shortcode( $content );
		}

		return '';
	}

	/**
	 * Render đệ quy các mục menu (dropdown thường + mega menu).
	 *
	 * @param array $by_parent  Mảng parent_id => [items] (đã sắp theo menu_order).
	 */
	private function render_menu_items( array $by_parent, $parent_id, $depth, array $ctx ) {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return;
		}

		foreach ( $by_parent[ $parent_id ] as $item ) {
			$is_mega = '1' === get_post_meta( $item->ID, '_xd_mega', true );

			$mega_html = '';
			if ( $is_mega ) {
				$mega_html = $this->get_mega_html(
					get_post_meta( $item->ID, '_xd_mega_template', true ),
					get_post_meta( $item->ID, '_xd_mega_content', true ),
					$ctx['use_cache']
				);
			}

			$has_children = ! empty( $by_parent[ $item->ID ] );
			$has_mega     = $is_mega && '' !== trim( $mega_html );
			$has_submenu  = $has_mega || $has_children;
			$wp_classes   = (array) $item->classes;
			$is_current   = in_array( 'current-menu-item', $wp_classes, true );
			$is_ancestor  = in_array( 'current-menu-ancestor', $wp_classes, true ) || in_array( 'current-menu-parent', $wp_classes, true );

			$classes = [ 'xd-menu-item', 'xd-menu-depth-' . $depth ];
			if ( $has_mega ) {
				$classes[] = 'xd-menu-has-mega';
			}
			if ( $has_children ) {
				$classes[] = 'xd-menu-has-children';
			}
			if ( $has_submenu ) {
				$classes[] = 'xd-menu-has-submenu';
			}
			if ( $is_current || $is_ancestor ) {
				$classes[] = 'xd-menu-current';
			}
			// Class CSS do admin nhập trong Giao diện > Menu.
			if ( ! empty( $ctx['custom_classes'][ $item->ID ] ) ) {
				$classes = array_merge( $classes, $ctx['custom_classes'][ $item->ID ] );
			}

			$panel_id = 'xd-sub-' . $ctx['widget_id'] . '-' . $item->ID;

			$attrs = [
				'href'  => $item->url ? esc_url( $item->url ) : '#',
				'class' => 'xd-menu-link',
			];
			if ( ! empty( $item->target ) ) {
				$attrs['target'] = $item->target;
			}
			$rel = trim( (string) $item->xfn );
			if ( '_blank' === $item->target && false === strpos( $rel, 'noopener' ) ) {
				$rel = trim( $rel . ' noopener' );
			}
			if ( '' !== $rel ) {
				$attrs['rel'] = $rel;
			}
			if ( ! empty( $item->attr_title ) ) {
				$attrs['title'] = $item->attr_title;
			}
			if ( $is_current ) {
				$attrs['aria-current'] = 'page';
			}

			$attr_html = '';
			foreach ( $attrs as $k => $v ) {
				$attr_html .= ' ' . $k . '="' . ( 'href' === $k ? $v : esc_attr( $v ) ) . '"';
			}

			$title = $item->title;

			echo '<li class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '">';

			echo '<a' . $attr_html . '>';
			echo '<span class="xd-menu-title">' . esc_html( $title ) . '</span>';
			if ( $has_submenu ) {
				echo '<span class="xd-icon-angle" aria-hidden="true">' . $ctx['angle_icon'] . '</span>';
			}
			echo '</a>';

			if ( $has_submenu ) {
				// Nút riêng để mở menu con: desktop ẩn (cho bàn phím/trình đọc màn hình), mobile hiện bên phải mục.
				echo '<button type="button" class="xd-submenu-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '" aria-label="' . esc_attr( 'Menu con: ' . $title ) . '">';
				echo '<span class="xd-icon-angle xd-toggle-icon" aria-hidden="true">' . $ctx['angle_icon'] . '</span>';
				echo '</button>';
			}

			$panel_header = '<div class="xd-mobile-panel-header">'
				. '<button type="button" class="xd-mobile-back-btn" aria-label="' . esc_attr( 'Quay lại: ' . $title ) . '">'
				. '<span class="xd-mobile-back-title">' . esc_html( $title ) . '</span>'
				. '<span class="xd-back-icon" aria-hidden="true">' . $ctx['back_icon'] . '</span>'
				. '</button>'
				. '</div>';

			if ( $has_mega ) {
				$width      = xd_mm_sanitize_width( get_post_meta( $item->ID, '_xd_mega_width', true ) );
				$mega_class = 'xd-menu-mega';
				$style      = '';

				if ( 'full' === $width ) {
					$mega_class .= ' xd-menu-mega-full';
				} elseif ( '' !== $width ) {
					$style = '--xd-mega-w:' . $width . ';';
				}

				echo '<div id="' . esc_attr( $panel_id ) . '" class="' . esc_attr( $mega_class ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
				echo $panel_header;
				echo '<div class="xd-mega-content-inner">';
				echo $mega_html; // HTML của Elementor template / shortcode.
				echo '</div>';
				echo '</div>';
			} elseif ( $has_children ) {
				echo '<div id="' . esc_attr( $panel_id ) . '" class="xd-sub-menu">';
				echo $panel_header;
				echo '<ul class="xd-sub-menu-list">';
				$this->render_menu_items( $by_parent, $item->ID, $depth + 1, $ctx );
				echo '</ul>';
				echo '</div>';
			}

			echo '</li>';
		}
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['menu'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="padding:10px;background:#f5f5f5;">Vui lòng chọn một menu ở phần Nội dung.</p>';
			}
			return;
		}

		$menu  = wp_get_nav_menu_object( $settings['menu'] );
		$items = $menu ? wp_get_nav_menu_items( $menu->term_id, [ 'update_post_term_cache' => false ] ) : false;
		if ( empty( $items ) ) {
			return;
		}

		// Lưu class CSS admin tự nhập trước khi WP thêm class ngữ cảnh.
		$custom_classes = [];
		foreach ( $items as $item ) {
			$cls = array_filter( array_map( 'sanitize_html_class', (array) $item->classes ) );
			if ( $cls ) {
				$custom_classes[ $item->ID ] = $cls;
			}
		}

		// WP chỉ tự gắn current-menu-item… trong wp_nav_menu(); widget tự dựng nên phải gọi thủ công.
		if ( function_exists( '_wp_menu_item_classes_by_context' ) ) {
			_wp_menu_item_classes_by_context( $items );
		}

		// Gom theo parent: O(n), giữ nguyên thứ tự menu_order mà WP đã sắp.
		$by_parent = [];
		foreach ( $items as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}

		$breakpoint = (int) $settings['mobile_breakpoint'];
		if ( ! in_array( $breakpoint, [ 0, 480, 767, 1024 ], true ) ) {
			$breakpoint = 767;
		}

		$widget_id = $this->get_id();
		$menu_id   = 'xd-menu-' . $widget_id;

		$ctx = [
			'widget_id'      => $widget_id,
			'angle_icon'     => $this->get_angle_icon_html( $settings ),
			'back_icon'      => $this->get_back_icon_html(),
			'use_cache'      => 'yes' === ( $settings['mega_cache'] ?? '' ),
			'custom_classes' => $custom_classes,
		];

		$nav_class = 'xd-menu-nav' . ( $breakpoint > 0 ? ' xd-bp-' . $breakpoint : '' );

		echo '<nav class="' . esc_attr( $nav_class ) . '" data-breakpoint="' . esc_attr( $breakpoint ) . '" aria-label="' . esc_attr( $menu->name ) . '">';

		if ( $breakpoint > 0 ) {
			echo '<div class="xd-menu-trigger">';
			echo '<button type="button" class="xd-menu-trigger-btn" aria-label="Mở menu" aria-expanded="false" aria-controls="' . esc_attr( $menu_id ) . '">';
			echo '<span class="xd-trigger-icon-open">';
			Icons_Manager::render_icon( $settings['trigger_icon_open'], [ 'aria-hidden' => 'true' ] );
			echo '</span>';
			echo '<span class="xd-trigger-icon-close">';
			Icons_Manager::render_icon( $settings['trigger_icon_close'], [ 'aria-hidden' => 'true' ] );
			echo '</span>';
			echo '</button>';
			echo '</div>';
		}

		echo '<ul class="xd-menu" id="' . esc_attr( $menu_id ) . '">';
		if ( $breakpoint > 0 ) {
			// Nút đóng cho bàn phím / trình đọc màn hình (chỉ hiện khi được focus).
			echo '<li class="xd-menu-a11y"><button type="button" class="xd-menu-close-btn">Đóng menu</button></li>';
		}
		$this->render_menu_items( $by_parent, 0, 0, $ctx );
		echo '</ul>';

		if ( $breakpoint > 0 ) {
			echo '<div class="xd-menu-overlay" aria-hidden="true"></div>';
		}

		echo '</nav>';
	}
}
