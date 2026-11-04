<?php
/**
 * Tiêu đề XD – dòng nhỏ + tiêu đề + mô tả + link "Xem tất cả", nhiều kiểu đường kẻ (Titles / Dividers).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Title extends Base {

	protected $exd_slug  = 'title';
	protected $exd_title = 'Tiêu đề XD';
	protected $exd_icon  = 'eicon-t-letter';
	protected $exd_keys  = array( 'title', 'heading', 'divider', 'tieu de', 'tiêu đề', 'kẻ' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'plain'       => 'Đơn giản',
				'underline'   => 'Gạch chân ngắn',
				'line'        => 'Kẻ ngang tới link (divided)',
				'center-line' => 'Kẻ hai bên tiêu đề',
				'bar'         => 'Thanh nhấn bên trái',
				'pill'        => 'Dòng nhỏ dạng nhãn',
				'display'     => 'Chữ lớn (display)',
			),
			'underline'
		);

		$this->add_control(
			'eyebrow',
			array(
				'label'       => 'Dòng nhỏ phía trên',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Dịch vụ',
				'label_block' => true,
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Giải pháp *nổi bật* của chúng tôi',
				'description' => 'Bọc chữ trong *dấu sao* để tô màu nhấn.',
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ HTML',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'div', 'p' ) ),
				'default' => 'h2',
			)
		);
		$this->add_control(
			'desc',
			array(
				'label'   => 'Mô tả',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'     => 'Chữ link',
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'separator' => 'before',
				'placeholder' => 'Xem tất cả',
			)
		);
		$this->add_control(
			'link',
			array(
				'label'     => 'Link',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'link_text!' => '' ),
			)
		);
		$this->exd_align( '.exd-title' );
		$this->exd_accent_control();
		$this->end_controls_section();

		/* Style */
		$this->start_controls_section(
			'st_line',
			array(
				'label' => 'Đường kẻ / thanh nhấn',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'line_color',
			array(
				'label'     => 'Màu',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-title' => '--exd-line-c: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'line_size',
			array(
				'label'      => 'Độ dày',
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-title' => '--exd-line-h: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'line_w',
			array(
				'label'      => 'Độ dài (gạch chân / kẻ hai bên)',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'max' => 400 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-title' => '--exd-line-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'line_gap',
			array(
				'label'      => 'Khoảng cách',
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-title' => '--exd-line-gap: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();

		$this->exd_style_text( 'eyebrow', 'Dòng nhỏ', '.exd-title__eyebrow', array( 'hover' => false ) );
		$this->exd_style_text( 'title', 'Tiêu đề', '.exd-title__text', array( 'hover' => false, 'shadow' => true ) );
		$this->start_controls_section(
			'st_hl',
			array(
				'label' => 'Chữ nhấn *...*',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->exd_text_controls( 'hl', '.exd-title__text .exd-hl', array( 'hover' => false, 'gap' => false ) );
		$this->end_controls_section();
		$this->exd_style_text( 'desc', 'Mô tả', '.exd-title__desc', array( 'hover' => false ) );
		$this->exd_style_text( 'link', 'Link', '.exd-title__link', array( 'gap' => false ) );
	}

	protected function render() {
		$s   = $this->exd_settings();
		$tag = H::tag( $s['tag'], 'h2' );

		echo '<div class="exd-title"><div class="exd-title__head"><div class="exd-title__main">';
		if ( '' !== $s['eyebrow'] ) {
			echo '<span class="exd-title__eyebrow">' . esc_html( $s['eyebrow'] ) . '</span>';
		}
		if ( '' !== $s['title'] ) {
			echo '<' . $tag . ' class="exd-title__text"><span>' . H::highlight( $s['title'] ) . '</span></' . $tag . '>'; // phpcs:ignore
		}
		if ( '' !== $s['desc'] ) {
			echo '<p class="exd-title__desc">' . wp_kses_post( $s['desc'] ) . '</p>';
		}
		echo '</div><span class="exd-title__line" aria-hidden="true"></span>';
		if ( '' !== $s['link_text'] ) {
			$this->add_render_attribute( 'link', 'class', 'exd-title__link' );
			if ( ! empty( $s['link']['url'] ) ) {
				$this->add_link_attributes( 'link', $s['link'] );
			}
			echo '<a ' . $this->get_render_attribute_string( 'link' ) . '>' . esc_html( $s['link_text'] ) . H::arrow() . '</a>'; // phpcs:ignore
		}
		echo '</div></div>';
	}
}
