<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * CSS / JS
 * ---------------------------------------------------------------- */
function xd_uses_single_archive_template() {
	if ( is_singular( 'post' ) ) return get_option( 'xd_tpl_single_post', '1' ) === '1';
	if ( is_category() || is_tag() ) return get_option( 'xd_tpl_archive_post', '1' ) === '1';
	return false;
}

function xd_floating_ui_enabled() {
	if ( get_option( 'xd_totop_btn', '1' ) === '1' ) return true;
	foreach ( xd_cbtn_get() as $cfg ) {
		if ( $cfg['enable'] === '1' ) return true;
	}
	return false;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'xd-extra-style', XD_ASSETS_URL . 'css/xd-extra.css', [], XD_VERSION );
	wp_register_style( 'xd-news', XD_ASSETS_URL . 'css/news.css', [], XD_VERSION );
	wp_register_style( 'xd-lien-he', XD_ASSETS_URL . 'css/lien-he.css', [], XD_VERSION );
	wp_register_style( 'xd-single-archive-style', XD_ASSETS_URL . 'css/single-archive.css', [], XD_VERSION );
	wp_register_style( 'xd-page-xd', XD_ASSETS_URL . 'css/page-xd.css', [], XD_VERSION );
	wp_register_script( 'xd-frontend', XD_ASSETS_URL . 'js/xd-frontend.js', [], XD_VERSION, true );

	if ( xd_floating_ui_enabled() ) {
		wp_enqueue_style( 'xd-extra-style' );
		if ( get_option( 'xd_totop_btn', '1' ) === '1' ) wp_enqueue_script( 'xd-frontend' );
	}
	if ( xd_uses_single_archive_template() ) {
		wp_enqueue_style( 'xd-single-archive-style' );
	}
	if ( is_page() && get_post_meta( get_queried_object_id(), '_wp_page_template', true ) === 'page-xd-plugin' ) {
		wp_enqueue_style( 'xd-page-xd' );
	}
	// Shortcode có trong nội dung trang -> nạp CSS ở <head> luôn.
	if ( xd_page_has_shortcode( 'xd_news' ) )    wp_enqueue_style( 'xd-news' );
	if ( xd_page_has_shortcode( 'xd_lien_he' ) ) wp_enqueue_style( 'xd-lien-he' );
} );

/* ------------------------------------------------------------------
 * <head>: favicon, khung .xd-container, biến màu & font
 * ---------------------------------------------------------------- */
add_action( 'wp_head', function () {
	$favicon_id = (int) get_option( 'xd_favicon_id', 0 );
	if ( ! $favicon_id ) return;
	$url = wp_get_attachment_url( $favicon_id );
	if ( ! $url ) return;
	$ext  = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
	$type = $ext === 'svg' ? 'image/svg+xml' : ( $ext === 'ico' ? 'image/x-icon' : 'image/png' );
	echo '<link rel="icon" type="' . esc_attr( $type ) . '" href="' . esc_url( $url ) . '">' . "\n";
	$touch = $ext === 'svg' || $ext === 'ico' ? $url : ( wp_get_attachment_image_url( $favicon_id, [ 180, 180 ] ) ?: $url );
	echo '<link rel="apple-touch-icon" href="' . esc_url( $touch ) . '">' . "\n";
}, 1 );

add_action( 'wp_head', function () {
	$vars = '';
	foreach ( [ 'primary', 'secondary', 'text', 'accent' ] as $k ) {
		$v = sanitize_hex_color( (string) get_option( 'xd_color_' . $k, '' ) );
		if ( $v ) $vars .= '--xd-' . $k . ':' . $v . ';';
	}
	$font = trim( (string) get_option( 'xd_font_family', '' ) );
	$font = preg_replace( '/[^\p{L}\p{N} \-]/u', '', $font );
	if ( $font ) $vars .= "--xd-font:'" . $font . "',sans-serif;";

	echo '<style id="xd-design-vars">.xd-container{padding:10px;margin:auto;max-width:1240px}';
	if ( $vars ) echo ':root{' . $vars . '}';
	if ( $font && get_option( 'xd_font_apply_global', '0' ) === '1' ) {
		// Không ép lên span/div/i để khỏi phá font icon (Font Awesome, eicons...).
		echo "body,h1,h2,h3,h4,h5,h6,p,a,li,label,button,input,textarea,select{font-family:'" . $font . "',sans-serif!important}";
	}
	echo '</style>' . "\n";

	if ( $font ) {
		$family = str_replace( ' ', '+', $font ) . ':wght@400;500;600;700';
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . esc_attr( $family ) . '&display=swap">' . "\n";
	}
}, 2 );

/* ------------------------------------------------------------------
 * Nút lên đầu trang
 * ---------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	if ( get_option( 'xd_totop_btn', '1' ) !== '1' ) return;
	$c1  = sanitize_hex_color( get_option( 'xd_totop_color', '#238543' ) ) ?: '#238543';
	$c2  = sanitize_hex_color( get_option( 'xd_totop_color2', '#f7c315' ) ) ?: '#f7c315';
	$pos = get_option( 'xd_totop_pos', 'right' ) === 'left' ? 'left' : 'right';
	$b   = (int) get_option( 'xd_totop_bottom', 50 );
	$bm  = get_option( 'xd_totop_bottom_m', '' );
	$bm  = $bm === '' ? $b : (int) $bm;
	$style = '--xd-tt1:' . $c1 . ';--xd-tt2:' . $c2 . ';--xd-b:' . $b . 'px;--xd-bm:' . $bm . 'px';
	echo '<button type="button" id="xd-back-to-top" class="xd-totop-' . $pos . '" style="' . esc_attr( $style ) . '" aria-label="Lên đầu trang"><span class="xd-totop-inner">' . xd_icon( 'up', 22 ) . '</span></button>';
}, 20 );

/* ------------------------------------------------------------------
 * Coming Soon (503, admin vẫn xem bình thường)
 * ---------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	if ( get_option( 'xd_cs_status' ) !== '1' ) return;
	if ( current_user_can( 'manage_options' ) ) return;
	if ( is_robots() || is_favicon() ) return;
	$content = get_option( 'xd_cs_content', '' );
	status_header( 503 );
	nocache_headers();
	header( 'Retry-After: 3600' );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( XD_ASSETS_URL . 'css/coming-soon.css?ver=' . XD_VERSION ); ?>">
</head>
<body class="xd-cs-body">
<div class="xd-cs-container">
<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
<div><?php echo wp_kses_post( wpautop( $content ) ); ?></div>
</div>
</body>
</html>
	<?php
	exit;
} );

/* ------------------------------------------------------------------
 * Paste as Text trong trình soạn thảo cổ điển (TinyMCE)
 * ---------------------------------------------------------------- */
add_filter( 'tiny_mce_before_init', function ( $init ) {
	if ( get_option( 'xd_paste_as_text', '0' ) === '1' ) {
		$init['paste_as_text'] = true;
	}
	return $init;
} );
