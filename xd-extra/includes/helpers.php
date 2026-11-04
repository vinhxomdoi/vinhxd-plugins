<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Đọc 1 trường trong Option page "thong-tin" (JetEngine). Gọi không tham số để lấy cả mảng.
 */
function xd_info( $key = null, $default = '' ) {
	static $info = null;
	if ( $info === null ) {
		$info = get_option( 'thong-tin', [] );
		if ( ! is_array( $info ) ) $info = [];
	}
	if ( $key === null ) return $info;
	$val = $info[ $key ] ?? $default;
	return is_string( $val ) ? trim( $val ) : $val;
}

/** Số điện thoại chỉ giữ số và dấu + để dùng trong tel: */
function xd_tel( $phone ) {
	return preg_replace( '/[^0-9+]/', '', (string) $phone );
}

/** Chuẩn hoá Zalo: nhập số điện thoại -> https://zalo.me/<số>, nhập link thì giữ nguyên. */
function xd_zalo_url( $zalo ) {
	$zalo = trim( (string) $zalo );
	if ( $zalo === '' ) return '';
	if ( preg_match( '/^[\d\+\s\-\.]+$/', $zalo ) ) {
		return 'https://zalo.me/' . preg_replace( '/[^0-9]/', '', $zalo );
	}
	return $zalo;
}

/** Lấy URL ảnh từ giá trị JetEngine media (ID, URL hoặc mảng). */
function xd_media_url( $val, $size = 'full' ) {
	if ( empty( $val ) ) return '';
	if ( is_numeric( $val ) ) return (string) wp_get_attachment_image_url( (int) $val, $size );
	if ( is_array( $val ) ) {
		if ( ! empty( $val['id'] ) ) return (string) wp_get_attachment_image_url( (int) $val['id'], $size );
		return (string) ( $val['url'] ?? '' );
	}
	return (string) $val;
}

/** Icon SVG dùng chung (fill = currentColor). */
function xd_icon( $name, $size = 20 ) {
	static $paths = [
		'phone' => 'M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1C10.61 21 3 13.39 3 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z',
		'pin'   => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z',
		'mail'  => 'M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z',
		'chat'  => 'M12 3C6.5 3 2 6.9 2 11.7c0 2.7 1.4 5.1 3.7 6.7L5 21.5l3.5-1.8c1.1.3 2.3.5 3.5.5 5.5 0 10-3.9 10-8.7S17.5 3 12 3z',
		'route' => 'M21.7 11.3l-9-9a1 1 0 00-1.4 0l-9 9a1 1 0 000 1.4l9 9a1 1 0 001.4 0l9-9a1 1 0 000-1.4zM14 14.5V12h-4v3H8v-4a1 1 0 011-1h5V7.5l3.5 3.5-3.5 3.5z',
		'map'   => 'M15 5.1L9 3 3 5v16l6-2 6 2 6-2V3l-6 2.1zM15 19l-6-2.1V5l6 2.1V19z',
		'clock' => 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.6l3.2 1.9-.8 1.3L11 13V7h2v5.6z',
		'user'  => 'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z',
		'eye'   => 'M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5C21.3 7.6 17 4.5 12 4.5zm0 12.5a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z',
		'tag'   => 'M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z',
		'fb'    => 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3V2z',
		'up'    => 'M12 7.6l-7.3 7.3 1.4 1.4L12 10.4l5.9 5.9 1.4-1.4z',
	];
	if ( ! isset( $paths[ $name ] ) ) return '';
	$s = (int) $size;
	return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . $paths[ $name ] . '"/></svg>';
}

/**
 * Trang hiện tại có dùng shortcode $tag không (nội dung thường + dữ liệu Elementor).
 * Dùng để nạp CSS ngay trong <head>, tránh giật layout.
 */
function xd_page_has_shortcode( $tag ) {
	if ( ! is_singular() ) return false;
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) return false;
	if ( has_shortcode( $post->post_content, $tag ) ) return true;
	$el = get_post_meta( $post->ID, '_elementor_data', true );
	return is_string( $el ) && strpos( $el, '[' . $tag ) !== false;
}

/**
 * Đảm bảo CSS của shortcode có mặt. Nếu chưa nạp ở <head> (shortcode nằm trong
 * template Elementor/JetThemeCore...), in CSS inline 1 lần ngay trước khối để không giật layout.
 */
function xd_shortcode_css( $handle, $file ) {
	static $printed = [];
	if ( isset( $printed[ $handle ] ) || wp_style_is( $handle, 'done' ) || wp_style_is( $handle, 'enqueued' ) ) return '';
	$printed[ $handle ] = true;
	$path = XD_ASSETS_DIR . 'css/' . $file;
	if ( ! is_readable( $path ) ) return '';
	return '<style id="' . esc_attr( $handle ) . '-inline">' . file_get_contents( $path ) . '</style>';
}
