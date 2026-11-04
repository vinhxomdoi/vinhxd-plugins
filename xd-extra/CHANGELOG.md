# XD Extra – Changelog

## 3.3.0
**Mới – module "CSS tùy chỉnh"** (`includes/post-css.php`, bật/tắt ở XD Extra → Cài đặt, mặc định bật)
- Meta box "CSS riêng cho trang này" cho mọi post type public (trừ attachment, elementor_library); filter `xd_post_css_post_types`. Lưu meta `_single_add_custom_css` (cùng key WP Add Custom CSS). Nonce, `edit_post`, bỏ qua autosave/revision, giữ dấu `\`, ô trống thì xoá meta.
- Trang "CSS toàn site" (Chèn Mã Nhúng → CSS toàn site), option `xd_global_css` (autoload). Thay cho ô "CSS Tùy Chỉnh" cũ.
- Lần đầu chạy: lấy CSS từ `xddb_global_css`, nếu trống thì từ `wpacc_settings['main_custom_style']`; nội dung ô `xd_custom_css` cũ được nối vào cuối.
- In inline trong `<head>` (wp_head 100): `#xd-global-css`, rồi `#xd-post-css` trên trang đơn. Lọc `</style`, `<script`; `&gt;` → `>`.
- Trình soạn có tô màu cú pháp (CodeMirror của WordPress) cho cả 2 ô.
- Guard `XD_POST_CSS_LOADED`: plugin nào nạp module trước thì chạy, bản còn lại nhường.


## 3.2.0
**Mới**
- [xd_lien_he] thiết kế lại: bản đồ nền + thẻ thông tin & form nổi; nút Gọi ngay / Chat Zalo / Chỉ đường; giờ làm việc; bản đồ chỉ tải khi bấm (ban_do="click|luon|tat"). CSS trong plugin, màu theo Design.
- Nút liên hệ nổi + nút lên đầu trang: khoảng cách dưới riêng cho Desktop và Mobile.
- Chống backup/clone: mu-plugin XD Guard (chạy trước mọi plugin, vẫn chặn khi XD Extra tắt), chặn cài (cả upload zip) và kích hoạt, ẩn khỏi tìm kiếm plugin, tắt Công cụ → Xuất dữ liệu, danh sách plugin được phép, bỏ qua tạm bằng XD_ALLOW_BACKUP.
- Chặn phần mềm tải cả website (HTTrack, wget, Scrapy, headless...), khoá tên miền (chống site nhái proxy/iframe), bảo vệ RSS, nhật ký chặn.
- Paste as Text hoạt động thật; tab Shortcodes có hướng dẫn.

**Sửa lỗi**
- Trang Kích thước ảnh: form lồng nhau làm "Lưu danh sách"/"Xóa" sai; kích thước mới không hiện ngay.
- WebP: không còn ghi đè ảnh cùng tên; ảnh WebP không bị nén lại nếu không cần thu nhỏ.
- [xd_uu_dai]: chữ hoa tiếng Việt (mb_strtoupper), tháng theo múi giờ WordPress, bỏ onclick inline.
- Chống copy: không còn chặn copy/dán trong ô nhập liệu.
- Lõi XD Guard nạp 2 lần an toàn.

**Tối ưu**
- Tách 1.300 dòng thành module (includes/, admin/).
- CSS/JS chỉ nạp khi cần; CSS shortcode nạp ở <head> (hoặc inline 1 lần) → không giật layout.
- Hiệu ứng nút dùng transform (không bắt trình duyệt tính lại layout); cuộn passive; tôn trọng prefers-reduced-motion.
- Template bài viết: 1 truy vấn bài liên quan thay vì 2, ảnh có srcset/width/height, ảnh đại diện fetchpriority=high, bỏ ảnh placehold.co, bỏ Font Awesome, breadcrumb có schema, màu theo Design.
- Bỏ !important trong CSS nút; Google Font bỏ độ đậm 300.

**Gỡ bỏ**
- Bộ cập nhật từ vinhxd.com (thêm Update URI: false để WordPress.org không đè bản cập nhật).
- includes/custom-functions.php, tab log chặn login cũ, script sticky không dùng.
