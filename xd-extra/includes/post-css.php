<?php
/**
 * XD Extra – Module "CSS tùy chỉnh"
 *
 * - CSS toàn site: option `xd_global_css` (autoload).
 * - CSS riêng từng trang: meta `_single_add_custom_css` (cùng key với plugin WP Add Custom CSS,
 *   nên dữ liệu cũ dùng được ngay, không cần chuyển).
 * - In inline trong <head> (wp_head, priority 100), không tạo request CSS động.
 *
 * Module có thể nằm cả ở core (XD Extra) lẫn plugin riêng của từng site; bản nào nạp trước thì chạy.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( defined( 'XD_POST_CSS_LOADED' ) ) return;
define( 'XD_POST_CSS_LOADED', 'xd-extra' );

// Hàm khai báo trong khối if chỉ được tạo khi khối chạy (sau dòng return ở trên),
// nên nếu bản khác của module đã nạp trước thì không bị lỗi khai báo trùng.
if ( ! function_exists( 'xd_pcss_clean' ) ) :

define( 'XD_PCSS_META', '_single_add_custom_css' );
define( 'XD_PCSS_OPTION', 'xd_global_css' );
define( 'XD_PCSS_NONCE', 'xd_pcss_nonce' );

/* ------------------------------------------------------------------
 * Hàm dùng chung
 * ---------------------------------------------------------------- */

/** Bỏ `</style` và `<script` khỏi CSS (dùng cả lúc lưu và lúc in). */
function xd_pcss_clean( $css ) {
	return (string) preg_replace( '#</style|<script#i', '', (string) $css );
}

/** CSS sẵn sàng in ra trang. */
function xd_pcss_prepare( $css ) {
	return trim( str_replace( '&gt;', '>', xd_pcss_clean( $css ) ) );
}

/** Post type có meta box: mọi post type public, trừ attachment và elementor_library. */
function xd_pcss_post_types() {
	$types = array_values( array_diff( get_post_types( [ 'public' => true ] ), [ 'attachment', 'elementor_library' ] ) );
	return (array) apply_filters( 'xd_post_css_post_types', $types );
}

/* ------------------------------------------------------------------
 * Chuyển dữ liệu lần đầu (chạy 1 lần)
 * ---------------------------------------------------------------- */
function xd_pcss_maybe_migrate() {
	if ( get_option( 'xd_pcss_migrated' ) ) return;

	$css = get_option( XD_PCSS_OPTION, null );
	if ( $css === null ) {
		// 1. Bản riêng của site (vd. XD Extra – Địa Điểm Bảo Lộc).
		$css = (string) get_option( 'xddb_global_css', '' );
		// 2. Plugin WP Add Custom CSS.
		if ( trim( $css ) === '' ) {
			$wpacc = get_option( 'wpacc_settings', [] );
			$css   = is_array( $wpacc ) ? (string) ( $wpacc['main_custom_style'] ?? '' ) : '';
		}
	} else {
		$css = (string) $css;
		// Đã có xd_global_css nhưng còn trống: vẫn lấy từ bản riêng của site nếu có.
		if ( trim( $css ) === '' ) $css = (string) get_option( 'xddb_global_css', '' );
	}

	// 3. Ô "CSS Tùy Chỉnh" của XD Extra 3.2.0 (đã gộp vào trang này).
	$old = trim( (string) get_option( 'xd_custom_css', '' ) );
	if ( $old !== '' && strpos( $css, $old ) === false ) {
		$css = rtrim( $css ) . ( trim( $css ) !== '' ? "\n\n" : '' ) . "/* Từ ô CSS Tùy Chỉnh cũ */\n" . $old;
	}

	$css = xd_pcss_clean( $css );
	if ( get_option( XD_PCSS_OPTION, null ) === null ) {
		add_option( XD_PCSS_OPTION, $css, '', true );
	} else {
		update_option( XD_PCSS_OPTION, $css, true );
	}
	update_option( 'xd_pcss_migrated', XD_VERSION, true );
}
add_action( 'init', 'xd_pcss_maybe_migrate', 5 );

/* ------------------------------------------------------------------
 * Front-end
 * ---------------------------------------------------------------- */
