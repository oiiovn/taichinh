# Triển khai & Push hot

Tài liệu hướng dẫn triển khai lần đầu và **push hot** (đẩy bản cập nhật nhanh lên server sau khi pull code).

---

## 1. Triển khai lần đầu

### 1.1 Yêu cầu

- PHP 8.2+ (khuyến nghị 8.3+)
- Composer, Node.js 18+, npm
- MySQL/MariaDB (DB_DATABASE=taichinh)
- (Tùy chọn) Supervisor cho queue worker

### 1.2 Các bước

```bash
# Clone / upload code
cd /path/to/canhan

# Cài đặt dependency
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Cấu hình môi trường
cp .env.example .env
php artisan key:generate
# Chỉnh .env: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, QUEUE_CONNECTION, ...

# Database
php artisan migrate --force
php artisan storage:link

# Cache production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 1.3 Queue & Scheduler

- **Queue:** Nếu `QUEUE_CONNECTION=database`, chạy worker (supervisor hoặc systemd):
  ```bash
  php artisan queue:work --sleep=3 --tries=3
  ```
- **Scheduler (cron):** Thêm vào crontab:
  ```bash
  * * * * * cd /path/to/canhan && php artisan schedule:run >> /dev/null 2>&1
  ```

---

## 2. Push hot (đẩy bản cập nhật nhanh)

**Push hot** = sau khi `git pull` (hoặc nhận code mới), chạy chuỗi lệnh tối thiểu để ứng dụng dùng bản mới mà không cần cài lại từ đầu.

### 2.1 Quy trình chuẩn (có thay đổi PHP/DB/config)

```bash
cd /path/to/canhan

git pull

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

Sau đó **restart queue worker** (nếu đang chạy):

```bash
# Supervisor
sudo supervisorctl restart canhan-worker

# Hoặc kill và chạy lại
php artisan queue:restart
# Worker đang chạy queue:work sẽ tự thoát; supervisor/systemd sẽ khởi động lại process.
```

### 2.2 Push hot chỉ code PHP/Blade (không đổi DB, không đổi frontend)

```bash
cd /path/to/canhan
git pull
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:clear
php artisan queue:restart
```

Không cần `npm run build` nếu không sửa JS/CSS. Không cần `migrate` nếu không có migration mới.

### 2.3 Push hot có migration mới

```bash
cd /path/to/canhan
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:clear
npm run build   # nếu có thay đổi frontend
php artisan queue:restart
```

### 2.4 One-liner (gợi ý — đủ cho đa số trường hợp)

```bash
cd /path/to/canhan && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:clear && npm run build && php artisan queue:restart
```

Có thể đặt alias trong shell:

```bash
alias push-hot='cd /path/to/canhan && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:clear && npm run build && php artisan queue:restart'
```

---

## 3. Lưu ý

| Việc | Ghi chú |
|------|--------|
| **Downtime** | Trong lúc chạy migrate + cache, request vẫn xử lý được; restart queue chỉ ảnh hưởng job đang chạy (retry sau). Nếu cần zero-downtime, dùng blue-green hoặc rolling deploy. |
| **Queue** | `php artisan queue:restart` báo worker thoát; cần supervisor/systemd để tự khởi động lại. |
| **.env** | Không ghi đè .env khi pull. Thêm biến mới (ví dụ MERCHANT_EMBEDDING_*) cần tự chỉnh trên server. |
| **Migration lỗi** | Nếu migrate thất bại, xem `storage/logs/laravel.log`; không chạy `migrate:rollback` tùy tiện trên production. |
| **Frontend** | Chỉ cần `npm run build` khi có thay đổi file trong `resources/js`, `resources/css` hoặc Blade dùng Vite. |

---

## 4. Changelog

| Ngày | Thay đổi |
|------|----------|
| 2026-03-07 | Tạo tài liệu: triển khai lần đầu, push hot (chuẩn / chỉ PHP / có migration), one-liner, lưu ý queue & .env. |

---
*Cập nhật lần cuối: 2026-03-07.*
