# Báo cáo tổng kết: Trang Công việc `/cong-viec`

**URL:** http://127.0.0.1:8000/cong-viec  
**Cập nhật:** 2026-03-07

---

## 1. Báo cáo tổng quan

### 1.1 Chức năng chính

| Thành phần | Mô tả |
|------------|--------|
| **Tab Tổng quan** | Một giao diện thống nhất, layout thay đổi theo “stage” hành vi (Focus / Guided / Analytic / Strategic). Hiển thị insight, tin nhắn coaching, tiến độ chương trình, danh sách “một việc quan trọng nhất” hoặc nhiều task hôm nay. |
| **Tab Hôm nay** | Danh sách instance công việc rơi vào ngày hiện tại (lazy generate từ template + repeat). Tick hoàn thành → cập nhật instance (completed_at), không ảnh hưởng task lặp ngày mai. |
| **Tab Dự kiến** | Task chưa đến hạn (due_date > hôm nay), sắp xếp theo ngày. |
| **Tab Hoàn thành** | Instance đã completed, nhóm theo **Hôm nay / Hôm qua / Tuần này / Trước đó**; undo qua toggle instance. |
| **Kanban** | Cột Backlog / This Cycle / In Progress / Done; kéo thả, cập nhật kanban_status và actual_duration khi chuyển Done. |
| **Chương trình (Program)** | Hành trình 30 ngày; task gắn program_id; tiến độ (days_elapsed, integrity_score), today_done/today_total lấy từ **instances** ngày hiện tại. |

### 1.2 Luồng dữ liệu chính

- **Mở trang:** `EnsureTaskInstancesService::ensureForUserAndDate(userId, today)` → tạo instance thiếu cho mọi task “rơi vào” hôm nay (occursOn + program task).
- **Hôm nay:** `getTasksToday()` → `WorkTaskInstance` với `instance_date = today`, status pending/skipped, load relation `task` (project, labels, program).
- **Tick hoàn thành (Hôm nay):** PATCH `/cong-viec/instances/{id}/toggle-complete` → cập nhật instance (status, completed_at), ghi event behavior, cập nhật trust; nếu P(real) thấp → modal xác nhận → POST `confirm-complete` instance.
- **Tick (Dự kiến / Hoàn thành / Kanban):** PATCH `/cong-viec/tasks/{id}/toggle-complete` (task-level), logic tương tự (ProbabilisticTruth, trust, program progress).

---

## 2. Mặt kỹ thuật

### 2.1 Kiến trúc dữ liệu

| Layer | Bảng / Model | Vai trò |
|-------|----------------|--------|
| **Template** | `work_tasks` (CongViecTask) | Định nghĩa công việc: title, due_date, due_time, repeat (none/daily/weekly/monthly), repeat_until, repeat_interval, program_id, priority, labels, project, kanban_status. |
| **Instance** | `work_task_instances` (WorkTaskInstance) | Phiên bản theo ngày: work_task_id, instance_date, status (pending/completed/skipped), completed_at, skipped, notes. Unique (work_task_id, instance_date). |
| **Phụ** | labels, projects, work_task_label, kanban_columns, behavior_programs | Nhãn, dự án, cột Kanban, chương trình 30 ngày. |

- **Lazy generate:** Không cron; mỗi lần mở trang gọi `ensureForUserAndDate(userId, today)` → insert instance thiếu cho ngày đó.
- **occursOn(date):** Task có due_date + (repeat_until hoặc null) và logic repeat + **repeat_interval** (mỗi N ngày/tuần/tháng) quyết định task có “rơi vào” ngày đó hay không.

### 2.2 API chính

| Method | Route | Mô tả |
|--------|--------|--------|
| GET | /cong-viec | Trang chính; data qua CongViecPageDataService::getIndexData(). |
| POST | /cong-viec/tasks | Tạo task (template). |
| PUT | /cong-viec/tasks/{id} | Cập nhật task. |
| DELETE | /cong-viec/tasks/{id} | Xóa task. |
| PATCH | /cong-viec/tasks/{id}/toggle-complete | Bật/tắt completed (task) — dùng cho Dự kiến, Hoàn thành, Kanban. |
| POST | /cong-viec/tasks/{id}/confirm-complete | Xác nhận hoàn thành (task) sau modal. |
| PATCH | /cong-viec/instances/{id}/toggle-complete | Bật/tắt completed **instance** — dùng cho tab Hôm nay. |
| POST | /cong-viec/instances/{id}/confirm-complete | Xác nhận hoàn thành instance sau modal. |
| PATCH | /cong-viec/tasks/{id}/kanban-status | Cập nhật kanban_status (+ actual_duration khi Done). |
| POST | /cong-viec/behavior-events | Batch event behavior (frontend gửi). |

