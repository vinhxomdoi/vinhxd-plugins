<?php
/**
 * Hộp thông báo XD – dải thông báo / kêu gọi hành động (Message box).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Message extends Base {

	protected $exd_slug  = 'message';
	protected $exd_title = 'Hộp thông báo XD';
	protected $exd_icon  = 'eicon-alert';
	protected $exd_keys  = array( 'message', 'alert', 'notice', 'cta', 'thong bao', 'thông báo' );
	protected $exd_js    = true;

	protected function exd_need_js(): bool {
		return 'yes' === $this->exd_get( 'dismiss' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'soft'     => 'Nền nhạt',
				'solid'    => 'Nền màu đặc',
				'gradient' => 'Nền chuyển màu',
				'outline'  => 'Viền',
				'accent'   => 'Vạch nhấn bên trái',
				'dark'     => 'Nền tối',
			),
			'soft'
		);
		$this->add_control(
			'layout',
			array(
				'label'        => 'Bố cục',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'row'   => 'Một hàng (nút bên phải)',
					'stack' => 'Xếp dọc',
				),
				'default'      => 'row',
				'prefix_class' => 'exd-lay-',
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'   => 'Icon',
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-gift',
					'library' => 'fa-solid',
				),
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'Tiêu đề',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => 'Ưu đãi tháng này',
				'label_block' => true,
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => 'Nội dung',
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array( 'active' => true ),
				'default' => 'Giảm 20% cho khách đăng ký trước ngày 30. Số lượng có hạn.',
			)
		);
		$this->add_control(
			'btn_text',
			array(
				'label'     => 'Chữ nút',
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Nhận ưu đãi',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'btn_link',
			array(
				'label'     => 'Link nút',
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
			'dismiss',
			array(
				'label'        => 'Cho phép đóng',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => 'Bật mới nạp JS (rất nhỏ) cho widget này.',
			)
		);
		$this->add_control(
			'dismiss_days',
			array(
				'label'       => 'Ẩn trong (ngày) sau khi đóng',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => '0 = chỉ ẩn trong lần xem này.',
				'condition'   => array( 'dismiss' => 'yes' ),
			)
		);
		$this->exd_align( '.exd-msg' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Hộp', '.exd-msg' );
		$this->exd_style_icon( 'icon', 'Icon', '.exd-msg__icon', array( 'hover_on' => '.exd-msg', 'space_on' => '.exd-msg' ) );
		$this->exd_style_text( 'title', 'Tiêu đề', '.exd-msg__title', array( 'hover' => false ) );
		$this->exd_style_text( 'text', 'Nội dung', '.exd-msg__text', array( 'hover' => false ) );
		$this->exd_style_button( 'btn', 'Nút chính', '.exd-msg__btn' );
		$this->exd_style_button( 'btn2', 'Nút phụ', '.exd-msg__btn2', array( 'condition' => array( 'btn2_text!' => '' ) ) );
	}

	protected function render() {
		$s = $this->exd_settings();
		$this->add_render_attribute( 'box', 'class', 'exd-msg' );
		$this->add_render_attribute( 'box', 'role', 'note' );
		if ( 'yes' === $s['dismiss'] ) {
			$this->add_render_attribute( 'box', 'data-exd-dismiss', $this->get_id() );
			$this->add_render_attribute( 'box', 'data-days', (int) $s['dismiss_days'] );
		}
		echo '<div ' . $this->get_render_attribute_string( 'box' ) . '>'; // phpcs:ignore
		$icon = H::icon( $s['icon'] );
		if ( $icon ) {
			echo '<span class="exd-msg__icon">' . $icon . '</span>'; // phpcs:ignore
		}
		echo '<div class="exd-msg__body">';
		if ( '' !== $s['title'] ) {
			echo '<div class="exd-msg__title">' . esc_html( $s['title'] ) . '</div>';
		}
		if ( '' !== $s['text'] ) {
			echo '<div class="exd-msg__text">' . wp_kses_post( $s['text'] ) . '</div>';
		}
		echo '</div>';
		if ( '' !== $s['btn_text'] || '' !== $s['btn2_text'] ) {
			echo '<div class="exd-msg__actions exd-btns">';
			foreach ( array( 'btn' => 'exd-btn--primary', 'btn2' => 'exd-btn--outline' ) as $b => $cls ) {
				if ( '' === $s[ $b . '_text' ] ) {
					continue;
				}
				$this->add_render_attribute( $b, 'class', array( 'exd-btn', $cls, 'exd-msg__' . $b ) );
				if ( ! empty( $s[ $b . '_link' ]['url'] ) ) {
					$this->add_link_attributes( $b, $s[ $b . '_link' ] );
				}
				echo '<a ' . $this->get_render_attribute_string( $b ) . '>' . esc_html( $s[ $b . '_text' ] ) . '</a>'; // phpcs:ignore
			}
			echo '</div>';
		}
		if ( 'yes' === $s['dismiss'] ) {
			echo '<button type="button" class="exd-msg__close" aria-label="Đóng thông báo"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>';
		}
		echo '</div>';
	}
}
