# Security Doc — Tài liệu Bảo mật

## 1. Mục đích

Tài liệu mô tả **chính sách và biện pháp bảo mật** của Taichinh: xác thực, phân quyền, bảo vệ dữ liệu, đầu vào và ứng phó sự cố.

## 2. Xác thực (Authentication)

| Mục | Nội dung |
|-----|----------|
| **Cơ chế** | Laravel session (SESSION_DRIVER=database hoặc file); cookie session, CSRF token cho form. |
| **Mật khẩu** | Hash bcrypt (BCRYPT_ROUNDS trong .env); không lưu plain text. |
| **Khóa phiên** | SESSION_LIFETIME; đăng xuất hủy session. Đổi mật khẩu có thể invalidate session tùy implementation. |
| **2FA** | Chưa triển khai. |

## 3. Phân quyền (Authorization)

| Mục | Nội dung |
|-----|----------|
| **Mô hình** | Ownership: user chỉ xem/sửa dữ liệu của mình (scope user_id). Admin: user có is_admin (hoặc role tương đương) mới vào được /admin. |
| **Middleware** | `auth` — bắt buộc đăng nhập cho route front/admin. `EnsureUserIsAdmin` (hoặc tên tương đương) — chỉ admin vào route /admin. |
| **Quy tắc dữ liệu** | Query luôn filter theo user_id (ví dụ TransactionHistory::where('user_id', auth()->id()), loan/liability thuộc user). |

## 4. Bảo vệ dữ liệu

### 4.1 Dữ liệu nhạy cảm

- **Mật khẩu:** Chỉ lưu hash. **Token / API key:** OPENAI_API_KEY, Pay2s/partner secret chỉ trong .env, không commit.
- **Số tài khoản, giao dịch:** Lưu DB; truy cập qua auth + scope user_id. **At rest:** DB nên nằm trong môi trường kiểm soát; mã hóa DB (TDE) tùy hạ tầng.
- **In transit:** HTTPS bắt buộc ở production (APP_URL=https://...).

### 4.2 PII & Tuân thủ

- PII: email, tên, thông tin hồ sơ, giao dịch tài chính. Lưu trong DB, chỉ chủ tài khoản và admin (nếu có chính sách) truy cập. Có thể bổ sung chính sách bảo mật, thời gian lưu, quyền xóa theo GDPR/PDPA khi áp dụng.

## 5. Đầu vào & Chống tấn công

| Mối đe dọa | Biện pháp |
|------------|-----------|
| **SQL Injection** | Eloquent / Query Builder; tránh raw query với input người dùng không bind. |
| **XSS** | Blade escape mặc định ({{ }}); cẩn thận khi dùng {!! !!} chỉ với nội dung đã sanitize. |
| **CSRF** | Laravel @csrf trong form; VerifyCsrfToken middleware. |
| **Mass assignment** | Model dùng $fillable / $guarded; không cho phép gán request->all() không filter. |
| **Upload file** | Nếu có: validate type, size; lưu ngoài webroot hoặc qua storage link. |

## 6. Cấu hình & Bí mật

- **Bí mật:** Không commit `.env`. API key, DB password chỉ trong env. `.env.example` không chứa giá trị thật.
- **Debug:** APP_DEBUG=false ở production; không lộ stack trace ra ngoài.
- **Log:** Không ghi mật khẩu, token, API key vào log.

## 7. Ứng phó sự cố

- **Rò rỉ dữ liệu:** Đánh giá phạm vi; thông báo nội bộ; thông báo user/cơ quan nếu theo quy định. Đổi key/credential bị lộ.
- **Tài khoản bị xâm phạm:** User đổi mật khẩu; admin có thể vô hiệu hóa tài khoản / đăng xuất phiên nếu có tính năng.
- **Audit:** Log truy cập nhạy cảm (đăng nhập, thay đổi cài đặt) tùy triển khai; lưu log theo chính sách.

## 8. Changelog bảo mật

| Ngày | Thay đổi |
|------|----------|
| 2026-02-24 | Viết lại Security Doc: auth session, bcrypt, middleware auth/admin, ownership, .env, HTTPS, CSRF, log. |

---
*Cập nhật lần cuối: 2026-02-24.*
