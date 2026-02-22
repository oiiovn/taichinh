<?php

namespace App\Services;

/**
 * Contextual Framing Engine: Insight không chỉ dựa trên deficit mà trên nhiều driver,
 * sau đó chọn tone (Calm | Advisory | Warning | Crisis) cho messaging.
 *
 * Drivers: deficit magnitude, runway, income stability, debt interest profile, liquidity ratio.
 */
class ContextualFramingService
{
    /** Runway (tháng) ≤ đây → có thể Crisis/Warning */
    private const RUNWAY_WARNING_MAX = 3;

    private const RUNWAY_ADVISORY_MAX = 6;

    /** Income stability score < đây → advisory thay vì calm */
    private const STABILITY_ADVISORY = 0.7;

    /** Liquidity (available) / monthly_expense < đây → warning */
    private const LIQUIDITY_RATIO_WARNING = 2.0;

    private const LIQUIDITY_RATIO_ADVISORY = 4.0;

    /**
     * Frame context và chọn tone từ position, projection, priority mode.
     *
     * @param  array{debt_exposure: float, liquid_balance?: float}  $position
     * @param  array{timeline?: array, sources?: array}  $projection
     * @param  array{key: string}|null  $priorityMode
     * @param  array{key: string}|null  $financialState
     * @return array{tone: string, label: string, rationale: string, drivers: array}
     */
    public function frame(
        array $position,
        array $projection,
        ?array $priorityMode = null,
        ?array $financialState = null
    ): array {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $timeline = $projection['timeline'] ?? [];

        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $months = max(1, count($timeline));
        $monthlyExpense = $chi + ($debtTotal / $months);

        $deficitMagnitude = (string) ($canonical['deficit_magnitude'] ?? 'none');
        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $runwayMonths = (int) ($sources['runway_months'] ?? 0);
        $runway = $runwayFromLiq ?? $runwayMonths;
        if ($runway === 0 && $timeline) {
            $minBalance = min(array_column($timeline, 'so_du_cuoi'));
            if ($minBalance >= 0) {
                $runway = count($timeline);
            }
        }

        $incomeStability = (float) ($sources['income_stability_score'] ?? 1.0);
        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $availableLiquidity = (float) ($canonical['available_liquidity'] ?? $canonical['liquid_balance'] ?? $position['liquid_balance'] ?? 0);
        $liquidityRatio = $monthlyExpense > 0 ? $availableLiquidity / $monthlyExpense : 0;

        $modeKey = $priorityMode['key'] ?? null;
        $stateKey = $financialState['key'] ?? null;

        $drivers = [
            'deficit_magnitude' => $deficitMagnitude,
            'runway_months' => $runway,
            'income_stability_score' => round($incomeStability, 2),
            'debt_exposure' => (int) round($debtExposure),
            'liquidity_ratio' => round($liquidityRatio, 1),
        ];

        if ($modeKey === 'crisis') {
            return $this->toneResult('crisis', 'Crisis', 'Chế độ khủng hoảng — cần hành động khẩn cấp.', $drivers);
        }

        if ($runway !== null && $runway <= self::RUNWAY_WARNING_MAX && $deficitMagnitude !== 'none') {
            return $this->toneResult('warning', 'Warning', 'Runway ngắn và có thâm hụt — cần theo dõi sát và hành động có ưu tiên.', $drivers);
        }

        if ($deficitMagnitude === 'significant') {
            return $this->toneResult('warning', 'Warning', 'Thâm hụt đáng kể — nên điều chỉnh thu chi hoặc tăng dự phòng.', $drivers);
        }

        if ($liquidityRatio > 0 && $liquidityRatio < self::LIQUIDITY_RATIO_WARNING && $stateKey === 'fragile_liquidity') {
            return $this->toneResult('warning', 'Warning', 'Thanh khoản mỏng so với chi — ưu tiên tăng buffer.', $drivers);
        }

        if ($modeKey === 'defensive' || $deficitMagnitude === 'moderate' || $incomeStability < self::STABILITY_ADVISORY) {
            return $this->toneResult('advisory', 'Advisory', 'Tình hình cần theo dõi — đề xuất ưu tiên theo mục tiêu của bạn.', $drivers);
        }

        if ($liquidityRatio < self::LIQUIDITY_RATIO_ADVISORY && $liquidityRatio >= self::LIQUIDITY_RATIO_WARNING) {
            return $this->toneResult('advisory', 'Advisory', 'Buffer ổn nhưng chưa dồi dào — duy trì và tối ưu theo mục tiêu.', $drivers);
        }

        return $this->toneResult('calm', 'Calm', 'Dòng tiền và thanh khoản ổn — có thể tập trung vào mục tiêu dài hạn.', $drivers);
    }

    private function toneResult(string $tone, string $label, string $rationale, array $drivers): array
    {
        return [
            'tone' => $tone,
            'label' => $label,
            'rationale' => $rationale,
            'drivers' => $drivers,
        ];
    }
}
