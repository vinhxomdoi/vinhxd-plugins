<?php
/**
 * Nhóm control Style dùng chung cho mọi widget XD.
 *
 * Quy ước:
 * - Mọi selector gắn {{WRAPPER}} cho TỪNG phần (w()).
 * - Preset là class trên wrapper (prefix_class exd-s-*), CSS preset viết bằng :where() nên
 *   control Style luôn thắng mà không cần !important.
 * - Control mới KHÔNG đặt default sinh CSS (để nâng cấp không đổi giao diện trang cũ).
 *
 * @package Elementor_XD
 */

namespace EXD;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;

defined( 'ABSPATH' ) || exit;

trait Controls {

	/** Gắn {{WRAPPER}} cho từng phần của selector nhiều phần. */
	protected function w( $sel ): string {
		$parts = array_map( 'trim', explode( ',', $sel ) );
		return implode(
			', ',
			array_map(
				static function ( $p ) {
					return '' === $p ? '{{WRAPPER}}' : '{{WRAPPER}} ' . $p;
				},
				$parts
			)
		);
	}

	/** Gắn :hover cho từng phần. */
	protected function hov( $sel, $hover_on = '' ): string {
		$parts = array_map( 'trim', explode( ',', $sel ) );
		return implode(
			', ',
			array_map(
				static function ( $p ) use ( $hover_on ) {
					return $hover_on ? '{{WRAPPER}} ' . $hover_on . ':hover ' . $p : '{{WRAPPER}} ' . $p . ':hover';
				},
				$parts
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Content helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Control chọn kiểu (preset). Đổi kiểu chỉ đổi class → giữ nguyên nội dung & Style đã chỉnh.
	 */
	protected function exd_preset( array $options, string $default, string $label = 'Kiểu (preset)', string $key = 'preset', string $prefix = 'exd-s-' ) {
		$this->add_control(
			$key,
			array(
				'label'        => $label,
				'type'         => Controls_Manager::SELECT,
				'options'      => $options,
				'default'      => $default,
				'prefix_class' => $prefix,
				'separator'    => 'after',
			)
		);
	}

	/** Hiệu ứng ảnh khi hover (CSS ở assets/fx.css). */
	protected function exd_fx( $default = 'zoom' ) {
		$this->add_control(
			'fx',
			array(
				'label'        => 'Hiệu ứng ảnh khi hover',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'none'      => 'Không',
					'zoom'      => 'Phóng to',
					'zoom-out'  => 'Thu nhỏ',
					'gray'      => 'Đen trắng → màu',
					'blur'      => 'Mờ nhẹ',
					'bright'    => 'Sáng lên',
					'rotate'    => 'Xoay nhẹ',
					'shine'     => 'Vệt sáng lướt',
				),
				'default'      => $default,
				'prefix_class' => 'exd-fx-',
			)
		);
	}

	/** Căn lề chữ (responsive). */
	protected function exd_align( $sel = '', $key = 'align', $label = 'Căn lề' ) {
		$this->add_responsive_control(
			$key,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => 'Trái', 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => 'Giữa', 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => 'Phải', 'icon' => 'eicon-text-align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'text-align:left; --exd-ml:0; --exd-mr:auto; --exd-jc:flex-start;',
					'center' => 'text-align:center; --exd-ml:auto; --exd-mr:auto; --exd-jc:center; --exd-basis:100%;',
					'right'  => 'text-align:right; --exd-ml:auto; --exd-mr:0; --exd-jc:flex-end;',
				),
				'selectors' => array(
					$this->w( $sel ) => '{{VALUE}}',
				),
			)
		);
	}

