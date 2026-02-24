# Product Doc — Tài liệu Sản phẩm Taichinh

## 1. Tổng quan sản phẩm

| Mục | Nội dung |
|-----|----------|
| **Tên sản phẩm** | Taichinh |
| **Phiên bản** | 1.x (đang phát triển) |
| **Đối tượng người dùng** | Cá nhân quản lý tài chính, muốn tổng hợp tài khoản, nợ/vay, phân tích và gợi ý chiến lược. |
| **Mục đích** | Một nơi tổng hợp: liên kết tài khoản ngân hàng, theo dõi thu chi, nợ & khoản vay, đặt ngưỡng ngân sách & mục tiêu thu nhập; hệ thống phân tích và đưa ra nhận định, gợi ý chiến lược (Insight AI). Đồng thời hỗ trợ quản lý công việc (task, kanban) với behavior intelligence và (tùy chọn) cộng đồng nhóm (TribeOS). |

## 2. Phạm vi chức năng (Scope)

### 2.1 Module chính

| Module | Mô tả ngắn |
|--------|-------------|
| **Tài khoản ngân hàng** | Liên kết tài khoản qua Pay2s, đồng bộ giao dịch; quản lý tài khoản đã liên kết, cập nhật số dư. |
| **Thu chi** | Ghi nhận thu/chi thủ công; nguồn thu, mẫu giao dịch lặp (recurring); danh sách giao dịch, phân loại (rule + GPT tùy chọn). |
| **Nợ (Liability)** | Khai báo khoản nợ (trả góp, lãi), ghi thanh toán & phát sinh; đóng nợ. |
| **Khoản vay (Loan)** | Hợp đồng khoản vay (cá nhân/ngân hàng), sổ cái thanh toán, kỳ trả, xác nhận/từ chối giao dịch trả. |
| **Phân tích & Chiến lược** | Runway, buffer theo ngữ cảnh, ưu tiên trả nợ; tab Chiến lược: Insight AI (narrative, gợi ý), phản hồi thumbs up/down; projection dòng tiền; sự kiện dashboard (ngưỡng, số dư thấp). |
| **Ngưỡng ngân sách & Mục tiêu thu** | Đặt ngưỡng theo danh mục; mục tiêu thu theo tháng; ràng buộc chi theo danh mục (income goal). |
| **Công việc** | Task, nhãn, dự án, kanban; Behavior Intelligence (9 tầng: identity baseline, event, temporal, trust, projection, …); chương trình (program), gợi ý micro-goal / giảm nhắc. |
| **TribeOS** | Nhóm, bài đăng, bình luận, reaction, mời thành viên; bật theo feature flag. |
| **Gói hiện tại** | Xem gói đăng ký, thanh toán (tích hợp ngoài), hết hạn. |
| **Admin** | Trang quản trị: danh sách user, tạo/sửa user (middleware admin). |

### 2.2 Ngoài phạm vi (Out of scope)

- Kế toán doanh nghiệp, hóa đơn VAT.
- Giao dịch chứng khoán / crypto (chỉ mô tả nếu có tích hợp sau).
- Thay thế hoàn toàn app ngân hàng (chỉ tổng hợp, đồng bộ theo API Pay2s).

## 3. Persona & User Stories

### 3.1 Persona

| ID | Persona | Mục tiêu | Pain point |
|----|---------|----------|------------|
| P1 | Người quản lý tài chính cá nhân | Một dashboard xem hết thu chi, nợ, vay; nhận gợi ý ưu tiên trả nợ và chiến lược | Nhiều tài khoản, nhiều nợ, không biết nên trả cái nào trước |
| P2 | User có giao dịch lặp (lương, tiền nhà) | Hệ thống nhận diện và dự báo thu chi định kỳ | Phải tự ghi tay từng khoản lặp |

### 3.2 User Stories (mẫu)

- **US-001:** Là P1, tôi muốn liên kết tài khoản ngân hàng để giao dịch được đồng bộ tự động và đưa vào phân tích.
- **US-002:** Là P1, tôi muốn xem runway và buffer khuyến nghị để biết còn bao lâu an toàn thanh khoản.
- **US-003:** Là P1, tôi muốn nhận gợi ý ưu tiên trả nợ và narrative insight để quyết định trả khoản nào trước.
- **US-004:** Là P1, tôi muốn đặt ngưỡng chi theo danh mục và nhận cảnh báo khi vượt ngưỡng.
- **US-005:** Là P2, tôi muốn hệ thống nhận diện giao dịch lặp (lương, subscription) để dự báo chính xác hơn.

