# Popup XD — Changelog

## 1.3.0

### Trang sửa popup
- **Xem trước nằm cạnh phần cấu hình** khi vùng soạn thảo đủ rộng (từ khoảng 1000px; ẩn bớt cột bên phải trong Screen Options để có thêm chỗ), dính theo khi cuộn. Màn hình hẹp hơn thì xếp bên dưới như cũ.
- **5 tab mới**: Nội dung · Giao diện · Kích hoạt · Nơi hiển thị · Nút & đo lường. Mỗi tab chia nhóm có tiêu đề. Plugin nhớ tab đang mở.
- **Công tắc Bật/Tắt** ở đầu khung cấu hình và **dòng tóm tắt** cập nhật ngay khi chỉnh, vd: "Hiện sau 3 giây hoặc khi cuộn 50% · chỉ Desktop · toàn site · mỗi người 1 lần/7 ngày".
- Chọn "Mẫu Elementor" thì ẩn trình soạn thảo WordPress.
- **Báo lỗi ngay khi nhập sai** (padding, ID loại trừ, CSS selector), chặn lưu và tự chuyển tới đúng ô.
- Các ô chỉ có tác dụng khi bật một lựa chọn sẽ mờ đi lúc lựa chọn đó tắt (giá trị vẫn được giữ).
- Ô offset chỉ hiện đúng phía tương ứng với vị trí popup đã chọn.
- **Nút X chọn theo góc**: góc trên phải/trái, phía trên khung (bên phải/trái), hoặc "Tùy chỉnh" (4 hướng như cũ). Popup tạo trước bản 1.3 tự dùng "Tùy chỉnh" nên vị trí không đổi.
- **Chọn trang có ô tìm kiếm**, lọc "chỉ trang đã chọn" và đếm số trang đã chọn (thay cho danh sách phải giữ Ctrl).
- **Nút Sao chép** cho shortcode, link `#popupxd-ID` và `PopupXD.open(ID)`.
- **Cảnh báo SEO** khi popup tự hiện dưới 5 giây trên mobile.
- Mục "Tự động hiện lại sau khi đóng" được thu vào phần Nâng cao.

### Danh sách popup
- Cột **Bật/Tắt** (công tắc AJAX), cột **Hoạt động** (dòng tóm tắt), cột "Nơi hiển thị" ghi rõ số trang / loại nội dung / ID loại trừ.
- Thao tác **Nhân bản** (tạo bản Nháp với đủ cấu hình).

### Phía khách
- **Vùng bấm nút X tối thiểu 44×44px** (icon vẫn giữ kích thước cũ).
- **Kiểu bottom sheet cho mobile**: rộng hết màn hình, trượt lên từ đáy, có thanh kéo, bo 2 góc trên, có khoảng an toàn cho iPhone.
- **Sau khi gửi form thành công** (Elementor Pro Forms, Fluent Forms, Contact Form 7): tự đóng sau N giây; không tự hiện lại với khách đó trong N ngày (mặc định 30); không bị "tự hiện lại" làm phiền.
- **Tính là đã xem khi popup hiện ra** (tùy chọn): khách chuyển trang mà không đóng popup cũng không bị làm phiền lại.
- **Sự kiện cho Google Tag Manager** qua `dataLayer`: `popupxd_open` (kèm nguồn kích hoạt), `popupxd_close` (kèm cách đóng), `popupxd_click`, `popupxd_form_submit`. Khung xem trước trong admin không đẩy sự kiện.

## 1.2.0

### Xem trước trực tiếp trong trang sửa popup
- Khung "Xem trước" ngay dưới các tab cấu hình. Popup được render bằng chính theme và CSS của site (kể cả CSS riêng của mẫu Elementor), hiển thị đúng như ngoài site: overlay, độ rộng, viền, bo góc, nút X, animation.
- Chuyển Desktop (1280px) / Tablet (820px) / Mobile (390px). Khung dùng đúng kích thước màn hình thật rồi thu nhỏ cho vừa. Dòng thông tin ghi độ rộng tối đa đã đặt và kích thước hiển thị thực tế của popup (vd: "Hiển thị thực tế: 600 × 197px").
- Cập nhật ngay khi đổi bất kỳ cài đặt nào, đổi mẫu, sửa tiêu đề hoặc nội dung trình soạn WordPress, không cần bấm Lưu.
- Bấm X, bấm ra ngoài hoặc nhấn Esc trong khung để xem lại hiệu ứng xuất hiện.
- Trang xem trước chỉ admin mở được (nonce + quyền sửa popup), gửi bằng POST và không bị plugin cache lưu lại.

