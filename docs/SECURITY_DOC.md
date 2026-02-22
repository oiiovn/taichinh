# Security Doc — Tài liệu Bảo mật

## 1. Mục đích

Tài liệu mô tả **chính sách và biện pháp bảo mật** của hệ thống: xác thực, phân quyền, bảo vệ dữ liệu, và xử lý sự cố.

## 2. Xác thực (Authentication)

| Mục | Nội dung |
|-----|----------|
| Cơ chế | [Session / Token / OAuth; Laravel Sanctum/Passport nếu có] |
| Mật khẩu | [Chính sách: độ dài, độ phức tạp; hash algo: bcrypt/argon] |
| Khóa phiên | [Timeout, đăng xuất khi đổi mật khẩu] |
| 2FA | [Có/Không; phương thức] |

## 3. Phân quyền (Authorization)

| Mục | Nội dung |
|-----|----------|
| Mô hình | [RBAC / ABAC / ownership (user chỉ xem dữ liệu của mình)] |
| Middleware | [auth, role/permission middleware nào] |
| Quy tắc dữ liệu | [User chỉ truy cập đúng scope: ví dụ theo user_id, tenant_id] |

## 4. Bảo vệ dữ liệu

### 4.1 Dữ liệu nhạy cảm
- [Liệt kê: mật khẩu, token, số tài khoản, giao dịch tài chính.]
- **At rest**: [Mã hóa DB có/không; encryption key quản lý thế nào.]
- **In transit**: [HTTPS bắt buộc.]

### 4.2 PII & Tuân thủ
- [PII nào thu thập; cách lưu, thời gian lưu.]
- [Tuân thủ: GDPR, PDPA (nếu áp dụng) — tóm tắt.]

## 5. Đầu vào & Chống tấn công

| Mối đe dọa | Biện pháp |
|------------|-----------|
| SQL Injection | [Eloquent/Query Builder; không raw query không an toàn] |
| XSS | [Escape output; CSP nếu có] |
| CSRF | [Laravel CSRF token cho form] |
| Mass assignment | [$fillable / $guarded trong Model] |
| Upload file | [Validate type/size; lưu ngoài webroot hoặc quét malware nếu cần] |

## 6. Cấu hình & Bí mật

- **Bí mật**: Không commit `.env`; API key, DB password chỉ trong env.
- **Debug**: `APP_DEBUG=false` ở production; không lộ stack trace ra ngoài.
- **Log**: [Không ghi mật khẩu/token vào log.]

## 7. Ứng phó sự cố

- **Rò rỉ dữ liệu**: [Quy trình: thông báo nội bộ, đánh giá, thông báo user/nếu cần.]
- **Tài khoản bị xâm phạm**: [Đổi mật khẩu, vô hiệu hóa phiên.]
- **Audit**: [Log truy cập nhạy cảm có/không; lưu bao lâu.]

## 8. Changelog bảo mật

| Ngày | Thay đổi |
|------|----------|
| [YYYY-MM-DD] | [Tóm tắt] |

---
*Cập nhật lần cuối: [YYYY-MM-DD].*