function xd_pcss_print() {
	$global = xd_pcss_prepare( get_option( XD_PCSS_OPTION, '' ) );
	if ( $global !== '' ) {
		echo '<style id="xd-global-css">' . "\n" . $global . "\n" . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSS đã lọc ở xd_pcss_prepare()
	}

	if ( ! is_singular( xd_pcss_post_types() ) ) return;
	$post_id = get_queried_object_id();
	if ( ! $post_id ) return;
	$css = xd_pcss_prepare( get_post_meta( $post_id, XD_PCSS_META, true ) );
	if ( $css !== '' ) {
		echo '<style id="xd-post-css">' . "\n" . $css . "\n" . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_head', 'xd_pcss_print', 100 );

/* ------------------------------------------------------------------
 * Meta box "CSS riêng cho trang này"
 * ---------------------------------------------------------------- */
function xd_pcss_add_meta_box() {
	foreach ( xd_pcss_post_types() as $type ) {
		add_meta_box( 'xd-post-css', 'CSS riêng cho trang này', 'xd_pcss_render_meta_box', $type, 'normal', 'low' );
	}
}
add_action( 'add_meta_boxes', 'xd_pcss_add_meta_box' );

function xd_pcss_render_meta_box( $post ) {
	wp_nonce_field( 'xd_pcss_save_' . $post->ID, XD_PCSS_NONCE );
	$css = (string) get_post_meta( $post->ID, XD_PCSS_META, true );
	echo '<p class="description" style="margin-top:0;">CSS thuần, không cần thẻ <code>&lt;style&gt;</code>. Chỉ áp dụng cho trang này, in sau CSS toàn site.</p>';
	echo '<textarea id="xd-pcss-field" class="xd-pcss-code" name="xd_post_css" rows="10" style="width:100%;font-family:monospace;font-size:13px;" spellcheck="false">' . esc_textarea( $css ) . '</textarea>';
}

function xd_pcss_save_post( $post_id, $post ) {
	if ( ! isset( $_POST[ XD_PCSS_NONCE ] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ XD_PCSS_NONCE ] ) ), 'xd_pcss_save_' . $post_id ) ) return;
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	if ( ! in_array( $post->post_type, xd_pcss_post_types(), true ) ) return;
	if ( ! isset( $_POST['xd_post_css'] ) ) return;

	$css = xd_pcss_clean( wp_unslash( $_POST['xd_post_css'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- CSS thô, đã lọc ở xd_pcss_clean()
	if ( trim( $css ) === '' ) {
		delete_post_meta( $post_id, XD_PCSS_META );
	} else {
		// update_post_meta tự unslash -> wp_slash để giữ dấu \ (vd. content:"\f101").
		update_post_meta( $post_id, XD_PCSS_META, wp_slash( $css ) );
	}
}
add_action( 'save_post', 'xd_pcss_save_post', 10, 2 );

/* ------------------------------------------------------------------
 * Trang "CSS toàn site" (Chèn Mã Nhúng → CSS toàn site)
 * ---------------------------------------------------------------- */
function xd_pcss_admin_menu() {
	add_submenu_page( 'xd-inject', 'CSS toàn site', 'CSS toàn site', 'manage_options', 'xd-custom-css', 'xd_pcss_global_page' );
}
add_action( 'admin_menu', 'xd_pcss_admin_menu', 20 );

function xd_pcss_handle_global_save() {
	if ( ! isset( $_POST['xd_pcss_global_save'] ) || ! current_user_can( 'manage_options' ) ) return false;
	check_admin_referer( 'xd_pcss_global' );
	$css = xd_pcss_clean( wp_unslash( $_POST['xd_global_css'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	update_option( XD_PCSS_OPTION, $css, true );
	return true;
}

function xd_pcss_global_page() {
	$saved = xd_pcss_handle_global_save();
	$css   = (string) get_option( XD_PCSS_OPTION, '' );
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px;"><span style="background:#215B63;color:#fff;border-radius:8px;padding:6px 14px;font-size:20px;">#</span> CSS toàn site</h1>
		<?php if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>Đã lưu CSS.</p></div>'; ?>
		<?php xd_pcss_conflict_notice(); ?>
		<form method="post">
			<?php wp_nonce_field( 'xd_pcss_global' ); ?>
			<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:24px;max-width:1000px;margin-bottom:16px;">
				<p class="description" style="margin:0 0 10px;">CSS thuần, không cần thẻ <code>&lt;style&gt;</code>. In inline trong <code>&lt;head&gt;</code> mọi trang. CSS riêng từng trang: ô "CSS riêng cho trang này" dưới trang sửa bài.</p>
				<textarea id="xd-pcss-global" class="xd-pcss-code" name="xd_global_css" rows="24" style="width:100%;font-family:monospace;font-size:13px;" spellcheck="false"><?php echo esc_textarea( $css ); ?></textarea>
			</div>
			<?php submit_button( 'Lưu CSS', 'primary', 'xd_pcss_global_save' ); ?>
		</form>
	</div>
	<?php
}

/** Cảnh báo khi WP Add Custom CSS còn bật (CSS sẽ bị in 2 lần). */
function xd_pcss_conflict_notice() {
	if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( is_plugin_active( 'wp-add-custom-css/wp-add-custom-css.php' ) ) {
		echo '<div class="notice notice-warning"><p><strong>WP Add Custom CSS</strong> vẫn đang bật nên CSS sẽ bị in 2 lần. Dữ liệu đã có ở đây, bạn có thể tắt plugin đó.</p></div>';
	}
}

/* ------------------------------------------------------------------
 * Trình soạn CSS có tô màu (CodeMirror của WordPress), nếu người dùng bật
 * ---------------------------------------------------------------- */
function xd_pcss_admin_assets( $hook ) {
	$is_global = $hook === 'chen-ma-nhung_page_xd-custom-css' || ( isset( $_GET['page'] ) && $_GET['page'] === 'xd-custom-css' ); // phpcs:ignore WordPress.Security.NonceVerification
	$is_edit   = in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && in_array( get_current_screen()->post_type ?? '', xd_pcss_post_types(), true );
	if ( ! $is_global && ! $is_edit ) return;

	$settings = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );
	if ( $settings === false ) return; // người dùng tắt tô màu cú pháp -> dùng textarea thường

	wp_add_inline_script( 'code-editor', sprintf(
		'jQuery(function(){document.querySelectorAll(".xd-pcss-code").forEach(function(t){var e=wp.codeEditor.initialize(t,%s);e.codemirror.setSize(null,%d);e.codemirror.on("change",function(cm){cm.save();});if(window.ResizeObserver){new ResizeObserver(function(){e.codemirror.refresh();}).observe(e.codemirror.getWrapperElement());}});});',
		wp_json_encode( $settings ),
		$is_global ? 520 : 240
	) );
}
add_action( 'admin_enqueue_scripts', 'xd_pcss_admin_assets' );

endif;
