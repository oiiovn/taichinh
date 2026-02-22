<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Objective Awareness: Hệ thống suy luận user đang ưu tiên mục tiêu gì
 * (Trả nợ | Tích lũy | Đầu tư | Giữ an toàn) để align chiến lược.
 */
class ObjectiveAwarenessService
{
    /** Trả nợ / thu > ngưỡng → ưu tiên trả nợ */
    private const DEBT_SERVICE_TO_INCOME_OBJECTIVE = 0.35;

    /** DTI > đây → có thể đang tập trung trả nợ */
    private const DTI_HIGH = 1.5;

    /** Runway <= đây (tháng) + DSCR < 0 + thu rất thấp → objective survival, không debt_repayment. */
    private const SURVIVAL_RUNWAY_MAX = 0.5;

    /** Thu < (chi * tỷ lệ) hoặc < ngưỡng VND → coi như survival. */
    private const SURVIVAL_INCOME_RATIO = 0.15;

    private const SURVIVAL_INCOME_CAP_VND = 1_000_000;

    /**
     * Suy luận mục tiêu chính từ state, mode, position, projection, nợ.
     *
     * @param  array{key: string}|null  $financialState
     * @param  array{key: string}|null  $priorityMode
     * @param  array{debt_exposure: float}|null  $position
     * @param  array{sources?: array}|null  $projection
     * @param  \Illuminate\Support\Collection  $oweItems  Khoản nợ (để biết có nợ lãi cao không)
     * @return array{key: string, label: string, description: string}
     */
    public function infer(
        ?array $financialState,
        ?array $priorityMode,
        ?array $position,
        ?array $projection,
        Collection $oweItems
    ): array {
        $stateKey = $financialState['key'] ?? null;
        $modeKey = $priorityMode['key'] ?? null;
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $timeline = $projection['timeline'] ?? [];
        $months = max(1, count($timeline) ?: 12);
        $debtService = $debtTotal / $months;
        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $surplus = $thu - $chi - $debtService >= -500_000;

        $debtToIncome = $thu > 0 ? $debtService / $thu : 0;
        $dti = $thu > 0 && $thu * 12 > 0 ? $debtExposure / ($thu * 12) : 0;
        $hasMeaningfulDebt = $oweItems->isNotEmpty() && $debtExposure > 0;

        if ($modeKey === 'crisis' || $modeKey === 'defensive' || $stateKey === 'fragile_liquidity') {
            if ($modeKey === 'crisis') {
                $runway = $canonical['runway_from_liquidity_months'] ?? null;
                $dscr = isset($canonical['dscr']) ? (float) $canonical['dscr'] : null;
                $outflow = $chi + ($months > 0 ? $debtService : 0);
                $runwaySurvival = $runway !== null && $runway <= self::SURVIVAL_RUNWAY_MAX;
                $dscrSurvival = $dscr === null || $dscr < 0;
                $incomeSurvival = $thu < self::SURVIVAL_INCOME_CAP_VND || ($outflow > 0 && $thu < $outflow * self::SURVIVAL_INCOME_RATIO);
                if ($runwaySurvival && $dscrSurvival && $incomeSurvival) {
                    return $this->objectiveResult('survival', 'Sinh tồn', 'Ưu tiên tiền mặt và tạo dòng tiền — không ưu tiên trả nợ hay tối ưu lãi trong giai đoạn này.');
                }
            }
            if ($stateKey === 'debt_spiral_risk' && $hasMeaningfulDebt) {
                return $this->objectiveResult('debt_repayment', 'Trả nợ', 'Ưu tiên ổn định nợ và thanh khoản — chiến lược đề xuất sẽ gắn với trả nợ và an toàn.');
            }
            return $this->objectiveResult('safety', 'Giữ an toàn', 'Ưu tiên bảo vệ buffer và thanh khoản — chiến lược đề xuất theo hướng giữ an toàn.');
        }

        if ($hasMeaningfulDebt && ($debtToIncome >= self::DEBT_SERVICE_TO_INCOME_OBJECTIVE || $dti >= 1.0)) {
            return $this->objectiveResult('debt_repayment', 'Trả nợ', 'Nợ đáng kể so với thu — đề xuất chiến lược ưu tiên trả nợ và tối ưu lãi.');
        }

        if ($stateKey === 'stable_conservative' && $surplus && $dti < 0.5) {
            return $this->objectiveResult('investment', 'Đầu tư', 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư; đề xuất gắn với tăng trưởng tài sản.');
        }

        if ($modeKey === 'growth' && $surplus) {
            return $this->objectiveResult('investment', 'Đầu tư', 'Dư tiền và chế độ tăng trưởng — đề xuất hướng tới đầu tư hoặc tích lũy dài hạn.');
        }

        if ($surplus && in_array($stateKey, ['accumulation_phase', 'leveraged_growth'], true)) {
            return $this->objectiveResult('accumulation', 'Tích lũy', 'Đang ở pha tích lũy — đề xuất ưu tiên tối ưu nợ và tích lũy, không thắt chặt quá mức.');
        }

        return $this->objectiveResult('accumulation', 'Tích lũy', 'Đề xuất chiến lược gắn với tích lũy và ổn định dòng tiền.');
    }

    private function objectiveResult(string $key, string $label, string $description): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ];
    }
}
