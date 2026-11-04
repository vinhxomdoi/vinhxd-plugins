=== Elementor XD ===
Contributors: vinhxd
Tags: elementor, widgets, slider, banner, testimonial
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Bộ widget Elementor gọn, hiện đại, có sẵn nhiều kiểu (preset). CSS/JS chỉ nạp ở trang có widget.

Tác giả: Vĩnh XD – https://vinhxd.com
Trang plugin & hướng dẫn: https://vinhxd.com/tai-nguyen/elementor-xd

== Widget ==

Danh mục "Elementor XD" trong khung Elementor (gõ "XD" để lọc nhanh):

* Khách hàng XD (Testimonials) – 6 kiểu: Thẻ, Bong bóng hội thoại, Tối giản, Ảnh lớn bên trái, Dấu ngoặc lớn nền màu, Nền tối. Có số sao (0.5).
* Thành viên XD (Team Member) – 5 kiểu: Thẻ, Thông tin hiện trên ảnh, Ảnh tròn, Thẻ tên nổi, Ảnh bên trái. Mạng xã hội dạng lặp.
* Hộp thông báo XD (Message box) – 6 kiểu: Nền nhạt, Nền màu, Chuyển màu, Viền, Vạch nhấn trái, Nền tối. 1 hàng / xếp dọc, 2 nút, cho phép đóng (nhớ N ngày).
* Cửa sổ nổi XD (Lightbox) – mở bằng nút/ảnh/chữ hoặc link #exd-lb-ID bất kỳ. Nội dung: soạn trực tiếp, template Elementor, ảnh, video. Kiểu: giữa màn hình, trượt phải, trượt từ dưới, toàn màn hình. Tự mở sau N giây / khi cuộn 50% / khi rê chuột ra ngoài, tần suất mỗi lần – mỗi phiên – N ngày.
* Hộp ảnh XD (Image Box) – 7 kiểu: Thẻ, Đơn giản, Chữ trên ảnh, Hiện chữ khi hover, Hộp chữ đè lên ảnh, Hộp chữ nổi trong ảnh, Ảnh bên trái.
* Icon Box XD – 7 kiểu × 3 vị trí icon (trên/trái/phải): Đơn giản, Icon nền nhạt, Icon nền đặc, Icon viền tròn, Thẻ nổi, Thẻ có vạch nhấn, Thẻ kính mờ. Icon / ảnh / số.
* Banner XD – 6 kiểu: Chữ trắng trên ảnh, Chuyển tối từ cạnh, Nền sáng, Hộp chữ trắng, Hộp kính mờ, Chia đôi. Vị trí nội dung 9 điểm, 2 nút, link cả banner, tuỳ chọn "Banner đầu trang" (fetchpriority=high cho LCP).
* Banner Grids XD – 7 bố cục: 1 lớn + 2, 1 lớn + 4, 2 nhỏ + 1 lớn phải, Mosaic, 1 rộng trên + 3, Hàng cao, Lưới đều. Dùng chung kiểu chữ của Banner.
* Slider XD (Sliders) – slide banner hoặc template Elementor; trượt (vuốt gốc) hoặc mờ dần; nhiều slide/khung, hé lộ slide kế, tự chạy (dừng khi rê chuột, khi ra khỏi màn hình, khi tab ẩn), mũi tên 4 kiểu, chấm 3 kiểu.
* Nút video XD (Video Button) – 4 kiểu, sóng lan, ảnh bìa (hoặc tự lấy ảnh bìa YouTube). YouTube/Vimeo/MP4, iframe chỉ tạo khi bấm.
* Tiêu đề XD (Titles / Dividers) – 7 kiểu: Đơn giản, Gạch chân ngắn, Kẻ ngang tới link, Kẻ hai bên, Thanh nhấn trái, Dòng nhỏ dạng nhãn, Chữ lớn. Bọc *chữ* trong dấu sao để tô màu nhấn.
* Bài viết XD (Blog Posts) – Lưới / Danh sách / Slider; 5 kiểu thẻ; chuyên mục, ID, sắp xếp, ngày, tác giả, thời gian đọc.
* Scroll To XD – mốc cuộn có chừa header + thanh chấm điều hướng cố định; hoặc nút "cuộn xuống" 3 kiểu.

== Hệ kiểu (preset) ==

* Mỗi widget có ô "Kiểu (preset)". Đổi kiểu chỉ đổi 1 class trên widget → giữ nguyên nội dung và mọi chỉnh sửa ở tab Style.
* CSS của kiểu viết bằng :where() (độ ưu tiên thấp) nên chỉnh ở tab Style luôn thắng, không cần !important.
* Màu nhấn lấy từ màu Primary trong Site Settings của Elementor; mỗi widget có ô "Màu nhấn của widget" để đổi riêng.
* Biến dùng chung có thể ghi đè trong Site Settings → Custom CSS: --exd-primary, --exd-heading, --exd-text, --exd-radius, --exd-shadow.

== Tốc độ ==

* Mỗi widget có file CSS/JS riêng trong assets/widgets/, chỉ nạp ở trang có widget đó (Elementor get_style_depends / get_script_depends).
* JS chỉ nạp khi cần: Hộp thông báo (khi bật "Cho phép đóng"), Cửa sổ nổi (JS tự mở chỉ khi bật "Tự mở"), Bài viết (chỉ kiểu Slider), Scroll To (chỉ khi bật thanh chấm).
* Không jQuery, không thư viện ngoài. Slider dùng CSS scroll-snap của trình duyệt.
* Ảnh in bằng wp_get_attachment_image (srcset, sizes, width/height, lazy). Video/iframe chỉ tạo khi bấm.
* Bỏ thẻ bọc thừa .elementor-widget-container (DOM gọn hơn). Widget không phụ thuộc người xem cho phép Elementor cache HTML.
* Tắt widget không dùng ở Elementor → Elementor XD.

== Changelog ==

= 1.0.0 =
* Phát hành đầu tiên: 13 widget, hệ kiểu preset, nạp asset theo widget.
