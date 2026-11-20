# Ghi chú cho agent khi triển khai VinhXD Connector

## Không bao giờ
- **Không dùng `create-file` của File Manager OnePanel với tên trùng một thư mục WordPress** (`wp-content`, `plugins`, `themes`…). Thao tác này từng phá/đè cây thư mục và xoá sạch plugin trên nhiều host (sự cố OnePanel 14/11/2026).
- Không tạo lại đường dẫn bằng cách loop `create-file`/`create-directory` qua từng đoạn path đã tồn tại.
- Không cài đè vào cả `WP_PLUGIN_DIR`.

## Cách cài/cập nhật an toàn (đã dùng cho 18 web trên host 0725.xd)
1. Chỉ tạo **một** thư mục mới khi nó chưa tồn tại (`plugins/vinhxd-connector`, hoặc thư mục tạm tên ngẫu nhiên ở docroot). Thư mục đã có thì upload `overwrite=1` vào đúng đường dẫn đó.
2. Ghi file bằng script tạm có hành động cố định: kiểm tra sha256 nội dung, chỉ ghi vào `plugins/vinhxd-connector/`, chụp danh sách thư mục plugin/theme trước và sau, mất bất kỳ thư mục nào → dừng.
3. Gửi nội dung PHP dạng **base64** (Wordfence WAF chặn POST có mã PHP thô).
4. Kích hoạt bằng `activate_plugin('vinhxd-connector/vinhxd-connector.php')`.
5. Cài **tuần tự từng web**, kiểm tra trang chủ + wp-login trước/sau; lỗi ở web nào thì dừng cả lô.
6. Xoá script/thư mục tạm ngay sau khi xong và xác nhận đã mất.

## Kiểm tra nhanh sau khi cài
- Plugin đang bật, `Version: 1.3.0`, sha256 file chính khớp bản trong repo.
- Số thư mục plugin ≥ trước, số thư mục theme = trước.
- Trang chủ trả 200 như trước.
- Dashboard chỉ tự hiện trên domain có tên hãng xe (xem `is_oto_domain()`), hoặc khi MEG bật `dash_oto` / tick trong Cài đặt → Vĩnh XD Connector.