### Nút cho mẫu Elementor (mở ở tab mới)
- **Sửa mẫu bằng Elementor**: mở trình soạn của mẫu đang chọn.
- **Tạo mẫu mới**: tạo mẫu trống tên "Popup XD – {tên popup}", chọn sẵn trong dropdown và mở trình soạn luôn.
- Quay lại tab popup sau khi sửa ở Elementor, khung xem trước tự làm mới.

### Khác
- API JS `PopupXD.open( id, { noFocus: true } )`.

## 1.1.0

### Sửa lỗi
- Popup chỉ bật cho một số thiết bị không còn khóa cuộn trang trên các thiết bị khác (trước đây popup bị CSS ẩn nhưng body vẫn bị `overflow:hidden`).
- Kích hoạt bằng CSS selector hoạt động cả khi khách đã đóng popup và đang trong thời gian "Không hiện lại". Trên thiết bị không được chọn, phần tử giữ hành vi gốc (link vẫn mở bình thường).
- Shortcode `[popupxd]` đặt ở header, footer, widget hay trang lưu trữ đều tải đủ CSS/JS và CSS của mẫu Elementor.
- Tùy chọn "Cho phép mở thủ công" giờ có tác dụng: khi tắt, nút bị ẩn với khách; admin thấy dòng nhắc thay cho nút.
- CSS selector nhập sai không còn gây lỗi JS ở mỗi lần click, thay vào đó in cảnh báo ra console.
- Nhiều popup tự động không còn mở chồng lên nhau: popup sau xếp hàng chờ popup trước đóng. Popup mở thủ công vẫn được chồng lên trên.
- Slider/carousel Elementor bên trong popup được tính lại kích thước mỗi khi popup mở.
- "Loại trừ ID" không còn nhầm ID chuyên mục với ID bài viết.
- Nhập JSON: gỡ bỏ ID mẫu Elementor và ID trang không tồn tại trên site mới, kèm thông báo số liên kết đã bỏ. Chỉ nhận các trường Popup XD biết, và sửa lỗi mảng bị xuất dưới dạng chuỗi serialize.
- Offset nút X: ô để trống giờ thật sự là để trống (trước đây ô "Trên" bị trả về 8px).
- Popup được in ở `wp_footer` ưu tiên 5, trước khi WordPress và Elementor in script footer, nên asset của mẫu Elementor trên trang không dựng bằng Elementor vẫn được nạp.
- Popup không tự bật trong khung soạn thảo Elementor.

### Hiệu năng
- Danh sách popup được truy vấn 1 lần mỗi request, kết quả khớp trang được cache.
- Scroll trigger dùng `requestAnimationFrame` và gỡ listener sau khi kích hoạt; exit-intent chỉ gắn trên thiết bị có chuột.
- Nạp bản `.min.js` / `.min.css` (bản đọc được dùng khi bật `SCRIPT_DEBUG`).
- Version asset = phiên bản + thời gian sửa file, nên cache trình duyệt/CDN tự làm mới sau mỗi lần cập nhật.
- Bỏ `wp_enqueue_media()` thừa ở trang sửa popup.

### Bảo mật
- Mọi trường đều được kiểm tra theo kiểu khi lưu, khi nhập JSON và khi hiển thị: màu (hex/rgb/transparent), kích thước CSS (1–4 giá trị), số, danh sách cố định, class CSS.
- Quyền nhập JSON theo quyền tạo popup (`edit_pages`) thay vì `edit_posts`; giới hạn file 2MB.

### Trợ năng và trải nghiệm
- `role="dialog"`, `aria-modal`, focus vào popup khi mở, giữ phím Tab trong popup, trả focus về nút đã bấm khi đóng. Esc chỉ đóng popup trên cùng.
- Nút X không còn trôi theo khi cuộn nội dung dài.
- Khóa cuộn trên cả `html` và `body` (iOS Safari), bù độ rộng thanh cuộn để trang không giật ngang.
- Tôn trọng `prefers-reduced-motion`.

### Tính năng nhỏ
- Mở popup bằng link `#popupxd-ID` (dán vào ô Link của nút Elementor hoặc menu).
- API JS: `PopupXD.open(id)`, `PopupXD.close(id)`, `PopupXD.closeAll()`; sự kiện `popupxd:open` / `popupxd:close` trên `document`.
- Cột danh sách hiển thị thiết bị và cảnh báo khi tắt mở thủ công.
- `uninstall.php`: mặc định GIỮ dữ liệu; thêm `define( 'POPUPXD_REMOVE_DATA_ON_UNINSTALL', true );` vào wp-config.php nếu muốn xóa sạch.
