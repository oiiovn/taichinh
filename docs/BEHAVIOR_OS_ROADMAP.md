# Behavior OS — Lộ trình phát triển

Tài liệu lộ trình: **Task Manager** → **Behavior Operating System**.  
Thứ tự nâng cấp theo **impact / effort**, không theo feature.

---

## 1. Câu kết luận chiến lược

**Hệ thống này không nên cố trở thành Todoist clone.**  
Nó nên trở thành **Behavior Operating System** với **task là dữ liệu đầu vào**.

- So theo **task features** (calendar, rrule, reminder, subtask…): **7/10** — thiếu calendar thật, rrule, reminder engine, subtask.
- So theo **behavior intelligence**: **9/10** — vượt nhiều nền tảng (P(real), trust, layout thích nghi, coaching, policy, projection).

---

## 2. Kiến trúc dài hạn

```
Task Layer          (work_tasks, work_task_instances — dữ liệu đầu vào)
      ↓
Behavior Data       (events, trust, temporal, anomaly, recovery)
      ↓
Metrics Engine      (streak, completion_rate, habit_strength, avg_delay)
      ↓
Prediction          (P(real), projection 60d/90d, risk)
      ↓
Coaching System     (trigger, message, recovery UI, identity reinforcement)
```

**Đã có:** Task Layer đầy đủ (template + instance, lazy generate); Behavior Data (9 tầng); Metrics (TaskStreakService, completion rate); Prediction (ProbabilisticTruth, LongTermProjection); Coaching (narrative, policy, layout thích nghi). **Thiếu:** Calendar thật, Reminder engine, Recovery UI hoàn chỉnh, Metrics materialize (task_metrics), Coaching trigger theo anomaly/recovery.

---

## 3. Thứ tự nâng cấp đúng (impact / effort)

### Tier 1 — Cực quan trọng

| # | Hạng mục | Mô tả | Công việc |
|---|----------|--------|-----------|
| 1 | **Calendar thật** | Month grid + week grid; xem instance theo ngày; click ngày → task. | Controller + view calendar; query instances trong range; ensure instances cho range (lazy/batch). |
| 2 | **Reminder engine** | Nhắc đúng lúc (push / email). | Queue job (schedule); bảng hoặc logic reminder; tích hợp push (FCM/OneSignal) hoặc email; dựa trên due_time + remind_minutes_before. |
| 3 | **Recovery UI** | Missed tasks rõ ràng; gợi ý restart habit. | RecoverySuggestionService (N instance missed liên tiếp); banner/block "Reset habit" / "Bắt đầu lại từ ngày mai"; API ack reset (tuỳ chọn). |

### Tier 2 — Cải thiện UX

| # | Hạng mục | Mô tả | Công việc |
|---|----------|--------|-----------|
| 4 | **Subtask** | Task con trong task (checklist). | Bảng `work_task_children` hoặc `task_subtasks`; CRUD + hiển thị trong form/row; optional: instance cho subtask hoặc chỉ complete parent. |
| 5 | **Natural language input** | Ví dụ: "Viết báo cáo ngày mai 9h". | Parser (ngày/giờ/tên) — rule-based hoặc LLM; map vào form task (title, due_date, due_time). |

### Tier 3 — Advanced

| # | Hạng mục | Mô tả | Công việc |
|---|----------|--------|-----------|
| 6 | **RRule** | RFC 5545; lặp phức tạp (thứ 2+4, ngày đầu tháng, weekday only). | Cột `work_tasks.rrule`; thư viện RRule (PHP); occursOn() ưu tiên rrule khi có. |
| 7 | **Auto scheduling** | AI gợi ý slot cho task (energy, deadline). | Energy/duration trên task; slot suggestion (productivity window từ metrics); optional: focus block. |

---

## 4. Phần mạnh nhất nên phát triển tiếp — Behavior Intelligence

Không cạnh tranh feature-for-feature với Todoist; **đẩy mạnh hành vi**:

| Hướng | Mô tả | Ví dụ |
|-------|--------|--------|
| **Behavior anomaly detection** | Bình thường hoàn thành task, hôm nay skip → trigger coaching. | Message: *"Bạn thường hoàn thành task này. Có gì thay đổi hôm nay?"* (TaskStreakService + instance skipped/pending). |
| **Habit stability score** | Chỉ số ổn định thói quen (habit_strength) từ streak + completion rate + variance. | Hiển thị trên task/program; dùng cho policy (micro_goal, reduced_reminder). |
| **Recovery system** | N ngày missed (vd. 3) → gợi ý reset thay vì dồn. | Logic đếm missed liên tiếp; UI "Bắt đầu lại từ ngày mai"; giảm overwhelm. |
| **Identity reinforcement** | Củng cố bản sắc hành vi. | Message kiểu: *"You are a consistent person"* khi streak/trust cao; narrative tích cực từ metrics. |

**Công việc gợi ý:** CoachingTriggerService (anomaly + completion_rate thấp); habit_strength trong metrics/task_metrics; Recovery UI (Tier 1); copy/template identity reinforcement trong CoachingNarrativeService.

---

## 5. Kiến trúc chi tiết (đã có / cần bổ sung)

**Đã có:**

- work_tasks, work_task_instances, EnsureTaskInstancesService.
- Tab Hoàn thành instance-based (Hôm nay / Hôm qua / Tuần này / Trước đó).
- occursOn + repeat_interval.
- TaskStreakService (streak, getCompletionRateInRange).
- 9 tầng behavior (Identity, Micro Event, Temporal, CLI, Probabilistic Truth, Trust, Anomaly, Recovery, Internalization, Projection, Policy, coaching meta-learning).

**Cần bổ sung (theo tier):**

- Tier 1: Calendar (month/week grid), Reminder (queue + push/email), Recovery UI.
- Tier 2: Subtask (task_children), Natural language input.
- Tier 3: RRule, Auto scheduling.
- Behavior: Coaching trigger (anomaly, completion_rate), habit_strength, identity reinforcement; task_metrics (materialize) cho prediction/coaching nhanh hơn.

---

## 6. Tóm tắt trạng thái

| Hạng mục | Trạng thái |
|----------|------------|
| Template + Instance, Tab Hoàn thành, repeat_interval, TaskStreakService | ✅ Done |
| **Tier 1:** Calendar thật | 📋 Roadmap |
| **Tier 1:** Reminder engine | 📋 Roadmap |
| **Tier 1:** Recovery UI | 📋 Roadmap |
| **Tier 2:** Subtask | 📋 Roadmap |
| **Tier 2:** Natural language input | 📋 Roadmap |
| **Tier 3:** RRule | 📋 Roadmap |
| **Tier 3:** Auto scheduling | 📋 Dài hạn |
| Behavior: Anomaly trigger, habit_strength, identity reinforcement | 📋 Phát triển tiếp |

---

*Cập nhật: 2026-03-07*
