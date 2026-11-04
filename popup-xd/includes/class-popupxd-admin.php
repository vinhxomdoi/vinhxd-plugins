<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_Admin {

	public function __construct() {
		add_filter( 'manage_' . POPUPXD_CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . POPUPXD_CPT . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_class', array( $this, 'row_class' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'elementor_notice' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_popupxd_export', array( $this, 'export_popup' ) );
		add_action( 'admin_post_popupxd_import', array( $this, 'import_popup' ) );
		add_action( 'admin_post_popupxd_duplicate', array( $this, 'duplicate_popup' ) );
		add_action( 'wp_ajax_popupxd_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'admin_notices', array( $this, 'import_export_ui' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'list_assets' ) );
	}

	/* --------------------------------------------------------------------
	 * List table
	 * ------------------------------------------------------------------ */

	public function list_assets( $hook ) {
		$screen = get_current_screen();
		if ( 'edit.php' !== $hook || ! $screen || 'edit-' . POPUPXD_CPT !== $screen->id ) {
			return;
		}
		wp_enqueue_style( 'popupxd-admin', POPUPXD_URL . 'assets/admin/admin.css', array(), PopupXD_Helpers::asset_version( 'assets/admin/admin.css' ) );
		wp_enqueue_script( 'popupxd-admin-list', POPUPXD_URL . 'assets/admin/admin-list.js', array( 'jquery' ), PopupXD_Helpers::asset_version( 'assets/admin/admin-list.js' ), true );
		wp_localize_script( 'popupxd-admin-list', 'PopupXDList', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'popupxd_toggle' ),
		) );
	}

	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['pxd_status'] = 'Bật/Tắt';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['pxd_summary']   = 'Hoạt động';
				$new['pxd_target']    = 'Nơi hiển thị';
				$new['pxd_shortcode'] = 'Shortcode';
			}
		}
		return $new;
	}

	public function row_class( $classes, $class, $post_id ) {
		if ( is_admin() && POPUPXD_CPT === get_post_type( $post_id ) && ! PopupXD_Helpers::is_enabled( $post_id ) ) {
			$classes[] = 'pxd-is-off';
		}
		return $classes;
	}

	private function m( $post_id, $key, $default = '' ) {
		return PopupXD_Helpers::meta( $post_id, $key, $default );
	}

	/**
	 * Capability needed to create popups (CPT uses capability_type "page").
	 */
	private function create_cap() {
		$obj = get_post_type_object( POPUPXD_CPT );
		return ( $obj && isset( $obj->cap->create_posts ) ) ? $obj->cap->create_posts : 'edit_pages';
	}

	public function column_content( $column, $post_id ) {
		if ( 'pxd_status' === $column ) {
			$on  = PopupXD_Helpers::is_enabled( $post_id );
			$can = current_user_can( 'edit_post', $post_id );
			printf(
				'<button type="button" class="pxd-list-switch" role="switch" aria-checked="%1$s" data-id="%2$d" %3$s aria-label="%4$s"><span class="pxd-switch-ui" aria-hidden="true"></span><span class="pxd-list-switch-text">%5$s</span></button>',
				$on ? 'true' : 'false',
				(int) $post_id,
				$can ? '' : 'disabled',
				esc_attr( 'Bật/tắt popup ' . get_the_title( $post_id ) ),
				$on ? 'Bật' : 'Tắt'
			);
			if ( 'publish' !== get_post_status( $post_id ) ) {
				echo '<br><small style="color:#8c8f94;">(chưa đăng)</small>';
			}
		}

		if ( 'pxd_summary' === $column ) {
			echo '<span class="pxd-list-summary">' . esc_html( PopupXD_Helpers::summary( $post_id ) ) . '</span>';
		}

		if ( 'pxd_target' === $column ) {
			if ( 'everywhere' === $this->m( $post_id, 'display_scope', 'everywhere' ) ) {
				echo 'Toàn bộ website';
			} else {
				$pages = count( PopupXD_Helpers::get_array( $post_id, 'target_pages' ) );
				$types = PopupXD_Helpers::get_array( $post_id, 'target_post_types' );
				$parts = array();
				if ( $pages ) {
					$parts[] = $pages . ' trang';
				}
				foreach ( $types as $t ) {
					$obj     = get_post_type_object( $t );
					$parts[] = $obj ? $obj->labels->name : $t;
				}
				echo $parts ? esc_html( implode( ', ', $parts ) ) : '<span style="color:#a00;">Chưa chọn nơi hiển thị</span>';
			}
			$excl = $this->m( $post_id, 'exclude_ids', '' );
			if ( $excl ) {
				echo '<br><small>' . esc_html( 'Trừ: ' . $excl ) . '</small>';
			}
		}

		if ( 'pxd_shortcode' === $column ) {
			printf( '<code>[popupxd id="%d"]</code>', (int) $post_id );
			if ( 'no' === $this->m( $post_id, 'trigger_manual', 'yes' ) ) {
				echo '<br><small style="color:#a00;">Đang tắt "mở thủ công" — nút sẽ bị ẩn</small>';
			}
		}
	}

	/**
	 * AJAX: switch a popup on/off from the list.
	 */
	public function ajax_toggle() {
		check_ajax_referer( 'popupxd_toggle', 'nonce' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || POPUPXD_CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Bạn không có quyền sửa popup này.' ), 403 );
		}
		$on = isset( $_POST['on'] ) && '1' === $_POST['on'];
		update_post_meta( $id, POPUPXD_META_PREFIX . 'enabled', $on ? 'yes' : 'no' );
		wp_send_json_success( array( 'on' => $on, 'summary' => PopupXD_Helpers::summary( $id ) ) );
	}

	/**
	 * Row action: copy a popup (title, content and every setting) as a draft.
	 */
	public function duplicate_popup() {
		$popup_id = isset( $_GET['popup_id'] ) ? absint( $_GET['popup_id'] ) : 0;
		if ( ! $popup_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'popupxd_duplicate_' . $popup_id ) ) {
			wp_die( 'Yêu cầu không hợp lệ.' );
		}
		$post = get_post( $popup_id );
		if ( ! $post || POPUPXD_CPT !== $post->post_type || ! current_user_can( 'edit_post', $popup_id ) || ! current_user_can( $this->create_cap() ) ) {
			wp_die( 'Bạn không có quyền nhân bản popup này.' );
		}

		$new_id = wp_insert_post( array(
			'post_type'    => POPUPXD_CPT,
			'post_status'  => 'draft',
			'post_title'   => $post->post_title . ' (bản sao)',
			'post_content' => $post->post_content,
			'post_author'  => get_current_user_id(),
		), true );
		if ( is_wp_error( $new_id ) ) {
			wp_die( 'Không nhân bản được popup.' );
		}
		foreach ( get_post_meta( $popup_id ) as $key => $values ) {
			if ( 0 === strpos( $key, POPUPXD_META_PREFIX ) ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
			}
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $new_id . '&action=edit&popupxd_duplicated=1' ) );
		exit;
	}

	public function elementor_notice() {
		global $post_type;
		if ( POPUPXD_CPT !== $post_type ) {
			return;
		}
		if ( isset( $_GET['popupxd_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Đã nhân bản popup. Bản sao đang ở trạng thái <strong>Nháp</strong> — chỉnh sửa rồi bấm Đăng.</p></div>';
		}
		if ( PopupXD_Elementor::is_active() ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Popup XD:</strong> Chưa phát hiện Elementor. Bạn vẫn có thể tạo nội dung popup bằng trình soạn thảo WordPress; cài Elementor để dùng thêm mẫu đã lưu (Saved Templates).</p></div>';
	}

	/**
	 * "Xuất JSON" row action on the popup list table.
	 */
	public function row_actions( $actions, $post ) {
		if ( POPUPXD_CPT !== $post->post_type ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=popupxd_export&popup_id=' . $post->ID ),
			'popupxd_export_' . $post->ID
		);
		$actions['popupxd_export'] = '<a href="' . esc_url( $url ) . '">Xuất JSON</a>';
		if ( current_user_can( 'edit_post', $post->ID ) && current_user_can( $this->create_cap() ) ) {
			$dup = wp_nonce_url(
				admin_url( 'admin-post.php?action=popupxd_duplicate&popup_id=' . $post->ID ),
				'popupxd_duplicate_' . $post->ID
			);
			$actions['popupxd_duplicate'] = '<a href="' . esc_url( $dup ) . '">Nhân bản</a>';
		}
		return $actions;
	}

	/**
	 * Downloads all Popup XD meta (+ title/content) for one popup as a JSON file.
	 */
	public function export_popup() {
		$popup_id = isset( $_GET['popup_id'] ) ? (int) $_GET['popup_id'] : 0;
		if ( ! $popup_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'popupxd_export_' . $popup_id ) ) {
			wp_die( 'Yêu cầu không hợp lệ.' );
		}
		if ( ! current_user_can( 'edit_post', $popup_id ) ) {
			wp_die( 'Bạn không có quyền xuất popup này.' );
		}
		$post = get_post( $popup_id );
		if ( ! $post || POPUPXD_CPT !== $post->post_type ) {
			wp_die( 'Không tìm thấy popup.' );
		}

		$export_meta = array();
		foreach ( get_post_meta( $popup_id ) as $key => $values ) {
			if ( 0 === strpos( $key, POPUPXD_META_PREFIX ) ) {
				$short                 = substr( $key, strlen( POPUPXD_META_PREFIX ) );
				// get_post_meta( $id ) returns raw (serialized) strings — decode so
				// arrays (target pages / types / devices) are exported as real JSON arrays.
				$export_meta[ $short ] = maybe_unserialize( $values[0] );
			}
		}

		$data = array(
			'plugin'  => 'popup-xd',
			'version' => POPUPXD_VERSION,
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'meta'    => $export_meta,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="popupxd-' . sanitize_title( $post->post_title ) . '-' . $popup_id . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Creates a new (draft) popup from an uploaded Popup XD JSON export.
	 */
	public function import_popup() {
		if ( ! isset( $_POST['popupxd_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['popupxd_import_nonce'] ) ), 'popupxd_import' ) ) {
			wp_die( 'Yêu cầu không hợp lệ.' );
		}
		if ( ! current_user_can( $this->create_cap() ) ) {
			wp_die( 'Bạn không có quyền nhập popup.' );
		}

		$redirect_base = admin_url( 'edit.php?post_type=' . POPUPXD_CPT );
		$file          = isset( $_FILES['popupxd_import_file'] ) ? $_FILES['popupxd_import_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! $file || empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'popupxd_import', 'empty', $redirect_base ) );
			exit;
		}
		if ( (int) $file['size'] > 2 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'popupxd_import', 'invalid', $redirect_base ) );
			exit;
		}

		$json = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$data = json_decode( (string) $json, true );

		if ( ! is_array( $data ) || empty( $data['meta'] ) || ! is_array( $data['meta'] ) || 'popup-xd' !== ( isset( $data['plugin'] ) ? $data['plugin'] : '' ) ) {
			wp_safe_redirect( add_query_arg( 'popupxd_import', 'invalid', $redirect_base ) );
			exit;
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => POPUPXD_CPT,
				'post_status'  => 'draft',
				'post_title'   => ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) . ' (đã nhập)' : 'Popup đã nhập',
				'post_content' => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_safe_redirect( add_query_arg( 'popupxd_import', 'error', $redirect_base ) );
			exit;
		}

		$dropped = 0;
		foreach ( $data['meta'] as $key => $val ) {
			$key = sanitize_key( $key );
			// Only fields Popup XD knows about, cleaned with the same rules as the metabox.
			if ( ! PopupXD_Helpers::is_known( $key ) ) {
				continue;
			}
			// Old exports stored arrays serialized — decode them safely (no objects).
			if ( is_string( $val ) && is_serialized( $val ) ) {
				$decoded = @unserialize( $val, array( 'allowed_classes' => false ) ); // phpcs:ignore
				$val     = is_array( $decoded ) ? $decoded : '';
			}
			$val = PopupXD_Helpers::sanitize( $key, $val );

			// IDs from another site usually don't exist here — drop them instead
			// of silently pointing at the wrong template / page.
			if ( 'elementor_template_id' === $key && '' !== $val ) {
				$tpl = get_post( (int) $val );
				if ( ! $tpl || 'elementor_library' !== $tpl->post_type ) {
					$val = '';
					$dropped++;
				}
			}
			if ( 'target_pages' === $key && is_array( $val ) ) {
				$kept = array();
				foreach ( $val as $page_id ) {
					$page = get_post( (int) $page_id );
					if ( $page && 'page' === $page->post_type ) {
						$kept[] = (string) (int) $page_id;
					} else {
						$dropped++;
					}
				}
				$val = $kept;
			}
			update_post_meta( $new_id, POPUPXD_META_PREFIX . $key, $val );
		}

		$args = array( 'popupxd_import' => 'success' );
		if ( $dropped ) {
			$args['popupxd_dropped'] = $dropped;
		}
		wp_safe_redirect( add_query_arg( $args, $redirect_base ) );
		exit;
	}

	/**
	 * Import form + result notice, shown only on the Popup XD list screen.
	 */
	public function import_export_ui() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . POPUPXD_CPT !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( $this->create_cap() ) ) {
			return;
		}

		if ( isset( $_GET['popupxd_import'] ) ) {
			$messages = array(
				'success' => array( 'notice-success', 'Nhập popup thành công! Popup mới đang ở trạng thái Nháp — kiểm tra lại cấu hình rồi Đăng nhé.' ),
				'invalid' => array( 'notice-error', 'File không hợp lệ hoặc không phải file xuất từ Popup XD.' ),
				'empty'   => array( 'notice-error', 'Bạn chưa chọn file để nhập.' ),
				'error'   => array( 'notice-error', 'Có lỗi khi tạo popup mới, vui lòng thử lại.' ),
			);
			$key = sanitize_key( wp_unslash( $_GET['popupxd_import'] ) );
			if ( isset( $messages[ $key ] ) ) {
				printf(
					'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( $messages[ $key ][0] ),
					esc_html( $messages[ $key ][1] )
				);
			}
			$dropped = isset( $_GET['popupxd_dropped'] ) ? absint( $_GET['popupxd_dropped'] ) : 0;
			if ( 'success' === $key && $dropped ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html( sprintf( 'Đã bỏ %d liên kết không tồn tại trên site này (mẫu Elementor hoặc trang đích). Vào popup vừa nhập để chọn lại ở tab "Nội dung" / "Nơi hiển thị".', $dropped ) )
				);
			}
		}
		?>
		<div class="notice notice-info" style="padding:12px;">
			<p style="margin-top:0;"><strong>Nhập Popup từ file JSON</strong> — dùng file được tải về từ nút "Xuất JSON" ở mỗi popup (kể cả từ site khác).</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:0;">
				<input type="hidden" name="action" value="popupxd_import" />
				<?php wp_nonce_field( 'popupxd_import', 'popupxd_import_nonce' ); ?>
				<input type="file" name="popupxd_import_file" accept="application/json,.json" required />
				<button type="submit" class="button">Nhập Popup</button>
			</form>
		</div>
		<?php
	}
}
