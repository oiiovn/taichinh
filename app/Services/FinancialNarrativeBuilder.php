<?php

namespace App\Services;

/**
 * Narrative Builder: Dựng 1 câu chuyện thống nhất — không bao giờ trộn nhiều narrative.
 * Sections: Financial Direction → Liquidity Position → Debt Stress → Strategic Mode → Tactical Suggestion.
 * Narrative Confidence Score = min(income_stability, liquidity_stability, classification_confidence).
 */
class FinancialNarrativeBuilder
{
    /**
     * Build narrative thống nhất từ resolved state, mode, projection, root causes (đã lọc), guidance.
     *
     * @param  array{key: string, label: string, description: string}|null  $resolvedState
     * @param  array{key: string, label: string, description: string}|null  $resolvedMode
     * @param  array{sources?: array}  $projection
     * @param  array<int, array{key: string, label: string}>  $rootCauses  Đã lọc theo state
     * @param  string[]  $guidanceLines
     * @return array{narrative: string, narrative_confidence: float, financial_direction: string, liquidity_position: string, debt_stress: string, strategic_mode: string, tactical_suggestion: string}
     */
    public function build(
        ?array $resolvedState,
        ?array $resolvedMode,
        array $projection,
        array $rootCauses = [],
        array $guidanceLines = []
    ): array {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];

        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $timeline = $projection['timeline'] ?? [];
        $months = max(1, count($timeline));
        $debtService = $debtTotal / $months;
        $outflow = $chi + $debtService;
        $deficit = $outflow - $thu;
        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $incomeStability = (float) ($sources['income_stability_score'] ?? $canonical['income_stability_score'] ?? 1.0);
        $requiredRunwayMonths = (int) ($canonical['required_runway_months'] ?? 3);
        $stateKey = $resolvedState['key'] ?? '';
        $modeKey = $resolvedMode['key'] ?? '';
        $maturityStage = $sources['maturity_stage'] ?? null;
        $trajectory = $sources['trajectory'] ?? null;

        $financialDirection = $this->buildFinancialDirection($thu, $chi, $debtService, $deficit, $incomeStability, $requiredRunwayMonths);
        $liquidityPosition = $this->buildLiquidityPosition($deficit, $runwayFromLiq);
        $debtStress = $this->buildDebtStress($stateKey, $debtService, $thu);
        $strategicMode = $this->buildStrategicMode($modeKey, $resolvedMode);
        $tacticalSuggestion = ! empty($guidanceLines) ? $guidanceLines[0] : $this->defaultTactical($stateKey, $modeKey);
        $doctrineNarrative = $this->buildDoctrineNarrative($maturityStage, $trajectory);

        $narrative = $this->assembleNarrative(
            $financialDirection,
            $liquidityPosition,
            $debtStress,
            $strategicMode,
            $tacticalSuggestion,
            $deficit,
            $runwayFromLiq,
            $modeKey,
            $incomeStability,
            $requiredRunwayMonths,
            $doctrineNarrative
        );

        $narrativeConfidence = $this->computeNarrativeConfidence($sources, $canonical, $runwayFromLiq, $resolvedState);

