<?php
/**
 * Banner XD – 1 banner ảnh nền + nội dung + nút. Đặt 2 banner trong 2 cột để có "banner đôi".
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\Parts;
use Elementor\Controls_Manager;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Banner extends Base {

	protected $exd_slug  = 'banner';
	protected $exd_title = 'Banner XD';
	protected $exd_icon  = 'eicon-banner';
	protected $exd_keys  = array( 'banner', 'hero', 'cta', 'anh nen' );
	protected $exd_style = array( 'exd-fx' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'plain' => 'Chữ trắng trên ảnh',
				'shade' => 'Chuyển tối từ cạnh',
				'light' => 'Nền sáng, chữ tối',
				'box'   => 'Hộp chữ trắng',
				'glass' => 'Hộp kính mờ',
				'split' => 'Chia đôi (ảnh phải)',
			),
			'shade'
		);
		$this->add_control(
			'image',
			array(
				'label'   => 'Ảnh nền',
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
					'large'     => 'Large',
					'1536x1536' => '1536px',
					'2048x2048' => '2048px',
					'full'      => 'Gốc',
				),
				'default' => 'full',
			)
		);
		$this->add_control(
			'eager',
			array(
				'label'        => 'Banner đầu trang (tải ảnh ưu tiên)',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => 'Bật cho banner nằm ở màn hình đầu tiên: bỏ lazy-load, thêm fetchpriority=high → điểm LCP tốt hơn.',
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'label'      => 'Chiều cao tối thiểu',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 120, 'max' => 1000 ),
					'vh' => array( 'min' => 10, 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}} .exd-bn' => '--exd-bn-h: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'eyebrow',
			array(
				'label'     => 'Dòng nhỏ',
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'default'   => 'Bộ sưu tập mới',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Sống *xanh* giữa cao nguyên',
				'description' => 'Bọc chữ trong *dấu sao* để tô màu nhấn.',
			)
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
		$this->add_control(
			'text',
			array(
				'label'   => 'Mô tả',
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array( 'active' => true ),
				'default' => 'Không gian sống trong lành, thiết kế tinh gọn và tiện nghi hiện đại.',
			)
		);
		$this->add_control(
			'btn_text',
			array(
				'label'     => 'Chữ nút chính',
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Khám phá ngay',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'btn_link',
			array(
				'label'     => 'Link nút chính',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'default'   => array( 'url' => '#' ),
				'condition' => array( 'btn_text!' => '' ),
			)
		);
		$this->add_control(
			'btn2_text',
			array(
				'label' => 'Chữ nút phụ',
				'type'  => Controls_Manager::TEXT,
			)
		);
		$this->add_control(
			'btn2_link',
			array(
				'label'     => 'Link nút phụ',
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'btn2_text!' => '' ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'       => 'Link cả banner',
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'separator'   => 'before',
				'description' => 'Bấm vào bất kỳ đâu trên banner. Nút vẫn giữ link riêng.',
			)
		);
		$this->exd_banner_position();
		$this->exd_fx( 'zoom' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Banner', '.exd-bn', array( 'padding' => false ) );
		$this->exd_banner_style();
	}

	protected function render() {
		$s = $this->exd_settings();
		Parts::banner(
			array(
				'image'     => $s['image'],
				'img_size'  => $s['img_size'] ?: 'full',
				'eager'     => 'yes' === $s['eager'],
				'sizes'     => '100vw',
				'eyebrow'   => $s['eyebrow'],
				'title'     => $s['title'],
				'tag'       => $s['tag'],
				'text'      => $s['text'],
				'btn_text'  => $s['btn_text'],
				'btn_link'  => $s['btn_link'],
				'btn2_text' => $s['btn2_text'],
				'btn2_link' => $s['btn2_link'],
				'link'      => $s['link'],
			),
			$this,
			'bn'
		);
	}
}
