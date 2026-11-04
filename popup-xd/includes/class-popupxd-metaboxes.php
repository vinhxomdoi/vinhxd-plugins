<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_Metaboxes {

	const NONCE_ACTION = 'popupxd_save_meta';
	const NONCE_NAME   = 'popupxd_meta_nonce';

	public function __construct() {
		add_action( 'edit_form_after_title', array( $this, 'render_box' ) );
		add_action( 'save_post_' . POPUPXD_CPT, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets( $hook ) {
		global $post_type;
		if ( POPUPXD_CPT !== $post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'popupxd-admin', POPUPXD_URL . 'assets/admin/admin.css', array(), PopupXD_Helpers::asset_version( 'assets/admin/admin.css' ) );
		wp_enqueue_script( 'popupxd-admin', POPUPXD_URL . 'assets/admin/admin.js', array( 'jquery', 'wp-color-picker' ), PopupXD_Helpers::asset_version( 'assets/admin/admin.js' ), true );

		$post_id = get_the_ID();
		wp_localize_script( 'popupxd-admin', 'PopupXDAdmin', array(
			'popupId'      => (int) $post_id,
			'previewUrl'   => PopupXD_Preview::preview_url(),
			'previewNonce' => wp_create_nonce( PopupXD_Preview::NONCE_ACTION . '_' . (int) $post_id ),
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'createAction' => PopupXD_Preview::AJAX_CREATE,
			'createNonce'  => wp_create_nonce( PopupXD_Preview::AJAX_CREATE ),
			'editUrl'      => admin_url( 'post.php?post=__ID__&action=elementor' ),
			'elementor'    => PopupXD_Elementor::is_active(),
			'canCreate'    => PopupXD_Elementor::is_active() && PopupXD_Preview::can_create_templates(),
		) );
		wp_enqueue_style( 'wp-color-picker' );
	}

	public function render_box( $post ) {
		if ( POPUPXD_CPT !== $post->post_type ) {
			return;
		}
		$enabled = 'no' !== PopupXD_Helpers::meta( $post->ID, 'enabled', 'yes' );
		?>
		<div class="postbox popupxd-postbox">
			<div class="pxd-boxhead">
				<h2 class="pxd-boxtitle">Cấu hình Popup XD</h2>
				<label class="pxd-switch" title="Tắt để tạm ngừng popup mà không cần chuyển về Nháp">
					<input type="checkbox" name="pxd_enabled" value="yes" <?php checked( $enabled ); ?> />
					<span class="pxd-switch-ui" aria-hidden="true"></span>
					<span class="pxd-switch-label" data-on="Đang bật" data-off="Đang tắt"><?php echo $enabled ? 'Đang bật' : 'Đang tắt'; ?></span>
				</label>
			</div>
			<p class="pxd-summary" aria-live="polite"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <span class="pxd-summary-text"><?php echo esc_html( PopupXD_Helpers::summary( $post->ID ) ); ?></span></p>
			<div class="inside">
				<?php $this->render( $post ); ?>
			</div>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------
	 * Small field helpers (keep the markup below readable)
	 * ------------------------------------------------------------------ */

	private function m( $post_id, $key, $default = '' ) {
		return PopupXD_Helpers::meta( $post_id, $key, $default );
	}

	private function num( $name, $value, $args = array() ) {
		$args = wp_parse_args( $args, array( 'min' => '', 'max' => '', 'unit' => '', 'width' => 76, 'placeholder' => '' ) );
		printf(
			'<span class="pxd-num"><input type="number" name="pxd_%1$s" value="%2$s"%3$s%4$s style="width:%5$dpx" placeholder="%6$s" />%7$s</span>',
			esc_attr( $name ),
			esc_attr( $value ),
			'' !== $args['min'] ? ' min="' . esc_attr( $args['min'] ) . '"' : '',
			'' !== $args['max'] ? ' max="' . esc_attr( $args['max'] ) . '"' : '',
			(int) $args['width'],
			esc_attr( $args['placeholder'] ),
			$args['unit'] ? '<span class="pxd-unit">' . esc_html( $args['unit'] ) . '</span>' : ''
		);
	}

	private function color( $name, $value ) {
		printf( '<input type="text" class="pxd-color-field" name="pxd_%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
	}

	private function select( $name, $value, $options, $attrs = '' ) {
		printf( '<select name="pxd_%s" %s>', esc_attr( $name ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput -- $attrs is a literal.
		foreach ( $options as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( (string) $value, (string) $val, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private function text( $name, $value, $args = array() ) {
		$args = wp_parse_args( $args, array( 'width' => 160, 'placeholder' => '', 'validate' => '', 'default' => '' ) );
		printf(
			'<input type="text" name="pxd_%1$s" value="%2$s" style="width:%3$dpx" placeholder="%4$s"%5$s%6$s />',
			esc_attr( $name ),
			esc_attr( $value ),
			(int) $args['width'],
			esc_attr( $args['placeholder'] ),
			$args['validate'] ? ' data-pxd-validate="' . esc_attr( $args['validate'] ) . '"' : '',
			'' !== $args['default'] ? ' data-pxd-default="' . esc_attr( $args['default'] ) . '"' : ''
		);
	}

	private function check( $name, $value, $label, $checked_value = 'yes' ) {
		printf(
			'<label class="pxd-check"><input type="checkbox" name="pxd_%1$s" value="yes" %2$s /> %3$s</label>',
			esc_attr( $name ),
			checked( $value, $checked_value, false ),
			$label // phpcs:ignore WordPress.Security.EscapeOutput -- trusted literal markup.
		);
	}

	private function radios( $name, $value, $options ) {
		echo '<span class="pxd-radios">';
		foreach ( $options as $val => $label ) {
			printf(
				'<label><input type="radio" name="pxd_%1$s" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $val ),
				checked( (string) $value, (string) $val, false ),
				$label // phpcs:ignore WordPress.Security.EscapeOutput -- trusted literal markup.
			);
		}
		echo '</span>';
	}

	private function copy_field( $value, $width = 360 ) {
		printf(
			'<span class="pxd-copy"><input type="text" readonly value="%1$s" style="width:%2$dpx" onclick="this.select();" /><button type="button" class="button pxd-copy-btn" data-copy="%1$s"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <span class="pxd-copy-label">Sao chép</span></button></span>',
			esc_attr( $value ),
			(int) $width
		);
	}

	/* --------------------------------------------------------------------
	 * Form
	 * ------------------------------------------------------------------ */

	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$id = $post->ID;
		$v  = function ( $key, $default = '' ) use ( $id ) {
			return PopupXD_Helpers::meta( $id, $key, $default );
		};

		// Close button placement: popups saved before 1.3 keep their 4 offsets ("custom").
		$close_position = $v( 'close_position', '' );
		if ( '' === $close_position ) {
			$close_position = metadata_exists( 'post', $id, POPUPXD_META_PREFIX . 'close_offset_top' ) ? 'custom' : 'top-right';
		}

		$target_pages   = array_map( 'strval', PopupXD_Helpers::get_array( $id, 'target_pages' ) );
		$target_types   = PopupXD_Helpers::get_array( $id, 'target_post_types' );
		$target_devices = PopupXD_Helpers::get_array( $id, 'target_devices' );
		if ( empty( $target_devices ) ) {
			$target_devices = array( 'desktop', 'tablet', 'mobile' );
		}

		$templates   = PopupXD_Elementor::get_saved_templates();
		$template_id = $v( 'elementor_template_id', '' );
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$pages       = get_pages( array( 'sort_column' => 'post_title' ) );
		$border_opts = array( 'none' => 'Không', 'solid' => 'Liền', 'dashed' => 'Đứt nét', 'dotted' => 'Chấm' );
		$btn_text    = $v( 'button_text', 'Xem chi tiết' );
		?>
		<div class="pxd-layout">
		<div class="pxd-settings popupxd-tabs-wrap">
			<h2 class="nav-tab-wrapper popupxd-tabs-nav">
				<a href="#pxd-tab-content" class="nav-tab nav-tab-active">1. Nội dung</a>
				<a href="#pxd-tab-design" class="nav-tab">2. Giao diện</a>
				<a href="#pxd-tab-trigger" class="nav-tab">3. Kích hoạt</a>
				<a href="#pxd-tab-target" class="nav-tab">4. Nơi hiển thị</a>
				<a href="#pxd-tab-button" class="nav-tab">5. Nút &amp; đo lường</a>
			</h2>

			<?php /* ============================ 1. NỘI DUNG ============================ */ ?>
			<div id="pxd-tab-content" class="popupxd-tab-panel" style="display:block;">
				<table class="form-table">
					<tr>
						<th>Nguồn nội dung</th>
						<td>
							<?php
							$this->radios( 'content_type', $v( 'content_type', 'editor' ), array(
								'elementor' => 'Mẫu Elementor đã lưu',
								'editor'    => 'Trình soạn thảo WordPress',
							) );
							?>
						</td>
					</tr>
					<tr class="pxd-row-elementor">
						<th>Chọn mẫu Elementor</th>
						<td>
							<select name="pxd_elementor_template_id" id="pxd-template-select" style="min-width:280px;max-width:100%;">
								<option value="">— Chọn mẫu đã lưu —</option>
								<?php foreach ( $templates as $tpl ) : ?>
									<option value="<?php echo esc_attr( $tpl->ID ); ?>" <?php selected( $template_id, $tpl->ID ); ?>><?php echo esc_html( $tpl->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( PopupXD_Elementor::is_active() ) : ?>
								<span class="pxd-template-actions">
									<a href="#" class="button" id="pxd-edit-template" target="_blank" rel="noopener" aria-disabled="true"><span class="dashicons dashicons-edit" aria-hidden="true"></span> Sửa mẫu bằng Elementor</a>
									<?php if ( PopupXD_Preview::can_create_templates() ) : ?>
										<button type="button" class="button" id="pxd-new-template"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Tạo mẫu mới</button>
									<?php endif; ?>
								</span>
								<p class="pxd-template-msg" role="status" aria-live="polite"></p>
								<p class="description">Hai nút mở trình soạn Elementor ở <strong>tab mới</strong>. Sửa xong bấm <em>Cập nhật</em> trong Elementor rồi quay lại tab này — khung xem trước tự làm mới. Mẫu tạo mới được chọn sẵn; nhớ bấm <em>Cập nhật</em> popup để lưu lựa chọn.</p>
							<?php else : ?>
								<p class="description">Chưa phát hiện Elementor — cài và kích hoạt Elementor để dùng mẫu đã lưu.</p>
							<?php endif; ?>
							<?php if ( empty( $templates ) && PopupXD_Elementor::is_active() ) : ?>
								<p class="description pxd-no-templates">Chưa có mẫu nào trong thư viện Elementor — bấm "Tạo mẫu mới" để bắt đầu.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="pxd-row-editor">
						<th>Nội dung</th>
						<td><p class="description">Soạn nội dung popup ở khung soạn thảo phía dưới (khung này tự ẩn khi bạn chọn "Mẫu Elementor").</p></td>
					</tr>
				</table>
			</div>

			<?php /* ============================ 2. GIAO DIỆN ============================ */ ?>
			<div id="pxd-tab-design" class="popupxd-tab-panel" style="display:none;">
				<h3 class="pxd-section">Khung popup</h3>
				<table class="form-table">
					<tr>
						<th>Độ rộng tối đa</th>
						<td><?php $this->num( 'max_width', $v( 'max_width', '600' ), array( 'min' => 200, 'max' => 1600, 'unit' => 'px', 'width' => 90 ) ); ?></td>
					</tr>
					<tr>
						<th>Khoảng đệm nội dung</th>
						<td>
							<?php $this->text( 'content_padding', $v( 'content_padding', '28px' ), array( 'placeholder' => '28px', 'validate' => 'box', 'default' => '28px' ) ); ?>
							<p class="description">1 giá trị (<code>28px</code>) hoặc theo từng phía như CSS (<code>20px 24px</code>). Đơn vị: px, em, rem, %.</p>
						</td>
					</tr>
					<tr>
						<th>Viền</th>
						<td class="pxd-inline">
							<?php $this->num( 'border_width', $v( 'border_width', '0' ), array( 'min' => 0, 'max' => 20, 'unit' => 'px' ) ); ?>
							<?php $this->select( 'border_style', $v( 'border_style', 'none' ), $border_opts ); ?>
							<?php $this->color( 'border_color', $v( 'border_color', '#dddddd' ) ); ?>
						</td>
					</tr>
					<tr>
						<th>Bo góc</th>
						<td><?php $this->num( 'border_radius', $v( 'border_radius', '8' ), array( 'min' => 0, 'max' => 60, 'unit' => 'px' ) ); ?></td>
					</tr>
					<tr>
						<th>Đổ bóng</th>
						<td><?php $this->select( 'shadow', $v( 'shadow', 'md' ), array( 'none' => 'Không', 'sm' => 'Nhẹ', 'md' => 'Vừa', 'lg' => 'Đậm' ) ); ?></td>
					</tr>
				</table>

				<h3 class="pxd-section">Nút đóng (X)</h3>
				<table class="form-table">
					<tr>
						<th>Hiển thị</th>
						<td><?php $this->check( 'show_close', $v( 'show_close', 'yes' ), 'Hiện nút đóng' ); ?></td>
					</tr>
					<tr class="pxd-dep-close">
						<th>Vị trí</th>
						<td class="pxd-inline">
							<?php
							$this->select( 'close_position', $close_position, array(
								'top-right'         => 'Góc trên phải',
								'top-left'          => 'Góc trên trái',
								'outside-top-right' => 'Phía trên khung, bên phải',
								'outside-top-left'  => 'Phía trên khung, bên trái',
								'custom'            => 'Tùy chỉnh (4 hướng)',
							) );
							?>
							<span class="pxd-close-gap">Cách mép <?php $this->num( 'close_offset', $v( 'close_offset', '10' ), array( 'min' => -50, 'max' => 100, 'unit' => 'px' ) ); ?></span>
							<p class="description pxd-outside-tip" hidden>Nút nằm trên nền mờ (ngoài khung) — nên chọn <strong>màu icon sáng</strong> (vd: trắng) ở dòng "Màu" bên dưới để dễ thấy.</p>
						</td>
					</tr>
					<tr class="pxd-dep-close pxd-close-custom">
						<th>Offset tùy chỉnh</th>
						<td class="pxd-inline">
							Trên <?php $this->num( 'close_offset_top', PopupXD_Helpers::get_saved( $id, 'close_offset_top', '8' ), array( 'unit' => 'px', 'width' => 64 ) ); ?>
							Phải <?php $this->num( 'close_offset_right', PopupXD_Helpers::get_saved( $id, 'close_offset_right', '12' ), array( 'unit' => 'px', 'width' => 64 ) ); ?>
							Dưới <?php $this->num( 'close_offset_bottom', PopupXD_Helpers::get_saved( $id, 'close_offset_bottom', '' ), array( 'unit' => 'px', 'width' => 64 ) ); ?>
							Trái <?php $this->num( 'close_offset_left', PopupXD_Helpers::get_saved( $id, 'close_offset_left', '' ), array( 'unit' => 'px', 'width' => 64 ) ); ?>
							<p class="description">Chỉ cần điền 2 hướng (vd: Trên + Phải), để trống các ô còn lại.</p>
						</td>
					</tr>
					<tr class="pxd-dep-close">
						<th>Màu</th>
						<td class="pxd-inline">
							<span class="pxd-lbl">Icon</span> <?php $this->color( 'close_color', $v( 'close_color', '#333333' ) ); ?>
							<span class="pxd-lbl">Nền</span> <?php $this->color( 'close_bg', $v( 'close_bg', 'transparent' ) ); ?>
						</td>
					</tr>
					<tr class="pxd-dep-close">
						<th>Kích thước &amp; viền</th>
						<td class="pxd-inline">
							<span class="pxd-lbl">Padding</span> <?php $this->text( 'close_padding', $v( 'close_padding', '4px' ), array( 'width' => 90, 'placeholder' => '4px', 'validate' => 'box', 'default' => '4px' ) ); ?>
							<span class="pxd-lbl">Bo góc</span> <?php $this->num( 'close_radius', $v( 'close_radius', '0' ), array( 'min' => 0, 'max' => 60, 'unit' => 'px' ) ); ?>
							<br>
							<span class="pxd-lbl">Viền</span> <?php $this->num( 'close_border_width', $v( 'close_border_width', '0' ), array( 'min' => 0, 'max' => 10, 'unit' => 'px', 'width' => 64 ) ); ?>
							<?php $this->select( 'close_border_style', $v( 'close_border_style', 'none' ), $border_opts ); ?>
							<?php $this->color( 'close_border_color', $v( 'close_border_color', '#dddddd' ) ); ?>
							<p class="description">Vùng bấm của nút luôn tối thiểu 44×44px để dễ chạm trên điện thoại, dù icon nhỏ.</p>
						</td>
					</tr>
				</table>

				<h3 class="pxd-section">Vị trí &amp; hiệu ứng</h3>
				<table class="form-table">
					<tr>
						<th>Vị trí trên màn hình</th>
						<td><?php $this->select( 'position', $v( 'position', 'center' ), array( 'center' => 'Giữa màn hình', 'top' => 'Trên', 'bottom' => 'Dưới', 'left' => 'Trái', 'right' => 'Phải' ) ); ?></td>
					</tr>
					<tr class="pxd-row-offset">
						<th>Cách mép màn hình</th>
						<td class="pxd-inline">
							<span class="pxd-off pxd-off-top">Trên <?php $this->num( 'offset_top', $v( 'offset_top', '0' ), array( 'unit' => 'px', 'width' => 64 ) ); ?></span>
							<span class="pxd-off pxd-off-right">Phải <?php $this->num( 'offset_right', $v( 'offset_right', '0' ), array( 'unit' => 'px', 'width' => 64 ) ); ?></span>
							<span class="pxd-off pxd-off-bottom">Dưới <?php $this->num( 'offset_bottom', $v( 'offset_bottom', '0' ), array( 'unit' => 'px', 'width' => 64 ) ); ?></span>
							<span class="pxd-off pxd-off-left">Trái <?php $this->num( 'offset_left', $v( 'offset_left', '0' ), array( 'unit' => 'px', 'width' => 64 ) ); ?></span>
						</td>
					</tr>
					<tr>
						<th>Hiệu ứng xuất hiện</th>
						<td>
							<?php
							$this->select( 'animation', $v( 'animation', 'fade' ), array(
								'fade'        => 'Hiện mờ dần (fade)',
								'zoom-in'     => 'Phóng to (zoom in)',
								'zoom-out'    => 'Thu nhỏ (zoom out)',
								'slide-up'    => 'Trượt lên',
								'slide-down'  => 'Trượt xuống',
								'slide-left'  => 'Trượt từ phải sang trái',
								'slide-right' => 'Trượt từ trái sang phải',
							) );
							?>
						</td>
					</tr>
					<tr>
						<th>Kiểu trên điện thoại</th>
						<td>
							<?php
							$this->radios( 'mobile_style', $v( 'mobile_style', 'default' ), array(
								'default' => 'Giống desktop',
								'sheet'   => 'Trượt lên từ đáy màn hình <em>(bottom sheet)</em>',
							) );
							?>
							<p class="description">Bottom sheet: rộng hết màn hình, nằm sát đáy — ngón cái dễ chạm, trông tự nhiên trên điện thoại. Chọn "Mobile" ở khung xem trước để thấy.</p>
						</td>
					</tr>
				</table>

				<h3 class="pxd-section">Nền mờ &amp; tương tác</h3>
				<table class="form-table">
					<tr>
						<th>Màu nền mờ (overlay)</th>
						<td class="pxd-inline">
							<?php $this->color( 'overlay_color', $v( 'overlay_color', '#000000' ) ); ?>
							<span class="pxd-lbl">Độ đậm</span> <?php $this->num( 'overlay_opacity', $v( 'overlay_opacity', '60' ), array( 'min' => 0, 'max' => 100, 'unit' => '%' ) ); ?>
						</td>
					</tr>
					<tr>
						<th>Khóa cuộn trang</th>
						<td><?php $this->radios( 'disable_scroll', $v( 'disable_scroll', 'yes' ), array( 'yes' => 'Có', 'no' => 'Không' ) ); ?></td>
					</tr>
					<tr>
						<th>Bấm ra ngoài để đóng</th>
						<td><?php $this->radios( 'close_outside', $v( 'close_outside', 'yes' ), array( 'yes' => 'Có', 'no' => 'Không' ) ); ?></td>
					</tr>
				</table>
			</div>

			<?php /* ============================ 3. KÍCH HOẠT ============================ */ ?>
			<div id="pxd-tab-trigger" class="popupxd-tab-panel" style="display:none;">
				<h3 class="pxd-section">Tự động mở khi <span class="pxd-section-note">— chọn một hoặc nhiều, điều kiện nào đến trước sẽ mở popup</span></h3>
				<table class="form-table pxd-triggers">
					<tr>
						<th><?php $this->check( 'trigger_time', $v( 'trigger_time', '' ), 'Sau một khoảng thời gian' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_trigger_time">Sau <?php $this->num( 'time_delay', $v( 'time_delay', '3' ), array( 'min' => 0, 'unit' => 'giây' ) ); ?> kể từ lúc vào trang</td>
					</tr>
					<tr class="pxd-warn-row" id="pxd-google-warn" hidden>
						<td colspan="2">
							<div class="pxd-warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <span><strong>Lưu ý SEO:</strong> popup che màn hình trên điện thoại ngay khi khách vừa vào trang (dưới 5 giây) có thể bị Google đánh giá là gây khó chịu và hạ thứ hạng trang trên mobile. Nên tăng lên ≥ 5 giây, dùng "Khi cuộn trang", chọn kiểu bottom sheet nhỏ gọn, hoặc bỏ chọn Mobile ở tab 4.</span></div>
						</td>
					</tr>
					<tr>
						<th><?php $this->check( 'trigger_scroll', $v( 'trigger_scroll', '' ), 'Khi cuộn trang' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_trigger_scroll">Cuộn đến <?php $this->num( 'scroll_percent', $v( 'scroll_percent', '50' ), array( 'min' => 1, 'max' => 100, 'unit' => '%' ) ); ?> chiều cao trang</td>
					</tr>
					<tr>
						<th><?php $this->check( 'trigger_exit', $v( 'trigger_exit', '' ), 'Khi định thoát trang' ); ?></th>
						<td class="description">Con trỏ chuột rời khỏi phía trên cửa sổ (chỉ máy có chuột).</td>
					</tr>
					<tr>
						<th><?php $this->check( 'trigger_click', $v( 'trigger_click', '' ), 'Khi click vào phần tử' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_trigger_click">
							<?php $this->text( 'click_selector', $v( 'click_selector', '' ), array( 'width' => 280, 'placeholder' => 'vd: baogiaxd hoặc .open-popup', 'validate' => 'selector' ) ); ?>
							<p class="description">Tên class trong ô "Class CSS" của Elementor (không cần dấu chấm, vd: <code>baogiaxd</code>) hoặc CSS selector (<code>.open-popup</code>, <code>#btn-lienhe</code>). Vẫn mở được kể cả khi khách đã đóng popup trước đó.</p>
						</td>
					</tr>
				</table>

				<h3 class="pxd-section">Mở thủ công</h3>
				<table class="form-table">
					<tr>
						<th><?php $this->check( 'trigger_manual', $v( 'trigger_manual', 'yes' ), 'Cho phép mở thủ công' ); ?></th>
						<td class="description">Bắt buộc bật để dùng shortcode, link <code>#popupxd-<?php echo (int) $id; ?></code> hoặc <code>PopupXD.open()</code> (tab 5). Khi tắt, nút shortcode bị ẩn với khách.</td>
					</tr>
				</table>

				<h3 class="pxd-section">Tần suất hiển thị</h3>
				<table class="form-table">
					<tr>
						<th><?php $this->check( 'cookie_enable', $v( 'cookie_enable', '' ), 'Giới hạn số lần hiện' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_cookie_enable">
							Mỗi khách chỉ tự hiện 1 lần trong <?php $this->num( 'cookie_days', $v( 'cookie_days', '7' ), array( 'min' => 0, 'unit' => 'ngày' ) ); ?>
							<span class="description">(0 = 1 lần cho mỗi lượt truy cập)</span>
							<div class="pxd-sub">
								Tính là đã xem khi:
								<?php
								$this->radios( 'count_mode', $v( 'count_mode', 'close' ), array(
									'close' => 'Khách bấm đóng popup',
									'show'  => 'Popup vừa hiện ra <em>(khách chuyển trang mà không đóng cũng tính)</em>',
								) );
								?>
							</div>
						</td>
					</tr>
				</table>

				<h3 class="pxd-section">Sau khi khách gửi form thành công</h3>
				<table class="form-table">
					<tr>
						<th><?php $this->check( 'form_close', $v( 'form_close', '' ), 'Tự đóng popup' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_form_close">Đóng sau <?php $this->num( 'form_close_delay', $v( 'form_close_delay', '3' ), array( 'min' => 0, 'max' => 60, 'unit' => 'giây' ) ); ?> <span class="description">(để khách kịp đọc lời cảm ơn)</span></td>
					</tr>
					<tr>
						<th><?php $this->check( 'form_hide_enable', $v( 'form_hide_enable', 'yes' ), 'Không tự hiện lại' ); ?></th>
						<td class="pxd-dep" data-dep="pxd_form_hide_enable">với khách đã gửi form trong <?php $this->num( 'form_hide_days', $v( 'form_hide_days', '30' ), array( 'min' => 0, 'unit' => 'ngày' ) ); ?> <span class="description">(nút/link thủ công vẫn mở được)</span></td>
					</tr>
					<tr>
						<td colspan="2" class="description pxd-forms-note">Hỗ trợ form đặt <strong>bên trong popup</strong>: Elementor Pro Forms, Fluent Forms, Contact Form 7.</td>
					</tr>
				</table>

				<details class="pxd-advanced">
					<summary>Nâng cao: tự động hiện lại sau khi đóng</summary>
					<p class="description pxd-warn-text">⚠️ Hành vi này thường bị xem là gây khó chịu (dark pattern). Chỉ nên bật để thử nghiệm, không khuyến khích cho popup thật.</p>
					<table class="form-table">
						<tr>
							<th><?php $this->check( 'reopen_enable', $v( 'reopen_enable', '' ), 'Tự động hiện lại' ); ?></th>
							<td class="pxd-dep" data-dep="pxd_reopen_enable">
								Sau <?php $this->num( 'reopen_seconds', $v( 'reopen_seconds', '30' ), array( 'min' => 1, 'unit' => 'giây', 'width' => 84 ) ); ?> kể từ lúc đóng
								<p class="description">Áp dụng trong lần xem trang hiện tại, bỏ qua giới hạn tần suất. Không áp dụng với khách đã gửi form.</p>
							</td>
						</tr>
					</table>
				</details>
			</div>

			<?php /* ============================ 4. NƠI HIỂN THỊ ============================ */ ?>
			<div id="pxd-tab-target" class="popupxd-tab-panel" style="display:none;">
				<table class="form-table">
					<tr>
						<th>Phạm vi</th>
						<td><?php $this->radios( 'display_scope', $v( 'display_scope', 'everywhere' ), array( 'everywhere' => 'Toàn bộ website', 'specific' => 'Chỉ định cụ thể' ) ); ?></td>
					</tr>
					<tr class="pxd-row-specific">
						<th>Theo trang</th>
						<td>
							<div class="pxd-pagepick">
								<div class="pxd-pagepick-tools">
									<input type="search" class="pxd-pagepick-search" placeholder="Tìm trang…" aria-label="Tìm trang" />
									<label class="pxd-pagepick-only"><input type="checkbox" class="pxd-pagepick-selected-only" /> Chỉ hiện trang đã chọn</label>
									<span class="pxd-pagepick-count"></span>
								</div>
								<div class="pxd-pagepick-list" role="group" aria-label="Danh sách trang">
									<?php foreach ( $pages as $page ) : ?>
										<label data-title="<?php echo esc_attr( strtolower( $page->post_title ) ); ?>">
											<input type="checkbox" name="pxd_target_pages[]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( in_array( (string) $page->ID, $target_pages, true ) ); ?> />
											<?php echo esc_html( '' !== $page->post_title ? $page->post_title : '(không tiêu đề)' ); ?>
											<span class="pxd-pid">#<?php echo (int) $page->ID; ?></span>
										</label>
									<?php endforeach; ?>
									<?php if ( empty( $pages ) ) : ?>
										<p class="description">Chưa có trang nào.</p>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
					<tr class="pxd-row-specific">
						<th>Theo loại nội dung</th>
						<td>
							<?php
							foreach ( $post_types as $pt ) :
								if ( POPUPXD_CPT === $pt->name || 'attachment' === $pt->name || 'elementor_library' === $pt->name ) {
									continue;
								}
								?>
								<label class="pxd-check pxd-inline-check">
									<input type="checkbox" name="pxd_target_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $target_types, true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">Hiện trên trang chi tiết của mọi bài thuộc loại được chọn.</p>
						</td>
					</tr>
					<tr>
						<th>Theo thiết bị</th>
						<td>
							<?php foreach ( array( 'desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile' ) as $val => $label ) : ?>
								<label class="pxd-check pxd-inline-check">
									<input type="checkbox" name="pxd_target_devices[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $target_devices, true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">Áp dụng cho các điều kiện tự động. Nút / link do khách tự bấm luôn mở. Mobile ≤ 767px · Tablet 768–1024px · Desktop ≥ 1025px.</p>
						</td>
					</tr>
					<tr>
						<th>Loại trừ</th>
						<td>
							<?php $this->text( 'exclude_ids', $v( 'exclude_ids', '' ), array( 'width' => 280, 'placeholder' => 'vd: 12, 45, 78', 'validate' => 'ids' ) ); ?>
							<p class="description">ID trang/bài viết, cách nhau bởi dấu phẩy — popup không tự hiện ở đó (nút thủ công vẫn mở được).</p>
						</td>
					</tr>
				</table>
			</div>

			<?php /* ============================ 5. NÚT & ĐO LƯỜNG ============================ */ ?>
			<div id="pxd-tab-button" class="popupxd-tab-panel" style="display:none;">
				<h3 class="pxd-section">Cách mở popup này</h3>
				<table class="form-table">
					<tr>
						<th>Shortcode nút</th>
						<td>
							<?php $this->copy_field( '[popupxd id="' . $id . '"]' . $btn_text . '[/popupxd]' ); ?>
							<p class="description">Dán vào bài viết/trang, widget hoặc widget Shortcode của Elementor — ở header, footer hay trang lưu trữ đều chạy.</p>
						</td>
					</tr>
					<tr>
						<th>Link</th>
						<td>
							<?php $this->copy_field( '#popupxd-' . $id, 200 ); ?>
							<p class="description">Dán vào ô <strong>Link</strong> của nút/ảnh/menu bất kỳ (vd: widget Button của Elementor) để mở popup, không cần shortcode.</p>
						</td>
					</tr>
					<tr>
						<th>JavaScript</th>
						<td><?php $this->copy_field( 'PopupXD.open(' . $id . ')', 200 ); ?></td>
					</tr>
				</table>

				<h3 class="pxd-section">Thiết kế nút shortcode</h3>
				<table class="form-table">
					<tr>
						<th>Chữ trên nút</th>
						<td><?php $this->text( 'button_text', $btn_text, array( 'width' => 280 ) ); ?></td>
					</tr>
					<tr>
						<th>Màu</th>
						<td class="pxd-inline">
							<span class="pxd-lbl">Nền</span> <?php $this->color( 'button_bg', $v( 'button_bg', '#2271b1' ) ); ?>
							<span class="pxd-lbl">Chữ</span> <?php $this->color( 'button_color', $v( 'button_color', '#ffffff' ) ); ?>
						</td>
					</tr>
					<tr>
						<th>Kích thước</th>
						<td class="pxd-inline">
							<span class="pxd-lbl">Cỡ chữ</span> <?php $this->num( 'button_font_size', $v( 'button_font_size', '15' ), array( 'min' => 10, 'max' => 40, 'unit' => 'px' ) ); ?>
							<span class="pxd-lbl">Padding</span> <?php $this->text( 'button_padding', $v( 'button_padding', '12px 24px' ), array( 'width' => 110, 'placeholder' => '12px 24px', 'validate' => 'box', 'default' => '12px 24px' ) ); ?>
							<span class="pxd-lbl">Bo góc</span> <?php $this->num( 'button_radius', $v( 'button_radius', '6' ), array( 'min' => 0, 'max' => 60, 'unit' => 'px' ) ); ?>
						</td>
					</tr>
					<tr>
						<th>CSS class thêm</th>
						<td>
							<?php $this->text( 'button_css_class', $v( 'button_css_class', '' ), array( 'width' => 280, 'placeholder' => 'vd: my-cta-btn' ) ); ?>
							<p class="description">Xem thêm hiệu ứng cho nút tại <a href="https://vinhxd.com/tai-nguyen/popup-xd" target="_blank" rel="noopener">trang Popup XD</a>.</p>
						</td>
					</tr>
				</table>

				<h3 class="pxd-section">Đo lường (Google Tag Manager / GA4)</h3>
				<p class="description">Popup tự đẩy sự kiện vào <code>dataLayer</code>. Trong GTM: tạo trigger <em>Custom Event</em> với tên sự kiện bên dưới, rồi gắn vào tag GA4 Event hoặc Google Ads Conversion. Biến chung: <code>popupxd_id</code>, <code>popupxd_title</code>.</p>
				<table class="widefat striped pxd-events">
					<thead><tr><th>Sự kiện</th><th>Khi nào</th><th>Biến thêm</th></tr></thead>
					<tbody>
						<tr><td><code>popupxd_open</code></td><td>Popup hiện ra</td><td><code>popupxd_trigger</code>: time, scroll, exit_intent, click_selector, button, link, reopen, api</td></tr>
						<tr><td><code>popupxd_close</code></td><td>Popup được đóng</td><td><code>popupxd_close_method</code>: button, overlay, esc, form, api</td></tr>
						<tr><td><code>popupxd_click</code></td><td>Click link/nút bên trong popup</td><td><code>popupxd_click_text</code>, <code>popupxd_click_url</code></td></tr>
						<tr><td><code>popupxd_form_submit</code></td><td>Gửi form thành công trong popup — <strong>dùng làm chuyển đổi Ads</strong></td><td><code>popupxd_form</code>: elementor, fluentform, cf7</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="pxd-side">
			<div class="pxd-preview" id="pxd-preview">
				<div class="pxd-preview-head">
					<strong class="pxd-preview-title">Xem trước</strong>
					<span class="pxd-preview-devices" role="group" aria-label="Kích thước màn hình">
						<button type="button" class="button pxd-dev is-active" data-device="desktop" aria-pressed="true" title="Desktop 1280px"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="pxd-dev-label"> Desktop</span></button>
						<button type="button" class="button pxd-dev" data-device="tablet" aria-pressed="false" title="Tablet 820px"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><span class="pxd-dev-label"> Tablet</span></button>
						<button type="button" class="button pxd-dev" data-device="mobile" aria-pressed="false" title="Mobile 390px"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="pxd-dev-label"> Mobile</span></button>
					</span>
					<button type="button" class="button pxd-preview-reload" title="Tải lại xem trước"><span class="dashicons dashicons-update" aria-hidden="true"></span><span class="screen-reader-text">Tải lại xem trước</span></button>
				</div>
				<div class="pxd-preview-meta" aria-live="polite"></div>
				<div class="pxd-preview-stage">
					<div class="pxd-preview-viewport">
						<iframe name="pxd-preview-frame" id="pxd-preview-frame" title="Xem trước popup" src="about:blank"></iframe>
					</div>
					<div class="pxd-preview-loading"><span class="spinner is-active"></span></div>
				</div>
				<p class="description">Hiển thị đúng kích thước màn hình thật rồi thu nhỏ cho vừa. Thay đổi được cập nhật ngay, <strong>chưa cần bấm Lưu</strong>.</p>
			</div>
		</div>
		</div>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$values = PopupXD_Helpers::from_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		foreach ( $values as $field => $val ) {
			update_post_meta( $post_id, POPUPXD_META_PREFIX . $field, $val );
		}
	}
}