### 2.3 Service layer (liên quan trang)

| Service | Trách nhiệm |
|---------|--------------|
| CongViecPageDataService | getIndexData: ensure instances, getTasksToday (instances), getInstancesUpcoming (instance_date > today, pending), getTasksInbox, **getCompletedInstancesGrouped** (today/yesterday/this_week/older), getKanbanData; behaviorRadar, editTask, behaviorPolicy, behaviorProjection; InterfaceAdaptation, CoachingNarrative; buildProgramProgressPayload (today_done/today_total từ instances). |
| EnsureTaskInstancesService | ensureForUserAndDate: tasksOccurringOnDate (due + occursOn, hoặc program_id); insert work_task_instances thiếu. |
| InterfaceAdaptationService | getAdaptation: BehaviorStageClassifier → stage → layout (focus/guided/analytic/strategic) + config; level-up message. |
| CoachingNarrativeService | getTodayNarrative: today_message, empty_today_copy, integrity/trust/projection interpretation, sidebar narrative, coaching effectiveness. |
| ProbabilisticTruthService | estimate(userId, workTaskId, payload): P(real_completion \| behavior); delay penalty, anomaly penalty; require_confirmation nếu P < threshold. |
| AdaptiveTrustGradientService | update trust (execution, honesty, consistency) sau tick/confirm; getLatestVarianceAndRecovery. |
| BehaviorProgramProgressService | getProgressForUi (integrity_score, days_elapsed, days_total, days_with_completion); computeIntegrity; today_done/today_total dùng từ CongViecPageDataService (instances). |
| LongTermProjectionService | getOrCompute: probability_maintain_60d/90d, suggestion (insight). |
| MicroEventCaptureService | capture event (task_tick_at, …) vào behavior_events. |
| TaskStreakService | getStreakForTask (chuỗi instance completed liên tiếp), getCompletionRateInRange (completed/total_occurrences trong khoảng). |

### 2.4 View & frontend

- **Layout:** `layouts.cong-viec`; nội dung list/board/calendar (calendar đang phát triển).
- **Tab:** tong-quan (nhiều partial: tong-quan-focus, guided, analytic, strategic theo interfaceAdaptation.layout), hom-nay, du-kien, hoan-thanh.
- **Task row:** partial `task-row`; hỗ trợ cả **instance** (data-instance-id, data-confirm-url cho Hôm nay) và **task** (Dự kiến, Hoàn thành).
- **Scripts:** Alpine.js congViecPage(); checkbox change → PATCH toggle (task hoặc instance); require_confirmation → modal → confirm-complete (task hoặc instance); cập nhật program progress DOM.

---

## 3. Ưu điểm

- **Recurring đúng nghĩa:** Template + Instance; hoàn thành từng ngày được lưu (completed_at), có lịch sử, không “mất luôn” khi tick.
- **Không phụ thuộc cron:** Instance tạo lazy khi mở trang, đơn giản vận hành.
- **Giao diện thích nghi:** Layout và mức độ chi tiết (Focus → Strategic) theo stage hành vi (trust, integrity, CLI, recovery, variance).
- **Coaching có dữ liệu:** Narrative từ integrity, trust, projection; empty_today_copy, today_message; meta-learning hiệu quả can thiệp (CoachingEffectivenessService).
- **Gate trung thực:** P(real) thấp → yêu cầu xác nhận; tick/confirm cập nhật trust và event, phù hợp behavior intelligence.
- **Chương trình 30 ngày:** Program + program_id; tiến độ và “hôm nay” tính theo instances, rõ ràng.
- **Kanban:** Cột tùy chỉnh, kéo thả, actual_duration khi Done.

---

## 4. Nhược điểm / Hạn chế

- **Tab Hoàn thành:** Đã chuyển sang **instance** (completedInstancesGrouped: Hôm nay / Hôm qua / Tuần này / Trước đó); undo qua toggle instance.
- **Recovery / streak:** RecoveryIntelligenceService, BehaviorProgramProgressService vẫn dùng task completed + updated_at cho một số metric; chưa chuyển hết sang “ngày có ≥1 instance completed”. **TaskStreakService** đã có: getStreakForTask, getCompletionRateInRange (dùng instance).
- **repeat_interval:** Đã hỗ trợ trong occursOn(): daily = mỗi N ngày (days_between % interval), weekly/monthly tương tự.
- **Calendar:** Chế độ lịch chưa tích hợp instance/template, chủ yếu placeholder.
- **Performance:** ensureForUserAndDate mỗi request; với nhiều task lặp + nhiều người dùng có thể cần cache/queue nhẹ (hiện tại insert batch đã tối ưu).
- **Rrule / quy tắc nâng cao:** Chưa có (every weekday, 1st Monday, last day of month); chỉ daily/weekly/monthly đơn giản.

