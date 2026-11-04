<?php
/**
 * Banner Grids XD – lưới banner nhiều bố cục (1 lớn + 2, 1 lớn + 4, mosaic...).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\Parts;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Banner_Grid extends Base {

	protected $exd_slug  = 'banner-grid';
	protected $exd_title = 'Banner Grids XD';
	protected $exd_icon  = 'eicon-gallery-grid';
	protected $exd_keys  = array( 'banner', 'grid', 'mosaic', 'luoi' );
	protected $exd_style = array( 'exd-fx', 'exd-w-banner' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_items', array( 'label' => 'Các banner' ) );

		$rep = new Repeater();
		$rep->add_control(
			'image',
			array(
				'label'   => 'Ảnh',
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => Utils::get_placeholder_image_src() ),
			)
		);
		$rep->add_control(
			'eyebrow',
			array(
				'label'   => 'Dòng nhỏ',
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Tiêu đề banner',
				'label_block' => true,
			)
		);
		$rep->add_control(
			'text',
			array(
				'label'   => 'Mô tả',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'dynamic' => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'btn_text',
			array(
				'label' => 'Chữ nút',
				'type'  => Controls_Manager::TEXT,
			)
		);
		$rep->add_control(
			'link',
			array(
				'label'   => 'Link',
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => '#' ),
			)
		);
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array( 'title' => 'Bộ sưu tập Thu', 'eyebrow' => 'Mới', 'btn_text' => 'Xem ngay' ),
					array( 'title' => 'Phụ kiện' ),
					array( 'title' => 'Giảm đến 50%' ),
					array( 'title' => 'Quà tặng' ),
					array( 'title' => 'Hàng bán chạy' ),
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_layout', array( 'label' => 'Bố cục' ) );
		$this->exd_preset(
			array(
				'g1' => '1 lớn + 2 nhỏ',
				'g2' => '1 lớn + 4 nhỏ',
				'g3' => '2 nhỏ + 1 lớn bên phải',
				'g4' => 'Mosaic (lớn + cao + 2 nhỏ)',
				'g5' => '1 rộng trên + 3 dưới',
				'g6' => 'Hàng ngang cao',
				'g7' => 'Lưới đều (tự chọn số cột)',
			),
			'g2',
			'Bố cục lưới',
			'layout',
			'exd-g-'
		);
		$this->add_responsive_control(
			'cols',
			array(
				'label'     => 'Số cột',
				'type'      => Controls_Manager::SELECT,
				'options'   => array( '' => 'Mặc định', 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6 ),
				'condition' => array( 'layout' => array( 'g6', 'g7' ) ),
				'selectors' => array( '{{WRAPPER}} .exd-bg' => '--exd-cols: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mcols',
			array(
				'label'     => 'Số cột trên điện thoại',
				'type'      => Controls_Manager::SELECT,
				'options'   => array( '1' => '1 cột', '2' => '2 cột (ô đầu chiếm cả hàng)' ),
				'default'   => '1',
				'selectors' => array( '{{WRAPPER}} .exd-bg' => '--exd-mcols: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'row_h',
			array(
				'label'      => 'Chiều cao 1 hàng',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 700 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-bg' => '--exd-row-h: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => 'Khoảng cách',
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-bg' => '--exd-gap: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_style_pre', array( 'label' => 'Kiểu banner' ) );
		$this->exd_preset(
			array(
				'shade' => 'Chuyển tối từ dưới',
				'plain' => 'Chữ trắng trên ảnh',
				'light' => 'Nền sáng, chữ tối',
				'box'   => 'Hộp chữ trắng',
				'glass' => 'Hộp kính mờ',
			),
			'shade'
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ tiêu đề',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h2', 'h3', 'h4', 'h5', 'div' ), array( 'H2', 'H3', 'H4', 'H5', 'div' ) ),
				'default' => 'h3',
			)
		);
		$this->exd_banner_position();
		$this->exd_fx( 'zoom' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Ô banner', '.exd-bn', array( 'padding' => false ) );
		$this->exd_banner_style( array( 'buttons' => false ) );
		$this->exd_style_button( 'bn_btn', 'Nút', '.exd-bn__btn' );
	}

	protected function render() {
		$s     = $this->exd_settings();
		$items = $s['items'] ?? array();
		if ( ! $items ) {
			return;
		}
		echo '<div class="exd-bg">';
		foreach ( $items as $i => $it ) {
			Parts::banner(
				array(
					'image'    => $it['image'],
					'img_size' => 0 === $i ? 'full' : 'large',
					'sizes'    => 0 === $i ? '(max-width: 767px) 100vw, 60vw' : '(max-width: 767px) 100vw, 33vw',
					'eyebrow'  => $it['eyebrow'],
					'title'    => $it['title'],
					'tag'      => $s['tag'],
					'text'     => $it['text'],
					'btn_text' => $it['btn_text'],
					'link'     => $it['link'],
					'cls'      => 'exd-bg__item elementor-repeater-item-' . $it['_id'],
				),
				$this,
				'it' . $i
			);
		}
		echo '</div>';
	}
}
