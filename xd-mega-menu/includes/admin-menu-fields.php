<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Danh sách Elementor template dùng được cho mega menu.
 * Lưu tĩnh để màn hình Menu chỉ query 1 lần dù có bao nhiêu menu item.
 * Bỏ các loại template của Theme Builder / popup / kit.
 */
function xd_mm_get_template_options() {
	static $options = null;
	if ( null !== $options ) {
		return $options;
	}
	$options = [];

	if ( ! post_type_exists( 'elementor_library' ) ) {
		return $options;
	}

	$excluded = apply_filters( 'xd_mm_excluded_template_types', [
		'kit', 'header', 'footer', 'single', 'single-post', 'single-page', 'archive',
		'search-results', 'error-404', 'popup', 'product', 'product-archive', 'loop-item',
	] );

	$posts = get_posts( [
		'post_type'              => 'elementor_library',
		'post_status'            => 'publish',
		'posts_per_page'         => 300,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'meta_query'             => [
			'relation' => 'OR',
			[
				'key'     => '_elementor_template_type',
				'value'   => $excluded,
				'compare' => 'NOT IN',
			],
			[
				'key'     => '_elementor_template_type',
				'compare' => 'NOT EXISTS',
			],
		],
	] );

	foreach ( $posts as $tpl ) {
		$options[ $tpl->ID ] = $tpl->post_title;
	}

	return $options;
}

/**
 * Hiển thị các field tuỳ chỉnh cho từng menu item trong Giao diện > Menu.
 */
