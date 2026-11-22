# XD Analytics (vinhxd-ga4)

Plugin WordPress của [Vĩnh XD](https://vinhxd.com): dashboard GA4, nhật ký chuyển đổi (gọi/Zalo/form), lỗi 404, lượt xem bài, Google Ads, và từ **1.15.0** có thêm **Traffic AI**.

- Thư mục plugin: `vinhxd-ga4/` (slug giữ nguyên để cập nhật đè được bản cũ)
- Yêu cầu: WordPress 5.8+, PHP 7.4+
- Đóng gói: folder trong zip tên trơn `vinhxd-ga4/`, chỉ tên file zip mang version (`vinhxd-ga4-v1.15.0.zip`)

---

## Traffic AI – ghi chú cho các agent (SEOto, Content, …)

### 1. Nó đếm cái gì
| Mục | Ý nghĩa |
|---|---|
| **Lượt vào từ AI** | Khách người thật bấm link từ câu trả lời của ChatGPT, Gemini, Perplexity, Claude, Copilot, DeepSeek, Grok, Meta AI, Mistral Le Chat, You.com, Phind, Poe, Qwen, Kimi, HuggingChat, Pi, Duck.ai. Gộp trùng: cùng khách + cùng trang + cùng nguồn trong 30 phút = 1 lượt. |
| **Khách (ước tính)** | Số visitor khác nhau, tính theo hash(IP + trình duyệt + ngày). Đổi mỗi ngày → khoảng nhiều ngày sẽ đếm 1 người nhiều lần. Không lưu IP. |
| **Bot AI crawl** | Lượt bot của AI đọc web (GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-User, PerplexityBot, Perplexity-User, Bytespider, CCBot, Meta-ExternalAgent, Amazonbot, Applebot-Extended, DuckAssistBot, MistralAI-User…). Bảng riêng, KHÔNG cộng vào lượt khách. |

Mỗi lượt lưu: thời gian, nguồn, tín hiệu nhận diện (`referrer` hay `utm`), cách ghi (`js` hay `server`), referrer (chỉ `https://host/path`, bỏ query vì có thể chứa câu hỏi của khách), utm_source/medium/campaign, trang đích (path + query, đã bỏ fbclid/gclid), thiết bị, User-Agent.

### 2. Xem ở đâu
wp-admin → **XD Analytics → Traffic AI** (`/wp-admin/admin.php?page=vinhxd-ga4-ai`), cần quyền admin.
- Bộ lọc: Hôm nay / 7 / 30 / 90 / 365 ngày / Tuỳ chọn (từ ngày → đến ngày).
- 4 thẻ tổng, biểu đồ cột chồng theo ngày (mỗi màu 1 nguồn), bảng theo nguồn (lượt, khách, tỷ trọng), top 20 trang đích kèm nguồn, 50 lượt gần nhất, bảng bot AI + top URL bị bot đọc.
- **Tải CSV**: nút cạnh bộ lọc, xuất đúng khoảng ngày đang xem (UTF-8 có BOM, mở Excel được). Cột: `time_local, day, source, source_label, signal, method, referrer, referrer_host, utm_source, utm_medium, utm_campaign, landing, device, user_agent, visitor_hash`.

### 3. Đọc số liệu cho đúng
- Số ở đây là **mức tối thiểu**. Traffic AI thật luôn cao hơn.
- Dùng để so sánh **xu hướng** (tuần này vs tuần trước, nguồn nào tăng) và xem **trang nào được AI dẫn về** → đó là nội dung AI đang trích dẫn, nên giữ cập nhật/mở rộng.
- Trang có nhiều **bot AI crawl** nhưng ít **lượt khách từ AI** = AI đọc nhưng chưa dẫn link → xem lại tiêu đề, dữ liệu cụ thể (giá, thông số, bảng), FAQ.
- So với GA4: GA4 có thể xếp các lượt này vào Referral (chatgpt.com…) hoặc theo utm_source; con số không khớp tuyệt đối vì GA4 bị chặn bởi adblock/cookie consent còn plugin thì gộp trùng 30 phút.
- Cột `signal`: `referrer` = trình duyệt báo trang trước là AI; `utm` = link có `utm_source` của AI (ChatGPT/Perplexity tự gắn `utm_source=chatgpt.com`…).
- Cột `method`: `js` = ghi bằng beacon (bình thường, kể cả trang cache); `server` = ghi phía PHP khi trang không lấy từ cache.

### 4. Giới hạn (nói rõ khi báo cáo)
1. **App không gửi referrer**: app ChatGPT/Gemini/Claude trên điện thoại, desktop app, hoặc khách copy link → không có referrer/utm → không thể biết từ AI, GA4 cũng xếp vào Direct.
2. **Google AI Overviews / AI Mode**: Google gửi referrer `google.com` giống hệt tìm kiếm thường; đoạn `#:~:text=` trong link không tới được server và trình duyệt giấu khỏi JS → **không tách được**, đang nằm trong Organic Search. Ước lượng bằng Search Console. Plugin không đoán bừa.
3. **Copilot trong Bing**: chỉ nhận khi referrer là `bing.com/chat…` hoặc `copilot.microsoft.com`; nếu Bing chỉ gửi `bing.com` thì không phân biệt được với Bing search.
4. **Cache + chặn JS**: ghi chính bằng JS beacon (chạy cả trên trang cache LiteSpeed). Khách tắt JS/adblock chặn file `ai-traffic.js` → chỉ ghi được nếu trang không lấy từ cache (fallback server).
5. **Bot AI**: chỉ đếm request tới được PHP. Trang được LiteSpeed/CDN trả từ cache thì bot không bị đếm → con số crawl là mức tối thiểu. `Google-Extended` không phải User-Agent thật (Google dùng Googlebot) nên không đếm riêng được.
6. **Referrer policy**: hầu hết AI chỉ gửi origin (`https://chatgpt.com/`), không có đường dẫn cuộc chat → không biết khách hỏi gì.
7. Admin/editor/author đang đăng nhập không được đếm (tắt được). Bot/UA lạ bị lọc bằng bộ lọc bot chung của XD Analytics.

### 5. Thêm / sửa nguồn AI
wp-admin → Traffic AI → **Cài đặt → Danh sách nguồn AI**, mỗi dòng:
```
key | Tên hiển thị | host1, host2/duong-dan | utm1, utm2
```
Ví dụ: `zalo_ai | Zalo AI | zalo.ai | zaloai`
- Host khớp cả subdomain (`perplexity.ai` khớp `www.perplexity.ai`). Có thể kèm path (`bing.com/chat`).
- utm_source so khớp nguyên giá trị, không phân biệt hoa thường.
- Đừng đổi `key` của nguồn đang có số liệu (số cũ sẽ hiện dưới key cũ). Tick "Khôi phục danh sách mặc định" để reset.
- Dev: filter `vinhxd_ai_sources` (mảng nguồn), `vinhxd_ai_crawlers` (mảng bot), action `vinhxd_ai_visit_logged` (sau khi ghi 1 lượt).

### 6. Cài đặt khác
Bật/tắt ghi, bỏ qua người quản trị, bật/tắt ghi bot AI, số ngày giữ dữ liệu (mặc định 180, cron xoá hằng ngày; 0 = giữ mãi), nút xoá toàn bộ dữ liệu Traffic AI.

### 7. Kỹ thuật (cho dev)
- Code: `includes/class-ai-traffic.php`, `assets/js/ai-traffic.js` (beacon ~1KB), `assets/js/ai-traffic-admin.js` (biểu đồ, dùng Chart.js có sẵn trong `assets/js/vendor/`, không CDN).
- Bảng: `{prefix}vinhxd_ai_visits`, `{prefix}vinhxd_ai_crawls` (dbDelta, option `vinhxd_ai_db_version`). Cài đặt: option `vinhxd_ai_settings`, `vinhxd_ai_rules`. Cron: `vinhxd_ai_cleanup`.
- Endpoint beacon: `admin-ajax.php?action=vinhxd_ai_hit` (POST `ref`, `url`). Không dùng nonce (trang cache giữ nonce hết hạn) – thay bằng: nguồn được tính lại phía server, trang đích phải cùng domain, kiểm tra header Origin, lọc bot/admin, giới hạn 20 lượt/visitor/10 phút, gộp trùng 30 phút. Trả JSON `{"r":"logged|duplicate|not_ai|invalid|limited|skip"}`.
- Beacon chỉ gửi request khi referrer/utm khớp danh sách → khách thường không tốn request nào.
- Gỡ plugin không xoá bảng (giữ số liệu khi cập nhật đè).
