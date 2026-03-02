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

## 6. Transaction Classification Engine v3 (CLASSIFICATION_V3_ENABLED=true)

- **Luồng:** Thu thập đa candidate (rule, behavior, recurring, global, GPT/keyword) → Unified scoring (0.4×source_weight + 0.3×historical_accuracy + 0.2×pattern_stability + 0.1×contextual_alignment) → Điều chỉnh theo anomaly (z-score amount), entropy merchant, recurring date drift → Chọn candidate có final_score cao nhất → Ghi `classification_meta` (candidate_scores, anomaly_flag, entropy, final_reason).
- **Historical accuracy:** Bảng `classification_accuracy_by_source` (user_id, source, usage_count, wrong_count). Khi áp dụng nguồn → tăng usage_count; khi user sửa danh mục → recordWrong (wrong_count++, GlobalMerchantPattern nếu là global/AI).
- **GlobalMerchantPattern:** Thêm correct_count, wrong_count, last_used_at, last_wrong_at, decay_factor. Confidence = accuracy × log(usage_count + 1) / 5 (không còn chỉ usage-based).
- **GPT cache v2:** Key có semantic_hash + version; cache_confidence, cache_created_at; effective_confidence = cache_confidence × exp(-λ × days_since_cached).
- **Recurring:** interval_variance, amount_cv, miss_streak; confidence = 0.5×interval_stability + 0.3×amount_stability + 0.2×streak_consistency.

## 7. Biến môi trường liên quan

| Key | Mô tả |
|-----|-------|
| GPT_CLASSIFICATION_ENABLED | Bật/tắt phân loại giao dịch bằng GPT |
| OPENAI_API_KEY, OPENAI_MODEL | API OpenAI |
| GPT_CLASSIFICATION_CONFIDENCE_THRESHOLD, CACHE_DAYS | Ngưỡng tin cậy, cache phân loại |
| FINANCIAL_USE_COGNITIVE_LAYER | Bật cognitive layer cho Insight |
| FINANCIAL_COGNITIVE_CONFIDENCE_THRESHOLD, FINANCIAL_INSIGHT_AI_CACHE_TTL_HOURS, FINANCIAL_COGNITIVE_TIMEOUT | Ngưỡng, TTL cache, timeout gọi AI |
| CLASSIFICATION_V3_ENABLED, CLASSIFICATION_CACHE_DECAY_LAMBDA, CLASSIFICATION_ANOMALY_Z_THRESHOLD, CLASSIFICATION_MIN_FINAL_SCORE | Engine v3: bật, decay cache, ngưỡng anomaly, điểm tối thiểu áp dụng |

## 8. Changelog AI

| Ngày | Thay đổi |
|------|----------|
| 2026-02-24 | Viết lại AI Learning Doc: phân loại GPT, cognitive layer, payload, feedback, fallback, env. |
| 2026-03-02 | Transaction Classification Engine v3: multi-candidate scoring, unified confidence, accuracy by source, GPT cache decay, anomaly/entropy, classification_meta. |

---
*Cập nhật lần cuối: 2026-03-02.*
