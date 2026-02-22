# Báo cáo tổng thể: Brain cấp 5 – Hạng mục 1 & 4

## Tổng quan

Đã triển khai **1. Context-aware Buffer** và **4. Multi-factor Debt Priority**, tích hợp vào luồng projection và insight hiện có.

---

## 1. Context-aware Buffer

### Mục tiêu
Buffer (số tháng runway khuyến nghị) **động** theo:
- **Income volatility**: thu dao động → cần buffer dài hơn.
- **Debt pressure**: nợ / trả nợ cao → cần buffer an toàn hơn.
- **Fixed cost ratio**: chi cố định (expense + debt service) / thu cao → khó cắt giảm nhanh, cần buffer.

### Triển khai

**Service mới:** `App\Services\ContextAwareBufferService`

- **Method:** `recommend(array $canonical, array $position): array`
- **Công thức:**  
  `recommended_runway_months = base(3) + volatility_add + debt_pressure_add + fixed_cost_add`  
  Clamp trong **[2, 12]** tháng.

| Thành phần | Logic (tóm tắt) |
|------------|------------------|
| **volatility_add** | Dựa trên `volatility_ratio`, `income_stability_score`: ổn định → 0; rất dao động → +3. |
| **debt_pressure_add** | Dựa trên debt service / thu, DTI: không nợ → 0; nợ rất cao → +3. |
| **fixed_cost_add** | (expense + debt service) / thu ≥ 95% → +2; ≥ 85% → +1; ≥ 75% → +0.5. |

**Tích hợp:**
- **CashflowProjectionService::run()**: Sau khi có `canonical` tạm (với `required_runway_months` cũ 3/6), gọi `ContextAwareBufferService::recommend($canonicalRaw, $position)` → ghi đè `required_runway_months` và thêm `context_aware_buffer_components` vào `canonical`.
- **InsightPayloadService**: `structural_metrics` có thêm `context_aware_buffer_components` (base, volatility_add, debt_pressure_add, fixed_cost_add) để GPT / frontend dùng.

**Kết quả:** Mọi nơi dùng `required_runway_months` (risk scoring, so_cụ_thể, narrative, v.v.) giờ dùng **số tháng buffer theo ngữ cảnh** thay cho cố định 3 hoặc 6.

---

## 2. Multi-factor Debt Priority

### Mục tiêu
- Thêm **concentration** (tỷ trọng từng khoản so với tổng nợ) và **psychological** (ưu tiên nhẹ khoản nhỏ khi safety / sensitivity cao).
- **Strategy alignment**: So thứ tự ưu tiên hiện tại với mục tiêu (debt_repayment / safety / accumulation / investment); nếu lệch thì gợi ý hướng đi (alternative_first_name, suggested_direction).

### Triển khai

**Cập nhật:** `App\Services\DebtPriorityService`

**Trọng số mới (tổng = 1):**
- Interest: 0.35  
- Urgency: 0.30  
- Size: 0.20  
- **Concentration: 0.10** (khoản chiếm tỷ trọng lớn → trả để giảm rủi ro tập trung).  
- **Psychological: 0.05** (khoản nhỏ &lt; 20% tổng nợ được cộng điểm khi objective = safety hoặc sensitivity_to_risk = high).

**Điều chỉnh theo objective / strategy profile:**
- `safety` hoặc `sensitivity_to_risk === 'high'`: tăng nhẹ urgency, giảm rate; bật “snowball” (psychological) cho khoản nhỏ.
- Mỗi item trả về thêm **concentration_ratio** (outstanding / total).

**API thay đổi:**
- `rankDebts(Collection $oweItems, ?array $objective = null, ?array $strategyProfile = null): array`  
  Trả về:  
  `['list' => array, 'priority_alignment' => ['aligned' => bool, 'suggested_direction' => string, 'alternative_first_name' => ?string]]`
