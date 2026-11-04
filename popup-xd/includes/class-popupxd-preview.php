<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin live preview + "create Elementor template" helper.
 *
 * The preview is a real front-end request (theme + Elementor CSS load
 * exactly as on the site) rendered into an iframe on the popup edit screen.
 * The edit form is POSTed to it, so unsaved changes are shown without
 * saving, and POST responses are never stored by page caches.
 */
class PopupXD_Preview {

	const QUERY_VAR    = 'popupxd_preview';
	const NONCE_ACTION = 'popupxd_preview';
	const AJAX_CREATE  = 'popupxd_create_template';

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
		add_action( 'wp_ajax_' . self::AJAX_CREATE, array( $this, 'ajax_create_template' ) );
	}

	public static function preview_url() {
		return add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
	}

	public static function edit_template_url( $template_id ) {
		return add_query_arg( array( 'post' => (int) $template_id, 'action' => 'elementor' ), admin_url( 'post.php' ) );
	}

	/** Capability to create Elementor library templates. */
	public static function can_create_templates() {
		$obj = get_post_type_object( 'elementor_library' );
		$cap = ( $obj && isset( $obj->cap->create_posts ) ) ? $obj->cap->create_posts : 'edit_posts';
		return current_user_can( $cap );
	}

	/* --------------------------------------------------------------------
	 * Preview page
	 * ------------------------------------------------------------------ */

	public function maybe_render() {
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		// Never cache this response (LiteSpeed, WP Rocket, W3TC, Cloudflare…).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		$popup_id = isset( $_POST['popup_id'] ) ? absint( $_POST['popup_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$nonce    = isset( $_POST['_pxdnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_pxdnonce'] ) ) : '';

		if ( ! $popup_id || ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $popup_id ) || ! current_user_can( 'edit_post', $popup_id ) ) {
			status_header( 403 );
			$this->message_page( 'Phiên xem trước đã hết hạn hoặc bạn không có quyền. Hãy tải lại trang sửa popup.' );
		}

		$post = get_post( $popup_id );
		if ( ! $post || POPUPXD_CPT !== $post->post_type ) {
			status_header( 404 );
			$this->message_page( 'Không tìm thấy popup.' );
		}

		// Unsaved values from the edit form, cleaned with the same rules as Save.
		$values = PopupXD_Helpers::from_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Preview = always open, no automatic triggers, no cookie, no re-open.
		foreach ( array( 'trigger_time', 'trigger_scroll', 'trigger_exit', 'trigger_click', 'cookie_enable', 'reopen_enable', 'form_hide_enable' ) as $off ) {
			$values[ $off ] = 'no';
		}
		$values['target_devices'] = array( 'desktop', 'tablet', 'mobile' );

		if ( isset( $_POST['pxd_preview_content'] ) ) {
			$values['__content'] = wp_kses_post( wp_unslash( $_POST['pxd_preview_content'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		if ( isset( $_POST['post_title'] ) ) {
			$values['__title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
		}

		$template_id = ( 'elementor' === $values['content_type'] ) ? (int) $values['elementor_template_id'] : 0;

		PopupXD_Frontend::$preview_mode = true;
		show_admin_bar( false );
		add_filter( 'show_admin_bar', '__return_false', 99 );

		// Load our assets + Elementor's in <head>, like a normal page would.
		add_action( 'wp_enqueue_scripts', function () use ( $template_id ) {
			PopupXD_Frontend::enqueue_assets();
			if ( $template_id ) {
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$frontend = \Elementor\Plugin::$instance->frontend;
					if ( $frontend && method_exists( $frontend, 'enqueue_styles' ) ) {
						$frontend->enqueue_styles();
					}
				}
				PopupXD_Elementor::ensure_styles( $template_id );
			}
		}, 20 );

		$this->render_page( $post, $values, $template_id );
		exit;
	}

	private function render_page( $post, $values, $template_id ) {
		if ( 'elementor' === $values['content_type'] && ! $template_id ) {
			// Show the frame with a hint instead of an empty box.
			$values['content_type'] = 'editor';
			$values['__content']    = '<div class="pxd-preview-empty">Chưa chọn mẫu Elementor — chọn mẫu có sẵn hoặc bấm "Tạo mẫu mới".</div>';
		}
		$popup_html = PopupXD_Frontend::render_popup( $post, $values );
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
<style>
	html, body { margin: 0 !important; padding: 0 !important; }
	body.popupxd-preview-body { min-height: 100vh; background: #f3f4f6; }
	.pxd-mock { max-width: 1100px; margin: 0 auto; padding: 24px; box-sizing: border-box; font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
	.pxd-mock-bar { height: 56px; border-radius: 8px; background: #e2e5ea; margin-bottom: 24px; }
	.pxd-mock-hero { height: 220px; border-radius: 10px; background: #d9dde3; margin-bottom: 24px; }
	.pxd-mock-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
	.pxd-mock-row div { height: 140px; border-radius: 8px; background: #e2e5ea; }
	.pxd-mock-line { height: 12px; border-radius: 6px; background: #e2e5ea; margin: 14px 0; }
	@media (max-width: 767px) { .pxd-mock-row { grid-template-columns: 1fr; } }
	.pxd-preview-empty { padding: 40px 24px; text-align: center; color: #646970; font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
</style>
</head>
<body class="popupxd-preview-body">
	<div class="pxd-mock" aria-hidden="true">
		<div class="pxd-mock-bar"></div>
		<div class="pxd-mock-hero"></div>
		<div class="pxd-mock-row"><div></div><div></div><div></div></div>
		<div class="pxd-mock-line"></div><div class="pxd-mock-line" style="width:80%"></div><div class="pxd-mock-line" style="width:60%"></div>
	</div>
	<?php
	echo $popup_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by render_popup().
	// Tells frontend.js not to push analytics events from the admin preview.
	echo '<script>window.PopupXDPreview = true;</script>';
	wp_footer();
	?>
<script>
(function () {
	var id = <?php echo (int) $post->ID; ?>;
	function show() {
		if ( window.PopupXD ) {
			window.PopupXD.open( id, { noFocus: true } );
		}
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		show();
		// Closing (X, overlay, Esc) replays the entrance animation.
		document.addEventListener( 'popupxd:close', function () {
			window.setTimeout( show, 700 );
		} );
		// Links inside the preview must not navigate the frame away.
		document.addEventListener( 'click', function ( e ) {
			var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
			if ( a && ! /^#/.test( a.getAttribute( 'href' ) ) ) {
				e.preventDefault();
			}
		}, true );
	} );
})();
</script>
</body>
</html>
		<?php
	}

	private function message_page( $message ) {
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"></head><body style="margin:0;font:14px/1.5 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#646970;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:24px;box-sizing:border-box;">' . esc_html( $message ) . '</body></html>';
		exit;
	}

	/* --------------------------------------------------------------------
	 * AJAX: create an empty Elementor template for this popup
	 * ------------------------------------------------------------------ */

	public function ajax_create_template() {
		check_ajax_referer( self::AJAX_CREATE, 'nonce' );

		$popup_id = isset( $_POST['popup_id'] ) ? absint( $_POST['popup_id'] ) : 0;
		if ( ! $popup_id || ! current_user_can( 'edit_post', $popup_id ) || ! self::can_create_templates() ) {
			wp_send_json_error( array( 'message' => 'Bạn không có quyền tạo mẫu Elementor.' ), 403 );
		}
		if ( ! PopupXD_Elementor::is_active() || ! post_type_exists( 'elementor_library' ) ) {
			wp_send_json_error( array( 'message' => 'Chưa kích hoạt Elementor.' ), 400 );
		}

		$popup_title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $popup_title ) {
			$popup_title = get_the_title( $popup_id );
		}
		$title = 'Popup XD – ' . ( '' !== $popup_title ? $popup_title : '#' . $popup_id );

		$template_id = PopupXD_Elementor::create_template( $title );
		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => 'Không tạo được mẫu Elementor, vui lòng thử lại.' ), 500 );
		}

		wp_send_json_success( array(
			'id'       => $template_id,
			'title'    => get_the_title( $template_id ),
			'edit_url' => self::edit_template_url( $template_id ),
		) );
	}
}
