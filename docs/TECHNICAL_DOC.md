# Technical Doc — Tài liệu Kỹ thuật

## 1. Tổng quan kiến trúc

### 1.1 Stack công nghệ
| Thành phần | Công nghệ | Ghi chú |
|------------|-----------|---------|
| Backend | Laravel [version] | PHP [version] |
| Frontend | [Blade / Vue / React] | [Phiên bản] |
| Database | MySQL / MariaDB | [Phiên bản] |
| Cache | Redis / File | [Nếu có] |
| Queue | Laravel Queue | [Driver] |
| Build | Vite | [Nếu có] |

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

### 2.1 Route chính
| Method | URI | Controller@action | Mô tả |
|--------|-----|-------------------|-------|
| GET | /tai-chinh | TaiChinhController@index | Trang tài chính |
| [GET/POST] | [uri] | [Controller@method] | [Mô tả] |

### 2.2 API nội bộ / Ajax
- [Liệt kê endpoint ajax hoặc API dùng nội bộ, request/response mẫu]

### 2.3 Service layer
| Service | Trách nhiệm |
|---------|--------------|
| CashflowProjectionService | Chiếu dòng tiền, runway |
| InsightPayloadService | Chuẩn bị payload cho insight/AI |
| DebtPriorityService | Xếp hạng ưu tiên trả nợ |
| [Tên khác] | [Mô tả] |

## 3. Cơ sở dữ liệu

### 3.1 Bảng chính
| Bảng | Mục đích |
|------|----------|
| users | Người dùng |
| [table_name] | [Mô tả] |

### 3.2 Migration & phiên bản schema
- [Quy ước đặt tên migration, cách chạy migrate]

## 4. Môi trường & Triển khai

### 4.1 Biến môi trường (.env)
| Key | Bắt buộc | Mô tả |
|-----|----------|-------|
| APP_ENV | Có | local / staging / production |
| APP_DEBUG | Có | true / false |
| DB_* | Có | Kết nối DB |
| [KEY] | [Có/Không] | [Mô tả] |

### 4.2 Lệnh triển khai
```bash
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
# [Queue, scheduler nếu có]
```

## 5. Testing & CI

### 5.1 Chạy test
- Unit: `php artisan test` hoặc `./vendor/bin/phpunit`
- [Frontend test nếu có]

### 5.2 CI pipeline (nếu có)
- [Mô tả bước build / test / deploy]

## 6. Log & Giám sát

- Log ứng dụng: `storage/logs/laravel.log`
- [Cấu hình log channel, monitoring tool nếu có]

## 7. Changelog kỹ thuật

| Ngày | Thay đổi |
|------|----------|
| [YYYY-MM-DD] | [Tóm tắt] |

---
*Cập nhật lần cuối: [YYYY-MM-DD].*
