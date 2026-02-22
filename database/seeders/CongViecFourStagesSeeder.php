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
 * Seed 4 user mẫu tương ứng 4 cấp độ layout tổng quan:
 * fragile→focus, stabilizing→guided, internalized→analytic, mastery→strategic.
 */
class CongViecFourStagesSeeder extends Seeder
{
    private const USERS = [
        'stage-fragile@fi-test.local' => [
            'name' => 'Mẫu Fragile (Focus)',
            'stage' => 'fragile',
            'trust' => 0.40,
            'cli' => 0.35,
            'recovery_days' => 8,
            'variance_score' => 0.60,
            'program_count' => 0,
        ],
        'stage-stabilizing@fi-test.local' => [
            'name' => 'Mẫu Stabilizing (Guided)',
            'stage' => 'stabilizing',
            'trust' => 0.55,
            'cli' => 0.52,
            'recovery_days' => 2,
            'variance_score' => 0.38,
            'program_count' => 1,
        ],
        'stage-internalized@fi-test.local' => [
            'name' => 'Mẫu Internalized (Analytic)',
            'stage' => 'internalized',
            'trust' => 0.72,
            'cli' => 0.75,
            'recovery_days' => 2,
            'variance_score' => 0.25,
            'program_count' => 1,
        ],
        'stage-mastery@fi-test.local' => [
            'name' => 'Mẫu Mastery (Strategic)',
            'stage' => 'mastery',
            'trust' => 0.76,
            'cli' => 0.78,
            'recovery_days' => 2,
            'variance_score' => 0.20,
            'program_count' => 2,
        ],
    ];

    public function run(): void
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');

