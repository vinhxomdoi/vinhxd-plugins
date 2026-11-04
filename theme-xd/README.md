# Theme XD v1.9.0

> Theme WordPress tối giản, tương thích hoàn toàn **Elementor** & **JetThemeCore (Crocoblock)**.
> Thiết kế sẵn layout đẹp cho Single Post & Archive — lấy cảm hứng từ lynkconguyenvantroi.vn.

---

## 📁 Cấu trúc file

```
theme-xd/
├── style.css                          # Theme header + toàn bộ CSS
├── functions.php                      # Core functions
├── header.php                         # Header wrapper
├── footer.php                         # Footer wrapper
├── index.php                          # Fallback template
├── single.php                         # Single post ⭐
├── archive.php                        # Archive / Blog ⭐
├── page.php                           # Static page
├── search.php                         # Search results
├── 404.php                            # 404 page
├── comments.php                       # Bình luận
├── inc/
│   ├── theme-options.php              # Trang "Cài đặt Theme XD" trong admin
│   ├── customizer.php                 # WordPress Customizer
│   └── widgets/menu-xd.php           # Elementor widget Menu XD
├── template-parts/
│   ├── header/fallback-header.php    # Header PHP fallback
│   └── footer/fallback-footer.php    # Footer PHP fallback
└── assets/
    ├── css/                           # CSS bổ sung (nếu cần)
    └── js/main.js                     # JavaScript chính
```

---

## 🚀 Cài đặt

1. **Upload theme**: Upload thư mục `theme-xd` vào `/wp-content/themes/`
2. **Kích hoạt**: Vào `Giao diện → Themes` → Kích hoạt Theme XD
3. **Cài plugins cần thiết**:
   - Elementor (miễn phí)
   - JetThemeCore by Crocoblock (nếu có license)

---

## ⚙️ Cấu hình JetThemeCore (Cách chính)

Đây là cách **ưu tiên** — JetThemeCore sẽ tự quản lý Header/Footer/Widget:

1. Vào **JetThemeCore → Theme Parts**
2. Tạo **Header** → Chọn điều kiện: `Toàn site`
3. Tạo **Footer** → Chọn điều kiện: `Toàn site`
4. Thiết kế bằng Elementor như bình thường
5. Theme XD tự nhận diện JetThemeCore và không render header/footer PHP

---

## 🎨 Cấu hình Elementor Template (Cách thay thế)

Nếu **không** dùng JetThemeCore:

1. Vào **Admin → Theme XD → Elementor Templates**
2. Chọn template cho từng vị trí:
   - **Header Template**: Template dùng làm header
   - **Footer Template**: Template dùng làm footer
   - **Sidebar Template**: Template thay thế sidebar WordPress
   - **Trang 404**: Template trang lỗi 404
3. Nhấn **Lưu Templates**

---

## 🎛️ Cài đặt Theme XD

Vào **Admin → Theme XD → Cài đặt chung**:

| Tùy chọn | Mô tả | Mặc định |
|---|---|---|
| Breadcrumb | Hỗ trợ Yoast, RankMath | ✅ Bật |
| Nút chia sẻ | Facebook, X, Copy link | ✅ Bật |
| Khung tác giả | Avatar + bio cuối bài | ✅ Bật |
| Bài viết liên quan | Grid 3 bài cùng chuyên mục | ✅ Bật |
| Điều hướng bài | Trước / Tiếp theo | ✅ Bật |
| Thời gian đọc | "X phút đọc" trong meta | ✅ Bật |
| Custom CSS / JS | Thêm code tuỳ chỉnh | — |

---

## 🎨 Màu sắc & Font chữ

Theme XD **kế thừa 100% từ Elementor Global Settings**:

- Vào **Elementor → Site Settings → Global Colors** để đặt màu
- Vào **Elementor → Site Settings → Global Fonts** để đặt font
- CSS variables của theme sẽ tự cập nhật theo

---

## 📐 Layout Single Post

