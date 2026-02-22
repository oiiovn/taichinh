<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Shock Absorption Simulation: "Nếu thu nhập giảm 30% → hệ nợ có sụp không?".
 * Dùng timeline từ projection có sẵn, áp dụng shock rồi tính runway & rủi ro vỡ nợ.
 * Phục vụ hướng A.
 */
class DebtShockSimulator
{
    /**
     * Mô phỏng shock: giảm thu, tăng chi trên timeline hiện có.
     *
     * @param  array{timeline: array<int, array{month: string, thu: float, chi: float, tra_no: float, so_du_dau: float, so_du_cuoi: float}>}  $projection
     * @param  float  $incomeDropPct  0–100, ví dụ 30 = giảm thu 30%
     * @param  float  $expenseIncreasePct  0–100, ví dụ 10 = tăng chi 10%
     * @param  \Illuminate\Support\Collection<int, object>|null  $oweItems  Để lấy most_urgent làm first_contract_at_risk
     * @return array{runway_after_shock_months: int|null, first_negative_month: string|null, probability_of_default: string, first_contract_at_risk: string|null}
     */
    public function simulateIncomeDrop(
        array $projection,
        float $incomeDropPct = 30.0,
        float $expenseIncreasePct = 0.0,
        ?Collection $oweItems = null
    ): array {
        $timeline = $projection['timeline'] ?? [];
        if (empty($timeline)) {
            return [
                'runway_after_shock_months' => null,
                'first_negative_month' => null,
                'probability_of_default' => 'unknown',
                'first_contract_at_risk' => null,
            ];
        }

        $incomeFactor = 1.0 - $incomeDropPct / 100.0;
        $expenseFactor = 1.0 + $expenseIncreasePct / 100.0;
        $soDuDau = (float) ($timeline[0]['so_du_dau'] ?? 0);
        $firstNegativeMonth = null;
        $runwayMonths = 0;

        foreach ($timeline as $row) {
            $thu = (float) ($row['thu'] ?? 0) * $incomeFactor;
            $chi = (float) ($row['chi'] ?? 0) * $expenseFactor;
            $traNo = (float) ($row['tra_no'] ?? 0);
            $soDuCuoi = $soDuDau + $thu - $chi - $traNo;

            if ($soDuCuoi >= 0) {
                $runwayMonths++;
            } else {
                if ($firstNegativeMonth === null) {
                    $firstNegativeMonth = $row['month'] ?? null;
                }
            }
            $soDuDau = $soDuCuoi;
        }

        $probabilityOfDefault = 'low';
        if ($firstNegativeMonth !== null) {
            if ($runwayMonths < 1) {
                $probabilityOfDefault = 'high';
            } elseif ($runwayMonths < 3) {
                $probabilityOfDefault = 'medium';
            }
        }

        $firstContractAtRisk = null;
        if ($oweItems && $oweItems->isNotEmpty()) {
            $priorityService = app(DebtPriorityService::class);
            $mostUrgent = $priorityService->getMostUrgent($oweItems);
            if ($mostUrgent !== null) {
                $firstContractAtRisk = $mostUrgent['name'];
            }
        }

        return [
            'runway_after_shock_months' => $runwayMonths > 0 ? $runwayMonths : null,
            'first_negative_month' => $firstNegativeMonth,
            'probability_of_default' => $probabilityOfDefault,
            'first_contract_at_risk' => $firstContractAtRisk,
        ];
    }
}
