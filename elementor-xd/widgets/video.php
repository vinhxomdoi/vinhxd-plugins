<?php
/**
 * Nút video XD – nút play mở video trong cửa sổ nổi. Iframe chỉ được tạo khi bấm.
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Video_Button extends Base {

	protected $exd_slug    = 'video';
	protected $exd_title   = 'Nút video XD';
	protected $exd_icon    = 'eicon-play';
	protected $exd_keys    = array( 'video', 'play', 'youtube', 'button', 'nut video' );
	protected $exd_style   = array( 'exd-fx', 'exd-modal' );
	protected $exd_scripts = array( 'exd-modal' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'solid'   => 'Nền màu',
				'light'   => 'Nền trắng',
				'outline' => 'Viền',
				'glass'   => 'Kính mờ',
			),
			'solid'
		);
		$this->add_control(
			'url',
			array(
				'label'       => 'Link video',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
				'placeholder' => 'YouTube, Vimeo hoặc file .mp4',
				'label_block' => true,
			)
		);
		$this->add_control(
			'label',
			array(
				'label'   => 'Chữ cạnh nút',
				'type'    => Controls_Manager::TEXT,
				'default' => 'Xem video giới thiệu',
			)
		);
		$this->add_control(
			'pulse',
			array(
				'label'        => 'Hiệu ứng sóng lan',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'prefix_class' => 'exd-pulse-',
			)
		);
		$this->add_control(
			'cover',
			array(
				'label'       => 'Ảnh bìa (tuỳ chọn)',
				'type'        => Controls_Manager::MEDIA,
				'dynamic'     => array( 'active' => true ),
				'separator'   => 'before',
				'description' => 'Có ảnh bìa → thành khung video, nút play ở giữa.',
			)
		);
		$this->add_control(
			'yt_thumb',
			array(
				'label'        => 'Không chọn ảnh bìa: dùng ảnh bìa YouTube',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->exd_ratio( '.exd-vb__cover' );
		$this->add_responsive_control(
			'align',
			array(
				'label'     => 'Căn lề',
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => 'Trái', 'icon' => 'eicon-text-align-left' ),
					'center'     => array( 'title' => 'Giữa', 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => 'Phải', 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .exd-vb' => 'justify-content: {{VALUE}};' ),
			)
		);
		$this->exd_fx( 'zoom' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->start_controls_section(
			'st_btn',
			array(
				'label' => 'Nút play',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'size',
			array(
				'label'     => 'Cỡ nút',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 32, 'max' => 160 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-vb' => '--exd-vb: {{SIZE}}px;' ),
			)
		);
		$this->start_controls_tabs( 'btn_tabs' );
		foreach ( array( 'n' => 'Thường', 'h' => 'Hover' ) as $st => $tl ) {
			$sel = 'n' === $st ? '{{WRAPPER}} .exd-vb__icon' : '{{WRAPPER}} .exd-vb:hover .exd-vb__icon';
			$this->start_controls_tab( 'btn_tab_' . $st, array( 'label' => $tl ) );
			$this->add_control(
				'btn_' . $st . '_color',
				array(
					'label'     => 'Màu icon',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $sel => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'btn_' . $st . '_bg',
				array(
					'label'     => 'Màu nền',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $sel => 'background-color: {{VALUE}}; --exd-vb-ring: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'btn_' . $st . '_bc',
				array(
					'label'     => 'Màu viền',
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $sel => 'border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'btn_shadow',
				'selector' => '{{WRAPPER}} .exd-vb__icon',
			)
		);
		$this->end_controls_section();
		$this->exd_style_text( 'label', 'Chữ cạnh nút', '.exd-vb__label', array( 'gap' => false, 'hover_on' => '.exd-vb' ) );
		$this->exd_style_image( 'cover', 'Ảnh bìa', '.exd-vb__cover img', array( 'wrap' => '.exd-vb__cover', 'hover_on' => '.exd-vb' ) );
	}

	protected function render() {
		$s = $this->exd_settings();
		$v = H::video( $s['url'] );
		if ( ! $v ) {
			return;
		}
		$cover = '';
		if ( ! empty( $s['cover']['url'] ) ) {
			$cover = H::img( $s['cover'], 'large', array( 'alt' => $s['label'], 'sizes' => H::sizes( 2 ) ) );
		} elseif ( 'yes' === $s['yt_thumb'] && ! empty( $v['id'] ) ) {
			$cover = '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg' ) . '" alt="' . esc_attr( $s['label'] ) . '" loading="lazy" decoding="async" width="480" height="360">';
		}
		$data = ' data-exd-video="' . esc_attr( $v['src'] ) . '" data-exd-vtype="' . esc_attr( $v['type'] ) . '"';
		$aria = $s['label'] ? $s['label'] : 'Xem video';

		echo '<div class="exd-vb' . ( $cover ? ' exd-vb--cover exd-fx-host' : '' ) . '"' . ( $cover ? $data : '' ) . '>'; // phpcs:ignore
		if ( $cover ) {
			echo '<div class="exd-vb__cover exd-media">' . $cover . '</div>'; // phpcs:ignore
		}
		echo '<button type="button" class="exd-vb__btn"' . $data . ' aria-label="' . esc_attr( $aria ) . '">'; // phpcs:ignore
		echo '<span class="exd-vb__icon">' . H::play() . '</span>'; // phpcs:ignore
		if ( '' !== $s['label'] ) {
			echo '<span class="exd-vb__label">' . esc_html( $s['label'] ) . '</span>';
		}
		echo '</button></div>';
	}
}
