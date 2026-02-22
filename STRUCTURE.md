# Cấu trúc thư mục & phân luồng hệ thống

## 1. Phân luồng route (`routes/`)

| File       | Luồng        | Mô tả |
|-----------|--------------|--------|
| `web.php` | Điểm vào     | Chỉ require 3 file bên dưới, không định nghĩa route. |
| `auth.php`| Xác thực     | Đăng nhập, đăng ký, đăng xuất (guest / auth). |
| `admin.php` | Quản trị   | Prefix `/admin`, middleware `auth` + `admin`. Trang chủ admin, CRUD user. |
| `front.php` | Giao diện chính | Sau đăng nhập: dashboard, profile, tài chính, gói hiện tại, … |

---

## 2. Thư mục ứng dụng (`app/`)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/           # Controller khu vực admin
│   │   │   └── UserController.php
│   │   ├── AuthController.php
│   │   ├── ProfileController.php
│   │   └── ...
│   └── Middleware/
│       └── EnsureUserIsAdmin.php   # Chặn user không phải admin
├── Models/
│   └── User.php
├── Helpers/
│   └── MenuHelper.php
└── View/Components/         # Blade components (ui, common, profile, header, ...)
```

---

## 3. View – phân theo luồng & tính năng (`resources/views/`)

```
resources/views/
├── layouts/
│   ├── app.blade.php              # Layout giao diện chính (user)
│   ├── admin.blade.php            # Layout khu vực admin
│   ├── sidebar.blade.php         # Menu sidebar user
│   ├── sidebar-admin.blade.php   # Menu sidebar admin (Trang chủ, Quản lý user)
│   ├── app-header.blade.php
│   ├── app-header-admin.blade.php
│   └── backdrop.blade.php
├── pages/
│   ├── auth/                      # Đăng nhập, đăng ký (nếu tách view auth)
│   ├── dashboard/                 # Trang chủ sau đăng nhập
│   │   └── home.blade.php
│   ├── profile.blade.php          # Hồ sơ cá nhân
│   ├── goi-hien-tai.blade.php     # Bảng giá / gói
│   ├── tai-chinh/                 # Tài chính
│   │   ├── tai-chinh.blade.php    # Layout tab (sidebar + nội dung)
│   │   └── tai-khoan.blade.php   # Tab liên kết tài khoản ngân hàng
│   ├── admin/                     # Toàn bộ view admin
│   │   ├── index.blade.php        # Trang chủ admin
│   │   └── users/
│   │       ├── index.blade.php    # Danh sách user
│   │       ├── create.blade.php
│   │       └── edit.blade.php
│   ├── form/                      # Form mẫu
│   ├── tables/                    # Bảng mẫu
│   ├── chart/                     # Biểu đồ mẫu
│   ├── ui-elements/               # UI mẫu
│   └── errors/                    # Trang lỗi (404, ...)
└── components/                    # Component dùng chung
    ├── common/                    # Breadcrumb, preloader, ...
    ├── header/                    # User dropdown, notification
    ├── profile/                   # Thẻ hồ sơ, địa chỉ, ...
    ├── ui/
    ├── tables/
    ├── form/
    └── ecommerce/
```

---

## 4. Luồng truy cập

1. **Chưa đăng nhập**  
   Chỉ vào được: `/signin`, `/login`, `/signup`.  
   Route do `routes/auth.php` định nghĩa (middleware `guest`).

2. **Đã đăng nhập (user thường)**  
   Vào được: `/` (dashboard), `/profile`, `/tai-chinh`, `/goi-hien-tai`, …  
   Route do `routes/front.php` định nghĩa (middleware `auth`).  
   Vào `/admin` → 403 (middleware `admin`).

3. **Đã đăng nhập (admin)**  
   Vào được toàn bộ front + `/admin`, `/admin/users`, …  
   Route admin do `routes/admin.php` định nghĩa (middleware `auth` + `admin`).  
   Layout dùng `layouts.admin` + `sidebar-admin` (menu riêng, không có Gói hiện tại).

---

## 5. Tóm tắt file quan trọng theo luồng

| Luồng   | Route          | Controller / View chính |
|--------|----------------|---------------------------|
| Auth   | `auth.php`     | `AuthController`, `pages/auth/*` |
| Front  | `front.php`    | Closure / `ProfileController`, `pages/dashboard`, `pages/tai-chinh`, `pages/goi-hien-tai`, … |
| Admin  | `admin.php`    | `Admin\UserController`, `pages/admin/*`, layout `admin`, `sidebar-admin` |
