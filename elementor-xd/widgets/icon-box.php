<?php
/**
 * Icon Box XD – icon/ảnh/số + tiêu đề + mô tả + link.
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Icon_Box extends Base {

	protected $exd_slug  = 'icon-box';
	protected $exd_title = 'Icon Box XD';
	protected $exd_icon  = 'eicon-icon-box';
	protected $exd_keys  = array( 'icon', 'box', 'feature', 'tinh nang' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'plain'   => 'Đơn giản',
				'soft'    => 'Icon nền nhạt',
				'filled'  => 'Icon nền đặc',
				'outline' => 'Icon viền tròn',
				'card'    => 'Thẻ nổi (card)',
				'line'    => 'Thẻ có vạch nhấn',
				'glass'   => 'Thẻ kính mờ (trên nền ảnh/màu)',
			),
			'soft'
		);
		$this->add_control(
			'layout',
			array(
				'label'        => 'Vị trí icon',
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'top'   => array( 'title' => 'Trên', 'icon' => 'eicon-v-align-top' ),
					'left'  => array( 'title' => 'Trái', 'icon' => 'eicon-h-align-left' ),
					'right' => array( 'title' => 'Phải', 'icon' => 'eicon-h-align-right' ),
				),
				'default'      => 'top',
				'prefix_class' => 'exd-pos-',
				'toggle'       => false,
			)
		);
		$this->add_control(
			'valign',
			array(
				'label'     => 'Canh dọc icon',
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => 'Trên', 'icon' => 'eicon-v-align-top' ),
					'center'     => array( 'title' => 'Giữa', 'icon' => 'eicon-v-align-middle' ),
				),
				'condition' => array( 'layout!' => 'top' ),
				'selectors' => array( '{{WRAPPER}} .exd-ib' => 'align-items: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'media',
			array(
				'label'     => 'Loại biểu tượng',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'icon'   => 'Icon',
					'image'  => 'Ảnh',
					'number' => 'Số / chữ',
				),
				'default'   => 'icon',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'     => 'Icon',
				'type'      => Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'fas fa-bolt',
					'library' => 'fa-solid',
				),
				'condition' => array( 'media' => 'icon' ),
			)
		);
		$this->add_control(
			'image',
			array(
				'label'     => 'Ảnh',
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'media' => 'image' ),
			)
		);
		$this->add_control(
			'number',
			array(
				'label'     => 'Số / chữ',
				'type'      => Controls_Manager::TEXT,
				'default'   => '01',
				'condition' => array( 'media' => 'number' ),
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Tốc độ vượt trội',
				'label_block' => true,
				'separator'   => 'before',
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
				'default' => 'Chỉ nạp CSS/JS khi widget có trên trang, giúp web nhẹ và nhanh.',
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
				'label'       => 'Chữ link (để trống = cả khối là link)',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Xem thêm',
			)
		);
		$this->exd_align( '.exd-ib' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Hộp', '.exd-ib' );
		$this->exd_style_icon( 'icon', 'Icon / Ảnh / Số', '.exd-ib__icon', array( 'hover_on' => '.exd-ib', 'space_on' => '.exd-ib' ) );
		$this->exd_style_text( 'title', 'Tiêu đề', '.exd-ib__title', array( 'hover_on' => '.exd-ib' ) );
		$this->exd_style_text( 'text', 'Mô tả', '.exd-ib__text', array( 'hover_on' => '.exd-ib' ) );
		$this->exd_style_text( 'more', 'Chữ link', '.exd-ib__more', array( 'gap' => false, 'hover_on' => '.exd-ib' ) );
	}

	protected function render() {
		$s   = $this->exd_settings();
		$tag = H::tag( $s['tag'], 'h3' );

		$media = '';
		if ( 'image' === $s['media'] ) {
			$media = H::img( $s['image'], 'thumbnail', array( 'class' => 'exd-ib__img' ) );
		} elseif ( 'number' === $s['media'] ) {
			$media = '<span class="exd-ib__num">' . esc_html( $s['number'] ) . '</span>';
		} else {
			$media = H::icon( $s['icon'] );
		}

		$has_link = ! empty( $s['link']['url'] );
		echo '<div class="exd-ib exd-fx-host">';
		if ( $media ) {
			echo '<div class="exd-ib__icon exd-ib__icon--' . esc_attr( $s['media'] ) . '">' . $media . '</div>'; // phpcs:ignore
		}
		echo '<div class="exd-ib__body">';
		if ( '' !== $s['title'] ) {
			echo '<' . $tag . ' class="exd-ib__title">' . esc_html( $s['title'] ) . '</' . $tag . '>'; // phpcs:ignore
		}
		if ( '' !== $s['text'] ) {
			echo '<div class="exd-ib__text">' . wp_kses_post( $s['text'] ) . '</div>';
		}
		if ( $has_link && '' !== $s['link_text'] ) {
			echo '<span class="exd-ib__more">' . esc_html( $s['link_text'] ) . H::arrow() . '</span>'; // phpcs:ignore
		}
		echo '</div>';
		if ( $has_link ) {
			$this->add_render_attribute( 'link', 'class', 'exd-cover-link' );
			$this->add_link_attributes( 'link', $s['link'] );
			echo '<a ' . $this->get_render_attribute_string( 'link' ) . '><span class="exd-sr">' . esc_html( $s['title'] ?: $s['link_text'] ) . '</span></a>'; // phpcs:ignore
		}
		echo '</div>';
	}
}