- `getMostUrgent()` / `getMostExpensive()` dùng `rankDebts(...)['list']`.

**Strategy alignment:**
- **debt_repayment**: Kỳ vọng thứ nhất là “lãi cao nhất” hoặc “đáo hạn sớn nhất”. Nếu khác → gợi ý trả lãi cao hoặc đáo hạn sớn trước.
- **safety**: Kỳ vọng thứ nhất là “đáo hạn sớn nhất”. Nếu khác → gợi ý ưu tiên khoản đáo hạn sớn để giảm rủi ro thanh khoản.
- **accumulation / investment**: Kỳ vọng thứ nhất là “lãi cao nhất”. Nếu khác → gợi ý trả lãi cao trước để tối ưu chi phí.

**Tích hợp:**
- **InsightPayloadService::buildDebtIntelligence()**: Gọi `rankDebts($oweItems, $optimization['objective'], $strategyProfile)`; đưa `list` vào `debt_priority_list`, `priority_alignment` vào payload và `cognitive_input.debt_intelligence.priority_alignment`.
- **View insight-ai.blade.php**: Khi `priority_alignment.aligned === false` và có `suggested_direction`, hiển thị gợi ý trong khối “Nợ & Ưu tiên trả”.

**Kết quả:** Ưu tiên trả nợ đa nhân tố (rate + urgency + penalty + size + concentration + psychological), có kiểm tra và gợi ý căn chỉnh theo mục tiêu chiến lược.

---

## 3. Luồng dữ liệu (tóm tắt)

1. **TaiChinhController::index()** → projection (CashflowProjectionService) → trong đó dùng **ContextAwareBufferService** để set `required_runway_months` và `context_aware_buffer_components`.
2. Cùng request → **InsightPayloadService::build()** → **buildDebtIntelligence()** gọi **DebtPriorityService::rankDebts()** với objective + strategyProfile → payload có `debt_priority_list`, `priority_alignment`, và các trường debt intelligence khác.
3. **structural_metrics** có `context_aware_buffer_components`; **cognitive_input.debt_intelligence** có `priority_alignment` và top3 priority list.
4. Tab Chiến lược (insight-ai) hiển thị DSI, most urgent/expensive, shock, capital_misallocation, và **suggested_direction** khi priority chưa aligned.

---

## 4. File thay đổi / thêm mới

| File | Thay đổi |
|------|----------|
| `app/Services/ContextAwareBufferService.php` | **Mới** – buffer động theo volatility, debt pressure, fixed cost. |
| `app/Services/CashflowProjectionService.php` | Gọi ContextAwareBufferService sau khi có canonical; ghi đè required_runway_months, thêm context_aware_buffer_components. |
| `app/Services/DebtPriorityService.php` | rankDebts đa nhân tố (concentration, psychological), thêm objective/strategyProfile, trả về priority_alignment; getMostUrgent/getMostExpensive dùng ['list']. |
| `app/Services/InsightPayloadService.php` | buildDebtIntelligence nhận strategyProfile; gọi rankDebts với objective + strategyProfile; payload + cognitive_input có priority_alignment và context_aware_buffer_components. |
| `resources/views/pages/tai-chinh/partials/chien-luoc/insight-ai.blade.php` | Hiển thị suggested_direction khi priority_alignment.aligned = false. |

---

## 5. Còn lại (Brain cấp 5)

- **2. Capital Allocation Engine**: Phân bổ surplus → Giữ / Trả nợ / Đầu tư (số hoặc %). Chưa triển khai.
- **3. Action Ladder trong Crisis**: Cấu trúc Cắt X, Hoãn Y, Thương lượng Z, Refinance A. Chưa triển khai.

Sau khi hoàn thành 1 & 4, hệ đã có **buffer theo ngữ cảnh** và **ưu tiên nợ đa nhân tố + alignment với mục tiêu**, sẵn sàng cho bước tiếp theo (2 & 3) khi cần.
