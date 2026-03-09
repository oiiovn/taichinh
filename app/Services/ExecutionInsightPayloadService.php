<?php

namespace App\Services;

use App\Services\BehaviorStageClassifier;

/**
 * Behavior Intelligence → Insight Payload → Narrative Generator.
 * Tách payload (data) khỏi narrative (message) để dễ thay bằng LLM, testable, analytics.
 *
 * @return array{
 *   behavior_profile: array|null,
 *   failure_detection: array|null,
 *   priority_summary: array|null,
 *   tasks_today_count: int,
 *   today_program_task_total: int,
 *   today_program_task_done: int,
 *   active_program_id: int|null,
 *   integrity_pct: int|null,
 *   trust_pct: int|null,
 *   suggestion: string|null,
 *   stage: string,
 *   interface_adaptation: array
 * }
 */
class ExecutionInsightPayloadService
{
    public function build(
        ?array $behaviorProfile,
        ?array $failureDetection,
        int $tasksTodayCount,
        int $todayProgramTaskTotal,
        int $todayProgramTaskDone,
        ?object $activeProgram = null,
        ?array $activeProgramProgress = null,
        ?array $behaviorRadar = null,
        ?array $behaviorProjection = null,
        ?array $interfaceAdaptation = null
    ): array {
        $integrity = $activeProgramProgress['integrity_score'] ?? null;
        $integrityPct = $integrity !== null ? (int) round($integrity * 100) : null;
        $trust = $behaviorRadar['trust_global'] ?? null;
        $trustPct = $trust !== null ? (int) round($trust * 100) : null;
        $suggestion = (is_array($behaviorProjection) && ! empty($behaviorProjection['suggestion']))
            ? $behaviorProjection['suggestion']
            : null;
        $stage = $interfaceAdaptation['stage'] ?? BehaviorStageClassifier::STAGE_STABILIZING;

        $prioritySummary = null;
        if ($behaviorProfile !== null || $failureDetection !== null) {
            $prioritySummary = [
                'profile' => $behaviorProfile['profile'] ?? null,
                'risk_tier' => $failureDetection['risk_tier'] ?? 'normal',
                'risk_score' => $failureDetection['risk_score'] ?? 0.0,
            ];
        }

        $executionStage = $this->deriveExecutionStage($failureDetection, $behaviorProfile);

        return [
            'behavior_profile' => $behaviorProfile,
            'failure_detection' => $failureDetection,
            'priority_summary' => $prioritySummary,
            'tasks_today_count' => $tasksTodayCount,
            'today_program_task_total' => $todayProgramTaskTotal,
            'today_program_task_done' => $todayProgramTaskDone,
            'active_program_id' => $activeProgram?->id ?? null,
            'integrity_pct' => $integrityPct,
            'trust_pct' => $trustPct,
            'suggestion' => $suggestion,
            'stage' => $stage,
            'execution_stage' => $executionStage,
            'interface_adaptation' => $interfaceAdaptation ?? [],
        ];
    }

    /** execution_stage: planning | execution | overload | recovery — cho narrative đúng ngữ cảnh. */
    protected function deriveExecutionStage(?array $failureDetection, ?array $behaviorProfile): string
    {
        $riskScore = $failureDetection['risk_score'] ?? 0.0;
        $riskTier = $failureDetection['risk_tier'] ?? 'normal';
        $completionRate30 = $behaviorProfile['completion_rate_30d'] ?? 0.0;

        if ($riskScore > 0.6) {
            return 'overload';
        }
        if ($completionRate30 >= 0.8 && $riskTier === 'normal') {
            return 'execution';
        }
        if ($completionRate30 < 0.5 && in_array($riskTier, ['warning', 'collapse'], true)) {
            return 'recovery';
        }
        return 'planning';
    }
}
