<?php
/**
 * Cửa sổ nổi XD – nút/ảnh/chữ mở cửa sổ nổi chứa nội dung, template, ảnh hoặc video (Lightbox).
 * Mở được từ link bất kỳ: href="#exd-lb-<ID>". Tuỳ chọn tự bật sau N giây.
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Lightbox extends Base {

	protected $exd_slug    = 'lightbox';
	protected $exd_title   = 'Cửa sổ nổi XD';
	protected $exd_icon    = 'eicon-lightbox-expand';
	protected $exd_keys    = array( 'lightbox', 'popup', 'modal', 'cua so', 'bật lên' );
	protected $exd_style   = array( 'exd-modal' );
	protected $exd_scripts = array( 'exd-modal' );
	protected $exd_js      = true;
	protected $exd_dynamic = true;

	/** JS riêng (lightbox.js) chỉ cần khi bật "Tự mở"; modal.js luôn cần. */
	public function get_script_depends(): array {
		$deps = array( 'exd-core', 'exd-modal' );
		if ( H::is_preview_request() || 'yes' === $this->exd_get( 'auto' ) ) {
			$deps[] = 'exd-w-lightbox';
		}
		return $deps;
	}

	protected function register_controls() {
		$this->start_controls_section( 'sec_trigger', array( 'label' => 'Nút mở' ) );
		$this->add_control(
			'trigger',
			array(
				'label'   => 'Kiểu nút mở',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'button' => 'Nút',
					'image'  => 'Ảnh',
					'text'   => 'Chữ (link)',
					'none'   => 'Không có (mở bằng link #ID hoặc tự mở)',
				),
				'default' => 'button',
			)
		);
		$this->add_control(
			'btn_text',
			array(
				'label'     => 'Chữ',
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Đăng ký tư vấn',
				'condition' => array( 'trigger' => array( 'button', 'text' ) ),
			)
		);
		$this->add_control(
			'btn_style',
			array(
				'label'     => 'Kiểu nút',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'primary' => 'Nền màu',
					'outline' => 'Viền',
					'light'   => 'Nền trắng',
					'ghost'   => 'Kính mờ (trên nền tối)',
				),
				'default'   => 'primary',
				'condition' => array( 'trigger' => 'button' ),
			)
		);
		$this->add_control(
			'btn_icon',
			array(
				'label'     => 'Icon',
				'type'      => Controls_Manager::ICONS,
				'condition' => array( 'trigger' => array( 'button', 'text' ) ),
			)
		);
		$this->add_control(
			'trigger_img',
			array(
				'label'     => 'Ảnh',
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'trigger' => 'image' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => 'Căn lề',
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => 'Trái', 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => 'Giữa', 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => 'Phải', 'icon' => 'eicon-text-align-right' ),
				),
				'condition' => array( 'trigger!' => 'none' ),
				'selectors' => array( '{{WRAPPER}} .exd-lb' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'lb_id',
			array(
				'label'       => 'ID cửa sổ',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'dang-ky',
				'separator'   => 'before',
				'description' => 'Đặt link bất kỳ (nút, menu…) thành #exd-lb-ID để mở cửa sổ này. Để trống sẽ dùng ID widget.',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_content', array( 'label' => 'Nội dung cửa sổ' ) );
		$this->add_control(
			'source',
			array(
				'label'   => 'Nguồn',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'editor'   => 'Soạn trực tiếp',
					'template' => 'Template Elementor (form, ưu đãi…)',
					'image'    => 'Ảnh',
					'video'    => 'Video',
				),
				'default' => 'editor',
			)
		);
		$this->add_control(
			'editor',
			array(
				'label'     => 'Nội dung',
				'type'      => Controls_Manager::WYSIWYG,
				'default'   => '<h3>Nhận tư vấn miễn phí</h3><p>Để lại thông tin, chúng tôi sẽ gọi lại trong 15 phút.</p>',
				'condition' => array( 'source' => 'editor' ),
			)
		);
		$this->add_control(
			'template',
			array(
				'label'     => 'Template',
				'type'      => Controls_Manager::SELECT,
				'options'   => H::templates(),
				'condition' => array( 'source' => 'template' ),
			)
		);
		$this->add_control(
			'image',
			array(
				'label'     => 'Ảnh',
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'image' ),
			)
		);
		$this->add_control(
			'video',
			array(
				'label'       => 'Link video',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'YouTube, Vimeo hoặc file .mp4',
				'label_block' => true,
				'condition'   => array( 'source' => 'video' ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'label'     => 'Kiểu cửa sổ',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'center' => 'Giữa màn hình',
					'side'   => 'Trượt từ bên phải',
					'bottom' => 'Trượt từ dưới lên',
					'full'   => 'Toàn màn hình',
				),
				'default'   => 'center',
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'label'      => 'Độ rộng',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 280, 'max' => 1400 ) ),
				'selectors'  => array( '{{WRAPPER}} .exd-modal' => '--exd-modal-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'sec_auto', array( 'label' => 'Tự mở' ) );
		$this->add_control(
			'auto',
			array(
				'label'        => 'Tự mở khi vào trang',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => 'Bật mới nạp thêm JS (rất nhỏ). Không tự mở trong khung soạn thảo.',
			)
		);
		$this->add_control(
			'auto_delay',
			array(
				'label'     => 'Sau (giây)',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0,
				'default'   => 5,
				'condition' => array( 'auto' => 'yes' ),
			)
		);
		$this->add_control(
			'auto_trigger',
			array(
				'label'     => 'Khi',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'time'   => 'Sau số giây trên',
					'scroll' => 'Cuộn được 50% trang',
					'exit'   => 'Rê chuột ra khỏi trang (máy tính)',
				),
				'default'   => 'time',
				'condition' => array( 'auto' => 'yes' ),
			)
		);
		$this->add_control(
			'auto_freq',
			array(
				'label'     => 'Tần suất',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'always'  => 'Mỗi lần tải trang',
					'session' => 'Một lần mỗi phiên',
					'days'    => 'Một lần trong N ngày',
				),
				'default'   => 'session',
				'condition' => array( 'auto' => 'yes' ),
			)
		);
		$this->add_control(
			'auto_days',
			array(
				'label'     => 'Số ngày',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'default'   => 3,
				'condition' => array( 'auto' => 'yes', 'auto_freq' => 'days' ),
			)
		);
		$this->end_controls_section();

		/* Style */
		$this->exd_style_button( 'btn', 'Nút mở', '.exd-lb__trigger', array( 'condition' => array( 'trigger' => array( 'button', 'text' ) ) ) );
		$this->exd_style_image( 'timg', 'Ảnh nút mở', '.exd-lb__img img', array( 'wrap' => '.exd-lb__img', 'condition' => array( 'trigger' => 'image' ) ) );
		$this->exd_style_box( 'win', 'Khung cửa sổ', '.exd-modal__box', array( 'padding' => false ) );
		$this->start_controls_section(
			'st_body',
			array(
				'label' => 'Nội dung & nền tối',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'body_pad',
			array(
				'label'      => 'Padding nội dung',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .exd-modal__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'body_color',
			array(
				'label'     => 'Màu chữ',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-modal__body' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'backdrop',
			array(
				'label'     => 'Màu nền tối',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-modal::backdrop' => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'close_color',
			array(
				'label'     => 'Màu nút đóng',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-modal__close' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'close_bg',
			array(
				'label'     => 'Nền nút đóng',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .exd-modal__close' => 'background: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s  = $this->exd_settings();
		$id = 'exd-lb-' . ( '' !== trim( (string) $s['lb_id'] ) ? sanitize_title( $s['lb_id'] ) : $this->get_id() );

		echo '<div class="exd-lb">';

		/* Nút mở */
		$icon = H::icon( $s['btn_icon'] ?? array() );
		$aria = ' aria-haspopup="dialog" aria-controls="' . esc_attr( $id ) . '" data-exd-open="' . esc_attr( $id ) . '"';
		if ( 'button' === $s['trigger'] ) {
			echo '<button type="button" class="exd-btn exd-btn--' . esc_attr( $s['btn_style'] ) . ' exd-lb__trigger"' . $aria . '>' . $icon . esc_html( $s['btn_text'] ) . '</button>'; // phpcs:ignore
		} elseif ( 'text' === $s['trigger'] ) {
			echo '<a href="#' . esc_attr( $id ) . '" class="exd-lb__trigger exd-lb__link"' . $aria . '>' . $icon . esc_html( $s['btn_text'] ) . '</a>'; // phpcs:ignore
		} elseif ( 'image' === $s['trigger'] && ! empty( $s['trigger_img']['url'] ) ) {
			echo '<button type="button" class="exd-lb__img exd-media"' . $aria . ' aria-label="Mở">' . H::img( $s['trigger_img'], 'large', array( 'sizes' => H::sizes( 2 ) ) ) . '</button>'; // phpcs:ignore
		} elseif ( H::is_editor() ) {
			echo '<div class="exd-lb__hint">Cửa sổ nổi XD · mở bằng link <code>#' . esc_html( $id ) . '</code></div>';
		}

		/* Cửa sổ */
		$cls = 'exd-modal exd-modal--' . $s['layout'] . ( in_array( $s['source'], array( 'image', 'video' ), true ) ? ' exd-modal--media' : '' );
		$auto = '';
		if ( 'yes' === $s['auto'] ) {
			$auto = ' data-exd-auto="' . esc_attr( max( 0, (int) $s['auto_delay'] ) ) . '" data-exd-on="' . esc_attr( $s['auto_trigger'] ) . '" data-exd-freq="' . esc_attr( 'days' === $s['auto_freq'] ? 'days:' . max( 1, (int) $s['auto_days'] ) : $s['auto_freq'] ) . '"';
		}
		echo '<dialog id="' . esc_attr( $id ) . '" class="' . esc_attr( $cls ) . '" aria-label="' . esc_attr( $s['btn_text'] ?: 'Cửa sổ' ) . '"' . $auto . '>'; // phpcs:ignore
		echo '<div class="exd-modal__box"><button type="button" class="exd-modal__close" aria-label="Đóng"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>';
		echo '<div class="exd-modal__body">';
		switch ( $s['source'] ) {
			case 'template':
				echo H::template( $s['template'] ); // phpcs:ignore
				break;
			case 'image':
				echo H::img( $s['image'], 'full', array( 'class' => 'exd-modal__img', 'sizes' => '100vw' ) ); // phpcs:ignore
				break;
			case 'video':
				$v = H::video( $s['video'] );
				if ( $v ) {
					echo '<div class="exd-modal__video" data-exd-src="' . esc_attr( $v['src'] ) . '" data-exd-type="' . esc_attr( $v['type'] ) . '"></div>';
				}
				break;
			default:
				echo '<div class="exd-lb__content">' . $this->parse_text_editor( $s['editor'] ) . '</div>'; // phpcs:ignore
		}
		echo '</div></div></dialog></div>';
	}
}
