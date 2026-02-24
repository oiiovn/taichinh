# AI Learning Doc — Tài liệu AI / Học máy

## 1. Mục đích

Tài liệu mô tả cách hệ thống dùng **AI / học máy**: phân loại giao dịch (GPT), cognitive layer cho Insight, payload, phản hồi người dùng và fallback khi thiếu dữ liệu.

## 2. Phạm vi AI trong hệ thống

| Thành phần | Vai trò AI | Loại |
|------------|------------|------|
| **Insight / Narrative** | Tạo nội dung phân tích, gợi ý chiến lược | LLM (GPT) với payload có cấu trúc; cognitive layer tổng hợp (FINANCIAL_USE_COGNITIVE_LAYER) |
| **Phân loại giao dịch** | Gán danh mục / merchant group cho giao dịch mới | Rule-based (UserMerchantRule, GlobalMerchantPattern) + tùy chọn GPT (TransactionClassificationGptService) khi GPT_CLASSIFICATION_ENABLED=true |
| **Recurring detection** | Nhận diện giao dịch lặp (lương, subscription) | Rule/heuristic (tần suất, khoảng ngày, độ lệch số tiền) — RECURRING_* trong .env |

## 3. Luồng dữ liệu cho AI

### 3.1 Input (Payload Insight)

- **Nguồn:** InsightPayloadService (và pipeline TaiChinhIndexViewDataBuilder) chuẩn bị payload cho LLM/cognitive layer.
- **Thành phần chính:** canonical (runway, buffer, thu/chi/nợ), structural_metrics (context_aware_buffer_components, risk), debt_intelligence (debt_priority_list, priority_alignment), cognitive_input (mục tiêu, strategy profile, constraints). DataSufficiencyService kiểm tra đủ dữ liệu trước; không đủ thì không gọi AI, trả onboarding narrative.

### 3.2 Output (Kỳ vọng)

- Narrative insight (đoạn văn), bullet gợi ý, cảnh báo. Định dạng tùy cognitive layer (text/JSON); hiển thị trên tab Chiến lược. Cache trong `financial_insight_ai_cache` (TTL theo FINANCIAL_INSIGHT_AI_CACHE_TTL_HOURS).

### 3.3 Gọi API ngoài

- **OpenAI:** Phân loại giao dịch (TransactionClassificationGptService) và/hoặc cognitive layer insight. Env: OPENAI_API_KEY, OPENAI_MODEL (ví dụ gpt-4o-mini). Không ghi API key vào doc/code; rate limit/retry/timeout theo client (FINANCIAL_COGNITIVE_TIMEOUT cho insight).

## 4. Học từ phản hồi (Learning Loop)

### 4.1 Thu thập phản hồi

- **Bảng:** `financial_insight_feedback` (user_id, snapshot/insight liên quan, action: thumbs up/down, có thể comment). Route POST `/tai-chinh/insight-feedback`.

### 4.2 Cách dùng phản hồi

- Hiện dùng cho thống kê / đánh giá chất lượng; có thể dùng sau để tinh chỉnh prompt hoặc ưu tiên loại insight. Chưa train lại mô hình tự động.

### 4.3 Đạo đức & Kiểm soát

- Không gửi dữ liệu nhạy cảm (số tài khoản đầy đủ, token) vào API bên ngoài ngoài nội dung cần thiết cho phân loại/insight. Có thể ẩn danh/aggregate khi gửi.

## 5. Chất lượng & Đánh giá

- **Metric:** Tỷ lệ thumbs up/down (từ financial_insight_feedback). Số lần user chỉnh/sửa insight nếu có.
- **Fallback:** Khi không đủ dữ liệu: DataSufficiencyService trả narrative cố định (“Chưa đủ dữ liệu… Liên kết tài khoản…”). Khi API AI lỗi/timeout: có thể trả template cố định hoặc ẩn block insight (tùy implementation).

## 6. Biến môi trường liên quan

| Key | Mô tả |
|-----|-------|
| GPT_CLASSIFICATION_ENABLED | Bật/tắt phân loại giao dịch bằng GPT |
| OPENAI_API_KEY, OPENAI_MODEL | API OpenAI |
| GPT_CLASSIFICATION_CONFIDENCE_THRESHOLD, CACHE_DAYS | Ngưỡng tin cậy, cache phân loại |
| FINANCIAL_USE_COGNITIVE_LAYER | Bật cognitive layer cho Insight |
| FINANCIAL_COGNITIVE_CONFIDENCE_THRESHOLD, FINANCIAL_INSIGHT_AI_CACHE_TTL_HOURS, FINANCIAL_COGNITIVE_TIMEOUT | Ngưỡng, TTL cache, timeout gọi AI |

## 7. Changelog AI

| Ngày | Thay đổi |
|------|----------|
| 2026-02-24 | Viết lại AI Learning Doc: phân loại GPT, cognitive layer, payload, feedback, fallback, env. |

---
*Cập nhật lần cuối: 2026-02-24.*
