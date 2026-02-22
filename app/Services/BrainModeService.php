<?php

namespace App\Services;

use App\Enums\BrainMode;

/**
 * Tầng 7 – Brain Mode Layer: chọn "cá tính" từ structural + behavioral + economic + drift.
 * Mỗi mode có cấu trúc UI riêng (narrative_blocks) và tham số ảnh hưởng Decision Core (enum).
 */
class BrainModeService
{
    /** Giữ tương thích với code cũ dùng BrainModeService::BLOCK_*. */
    public const BLOCK_NHAN_DINH = BrainMode::BLOCK_NHAN_DINH;
    public const BLOCK_GIAI_THICH = BrainMode::BLOCK_GIAI_THICH;
    public const BLOCK_LUA_CHON_CAI_THIEN = BrainMode::BLOCK_LUA_CHON_CAI_THIEN;
    public const BLOCK_HANH_DONG_NGAY = BrainMode::BLOCK_HANH_DONG_NGAY;

    /**
     * Chọn brain mode từ ngữ cảnh. Trả mảng đầy đủ (UI + decision params) từ enum.
     *
     * @param  array{key?: string}|null  $financialState
     * @param  array{key?: string}|null  $priorityMode
     * @param  array{tone?: string}|null  $contextualFrame
     * @param  array{income_concentration?: float, platform_dependency?: string, volatility_cluster?: string}  $economicContext
     * @param  array{summary?: string, repeated_high_dsi?: bool, feedback_count_increase?: int}  $driftSignals
     * @param  array{execution_consistency_score_reduce_expense?: float|null, execution_consistency_score?: float|null}  $behavioralScores
     * @param  array{conservative_bias?: float}|null  $userParams
     * @param  array{active_count: int, thresholds: array, aggregate: array{avg_self_control_index?: float|null, max_breach_streak?: int}}|null  $thresholdSummary
     * @param  array{discipline_score?: float|null, planning_realism_index?: float|null, impulse_risk_score?: float|null, behavior_mismatch_warning?: bool}|null  $budgetIntelligence
     */
    public function resolve(
        ?array $financialState,
        ?array $priorityMode,
        ?array $contextualFrame,
        array $economicContext = [],
        array $driftSignals = [],
        array $behavioralScores = [],
        ?array $userParams = null,
        ?array $thresholdSummary = null,
        ?array $budgetIntelligence = null
    ): array {
        $tone = $contextualFrame['tone'] ?? 'advisory';
        $stateKey = $financialState['key'] ?? null;
        $modeKey = $priorityMode['key'] ?? null;
        $platformDep = strtolower((string) ($economicContext['platform_dependency'] ?? ''));
        $concentration = (float) ($economicContext['income_concentration'] ?? 0);
        $repeatedHighDsi = ! empty($driftSignals['repeated_high_dsi']);
        $feedbackIncrease = (int) ($driftSignals['feedback_count_increase'] ?? 0);
        $reduceExpenseScore = isset($behavioralScores['execution_consistency_score_reduce_expense'])
            ? (float) $behavioralScores['execution_consistency_score_reduce_expense'] : null;
        $overallScore = isset($behavioralScores['execution_consistency_score']) ? (float) $behavioralScores['execution_consistency_score'] : null;

        $scores = $this->computeModeScores(
            $stateKey,
            $modeKey,
            $tone,
            $platformDep,
            $concentration,
            $repeatedHighDsi,
            $feedbackIncrease,
            $reduceExpenseScore,
            $overallScore,
            $thresholdSummary,
            $budgetIntelligence
        );
        $enum = $this->selectModeByScore($scores);
        return $enum->toArray();
    }