---

## 5. Sự thông minh (Behavior Intelligence)

### 5.1 Tích hợp 9 tầng (theo BEHAVIOR_INTELLIGENCE_DOC)

| Tầng | Áp dụng trên /cong-viec |
|------|--------------------------|
| **Identity Baseline** | Baseline cá nhân (chronotype, trì hoãn, …) làm ngữ cảnh. |
| **Micro Event** | Tick, dwell, app_open ghi qua POST behavior-events; task_tick_at khi toggle/confirm. |
| **Temporal** | Variance, drift, streak risk → dùng trong Trust + Policy. |
| **Cognitive Load (CLI)** | Từ task/dwell → snapshot CLI; stage có thể chọn micro_goal khi CLI cao. |
| **Probabilistic Truth** | Mỗi tick → estimate P(real); < ngưỡng → modal xác nhận; confirm ghi P=1.0. |
| **Adaptive Trust** | Sau mỗi tick/confirm: update trust (execution, honesty, consistency); theo program_id khi có. |
| **Anomaly** | Lệch so baseline → log; policy sync có thể đổi mode. |
| **Recovery** | Ngày kỳ vọng làm nhưng không completed → recovery state; ổn định N ngày → cải thiện trust. |
| **Internalization** | Task lặp ổn định → internalized_at; giảm nhắc khi reduced_reminder. |
| **Projection** | P(duy trì 60d/90d), suggestion; hiển thị trên trang khi có. |
| **Policy** | behavior_user_policy (normal | micro_goal | reduced_reminder); banner đề xuất + 3 nút phản hồi. |
| **Coaching meta-learning** | Hiệu quả từng loại can thiệp → ưu tiên type hiệu quả, giảm type kém. |

### 5.2 Thể hiện trên giao diện

- **Layout theo stage:** Focus (1 việc, tối giản) → Guided (KPI, program card) → Analytic (Trust, CLI, 60d, Còn hôm nay) → Strategic (đa program, 90d, control).
- **Tin nhắn “Hệ thống hôm nay muốn nói”:** Từ CoachingNarrativeService (stage, tasksTodayCount, program done/total, integrity, suggestion).
- **Banner đề xuất:** Micro-goal hoặc Giảm nhắc + Áp dụng / Bỏ qua / Nhắc lại sau → policy_feedback → cập nhật trust.
- **Block dự báo:** Projection 60d (và 90d khi Strategic) khi đủ dữ liệu.
- **Program progress:** Integrity %, today_done/today_total, days_elapsed/days_total; cập nhật real-time sau tick instance/task.

### 5.3 Tóm tắt “thông minh”

- **Đo lường hành vi:** Tick sớm/trễ, batch tick, pattern hoàn thành → P(real), trust, recovery.
- **Thích nghi giao diện:** Không chỉ nội dung mà cấu trúc trang (số task hiển thị, độ chi tiết) theo stage.
- **Coaching có cơ sở:** Narrative từ số liệu (integrity, trust, projection), không chung chung; meta-learning điều chỉnh loại can thiệp.
- **Dữ liệu instance:** Mỗi ngày một bản ghi instance + completed_at → sau này có thể làm streak, completion rate, miss detection, AI insight (productivity window, overdue pattern) mà không cần đổi kiến trúc.

---

## 6. Kết luận

Trang `/cong-viec` vừa là **task list + Kanban + Program**, vừa là **điểm neo cho Behavior Intelligence**: template + instance, lazy generate, gate P(real), trust và policy, layout và narrative thích nghi.

**Đánh giá so sánh:** So theo task features (calendar, rrule, reminder, subtask): **7/10** — thiếu calendar thật, rrule, reminder engine, subtask. So theo behavior intelligence: **9/10** — vượt nhiều nền tảng.

**Kết luận chiến lược:** Hệ thống này không nên cố trở thành Todoist clone. Nó nên trở thành **Behavior Operating System** với **task là dữ liệu đầu vào**. Thứ tự nâng cấp nên theo impact/effort: Tier 1 (Calendar thật, Reminder engine, Recovery UI) → Tier 2 (Subtask, Natural language) → Tier 3 (RRule, Auto scheduling); đồng thời đẩy mạnh behavior (anomaly trigger, habit stability, recovery, identity reinforcement). Chi tiết lộ trình: `docs/BEHAVIOR_OS_ROADMAP.md`.
