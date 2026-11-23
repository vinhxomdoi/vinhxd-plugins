# VinhXD Connector — Changelog

## 1.4.0

### Tách & nâng cấp toàn diện Chống Clone từ XD Extra (`includes/guard-core.php`, `includes/guard-admin.php`)
- **Ẩn & tự vệ cho VinhXD Connector (`xd_hide_connector`, mặc định bật)**:
  - Ẩn hoàn toàn khỏi `Plugins → Đã cài đặt`, `Dashboard → Cập nhật`, tab `Must-Use` (`xd-guard.php`) và menu sidebar `Cài đặt → Vĩnh XD Connector` (vẫn vào được bằng URL trực tiếp `wp-admin/options-general.php?page=vxd-connector`).
  - Chặn tắt/xóa qua UI, URL trực tiếp, Bulk Action, AJAX, REST API `/wp/v2/plugins` và tự phục hồi nếu bị gỡ khỏi `active_plugins`.
  - Khóa tab **Chống Clone & Bảo Mật** và **Nhật ký chặn** bằng **License Key MEG** (hoặc Mã PIN quản trị, phiên 30 phút, chống brute-force 5 lần/15 phút); tự động che License Key trên tab Kết nối MEG khi chưa mở khóa và chặn sửa trái phép các option bảo mật qua `/wp-admin/options.php`.
- **Kịch bản chống clone nâng cao**:
  - Mở rộng danh sách chặn plugin Backup / Migration / Clone / File Manager / DB Manager / Static Exporter / Remote Manager; bỏ mặc định whitelist `wp-file-manager` (không chặn các plugin chèn mã nhúng phổ biến như Code Snippets, WPCode, HFCM…).
  - **Quét chữ ký mã nguồn** cho cả plugin & theme khi cài đặt/kích hoạt (chống đổi tên thư mục/ZIP, phát hiện chữ ký công cụ backup/file manager, mã dump DB, mã zip source, `eval(base64_decode...)`).
  - **Khóa cứng sửa file Theme/Plugin** (`xd_disable_file_edit`) ở tầng `map_meta_cap`, trình soạn thảo và AJAX `edit-theme-plugin-file` — có hiệu lực kể cả khi `wp-config.php` đặt `DISALLOW_FILE_EDIT = false`.
  - **Chặn tải lên file ZIP plugin & giao diện từ bên ngoài** (`xd_block_zip_upload`), vẫn cho cài plugin sạch từ kho WP và cập nhật từ MEG.
  - **Chặn REST API ẩn danh (`/wp-json/wp/v2/*`) & tắt XML-RPC** (`xd_block_rest_anon`), không ảnh hưởng user đã đăng nhập, Fluent Forms, Elementor, VinhXD.
  - Tùy chọn chặn xuất mẫu Elementor & Skin JetEngine (`xd_block_tpl_export`), cùng toàn bộ lớp Frontend (chống copy/F12, chặn bot tải web, khóa tên miền, bảo vệ RSS).
  - Quét & cảnh báo tệp backup bỏ quên (`.zip`, `.wpress`, `.sql`…) ở docroot / `wp-content/`.
- **Điều khiển & giám sát từ xa qua MEG**:
  - Hỗ trợ endpoint `?vxd_connector=guard` (ký HMAC-SHA256) và `features['guard']`; gửi trạng thái bảo vệ + nhật ký chặn + cảnh báo file backup lộ về MEG khi `ping()`.
- Giữ tương thích 100% với các `wp_options` cũ từ XD Extra (`xd_anti_migration`, `xd_anti_clone`, `xd_block_rippers`, `xd_disable_file_edit`…).

## 1.3.0

### Sửa lỗi & an toàn
- **Cài đè khi cập nhật lỗi chỉ trong thư mục của chính plugin** (`wp-content/plugins/<slug>`). Bản cũ cài đè vào cả `WP_PLUGIN_DIR` với `clear_destination`. Đường dẫn bất thường → dừng, không cài.
- **Kiểm tra trước/sau khi cập nhật từ xa**: chụp danh sách thư mục plugin + theme trước khi chạy; nếu sau khi chạy có thư mục khác biến mất → trả lỗi `DỪNG` kèm danh sách `lost` về MEG.
- **Báo web thiếu file** (`content_health`): theme đang dùng/theme cha mất, thư mục themes trống, plugin đang bật mất file → thông báo đỏ trong admin và gửi kèm ping hằng ngày về MEG.
- `http_request_host_is_external` chỉ mở cho `vinhxd.com` / `*.vinhxd.com` (bản cũ mở cho mọi host).
- `register_deactivation_hook` chuyển ra ngoài constructor.

### Giao diện trang quản trị
- Viết lại dashboard theo style pastel kiểu Geely, màu Vĩnh XD `#0F6E56`: hero gradient pastel, thẻ trắng bo 24px, ảnh SaleOto trên các vòng elip, thanh YouTube gradient đậm, khối hỗ trợ kỹ thuật (Zalo 0965 401 120), ô số liệu (lead 30 ngày từ Fluent Forms, số xe, bài viết, trang) và trạng thái kết nối ở cuối trang.
- Font: Lexend (tiêu đề, có dấu tiếng Việt) + Inter. Icon SVG nét mảnh, không emoji.
- Line-height đặt rõ cho mọi khối chữ lớn (WP admin ép line-height nhỏ làm dính chữ).

### Khác
- Mô tả: "Kết nối web với Vĩnh XD, cập nhật giao diện, chức năng và xử lý lỗi." · Tác giả: Vĩnh XD.

## 1.2.5
- Bản Hermes viết (nhận cập nhật từ MEG, ping hằng ngày, dashboard Oto).
