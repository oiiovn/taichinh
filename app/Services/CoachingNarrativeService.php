<?php

namespace App\Services;

use App\Services\BehaviorProfileService;
use App\Services\BehaviorStageClassifier;

/**
 * "Hệ thống hôm nay muốn nói điều gì quan trọng nhất."
 * Kiến trúc: Behavior Intelligence → ExecutionInsightPayload → Narrative Generator.
 * generateFromPayload() nhận payload để dễ thay bằng LLM, testable, analytics.
 */
class CoachingNarrativeService
{
    /**
     * Generate narrative từ Execution Insight Payload (payload → narrative layer).
     */
    public function generateFromPayload(
        array $insightPayload,
        ?int $userId = null,
        ?object $activeProgram = null,
        ?array $behaviorProjection = null
    ): array {
        $behaviorProfile = $insightPayload['behavior_profile'] ?? null;
        $failureDetection = $insightPayload['failure_detection'] ?? null;
        $stage = $insightPayload['stage'] ?? BehaviorStageClassifier::STAGE_STABILIZING;
        $integrityPct = $insightPayload['integrity_pct'] ?? null;
        $trustPct = $insightPayload['trust_pct'] ?? null;
        $suggestion = $insightPayload['suggestion'] ?? null;
        $tasksTodayCount = $insightPayload['tasks_today_count'] ?? 0;
        $todayProgramTaskTotal = $insightPayload['today_program_task_total'] ?? 0;
        $todayProgramTaskDone = $insightPayload['today_program_task_done'] ?? 0;
        $interfaceAdaptation = $insightPayload['interface_adaptation'] ?? [];

        $proj60 = isset($behaviorProjection['probability_maintain_60d']) ? (int) round($behaviorProjection['probability_maintain_60d'] * 100) : null;
        $integrityInterpretation = $this->interpretIntegrity($integrityPct, $stage);
        $todayMessage = $this->getTodayMessage($stage, $tasksTodayCount, $todayProgramTaskTotal, $todayProgramTaskDone, $integrityInterpretation, $suggestion, $failureDetection, $behaviorProfile);
        $emptyTodayCopy = $this->getEmptyTodayCopy($activeProgram, $todayProgramTaskTotal, $behaviorProfile);

        $leastEffectiveType = null;
        $coachingEffectivenessScores = [];
        if ($userId && config('behavior_intelligence.coaching_effectiveness.enabled', true)) {
            $effectiveness = app(CoachingEffectivenessService::class)->getEffectivenessByUser($userId);
            $coachingEffectivenessScores = $effectiveness;
            $leastEffectiveType = app(CoachingEffectivenessService::class)->getLeastEffectiveType($userId);
        }
        $sidebarNarrative = $this->getSidebarNarrative($stage, $integrityInterpretation, $suggestion, $activeProgram, $leastEffectiveType, $failureDetection, $behaviorProfile);
        $trustInterpretation = $this->interpretTrust($trustPct);

        return [
            'today_message' => $todayMessage,
            'integrity_interpretation' => $integrityInterpretation,
            'integrity_pct' => $integrityPct,
            'trust_interpretation' => $trustInterpretation,
            'trust_pct' => $trustPct,
            'phase_label' => $this->getPhaseLabel($stage),
            'empty_today_copy' => $emptyTodayCopy,
            'sidebar_narrative' => $sidebarNarrative,
            'projection_interpretation' => $proj60 !== null ? $this->interpretProjection($proj60) : null,
            'projection_60d' => $proj60,
            'coaching_effectiveness_scores' => $coachingEffectivenessScores,
            'collapse_risk_message' => $failureDetection['collapse_risk_message'] ?? null,
            'failure_suggestions' => $failureDetection['suggestions'] ?? [],
            'behavior_profile_label' => $behaviorProfile['profile_label'] ?? null,
        ];
    }

