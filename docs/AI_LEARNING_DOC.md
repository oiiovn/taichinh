# AI Learning Doc — Tài liệu Học máy / AI

## 1. Mục đích

Tài liệu mô tả cách hệ thống sử dụng **AI / học máy**: mô hình, dữ liệu huấn luyện, input/output, và quy tắc cập nhật (learning loop).

## 2. Phạm vi AI trong hệ thống

| Thành phần | Vai trò AI | Loại (rule-based / ML / LLM) |
|------------|------------|------------------------------|
| Insight / Narrative | Tạo nội dung phân tích, gợi ý | [Ví dụ: LLM (GPT) với payload có cấu trúc] |
| Phân loại giao dịch | [Mô tả] | [Classifier / rule] |
| [Tên khác] | [Mô tả] | [Loại] |

## 3. Luồng dữ liệu cho AI

### 3.1 Input (Payload)
- **Nguồn**: InsightPayloadService (hoặc tương đương) chuẩn bị payload cho LLM.
- **Thành phần chính** (tham chiếu kỹ thuật):
  - canonical (runway, buffer, thu/chi/nợ)
  - structural_metrics (context_aware_buffer_components, risk, v.v.)
  - debt_intelligence (debt_priority_list, priority_alignment)
  - cognitive_input (mục tiêu, strategy profile, constraints)
  - [Các khối khác]

### 3.2 Output (Kỳ vọng)
- [Ví dụ: Đoạn văn insight, bullet gợi ý, cảnh báo.]
- [Định dạng: JSON / plain text; cách parse.]

### 3.3 Gọi API ngoài (nếu có)
- [Vendor, endpoint, model name; không ghi API key.]
- [Rate limit, retry, timeout.]

## 4. Học từ phản hồi (Learning Loop)

### 4.1 Thu thập phản hồi
- **Bảng / nguồn**: [Ví dụ: FinancialInsightFeedback, UserBehaviorPattern.]
- **Dữ liệu lưu**: [user_id, insight_id, action (thumbs up/down, chỉnh sửa), metadata.]

### 4.2 Cách dùng phản hồi
- [Mô tả: dùng để tinh chỉnh prompt, train mô hình, hoặc chỉ thống kê.]
- [Tần suất cập nhật: realtime / batch hàng ngày.]

### 4.3 Đạo đức & Kiểm soát
- [Không dùng dữ liệu nhạy cảm để train mà không được phép.]
- [Quy trình xóa / ẩn danh khi cần.]

## 5. Chất lượng & Đánh giá

- **Metric**: [Ví dụ: tỷ lệ thumbs up, số lần người dùng chỉnh insight.]
- **A/B test**: [Nếu có: mô tả ngắn.]
- **Fallback**: [Khi API AI lỗi: trả về template cố định hay ẩn block.]

## 6. Phiên bản prompt / model

| Phiên bản | Ngày | Thay đổi (tóm tắt) |
|-----------|------|---------------------|
| v1 | [YYYY-MM-DD] | [Mô tả] |

## 7. Changelog AI

| Ngày | Thay đổi |
|------|----------|
| [YYYY-MM-DD] | [Tóm tắt] |

---
*Cập nhật lần cuối: [YYYY-MM-DD].*
