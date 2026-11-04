# XD Mega Menu – Nhật ký thay đổi

## 3.3

### Sửa lỗi
- **Mất cờ Mega Menu**: trước đây lưu menu trong Customizer, REST API, import hoặc plugin khác làm xoá `_xd_mega`. Giờ chỉ lưu khi có nonce của plugin và item có field trong form. Form bị cắt do `max_input_vars` cũng không làm mất dữ liệu.
- **Màu "trang hiện tại" chưa bao giờ chạy**: widget không gọi `_wp_menu_item_classes_by_context()` nên không có `current-menu-item`. Đã sửa; link hiện tại có thêm `aria-current="page"`.
- **Mobile không vào được trang của mục cha**: chữ giờ là link thật; nút mũi tên riêng mở menu con. Link `#` vẫn mở menu con như cũ.
- **Mũi tên phụ thuộc Font Awesome**: chuyển sang SVG có sẵn, hoặc chọn icon trong control mới "Icon mũi tên menu con".
- **Element Caching của Elementor**: widget khai báo `is_dynamic_content()` nên mục active không bị cache sai trang.
- **Cache grid xe không xoá khi có Redis/Memcached**: chuyển sang cache phiên bản hoá (`xd_mm_cache_ver`). Chỉ làm mới khi post type/meta mà shortcode dùng thay đổi (kể cả đổi giá, đổi ảnh đại diện, import).
- Control "Padding khung" (mobile) trước áp cả desktop. Giờ chỉ áp trong menu mobile.
- Class `.xd-menu-open` trước đây không bao giờ được thêm. Giờ JS dùng class này để mở menu.
- Lưu chiều rộng mega: kiểm tra định dạng (`1200px`, `80vw`, `full`…). Số trần như `900` tự hiểu là `900px`. ID template dùng `absint`.
- Chống lặp vô hạn khi template mega chứa chính widget XD Menu.

### Hiệu năng
- Bỏ `<style>` inline: toàn bộ CSS nằm trong file tĩnh `assets/widgets/xd-menu.css`. Breakpoint theo class `.xd-bp-1024/767/480`, tuỳ chỉnh mobile qua biến CSS `--xd-m-*`. Xem trước trong editor cập nhật ngay.
- JS viết lại bằng JS thuần, không cần jQuery, nạp `defer`. Sự kiện scroll giới hạn 1 lần/khung hình, chỉ chạy khi mega đang mở.
- Trang Giao diện > Menu chỉ query danh sách template 1 lần, bỏ template header/footer/popup/kit…
- Ảnh trong grid xe: `loading="lazy"`, `fetchpriority="low"`, `decoding="async"` để không bị coi là ảnh LCP.
- Tuỳ chọn **Cache HTML template mega (thử nghiệm)** ở tab Nội dung > Nâng cao. Mặc định tắt. Chỉ cache cho khách chưa đăng nhập, tự nạp lại CSS/JS của template, tự làm mới khi sửa template, Regenerate CSS hoặc đổi phiên bản Elementor.
- Version asset = phiên bản + `filemtime` để tránh cache cũ.

### UX và khả năng truy cập
- Desktop: hover có trễ đóng 150ms, có "cầu nối" phủ khoảng hở giữa mục menu và mega.
- Màn hình cảm ứng rộng (iPad ngang): chạm lần 1 mở menu, chạm lần 2 vào trang, chạm ra ngoài thì đóng.
- Bàn phím: Tab tới nút mở menu con (ẩn trên desktop, có viền focus trên link), Enter để mở, Esc để đóng, Tab ra ngoài tự đóng. Có `aria-expanded`, `aria-controls`, `aria-label`.
- Drawer mobile: giữ focus bên trong, Esc đóng từng tầng, trả focus về nút. Có nút "Đóng menu" cho bàn phím. Panel đóng thì không Tab vào được.
- Mega tự bù vị trí khi phần tử cha có `transform` (Motion effects/animation của Elementor).
- Mega `full` rộng đúng bằng màn hình (không tính thanh cuộn).
- Panel con mobile mở đúng chỗ dù khung menu đã cuộn. Danh sách con dài cuộn được.
- Tôn trọng `prefers-reduced-motion`.

### Control mới (giữ nguyên ID control cũ)
- Nội dung: Icon mũi tên menu con, Cache HTML template mega.
- Style > Icon mũi tên: kích thước, khoảng cách, màu thường/hover.
- Style > Mega/Dropdown: chiều rộng mega mặc định, typography và padding mục dropdown.
- Style > Mobile: màu nút mũi tên, typography/màu/nền/đường kẻ thanh "quay lại".

### Khác
- Shortcode `[xd_xe_grid]` thêm `columns_tablet` (mặc định 4) và `columns_mobile` (mặc định 2). CSS riêng `assets/widgets/xd-xe-grid.css`, chỉ nạp khi có shortcode.
- Giữ class CSS tuỳ chỉnh của mục menu (Giao diện > Menu > CSS Classes), `rel`, `title`. Link `_blank` thêm `noopener`.
- `uninstall.php` dọn meta/option/transient khi xoá plugin.
- Khi nâng cấp, plugin tự xoá transient cũ và cache CSS của Elementor (tương đương Regenerate CSS) một lần.

### Lưu ý
- Dropdown thường (không phải mega) vẫn dùng `position:absolute`, nên bị cắt nếu container header đặt **Overflow: Hidden**. Mega thì không bị.
- Sửa CSS: chỉnh `dev/base.css` hoặc `dev/mobile.tpl.css`, rồi chạy `python3 dev/build-css.py assets/widgets/xd-menu.css`.
