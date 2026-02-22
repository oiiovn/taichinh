<?php

namespace App\Services;

/**
 * Kiểm tra tính nhất quán: không được vừa báo "không có thu" vừa có thu cao; không được vừa dư mạnh vừa Nguy hiểm.
 */
class FinancialConsistencyValidator
{
    /**
     * Loại bỏ root cause "no_income" nếu effective income đủ lớn và số dư không âm.
     *
     * @param  array<int, array{key: string, label: string}>  $rootCauses
     * @return array<int, array{key: string, label: string}>
     */
    public function sanitizeRootCauses(float $effectiveIncome, float $minBalance, array $rootCauses): array
    {
        $cfg = config('financial_brain.consistency', []);
        $minIncome = (float) ($cfg['min_income_no_income_cause_vnd'] ?? 1_000_000);
        $minBalanceOk = (float) ($cfg['min_balance_positive_for_no_income_cause_vnd'] ?? 0);

        if ($effectiveIncome < $minIncome || $minBalance < $minBalanceOk) {
            return $rootCauses;
        }

        return array_values(array_filter($rootCauses, fn ($c) => ($c['key'] ?? '') !== 'no_income'));
    }

    /**
     * Nếu dòng tiền mạnh (runway đủ, không tháng âm) thì hạ risk xuống tối đa Cảnh báo.
     * Hoặc: nếu runway = 0 nhưng thâm hụt dưới materiality và có thanh khoản đủ → hạ risk (không "vỡ dòng tiền").
     *
     * @param  array{score: string, label: string, color: string}  $riskResult
     * @param  array{liquid_balance?: float, materiality_below?: bool, runway_from_liquidity_months?: int|null}  $liquidityContext
     */
    public function applyRiskCap(array $riskResult, float $minBalance, int $runwayMonths, array $liquidityContext = []): array
    {
        $cfg = config('financial_brain.runway', []);
        $minSurplusMonths = (int) ($cfg['min_surplus_months_strong'] ?? 6);
        $minBalanceStrong = (float) ($cfg['min_balance_strong_vnd'] ?? 5_000_000);

        $strong = $minBalance >= $minBalanceStrong && $runwayMonths >= $minSurplusMonths;
        if (! $strong) {
            $availableLiquidity = (float) ($liquidityContext['available_liquidity'] ?? $liquidityContext['liquid_balance'] ?? 0);
            $materialityBelow = (bool) ($liquidityContext['materiality_below'] ?? false);
            $runwayFromLiq = $liquidityContext['runway_from_liquidity_months'] ?? null;
            $deficitMildWithLiquidity = $materialityBelow
                && ($availableLiquidity > 0 || $runwayFromLiq !== null)
                && ($runwayFromLiq === null || $runwayFromLiq >= 12);

            if ($runwayMonths === 0 && $minBalance < 0 && $deficitMildWithLiquidity) {
                $dangerous = in_array($riskResult['score'] ?? '', [
                    \App\Services\CashflowProjectionService::RISK_CRITICAL,
                    \App\Services\CashflowProjectionService::RISK_DANGER,
                ], true);
                if ($dangerous) {
                    return ['score' => \App\Services\CashflowProjectionService::RISK_WARNING, 'label' => 'Cảnh báo', 'color' => 'yellow'];
                }
            }
            return $riskResult;
        }

        $dangerous = in_array($riskResult['score'] ?? '', [
            \App\Services\CashflowProjectionService::RISK_CRITICAL,
            \App\Services\CashflowProjectionService::RISK_DANGER,
        ], true);

        if (! $dangerous) {
            return $riskResult;
        }

        $maxWhenStrong = config('financial_brain.risk.max_risk_when_strong_cashflow', 'warning');

        if ($maxWhenStrong === 'stable') {
            return ['score' => \App\Services\CashflowProjectionService::RISK_STABLE, 'label' => 'Ổn định', 'color' => 'green'];
        }

        return ['score' => \App\Services\CashflowProjectionService::RISK_WARNING, 'label' => 'Cảnh báo', 'color' => 'yellow'];
    }
}
