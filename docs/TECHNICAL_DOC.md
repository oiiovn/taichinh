# Technical Doc — Tài liệu Kỹ thuật

## 1. Tổng quan kiến trúc

### 1.1 Stack công nghệ
| Thành phần | Công nghệ | Ghi chú |
|------------|-----------|---------|
| Backend | Laravel 12 | PHP 8.2+ (khuyến nghị 8.3+ cho test) |
| Frontend | Blade + Tailwind CSS v4 + Alpine.js | UI TailAdmin |
| Database | MySQL / MariaDB | DB_DATABASE=taichinh |
| Cache | database (CACHE_STORE) | Session, cache dùng bảng DB |
| Queue | Laravel Queue (database) | QUEUE_CONNECTION=database |
| Build | Vite | HMR dev, build production |

### 1.2 Sơ đồ kiến trúc (cao cấp)
```
[Client] → [Web Server] → [Laravel] → [DB / Cache / Queue]
                ↓
           [Static / Vite]
```

### 1.3 Thư mục / namespace chính
- `app/Http/Controllers` — Điều khiển HTTP
- `app/Services` — Logic nghiệp vụ
- `app/Models` — Eloquent
- `resources/views` — Blade
- `routes/` — Định tuyến

## 2. Luồng dữ liệu & API

### 2.1 Route chính (front — middleware auth)