    /**
     * Tính điểm từng mode: w1*crisis_signal + w2*platform_dependency + ... + threshold_summary (self_control, breach_streak).
     *
     * @param  array{active_count: int, aggregate?: array{avg_self_control_index?: float|null, max_breach_streak?: int}}|null  $thresholdSummary
     * @param  array{discipline_score?: float|null, planning_realism_index?: float|null, impulse_risk_score?: float|null, behavior_mismatch_warning?: bool}|null  $budgetIntelligence
     * @return array<string, float> key = enum value (BrainMode->value), value = score
     */
    private function computeModeScores(
        ?string $stateKey,
        ?string $modeKey,
        string $tone,
        string $platformDep,
        float $concentration,
        bool $repeatedHighDsi,
        int $feedbackIncrease,
        ?float $reduceExpenseScore,
        ?float $overallScore,
        ?array $thresholdSummary = null,
        ?array $budgetIntelligence = null
    ): array {
        $crisisSignal = ($modeKey === 'crisis' || $tone === 'crisis' || $stateKey === 'debt_spiral_risk') ? 1.0 : 0.0;
        $platformDependency = ($platformDep === 'high' || $concentration >= 0.75) && $tone === 'advisory' ? 1.0 : 0.0;
        $behaviorMismatch = $feedbackIncrease > 0 && $reduceExpenseScore !== null && $reduceExpenseScore < 50 ? 1.0 : 0.0;
        $fragileSignal = $repeatedHighDsi && in_array($tone, ['warning', 'advisory'], true) ? 0.8 : 0.0;
        $fragileTone = ($tone === 'warning' || $stateKey === 'fragile_liquidity') ? 0.7 : 0.0;
        $disciplinedSignal = $overallScore !== null && $overallScore >= 70 && $tone === 'calm' ? 0.9 : 0.0;
        $growthSignal = ($tone === 'calm' && $modeKey === 'growth') ? 0.8 : 0.0;

        $thresholdBoostFragile = 0.0;
        $thresholdBoostDisciplined = 0.0;
        if ($thresholdSummary !== null && (int) ($thresholdSummary['active_count'] ?? 0) > 0) {
            $agg = $thresholdSummary['aggregate'] ?? [];
            $avgSpi = isset($agg['avg_self_control_index']) ? (float) $agg['avg_self_control_index'] : null;
            $maxBreachStreak = (int) ($agg['max_breach_streak'] ?? 0);
            if ($avgSpi !== null && $avgSpi < 50) {
                $thresholdBoostFragile = 0.3;
            }
            if ($avgSpi !== null && $avgSpi >= 70 && $maxBreachStreak <= 1) {
                $thresholdBoostDisciplined = 0.2;
            }
        }

        $budgetPressure = 0.0;
        $budgetBehaviorMismatch = 0.0;
        $budgetDisciplined = 0.0;
        if ($budgetIntelligence !== null) {
            $disciplineScore = isset($budgetIntelligence['discipline_score']) ? (float) $budgetIntelligence['discipline_score'] : null;
            $planningRealism = isset($budgetIntelligence['planning_realism_index']) ? (float) $budgetIntelligence['planning_realism_index'] : null;
            $impulseRisk = (float) ($budgetIntelligence['impulse_risk_score'] ?? 0);
            $behaviorMismatchWarning = ! empty($budgetIntelligence['behavior_mismatch_warning']);
            $rationalizationFlag = ! empty($budgetIntelligence['rationalization_flag']);
            $predictiveBreach = isset($budgetIntelligence['predictive_breach_probability']) ? (float) $budgetIntelligence['predictive_breach_probability'] : null;
            $correctionSpeedIndex = isset($budgetIntelligence['correction_speed_index']) ? (float) $budgetIntelligence['correction_speed_index'] : null;
            if ($disciplineScore !== null && $disciplineScore < 50) {
                $budgetPressure = 0.25;
            }
            if ($predictiveBreach !== null && $predictiveBreach >= 0.7) {
                $budgetPressure = max($budgetPressure, 0.35);
            }
            if ($correctionSpeedIndex !== null && $correctionSpeedIndex < 40) {
                $budgetPressure = max($budgetPressure, 0.2);
            }
            if ($planningRealism !== null && $planningRealism < 40) {
                $budgetBehaviorMismatch = 0.4;
            }
            if ($rationalizationFlag) {
                $budgetBehaviorMismatch = max($budgetBehaviorMismatch, 0.35);
            }
            if ($behaviorMismatchWarning && ($stateKey === 'fragile_liquidity' || $tone === 'warning')) {
                $budgetBehaviorMismatch = max($budgetBehaviorMismatch, 0.5);
            }
            if ($disciplineScore !== null && $disciplineScore >= 70 && $impulseRisk < 30) {
                $budgetDisciplined = 0.15;
            }
        }

        $w = 1.0;
        return [
            BrainMode::CrisisDirective->value => $w * $crisisSignal,
            BrainMode::PlatformRiskAlert->value => $w * $platformDependency * ($tone === 'advisory' ? 1 : 0.5),
            BrainMode::BehaviorMismatchWarning->value => $w * max($behaviorMismatch, $budgetBehaviorMismatch),
            BrainMode::FragileCoaching->value => $w * (max($fragileSignal, $fragileTone) + 0.2 + $thresholdBoostFragile + $budgetPressure),
            BrainMode::DisciplinedAccelerator->value => $w * ($disciplinedSignal + $thresholdBoostDisciplined + $budgetDisciplined),
            BrainMode::StableGrowth->value => $w * $growthSignal,
        ];
    }

    /** Chọn mode có score cao nhất; nếu không có mode nào vượt ngưỡng thì mặc định FragileCoaching. */
    private function selectModeByScore(array $scores): BrainMode
    {
        $threshold = 0.25;
        $best = null;
        $bestScore = -1.0;
        foreach ($scores as $value => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $value;
            }
        }
        if ($best !== null && $bestScore >= $threshold) {
            $mode = BrainMode::tryFrom($best);
            if ($mode !== null) {
                return $mode;
            }
        }
        return BrainMode::FragileCoaching;
    }

    /** Trả cấu trúc UI + decision params cho một mode (theo key). */
    public function getModeConfig(string $modeKey): array
    {
        return BrainMode::fromKey($modeKey)->toArray();
    }

    public function shouldShowLuaChonCaiThien(array $modeResult): bool
    {
        $blocks = $modeResult['narrative_blocks'] ?? [];
        return in_array(self::BLOCK_LUA_CHON_CAI_THIEN, $blocks, true);
    }
}