## 4. Luồng nghiệp vụ (Business Flows)

### 4.1 Luồng chính: Từ chưa có dữ liệu → Đủ dữ liệu → Insight

1. User đăng nhập → vào Tài chính (tab Chiến lược).
2. Nếu chưa liên kết tài khoản / chưa đủ giao dịch: hiển thị onboarding narrative (DataSufficiencyService): “Chưa đủ dữ liệu… Liên kết tài khoản… Khi đủ dữ liệu hệ thống sẽ phân tích…”.
3. User liên kết tài khoản (Pay2s), đồng bộ giao dịch; có thể thêm nợ/khoản vay thủ công.
4. Khi đủ điều kiện (số giao dịch, số tháng): hệ thống chạy pipeline (financial state, insight payload), gọi cognitive layer (nếu bật) → hiển thị narrative + gợi ý.
5. User có thể phản hồi insight (thumbs up/down), cập nhật ngưỡng, mục tiêu thu.

### 4.2 Luồng phụ

- **Thu chi thủ công:** Vào Thu chi → thêm thu/chi, nguồn thu, mẫu lặp.
- **Nợ/Vay:** Tab hoặc route riêng → tạo nợ/vay → ghi thanh toán / sổ cái.
- **Công việc:** Vào Công việc → tạo task, kéo kanban; tick hoàn thành (có thể qua gate P(real) → xác nhận); nhận gợi ý behavior (micro-goal, giảm nhắc).

## 5. Yêu cầu chức năng (Functional Requirements) — tóm tắt

| ID | Yêu cầu | Ưu tiên |
|----|---------|---------|
| FR-001 | Liên kết tài khoản ngân hàng (Pay2s), đồng bộ giao dịch | P0 |
| FR-002 | Hiển thị giao dịch, phân loại (rule + GPT tùy chọn) | P0 |
| FR-003 | Quản lý nợ & khoản vay, thanh toán, sổ cái | P0 |
| FR-004 | Tính runway, buffer, ưu tiên trả nợ; hiển thị Insight AI khi đủ dữ liệu | P0 |
| FR-005 | Ngưỡng ngân sách, mục tiêu thu, cảnh báo vượt ngưỡng | P1 |
| FR-006 | Thu chi thủ công, recurring | P1 |
| FR-007 | Công việc (task, kanban), Behavior Intelligence | P1 |
| FR-008 | TribeOS (nhóm, bài đăng) — feature flag | P2 |
| FR-009 | Admin: CRUD user | P1 |

## 6. Yêu cầu phi chức năng (NFR)

| ID | Loại | Mô tả | Tiêu chí chấp nhận |
|----|------|-------|--------------------|
| NFR-001 | Hiệu năng | Trang tài chính load trong thời gian chấp nhận được | Tránh query N+1; cache insight theo TTL |
| NFR-002 | Khả dụng | Ứng dụng web responsive | Dùng Tailwind responsive, mobile-first |
| NFR-003 | Bảo mật | User chỉ xem/sửa dữ liệu của mình | Scope theo user_id; middleware auth, admin |

## 7. Giao diện & UX

### 7.1 Màn hình chính (route tiêu biểu)

- `/` → redirect Tài chính (tab Chiến lược)
- `/tai-chinh` — Tài chính (tab: Chiến lược, Tài khoản, Nợ/Khoản vay, Thu chi, Ngưỡng, Mục tiêu thu, …)
- `/thu-chi` — Thu chi thủ công
- `/cong-viec` — Công việc (task, kanban, behavior)
- `/tribeos` — TribeOS (feed, nhóm)
- `/goi-hien-tai` — Gói hiện tại
- `/admin`, `/admin/users` — Quản trị (admin)

### 7.2 Quy tắc UX

- Khi chưa đủ dữ liệu: không hiển thị số runway/insight “ảo”; hiển thị narrative onboarding rõ ràng.
- Insight: có nút phản hồi (thumbs) để học sau này.
- Thông báo lỗi/validation nhất quán (Blade + Alpine).

## 8. Changelog sản phẩm

| Phiên bản | Ngày | Thay đổi chính |
|-----------|------|----------------|
| 1.0 | 2026-02-24 | Viết lại Product Doc đầy đủ: scope, module, user stories, luồng, FR/NFR, màn hình. |

---
*Cập nhật lần cuối: 2026-02-24.*