add_action( 'wp_nav_menu_item_custom_fields', function ( $item_id, $item, $depth, $args, $id ) {

	// Nonce in 1 lần cho cả form (không thêm field cho từng item → không làm tăng max_input_vars).
	static $nonce_printed = false;
	if ( ! $nonce_printed ) {
		wp_nonce_field( 'xd_mm_save_fields', 'xd_mm_nonce', false );
		$nonce_printed = true;
	}

	$is_mega     = get_post_meta( $item_id, '_xd_mega', true );
	$width       = get_post_meta( $item_id, '_xd_mega_width', true );
	$template_id = (int) get_post_meta( $item_id, '_xd_mega_template', true );
	$content     = get_post_meta( $item_id, '_xd_mega_content', true );
	$templates   = xd_mm_get_template_options();

	// Template đang chọn nhưng không còn trong danh sách (đổi loại, bị ẩn…) vẫn giữ để không mất khi lưu.
	if ( $template_id && ! isset( $templates[ $template_id ] ) ) {
		$templates[ $template_id ] = get_the_title( $template_id ) . ' (ngoài danh sách)';
	}
	?>
	<p class="field-xd-mega description description-wide">
		<label for="edit-menu-item-xd-mega-<?php echo esc_attr( $item_id ); ?>">
			<input
				type="checkbox"
				id="edit-menu-item-xd-mega-<?php echo esc_attr( $item_id ); ?>"
				name="menu-item-xd-mega[<?php echo esc_attr( $item_id ); ?>]"
				value="1"
				<?php checked( $is_mega, '1' ); ?>
			/>
			Đây là Mega Menu
		</label>
	</p>

	<p class="field-xd-mega-width description description-wide">
		<label for="edit-menu-item-xd-mega-width-<?php echo esc_attr( $item_id ); ?>">
			Chiều rộng mega menu<br />
			<input
				type="text"
				class="widefat"
				id="edit-menu-item-xd-mega-width-<?php echo esc_attr( $item_id ); ?>"
				name="menu-item-xd-mega-width[<?php echo esc_attr( $item_id ); ?>]"
				value="<?php echo esc_attr( $width ); ?>"
				placeholder="vd: 1200px, 80vw, full"
			/>
			<span class="description">"full" = tràn toàn màn hình. Dùng px, %, vw, rem hoặc em. Để trống = theo widget.</span>
		</label>
	</p>

	<?php if ( ! empty( $templates ) ) : ?>
	<p class="field-xd-mega-template description description-wide">
		<label for="edit-menu-item-xd-mega-template-<?php echo esc_attr( $item_id ); ?>">
			Chọn Elementor Template<br />
			<select
				class="widefat"
				id="edit-menu-item-xd-mega-template-<?php echo esc_attr( $item_id ); ?>"
				name="menu-item-xd-mega-template[<?php echo esc_attr( $item_id ); ?>]"
			>
				<option value="">— Không chọn (Dùng shortcode bên dưới) —</option>
				<?php foreach ( $templates as $tpl_id => $tpl_title ) : ?>
					<option value="<?php echo esc_attr( $tpl_id ); ?>" <?php selected( $template_id, $tpl_id ); ?>>
						<?php echo esc_html( $tpl_title ); ?> (ID: <?php echo esc_html( $tpl_id ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description">Template Elementor cho Mega Menu này (ưu tiên hơn shortcode bên dưới).</span>
		</label>
	</p>
	<?php endif; ?>

	<p class="field-xd-mega-content description description-wide">
		<label for="edit-menu-item-xd-mega-content-<?php echo esc_attr( $item_id ); ?>">
			Nội dung (shortcode / HTML tuỳ chỉnh)<br />
			<textarea
				rows="2"
				class="widefat"
				id="edit-menu-item-xd-mega-content-<?php echo esc_attr( $item_id ); ?>"
				name="menu-item-xd-mega-content[<?php echo esc_attr( $item_id ); ?>]"
				placeholder='vd: [elementor-template id="123"] hoặc [xd_xe_grid ...]'
			><?php echo esc_textarea( $content ); ?></textarea>
		</label>
	</p>
	<?php
}, 10, 5 );

/**
 * Lưu các field khi menu được cập nhật ở Giao diện > Menu.
 *
 * Hook này còn chạy ở Customizer, REST API, import… – những nơi đó không có
 * field của plugin. Vì vậy chỉ xử lý khi:
 *  - có nonce của plugin (form Giao diện > Menu), và
 *  - item này có field chiều rộng trong form (input text luôn được gửi). Nếu
 *    form bị cắt do max_input_vars thì item thiếu field sẽ được giữ nguyên.
 */
add_action( 'wp_update_nav_menu_item', function ( $menu_id, $menu_item_db_id, $args ) {

	if ( empty( $_POST['xd_mm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xd_mm_nonce'] ) ), 'xd_mm_save_fields' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['menu-item-xd-mega-width'][ $menu_item_db_id ] ) ) {
		return;
	}

	if ( isset( $_POST['menu-item-xd-mega'][ $menu_item_db_id ] ) ) {
		update_post_meta( $menu_item_db_id, '_xd_mega', '1' );
	} else {
		delete_post_meta( $menu_item_db_id, '_xd_mega' );
	}

	$width = xd_mm_sanitize_width( wp_unslash( $_POST['menu-item-xd-mega-width'][ $menu_item_db_id ] ) );
	if ( '' !== $width ) {
		update_post_meta( $menu_item_db_id, '_xd_mega_width', $width );
	} else {
		delete_post_meta( $menu_item_db_id, '_xd_mega_width' );
	}

	if ( isset( $_POST['menu-item-xd-mega-template'][ $menu_item_db_id ] ) ) {
		$tpl = absint( $_POST['menu-item-xd-mega-template'][ $menu_item_db_id ] );
		if ( $tpl ) {
			update_post_meta( $menu_item_db_id, '_xd_mega_template', $tpl );
		} else {
			delete_post_meta( $menu_item_db_id, '_xd_mega_template' );
		}
	}

	if ( isset( $_POST['menu-item-xd-mega-content'][ $menu_item_db_id ] ) ) {
		$content = wp_kses_post( wp_unslash( $_POST['menu-item-xd-mega-content'][ $menu_item_db_id ] ) );
		if ( '' !== trim( $content ) ) {
			update_post_meta( $menu_item_db_id, '_xd_mega_content', $content );
		} else {
			delete_post_meta( $menu_item_db_id, '_xd_mega_content' );
		}
	}
}, 10, 3 );