```
┌─────────────────────────────────────────────┐
│              HEADER (JetThemeCore)           │
├─────────────────────────────────────────────┤
│ Breadcrumb                                  │
├──────────────────────────┬──────────────────┤
│                          │                  │
│  [Category Badge]        │   SIDEBAR        │
│  Tiêu đề bài             │                  │
│  Meta (tác giả, ngày,    │  - Widget 1      │
│        thời gian đọc)    │  - Widget 2      │
│                          │  - ...           │
│  [Featured Image]        │                  │
│                          │  (Sticky)        │
│  Nội dung bài viết       │                  │
│                          │                  │
│  Tags                    │                  │
│  Share buttons           │                  │
│  Author box              │                  │
│  Post navigation         │                  │
│  Related posts           │                  │
│  Comments                │                  │
│                          │                  │
├──────────────────────────┴──────────────────┤
│              FOOTER (JetThemeCore)           │
└─────────────────────────────────────────────┘
```

---

## 🔌 Tương thích

- ✅ **JetThemeCore** (Crocoblock) — ưu tiên cao nhất
- ✅ **Elementor Free & Pro**
- ✅ **Yoast SEO** (breadcrumb, sitemap)
- ✅ **RankMath SEO** (breadcrumb)
- ✅ **WooCommerce**
- ✅ **WordPress 6.0+**
- ✅ **PHP 7.4+**

---

## 🛠️ Tuỳ chỉnh CSS

Các CSS variables có thể ghi đè:

```css
:root {
    --txd-primary:       #0a0a0a;    /* Màu chính */
    --txd-accent:        #e63946;    /* Màu nhấn */
    --txd-text:          #1a1a1a;    /* Màu chữ */
    --txd-bg:            #ffffff;    /* Màu nền */
    --txd-max-width:     1200px;     /* Chiều rộng tối đa */
    --txd-content-width: 780px;      /* Chiều rộng nội dung */
    --txd-radius:        6px;        /* Border radius */
}
```

Thêm vào **Admin → Theme XD → CSS tuỳ chỉnh** hoặc **Elementor → Custom CSS**.

---

## 📞 Hỗ trợ

- Website: [vinhxd.com](https://vinhxd.com)
- Thiết kế bởi: **Vĩnh XD**

---

## 📝 Changelog 1.9.0

- Gỡ bỏ hoàn toàn TOC tự động (PHP, JS, CSS, option, Customizer). Heading vẫn có `scroll-margin-top` để dùng plugin mục lục (Rank Math, Easy TOC…).
- Typography nội dung mới (`.txd-prose`): nhịp khoảng cách H2–H6, list lồng nhau, bảng (tự bọc cuộn ngang mobile, chuẩn hoá bảng dán từ Word), blockquote, căn ảnh Classic Editor, video responsive. Tự bỏ `<p>&nbsp;</p>` rỗng.
- Archive tối giản: bỏ viền/bóng, bài nổi bật chỉ ở trang 1, mobile dạng hàng ngang, phân trang gọn.
- Tốc độ: main.js bỏ jQuery + defer, chỉ nạp khi cần; bỏ emoji script, CSS block khi không dùng; bài liên quan bỏ ORDER BY RAND + cache; excerpt không render the_content; ảnh archive có srcset/sizes, ảnh đầu fetchpriority=high.
- Bỏ toàn bộ image size theme tự tạo (txd-featured, txd-card, txd-thumb, txd-mini, post-thumbnail 900×480). Template dùng size gốc WP (large / medium_large / medium) kèm srcset; size tuỳ chỉnh quản lý bằng XD Extra.
- Sửa lỗi: page.php dùng nhầm template bài viết; author box in thừa câu mặc định; mã nhúng JS bị wp_kses_post làm hỏng; màu XD Extra không áp dụng; Search Template không hoạt động; thiếu comments.php; cột sidebar trống khi chưa có widget; Menu XD (ô SĐT bị bỏ qua, gạch dưới đè mũi tên, menu cấp 3, trùng ID, bàn phím).
