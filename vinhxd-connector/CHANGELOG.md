# VinhXD Connector — Changelog

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