    /**
     * Trả về narrative cho trang Tổng quan (legacy: nhận từng tham số).
     * Nếu có ExecutionInsightPayload thì nên gọi generateFromPayload().
     *
     * @param  array{stage: string, layout: string}|null  $interfaceAdaptation
     * @param  array{integrity_score: float}|null  $activeProgramProgress
     * @param  array{profile: string, profile_label: string, hints: array}|null  $behaviorProfile
     * @param  array{at_risk: bool, collapse_risk_message: string|null, suggestions: array}|null  $failureDetection
     */
    public function getTodayNarrative(
        ?object $activeProgram,
        ?array $activeProgramProgress,
        ?array $behaviorRadar,
        ?array $behaviorProjection,
        $interfaceAdaptation,
        int $todayProgramTaskTotal,
        int $todayProgramTaskDone,
        int $tasksTodayCount,
        ?int $userId = null,
        ?array $behaviorProfile = null,
        ?array $failureDetection = null
    ): array {
        $stage = $interfaceAdaptation['stage'] ?? BehaviorStageClassifier::STAGE_STABILIZING;
        $integrity = $activeProgramProgress['integrity_score'] ?? null;
        $integrityPct = $integrity !== null ? (int) round($integrity * 100) : null;
        $trust = $behaviorRadar['trust_global'] ?? null;
        $trustPct = $trust !== null ? (int) round($trust * 100) : null;
        $proj60 = isset($behaviorProjection['probability_maintain_60d']) ? (int) round($behaviorProjection['probability_maintain_60d'] * 100) : null;
        $suggestion = (is_array($behaviorProjection) && ! empty($behaviorProjection['suggestion'])) ? $behaviorProjection['suggestion'] : null;

        $integrityInterpretation = $this->interpretIntegrity($integrityPct, $stage);
        $todayMessage = $this->getTodayMessage($stage, $tasksTodayCount, $todayProgramTaskTotal, $todayProgramTaskDone, $integrityInterpretation, $suggestion, $failureDetection, $behaviorProfile);
        $emptyTodayCopy = $this->getEmptyTodayCopy($activeProgram, $todayProgramTaskTotal, $behaviorProfile);

        $leastEffectiveType = null;
        $coachingEffectivenessScores = [];
        if ($userId && config('behavior_intelligence.coaching_effectiveness.enabled', true)) {
            $effectiveness = app(CoachingEffectivenessService::class)->getEffectivenessByUser($userId);
            $coachingEffectivenessScores = $effectiveness;
            $leastEffectiveType = app(CoachingEffectivenessService::class)->getLeastEffectiveType($userId);
        }
        $sidebarNarrative = $this->getSidebarNarrative($stage, $integrityInterpretation, $suggestion, $activeProgram, $leastEffectiveType, $failureDetection, $behaviorProfile);

        $trustInterpretation = $this->interpretTrust($trustPct);
        $phaseLabel = $this->getPhaseLabel($stage);

        return [
            'today_message' => $todayMessage,
            'integrity_interpretation' => $integrityInterpretation,
            'integrity_pct' => $integrityPct,
            'trust_interpretation' => $trustInterpretation,
            'trust_pct' => $trustPct,
            'phase_label' => $phaseLabel,
            'empty_today_copy' => $emptyTodayCopy,
            'sidebar_narrative' => $sidebarNarrative,
            'projection_interpretation' => $proj60 !== null ? $this->interpretProjection($proj60) : null,
            'projection_60d' => $proj60,
            'coaching_effectiveness_scores' => $coachingEffectivenessScores,
            'collapse_risk_message' => $failureDetection['collapse_risk_message'] ?? null,
            'failure_suggestions' => $failureDetection['suggestions'] ?? [],
            'behavior_profile_label' => $behaviorProfile['profile_label'] ?? null,
        ];
    }

    protected function interpretIntegrity(?int $pct, string $stage): array
    {
        if ($pct === null) {
            return ['label' => '—', 'risk' => null, 'hint' => 'Chưa đủ dữ liệu để đánh giá.'];
        }
        if ($pct >= 70) {
            return ['label' => 'Ổn định', 'risk' => 'thấp', 'hint' => 'Bạn đang giữ cam kết tốt. Tiếp tục nhịp hiện tại.'];
        }
        if ($pct >= 50) {
            return ['label' => 'Đang ổn định', 'risk' => 'trung bình', 'hint' => 'Có thể cải thiện bằng cách hoàn thành đều đặn 1 cam kết mỗi ngày.'];
        }
        if ($pct >= 30) {
            return ['label' => 'Thấp', 'risk' => 'cao', 'hint' => 'Có nguy cơ trượt. Hôm nay hãy chọn đúng một việc và hoàn thành nó.'];
        }
        return ['label' => 'Rất thấp', 'risk' => 'cao', 'hint' => 'Hệ thống gợi ý: tập trung vào một cam kết duy nhất hôm nay để lấy lại nhịp.'];
    }

    protected function interpretTrust(?int $pct): ?array
    {
        if ($pct === null) {
            return null;
        }
        if ($pct >= 70) {
            return ['label' => 'Tốt', 'hint' => 'Mức độ tin cậy hành vi đang ổn.'];
        }
        if ($pct >= 50) {
            return ['label' => 'Đang xây', 'hint' => 'Mỗi ngày hoàn thành cam kết sẽ nâng Trust dần.'];
        }
        return ['label' => 'Cần ổn định', 'hint' => 'Hệ thống đang quan sát. Một việc làm xong hôm nay sẽ giúp cải thiện.'];
    }

    protected function interpretProjection(int $proj60): array
    {
        if ($proj60 >= 75) {
            return ['label' => 'Khả năng duy trì tốt', 'hint' => 'Xu hướng hiện tại cho thấy bạn có thể duy trì được lâu dài.'];
        }
        if ($proj60 >= 50) {
            return ['label' => 'Ổn định từng bước', 'hint' => 'Duy trì đều đặn mỗi ngày sẽ cải thiện dự báo.'];
        }
        return ['label' => 'Cần tập trung', 'hint' => 'Hệ thống gợi ý giảm tải và hoàn thành ít nhất một cam kết mỗi ngày.'];
    }

