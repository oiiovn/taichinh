# Behavior Intelligence Doc — Tài liệu Stack Hành vi (9 tầng)

## 1. Mục đích

Tài liệu mô tả **Behavior Intelligence Stack** cho module Công việc: luồng Identity → Behavior → Pattern → Trust → Projection → Adjustment. Hệ thống so sánh user với **baseline của chính họ**, không so với user khác.

## 2. Cấu hình

- **File:** `config/behavior_intelligence.php`
- **Feature flags:** `layers.identity_baseline`, `layers.micro_event_capture`, … (bật/tắt từng tầng).
- **Ngưỡng:** P(real) yêu cầu xác nhận &lt; 0.7; tự chấp nhận ≥ 0.9; recovery ổn định 3 ngày; internalized giảm nhắc 70%; anomaly 2 sigma; projection cần ≥ 90 ngày dữ liệu.

## 3. Các tầng

| Tầng | Mô tả ngắn | Dữ liệu / Service |
|------|------------|-------------------|
| **0 Identity Baseline** | BSV từ chronotype, giờ ngủ, trì hoãn, áp lực | `behavior_identity_baselines`, `BehaviorIdentityBaselineService` |
| **1 Micro Event** | Event vi mô: app_open, task_tick_at, dwell_ms, scroll_count | `behavior_events`, `MicroEventCaptureService`, consent `users.behavior_events_consent` |
| **2 Temporal** | Variance, drift, streak risk | `TemporalConsistencyService`, `behavior_temporal_aggregates` |
| **3 Cognitive Load** | CLI từ task mới, active, dwell | `CognitiveLoadEstimatorService`, `behavior_cognitive_snapshots` |
| **4 Probabilistic Truth** | P(real_completion \| behavior), ngưỡng xác nhận | `ProbabilisticTruthService` |
| **5 Adaptive Trust** | 3 trục: execution, honesty, consistency | `AdaptiveTrustGradientService`, `behavior_trust_gradients` |
| **6 Anomaly** | Lệch &gt; 2 sigma so với baseline tuần | `BehavioralAnomalyDetectorService`, `behavior_anomaly_logs` |
| **7 Recovery** | Thời gian từ fail đến ổn định | `RecoveryIntelligenceService`, `behavior_recovery_state` |
| **8 Internalization** | Task lặp ổn định → internalized_at, giảm nhắc | `HabitInternalizationService`, `work_tasks.internalized_at` |
| **9 Projection** | Xác suất duy trì 60/90 ngày | `LongTermProjectionService`, `behavior_projection_snapshots` |
| **Program** | Tầng trên Task: chương trình = mục tiêu + thời hạn + quy tắc | `behavior_programs`, `work_tasks.program_id`, `BehaviorProgramProgressService`, trust/aggregate theo `program_id` |
| **Coaching Meta-learning** | Hiệu quả từng loại can thiệp → ưu tiên type hiệu quả, giảm type kém | `coaching_intervention_events`, `CoachingInterventionLogger`, `CoachingEffectivenessService`, `CoachingEffectivenessOutcomeJob` |

## 4. Luồng dữ liệu

- **Event:** Frontend (Công việc) gửi POST `/cong-viec/behavior-events` (batch). Chỉ ghi khi user có `behavior_events_consent`. Event `policy_feedback` (accepted/ignored/rejected) cập nhật trust qua `PolicyFeedbackService`.
- **Tick → Intelligence Gate:** `toggleComplete` gọi `ProbabilisticTruthService::estimate()` trước khi lưu; nếu P(real) &lt; ngưỡng → trả về `require_confirmation`, frontend hiển thị modal; xác nhận qua POST `confirm-complete` → lưu, ghi event, cập nhật trust.
- **Trust real-time:** Mỗi tick/confirm gọi `AdaptiveTrustGradientService::update()` (P(real), completion rate 7 ngày, variance/recovery từ snapshot). Nếu task có `program_id` thì cập nhật cả trust toàn cục (program_id null) và trust theo program.
- **Program:** Task có thể gắn `program_id` (BehaviorProgram). Trust, temporal aggregate hỗ trợ dimension theo program (bảng có `program_id` nullable). `BehaviorProgramProgressService` tính completion rate, integrity score, tiến độ theo chương trình.
- **Aggregate:** Job `BehaviorIntelligenceAggregateJob` chạy hàng ngày 02:30: tính temporal, CLI, recovery, anomaly, internalization; **ngay sau mỗi user** gọi `BehaviorPolicySyncService::syncUser()` (policy không chờ 03:00).
- **Policy:** Job `BehaviorPolicySyncJob` chạy 03:00: cập nhật `behavior_user_policy` (mode: normal | micro_goal | reduced_reminder) qua `BehaviorPolicySyncService`.
- **UI:** Trang Công việc nhận `behaviorPolicy`, `behaviorProjection`; hiển thị đề xuất (micro-goal / giảm nhắc) với 3 nút phản hồi (Áp dụng, Bỏ qua, Nhắc lại sau) và block dự báo kỷ luật khi có đủ dữ liệu.

## 5. Công thức tóm tắt

