<?php
/**
 * Slider XD – slider banner hoặc template Elementor. Dựa trên CSS scroll-snap (vuốt gốc), JS nhỏ cho mũi tên/chấm/tự chạy.
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use EXD\Parts;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Slider extends Base {

	protected $exd_slug    = 'slider';
	protected $exd_title   = 'Slider XD';
	protected $exd_icon    = 'eicon-slides';
	protected $exd_keys    = array( 'slider', 'carousel', 'slide', 'hero' );
	protected $exd_style   = array( 'exd-fx', 'exd-w-banner' );
	protected $exd_js      = true;
	protected $exd_dynamic = true; // có thể chứa template Elementor

	protected function register_controls() {
		$this->start_controls_section( 'sec_slides', array( 'label' => 'Slide' ) );

		$rep = new Repeater();
		$rep->add_control(
			'source',
			array(
				'label'   => 'Nguồn',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'content'  => 'Banner (ảnh + chữ)',
					'template' => 'Template Elementor',
				),
				'default' => 'content',
			)
		);
		$rep->add_control(
			'template',
			array(
				'label'       => 'Template',
				'type'        => Controls_Manager::SELECT,
				'options'     => H::templates(),
				'condition'   => array( 'source' => 'template' ),
				'description' => 'Tạo ở Templates → Saved Templates. Đặt được mọi widget (Khách hàng XD, Thành viên XD...).',
			)
		);
		$rep->add_control(
			'image',
			array(
				'label'     => 'Ảnh nền',
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'default'   => array( 'url' => Utils::get_placeholder_image_src() ),
				'condition' => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'eyebrow',
			array(
				'label'     => 'Dòng nhỏ',
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Tiêu đề slide',
				'condition'   => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'text',
			array(
				'label'     => 'Mô tả',
				'type'      => Controls_Manager::TEXTAREA,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'btn_text',
			array(
				'label'     => 'Chữ nút chính',
				'type'      => Controls_Manager::TEXT,
				'condition' => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'btn_link',
			array(
				'label'     => 'Link nút chính',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'content', 'btn_text!' => '' ),
			)
		);
		$rep->add_control(
			'btn2_text',
			array(
				'label'     => 'Chữ nút phụ',
				'type'      => Controls_Manager::TEXT,
				'condition' => array( 'source' => 'content' ),
			)
		);
		$rep->add_control(
			'btn2_link',
			array(
				'label'     => 'Link nút phụ',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'content', 'btn2_text!' => '' ),
			)
		);
		$rep->add_control(
			'link',
			array(
				'label'     => 'Link cả slide',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'content' ),
			)
		);
		$this->add_control(
			'slides',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ "template" === source ? "Template #" + template : title }}}',
				'default'     => array(
					array(
						'eyebrow'  => 'Ưu đãi mùa này',
						'title'    => 'Thiết kế *gọn*, tải siêu nhanh',
						'text'     => 'Slider dùng cuộn gốc của trình duyệt: vuốt mượt trên điện thoại.',
						'btn_text' => 'Xem ngay',
						'btn_link' => array( 'url' => '#' ),
					),
					array( 'title' => 'Slide thứ hai' ),
					array( 'title' => 'Slide thứ ba' ),
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_opts', array( 'label' => 'Tuỳ chọn slider' ) );
		$this->add_control(
			'effect',
			array(
				'label'        => 'Hiệu ứng chuyển',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'slide' => 'Trượt (vuốt được)',
					'fade'  => 'Mờ dần (1 slide/khung)',
				),
				'default'      => 'slide',
				'prefix_class' => 'exd-eff-',
				'render_type'  => 'template',
			)
		);
		$this->add_responsive_control(
			'per',
			array(
				'label'          => 'Số slide hiển thị',
				'type'           => Controls_Manager::SELECT,
				'options'        => array( '' => 'Mặc định' ) + array_combine( range( 1, 6 ), range( 1, 6 ) ),
				'default'        => '1',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'condition'      => array( 'effect' => 'slide' ),
				'selectors'      => array( '{{WRAPPER}} .exd-sl' => '--exd-per: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'peek',
			array(
				'label'       => 'Hé lộ slide kế (px)',
				'type'        => Controls_Manager::SLIDER,
				'range'       => array( 'px' => array( 'max' => 200 ) ),
				'description' => 'Cho thấy 1 phần slide tiếp theo để gợi ý vuốt.',
				'condition'   => array( 'effect' => 'slide' ),
				'selectors'   => array( '{{WRAPPER}} .exd-sl' => '--exd-peek: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'     => 'Khoảng cách slide',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 60 ) ),
				'condition' => array( 'effect' => 'slide' ),
				'selectors' => array( '{{WRAPPER}} .exd-sl' => '--exd-gap: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'label'      => 'Chiều cao slide banner',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array( 'min' => 150, 'max' => 1000 ),
					'vh' => array( 'min' => 10, 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}} .exd-sl' => '--exd-bn-h: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'label'        => 'Tự chạy',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'interval',
			array(
				'label'     => 'Thời gian mỗi slide (giây)',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 30,
				'step'      => 0.5,
				'default'   => 5,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause',
			array(
				'label'        => 'Dừng khi rê chuột',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'label'        => 'Quay vòng',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'eager',
			array(
				'label'        => 'Slider đầu trang (tải ảnh slide 1 ưu tiên)',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'arrows',
			array(
				'label'        => 'Mũi tên',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'circle'  => 'Tròn nền trắng',
					'glass'   => 'Tròn kính mờ',
					'square'  => 'Vuông',
					'minimal' => 'Chỉ mũi tên',
					'none'    => 'Ẩn',
				),
				'default'      => 'glass',
				'prefix_class' => 'exd-nav-',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'dots',
			array(
				'label'        => 'Chấm điều hướng',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'dots'  => 'Chấm tròn',
					'pills' => 'Chấm kéo dài',
					'lines' => 'Vạch ngang',
					'none'  => 'Ẩn',
				),
				'default'      => 'pills',
				'prefix_class' => 'exd-dot-',
			)
		);
		$this->add_control(
			'dots_pos',
			array(
				'label'        => 'Vị trí chấm',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'inside'  => 'Trong slide',
					'outside' => 'Dưới slide',
				),
				'default'      => 'inside',
				'prefix_class' => 'exd-dotpos-',
				'condition'    => array( 'dots!' => 'none' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_look', array( 'label' => 'Kiểu banner' ) );
		$this->exd_preset(
			array(
				'shade' => 'Chuyển tối từ cạnh',
				'plain' => 'Chữ trắng trên ảnh',
				'light' => 'Nền sáng, chữ tối',
				'box'   => 'Hộp chữ trắng',
				'glass' => 'Hộp kính mờ',
				'split' => 'Chia đôi (ảnh phải)',
			),
			'shade'
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ tiêu đề',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h1', 'h2', 'h3', 'h4', 'div' ), array( 'H1', 'H2', 'H3', 'H4', 'div' ) ),
				'default' => 'h2',
			)
		);
		$this->exd_banner_position();
		$this->exd_fx( 'none' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Slide', '.exd-bn', array( 'padding' => false ) );
		$this->exd_banner_style();

		/* Mũi tên & chấm */
		$this->start_controls_section(
			'st_nav',
			array(
				'label' => 'Mũi tên & chấm',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'     => 'Cỡ mũi tên',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 24, 'max' => 90 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-sl' => '--exd-arrow: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'arrow_off',
			array(
				'label'     => 'Cách mép',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => -80, 'max' => 80 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-sl' => '--exd-arrow-x: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'arrow_color',
			array(
				'label'     => 'Màu mũi tên',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sl__arrow' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'arrow_bg',
			array(
				'label'     => 'Nền mũi tên',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sl__arrow' => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'arrow_hbg',
			array(
				'label'     => 'Nền mũi tên khi hover',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sl__arrow:hover' => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'dot_color',
			array(
				'label'     => 'Màu chấm',
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .exd-sl__dots' => '--exd-dot: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'dot_active',
			array(
				'label'     => 'Màu chấm đang chọn',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sl__dots' => '--exd-dot-on: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'dot_off',
			array(
				'label'     => 'Cách đáy',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 80 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-sl__dots' => '--exd-dot-y: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->exd_settings();
		$slides = $s['slides'] ?? array();
		if ( ! $slides ) {
			return;
		}
		$n    = count( $slides );
		$conf = array(
			'auto'  => ( 'yes' === $s['autoplay'] && $n > 1 ) ? (int) round( (float) $s['interval'] * 1000 ) : 0,
			'pause' => 'yes' === $s['pause'] ? 1 : 0,
			'loop'  => 'yes' === $s['loop'] ? 1 : 0,
			'fade'  => 'fade' === $s['effect'] ? 1 : 0,
		);

		echo '<div class="exd-sl" data-exd-sl="' . esc_attr( wp_json_encode( $conf ) ) . '" role="region" aria-roledescription="carousel" aria-label="Slider">';
		echo '<div class="exd-sl__track" tabindex="0">';
		foreach ( $slides as $i => $sl ) {
			$active = 0 === $i ? ' is-active' : '';
			echo '<div class="exd-sl__slide elementor-repeater-item-' . esc_attr( $sl['_id'] ) . $active . '" role="group" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' / ' . $n ) . '">';
			if ( 'template' === $sl['source'] ) {
				echo '<div class="exd-sl__tpl">' . H::template( $sl['template'] ) . '</div>'; // phpcs:ignore
			} else {
				Parts::banner(
					array(
						'image'     => $sl['image'],
						'img_size'  => 'full',
						'eager'     => 0 === $i && 'yes' === $s['eager'],
						'sizes'     => '100vw',
						'eyebrow'   => $sl['eyebrow'],
						'title'     => $sl['title'],
						'tag'       => 0 === $i ? $s['tag'] : ( 'h1' === $s['tag'] ? 'h2' : $s['tag'] ),
						'text'      => $sl['text'],
						'btn_text'  => $sl['btn_text'],
						'btn_link'  => $sl['btn_link'],
						'btn2_text' => $sl['btn2_text'],
						'btn2_link' => $sl['btn2_link'],
						'link'      => $sl['link'],
					),
					$this,
					'sl' . $i
				);
			}
			echo '</div>';
		}
		echo '</div>';

		if ( $n > 1 ) {
			$chev = '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			echo '<button type="button" class="exd-sl__arrow exd-sl__prev" aria-label="Slide trước">' . $chev . '</button>'; // phpcs:ignore
			echo '<button type="button" class="exd-sl__arrow exd-sl__next" aria-label="Slide sau">' . $chev . '</button>'; // phpcs:ignore
			echo '<div class="exd-sl__dots">';
			for ( $i = 0; $i < $n; $i++ ) {
				echo '<button type="button" class="exd-sl__dot' . ( 0 === $i ? ' is-active' : '' ) . '" aria-label="' . esc_attr( 'Tới slide ' . ( $i + 1 ) ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '><i></i></button>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