| Method | URI | Mô tả |
|--------|-----|-------|
| GET | / | Redirect → tai-chinh?tab=chien-luoc |
| GET | /tai-chinh | TaiChinhController@index — Trang tài chính (tab) |
| GET | /tai-chinh/projection | projection dòng tiền |
| GET | /tai-chinh/insight-payload | Payload cho Insight (Ajax) |
| POST | /tai-chinh/insight-feedback | Lưu phản hồi thumbs up/down |
| GET | /tai-chinh/giao-dich-table | Bảng giao dịch (Ajax) |
| POST | /tai-chinh/tai-khoan, /tai-khoan/unlink | Liên kết / hủy liên kết TK |
| GET/POST | /tai-chinh/liability/* | Nợ: create, show, store, payment, accrual, close |
| GET/POST | /tai-chinh/loans/* | Khoản vay: index, create, store, show, payment, close, pending |
| GET/POST | /tai-chinh/nguong-ngan-sach* | Ngưỡng ngân sách |
| GET/POST | /tai-chinh/muc-tieu-thu* | Mục tiêu thu |
| GET | /thu-chi | ThuChiController — Thu chi thủ công |
| POST | /thu-chi/income, /thu-chi/expense | Thêm thu/chi |
| GET | /cong-viec | CongViecController — Công việc (task, kanban) |
| PATCH | /cong-viec/tasks/{id}/toggle-complete | Tick hoàn thành (có thể qua gate P(real)) |
| POST | /cong-viec/tasks/{id}/confirm-complete | Xác nhận hoàn thành (sau modal) |
| POST | /cong-viec/behavior-events | Gửi batch event behavior |
| GET/POST | /tribeos, /tribeos/groups/* | TribeOS (feature flag) |
| GET | /goi-hien-tai | Gói hiện tại |
| GET | /profile | Hồ sơ |
| GET | /admin, /admin/users/* | Admin (middleware admin) |

### 2.2 API nội bộ / Ajax (web)

- `GET /tai-chinh/insight-payload` — JSON payload cho cognitive layer / hiển thị insight.
- `POST /tai-chinh/insight-feedback` — Lưu feedback (thumbs up/down, optional comment).
- `GET /tai-chinh/giao-dich-table` — Bảng giao dịch (có thể phân trang, filter).
- `GET /notifications/unread-count` — Số thông báo chưa đọc.
- Các route `*table`, `*edit-json` trả fragment hoặc JSON cho load động.

### 2.3 API cho app mobile (REST, Sanctum)

**Base URL:** `/api` (prefix tự động). **Phiên bản:** `/api/v1`. **Auth:** Header `Authorization: Bearer <token>` (Laravel Sanctum).

| Method | URI | Auth | Mô tả |
|--------|-----|------|-------|
| POST | /api/v1/login | — | Body: `email`, `password`. Trả về: `token`, `token_type`, `user` (id, name, email). |
| POST | /api/v1/logout | Bearer | Thu hồi token hiện tại. |
| GET | /api/user | Bearer | User hiện tại (route mặc định Laravel). |
| GET | /api/v1/tai-chinh/insight-payload | Bearer | Payload 5 tầng + prompt (query: `scenarios=1` tùy chọn). |
| GET | /api/v1/tai-chinh/projection | Bearer | Projection dòng tiền (query: months, extra_income_per_month, expense_reduction_pct, …). |
| POST | /api/v1/tai-chinh/insight-feedback | Bearer | Body: insight_hash, feedback_type (agree\|infeasible\|incorrect\|alternative), reason_code, … |
| GET | /api/v1/tai-chinh/giao-dich | Bearer | Danh sách giao dịch JSON (query: page, per_page, stk, loai, q, category_id). Trả về: data, meta (current_page, last_page, total). |

**Cách dùng:** App mobile gửi POST `/api/v1/login` với email/password → nhận `token` → gửi kèm header `Authorization: Bearer {token}` cho mọi request sau. Model `User` dùng trait `HasApiTokens` (Sanctum).

### 2.4 Service layer
| Service | Trách nhiệm |
|---------|--------------|
| DataSufficiencyService | Kiểm tra đủ dữ liệu trước reasoning; onboarding narrative khi thiếu |
| UserFinancialContextService | Ngữ cảnh tài chính user, giao dịch |
| TaiChinhIndexViewDataBuilder | Dữ liệu cho trang tài chính (dashboard, insight) |
| FinancialInsightPipeline, InsightPayloadService | Pipeline và payload cho Insight AI |
| TransactionClassifier, TransactionSemanticLayer | Phân loại giao dịch, semantic layer |
| LoanLedgerService, UnifiedLoansBuilderService | Sổ cái khoản vay, gom nợ + vay |
| BudgetThresholdService, IncomeGoalService | Ngưỡng ngân sách, mục tiêu thu nhập |
| AdaptiveThresholdService, DualAxisAwarenessService | Ngưỡng thích nghi, nhận thức hai trục |

## 3. Cơ sở dữ liệu

### 3.1 Bảng chính (một phần)
| Bảng | Mục đích |
|------|----------|
| users | Người dùng, profile, admin, ngưỡng dashboard |
| transaction_history | Giao dịch ngân hàng (đồng bộ từ liên kết) |
| user_bank_accounts, pay2s_* | Tài khoản ngân hàng, cấu hình API Pay2s |
| loan_contracts, loan_ledger_entries, loan_pending_payments | Khoản vay, sổ cái, lịch trả |
| user_liabilities, liability_payments, liability_accruals | Nợ, thanh toán, phát sinh |
| budget_thresholds, budget_threshold_events | Ngưỡng ngân sách, sự kiện vượt ngưỡng |
| financial_state_snapshots, financial_insight_ai_cache | Snapshot trạng thái tài chính, cache insight AI |
| work_tasks, labels, projects | Công việc, nhãn, dự án (behavior/kanban) |
| behavior_* | Các bảng behavior intelligence (events, aggregates, trust, …) |
| cache, sessions, jobs | Laravel cache/session/queue (database driver) |

### 3.2 Migration & phiên bản schema
- **Quy ước:** Mỗi thay đổi schema = một file migration mới (tên dạng `YYYY_MM_DD_HHMMSS_mô_tả.php`). Không sửa file migration đã chạy.
- **Thêm cột/bảng:** tạo migration mới, ví dụ `add_xyz_to_loan_contracts_table` dùng `Schema::table('loan_contracts', ...)`.
- Chạy: `php artisan migrate` (production: `php artisan migrate --force`).
- Migrations có thể dùng guard `Schema::hasTable()` / `Schema::hasColumn()` khi thứ tự chạy phụ thuộc bảng khác.

## 4. Môi trường & Triển khai

### 4.1 Biến môi trường (.env)
| Key | Bắt buộc | Mô tả |
|-----|----------|-------|
| APP_ENV, APP_DEBUG, APP_URL | Có | APP_URL=http://localhost:8000 khi chạy local |
| DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Có | DB_DATABASE=taichinh |
| GPT_CLASSIFICATION_ENABLED, OPENAI_API_KEY, OPENAI_MODEL | Không | Phân loại giao dịch bằng GPT (tắt nếu không có key) |
| FINANCIAL_USE_COGNITIVE_LAYER, FINANCIAL_COGNITIVE_*, FINANCIAL_INSIGHT_AI_CACHE_TTL_HOURS | Không | Cognitive layer cho Insight AI |
| RECURRING_* | Không | Recurring engine (lương, subscription): MIN_TRANSACTIONS, INTERVAL_DAYS, MATCH_CONFIDENCE, ... |
| LARAVEL_BYPASS_ENV_CHECK | Không | =1 khi chạy `npm run dev` trong môi trường bị nhận là CI |
| SANCTUM_STATEFUL_DOMAINS | Không | Nếu app SPA cùng domain; API mobile dùng token không cần. |

### 4.2 Lệnh triển khai

```bash
composer install --no-dev
cp .env.example .env && php artisan key:generate
# Cấu hình DB trong .env (DB_DATABASE=taichinh, ...)
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
npm run build
```

**Queue:** `QUEUE_CONNECTION=database` → cần chạy worker: `php artisan queue:work` (hoặc supervisor). Một số job: đồng bộ giao dịch, cập nhật pattern, behavior aggregate.

**Scheduler:** Nếu dùng cron cho job định kỳ (ví dụ behavior aggregate 02:30, policy sync 03:00): `* * * * * cd /path && php artisan schedule:run`.

## 5. Testing & CI

### 5.1 Chạy test

- **Pest:** `php artisan test` hoặc `composer run test`
- **Coverage:** `php artisan test --coverage`
- **Filter:** `php artisan test --filter=ExampleTest`
- Yêu cầu PHP 8.3+ cho một số package test (composer lock); dev có thể dùng `--ignore-platform-reqs` với PHP 8.2.

### 5.2 CI pipeline

- Tùy setup (GitHub Actions / GitLab CI): bước thường gồm `composer install`, `npm ci`, `npm run build`, `php artisan test`, (optional) `php artisan migrate --force` cho staging.

## 6. Log & Giám sát

- **Log ứng dụng:** `storage/logs/laravel.log`
- **Log channel:** config `config/logging.php` (stack, single, level)
- Không ghi mật khẩu/token vào log.

## 7. Changelog kỹ thuật

| Ngày | Thay đổi |
|------|----------|
| 2026-02-24 | Cập nhật doc: stack, env, migration, service, Vite bypass. |
| 2026-02-24 | Bổ sung bảng route chính (tai-chinh, thu-chi, cong-viec, tribeos, admin), API nội bộ, lệnh triển khai (queue, scheduler), test, log. |
| 2026-02-24 | Chuẩn bị API cho app mobile: Laravel Sanctum, routes/api.php v1 (login, logout, tai-chinh/insight-payload, projection, insight-feedback, giao-dich), GiaoDichController@giaoDichJson, doc mục 2.3. |

---
*Cập nhật lần cuối: 2026-02-24.*