        return [
            'narrative' => $narrative,
            'narrative_confidence' => $narrativeConfidence,
            'financial_direction' => $financialDirection,
            'liquidity_position' => $liquidityPosition,
            'debt_stress' => $debtStress,
            'strategic_mode' => $strategicMode,
            'tactical_suggestion' => $tacticalSuggestion,
            'maturity_stage' => $maturityStage,
            'trajectory' => $trajectory,
        ];
    }

    private function buildDoctrineNarrative(?array $maturityStage, ?array $trajectory): ?string
    {
        if ($maturityStage === null || empty($maturityStage['doctrine']['narrative_hint'])) {
            return null;
        }
        $label = $maturityStage['label'] ?? '';
        $hint = $maturityStage['doctrine']['narrative_hint'] ?? '';
        $line = "Cấu trúc tài chính của bạn đang ở pha {$label}. {$hint}";
        if ($trajectory !== null && ($trajectory['direction'] ?? '') !== 'stable' && ! empty($trajectory['hint'])) {
            $line .= ' ' . $trajectory['hint'];
        }
        return $line;
    }

    /**
     * Độ tin cậy dự báo: min(income_stability, liquidity_stability, classification_confidence). Trả về 0–100.
     */
    private function computeNarrativeConfidence(array $sources, array $canonical, ?int $runwayFromLiq, ?array $resolvedState): float
    {
        $incomeStability = (float) ($sources['income_stability_score'] ?? 1.0);
        $liquidityStability = $this->liquidityStabilityScore($runwayFromLiq, $canonical);
        $classificationConfidence = $resolvedState !== null ? 1.0 : 0.7;
        $raw = min($incomeStability, $liquidityStability, $classificationConfidence);
        return round(max(0, min(100, $raw * 100)), 0);
    }

    private function liquidityStabilityScore(?int $runwayFromLiq, array $canonical): float
    {
        if ($runwayFromLiq === null) {
            $available = (float) ($canonical['available_liquidity'] ?? 0);
            return $available > 0 ? 0.6 : 0.3;
        }
        if ($runwayFromLiq >= 12) {
            return 1.0;
        }
        if ($runwayFromLiq >= 6) {
            return 0.95;
        }
        if ($runwayFromLiq >= 3) {
            return 0.85;
        }
        if ($runwayFromLiq >= 2) {
            return 0.7;
        }
        if ($runwayFromLiq >= 1) {
            return 0.5;
        }
        return 0.3;
    }

    private function buildFinancialDirection(float $thu, float $chi, float $debtService, float $deficit, float $incomeStability = 1.0, int $requiredRunwayMonths = 3): string
    {
        if ($deficit <= 0) {
            if ($incomeStability < 0.4) {
                $stabilityPct = round($incomeStability * 100);
                return "Thu nhập của bạn biến động mạnh (độ ổn định {$stabilityPct}%). Dù trung bình hiện tại vượt chi, rủi ro tháng thấp vẫn cao. Nên duy trì tối thiểu {$requiredRunwayMonths} tháng chi phí dự phòng trước khi tối ưu dài hạn.";
            }
            return 'Thu vượt chi (sau trả nợ).';
        }
        $deficitShort = $deficit >= 1_000_000 ? (round($deficit / 1_000_000, 1) . ' triệu') : (number_format((int) $deficit) . ' ₫');
        return 'Chi vượt thu — thâm hụt khoảng ' . $deficitShort . '/tháng.';
    }

    private function buildLiquidityPosition(float $deficit, ?int $runwayFromLiq): string
    {
        if ($deficit <= 0) {
            return 'Thanh khoản đủ cho dòng tiền hiện tại.';
        }
        if ($runwayFromLiq === null) {
            return 'Thanh khoản cần theo dõi.';
        }
        if ($runwayFromLiq <= 1) {
            return 'Thanh khoản chỉ đủ tối đa 1 tháng — rất căng.';
        }
        return 'Có đủ thanh khoản để duy trì khoảng ' . $runwayFromLiq . ' tháng.';
    }

    private function buildDebtStress(string $stateKey, float $debtService, float $thu): string
    {
        if ($stateKey === 'debt_spiral_risk') {
            return 'Áp lực nợ cao (trả nợ > 40% thu).';
        }
        if ($debtService > 0 && $thu > 0 && $debtService / $thu > 0.25) {
            return 'Trả nợ đáng kể so với thu.';
        }
        if ($debtService > 0) {
            return 'Có trả nợ định kỳ.';
        }
        return 'Không có trả nợ định kỳ.';
    }

    private function buildStrategicMode(string $modeKey, ?array $resolvedMode): string
    {
        if ($resolvedMode === null) {
            return '';
        }
        $label = $resolvedMode['label'] ?? '';
        if ($modeKey === 'crisis') {
            return 'Chế độ khủng hoảng — cần hành động khẩn cấp.';
        }
        if ($modeKey === 'defensive') {
            return 'Chế độ phòng thủ — chưa phải khủng hoảng nhưng cần ổn định.';
        }
        if ($modeKey === 'optimization') {
            return 'Chế độ tối ưu — có thể tập trung vào mục tiêu dài hạn.';
        }
        if ($modeKey === 'growth') {
            return 'Chế độ tăng trưởng — có thể cân nhắc đầu tư.';
        }
        return $label ?: '';
    }

    private function defaultTactical(string $stateKey, string $modeKey): string
    {
        if ($modeKey === 'crisis') {
            return 'Ưu tiên tối đa thanh khoản và giảm rủi ro ngay.';
        }
        if ($modeKey === 'defensive') {
            return 'Ưu tiên bảo vệ buffer và điều chỉnh thu chi.';
        }
        if ($stateKey === 'debt_spiral_risk') {
            return 'Ưu tiên tái cấu trúc nợ và thanh khoản.';
        }
        return 'Duy trì và tối ưu theo mục tiêu.';
    }

    private function assembleNarrative(
        string $financialDirection,
        string $liquidityPosition,
        string $debtStress,
        string $strategicMode,
        string $tacticalSuggestion,
        float $deficit,
        ?int $runwayFromLiq,
        string $modeKey,
        float $incomeStability = 1.0,
        int $requiredRunwayMonths = 3,
        ?string $doctrineNarrative = null
    ): string {
        if ($doctrineNarrative !== null && $doctrineNarrative !== '') {
            return $doctrineNarrative . ' ' . $tacticalSuggestion;
        }
        if ($deficit > 0 && $runwayFromLiq !== null && $runwayFromLiq >= 2) {
            $deficitShort = $deficit >= 1_000_000 ? (round($deficit / 1_000_000, 1) . ' triệu') : (number_format((int) $deficit) . ' ₫');
            $narrative = 'Bạn đang thâm hụt nhẹ (~' . $deficitShort . '/tháng), nhưng có đủ thanh khoản để duy trì khoảng ' . $runwayFromLiq . ' tháng.';
            $narrative .= $modeKey === 'crisis' ? ' Đây là tình huống căng — cần hành động khẩn cấp.' : ' Đây không phải khủng hoảng, nhưng cấu trúc hiện tại không bền vững nếu không điều chỉnh.';
            return $narrative;
        }
        if ($deficit > 0 && ($runwayFromLiq === null || $runwayFromLiq <= 1)) {
            return 'Bạn đang thâm hụt và thanh khoản rất mỏng. Cần hành động ngay để ổn định dòng tiền.';
        }
        if ($deficit <= 0) {
            if ($incomeStability < 0.4) {
                $narrative = $financialDirection;
                if ($strategicMode !== '') {
                    $narrative .= ' ' . $strategicMode;
                }
                $narrative .= ' ' . $tacticalSuggestion;
                return $narrative;
            }
            $narrative = 'Thu vượt chi — dòng tiền ổn. ' . $liquidityPosition;
            if ($strategicMode !== '') {
                $narrative .= ' ' . $strategicMode;
            }
            $narrative .= ' ' . $tacticalSuggestion;
            return $narrative;
        }
        $parts = array_filter([$financialDirection, $liquidityPosition, $debtStress, $strategicMode, $tacticalSuggestion]);
        return implode(' ', $parts);
    }
}
