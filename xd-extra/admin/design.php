<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Design – Màu sắc & Font. Biến CSS được in ở <head> (xem includes/frontend.php):
 * --xd-primary, --xd-secondary, --xd-text, --xd-accent, --xd-font
 */
function xd_design_page() {
    if ( isset( $_POST['xd_save_design'] ) ) {
        check_admin_referer( 'xd_design_save' );
        update_option( 'xd_color_primary',   sanitize_hex_color( $_POST['xd_color_primary']   ?? '#1d9e75' ) );
        update_option( 'xd_color_secondary', sanitize_hex_color( $_POST['xd_color_secondary'] ?? '#0f6e56' ) );
        update_option( 'xd_color_text',      sanitize_hex_color( $_POST['xd_color_text']      ?? '#454545' ) );
        update_option( 'xd_color_accent',    sanitize_hex_color( $_POST['xd_color_accent']    ?? '#f7c315' ) );
        update_option( 'xd_font_family',      preg_replace( '/[^\p{L}\p{N} \-]/u', '', sanitize_text_field( wp_unslash( $_POST['xd_font_family'] ?? '' ) ) ) );
        update_option( 'xd_font_apply_global', isset( $_POST['xd_font_apply_global'] ) ? '1' : '0' );
        echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt Design!</p></div>';
    }

    $primary   = get_option( 'xd_color_primary',   '#1d9e75' );
    $secondary = get_option( 'xd_color_secondary', '#0f6e56' );
    $text      = get_option( 'xd_color_text',      '#454545' );
    $accent    = get_option( 'xd_color_accent',    '#f7c315' );
    $font      = get_option( 'xd_font_family',     '' );
    $font_global = get_option( 'xd_font_apply_global', '0' );

        $preview_font_url = '';
    if ( $font ) {
        $preview_font_url = 'https://fonts.googleapis.com/css2?family=' . urlencode( $font ) . ':wght@400;700&display=swap';
    }
    ?>
    <?php if ( $preview_font_url ) : ?>
    <link href="<?php echo esc_url( $preview_font_url ); ?>" rel="stylesheet">
    <?php endif; ?>

    <div class="wrap" style="max-width:780px;">
        <h1>🎨 Design – Màu sắc & Font</h1>
        <p style="color:#666;margin-bottom:20px;">Các biến CSS được output vào <code>:root</code> — mọi component trong plugin sẽ tự nhận màu và font này. Sau khi lưu, reload trang để xem thay đổi.</p>

        <!-- PREVIEW -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;margin-bottom:20px;">
            <div style="font-size:11px;font-weight:600;color:#aaa;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px;">PREVIEW</div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div id="prev-primary"   style="width:48px;height:48px;border-radius:8px;background:<?php echo esc_attr($primary); ?>;transition:.3s;"></div>
                <div id="prev-secondary" style="width:48px;height:48px;border-radius:8px;background:<?php echo esc_attr($secondary); ?>;transition:.3s;"></div>
                <div id="prev-text"      style="width:48px;height:48px;border-radius:8px;background:<?php echo esc_attr($text); ?>;transition:.3s;"></div>
                <div id="prev-accent"    style="width:48px;height:48px;border-radius:8px;background:<?php echo esc_attr($accent); ?>;transition:.3s;"></div>
                <div style="margin-left:8px;">
                    <div id="prev-site-name" style="font-size:20px;font-weight:700;color:<?php echo esc_attr($text); ?>;font-family:'<?php echo esc_attr($font ?: 'inherit'); ?>',sans-serif;transition:.3s;"><?php echo esc_html( get_bloginfo('name') ); ?></div>
                    <div id="prev-font-label" style="font-size:13px;color:<?php echo esc_attr($secondary); ?>;font-family:'<?php echo esc_attr($font ?: 'inherit'); ?>',sans-serif;transition:.3s;"><?php echo esc_html( $font ?: 'Font mặc định' ); ?> — màu phụ</div>
                </div>
                <button type="button" id="xd-apply-preview" style="margin-left:auto;padding:8px 18px;background:#2271b1;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">▶ Áp dụng preview</button>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'xd_design_save' ); ?>

            <!-- MÀU SẮC -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;margin-bottom:20px;">
                <h2 style="margin-top:0;font-size:16px;">🎨 Màu sắc</h2>
                <table class="form-table" style="margin:0;">
                    <?php
                    $colors = [
                        [ 'xd_color_primary',   $primary,   'Màu chính',   'Nút, link, nhãn trong template bài viết, khối Liên hệ, trang Page XD.' ],
                        [ 'xd_color_secondary', $secondary, 'Màu phụ',     'Màu đậm hơn màu chính (hover nút, nền Page XD khi chưa có ảnh).' ],
                        [ 'xd_color_text',      $text,      'Màu văn bản', 'Màu chữ mặc định toàn site.' ],
                        [ 'xd_color_accent',    $accent,    'Màu nổi bật', 'Dùng cho icon sao rating, highlight đặc biệt.' ],
                    ];
                    foreach ( $colors as [$name, $val, $label, $desc] ) :
                        $js_target = str_replace( 'xd_color_', 'prev-', $name );
                    ?>
                    <tr>
                        <th style="width:140px;padding:10px 0;"><label for="<?php echo $name; ?>"><?php echo $label; ?></label></th>
                        <td style="padding:10px 0;display:flex;align-items:center;gap:12px;">
                            <input type="color" name="<?php echo $name; ?>" id="<?php echo $name; ?>"
                                value="<?php echo esc_attr($val); ?>"
                                style="width:48px;height:40px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;"
                                oninput="document.getElementById('<?php echo $name; ?>_hex').value=this.value;document.getElementById('<?php echo $js_target; ?>').style.background=this.value;<?php if($name==='xd_color_text') echo "document.getElementById('prev-site-name').style.color=this.value;"; if($name==='xd_color_secondary') echo "document.getElementById('prev-font-label').style.color=this.value;"; ?>">
                            <input type="text" id="<?php echo $name; ?>_hex"
                                value="<?php echo esc_attr($val); ?>"
                                maxlength="7" style="width:90px;font-family:monospace;font-size:13px;padding:6px 8px;border:1px solid #ddd;border-radius:4px;"
                                oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/)){document.getElementById('<?php echo $name; ?>').value=this.value;document.getElementById('<?php echo $js_target; ?>').style.background=this.value;}">
                            <span style="font-size:12px;color:#888;"><?php echo esc_html($desc); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- FONT FAMILY -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;margin-bottom:24px;">
                <h2 style="margin-top:0;font-size:16px;">🔤 Font Family</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:140px;padding:10px 0;"><label for="xd_font_family">Font mặc định</label></th>
                        <td style="padding:10px 0;">
                            <input type="text" name="xd_font_family" id="xd_font_family"
                                value="<?php echo esc_attr($font); ?>"
                                placeholder="VD: Be Vietnam Pro"
                                style="width:280px;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                            <p class="description" style="margin-top:6px;">
                                Nhập tên font <a href="https://fonts.google.com" target="_blank">Google Fonts</a>. Plugin tự load font này từ Google. Sau khi lưu, vào
                                <strong>Elementor → Site Settings → Typography</strong> để đặt cùng font cho nhất quán.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0;">Áp dụng toàn trang:</th>
                        <td style="padding:10px 0;">
                            <label>
                                <input type="checkbox" name="xd_font_apply_global" value="1" <?php checked( '1', $font_global ); ?>>
                                Ghi đè font lên chữ toàn trang (<code>!important</code>, không đụng font icon)
                            </label>
                            <p class="description" style="margin-top:4px;">
                                Tắt nếu bạn muốn Elementor tự quản lý font theo từng trang/widget. Bật nếu muốn đồng nhất font toàn site.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0;">Preview font</th>
                        <td style="padding:10px 0;">
                            <div id="xd-font-preview-box" style="background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
                                <div id="xd-font-preview-h" style="font-size:22px;font-weight:700;font-family:'<?php echo esc_attr($font ?: 'inherit'); ?>',sans-serif;color:<?php echo esc_attr($text); ?>;margin-bottom:4px;"><?php echo esc_html( get_bloginfo('name') ); ?> – <?php echo esc_html( $font ?: 'Font mặc định' ); ?></div>
                                <div id="xd-font-preview-p" style="font-size:14px;font-family:'<?php echo esc_attr($font ?: 'inherit'); ?>',sans-serif;color:#666;">The quick brown fox jumps over the lazy dog – 0123456789</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <button type="submit" name="xd_save_design" class="button button-primary" style="font-size:15px;padding:8px 28px;">Lưu cài đặt Design</button>
        </form>
    </div>

    <script>
    var fontInput = document.getElementById('xd_font_family');
    var fontPreviewH = document.getElementById('xd-font-preview-h');
    var fontPreviewP = document.getElementById('xd-font-preview-p');
    var fontLabelEl  = document.getElementById('prev-font-label');
    var siteNameEl   = document.getElementById('prev-site-name');

    var loadedFonts = {};
    function loadAndApplyFont(fontName) {
        if ( ! fontName ) {
            fontPreviewH.style.fontFamily = 'inherit';
            fontPreviewP.style.fontFamily = 'inherit';
            if(siteNameEl) siteNameEl.style.fontFamily = 'inherit';
            if(fontLabelEl) { fontLabelEl.style.fontFamily='inherit'; fontLabelEl.textContent='Font mặc định — màu phụ'; }
            return;
        }
        if ( ! loadedFonts[fontName] ) {
            var link = document.createElement('link');
            link.rel  = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(fontName) + ':wght@400;700&display=swap';
            document.head.appendChild(link);
            loadedFonts[fontName] = true;
        }
        var ff = "'" + fontName + "', sans-serif";
        fontPreviewH.style.fontFamily = ff;
        fontPreviewP.style.fontFamily = ff;
        if(siteNameEl)  siteNameEl.style.fontFamily  = ff;
        if(fontLabelEl) { fontLabelEl.style.fontFamily = ff; fontLabelEl.textContent = fontName + ' — màu phụ'; }
    }

    var fontTimer;
    if(fontInput) {
        loadAndApplyFont(fontInput.value.trim());
        fontInput.addEventListener('input', function(){
            clearTimeout(fontTimer);
            fontTimer = setTimeout(function(){ loadAndApplyFont(fontInput.value.trim()); }, 600);
        });
    }

    var applyBtn = document.getElementById('xd-apply-preview');
    if(applyBtn) {
        applyBtn.addEventListener('click', function(){
            ['primary','secondary','text','accent'].forEach(function(key){
                var inp = document.getElementById('xd_color_' + key);
                var box = document.getElementById('prev-' + key);
                if(inp && box) box.style.background = inp.value;
            });
            if(siteNameEl) siteNameEl.style.color = document.getElementById('xd_color_text').value;
            if(fontLabelEl) fontLabelEl.style.color = document.getElementById('xd_color_secondary').value;
            loadAndApplyFont(fontInput ? fontInput.value.trim() : '');
        });
    }
    </script>
    <?php
}
