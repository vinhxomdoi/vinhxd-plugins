# XD Bảng Giá Xe SEO (`xd-bang-gia-seo`)

Plugin WordPress bảng giá xe dạng `<table>` HTML chuẩn SEO (tối ưu Google Featured Snippets & Schema `ItemList / Product / Offer`), tương thích hoàn toàn với **VinhXD Ô tô** (`VinhXD_Pricing`).

## Tính năng chính
- **Tự động cập nhật 100%** theo dữ liệu giá phiên bản (`gia-xe` / `gia`), khuyến mãi đang chạy (`khuyen-mai`) và biểu phí lăn bánh của **VinhXD Ô tô** (kể cả khi đồng bộ từ Hub).
- **Tính "Ưu đãi lên đến" riêng cho từng phiên bản**: hỗ trợ lọc theo phạm vi phiên bản (`_xdh_scope`), mức giảm riêng từng xe (`muc-rieng`), tự loại ưu đãi hết hạn, gom nhóm độc quyền (`nhom`) và tính `% sau ưu đãi khác` (`sau-uu-dai = 1`, ví dụ VinClub).
- **3 chế độ tính ưu đãi** (cấu hình tại **WP Admin → Xe → Bảng Giá SEO** hoặc qua thuộc tính `mode`):
  - `tong` *(mặc định)*: Cùng `nhom` lấy mức cao nhất, khác nhóm cộng dồn, ưu đãi `% sau ưu đãi khác` tính trên giá còn lại.
  - `max`: Đồng nhất với `vinhxd_km_the()` trên thẻ xe VinhXD Ô tô 3.10.3.
  - `mac_dinh`: Chỉ tính các ưu đãi tick sẵn (`mac-dinh = 1`).
- **Tương thích `xd-guard.php`**: Slug bắt đầu bằng `xd-`, an toàn với hệ thống bảo mật VinhXD Connector.

## Danh sách Shortcode

| Shortcode | Mô tả |
|---|---|
| `[xd_bang_gia_seo]` | Bảng giá toàn bộ xe theo từng phiên bản (`Dòng xe`, `Phiên bản`, `Giá niêm yết`, `Ưu đãi lên đến`, `Giá sau ưu đãi`) + Schema `ItemList`. |
| `[xd_bang_gia_seo group_by_dong="yes" dong_xe="xe-vinfast,xe-dich-vu" title="Bảng giá xe tháng {thang}"]` | Chia bảng theo từng nhóm `dong-xe`, tiêu đề tự cập nhật tháng/năm (`{thang}`, `{nam}`, `{ngay}`). |
| `[xd_bang_gia_seo mode="max" exclude_nhom="congvu" show_lanbanh="yes"]` | Ghi đè chế độ tính ưu đãi, loại trừ nhóm `congvu`, bật thêm cột giá lăn bánh tạm tính. |
| `[xd_bang_lan_banh_seo title="Bảng giá lăn bánh tạm tính tháng {thang}"]` | Bảng giá lăn bánh tự động theo các khu vực đăng ký trong `VinhXD_Pricing::regions()` (tự xử lý xe điện miễn trước bạ, xe tải/van, bán tải). |
| `[xd_bang_uu_dai_seo title="Chương trình ưu đãi tháng {thang}"]` | Bảng tổng hợp các chương trình khuyến mãi đang còn hiệu lực. |

## Các thuộc tính phổ biến của `[xd_bang_gia_seo]`
- `dong_xe=""`: Lọc theo slug hoặc ID của taxonomy `dong-xe` (ngăn cách dấu phẩy).
- `group_by_dong="no|yes"`: Chia thành nhiều bảng theo từng `dong-xe`.
- `mode="tong|max|mac_dinh"`: Công thức tính ưu đãi lên đến.
- `exclude_nhom=""`: Loại trừ nhóm `nhom` khuyến mãi (VD: `congvu`).
- `orderby="price_asc|price_desc|menu_order|title"`: Thứ tự dòng xe.
- `show_img="yes|no"`, `show_uudai="yes|no"`, `show_after="yes|no"`, `show_lanbanh="no|yes"`, `schema="yes|no"`, `note="yes|no"`.

## Cài đặt
1. Tải file `xd-bang-gia-seo.zip` (hoặc nén thư mục `xd-bang-gia-seo/` thành `.zip`).
2. Vào **WP Admin → Plugins → Add New → Upload Plugin** và kích hoạt (yêu cầu đã kích hoạt `vinhxd-oto`).
3. Tùy chỉnh mặc định tại **WP Admin → Xe → Bảng Giá SEO**.
