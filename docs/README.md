# Tài liệu Hệ thống Taichinh

Hệ thống tài liệu gồm **7 bộ tài liệu chính**:

| # | Tài liệu | File | Mục đích |
|---|----------|------|----------|
| 1 | **Product Doc** | [PRODUCT_DOC.md](./PRODUCT_DOC.md) | Sản phẩm: scope, module, user stories, luồng nghiệp vụ, yêu cầu chức năng & NFR. |
| 2 | **Technical Doc** | [TECHNICAL_DOC.md](./TECHNICAL_DOC.md) | Kỹ thuật: stack, kiến trúc, route, API, DB, env, migration, test. |
| 3 | **Deploy & Push hot** | [DEPLOY_AND_PUSH_HOT.md](./DEPLOY_AND_PUSH_HOT.md) | Triển khai lần đầu, **push hot** (đẩy bản cập nhật nhanh: pull → migrate → cache → build → restart queue). |
| 4 | **Financial Logic Doc** | [FINANCIAL_LOGIC_DOC.md](./FINANCIAL_LOGIC_DOC.md) | Logic tài chính: công thức runway, buffer, ưu tiên nợ, ngưỡng, nguồn dữ liệu. |
| 5 | **AI Learning Doc** | [AI_LEARNING_DOC.md](./AI_LEARNING_DOC.md) | AI/Học máy: phân loại GPT, cognitive layer insight, payload, feedback, fallback. |
| 6 | **Security Doc** | [SECURITY_DOC.md](./SECURITY_DOC.md) | Bảo mật: xác thực, phân quyền, dữ liệu nhạy cảm, đầu vào, ứng phó sự cố. |
| 7 | **Behavior Intelligence Doc** | [BEHAVIOR_INTELLIGENCE_DOC.md](./BEHAVIOR_INTELLIGENCE_DOC.md) | Stack 9 tầng hành vi: Identity → Behavior → Trust → Projection, Program, Coaching. |

Ngoài ra: [STRUCTURE.md](../STRUCTURE.md) — cấu trúc thư mục, phân luồng route, view, luồng truy cập.

---

## Quy ước cập nhật

- **Nên cập nhật doc khi:** thêm tính năng mới, thay đổi API/env, thêm migration hoặc service quan trọng.
- Mỗi doc có phần **Changelog** ở cuối — ghi lại thay đổi khi chỉnh.
- Tham chiếu chéo giữa các doc khi cần (Technical ↔ Financial Logic, AI Learning ↔ Product).

---
*Cập nhật lần cuối: 2026-03-07. Thêm DEPLOY_AND_PUSH_HOT.md — hướng dẫn triển khai và push hot.*
