<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_Shortcode {

	public function __construct() {
		add_shortcode( 'popupxd', array( $this, 'render' ) );
	}

	public function render( $atts, $content = '' ) {
		$atts = shortcode_atts( array(
			'id'    => 0,
			'class' => '',
		), $atts, 'popupxd' );

		$popup_id = (int) $atts['id'];
		if ( ! $popup_id ) {
			return '';
		}

		$post = get_post( $popup_id );
		if ( ! $post || POPUPXD_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		// Popup switched off in the list → no button (hint for editors).
		if ( ! PopupXD_Helpers::is_enabled( $popup_id ) ) {
			return current_user_can( 'edit_post', $popup_id )
				? '<span class="popupxd-admin-hint" style="display:inline-block;padding:4px 8px;border:1px dashed #d63638;color:#d63638;font-size:12px;">' . esc_html( sprintf( 'Popup XD #%d đang TẮT nên nút bị ẩn với khách (chỉ admin thấy dòng này).', $popup_id ) ) . '</span>'
				: '';
		}

		// "Cho phép mở thủ công" is off → no button for visitors; editors get
		// a hint so the missing button isn't a mystery.
		if ( ! PopupXD_Frontend::manual_allowed( $popup_id ) ) {
			if ( current_user_can( 'edit_post', $popup_id ) ) {
				return sprintf(
					'<span class="popupxd-admin-hint" style="display:inline-block;padding:4px 8px;border:1px dashed #d63638;color:#d63638;font-size:12px;">%s</span>',
					esc_html( sprintf( 'Popup XD #%d: đang tắt "Cho phép mở thủ công" nên nút bị ẩn với khách (chỉ admin thấy dòng này).', $popup_id ) )
				);
			}
			return '';
		}

		// Queue the popup so its markup is printed in the footer even if this
		// page isn't matched by its targeting rules, and load CSS/JS now —
		// covers shortcodes in headers, footers, widgets and archives.
		PopupXD_Frontend::$force_ids[] = $popup_id;
		PopupXD_Frontend::enqueue_assets();
		PopupXD_Frontend::enqueue_popup_styles( $popup_id );

		$label = trim( wp_strip_all_tags( (string) $content ) );
		if ( '' === $label ) {
			$label = PopupXD_Helpers::get( $popup_id, 'button_text', 'Xem chi tiết' );
		}

		$style = sprintf(
			'background-color:%s;color:%s;border-radius:%dpx;padding:%s;font-size:%dpx;',
			PopupXD_Helpers::get( $popup_id, 'button_bg', '#2271b1' ),
			PopupXD_Helpers::get( $popup_id, 'button_color', '#ffffff' ),
			max( 0, (int) PopupXD_Helpers::get( $popup_id, 'button_radius', '6' ) ),
			PopupXD_Helpers::get( $popup_id, 'button_padding', '12px 24px' ),
			max( 8, (int) PopupXD_Helpers::get( $popup_id, 'button_font_size', '15' ) )
		);

		$classes = trim( 'popupxd-btn ' . PopupXD_Helpers::get( $popup_id, 'button_css_class', '' ) . ' ' . PopupXD_Helpers::classes( $atts['class'] ) );

		return sprintf(
			'<button type="button" class="%1$s" data-popupxd-open="%2$d" aria-haspopup="dialog" aria-controls="popupxd-%2$d" style="%3$s">%4$s</button>',
			esc_attr( $classes ),
			$popup_id,
			esc_attr( $style ),
			esc_html( $label )
		);
	}
}