- **BSV:** Vector 5 thành phần từ form (chronotype, sleep_stability, energy_amplitude, procrastination, stress), chuẩn hóa scale -1..1.
- **CLI:** Heuristic từ new_tasks, active_tasks, active_minutes (dwell); cao → micro_goal.
- **Recovery:** Fail = ngày kỳ vọng làm (due/repeat) nhưng không completed; ổn định = N ngày liên tiếp hoàn thành (N=3).
- **Trust gradient:** EWMA 3 trục từ completion rate, P(real), variance/recovery.
- **Projection:** Cần ≥ 90 ngày dữ liệu; P(duy trì 60d/90d) từ trust, CLI, recovery (heuristic/survival đơn giản).

## 6. Kế hoạch

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|------------|--------|
| 1 | **Real-time Truth Hook** | ✅ Done | Tick → ProbabilisticTruth trước lưu; require_confirmation + modal; confirm-complete; backend ghi event + trust. |
| 2 | **Trust Delta** | ✅ Done | Mỗi tick/confirm gọi AdaptiveTrustGradientService::update (P(real), completion rate, variance/recovery). |
| 3 | **Policy Feedback (Reaction Loop)** | ✅ Done | Event `policy_feedback`; 3 nút Áp dụng/Bỏ qua/Nhắc lại sau; PolicyFeedbackService cập nhật trust. |
| 4 | **Policy sync ngay** | ✅ Done | BehaviorPolicySyncService; gọi syncUser() trong AggregateJob sau mỗi user (anomaly/recovery/internalization). |
| 5 | Bảng `behavior_policy_feedback` (optional) | Tùy chọn | Ghi log feedback để tuning policy/CLI sau này. |
| 6 | Immediate downgrade khi anomaly nặng (trong request) | Chưa | Hiện policy sync chạy trong job 02:30; có thể thêm trigger real-time khi phát hiện anomaly nặng. |
| 7 | **Behavior Program (tầng trên Task)** | ✅ Done | Model BehaviorProgram; work_tasks.program_id; trust/aggregate theo program_id; BehaviorProgramProgressService (integrity, tiến độ); UI tạo/xem chương trình, gắn task vào program. |

## 7. Tín hiệu ẩn từ task (Behavioral signal)

Hệ thống không chỉ dựa vào “tạo task” và “tick hoàn thành”. Các tín hiệu có thể khai thác:

| Tín hiệu | Ý nghĩa (xu hướng) |
|----------|---------------------|
| Thời điểm tạo | Tạo nhiều ban đêm → kế hoạch cảm xúc |
| Tần suất tạo | Nhiều tạo, ít làm → overcommit |
| Cách sửa / độ cụ thể tiêu đề | Dài, chi tiết → thiên cấu trúc; ngắn, mơ hồ → thiên ý tưởng |
| Độ trễ tick | Sớm hơn deadline → proactive; sát deadline → pressure-driven; trễ đều → drift |
| Pattern lặp, tỷ lệ undo | Undo nhiều → integrity issue; batch tick cuối ngày → batch compensation |
| Task tạo nhưng không bao giờ làm | Overcommit / avoidance |
| Phản ứng với gợi ý (policy_feedback, coaching) | Học kiểu can thiệp hiệu quả → meta-learning |

Từ đó hệ thống có thể suy ra: kỷ luật, ổn định, khả năng phục hồi, xu hướng trì hoãn, overcommit, tính trung thực. **Không** suy ra: cảm xúc sâu, nguyên nhân cá nhân, áp lực bên ngoài. Coaching phải dựa trên **xác suất**, không khẳng định.

## 8. Ba cấp độ coaching

| Mức | Tên | Mô tả |
|-----|-----|--------|
| **1 – Phản ánh (Mirror)** | Chỉ phản ánh, không phán xét | VD: “Bạn thường tạo 5 việc mỗi tối nhưng chỉ hoàn thành 2 việc hôm sau.” |
| **2 – Nhận diện mẫu (Pattern)** | Coaching cấp 1 | VD: “Bạn hoàn thành tốt hơn khi mục tiêu ≤ 2 việc/ngày.” |
| **3 – Tự hỗ trợ (Auto-assist)** | Chỉ khi trust_execution + trust_honesty đủ cao | Gợi ý tick task lặp đã internalized; chia task dài; dời deadline trước khi trượt. |

## 9. Auto-check (check hộ) — điều kiện bắt buộc

Có thể cho phép hệ thống “ghi nhận hoàn thành hộ” **chỉ khi**:

1. Chỉ với **task lặp đã internalized**.
2. **P(real) > 0,9** liên tục (trust đủ cao).
3. **Undo dễ dàng** (1 thao tác hoàn tác).
4. **Thông báo rõ:** “Tôi đã ghi nhận việc này như thường lệ. Nếu chưa đúng, hãy hoàn tác.”

Đây là automation dựa trên niềm tin; vi phạm điều kiện thì không triển khai.

## 10. Changelog

| Ngày | Thay đổi |
|------|----------|
| 2026-02-22 | Thêm: Tín hiệu ẩn từ task, 3 cấp coaching, điều kiện auto-check, hàng Coaching Meta-learning; tài liệu đồng bộ với vision. |
| 2026-03-01 | Triển khai 9 tầng: config, migrations, services, jobs, UI đề xuất và dự báo. |
| 2026-02-21 | Bốn hook chiến lược: Real-time Truth Gate, Trust Delta, Policy Feedback, Policy sync ngay; cập nhật luồng và Kế hoạch. |
| 2026-02-21 | Tầng Program: behavior_programs, program_id trên task, trust/aggregate theo program, BehaviorProgramProgressService, UI Chương trình. |

---
*Cập nhật lần cuối: 2026-02-22.*
