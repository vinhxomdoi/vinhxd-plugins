<?php
/**
 * Khách hàng XD – cảm nhận khách hàng (Testimonials).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Testimonial extends Base {

	protected $exd_slug  = 'testimonial';
	protected $exd_title = 'Khách hàng XD';
	protected $exd_icon  = 'eicon-testimonial';
	protected $exd_keys  = array( 'testimonial', 'review', 'khach hang', 'đánh giá', 'cảm nhận' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'card'    => 'Thẻ (tác giả dưới)',
				'bubble'  => 'Bong bóng hội thoại',
				'minimal' => 'Tối giản, chữ lớn',
				'side'    => 'Ảnh lớn bên trái',
				'quote'   => 'Dấu ngoặc lớn nền màu',
				'dark'    => 'Nền tối',
			),
			'card'
		);
		$this->add_control(
			'text',
			array(
				'label'   => 'Nội dung cảm nhận',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 5,
				'dynamic' => array( 'active' => true ),
				'default' => 'Dịch vụ chuyên nghiệp, phản hồi nhanh. Website mới tải rất nhanh và khách liên hệ nhiều hơn hẳn so với trước.',
			)
		);
		$this->add_control(
			'image',
			array(
				'label'     => 'Ảnh đại diện',
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'name',
			array(
				'label'   => 'Tên',
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
				'default' => 'Nguyễn Minh Anh',
			)
		);
		$this->add_control(
			'role',
			array(
				'label'   => 'Chức danh / công ty',
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
				'default' => 'Chủ cửa hàng, Bảo Lộc',
			)
		);
		$this->add_control(
			'link',
			array(
				'label'   => 'Link (tên tác giả)',
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'rating',
			array(
				'label'     => 'Số sao (0 = ẩn)',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 5,
				'step'      => 0.5,
				'default'   => 5,
				'separator' => 'before',
			)
		);
		$this->add_control(
			'quote_icon',
			array(
				'label'        => 'Hiện dấu ngoặc kép',
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);
		$this->exd_align( '.exd-tm' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Hộp', '.exd-tm' );
		$this->exd_style_image( 'avatar', 'Ảnh đại diện', '.exd-tm__avatar', array( 'hover_on' => '.exd-tm' ) );
		$this->exd_style_text( 'text', 'Nội dung', '.exd-tm__text', array( 'hover' => false ) );
		$this->exd_style_text( 'name', 'Tên', '.exd-tm__name', array( 'gap' => false ) );
		$this->exd_style_text( 'role', 'Chức danh', '.exd-tm__role', array( 'gap' => false, 'hover' => false ) );

		$this->start_controls_section(
			'st_extra',
			array(
				'label' => 'Sao & dấu ngoặc',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'star_color',
			array(
				'label'     => 'Màu sao',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-tm__stars' => '--exd-star: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'star_off',
			array(
				'label'     => 'Màu sao trống',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-tm__stars' => '--exd-star-off: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'star_size',
			array(
				'label'     => 'Cỡ sao',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-tm__stars' => 'font-size: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'q_color',
			array(
				'label'     => 'Màu dấu ngoặc',
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .exd-tm__q' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'q_size',
			array(
				'label'     => 'Cỡ dấu ngoặc',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 12, 'max' => 160 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-tm__q' => 'font-size: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->exd_settings();
		$rating = max( 0, min( 5, (float) $s['rating'] ) );

		echo '<figure class="exd-tm">';
		if ( 'yes' === $s['quote_icon'] ) {
			echo '<span class="exd-tm__q" aria-hidden="true"><svg viewBox="0 0 32 32" width="1em" height="1em"><path fill="currentColor" d="M13 7.5C8 9.2 4.5 13.6 4.5 19.4c0 3.6 2.2 6.1 5.3 6.1 2.7 0 4.7-2 4.7-4.6 0-2.5-1.8-4.4-4.2-4.4-.5 0-1 .1-1.3.2.6-2.9 3-5.6 5.6-6.7L13 7.5zm14 0c-5 1.7-8.5 6.1-8.5 11.9 0 3.6 2.2 6.1 5.3 6.1 2.7 0 4.7-2 4.7-4.6 0-2.5-1.8-4.4-4.2-4.4-.5 0-1 .1-1.3.2.6-2.9 3-5.6 5.6-6.7L27 7.5z"/></svg></span>';
		}
		if ( $rating > 0 ) {
			/* translators: %s: số sao */
			echo '<div class="exd-tm__stars" role="img" aria-label="' . esc_attr( sprintf( 'Đánh giá %s/5', $rating ) ) . '" style="--p:' . esc_attr( $rating * 20 ) . '%"></div>';
		}
		echo '<blockquote class="exd-tm__text">' . wp_kses_post( wpautop( $s['text'] ) ) . '</blockquote>';

		echo '<figcaption class="exd-tm__author">';
		if ( ! empty( $s['image']['url'] ) ) {
			echo H::img( $s['image'], 'thumbnail', array( 'class' => 'exd-tm__avatar', 'alt' => $s['name'], 'sizes' => '96px' ) ); // phpcs:ignore
		}
		echo '<span class="exd-tm__meta">';
		if ( '' !== $s['name'] ) {
			if ( ! empty( $s['link']['url'] ) ) {
				$this->add_link_attributes( 'link', $s['link'] );
				echo '<a class="exd-tm__name" ' . $this->get_render_attribute_string( 'link' ) . '>' . esc_html( $s['name'] ) . '</a>'; // phpcs:ignore
			} else {
				echo '<cite class="exd-tm__name">' . esc_html( $s['name'] ) . '</cite>';
			}
		}
		if ( '' !== $s['role'] ) {
			echo '<span class="exd-tm__role">' . esc_html( $s['role'] ) . '</span>';
		}
		echo '</span></figcaption></figure>';
	}
}
