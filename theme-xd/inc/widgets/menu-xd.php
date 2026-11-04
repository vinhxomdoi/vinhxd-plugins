<?php
/**
 * Theme XD - Elementor Widget: Menu XD
 * @package ThemeXD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'elementor/widgets/register', 'txd_register_menu_widget' );
function txd_register_menu_widget( $manager ) {
	if ( ! did_action( 'elementor/loaded' ) ) return;

	if ( ! class_exists( 'TXD_Menu_Walker' ) ) {
		class TXD_Menu_Walker extends \Walker_Nav_Menu {
			public function start_lvl( &$output, $depth = 0, $args = null ) {
				$output .= '<ul class="txd-submenu sub-menu">';
			}
		}
	}

	class TXD_Menu_Widget extends \Elementor\Widget_Base {

		public function get_name()       { return 'txd-menu'; }
		public function get_title()      { return 'Menu XD'; }
		public function get_icon()       { return 'eicon-nav-menu'; }
		public function get_categories() { return [ 'general' ]; }
		public function get_keywords()   { return [ 'menu', 'nav', 'navigation', 'xd', 'menu xd' ]; }

		protected function register_controls() {

			/* ===== Content: Chọn Menu ===== */
			$this->start_controls_section( 'section_menu', [
				'label' => '📋 Menu',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]);
			$menus   = wp_get_nav_menus();
			$options = [ '' => '— Chọn menu —' ];
			foreach ( $menus as $m ) $options[ $m->term_id ] = $m->name;
			$this->add_control( 'menu_id', [
				'label'   => 'Chọn Menu',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
			]);
			$this->add_control( 'layout', [
				'label'   => 'Layout',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'horizontal',
				'options' => [ 'horizontal' => 'Ngang', 'vertical' => 'Dọc' ],
			]);
			$this->add_control( 'align', [
				'label'     => 'Căn chỉnh desktop',
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => [
					'flex-start' => [ 'title' => 'Trái',  'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => 'Giữa',  'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => 'Phải',  'icon' => 'eicon-text-align-right' ],
				],
				'default'   => 'flex-start',
				'selectors' => [ '{{WRAPPER}} .txd-nav' => 'justify-content: {{VALUE}};' ],
			]);
			$this->end_controls_section();

			/* ===== Content: Nút Gọi Mobile ===== */
			$this->start_controls_section( 'section_call_btn', [
				'label' => '📞 Nút Gọi Mobile',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]);
			$this->add_control( 'show_call_btn', [
				'label'        => 'Thêm nút gọi bên dưới menu',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Có',
				'label_off'    => 'Không',
				'return_value' => 'yes',
				'default'      => '',
			]);
			$this->add_control( 'call_number', [
				'label'       => 'Số điện thoại',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '0909 123 456',
				'description' => 'Để trống sẽ lấy từ JetEngine Options Page "thong-tin" → field "sdt".',
				'condition'   => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_control( 'call_label', [
				'label'     => 'Text nút gọi',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Gọi ngay',
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_control( 'call_icon', [
				'label'     => 'Hiện icon điện thoại',
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->end_controls_section();

			/* ===== Style: Menu Chính ===== */
			$this->start_controls_section( 'section_style_main', [
				'label' => '🎨 Style Menu Chính',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]);
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'menu_typography',
				'selector' => '{{WRAPPER}} .txd-nav > li > a',
			]);
			$this->add_control( 'menu_gap', [
				'label'     => 'Khoảng cách items',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'   => [ 'size' => 8 ],
				'selectors' => [ '{{WRAPPER}} .txd-nav' => 'gap: {{SIZE}}px;' ],
			]);
			$this->add_responsive_control( 'menu_padding', [
				'label'      => 'Padding item',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'top'=>'8','right'=>'14','bottom'=>'8','left'=>'14','unit'=>'px','isLinked'=>false ],
				'selectors'  => [ '{{WRAPPER}} .txd-nav > li > a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]);
			$this->add_control( 'menu_border_radius', [
				'label'      => 'Border Radius',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [ '{{WRAPPER}} .txd-nav > li > a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]);
			$this->start_controls_tabs( 'tabs_menu_style' );
				$this->start_controls_tab( 'tab_menu_normal', [ 'label' => 'Bình thường' ] );
					$this->add_control( 'menu_color', [
						'label'     => 'Màu chữ',
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => [ '{{WRAPPER}} .txd-nav > li > a' => 'color: {{VALUE}};' ],
					]);
					$this->add_control( 'menu_bg', [
						'label'     => 'Background',
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => [ '{{WRAPPER}} .txd-nav > li > a' => 'background-color: {{VALUE}};' ],
					]);
				$this->end_controls_tab();
				$this->start_controls_tab( 'tab_menu_hover', [ 'label' => 'Hover / Active' ] );
					$this->add_control( 'menu_color_hover', [
						'label'     => 'Màu chữ hover',
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .txd-nav > li > a:hover' => 'color: {{VALUE}};',
							'{{WRAPPER}} .txd-nav > li.current-menu-item > a' => 'color: {{VALUE}};',
						],
					]);
					$this->add_control( 'menu_bg_hover', [
						'label'     => 'Background hover',
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .txd-nav > li > a:hover' => 'background-color: {{VALUE}};',
							'{{WRAPPER}} .txd-nav > li.current-menu-item > a' => 'background-color: {{VALUE}};',
						],
					]);
					$this->add_control( 'menu_hover_effect', [
						'label'   => 'Hiệu ứng hover',
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'none',
						'options' => [
							'none'      => 'Không có',
							'underline' => 'Gạch dưới',
							'overline'  => 'Gạch trên',
							'slide-up'  => 'Slide lên',
							'fade'      => 'Fade',
						],
					]);
				$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();

			/* ===== Style: Dropdown ===== */
			$this->start_controls_section( 'section_style_dropdown', [
				'label' => '📂 Dropdown / Menu con',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]);
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'dropdown_typography',
				'selector' => '{{WRAPPER}} .txd-submenu a',
			]);
			$this->add_control( 'dropdown_width', [
				'label'     => 'Chiều rộng dropdown',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 150, 'max' => 400 ] ],
				'default'   => [ 'size' => 220 ],
				'selectors' => [ '{{WRAPPER}} .txd-submenu' => 'min-width: {{SIZE}}px;' ],
			]);
			$this->add_control( 'dropdown_bg', [
				'label'     => 'Background',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .txd-submenu' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_control( 'dropdown_color', [
				'label'     => 'Màu chữ',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1a1a1a',
				'selectors' => [ '{{WRAPPER}} .txd-submenu a' => 'color: {{VALUE}};' ],
			]);
			$this->add_control( 'dropdown_color_hover', [
				'label'     => 'Màu chữ hover',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .txd-submenu a:hover' => 'color: {{VALUE}};' ],
			]);
			$this->add_control( 'dropdown_bg_hover', [
				'label'     => 'Background item hover',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .txd-submenu li a:hover' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
				'name'     => 'dropdown_shadow',
				'selector' => '{{WRAPPER}} .txd-submenu',
			]);
			$this->add_control( 'dropdown_border_radius', [
				'label'      => 'Border Radius',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top'=>'6','right'=>'6','bottom'=>'6','left'=>'6','unit'=>'px','isLinked'=>true ],
				'selectors'  => [ '{{WRAPPER}} .txd-submenu' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]);
			$this->end_controls_section();

			/* ===== Style: Mobile ===== */
			$this->start_controls_section( 'section_mobile', [
				'label' => '📱 Mobile Menu',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]);
			$this->add_control( 'mobile_breakpoint', [
				'label'   => 'Breakpoint',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1024',
				'options' => [
					'none' => 'Không cần (luôn hiển thị desktop)',
					'768'  => '768px (tablet)',
					'1024' => '1024px (laptop)',
					'1200' => '1200px',
				],
			]);
			$this->add_control( 'mobile_align', [
				'label'   => 'Căn chỉnh menu mobile',
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => 'Trái',  'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => 'Giữa',  'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => 'Phải',  'icon' => 'eicon-text-align-right' ],
				],
				'default' => 'left',
			]);

			/* --- Hamburger trigger --- */
			$this->add_control( 'heading_trigger', [
				'label'     => 'Nút Hamburger',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]);
			$this->add_control( 'trigger_align', [
				'label'   => 'Vị trí trigger',
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [ 'title' => 'Trái', 'icon' => 'eicon-text-align-left' ],
					'flex-end'   => [ 'title' => 'Phải', 'icon' => 'eicon-text-align-right' ],
				],
				'default'   => 'flex-end',
				'selectors' => [ '{{WRAPPER}} .txd-trigger-wrap' => 'justify-content: {{VALUE}};' ],
			]);
			$this->add_control( 'trigger_color', [
				'label'     => 'Màu icon hamburger',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .txd-menu-trigger span' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_control( 'trigger_bg', [
				'label'     => 'Background nút',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [ '{{WRAPPER}} .txd-menu-trigger' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_control( 'trigger_border_radius', [
				'label'     => 'Border Radius nút',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors' => [ '{{WRAPPER}} .txd-menu-trigger' => 'border-radius: {{SIZE}}px;' ],
			]);

			/* --- Close button --- */
			$this->add_control( 'heading_close', [
				'label'     => 'Nút đóng panel (✕)',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]);
			$this->add_control( 'close_color', [
				'label'     => 'Màu icon ✕',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .txd-mobile-close' => 'color: {{VALUE}};' ],
			]);
			$this->add_control( 'close_bg', [
				'label'     => 'Background nút ✕',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [ '{{WRAPPER}} .txd-mobile-close' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_control( 'close_border_radius', [
				'label'     => 'Border Radius nút ✕',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors' => [ '{{WRAPPER}} .txd-mobile-close' => 'border-radius: {{SIZE}}px;' ],
			]);
			$this->add_control( 'close_size', [
				'label'     => 'Cỡ chữ nút ✕',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 12, 'max' => 40 ] ],
				'default'   => [ 'size' => 20 ],
				'selectors' => [ '{{WRAPPER}} .txd-mobile-close' => 'font-size: {{SIZE}}px;' ],
			]);

			/* --- Panel --- */
			$this->add_control( 'heading_panel', [
				'label'     => 'Panel Mobile',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]);
			$this->add_control( 'mobile_panel_bg', [
				'label'     => 'Background panel',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1a1a1a',
				'selectors' => [ '{{WRAPPER}} .txd-mobile-panel' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_control( 'mobile_panel_width', [
				'label'      => 'Độ rộng panel',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [ 'px' => [ 'min' => 200, 'max' => 600 ], '%' => [ 'min' => 30, 'max' => 100 ] ],
				'default'    => [ 'size' => 280, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .txd-mobile-panel' => 'width: {{SIZE}}{{UNIT}};' ],
			]);
			$this->add_control( 'mobile_color', [
				'label'     => 'Màu chữ menu',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .txd-mobile-panel .txd-mobile-nav a' => 'color: {{VALUE}};' ],
			]);
			$this->add_control( 'mobile_color_hover', [
				'label'     => 'Màu chữ hover',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .txd-mobile-panel .txd-mobile-nav a:hover' => 'color: {{VALUE}};' ],
			]);
			$this->add_control( 'mobile_item_bg_hover', [
				'label'     => 'Background item hover',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .txd-mobile-panel .txd-mobile-nav li a:hover' => 'background-color: {{VALUE}};' ],
			]);
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'mobile_typography',
				'selector' => '{{WRAPPER}} .txd-mobile-panel .txd-mobile-nav a',
			]);

			/* --- Style nút gọi mobile --- */
			$this->add_control( 'heading_call_style', [
				'label'     => 'Style nút gọi',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_control( 'call_btn_color', [
				'label'     => 'Màu chữ nút gọi',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .txd-call-btn' => 'color: {{VALUE}};' ],
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_control( 'call_btn_bg', [
				'label'     => 'Background nút gọi',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#22c55e',
				'selectors' => [ '{{WRAPPER}} .txd-call-btn' => 'background-color: {{VALUE}};' ],
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_control( 'call_btn_radius', [
				'label'      => 'Border Radius',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8 ],
				'selectors'  => [ '{{WRAPPER}} .txd-call-btn' => 'border-radius: {{SIZE}}px;' ],
				'condition'  => [ 'show_call_btn' => 'yes' ],
			]);
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
				'name'      => 'call_btn_typography',
				'selector'  => '{{WRAPPER}} .txd-call-btn',
				'condition' => [ 'show_call_btn' => 'yes' ],
			]);
			$this->end_controls_section();
		}

		protected function render() {
			$s         = $this->get_settings_for_display();
			$menu_id   = $s['menu_id'] ?? '';
			$layout    = $s['layout'] ?? 'horizontal';
			$bp_raw    = $s['mobile_breakpoint'] ?? '1024';
			$bp        = ( $bp_raw === 'none' ) ? 0 : absint( $bp_raw );
			$effect    = $s['menu_hover_effect'] ?? 'none';
			$mob_align = $s['mobile_align'] ?? 'left';
			$wid       = 'txdm-' . $this->get_id();

			// Nút gọi - lấy SĐT từ JetEngine Options Page 'thong-tin' field 'sdt'
			$show_call  = ( $s['show_call_btn'] ?? '' ) === 'yes';
			$call_label = $s['call_label'] ?? 'Gọi ngay';
			$call_icon  = ( $s['call_icon'] ?? 'yes' ) === 'yes';

			// Lấy SĐT từ JetEngine Options Page
			$call_num = '';
			if ( $show_call ) {
				$call_num  = trim( (string) ( $s['call_number'] ?? '' ) ); // ưu tiên ô "Số điện thoại" của widget
				$thong_tin = $call_num ? null : get_option( 'thong-tin' );
				if ( is_array( $thong_tin ) && ! empty( $thong_tin['sdt'] ) ) {
					$call_num = $thong_tin['sdt'];
				} elseif ( ! $call_num && function_exists( 'jet_engine' ) ) {
					// Fallback: JetEngine lưu từng field riêng
					$call_num = get_option( 'jet_engine_options_thong-tin_sdt', '' );
				}
			}

			if ( ! $menu_id ) {
				echo '<p style="padding:1rem;color:#999;font-size:.875rem;">Vui lòng chọn menu trong tab Content.</p>';
				return;
			}

			$menu_html = wp_nav_menu([
				'menu'       => $menu_id,
				'menu_id'    => $wid . '-desktop',
				'menu_class' => 'txd-nav txd-nav--' . esc_attr( $layout ),
				'container'  => false,
				'echo'       => false,
				'walker'     => new TXD_Menu_Walker(),
			]);
			?>
			<div class="txd-menu-wrap <?php echo esc_attr($wid); ?> txd-effect-<?php echo esc_attr($effect); ?>">

				<!-- Desktop -->
				<nav class="txd-nav-desktop"><?php echo $menu_html; ?></nav>

				<!-- Trigger wrapper (để căn trái/phải) -->
				<div class="txd-trigger-wrap">
					<button class="txd-menu-trigger" aria-expanded="false" aria-label="Mở menu">
						<span></span><span></span><span></span>
					</button>
				</div>

				<!-- Mobile Panel -->
				<div class="txd-mobile-panel txd-mob-<?php echo esc_attr($mob_align); ?>" aria-hidden="true">
					<button class="txd-mobile-close" aria-label="Đóng">✕</button>
					<?php
					// Bỏ id="menu-item-xxx" ở bản mobile để không trùng ID với menu desktop
					add_filter( 'nav_menu_item_id', '__return_empty_string' );
					wp_nav_menu([ 'menu' => $menu_id, 'menu_id' => $wid . '-mobile', 'menu_class' => 'txd-mobile-nav', 'container' => false ]);
					remove_filter( 'nav_menu_item_id', '__return_empty_string' );
					?>

					<?php if ( $show_call && $call_num ) : ?>
					<div class="txd-call-wrap">
						<a href="tel:<?php echo esc_attr( preg_replace('/\s+/', '', $call_num) ); ?>" class="txd-call-btn">
							<?php if ( $call_icon ) : ?>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
							<?php endif; ?>
							<?php echo esc_html( $call_label ); ?>
							<span class="txd-call-number"><?php echo esc_html( $call_num ); ?></span>
						</a>
					</div>
					<?php endif; ?>
				</div>

				<div class="txd-mobile-overlay"></div>
			</div>

			<style>
			/* Desktop nav */
			.<?php echo $wid;?> .txd-nav{display:flex;flex-wrap:wrap;list-style:none;margin:0;padding:0;}
			.<?php echo $wid;?> .txd-nav--vertical{flex-direction:column;}
			.<?php echo $wid;?> .txd-nav>li{position:relative;}
			.<?php echo $wid;?> .txd-nav>li>a{display:flex;align-items:center;gap:5px;text-decoration:none;transition:color .2s,background .2s;}
			/* Icon dropdown - chỉ hiện với item có submenu */
			.<?php echo $wid;?> .txd-nav>li.menu-item-has-children>a::after{content:'';flex-shrink:0;width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:rotate(45deg);margin-top:-3px;transition:transform .2s;}
			.<?php echo $wid;?> .txd-nav>li.menu-item-has-children:hover>a::after{transform:rotate(-135deg);margin-top:3px;}
			/* Dropdown */
			.<?php echo $wid;?> .txd-submenu{position:absolute;top:100%;left:0;z-index:9999;list-style:none;margin:0;padding:6px 0;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.12);border-radius:6px;opacity:0;visibility:hidden;transform:translateY(8px);transition:all .2s;pointer-events:none;min-width:220px;}
			.<?php echo $wid;?> .txd-nav>li:hover>.txd-submenu,.<?php echo $wid;?> .txd-nav>li:focus-within>.txd-submenu{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
			.<?php echo $wid;?> .txd-submenu li{position:relative;}
			.<?php echo $wid;?> .txd-submenu .txd-submenu{top:-6px;left:100%;transform:translateX(8px);}
			.<?php echo $wid;?> .txd-submenu li:hover>.txd-submenu,.<?php echo $wid;?> .txd-submenu li:focus-within>.txd-submenu{opacity:1;visibility:visible;transform:translateX(0);pointer-events:auto;}
			.<?php echo $wid;?> .txd-submenu a{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 18px;text-decoration:none;transition:color .15s,background .15s;white-space:nowrap;}
			.<?php echo $wid;?> .txd-submenu li.menu-item-has-children>a::after{content:'';flex-shrink:0;width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:rotate(-45deg);}
			/* Hover effects */
			.<?php echo $wid;?>.txd-effect-underline .txd-nav>li>a{position:relative;}
			.<?php echo $wid;?>.txd-effect-underline .txd-nav>li>a::before{content:'';position:absolute;bottom:0;left:50%;right:50%;height:2px;background:currentColor;transition:left .25s,right .25s;}
			.<?php echo $wid;?>.txd-effect-underline .txd-nav>li>a:hover::before{left:0;right:0;}
			.<?php echo $wid;?>.txd-effect-overline .txd-nav>li>a{border-top:2px solid transparent;transition:border-color .2s,color .2s,background .2s;}
			.<?php echo $wid;?>.txd-effect-overline .txd-nav>li>a:hover{border-top-color:currentColor;}
			.<?php echo $wid;?>.txd-effect-slide-up .txd-nav>li>a{transition:transform .25s,color .2s,background .2s;}
			.<?php echo $wid;?>.txd-effect-slide-up .txd-nav>li>a:hover{transform:translateY(-3px);}
			.<?php echo $wid;?>.txd-effect-fade .txd-nav>li>a:hover{opacity:.7;}
			/* Trigger wrap */
			.<?php echo $wid;?> .txd-trigger-wrap{display:none;width:100%;align-items:center;}
			.<?php echo $wid;?> .txd-menu-trigger{display:flex;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:40px;height:40px;padding:8px;cursor:pointer;border:none;background:transparent;}
			.<?php echo $wid;?> .txd-menu-trigger span{display:block;width:22px;height:2px;background:#fff;transition:transform .3s,opacity .3s;}
			.<?php echo $wid;?> .txd-menu-trigger.is-open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
			.<?php echo $wid;?> .txd-menu-trigger.is-open span:nth-child(2){opacity:0;}
			.<?php echo $wid;?> .txd-menu-trigger.is-open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
			/* Mobile Panel */
			.<?php echo $wid;?> .txd-mobile-panel{position:fixed;top:0;left:-320px;height:100dvh;z-index:9999;padding:60px 0 20px;overflow-y:auto;transition:left .3s ease;width:280px;display:flex;flex-direction:column;}
			.<?php echo $wid;?> .txd-mobile-panel.is-open{left:0;}
			.<?php echo $wid;?> .txd-mobile-close{position:absolute;top:12px;right:12px;background:transparent;border:none;font-size:20px;cursor:pointer;color:#fff;width:36px;height:36px;display:flex;align-items:center;justify-content:center;line-height:1;}
			.<?php echo $wid;?> .txd-mobile-nav{list-style:none;margin:0;padding:0;flex:1;}
			.<?php echo $wid;?> .txd-mobile-nav li a{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 24px;text-decoration:none;transition:color .2s,background .2s;border-bottom:1px solid rgba(255,255,255,.07);}
			.<?php echo $wid;?> .txd-mobile-nav li.menu-item-has-children>a::after{content:'';flex-shrink:0;width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:rotate(45deg);}
			.<?php echo $wid;?> .txd-mobile-nav .sub-menu{background:rgba(0,0,0,.15);}
			.<?php echo $wid;?> .txd-mobile-nav .sub-menu a{padding-left:40px;font-size:.9rem;}
			.<?php echo $wid;?> .txd-mob-center .txd-mobile-nav li a{justify-content:center;text-align:center;}
			.<?php echo $wid;?> .txd-mob-center .txd-mobile-nav li.menu-item-has-children>a{justify-content:space-between;}
			.<?php echo $wid;?> .txd-mob-right .txd-mobile-nav li a{justify-content:flex-end;text-align:right;}
			.<?php echo $wid;?> .txd-mob-right .txd-mobile-nav li.menu-item-has-children>a{flex-direction:row-reverse;}
			/* Nút gọi */
			.<?php echo $wid;?> .txd-call-wrap{padding:16px 20px 20px;}
			.<?php echo $wid;?> .txd-call-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;background:#22c55e;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;transition:opacity .2s;text-align:center;flex-direction:column;line-height:1.3;}
			.<?php echo $wid;?> .txd-call-btn:hover{opacity:.9;color:#fff;}
			.<?php echo $wid;?> .txd-call-number{font-size:1.1em;font-weight:800;letter-spacing:.03em;}
			/* Overlay */
			.<?php echo $wid;?> .txd-mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;}
			.<?php echo $wid;?> .txd-mobile-overlay.is-open{display:block;}
			/* Responsive */
			<?php if ( $bp > 0 ) : ?>
			@media(max-width:<?php echo $bp;?>px){
				.<?php echo $wid;?> .txd-nav-desktop{display:none!important;}
				.<?php echo $wid;?> .txd-trigger-wrap{display:flex!important;}
			}
			@media(min-width:<?php echo ($bp+1);?>px){
				.<?php echo $wid;?> .txd-trigger-wrap{display:none!important;}
				.<?php echo $wid;?> .txd-mobile-panel,.<?php echo $wid;?> .txd-mobile-overlay{display:none!important;}
			}
			<?php else : ?>
			/* Breakpoint = none: luôn hiển thị desktop, ẩn trigger/panel */
			.<?php echo $wid;?> .txd-trigger-wrap{display:none!important;}
			.<?php echo $wid;?> .txd-mobile-panel,.<?php echo $wid;?> .txd-mobile-overlay{display:none!important;}
			<?php endif; ?>
			</style>

			<script>
			(function(){
				var w=document.querySelector('.<?php echo $wid;?>');
				if(!w)return;
				var t=w.querySelector('.txd-menu-trigger'),p=w.querySelector('.txd-mobile-panel'),o=w.querySelector('.txd-mobile-overlay'),c=w.querySelector('.txd-mobile-close');
				function open(){p.classList.add('is-open');o.classList.add('is-open');t.classList.add('is-open');t.setAttribute('aria-expanded','true');document.body.style.overflow='hidden';}
				function close(){p.classList.remove('is-open');o.classList.remove('is-open');t.classList.remove('is-open');t.setAttribute('aria-expanded','false');document.body.style.overflow='';}
				if(t)t.addEventListener('click',function(){p.classList.contains('is-open')?close():open();});
				if(c)c.addEventListener('click',close);
				if(o)o.addEventListener('click',close);
				document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
			})();
			</script>
			<?php
		}
	}

	$manager->register( new TXD_Menu_Widget() );
}
