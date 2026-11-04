<?php
/**
 * Hàm dùng chung khi render.
 *
 * @package Elementor_XD
 */

namespace EXD;

defined( 'ABSPATH' ) || exit;

final class H {

	/**
	 * In ảnh từ control MEDIA: có ID thì dùng wp_get_attachment_image (srcset, width/height).
	 *
	 * @param array  $media  Giá trị control MEDIA.
	 * @param string $size   Cỡ ảnh WP.
	 * @param array  $attr   class, sizes, loading, fetchpriority, alt...
	 */
	public static function img( $media, $size = 'large', $attr = array() ): string {
		if ( empty( $media['id'] ) && empty( $media['url'] ) ) {
			return '';
		}
		$attr = array_filter( $attr, static function ( $v ) {
			return '' !== $v && null !== $v;
		} );
		if ( ! isset( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( ! isset( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		if ( ! empty( $media['id'] ) && wp_attachment_is_image( $media['id'] ) ) {
			return (string) wp_get_attachment_image( (int) $media['id'], $size, false, $attr );
		}
		$out = '<img src="' . esc_url( $media['url'] ) . '"';
		if ( ! isset( $attr['alt'] ) ) {
			$attr['alt'] = '';
		}
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return $out . '>';
	}

	/** Thuộc tính sizes theo số cột hiển thị trên desktop. */
	public static function sizes( $cols = 1 ): string {
		$cols = max( 1, (int) $cols );
		$vw   = (int) round( 100 / $cols );
		return '(max-width: 767px) 100vw, (max-width: 1024px) ' . min( 100, $vw * 2 ) . 'vw, ' . $vw . 'vw';
	}

	/** Icon từ control ICONS. */
	public static function icon( $icon, $attrs = array() ): string {
		if ( empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, array_merge( array( 'aria-hidden' => 'true' ), $attrs ) );
		return (string) ob_get_clean();
	}

	/** Danh sách template Elementor cho control SELECT. */
	public static function templates(): array {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list  = array( '' => '— Chọn template —' );
		$posts = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $id ) {
			$list[ $id ] = get_the_title( $id ) . ' (#' . $id . ')';
		}
		return $list;
	}

	/** Nội dung 1 template Elementor (kèm CSS của template). */
	public static function template( $id ): string {
		$id = absint( $id );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		if ( get_the_ID() === $id ) {
			return ''; // chống lặp vô hạn
		}
		return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
	}

	/**
	 * Đổi link video thành URL nhúng. Trả về ['type'=>'iframe|file', 'src'=>...].
	 */
	public static function video( $url ): array {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return array();
		}
		if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $url, $m ) ) {
			return array(
				'type' => 'iframe',
				'src'  => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0&playsinline=1',
				'id'   => $m[1],
			);
		}
		if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
			return array(
				'type' => 'iframe',
				'src'  => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1',
			);
		}
		if ( preg_match( '~\.(mp4|webm|ogg)(\?.*)?$~i', $url ) ) {
			return array(
				'type' => 'file',
				'src'  => $url,
			);
		}
		return array(
			'type' => 'iframe',
			'src'  => $url,
		);
	}

	/** Đang ở trình soạn thảo / xem trước của Elementor? */
	public static function is_editor(): bool {
		$p = \Elementor\Plugin::instance();
		return ( isset( $p->editor ) && $p->editor->is_edit_mode() ) || ( isset( $p->preview ) && $p->preview->is_preview_mode() );
	}

	/**
	 * Request đang là khung xem trước của trình soạn thảo Elementor (iframe ?elementor-preview=).
	 * Dùng cho nạp asset: không phụ thuộc trạng thái ajax lưu trang.
	 */
	public static function is_preview_request(): bool {
		return isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Cho phép thẻ tiêu đề hợp lệ. */
	public static function tag( $tag, $default = 'h3' ): string {
		$ok = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
		return in_array( $tag, $ok, true ) ? $tag : $default;
	}

	/** Tiêu đề: *chữ* → <mark> (tô màu nhấn). */
	public static function highlight( $text ): string {
		$text = wp_kses_post( $text );
		return preg_replace( '/\*(.+?)\*/u', '<mark class="exd-hl">$1</mark>', $text );
	}

	/** Mũi tên SVG nhỏ dùng cho link/nút (không cần font icon). */
	public static function arrow(): string {
		return '<svg class="exd-arrow" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	public static function play(): string {
		return '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z" fill="currentColor"/></svg>';
	}
}