	/** Tỷ lệ khung ảnh (responsive). */
	protected function exd_ratio( $sel, $key = 'ratio', $label = 'Tỷ lệ khung ảnh' ) {
		$this->add_responsive_control(
			$key,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''     => 'Theo kiểu',
					'1/1'  => '1:1 vuông',
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'21/9' => '21:9',
					'3/4'  => '3:4 đứng',
					'2/3'  => '2:3 đứng',
					'auto' => 'Theo ảnh gốc',
				),
				'selectors' => array(
					$this->w( $sel ) => 'aspect-ratio: {{VALUE}};',
				),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Style sections
	 * ------------------------------------------------------------------ */

	/**
	 * Section Style cho 1 phần chữ: typography, màu, màu hover, khoảng cách dưới.
	 *
	 * @param array $o hover_on (selector cha để hover), condition, gap (bool), shadow (bool).
	 */
	protected function exd_style_text( $id, $label, $sel, $o = array() ) {
		$this->start_controls_section(
			'st_' . $id,
			array(
				'label'     => $label,
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $o['condition'] ?? array(),
			)
		);
		$this->exd_text_controls( $id, $sel, $o );
		$this->end_controls_section();
	}

	/** Bộ control chữ (không mở section) – dùng lồng trong section khác. */
	protected function exd_text_controls( $id, $sel, $o = array() ) {
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $this->w( $sel ),
			)
		);
		$this->add_control(
			$id . '_color',
			array(
				'label'     => 'Màu chữ',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $this->w( $sel ) => 'color: {{VALUE}};' ),
			)
		);
		if ( ( $o['hover'] ?? true ) ) {
			$this->add_control(
				$id . '_hcolor',
				array(
					'label'     => 'Màu chữ khi hover',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $this->hov( $sel, $o['hover_on'] ?? '' ) => 'color: {{VALUE}};' ),
				)
			);
		}
		if ( ! empty( $o['shadow'] ) ) {
			$this->add_group_control(
				Group_Control_Text_Shadow::get_type(),
				array(
					'name'     => $id . '_tshadow',
					'selector' => $this->w( $sel ),
				)
			);
		}
		if ( $o['gap'] ?? true ) {
			$this->add_responsive_control(
				$id . '_gap',
				array(
					'label'      => 'Khoảng cách dưới',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array( 'px' => array( 'max' => 100 ) ),
					'selectors'  => array( $this->w( $sel ) => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
		}
	}

	/**
	 * Section Style cho hộp/thẻ: padding, bo góc, Normal/Hover (nền, viền, bóng, nâng lên, transition).
	 *
	 * @param array $o hover_on, condition, padding (bool), section (bool).
	 */
	protected function exd_style_box( $id, $label, $sel, $o = array() ) {
		$section = $o['section'] ?? true;
		if ( $section ) {
			$this->start_controls_section(
				'st_' . $id,
				array(
					'label'     => $label,
					'tab'       => Controls_Manager::TAB_STYLE,
					'condition' => $o['condition'] ?? array(),
				)
			);
		}
		if ( $o['padding'] ?? true ) {
			$this->add_responsive_control(
				$id . '_pad',
				array(
					'label'      => 'Padding',
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array( $this->w( $sel ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
		}
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => 'Bo góc',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $this->w( $sel ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$hsel = $this->hov( $sel, $o['hover_on'] ?? '' );

		$this->start_controls_tabs( $id . '_tabs' );
		$this->start_controls_tab( $id . '_tab_n', array( 'label' => 'Thường' ) );
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => $id . '_bg',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => $this->w( $sel ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => $this->w( $sel ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $id . '_shadow',
				'selector' => $this->w( $sel ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( $id . '_tab_h', array( 'label' => 'Hover' ) );
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => $id . '_hbg',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => $hsel,
			)
		);
		$this->add_control(
			$id . '_hborder',
			array(
				'label'     => 'Màu viền',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $hsel => 'border-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $id . '_hshadow',
				'selector' => $hsel,
			)
		);
		$this->add_control(
			$id . '_lift',
			array(
				'label'      => 'Nâng lên (px)',
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => -20, 'max' => 20 ) ),
				'selectors'  => array( $hsel => 'transform: translateY({{SIZE}}px);' ),
			)
		);
		$this->add_control(
			$id . '_dur',
			array(
				'label'     => 'Thời gian chuyển (ms)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1500, 'step' => 50 ) ),
				'selectors' => array( $this->w( $sel ) => 'transition-duration: {{SIZE}}ms;' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		if ( $section ) {
			$this->end_controls_section();
		}
	}

	/**
	 * Section Style cho nút.
	 */
	protected function exd_style_button( $id, $label, $sel, $o = array() ) {
		$this->start_controls_section(
			'st_' . $id,
			array(
				'label'     => $label,
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $o['condition'] ?? array(),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $this->w( $sel ),
			)
		);
		$this->add_responsive_control(
			$id . '_pad',
			array(
				'label'      => 'Padding',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $this->w( $sel ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => 'Bo góc',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $this->w( $sel ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => $this->w( $sel ),
			)
		);

		$hsel = $this->hov( $sel );
		$this->start_controls_tabs( $id . '_tabs' );
		foreach ( array( 'n' => 'Thường', 'h' => 'Hover' ) as $st => $tl ) {
			$s = 'n' === $st ? $this->w( $sel ) : $hsel . ', ' . str_replace( ':hover', ':focus-visible', $hsel );
			$this->start_controls_tab( $id . '_tab_' . $st, array( 'label' => $tl ) );
			$this->add_control(
				$id . '_' . $st . '_color',
				array(
					'label'     => 'Màu chữ',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $s => 'color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => $id . '_' . $st . '_bg',
					'types'    => array( 'classic', 'gradient' ),
					'exclude'  => array( 'image' ),
					'selector' => $s,
				)
			);
			$this->add_control(
				$id . '_' . $st . '_bcolor',
				array(
					'label'     => 'Màu viền',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $s => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $id . '_' . $st . '_shadow',
					'selector' => $s,
				)
			);
			if ( 'h' === $st ) {
				$this->add_control(
					$id . '_lift',
					array(
						'label'     => 'Nâng lên (px)',
						'type'      => Controls_Manager::SLIDER,
						'range'     => array( 'px' => array( 'min' => -10, 'max' => 10 ) ),
						'selectors' => array( $s => 'transform: translateY({{SIZE}}px);' ),
					)
				);
			}
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/**
	 * Section Style cho ảnh: rộng, cao, object-fit, viền, bo góc, bóng, filter Normal/Hover.
	 *
	 * @param array $o hover_on, condition, size (bool), wrap (selector khung bo góc).
	 */
	protected function exd_style_image( $id, $label, $sel, $o = array() ) {
		$wrap = $o['wrap'] ?? $sel;
		$this->start_controls_section(
			'st_' . $id,
			array(
				'label'     => $label,
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $o['condition'] ?? array(),
			)
		);
		if ( $o['size'] ?? true ) {
			$this->add_responsive_control(
				$id . '_w',
				array(
					'label'      => 'Chiều rộng',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%', 'vw' ),
					'range'      => array( 'px' => array( 'max' => 1200 ) ),
					'selectors'  => array( $this->w( $wrap ) => 'width: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				$id . '_h',
				array(
					'label'      => 'Chiều cao',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vh', 'em' ),
					'range'      => array( 'px' => array( 'max' => 1000 ) ),
					'selectors'  => array( $this->w( $wrap ) => 'height: {{SIZE}}{{UNIT}}; aspect-ratio: auto;' ),
				)
			);
		}
		$this->add_control(
			$id . '_fit',
			array(
				'label'     => 'Cách lấp ảnh',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''        => 'Mặc định',
					'cover'   => 'Cover (cắt vừa khung)',
					'contain' => 'Contain (hiện đủ ảnh)',
					'fill'    => 'Kéo giãn',
				),
				'selectors' => array( $this->w( $sel ) => 'object-fit: {{VALUE}};' ),
			)
		);
		$this->add_control(
			$id . '_pos',
			array(
				'label'     => 'Vị trí ảnh',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''              => 'Mặc định',
					'center center' => 'Giữa',
					'center top'    => 'Trên',
					'center bottom' => 'Dưới',
					'left center'   => 'Trái',
					'right center'  => 'Phải',
				),
				'selectors' => array( $this->w( $sel ) => 'object-position: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => $this->w( $wrap ),
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => 'Bo góc',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $this->w( $wrap ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $id . '_shadow',
				'selector' => $this->w( $wrap ),
			)
		);
		$hsel = $this->hov( $sel, $o['hover_on'] ?? '' );
		$this->start_controls_tabs( $id . '_tabs' );
		$this->start_controls_tab( $id . '_tab_n', array( 'label' => 'Thường' ) );
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => $id . '_filter',
				'selector' => $this->w( $sel ),
			)
		);
		$this->add_control(
			$id . '_op',
			array(
				'label'     => 'Độ trong',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( $this->w( $sel ) => 'opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( $id . '_tab_h', array( 'label' => 'Hover' ) );
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => $id . '_hfilter',
				'selector' => $hsel,
			)
		);
		$this->add_control(
			$id . '_hop',
			array(
				'label'     => 'Độ trong',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( $hsel => 'opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/**
	 * Section Style cho icon: cỡ, khung, màu, nền, viền, Normal/Hover, khoảng cách tới chữ.
	 */
	protected function exd_style_icon( $id, $label, $sel, $o = array() ) {
		$this->start_controls_section(
			'st_' . $id,
			array(
				'label'     => $label,
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $o['condition'] ?? array(),
			)
		);
		$this->add_responsive_control(
			$id . '_size',
			array(
				'label'      => 'Cỡ icon',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 200 ) ),
				'selectors'  => array( $this->w( $sel ) => '--exd-icon: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_box',
			array(
				'label'      => 'Cỡ khung',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 300 ) ),
				'selectors'  => array( $this->w( $sel ) => '--exd-icon-box: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_space',
			array(
				'label'      => 'Khoảng cách tới chữ',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 100 ) ),
				'selectors'  => array( $this->w( $o['space_on'] ?? '' ) => '--exd-icon-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => 'Bo góc khung',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $this->w( $sel ) => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => $this->w( $sel ),
			)
		);
		$hsel = $this->hov( $sel, $o['hover_on'] ?? '' );
		$this->start_controls_tabs( $id . '_tabs' );
		foreach ( array( 'n' => 'Thường', 'h' => 'Hover' ) as $st => $tl ) {
			$s = 'n' === $st ? $this->w( $sel ) : $hsel;
			$this->start_controls_tab( $id . '_tab_' . $st, array( 'label' => $tl ) );
			$this->add_control(
				$id . '_' . $st . '_color',
				array(
					'label'     => 'Màu icon',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $s => 'color: {{VALUE}}; --exd-icon-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_' . $st . '_bg',
				array(
					'label'     => 'Màu nền khung',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $s => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_' . $st . '_bc',
				array(
					'label'     => 'Màu viền',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $s => 'border-color: {{VALUE}};' ),
				)
			);
			if ( 'h' === $st ) {
				$this->add_control(
					$id . '_hscale',
					array(
						'label'     => 'Phóng to',
						'type'      => Controls_Manager::SLIDER,
						'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 1.5, 'step' => 0.05 ) ),
						'selectors' => array( $s => 'transform: scale({{SIZE}});' ),
					)
				);
			}
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/** Màu chủ đạo riêng cho 1 widget (ghi đè --exd-primary). */
	protected function exd_accent_control() {
		$this->add_control(
			'accent',
			array(
				'label'       => 'Màu nhấn của widget',
				'type'        => Controls_Manager::COLOR,
				'description' => 'Mặc định lấy màu Primary trong Site Settings của Elementor.',
				'selectors'   => array( '{{WRAPPER}}' => '--exd-primary: {{VALUE}};' ),
			)
		);
	}

	/** Control vị trí nội dung banner (ngang + dọc), responsive. */
	protected function exd_banner_position( $sel = '.exd-bn__inner', $prefix = '' ) {
		$this->add_responsive_control(
			$prefix . 'pos_x',
			array(
				'label'                => 'Vị trí ngang',
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array( 'title' => 'Trái', 'icon' => 'eicon-h-align-left' ),
					'center' => array( 'title' => 'Giữa', 'icon' => 'eicon-h-align-center' ),
					'right'  => array( 'title' => 'Phải', 'icon' => 'eicon-h-align-right' ),
				),
				'selectors_dictionary' => array(
					'left'   => 'justify-content:flex-start; text-align:left; --exd-jc:flex-start;',
					'center' => 'justify-content:center; text-align:center; --exd-jc:center;',
					'right'  => 'justify-content:flex-end; text-align:right; --exd-jc:flex-end;',
				),
				'selectors'            => array( '{{WRAPPER}} ' . $sel => '{{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			$prefix . 'pos_y',
			array(
				'label'                => 'Vị trí dọc',
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'top'    => array( 'title' => 'Trên', 'icon' => 'eicon-v-align-top' ),
					'middle' => array( 'title' => 'Giữa', 'icon' => 'eicon-v-align-middle' ),
					'bottom' => array( 'title' => 'Dưới', 'icon' => 'eicon-v-align-bottom' ),
				),
				'selectors_dictionary' => array(
					'top'    => 'align-items:flex-start;',
					'middle' => 'align-items:center;',
					'bottom' => 'align-items:flex-end;',
				),
				'selectors'            => array( '{{WRAPPER}} ' . $sel => '{{VALUE}}' ),
			)
		);
	}

	/** Các section Style chung cho banner (dùng ở Banner, Banner Grids, Slider). */
	protected function exd_banner_style( $o = array() ) {
		$this->start_controls_section(
			'st_bn_overlay',
			array(
				'label' => 'Lớp phủ ảnh',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'bn_ov',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => '{{WRAPPER}} .exd-bn__bg::before',
			)
		);
		$this->add_control(
			'bn_ov_op',
			array(
				'label'     => 'Độ đậm',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-bn__bg::before' => 'opacity: {{SIZE}};' ),
			)
		);
		$this->add_control(
			'bn_ov_hop',
			array(
				'label'     => 'Độ đậm khi hover',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-bn:hover .exd-bn__bg::before' => 'opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_section();

		$this->exd_style_box( 'bn_content', 'Khung nội dung', '.exd-bn__content', array( 'hover_on' => '.exd-bn' ) );
		$this->start_controls_section(
			'st_bn_width',
			array(
				'label' => 'Độ rộng & lề nội dung',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'bn_content_w',
			array(
				'label'      => 'Độ rộng tối đa',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 1400 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-bn__content' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'bn_inner_pad',
			array(
				'label'      => 'Lề trong banner',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .exd-bn__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->exd_style_text( 'bn_eyebrow', 'Dòng nhỏ', '.exd-bn__eyebrow', array( 'hover' => false ) );
		$this->exd_style_text( 'bn_title', 'Tiêu đề', '.exd-bn__title', array( 'hover_on' => '.exd-bn', 'shadow' => true ) );
		$this->exd_style_text( 'bn_text', 'Mô tả', '.exd-bn__text', array( 'hover' => false ) );
		if ( $o['buttons'] ?? true ) {
			$this->exd_style_button( 'bn_btn', 'Nút chính', '.exd-bn__btn' );
			$this->exd_style_button( 'bn_btn2', 'Nút phụ', '.exd-bn__btn2' );
		}
	}
}
