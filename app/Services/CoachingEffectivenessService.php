<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meta-learning: đo hiệu quả từng loại can thiệp theo user (completion 3 ngày sau, recovery).
 * Dùng để ưu tiên message type hiệu quả, giảm loại kém hiệu quả.
 */
class CoachingEffectivenessService
{
    /**
     * Hiệu quả theo (user, intervention_type): điểm 0–1, càng cao càng nên dùng.
     *
     * @return array<string, float> intervention_type => score (0-1, 0.5 = chưa đủ dữ liệu)
     */
    public function getEffectivenessByUser(int $userId): array
    {
        if (! Schema::hasTable('coaching_intervention_events')) {
            return $this->defaultScores();
        }

        $rows = DB::table('coaching_intervention_events')
            ->where('user_id', $userId)
            ->whereNotNull('outcome_measured_at')
            ->whereNotNull('outcome_completion_3d')
            ->selectRaw('intervention_type, AVG(outcome_completion_3d) as avg_completion, COUNT(*) as cnt')
            ->groupBy('intervention_type')
            ->get();

        $scores = $this->defaultScores();
        foreach ($rows as $row) {
            $minSamples = 3;
            if ((int) $row->cnt >= $minSamples) {
                $scores[$row->intervention_type] = round((float) $row->avg_completion, 4);
            }
        }

        return $scores;
    }

    /**
     * Có nên ưu tiên loại can thiệp này cho user không (so với mức mặc định).
     */
    public function shouldPreferIntervention(int $userId, string $interventionType): bool
    {
        $eff = $this->getEffectivenessByUser($userId);
        $score = $eff[$interventionType] ?? 0.5;
        $avg = count($eff) > 0 ? array_sum($eff) / count($eff) : 0.5;

        return $score >= 0.5 && $score >= $avg;
    }

    /**
     * Loại can thiệp có hiệu quả thấp nhất (để giảm tần suất).
     */
    public function getLeastEffectiveType(int $userId): ?string
    {
        $eff = $this->getEffectivenessByUser($userId);
        $withData = array_filter($eff, fn ($s) => $s !== 0.5);
        if (empty($withData)) {
            return null;
        }
        $min = min($withData);
        $key = array_search($min, $eff, true);

        return $key !== false ? $key : null;
    }

    protected function defaultScores(): array
    {
        return [
            CoachingInterventionLogger::TYPE_POLICY_BANNER_MICRO_GOAL => 0.5,
            CoachingInterventionLogger::TYPE_POLICY_BANNER_REDUCED_REMINDER => 0.5,
            CoachingInterventionLogger::TYPE_LEVEL_UP_MESSAGE => 0.5,
            CoachingInterventionLogger::TYPE_TODAY_MESSAGE => 0.5,
            CoachingInterventionLogger::TYPE_INSIGHT_BLOCK => 0.5,
        ];
    }
}
