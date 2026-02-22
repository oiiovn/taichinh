<?php

namespace Database\Seeders;

use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed nhiều mô phỏng cho dataset3@fi-test.local: task, program, trust, recovery, CLI
 * để trang Tổng quan / Interface Adaptation hiển thị đầy đủ.
 */
class CongViecBehaviorDataset3Seeder extends Seeder
{
    private const EMAIL = 'dataset3@fi-test.local';

    public function run(): void
    {
        $user = User::where('email', self::EMAIL)->first();
        if (! $user) {
            $user = User::create([
                'name' => 'FI Test – High debt (Công việc mô phỏng)',
                'email' => self::EMAIL,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        $userId = $user->id;
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');

        $this->seedTrustAndRecovery($userId);
        $this->seedCognitiveSnapshots($userId);
        $this->seedTemporalAggregates($userId);
        $program = $this->seedProgram($userId);
        $this->seedTasks($userId, $today, $program);
        $this->seedBehaviorInterfaceState($userId);
    }

    private function seedTrustAndRecovery(int $userId): void
    {
        DB::table('behavior_trust_gradients')->updateOrInsert(
            ['user_id' => $userId],
            [
                'trust_execution' => 0.72,
                'trust_honesty' => 0.68,
                'trust_consistency' => 0.75,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        if (Schema::hasTable('behavior_recovery_state')) {
            DB::table('behavior_recovery_state')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'last_fail_at' => Carbon::now()->subDays(14)->format('Y-m-d'),
                    'recovery_days' => 2,
                    'streak_after_recovery' => 12,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    private function seedCognitiveSnapshots(int $userId): void
    {
        if (! Schema::hasTable('behavior_cognitive_snapshots')) {
            return;
        }
        $base = Carbon::now()->subDays(10);
        foreach (range(0, 10) as $i) {
            $date = $base->copy()->addDays($i)->format('Y-m-d');
            DB::table('behavior_cognitive_snapshots')->updateOrInsert(
                ['user_id' => $userId, 'snapshot_date' => $date],
                [
                    'cli' => 0.55 + $i * 0.02,
                    'new_tasks_count' => 2,
                    'active_tasks_count' => 5,
                    'active_minutes' => 90,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    private function seedTemporalAggregates(int $userId): void
    {
        if (! Schema::hasTable('behavior_temporal_aggregates')) {
            return;
        }
        $end = Carbon::now()->startOfWeek();
        $start = $end->copy()->subDays(7);
        DB::table('behavior_temporal_aggregates')->updateOrInsert(
            [
                'user_id' => $userId,
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
            ],
            [
                'variance_score' => 0.25,
                'drift_slope' => null,
                'streak_risk' => 'low',
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    private function seedProgram(int $userId): BehaviorProgram
    {
        $start = Carbon::now()->subDays(15)->format('Y-m-d');
        $program = BehaviorProgram::firstOrCreate(
            [
                'user_id' => $userId,
                'title' => 'Mô phỏng 30 ngày – Dataset3',
            ],
            [
                'description' => 'Chương trình seed để test Tổng quan.',
                'start_date' => $start,
                'end_date' => Carbon::parse($start)->addDays(30)->format('Y-m-d'),
                'duration_days' => 30,
                'archetype' => BehaviorProgram::ARCHETYPE_BINARY,
                'difficulty_level' => 2,
                'status' => BehaviorProgram::STATUS_ACTIVE,
                'escalation_rule' => BehaviorProgram::ESCALATION_ALLOW_2_SKIPS,
                'skip_policy' => BehaviorProgram::SKIP_POLICY_ALLOW_2,
            ]
        );

        return $program;
    }

    private function seedTasks(int $userId, string $today, BehaviorProgram $program): void
    {
        $todayCarbon = Carbon::parse($today);

        CongViecTask::where('user_id', $userId)->delete();

        $titles = [
            'Review báo cáo tuần',
            'Gọi khách hàng A',
            'Cập nhật backlog',
            'Học 30 phút',
            'Tập thể dục 15 phút',
            'Viết báo cáo tháng',
            'Meeting nội bộ',
            'Đọc tài liệu kỹ thuật',
        ];

        foreach (range(0, 4) as $i) {
            $due = $todayCarbon->copy()->addDays($i);
            $dueDate = $due->format('Y-m-d');
            $programId = ($i % 2 === 0) ? $program->id : null;
            CongViecTask::create([
                'user_id' => $userId,
                'project_id' => null,
                'title' => $titles[$i] ?? "Task mô phỏng " . ($i + 1),
                'due_date' => $dueDate,
                'due_time' => $i === 0 ? '09:00' : ($i === 1 ? '14:00' : null),
                'completed' => $i === 0 && rand(0, 1),
                'kanban_status' => $i === 0 ? 'in_progress' : 'this_cycle',
                'program_id' => $programId,
            ]);
        }

        foreach (range(1, 8) as $i) {
            $due = $todayCarbon->copy()->addDays($i + 5);
            CongViecTask::create([
                'user_id' => $userId,
                'title' => "Dự kiến task " . $i,
                'due_date' => $due->format('Y-m-d'),
                'completed' => false,
                'kanban_status' => 'backlog',
                'program_id' => $i <= 2 ? $program->id : null,
            ]);
        }

        foreach (range(1, 12) as $i) {
            $past = $todayCarbon->copy()->subDays($i);
            CongViecTask::create([
                'user_id' => $userId,
                'title' => "Đã xong ngày " . $past->format('d/m'),
                'due_date' => $past->format('Y-m-d'),
                'completed' => true,
                'kanban_status' => 'done',
                'program_id' => $i % 3 === 0 ? $program->id : null,
            ]);
        }

        for ($d = 15; $d <= 90; $d += 5) {
            $past = $todayCarbon->copy()->subDays($d);
            CongViecTask::create([
                'user_id' => $userId,
                'title' => 'Lịch sử mô phỏng ' . $past->format('Y-m-d'),
                'due_date' => $past->format('Y-m-d'),
                'completed' => true,
                'kanban_status' => 'done',
                'program_id' => null,
            ]);
        }

        $this->seedProjectionSnapshot($userId);
    }

    private function seedProjectionSnapshot(int $userId): void
    {
        if (! Schema::hasTable('behavior_projection_snapshots')) {
            return;
        }
        DB::table('behavior_projection_snapshots')->where('user_id', $userId)->delete();
        DB::table('behavior_projection_snapshots')->insert([
            'user_id' => $userId,
            'snapshot_at' => now(),
            'probabilities' => json_encode(['60d' => 0.78, '90d' => 0.72]),
            'risk_weeks' => json_encode([]),
            'suggestion' => 'Với mô hình hiện tại, xác suất duy trì 60 ngày tiếp theo khoảng 78%.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBehaviorInterfaceState(int $userId): void
    {
        if (! Schema::hasTable('behavior_interface_state')) {
            return;
        }
        DB::table('behavior_interface_state')->updateOrInsert(
            ['user_id' => $userId],
            ['last_stage' => 'stabilizing', 'last_stage_at' => now(), 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
        );
    }
}
