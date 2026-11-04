<?php
/**
 * Scroll To XD – mốc cuộn (anchor) + thanh chấm điều hướng bên cạnh, hoặc nút "cuộn xuống".
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Scroll_To extends Base {

	protected $exd_slug  = 'scroll-to';
	protected $exd_title = 'Scroll To XD';
	protected $exd_icon  = 'eicon-anchor';
	protected $exd_keys  = array( 'scroll', 'anchor', 'menu', 'cuon', 'neo' );
	protected $exd_js    = true;

	protected function exd_need_js(): bool {
		return 'anchor' === $this->exd_get( 'mode', 'anchor' ) && 'yes' === $this->exd_get( 'bullet', 'yes' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );
		$this->add_control(
			'mode',
			array(
				'label'   => 'Loại',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'anchor' => 'Mốc cuộn (đặt đầu mỗi khối)',
					'button' => 'Nút cuộn tới mốc',
				),
				'default' => 'anchor',
			)
		);
		$this->add_control(
			'anchor',
			array(
				'label'       => 'ID mốc',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'gioi-thieu',
				'description' => 'Link tới mốc: #gioi-thieu (menu, nút…).',
				'condition'   => array( 'mode' => 'anchor' ),
			)
		);
		$this->add_control(
			'label',
			array(
				'label'     => 'Tên mốc (hiện khi rê vào chấm)',
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Giới thiệu',
				'condition' => array( 'mode' => 'anchor' ),
			)
		);
		$this->add_responsive_control(
			'offset',
			array(
				'label'       => 'Chừa khoảng phía trên (px)',
				'type'        => Controls_Manager::SLIDER,
				'range'       => array( 'px' => array( 'max' => 300 ) ),
				'description' => 'Bằng chiều cao header dính để tiêu đề khối không bị che.',
				'condition'   => array( 'mode' => 'anchor' ),
				'selectors'   => array( '{{WRAPPER}} .exd-sto' => 'scroll-margin-top: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'bullet',
			array(
				'label'        => 'Hiện thanh chấm điều hướng',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'Gom tất cả mốc trên trang thành 1 thanh chấm cố định. Bật mới nạp JS.',
				'condition'    => array( 'mode' => 'anchor' ),
			)
		);
		$this->add_control(
			'side',
			array(
				'label'     => 'Vị trí thanh chấm',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'right' => 'Phải',
					'left'  => 'Trái',
				),
				'default'   => 'right',
				'condition' => array( 'mode' => 'anchor', 'bullet' => 'yes' ),
			)
		);
		$this->add_control(
			'target',
			array(
				'label'       => 'Cuộn tới ID',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'gioi-thieu',
				'condition'   => array( 'mode' => 'button' ),
			)
		);
		$this->add_control(
			'btn_text',
			array(
				'label'     => 'Chữ cạnh nút',
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Cuộn xuống',
				'condition' => array( 'mode' => 'button' ),
			)
		);
		$this->exd_preset(
			array(
				'mouse'  => 'Con chuột',
				'circle' => 'Mũi tên tròn',
				'line'   => 'Vạch chạy dọc',
			),
			'mouse',
			'Kiểu nút'
		);
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
				'condition' => array( 'mode' => 'button' ),
				'selectors' => array( '{{WRAPPER}} .exd-sto-wrap' => 'justify-content: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'st_btn',
			array(
				'label'     => 'Nút cuộn',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'mode' => 'button' ),
			)
		);
		$this->add_control(
			'btn_color',
			array(
				'label'     => 'Màu',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sto-btn' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'btn_hcolor',
			array(
				'label'     => 'Màu khi hover',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sto-btn:hover' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'btn_size',
			array(
				'label'     => 'Cỡ',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 10, 'max' => 40 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-sto-btn' => 'font-size: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'st_nav',
			array(
				'label'     => 'Thanh chấm',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'mode' => 'anchor', 'bullet' => 'yes' ),
			)
		);
		$this->add_control(
			'nav_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'Thanh chấm dùng màu của mốc đầu tiên trên trang.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'nav_color',
			array(
				'label'     => 'Màu chấm',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sto' => '--exd-sto-c: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'nav_active',
			array(
				'label'     => 'Màu chấm đang xem',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-sto' => '--exd-sto-on: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->exd_settings();

		if ( 'button' === $s['mode'] ) {
			$target = sanitize_title( $s['target'] );
			echo '<div class="exd-sto-wrap"><a class="exd-sto-btn" href="#' . esc_attr( $target ) . '">';
			echo '<span class="exd-sto-btn__ic" aria-hidden="true"><i></i></span>';
			echo '' !== $s['btn_text'] ? '<span class="exd-sto-btn__label">' . esc_html( $s['btn_text'] ) . '</span>' : '<span class="exd-sr">Cuộn xuống</span>';
			echo '</a></div>';
			return;
		}

		$id = sanitize_title( $s['anchor'] );
		if ( '' === $id ) {
			return;
		}
		$attrs = ' id="' . esc_attr( $id ) . '"';
		if ( 'yes' === $s['bullet'] ) {
			$attrs .= ' data-exd-bullet="' . esc_attr( $s['label'] ) . '" data-side="' . esc_attr( $s['side'] ) . '"';
		}
		echo '<span class="exd-sto"' . $attrs . '></span>'; // phpcs:ignore
		if ( H::is_editor() ) {
			echo '<div class="exd-sto__hint">⚓ Scroll To: <code>#' . esc_html( $id ) . '</code>' . ( $s['label'] ? ' · ' . esc_html( $s['label'] ) : '' ) . '</div>';
		}
	}
}
