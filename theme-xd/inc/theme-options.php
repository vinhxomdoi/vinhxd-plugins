<?php
/**
 * Theme XD - Trang Cài đặt Admin
 * Menu: Cài đặt Theme XD
 *
 * @package ThemeXD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   ĐĂNG KÝ MENU ADMIN
============================================================ */
add_action( 'admin_menu', 'txd_admin_menu' );
function txd_admin_menu() {
    add_menu_page(
        __( 'Cài đặt Theme XD', 'theme-xd' ),
        __( 'Theme XD', 'theme-xd' ),
        'manage_options',
        'theme-xd-settings',
        'txd_settings_page',
        'dashicons-layout',
        60
    );

    add_submenu_page(
        'theme-xd-settings',
        __( 'Cài đặt chung', 'theme-xd' ),
        __( 'Cài đặt chung', 'theme-xd' ),
        'manage_options',
        'theme-xd-settings',
        'txd_settings_page'
    );

    add_submenu_page(
        'theme-xd-settings',
        __( 'Elementor Templates', 'theme-xd' ),
        __( 'Elementor Templates', 'theme-xd' ),
        'manage_options',
        'theme-xd-templates',
        'txd_templates_page'
    );
}

/* ============================================================
   ĐĂNG KÝ OPTIONS
============================================================ */
add_action( 'admin_init', 'txd_register_settings' );
function txd_register_settings() {

    // === SECTION: Elementor Templates ===
    register_setting( 'txd_templates_group', 'txd_template_header',  [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'txd_templates_group', 'txd_template_footer',  [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'txd_templates_group', 'txd_template_sidebar', [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'txd_templates_group', 'txd_template_404',     [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'txd_templates_group', 'txd_template_search',  [ 'sanitize_callback' => 'absint' ] );

    add_settings_section( 'txd_templates_section', '', '__return_false', 'txd_templates_group' );

    // === SECTION: Cài đặt chung ===
    register_setting( 'txd_general_group', 'txd_show_related',    [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_related_count',   [ 'sanitize_callback' => 'absint', 'default' => 3 ] );
    register_setting( 'txd_general_group', 'txd_show_author_box', [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_show_share',      [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_show_breadcrumb', [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_show_post_nav',   [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_reading_time',    [ 'sanitize_callback' => 'absint', 'default' => 1 ] );
    register_setting( 'txd_general_group', 'txd_custom_css',        [ 'sanitize_callback' => 'wp_strip_all_tags' ] );
    register_setting( 'txd_general_group', 'txd_custom_css_mobile', [ 'sanitize_callback' => 'wp_strip_all_tags' ] );
    register_setting( 'txd_general_group', 'txd_custom_js_head',    [ 'sanitize_callback' => 'txd_sanitize_code' ] );
    register_setting( 'txd_general_group', 'txd_custom_js_foot',    [ 'sanitize_callback' => 'txd_sanitize_code' ] );
}

/**
 * Mã nhúng: admin có quyền unfiltered_html được lưu nguyên văn.
 * (wp_kses_post cũ xoá thẻ <script> và đổi && thành &amp;&amp; → làm hỏng JS / mã Google Analytics, Pixel...)
 */
function txd_sanitize_code( $value ) {
    $value = (string) $value;
    return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
}

/* ============================================================
   ENQUEUE ADMIN ASSETS
============================================================ */
add_action( 'admin_enqueue_scripts', 'txd_admin_enqueue' );
function txd_admin_enqueue( $hook ) {
    if ( strpos( $hook, 'theme-xd' ) === false ) return;

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );

    wp_add_inline_style( 'wp-admin', txd_admin_css() );
}

function txd_admin_css() {
    return '
    .txd-admin-wrap { max-width: 960px; }
    .txd-admin-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; padding:20px 24px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.05); }
    .txd-admin-header .logo { width:40px; height:40px; background:#0a0a0a; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .txd-admin-header .logo span { color:#fff; font-weight:900; font-size:14px; }
    .txd-admin-header h1 { margin:0; font-size:20px; font-weight:700; color:#1a1a2e; }
    .txd-admin-header .version { background:#f0f4ff; color:#4f46e5; font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; }
    .txd-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin-bottom:24px; }
    .txd-tab-btn { padding:10px 20px; background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer; font-size:14px; font-weight:600; color:#64748b; transition:all .2s; }
    .txd-tab-btn.active, .txd-tab-btn:hover { color:#0a0a0a; border-bottom-color:#0a0a0a; }
    .txd-tab-content { display:none; }
    .txd-tab-content.active { display:block; }
    .txd-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .txd-card h2 { margin:0 0 4px; font-size:15px; font-weight:700; color:#1a1a2e; }
    .txd-card .desc { color:#64748b; font-size:13px; margin-bottom:20px; }
    .txd-field-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
    .txd-field { }
    .txd-field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
    .txd-field select, .txd-field input[type="number"] { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; background:#fff; color:#1a1a2e; }
    .txd-field select:focus, .txd-field input:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
    .txd-template-preview { font-size:12px; color:#6366f1; margin-top:4px; }
    .txd-toggle-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f1f5f9; }
    .txd-toggle-row:last-child { border:none; }
    .txd-toggle-row label { font-size:14px; color:#374151; cursor:pointer; }
    .txd-toggle-row .hint { font-size:12px; color:#94a3b8; display:block; margin-top:2px; }
    .txd-toggle { position:relative; display:inline-flex; align-items:center; cursor:pointer; }
    .txd-toggle input { opacity:0; width:0; height:0; }
    .txd-toggle-slider { width:44px; height:24px; background:#d1d5db; border-radius:12px; transition:.2s; position:relative; }
    .txd-toggle-slider::after { content:""; position:absolute; left:2px; top:2px; width:20px; height:20px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .txd-toggle input:checked + .txd-toggle-slider { background:#4f46e5; }
    .txd-toggle input:checked + .txd-toggle-slider::after { transform:translateX(20px); }
    .txd-save-btn { background:#1a1a2e; color:#fff; padding:10px 28px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s; }
    .txd-save-btn:hover { background:#2d2d5e; }
    .txd-save-btn:active { transform:scale(.98); }
    .txd-notice-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:14px; }
    .txd-code-area { width:100%; font-family:monospace; font-size:13px; border:1px solid #d1d5db; border-radius:6px; padding:12px; height:300px; max-height:300px; background:#f8fafc; resize:vertical; overflow-y:auto; box-sizing:border-box; }
    .txd-info-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px 16px; font-size:13px; color:#1e40af; margin-bottom:16px; }
    .txd-info-box strong { font-weight:700; }
    ';
}

/* ============================================================
   TRANG CHÍNH: CÀI ĐẶT THEME XD
============================================================ */
function txd_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['txd_save_general'] ) && check_admin_referer( 'txd_save_general' ) ) {
        update_option( 'txd_show_related',    isset( $_POST['txd_show_related'] ) ? 1 : 0 );
        update_option( 'txd_related_count',   max( 1, min( 12, absint( $_POST['txd_related_count'] ?? 3 ) ) ) );
        update_option( 'txd_show_author_box', isset( $_POST['txd_show_author_box'] ) ? 1 : 0 );
        update_option( 'txd_show_share',      isset( $_POST['txd_show_share'] ) ? 1 : 0 );
        update_option( 'txd_show_breadcrumb', isset( $_POST['txd_show_breadcrumb'] ) ? 1 : 0 );
        update_option( 'txd_show_post_nav',   isset( $_POST['txd_show_post_nav'] ) ? 1 : 0 );
        update_option( 'txd_reading_time',    isset( $_POST['txd_reading_time'] ) ? 1 : 0 );
        update_option( 'txd_custom_css',        wp_strip_all_tags( wp_unslash( $_POST['txd_custom_css'] ?? '' ) ) );
        update_option( 'txd_custom_css_mobile', wp_strip_all_tags( wp_unslash( $_POST['txd_custom_css_mobile'] ?? '' ) ) );
        // sanitize qua txd_sanitize_code() (đăng ký ở register_setting → tự chạy trong update_option)
        update_option( 'txd_custom_js_head',    wp_unslash( $_POST['txd_custom_js_head'] ?? '' ) );
        update_option( 'txd_custom_js_foot',    wp_unslash( $_POST['txd_custom_js_foot'] ?? '' ) );
        echo '<div class="txd-notice-success">✅ ' . __( 'Đã lưu cài đặt thành công!', 'theme-xd' ) . '</div>';
    }
    ?>
    <div class="wrap txd-admin-wrap">
        <?php txd_admin_header(); ?>

        <form method="post">
            <?php wp_nonce_field( 'txd_save_general' ); ?>

            <!-- Tính năng bài viết -->
            <div class="txd-card">
                <h2><?php _e( 'Tính năng trang bài viết (Single Post)', 'theme-xd' ); ?></h2>
                <p class="desc"><?php _e( 'Bật/tắt các thành phần hiển thị trong trang bài viết.', 'theme-xd' ); ?></p>

                <?php
                $toggles = [
                    'txd_show_breadcrumb' => [ __( 'Breadcrumb', 'theme-xd' ),                  __( 'Hiển thị đường dẫn điều hướng (hỗ trợ Yoast, RankMath)', 'theme-xd' ) ],
                    'txd_show_share'      => [ __( 'Nút chia sẻ mạng xã hội', 'theme-xd' ),    __( 'Facebook, X, Sao chép link', 'theme-xd' ) ],
                    'txd_show_author_box' => [ __( 'Khung thông tin tác giả', 'theme-xd' ),     __( 'Hiển thị avatar, tên và mô tả tác giả cuối bài', 'theme-xd' ) ],
                    'txd_show_related'    => [ __( 'Bài viết liên quan', 'theme-xd' ),           __( 'Hiển thị grid bài viết liên quan cuối trang', 'theme-xd' ) ],
                    'txd_show_post_nav'   => [ __( 'Điều hướng bài kế/trước', 'theme-xd' ),     __( 'Hiển thị nút bài trước / bài tiếp theo', 'theme-xd' ) ],
                    'txd_reading_time'    => [ __( 'Thời gian đọc ước tính', 'theme-xd' ),       __( 'Ví dụ: "3 phút đọc" trong meta bài viết', 'theme-xd' ) ],
                ];
                foreach ( $toggles as $key => [$label, $hint] ) :
                    $val = get_option( $key, 1 );
                ?>
                <div class="txd-toggle-row">
                    <div>
                        <label for="<?php echo $key; ?>"><?php echo $label; ?></label>
                        <span class="hint"><?php echo $hint; ?></span>
                    </div>
                    <label class="txd-toggle">
                        <input type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="1" <?php checked( 1, $val ); ?>>
                        <span class="txd-toggle-slider"></span>
                    </label>
                </div>
                <?php endforeach; ?>

                <div class="txd-toggle-row">
                    <div>
                        <label><?php _e( 'Số bài viết liên quan', 'theme-xd' ); ?></label>
                        <span class="hint"><?php _e( 'Số lượng bài hiển thị trong khung "Bài viết liên quan"', 'theme-xd' ); ?></span>
                    </div>
                    <input type="number" name="txd_related_count" value="<?php echo absint( get_option( 'txd_related_count', 3 ) ); ?>" min="1" max="12" style="width:70px;">
                </div>
            </div>

            <!-- CSS Tuỳ Chỉnh -->
            <div class="txd-card">
                <h2><?php _e( 'CSS Tuỳ Chỉnh', 'theme-xd' ); ?></h2>
                <p class="desc"><?php _e( 'Thêm CSS tuỳ chỉnh. Không cần thẻ &lt;style&gt;.', 'theme-xd' ); ?></p>

                <div class="txd-field" style="margin-bottom:16px;">
                    <label><?php _e( 'CSS Chung', 'theme-xd' ); ?></label>
                    <textarea name="txd_custom_css" class="txd-code-area" placeholder="/* CSS áp dụng cho toàn site */"><?php echo esc_textarea( get_option( 'txd_custom_css', '' ) ); ?></textarea>
                </div>
                <div class="txd-field">
                    <label><?php _e( 'CSS Riêng Cho Mobile', 'theme-xd' ); ?></label>
                    <span class="hint" style="display:block;margin-bottom:6px;"><?php _e( 'Tự động bọc trong @media (max-width: 768px), không cần viết lại media query.', 'theme-xd' ); ?></span>
                    <textarea name="txd_custom_css_mobile" class="txd-code-area" placeholder="/* CSS chỉ áp dụng cho màn hình ≤ 768px */"><?php echo esc_textarea( get_option( 'txd_custom_css_mobile', '' ) ); ?></textarea>
                </div>
            </div>

            <button type="submit" name="txd_save_general" class="txd-save-btn">
                <?php _e( '💾 Lưu cài đặt', 'theme-xd' ); ?>
            </button>

            <!-- Chèn Mã Nhúng -->
            <div class="txd-card">
                <h2><?php _e( 'Chèn Mã Nhúng', 'theme-xd' ); ?></h2>
                <p class="desc"><?php _e( 'Dán nguyên đoạn mã (Google Analytics, Pixel... kèm thẻ &lt;script&gt;) hoặc chỉ JS thuần — theme tự bọc &lt;script&gt; khi cần.', 'theme-xd' ); ?></p>

                <div class="txd-field" style="margin-bottom:16px;">
                    <label><?php _e( 'Mã Nhúng trong &lt;head&gt;', 'theme-xd' ); ?></label>
                    <textarea name="txd_custom_js_head" class="txd-code-area" placeholder="// Script trong <head>"><?php echo esc_textarea( get_option( 'txd_custom_js_head', '' ) ); ?></textarea>
                </div>
                <div class="txd-field">
                    <label><?php _e( 'Mã Nhúng trước &lt;/body&gt;', 'theme-xd' ); ?></label>
                    <textarea name="txd_custom_js_foot" class="txd-code-area" placeholder="// Script trước </body>"><?php echo esc_textarea( get_option( 'txd_custom_js_foot', '' ) ); ?></textarea>
                </div>
            </div>

            <button type="submit" name="txd_save_general" class="txd-save-btn">
                <?php _e( '💾 Lưu cài đặt', 'theme-xd' ); ?>
            </button>
        </form>
    </div>
    <?php
}

/* ============================================================
   TRANG ELEMENTOR TEMPLATES
============================================================ */
function txd_templates_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['txd_save_templates'] ) && check_admin_referer( 'txd_save_templates' ) ) {
        update_option( 'txd_template_header',  absint( $_POST['txd_template_header']  ?? 0 ) );
        update_option( 'txd_template_footer',  absint( $_POST['txd_template_footer']  ?? 0 ) );
        update_option( 'txd_template_sidebar', absint( $_POST['txd_template_sidebar'] ?? 0 ) );
        update_option( 'txd_template_404',     absint( $_POST['txd_template_404']     ?? 0 ) );
        update_option( 'txd_template_search',  absint( $_POST['txd_template_search']  ?? 0 ) );
        echo '<div class="txd-notice-success">✅ ' . __( 'Đã lưu templates thành công!', 'theme-xd' ) . '</div>';
    }

    // Lấy danh sách Elementor Templates
    $templates = txd_get_elementor_templates();
    ?>
    <div class="wrap txd-admin-wrap">
        <?php txd_admin_header(); ?>

        <div class="txd-info-box">
            <strong><?php _e( 'Lưu ý:', 'theme-xd' ); ?></strong>
            <?php _e( 'Nếu bạn đang dùng <strong>JetThemeCore (Crocoblock)</strong>, header/footer/widget sẽ được quản lý trực tiếp trong <em>Templates → Theme Parts</em> của JetThemeCore. Trang này dành cho khi bạn dùng Elementor Template thủ công.', 'theme-xd' ); ?>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'txd_save_templates' ); ?>

            <div class="txd-card">
                <h2><?php _e( 'Gán Elementor Template cho Header / Footer / Sidebar', 'theme-xd' ); ?></h2>
                <p class="desc"><?php _e( 'Chọn template đã tạo trong Elementor → Templates. Để trống nếu dùng JetThemeCore.', 'theme-xd' ); ?></p>

                <?php
                $template_fields = [
                    'txd_template_header'  => [ __( 'Header Template', 'theme-xd' ),  __( 'Template dùng làm header toàn site', 'theme-xd' ) ],
                    'txd_template_footer'  => [ __( 'Footer Template', 'theme-xd' ),  __( 'Template dùng làm footer toàn site', 'theme-xd' ) ],
                    'txd_template_sidebar' => [ __( 'Sidebar Template', 'theme-xd' ), __( 'Template dùng làm sidebar (ghi đè WordPress Widgets)', 'theme-xd' ) ],
                    'txd_template_404'     => [ __( 'Trang 404 Template', 'theme-xd' ), __( 'Template cho trang không tìm thấy', 'theme-xd' ) ],
                    'txd_template_search'  => [ __( 'Search Template', 'theme-xd' ),  __( 'Template cho trang kết quả tìm kiếm', 'theme-xd' ) ],
                ];
                foreach ( $template_fields as $key => [$label, $hint] ) :
                    $val = get_option( $key, 0 );
                ?>
                <div class="txd-toggle-row">
                    <div>
                        <label for="<?php echo $key; ?>"><?php echo $label; ?></label>
                        <span class="hint"><?php echo $hint; ?></span>
                        <?php if ( $val ) : ?>
                            <span class="txd-template-preview">
                                → <a href="<?php echo get_edit_post_link( $val ); ?>" target="_blank">
                                    <?php echo get_the_title( $val ); ?> (ID: <?php echo $val; ?>)
                                </a>
                            </span>
                        <?php endif; ?>
                    </div>
                    <select name="<?php echo $key; ?>" id="<?php echo $key; ?>" style="min-width:220px;">
                        <option value="0"><?php _e( '— Không dùng —', 'theme-xd' ); ?></option>
                        <?php foreach ( $templates as $tpl ) : ?>
                            <option value="<?php echo $tpl->ID; ?>" <?php selected( $val, $tpl->ID ); ?>>
                                <?php echo esc_html( $tpl->post_title ); ?> (<?php echo $tpl->ID; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <?php if ( empty( $templates ) ) : ?>
                    <p style="color:#94a3b8; font-size:13px; margin-top:8px;">
                        <?php _e( 'Chưa có Elementor Template nào. Vào <a href="' . admin_url( 'edit.php?post_type=elementor_library' ) . '">Elementor → Templates</a> để tạo.', 'theme-xd' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <button type="submit" name="txd_save_templates" class="txd-save-btn">
                <?php _e( '💾 Lưu Templates', 'theme-xd' ); ?>
            </button>
        </form>
    </div>
    <?php
}

/* ============================================================
   HELPER: LẤY DANH SÁCH ELEMENTOR TEMPLATES
============================================================ */
function txd_get_elementor_templates() {
    return get_posts([
        'post_type'      => 'elementor_library',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
}

/* ============================================================
   HELPER: ADMIN HEADER HTML
============================================================ */
function txd_admin_header() {
    $is_templates = strpos( $_GET['page'] ?? '', 'templates' ) !== false;
    ?>
    <div class="txd-admin-header">
        <div class="logo"><span>XD</span></div>
        <div>
            <h1><?php _e( 'Cài đặt Theme XD', 'theme-xd' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=theme-xd-settings' ); ?>" style="font-size:13px; color:#6366f1; margin-right:16px;">⚙️ <?php _e( 'Cài đặt chung', 'theme-xd' ); ?></a>
            <a href="<?php echo admin_url( 'admin.php?page=theme-xd-templates' ); ?>" style="font-size:13px; color:#6366f1;">🎨 <?php _e( 'Elementor Templates', 'theme-xd' ); ?></a>
        </div>
        <span class="version"><?php echo TXD_VERSION; ?></span>
    </div>
    <?php
}

// Output custom CSS/JS được xử lý trong functions.php
