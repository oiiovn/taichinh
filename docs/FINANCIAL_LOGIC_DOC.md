# Financial Logic Doc — Tài liệu Logic Tài chính

## 1. Mục đích

Tài liệu này mô tả **logic tài chính** (công thức, quy tắc, ngưỡng) dùng trong hệ thống để đảm bảo tính nhất quán, kiểm toán được và dễ bảo trì.

## 2. Định nghĩa & Thuật ngữ

| Thuật ngữ | Định nghĩa |
|-----------|------------|
| **Runway** | Số tháng có thể duy trì chi tiêu với số dư hiện tại (không thu thêm). |
| **DTI** | Debt-to-Income: Tổng trả nợ / Thu nhập. |
| **Buffer** | Số tháng runway khuyến nghị để an toàn thanh khoản. |
| **Debt service** | Tổng chi trả nợ định kỳ (lãi + gốc theo kỳ). |
| [Khác] | [Định nghĩa] |

## 3. Công thức & Quy tắc chính

### 3.1 Runway (số tháng tồn tại)
- **Công thức cơ bản**: `runway_months = liquid_balance / net_burn_per_month`
  - `net_burn = (expense + debt_service) - income` (khi burn dương).
- **Xử lý biên**: Thu ≥ Chi → runway có thể coi là “vô hạn” hoặc giá trị đặc biệt theo quy ước hệ thống.

### 3.2 Context-aware Buffer (số tháng runway khuyến nghị)
- **Công thức**:  
  `recommended_runway_months = base(3) + volatility_add + debt_pressure_add + fixed_cost_add`  
  **Clamp**: [2, 12] tháng.

| Thành phần | Cách tính (tóm tắt) |
|------------|----------------------|
| **volatility_add** | Từ volatility_ratio, income_stability_score: ổn định → 0; dao động mạnh → tối đa +3. |
| **debt_pressure_add** | Từ debt service / thu, DTI: không nợ → 0; nợ cao → tối đa +3. |
| **fixed_cost_add** | (expense + debt service) / thu ≥ 95% → +2; ≥ 85% → +1; ≥ 75% → +0.5. |

- **Nguồn**: ContextAwareBufferService; tích hợp trong CashflowProjectionService và InsightPayloadService.

### 3.3 Ưu tiên trả nợ (Debt Priority)
- **Trọng số tổng = 1**: Interest 0.35, Urgency 0.30, Size 0.20, Concentration 0.10, Psychological 0.05.
- **Concentration**: tỷ trọng khoản nợ so với tổng nợ (giảm rủi ro tập trung).
- **Psychological**: tăng điểm cho khoản nhỏ (< 20% tổng nợ) khi objective = safety hoặc sensitivity_to_risk = high (hiệu ứng “snowball”).
- **Strategy alignment**: So thứ tự ưu tiên với mục tiêu (debt_repayment / safety / accumulation / investment); trả về aligned + suggested_direction / alternative_first_name khi lệch.
- **Nguồn**: DebtPriorityService; dùng trong InsightPayloadService và view insight.

### 3.4 Ngưỡng rủi ro (Risk / Ngân sách)
- **Budget threshold:** Người dùng đặt ngưỡng chi theo danh mục (bảng `budget_thresholds`); sự kiện vượt ngưỡng ghi `budget_threshold_events`. Cảnh báo trên dashboard.
- **Low balance:** User có thể cấu hình ngưỡng số dư thấp (`users.low_balance_threshold`); dùng cho sự kiện/cảnh báo.
- **Adaptive threshold:** AdaptiveThresholdService, metric người dùng (volatility, DTI, …) ảnh hưởng ngưỡng/buffer (xem 3.2).
- **Nguồn:** BudgetThresholdService, AdaptiveThresholdService, DualAxisAwarenessService.

## 4. Nguồn dữ liệu đầu vào

| Dữ liệu | Nguồn (Model/Service/Input) |
|---------|-----------------------------|
| Thu nhập | canonical income (từ giao dịch đã phân loại), estimated_incomes, income_goals |
| Chi tiêu | canonical expense, transaction_history (phân loại), estimated_expenses |
| Nợ & trả nợ | user_liabilities, liability_payments, liability_accruals; loan_contracts, loan_ledger_entries (debt service) |
| Số dư thanh khoản | user_bank_accounts (số dư), position / liquid balance từ service tài chính |
| Giao dịch | transaction_history (đồng bộ Pay2s + phân loại rule/GPT) |

## 5. Làm tròn & Đơn vị

- **Tiền tệ:** VND; làm tròn theo nhu cầu hiển thị (thường nguyên hoặc 0 chữ số thập phân cho VND).
- **Thời gian:** Tháng (runway, buffer); ngày (đáo hạn nợ, giao dịch).
- **Tỷ lệ:** Giữ 2–4 chữ số thập phân cho %, lãi suất (tùy config).

## 6. Ngoại lệ & Biên

- **Runway âm hoặc vô hạn:** Thu ≥ Chi → runway có thể coi “vô hạn” hoặc giá trị đặc biệt; Runway âm (burn dương, hết tiền) → hiển thị theo quy ước UI (ví dụ “0 tháng” hoặc cảnh báo).
- **Thiếu dữ liệu:** DataSufficiencyService kiểm tra số giao dịch, số tháng, có liên kết tài khoản/nợ; khi không đủ → short-circuit, hiển thị onboarding narrative (“Chưa đủ dữ liệu…”), không tính runway/insight để tránh overconfident.

## 7. Changelog logic tài chính

| Ngày | Thay đổi |
|------|----------|
| 2026-02-24 | Điền nguồn dữ liệu, ngưỡng rủi ro, ngoại lệ & biên; tham chiếu service. |

---
*Cập nhật lần cuối: 2026-02-24. Tham chiếu: BAO_CAO_BRAIN_CAP_5_1_4.md (nếu áp dụng).*
