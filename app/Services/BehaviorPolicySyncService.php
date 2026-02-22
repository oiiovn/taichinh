<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BehaviorPolicySyncService
{
    /**
     * Đồng bộ policy cho một user (CLI, recovery, internalized) → mode.
     * Gọi ngay sau aggregate hoặc theo lịch.
     */
    public function syncUser(int $userId): void
    {
        $cliHigh = (float) config('behavior_intelligence.policy.cli_high_threshold', 0.7);
        $mode = 'normal';
        $strictness = 'normal';

        $cliRow = DB::table('behavior_cognitive_snapshots')
            ->where('user_id', $userId)
            ->orderByDesc('snapshot_date')
            ->first();
        $cli = $cliRow ? (float) $cliRow->cli : 0;
        if ($cli >= $cliHigh) {
            $mode = 'micro_goal';
        }

        $recovery = app(RecoveryIntelligenceService::class);
        if ($recovery->isSlowRecovery($userId)) {
            $mode = 'micro_goal';
        }

        $internalizedCount = DB::table('work_tasks')
            ->where('user_id', $userId)
            ->whereNotNull('internalized_at')
            ->count();
        $reducedReminderEligible = $internalizedCount > 0;
        if ($reducedReminderEligible && $mode === 'normal') {
            $mode = 'reduced_reminder';
        }

        $microGoalEligible = $cli >= $cliHigh || $recovery->isSlowRecovery($userId);
        if (config('behavior_intelligence.coaching_effectiveness.enabled', true)) {
            $eff = app(CoachingEffectivenessService::class);
            $microGoalType = CoachingInterventionLogger::TYPE_POLICY_BANNER_MICRO_GOAL;
            $reducedType = CoachingInterventionLogger::TYPE_POLICY_BANNER_REDUCED_REMINDER;
            if ($mode === 'micro_goal' && $reducedReminderEligible && $eff->shouldPreferIntervention($userId, $reducedType) && ! $eff->shouldPreferIntervention($userId, $microGoalType)) {
                $mode = 'reduced_reminder';
            } elseif ($mode === 'reduced_reminder' && $microGoalEligible && $eff->shouldPreferIntervention($userId, $microGoalType) && ! $eff->shouldPreferIntervention($userId, $reducedType)) {
                $mode = 'micro_goal';
            }
        }

        try {
            DB::table('behavior_user_policy')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'mode' => $mode,
                    'strictness_level' => $strictness,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('BehaviorPolicySyncService syncUser failed for user ' . $userId . ': ' . $e->getMessage());
        }
    }
}
