<?php
/**
 * Hộp ảnh XD – ảnh + dòng nhỏ + tiêu đề + mô tả + link, nhiều kiểu bố cục.
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Image_Box extends Base {

	protected $exd_slug  = 'image-box';
	protected $exd_title = 'Hộp ảnh XD';
	protected $exd_icon  = 'eicon-image-box';
	protected $exd_keys  = array( 'image', 'box', 'card', 'hop anh', 'ảnh' );
	protected $exd_style = array( 'exd-fx' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'card'    => 'Thẻ (ảnh trên, chữ dưới)',
				'plain'   => 'Đơn giản (không khung)',
				'overlay' => 'Chữ trên ảnh',
				'reveal'  => 'Hiện chữ khi hover',
				'push'    => 'Hộp chữ đè lên ảnh',
				'float'   => 'Hộp chữ nổi trong ảnh',
				'side'    => 'Ảnh bên trái',
			),
			'card'
		);
		$this->add_control(
			'image',
			array(
				'label'   => 'Ảnh',
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => Utils::get_placeholder_image_src() ),
			)
		);
		$this->add_control(
			'img_size',
			array(
				'label'   => 'Cỡ ảnh tải về',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
					'full'         => 'Gốc',
				),
				'default' => 'large',
			)
		);
		$this->exd_ratio( '.exd-imb__media' );
		$this->add_control(
			'eyebrow',
			array(
				'label'     => 'Dòng nhỏ',
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Không gian xanh giữa lòng phố',
				'label_block' => true,
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ tiêu đề',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), array( 'H2', 'H3', 'H4', 'H5', 'H6', 'div' ) ),
				'default' => 'h3',
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => 'Mô tả',
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array( 'active' => true ),
				'default' => 'Mô tả ngắn gọn, nêu điểm nổi bật để khách bấm xem.',
			)
		);
		$this->add_control(
			'link',
			array(
				'label'     => 'Link',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'       => 'Chữ link',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Xem chi tiết',
			)
		);
		$this->exd_fx( 'zoom' );
		$this->exd_align( '.exd-imb__body' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Hộp', '.exd-imb' );
		$this->exd_style_image( 'img', 'Ảnh', '.exd-imb__media img', array( 'wrap' => '.exd-imb__media', 'hover_on' => '.exd-imb' ) );
		$this->start_controls_section(
			'st_overlay',
			array(
				'label' => 'Lớp phủ ảnh',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'ov',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => '{{WRAPPER}} .exd-imb__media::before',
			)
		);
		$this->add_control(
			'ov_op',
			array(
				'label'     => 'Độ đậm',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-imb__media::before' => 'opacity: {{SIZE}};' ),
			)
		);
		$this->add_control(
			'ov_hop',
			array(
				'label'     => 'Độ đậm khi hover',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-imb:hover .exd-imb__media::before' => 'opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_section();
		$this->exd_style_box( 'body', 'Khung chữ', '.exd-imb__body', array( 'hover_on' => '.exd-imb' ) );
		$this->exd_style_text( 'eyebrow', 'Dòng nhỏ', '.exd-imb__eyebrow', array( 'hover_on' => '.exd-imb' ) );
		$this->exd_style_text( 'title', 'Tiêu đề', '.exd-imb__title', array( 'hover_on' => '.exd-imb' ) );
		$this->exd_style_text( 'text', 'Mô tả', '.exd-imb__text', array( 'hover_on' => '.exd-imb' ) );
		$this->exd_style_text( 'more', 'Chữ link', '.exd-imb__more', array( 'gap' => false, 'hover_on' => '.exd-imb' ) );
	}

	protected function render() {
		$s   = $this->exd_settings();
		$tag = H::tag( $s['tag'], 'h3' );

		$has_link = ! empty( $s['link']['url'] );
		echo '<div class="exd-imb exd-fx-host">';
		echo '<div class="exd-imb__media exd-media">' . H::img( $s['image'], $s['img_size'] ?: 'large', array( 'sizes' => H::sizes( 3 ), 'alt' => $s['title'] ) ) . '</div>'; // phpcs:ignore
		echo '<div class="exd-imb__body">';
		if ( '' !== $s['eyebrow'] ) {
			echo '<span class="exd-imb__eyebrow">' . esc_html( $s['eyebrow'] ) . '</span>';
		}
		if ( '' !== $s['title'] ) {
			echo '<' . $tag . ' class="exd-imb__title">' . esc_html( $s['title'] ) . '</' . $tag . '>'; // phpcs:ignore
		}
		echo '<div class="exd-imb__extra"><div class="exd-imb__extra-in">';
		if ( '' !== $s['text'] ) {
			echo '<div class="exd-imb__text">' . wp_kses_post( $s['text'] ) . '</div>';
		}
		if ( $has_link && '' !== $s['link_text'] ) {
			echo '<span class="exd-imb__more">' . esc_html( $s['link_text'] ) . H::arrow() . '</span>'; // phpcs:ignore
		}
		echo '</div></div></div>';
		if ( $has_link ) {
			$this->add_render_attribute( 'link', 'class', 'exd-cover-link' );
			$this->add_link_attributes( 'link', $s['link'] );
			echo '<a ' . $this->get_render_attribute_string( 'link' ) . '><span class="exd-sr">' . esc_html( $s['title'] ?: $s['link_text'] ) . '</span></a>'; // phpcs:ignore
		}
		echo '</div>';
	}
}