        foreach (self::USERS as $email => $config) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $config['name'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
            $user->name = $config['name'];
            $user->save();

            $userId = $user->id;
            $this->seedTrust($userId, $config['trust']);
            $this->seedRecovery($userId, $config['recovery_days']);
            $this->seedCognitiveSnapshots($userId, $config['cli']);
            $this->seedTemporalAggregates($userId, $config['variance_score']);

            $programs = [];
            for ($i = 0; $i < $config['program_count']; $i++) {
                $programs[] = $this->seedProgram($userId, $i + 1);
            }

            $this->seedTasks($userId, $today, $programs, $config['stage']);
            $this->seedBehaviorInterfaceState($userId, $config['stage']);
            $this->seedPolicy($userId, $config['stage']);
        }
    }

    private function seedTrust(int $userId, float $trust): void
    {
        $avg = round($trust, 4);
        $values = [
            'trust_execution' => $avg,
            'trust_honesty' => $avg,
            'trust_consistency' => $avg,
            'updated_at' => now(),
            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
        ];
        $keys = ['user_id' => $userId];
        if (Schema::hasTable('behavior_trust_gradients') && Schema::hasColumn('behavior_trust_gradients', 'program_id')) {
            $keys['program_id'] = null;
        }
        DB::table('behavior_trust_gradients')->updateOrInsert($keys, $values);
    }

    private function seedRecovery(int $userId, int $recoveryDays): void
    {
        if (! Schema::hasTable('behavior_recovery_state')) {
            return;
        }
        $keys = ['user_id' => $userId];
        if (Schema::hasColumn('behavior_recovery_state', 'program_id')) {
            $keys['program_id'] = null;
        }
        DB::table('behavior_recovery_state')->updateOrInsert(
            $keys,
            [
                'last_fail_at' => Carbon::now()->subDays($recoveryDays + 5)->format('Y-m-d'),
                'recovery_days' => $recoveryDays,
                'streak_after_recovery' => max(1, 5 - $recoveryDays),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    private function seedCognitiveSnapshots(int $userId, float $cli): void
    {
        if (! Schema::hasTable('behavior_cognitive_snapshots')) {
            return;
        }
        $base = Carbon::now()->subDays(10);
        $keysBase = ['user_id' => $userId];
        if (Schema::hasColumn('behavior_cognitive_snapshots', 'program_id')) {
            $keysBase['program_id'] = null;
        }
        foreach (range(0, 10) as $i) {
            $date = $base->copy()->addDays($i)->format('Y-m-d');
            DB::table('behavior_cognitive_snapshots')->updateOrInsert(
                array_merge($keysBase, ['snapshot_date' => $date]),
                [
                    'cli' => $cli,
                    'new_tasks_count' => 2,
                    'active_tasks_count' => 5,
                    'active_minutes' => 90,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    private function seedTemporalAggregates(int $userId, float $varianceScore): void
    {
        if (! Schema::hasTable('behavior_temporal_aggregates')) {
            return;
        }
        $end = Carbon::now()->startOfWeek();
        $start = $end->copy()->subDays(7);
        $keys = [
            'user_id' => $userId,
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
        ];
        if (Schema::hasColumn('behavior_temporal_aggregates', 'program_id')) {
            $keys['program_id'] = null;
        }
        DB::table('behavior_temporal_aggregates')->updateOrInsert(
            $keys,
            [
                'variance_score' => $varianceScore,
                'drift_slope' => null,
                'streak_risk' => $varianceScore > 0.5 ? 'high' : 'low',
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    private function seedProgram(int $userId, int $index): BehaviorProgram
    {
        $start = Carbon::now()->subDays(20)->format('Y-m-d');
        $title = $index === 1 ? 'Chương trình chính' : 'Chương trình phụ';
        $program = BehaviorProgram::firstOrCreate(
            [
                'user_id' => $userId,
                'title' => $title . ' – User ' . $userId,
            ],
            [
                'description' => 'Seed cho mẫu 4 stage.',
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

    private function seedTasks(int $userId, string $today, array $programs, string $stage): void
    {
        CongViecTask::where('user_id', $userId)->delete();
        $todayCarbon = Carbon::parse($today);
        $program1 = $programs[0] ?? null;
        $program2 = $programs[1] ?? null;

        foreach (range(0, 4) as $i) {
            $due = $todayCarbon->copy()->addDays($i);
            $prog = ($i % 2 === 0 && $program1) ? $program1 : (($i % 2 === 1 && $program2) ? $program2 : $program1);
            CongViecTask::create([
                'user_id' => $userId,
                'project_id' => null,
                'title' => 'Task hôm nay ' . ($i + 1),
                'due_date' => $due->format('Y-m-d'),
                'due_time' => $i === 0 ? '09:00' : null,
                'completed' => false,
                'kanban_status' => 'this_cycle',
                'program_id' => $prog?->id,
            ]);
        }

        if ($program1) {
            $start = Carbon::parse($program1->start_date);
            $end = Carbon::parse($program1->getEndDateResolved());
            $daysTotal = $start->diffInDays($end) + 1;
            $needCompletion = (int) ceil($daysTotal * 0.75);
            foreach (range(0, $needCompletion - 1) as $i) {
                $d = $start->copy()->addDays((int) floor($i * ($daysTotal / max(1, $needCompletion))));
                if ($d->gt($todayCarbon)) {
                    continue;
                }
                $t = CongViecTask::create([
                    'user_id' => $userId,
                    'project_id' => null,
                    'title' => 'Đã xong ' . $d->format('d/m'),
                    'due_date' => $d->format('Y-m-d'),
                    'completed' => true,
                    'kanban_status' => 'done',
                    'program_id' => $program1->id,
                ]);
                $t->updated_at = $d->copy()->endOfDay();
                $t->saveQuietly();
            }
        }

        if ($program2 && $stage === 'mastery') {
            $start = Carbon::parse($program2->start_date);
            $end = Carbon::parse($program2->getEndDateResolved());
            $daysTotal = $start->diffInDays($end) + 1;
            $needCompletion = (int) ceil($daysTotal * 0.7);
            foreach (range(0, $needCompletion - 1) as $i) {
                $d = $start->copy()->addDays((int) floor($i * ($daysTotal / max(1, $needCompletion))));
                if ($d->gt($todayCarbon)) {
                    continue;
                }
                $t = CongViecTask::create([
                    'user_id' => $userId,
                    'project_id' => null,
                    'title' => 'P2 done ' . $d->format('d/m'),
                    'due_date' => $d->format('Y-m-d'),
                    'completed' => true,
                    'kanban_status' => 'done',
                    'program_id' => $program2->id,
                ]);
                $t->updated_at = $d->copy()->endOfDay();
                $t->saveQuietly();
            }
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
            'probabilities' => json_encode(['60d' => 0.7, '90d' => 0.65]),
            'risk_weeks' => json_encode([]),
            'suggestion' => 'Mẫu dữ liệu cho 4 stage.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBehaviorInterfaceState(int $userId, string $stage): void
    {
        if (! Schema::hasTable('behavior_interface_state')) {
            return;
        }
        DB::table('behavior_interface_state')->updateOrInsert(
            ['user_id' => $userId],
            ['last_stage' => $stage, 'last_stage_at' => now(), 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
        );
    }

    private function seedPolicy(int $userId, string $stage): void
    {
        if (! Schema::hasTable('behavior_user_policy')) {
            return;
        }
        $mode = $stage === 'fragile' ? 'micro_goal' : ($stage === 'mastery' ? 'reduced_reminder' : 'normal');
        DB::table('behavior_user_policy')->updateOrInsert(
            ['user_id' => $userId],
            ['mode' => $mode, 'strictness_level' => 'normal', 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
        );
    }
}