    protected function getPhaseLabel(string $stage): string
    {
        return match ($stage) {
            BehaviorStageClassifier::STAGE_FRAGILE => 'Pha cần ổn định',
            BehaviorStageClassifier::STAGE_STABILIZING => 'Pha đang ổn định',
            BehaviorStageClassifier::STAGE_INTERNALIZED => 'Pha đã nội tâm hóa',
            BehaviorStageClassifier::STAGE_MASTERY => 'Pha làm chủ',
            default => 'Pha đang ổn định',
        };
    }

    protected function getTodayMessage(
        string $stage,
        int $tasksTodayCount,
        int $todayProgramTaskTotal,
        int $todayProgramTaskDone,
        array $integrityInterpretation,
        ?string $suggestion,
        ?array $failureDetection = null,
        ?array $behaviorProfile = null
    ): string {
        if ($failureDetection && ! empty($failureDetection['at_risk']) && ! empty($failureDetection['collapse_risk_message'])) {
            return $failureDetection['collapse_risk_message'];
        }
        if ($tasksTodayCount === 0 && $todayProgramTaskTotal === 0) {
            return 'Hôm nay bạn chưa có cam kết nào trong chương trình. Hãy thêm một việc để bắt đầu nhịp.';
        }
        if ($tasksTodayCount === 0 && $todayProgramTaskTotal > 0) {
            return 'Hôm nay bạn có ' . $todayProgramTaskTotal . ' mục tiêu từ chương trình. Hoàn thành từng cái một.';
        }
        if ($integrityInterpretation['risk'] === 'cao') {
            return 'Hệ thống gợi ý: hôm nay chỉ cần hoàn thành một việc quan trọng nhất. Chất lượng hơn số lượng.';
        }
        if ($todayProgramTaskDone >= $todayProgramTaskTotal && $todayProgramTaskTotal > 0) {
            return 'Bạn đã hoàn thành mục tiêu chương trình hôm nay. Rất tốt.';
        }
        if ($behaviorProfile && ! empty($behaviorProfile['hints'])) {
            return $behaviorProfile['hints'][0];
        }
        if ($suggestion) {
            return $suggestion;
        }
        return 'Tập trung vào cam kết hôm nay. Hệ thống đang theo dõi và phản ánh tiến độ của bạn.';
    }

    protected function getEmptyTodayCopy(?object $activeProgram, int $todayProgramTaskTotal, ?array $behaviorProfile = null): string
    {
        if ($behaviorProfile && in_array($behaviorProfile['profile'] ?? '', [BehaviorProfileService::PROFILE_PROCRASTINATOR, BehaviorProfileService::PROFILE_BURNOUT_RISK], true) && ! empty($behaviorProfile['hints'])) {
            return $behaviorProfile['hints'][0];
        }
        if ($activeProgram && $todayProgramTaskTotal > 0) {
            return 'Hôm nay bạn chưa hoàn thành cam kết nào trong chương trình. Chọn một việc và làm xong.';
        }
        if ($activeProgram) {
            return 'Hôm nay bạn chưa có cam kết trong chương trình. Thêm một việc để tạo nhịp.';
        }
        return 'Hôm nay bạn chưa có cam kết nào. Thêm một việc để bắt đầu.';
    }

    /**
     * Một điều quan trọng cho cột phải — không lặp 1/31, Integrity số.
     * Khi $leastEffectiveType = insight_block: ưu tiên hint ngắn thay vì suggestion dài (meta-learning).
     * Failure Detection: ưu tiên gợi ý khi at_risk.
     */
    protected function getSidebarNarrative(string $stage, array $integrityInterpretation, ?string $suggestion, ?object $activeProgram, ?string $leastEffectiveType = null, ?array $failureDetection = null, ?array $behaviorProfile = null): array
    {
        $headline = 'Điều quan trọng hôm nay';
        $body = $integrityInterpretation['hint'];
        $useSuggestion = $suggestion && strlen($suggestion) <= 120;
        if ($leastEffectiveType === CoachingInterventionLogger::TYPE_INSIGHT_BLOCK) {
            $useSuggestion = false;
        }
        if ($failureDetection && ! empty($failureDetection['at_risk']) && ! empty($failureDetection['suggestions'])) {
            $body = implode(' ', array_slice($failureDetection['suggestions'], 0, 2));
        } elseif ($useSuggestion) {
            $body = $suggestion;
        } elseif ($integrityInterpretation['risk'] === 'cao') {
            $body = 'Hệ thống nhận thấy Integrity đang thấp. ' . $integrityInterpretation['hint'];
        }
        $cta = $activeProgram ? 'Xem hành trình' : 'Tạo chương trình';
        $cta_url = $activeProgram ? route('cong-viec.programs.show', $activeProgram->id) : route('cong-viec.programs.index');

        return [
            'headline' => $headline,
            'body' => $body,
            'cta' => $cta,
            'cta_url' => $cta_url,
            'phase' => $this->getPhaseLabel($stage),
        ];
    }
}
