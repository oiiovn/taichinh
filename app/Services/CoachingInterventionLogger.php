<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meta-learning cho coaching: ghi nhận mỗi lần hiển thị can thiệp (message type) để sau đo hiệu quả.
 */
class CoachingInterventionLogger
{
    public const TYPE_POLICY_BANNER_MICRO_GOAL = 'policy_banner_micro_goal';
    public const TYPE_POLICY_BANNER_REDUCED_REMINDER = 'policy_banner_reduced_reminder';
    public const TYPE_LEVEL_UP_MESSAGE = 'level_up_message';
    public const TYPE_TODAY_MESSAGE = 'today_message';
    public const TYPE_INSIGHT_BLOCK = 'insight_block';

    /**
     * Ghi nhận can thiệp đã hiển thị. Gọi tối đa 1 lần/type/user/ngày để tránh spam.
     */
    public function logIfNotAlreadyToday(int $userId, string $interventionType, array $context = []): void
    {
        if (! Schema::hasTable('coaching_intervention_events')) {
            return;
        }
        $valid = [
            self::TYPE_POLICY_BANNER_MICRO_GOAL,
            self::TYPE_POLICY_BANNER_REDUCED_REMINDER,
            self::TYPE_LEVEL_UP_MESSAGE,
            self::TYPE_TODAY_MESSAGE,
            self::TYPE_INSIGHT_BLOCK,
        ];
        if (! in_array($interventionType, $valid, true)) {
            return;
        }

        try {
            $today = now()->format('Y-m-d');
            $exists = DB::table('coaching_intervention_events')
                ->where('user_id', $userId)
                ->where('intervention_type', $interventionType)
                ->whereDate('shown_at', $today)
                ->exists();
            if ($exists) {
                return;
            }
            DB::table('coaching_intervention_events')->insert([
                'user_id' => $userId,
                'intervention_type' => $interventionType,
                'context' => json_encode($context),
                'shown_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function log(int $userId, string $interventionType, array $context = []): void
    {
        $this->logIfNotAlreadyToday($userId, $interventionType, $context);
    }
}
