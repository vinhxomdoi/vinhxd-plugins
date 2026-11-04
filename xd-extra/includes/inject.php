<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Chèn Mã Nhúng (GTM, Pixel, live chat...).
 * Các ô này dành cho mã của khách; CSS/JS tính năng phải nằm trong plugin.
 */

add_action( 'admin_menu', function () {
	add_menu_page( 'Chèn Mã Nhúng', 'Chèn Mã Nhúng', 'manage_options', 'xd-inject', 'xd_inject_page', 'dashicons-editor-code', 4 );
	add_submenu_page( 'xd-inject', 'Chèn Mã Nhúng', 'Chèn Mã Nhúng', 'manage_options', 'xd-inject', 'xd_inject_page' );
} );

add_action( 'admin_head', function () {
	echo '<style>#adminmenu li.toplevel_page_xd-inject>a,#adminmenu li.toplevel_page_xd-inject>a:hover{background:#215B63}#adminmenu li.toplevel_page_xd-inject>a .wp-menu-name{color:#fff;font-weight:600}#adminmenu li.toplevel_page_xd-inject .wp-menu-image:before{color:#fff}</style>';
} );

/** Chỉ người có quyền unfiltered_html mới lưu được mã script (an toàn cho multisite). */
function xd_sanitize_code( $val ) {
	$val = (string) $val;
	return current_user_can( 'unfiltered_html' ) ? $val : wp_kses_post( $val );
}

/** CSS thuần: bỏ thẻ HTML và chặn đóng thẻ </style>. */
function xd_sanitize_css( $css ) {
	$css = wp_strip_all_tags( (string) $css );
	return str_ireplace( '</style', '', $css );
}

add_action( 'admin_init', function () {
	register_setting( 'xd_inject_group', 'xd_inject_header',  [ 'sanitize_callback' => 'xd_sanitize_code' ] );
	register_setting( 'xd_inject_group', 'xd_inject_footer',  [ 'sanitize_callback' => 'xd_sanitize_code' ] );
	register_setting( 'xd_inject_group', 'xd_inject_footer2', [ 'sanitize_callback' => 'xd_sanitize_code' ] );
} );

function xd_code_textarea_style() {
	return 'width:100%;font-family:monospace;font-size:13px;background:#1e1e2e;color:#cdd6f4;border:1px solid #444;border-radius:6px;padding:14px;resize:vertical;line-height:1.6;';
}

function xd_inject_page() {
	$boxes = [
		[ 'xd_inject_header',  'HEAD',   'Google Tag Manager, Facebook Pixel, meta xác minh...' ],
		[ 'xd_inject_footer',  'BODY',   'Live chat widget, mã theo dõi cuối trang...' ],
		[ 'xd_inject_footer2', 'FOOTER', 'Mã chèn trước mã BODY ở trên (in sớm hơn 1 bước).' ],
	];
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px;"><span style="background:#215B63;color:#fff;border-radius:8px;padding:6px 14px;font-size:20px;">⟨/⟩</span> Chèn Mã Nhúng</h1>
		<form method="post" action="options.php"><?php settings_fields( 'xd_inject_group' ); ?>
			<?php foreach ( $boxes as [ $opt, $label, $desc ] ) : ?>
			<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:24px;max-width:900px;margin-bottom:20px;">
				<h2 style="margin-top:0;">Mã chèn vào &lt;<?php echo esc_html( $label ); ?>&gt;</h2>
				<p class="description" style="margin-bottom:10px;"><?php echo esc_html( $desc ); ?></p>
				<textarea name="<?php echo esc_attr( $opt ); ?>" rows="10" style="<?php echo esc_attr( xd_code_textarea_style() ); ?>"><?php echo esc_textarea( get_option( $opt, '' ) ); ?></textarea>
			</div>
			<?php endforeach; ?>
			<?php submit_button( 'Lưu Cài Đặt' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'wp_head',   function () { $c = (string) get_option( 'xd_inject_header', '' );  if ( trim( $c ) !== '' ) echo "\n" . $c . "\n"; }, 1 );
add_action( 'wp_footer', function () { $c = (string) get_option( 'xd_inject_footer2', '' ); if ( trim( $c ) !== '' ) echo "\n" . $c . "\n"; }, 99 );
add_action( 'wp_footer', function () { $c = (string) get_option( 'xd_inject_footer', '' );  if ( trim( $c ) !== '' ) echo "\n" . $c . "\n"; }, 100 );

// CSS toàn site + CSS từng trang: xem includes/post-css.php (module "CSS tùy chỉnh").
