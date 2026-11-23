# XD Analytics – Changelog

## 1.15.0 (Traffic AI)

### Mới
- Trang **XD Analytics → Traffic AI**: đếm lượt khách vào web từ ChatGPT, Gemini, Perplexity, Claude, Copilot, DeepSeek, Grok, Meta AI, Mistral Le Chat, You.com, Phind, Poe, Qwen, Kimi, HuggingChat, Pi, Duck.ai.
- Nhận diện qua referrer + `utm_source` (ChatGPT/Perplexity tự gắn `utm_source=chatgpt.com`...). Danh sách nguồn sửa được ngay trong wp-admin.
- Ghi bằng JS beacon (chạy cả khi bật cache LiteSpeed) + fallback phía server, gộp trùng 30 phút. Không lưu IP (chỉ hash theo ngày).
- Thống kê: tổng lượt, khách ước tính, theo nguồn, biểu đồ theo ngày (Chart.js có sẵn), trang đích top, 50 lượt gần nhất, lọc theo ngày, tải CSV.
- Ghi lượt bot AI crawl (GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, PerplexityBot, Bytespider, CCBot, Meta-ExternalAgent, Amazonbot...) – chỉ request không qua cache.
- Bảng `{prefix}vinhxd_ai_visits`, `{prefix}vinhxd_ai_crawls`; tự xoá dữ liệu cũ (mặc định 180 ngày).
- README.md hướng dẫn đọc số liệu & giới hạn (Google AI Overviews không tách được khỏi Organic).

## 1.14.1 (hotfix bảo mật & ổn định)

### Bảo mật
- Sửa XSS ở hộp "Thống kê bài viết": dữ liệu GA4 (utm_source, link_domain…) được escape trước khi hiển thị.
- Lấy IP khách an toàn: chỉ tin `CF-Connecting-IP` khi request đến từ Cloudflare, chỉ tin `X-Forwarded-For` khi đi qua proxy tin cậy (IP nội bộ hoặc khai báo ở Cài đặt → Proxy tin cậy). Trang Cài đặt hiển thị IP plugin nhận diện để tự kiểm tra.
- OAuth Google Ads có tham số `state` chống CSRF.
- Ghi CSV an toàn: escape dấu ngoặc kép, bỏ xuống dòng, chặn CSV formula injection; cookie/POST được sanitize & giới hạn độ dài.
- Giới hạn 30 lượt log / IP / 10 phút cho endpoint `log_contact_click`.
- File log đổi sang tên ngẫu nhiên (tự chuyển file cũ), `.htaccess` hỗ trợ Apache 2.2 + 2.4, thêm `index.php`.
- Client Secret không còn in ra HTML; secret/private key/refresh token không autoload.
- Cookie theo dõi đặt HttpOnly, SameSite=Lax, Secure (khi HTTPS).

### Hiệu năng & độ tin cậy
- Dải IP Google (AdsBot/Googlebot) tải bằng WP-Cron hằng ngày, không còn gọi mạng trên request của khách (trước đây có thể treo tới 20 giây).
- Khoá file trong suốt đọc–sửa–ghi log (không mất dòng khi nhiều click cùng lúc); chỉ duyệt các dòng 10 phút gần nhất khi gộp lượt trùng.
- Click gọi/Zalo gửi bằng `navigator.sendBeacon` (fallback `fetch keepalive`) – không mất log khi trang chuyển sang Zalo.
- Không cache lỗi GA4 API (trước đây lỗi tạm thời bị giữ 15 phút / 6 tiếng).
- Google Ads: báo lỗi tiếng Việt kèm hướng dẫn khi Cloud project chưa có Explorer access (`CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION`), không thử chặn từng chiến dịch vô ích trong trường hợp này.
- Thẻ "Tỷ lệ thoát": tăng hiển thị màu đỏ (trước đây xanh).
