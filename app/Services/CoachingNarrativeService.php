<?php

namespace App\Services;

use App\Services\BehaviorStageClassifier;

/**
 * "Hệ thống hôm nay muốn nói điều gì quan trọng nhất."
 * Chuyển số liệu thành nhận định + ngôn ngữ huấn luyện.
 */
class CoachingNarrativeService
{
    /**
     * Trả về narrative cho trang Tổng quan: nhận định, copy huấn luyện, một điều quan trọng (cột phải).
     * Khi $userId được truyền và bật coaching_effectiveness, tích hợp hiệu quả can thiệp (meta-learning).
     *
     * @param  array{stage: string, layout: string}|null  $interfaceAdaptation
     * @param  array{integrity_score: float, days_elapsed: int, days_total: int}|null  $activeProgramProgress
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
        ?int $userId = null
    ): array {
        $stage = $interfaceAdaptation['stage'] ?? BehaviorStageClassifier::STAGE_STABILIZING;
        $integrity = $activeProgramProgress['integrity_score'] ?? null;
        $integrityPct = $integrity !== null ? (int) round($integrity * 100) : null;
        $trust = $behaviorRadar['trust_global'] ?? null;
        $trustPct = $trust !== null ? (int) round($trust * 100) : null;
        $proj60 = isset($behaviorProjection['probability_maintain_60d']) ? (int) round($behaviorProjection['probability_maintain_60d'] * 100) : null;
        $suggestion = (is_array($behaviorProjection) && ! empty($behaviorProjection['suggestion'])) ? $behaviorProjection['suggestion'] : null;

        $integrityInterpretation = $this->interpretIntegrity($integrityPct, $stage);
        $todayMessage = $this->getTodayMessage($stage, $tasksTodayCount, $todayProgramTaskTotal, $todayProgramTaskDone, $integrityInterpretation, $suggestion);
        $emptyTodayCopy = $this->getEmptyTodayCopy($activeProgram, $todayProgramTaskTotal);

        $leastEffectiveType = null;
        $coachingEffectivenessScores = [];
        if ($userId && config('behavior_intelligence.coaching_effectiveness.enabled', true)) {
            $effectiveness = app(CoachingEffectivenessService::class)->getEffectivenessByUser($userId);
            $coachingEffectivenessScores = $effectiveness;
            $leastEffectiveType = app(CoachingEffectivenessService::class)->getLeastEffectiveType($userId);
        }
        $sidebarNarrative = $this->getSidebarNarrative($stage, $integrityInterpretation, $suggestion, $activeProgram, $leastEffectiveType);

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
        ?string $suggestion
    ): string {
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
        if ($suggestion) {
            return $suggestion;
        }
        return 'Tập trung vào cam kết hôm nay. Hệ thống đang theo dõi và phản ánh tiến độ của bạn.';
    }

    protected function getEmptyTodayCopy(?object $activeProgram, int $todayProgramTaskTotal): string
    {
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
     */
    protected function getSidebarNarrative(string $stage, array $integrityInterpretation, ?string $suggestion, ?object $activeProgram, ?string $leastEffectiveType = null): array
    {
        $headline = 'Điều quan trọng hôm nay';
        $body = $integrityInterpretation['hint'];
        $useSuggestion = $suggestion && strlen($suggestion) <= 120;
        if ($leastEffectiveType === CoachingInterventionLogger::TYPE_INSIGHT_BLOCK) {
            $useSuggestion = false;
        }
        if ($useSuggestion) {
            $body = $suggestion;
        }
        if ($integrityInterpretation['risk'] === 'cao') {
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
