# API Chấm công (app mobile)

Base URL local: `http://127.0.0.1:8000`  
Auth: Laravel Sanctum — header `Authorization: Bearer {token}`

## 1. Đăng nhập

`POST /api/v1/login`

```json
{ "email": "nv@example.com", "password": "secret" }
```

Response:

```json
{
  "token": "1|xxxxx",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "…", "email": "…" }
}
```

Lưu `token` để gọi các API sau.

## 2. Lấy lịch chấm công theo tháng

`GET /api/v1/food/cham-cong?month=2026-07`

| Query | Mô tả |
|-------|--------|
| `month` | `YYYY-MM` (mặc định tháng hiện tại) |
| `employee_id` | Chỉ manager: lọc 1 NV |

Header:

```http
Authorization: Bearer {token}
Accept: application/json
```

### Response (rút gọn khớp UI)

```json
{
  "ok": true,
  "month": "2026-07",
  "month_label": "tháng 7 năm 2026",
  "from": "2026-07-01",
  "to": "2026-07-31",
  "is_manager": false,
  "employee": { "id": 3, "name": "…" },
  "today": {
    "has_checked_in": true,
    "has_checked_out": false,
    "has_break_start": false,
    "has_break_end": false,
    "log": { }
  },
  "logs": [
    {
      "id": 12,
      "work_date": "2026-07-25",
      "work_date_label": "25/07/2026 (Thứ 7)",
      "day_name": "Thứ 7",
      "is_off": false,
      "status_badge": "355 phút",
      "check_in_at": "13:57",
      "check_out_at": "19:52",
      "break_start_at": null,
      "break_end_at": null,
      "break_label": null,
      "work_minutes": 355,
      "daily_salary": 242583,
      "daily_salary_formatted": "242.583 đ",
      "note": "Mọi người nhậu cùng nhau",
      "late_minutes": 0,
      "late_penalty": 0
    },
    {
      "id": 13,
      "work_date": "2026-07-26",
      "work_date_label": "26/07/2026 (Chủ nhật)",
      "is_off": true,
      "status_badge": "OFF",
      "check_in_at": null,
      "check_out_at": null,
      "daily_salary": null,
      "daily_salary_formatted": null,
      "note": "ốm"
    }
  ]
}
```

## 3. Chấm công (vào / ra / nghỉ)

`POST /api/v1/food/cham-cong`

```json
{
  "work_date": "2026-07-29",
  "action": "check_in"
}
```

`action`: `check_in` | `check_out` | `break_start` | `break_end`

## 4. Map UI (theo màn FOOD Chấm công)

| UI | Field API |
|----|-----------|
| Ô tháng + nút Xem | `month` / `month_label` → gọi lại `?month=YYYY-MM` |
| Tiêu đề ngày | `work_date_label` |
| Badge góc phải | `status_badge` (`OFF` hoặc `355 phút`) |
| VÀO CA | `check_in_at` (ẩn nếu `is_off`) |
| RA CA | `check_out_at` |
| Nghỉ | `break_label` (chỉ hiện khi có) |
| Lương ngày | `daily_salary_formatted` |
| Ghi chú | `note` |

### Pseudo UI

```text
for log in response.logs:
  show title = log.work_date_label
  show badge = log.status_badge
  if log.is_off:
    show note only
  else:
    show VÀO CA = log.check_in_at
    show RA CA  = log.check_out_at
    if log.break_label: show Nghỉ = log.break_label
    if log.daily_salary_formatted: show Lương ngày
    if log.note: show note
```

## 5. Ví dụ curl

```bash
# Login
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/v1/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"EMAIL","password":"PASSWORD"}' | jq -r .token)

# Lấy tháng 7/2026
curl -s "http://127.0.0.1:8000/api/v1/food/cham-cong?month=2026-07" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq

# Vào ca hôm nay
curl -s -X POST http://127.0.0.1:8000/api/v1/food/cham-cong \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d "{\"work_date\":\"$(date +%F)\",\"action\":\"check_in\"}" | jq
```

## 6. Flutter / Dart (gợi ý)

```dart
final res = await dio.get(
  '/api/v1/food/cham-cong',
  queryParameters: {'month': '2026-07'},
  options: Options(headers: {'Authorization': 'Bearer $token'}),
);
final logs = (res.data['logs'] as List);
// ListView.builder → card theo map UI ở mục 4
```
