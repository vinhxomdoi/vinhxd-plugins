<?php
/**
 * Thành viên XD – ảnh, tên, chức danh, giới thiệu, mạng xã hội (Team Member).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Team extends Base {

	protected $exd_slug  = 'team';
	protected $exd_title = 'Thành viên XD';
	protected $exd_icon  = 'eicon-person';
	protected $exd_keys  = array( 'team', 'member', 'person', 'thanh vien', 'nhân sự', 'đội ngũ' );
	protected $exd_style = array( 'exd-fx' );

	protected function register_controls() {
		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung' ) );

		$this->exd_preset(
			array(
				'card'    => 'Thẻ (ảnh trên)',
				'overlay' => 'Thông tin hiện trên ảnh khi hover',
				'circle'  => 'Ảnh tròn, căn giữa',
				'float'   => 'Thẻ tên nổi dưới ảnh',
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
		$this->exd_ratio( '.exd-team__media' );
		$this->add_control(
			'name',
			array(
				'label'     => 'Họ tên',
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'default'   => 'Trần Quốc Bảo',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ tên',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), array( 'H2', 'H3', 'H4', 'H5', 'H6', 'div' ) ),
				'default' => 'h3',
			)
		);
		$this->add_control(
			'role',
			array(
				'label'   => 'Chức danh',
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
				'default' => 'Trưởng phòng kinh doanh',
			)
		);
		$this->add_control(
			'bio',
			array(
				'label'   => 'Giới thiệu',
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'   => 'Link (ảnh & tên)',
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
			)
		);

		$rep = new Repeater();
		$rep->add_control(
			'icon',
			array(
				'label'   => 'Icon',
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fab fa-facebook-f',
					'library' => 'fa-brands',
				),
			)
		);
		$rep->add_control(
			'label',
			array(
				'label'   => 'Tên mạng',
				'type'    => Controls_Manager::TEXT,
				'default' => 'Facebook',
			)
		);
		$rep->add_control(
			'url',
			array(
				'label'   => 'Link',
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => '#' ),
			)
		);
		$this->add_control(
			'socials',
			array(
				'label'       => 'Mạng xã hội / liên hệ',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ label }}}',
				'separator'   => 'before',
				'default'     => array(
					array( 'label' => 'Facebook' ),
					array(
						'label' => 'Zalo / Điện thoại',
						'icon'  => array( 'value' => 'fas fa-phone', 'library' => 'fa-solid' ),
					),
				),
			)
		);
		$this->exd_fx( 'zoom' );
		$this->exd_align( '.exd-team__body' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->exd_style_box( 'box', 'Hộp', '.exd-team' );
		$this->exd_style_image( 'img', 'Ảnh', '.exd-team__media img', array( 'wrap' => '.exd-team__media', 'hover_on' => '.exd-team' ) );
		$this->exd_style_box( 'body', 'Khung thông tin', '.exd-team__body', array( 'hover_on' => '.exd-team' ) );
		$this->exd_style_text( 'name', 'Họ tên', '.exd-team__name', array( 'hover_on' => '.exd-team' ) );
		$this->exd_style_text( 'role', 'Chức danh', '.exd-team__role', array( 'hover' => false ) );
		$this->exd_style_text( 'bio', 'Giới thiệu', '.exd-team__bio', array( 'hover' => false ) );
		$this->exd_style_icon( 'soc', 'Icon mạng xã hội', '.exd-team__soc a', array( 'space_on' => '.exd-team__soc' ) );
	}

	protected function render() {
		$s   = $this->exd_settings();
		$tag = H::tag( $s['tag'], 'h3' );

		$open = '';
		$close = '';
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'link', $s['link'] );
			$open  = '<a ' . $this->get_render_attribute_string( 'link' ) . '>';
			$close = '</a>';
		}

		echo '<div class="exd-team exd-fx-host">';
		echo '<div class="exd-team__media exd-media">' . $open . H::img( $s['image'], 'large', array( 'alt' => $s['name'], 'sizes' => H::sizes( 4 ) ) ) . $close; // phpcs:ignore
		echo '</div><div class="exd-team__body">';
		if ( '' !== $s['name'] ) {
			echo '<' . $tag . ' class="exd-team__name">' . $open . esc_html( $s['name'] ) . $close . '</' . $tag . '>'; // phpcs:ignore
		}
		if ( '' !== $s['role'] ) {
			echo '<div class="exd-team__role">' . esc_html( $s['role'] ) . '</div>';
		}
		echo '<div class="exd-team__extra"><div class="exd-team__extra-in">';
		if ( '' !== $s['bio'] ) {
			echo '<div class="exd-team__bio">' . wp_kses_post( $s['bio'] ) . '</div>';
		}
		if ( ! empty( $s['socials'] ) ) {
			echo '<div class="exd-team__soc">';
			foreach ( $s['socials'] as $i => $it ) {
				$k = 'soc' . $i;
				$this->add_render_attribute( $k, 'aria-label', $it['label'] );
				$this->add_render_attribute( $k, 'title', $it['label'] );
				if ( ! empty( $it['url']['url'] ) ) {
					$this->add_link_attributes( $k, $it['url'] );
				}
				echo '<a ' . $this->get_render_attribute_string( $k ) . '>' . H::icon( $it['icon'] ) . '</a>'; // phpcs:ignore
			}
			echo '</div>';
		}
		echo '</div></div></div></div>';
	}
}
