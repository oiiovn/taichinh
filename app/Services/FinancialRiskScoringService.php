<?php

namespace App\Services;

use App\Services\CashflowProjectionService;

/**
 * Risk scoring chuẩn hóa: 4 trụ (Cashflow, Runway, DTI, Leverage).
 * Phân biệt rủi ro dòng tiền vs rủi ro đòn bẩy: cashflow mạnh thì leverage không đẩy lên Nguy hiểm/Cực cao.
 */
class FinancialRiskScoringService
{
    public const PILLAR_CASHFLOW = 'cashflow';
    public const PILLAR_RUNWAY = 'runway';
    public const PILLAR_DTI = 'dti';
    public const PILLAR_LEVERAGE = 'leverage';
    public const PILLAR_DSCR = 'dscr';

    /**
     * Canonical metrics: effective_income, monthly_expense, monthly_debt, net_leverage, debt_exposure, min_balance, runway_months.
     *
     * @param  array{effective_income: float, monthly_expense: float, monthly_debt: float, net_leverage: float, debt_exposure: float, min_balance: float, runway_months: int}  $canonical
     * @param  array<int, array{so_du_cuoi: float}>  $timeline
     * @return array{score: string, label: string, color: string, pillars: array{cashflow: array, runway: array, dti: array, leverage: array}, raw_score: int}
     */
    public function score(array $canonical, array $timeline): array
    {
        $income = max(0.01, (float) ($canonical['effective_income'] ?? 0));
        $expense = (float) ($canonical['monthly_expense'] ?? 0);
        $debtMonthly = (float) ($canonical['monthly_debt'] ?? 0);
        $netLeverage = (float) ($canonical['net_leverage'] ?? 0);
        $minBalance = (float) ($canonical['min_balance'] ?? 0);
        $runwayMonths = (int) ($canonical['runway_months'] ?? 0);

        $outflow = $expense + $debtMonthly;
        $dti = $income > 0 ? $outflow / $income : 999;

        $hasNegative = $minBalance < 0;
        $allNegative = $timeline && $minBalance < 0 && min(array_column($timeline, 'so_du_cuoi')) < 0;
        $firstNegativeIndex = null;
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $firstNegativeIndex = $i;
                break;
            }
        }
        $survivalHorizonZero = $firstNegativeIndex === 0;

        $cfg = config('financial_brain.risk', []);

        // Trụ 1: Cashflow — dòng tiền có âm không, số dư thấp nhất
        $cashflowScore = 0;
        if ($allNegative) {
            $cashflowScore = 2;
        } elseif ($hasNegative) {
            $cashflowScore = 1;
        }
        $minSurplusVnd = (int) ($cfg['min_balance_strong_vnd'] ?? config('financial_brain.runway.min_balance_strong_vnd', 5_000_000));
        $strongCashflow = ! $hasNegative && count($timeline) > 0 && $minBalance >= $minSurplusVnd;
        $pillarCashflow = ['score' => $cashflowScore, 'label' => $strongCashflow ? 'Tốt' : ($cashflowScore === 2 ? 'Rất xấu' : 'Cần theo dõi'), 'strong' => $strongCashflow];

        // Trụ 2: Runway — số tháng còn có thể sống với dòng tiền hiện tại (volatile income cần >= 6 tháng)
        $requiredRunwayGood = (int) ($canonical['required_runway_months'] ?? $cfg['runway_good_months'] ?? 6);
        $runwayScore = 0;
        if ($runwayMonths <= 0 && $hasNegative) {
            $runwayScore = 2;
        } elseif ($runwayMonths < $requiredRunwayGood) {
            $runwayScore = 1;
        }
        $liquidityStatus = (string) ($canonical['liquidity_status'] ?? 'positive');
        if ($liquidityStatus === 'unknown' && $runwayScore === 2) {
            $runwayScore = 1;
        }
        $pillarRunway = ['score' => $runwayScore, 'months' => $runwayMonths, 'label' => $runwayMonths >= 12 ? '12+' : (string) $runwayMonths];

        // Trụ 3: DTI
        $dtiScore = 0;
        $dtiDanger = (float) ($cfg['dti_danger'] ?? 1.0);
        $dtiWarning = (float) ($cfg['dti_warning'] ?? 0.8);
        if ($dti >= $dtiDanger) {
            $dtiScore = 2;
        } elseif ($dti >= $dtiWarning) {
            $dtiScore = 1;
        }
        $pillarDti = ['score' => $dtiScore, 'ratio' => round($dti, 2), 'label' => $dtiScore === 0 ? 'Ổn' : ($dtiScore === 2 ? 'Cao' : 'Cần theo dõi')];

        // Trụ 4: Leverage — tổng nợ ròng
        $leverageScore = 0;
        $levCritical = (float) ($cfg['leverage_critical'] ?? -500_000_000);
        $levDanger = (float) ($cfg['leverage_danger'] ?? -100_000_000);
        if ($netLeverage <= $levCritical) {
            $leverageScore = 2;
        } elseif ($netLeverage <= $levDanger) {
            $leverageScore = 1;
        } elseif ($netLeverage < 0) {
            $leverageScore = 1;
        }
        $pillarLeverage = ['score' => $leverageScore, 'net' => $netLeverage, 'label' => $leverageScore === 0 ? 'Chấp nhận được' : 'Cao'];

        $cashflowTrumps = (bool) ($cfg['cashflow_trumps_leverage'] ?? true);
        if ($cashflowTrumps && $strongCashflow) {
            $leverageScore = 0;
            $pillarLeverage['score'] = 0;
            $pillarLeverage['label'] = 'Bù bởi dòng tiền tốt';
        }

        $dscr = $canonical['dscr'] ?? null;
        $dscrScore = 0;
        $pillarDscr = ['score' => 0, 'ratio' => null, 'label' => 'N/A'];
        if ($dscr !== null && is_numeric($dscr)) {
            $dscrVal = (float) $dscr;
            $dscrMinSafe = (float) (config('financial_structure.dscr.min_safe', 1.2));
            $dscrWarning = (float) (config('financial_structure.dscr.warning', 1.0));
            if ($dscrVal < $dscrWarning) {
                $dscrScore = 2;
                $pillarDscr = ['score' => 2, 'ratio' => round($dscrVal, 2), 'label' => 'Không đủ trả nợ'];
            } elseif ($dscrVal < $dscrMinSafe) {
                $dscrScore = 1;
                $pillarDscr = ['score' => 1, 'ratio' => round($dscrVal, 2), 'label' => 'Sát ngưỡng'];
            } else {
                $pillarDscr = ['score' => 0, 'ratio' => round($dscrVal, 2), 'label' => 'Đủ trả nợ'];
            }
        }

        $rawScore = $cashflowScore + $runwayScore + $dtiScore + $leverageScore + $dscrScore;
        $stabilityScore = (float) ($canonical['income_stability_score'] ?? 1.0);
        $rawScore += max(0, (int) round((1 - $stabilityScore) * 2));
        if ($survivalHorizonZero && $netLeverage < -50_000_000) {
            $rawScore += 2;
        }

        $debtExposure = (float) ($canonical['debt_exposure'] ?? 0);
        $surplus = $income - $outflow;
        $volatilityRatio = (float) ($canonical['volatility_ratio'] ?? (1 - $stabilityScore));
        if ($debtExposure <= 0 && $surplus >= 0 && $volatilityRatio < 0.7) {
            $rawScore = min($rawScore, 3);
        }

        $monthlyDeficitAbsolute = (float) ($canonical['monthly_deficit_absolute'] ?? 0);
        $burnDampeningVnd = (int) ($cfg['burn_dampening_absolute_vnd'] ?? 500_000);
        $burnDampeningPoints = (int) ($cfg['burn_dampening_points'] ?? 1);
        if ($monthlyDeficitAbsolute > 0 && $monthlyDeficitAbsolute < $burnDampeningVnd) {
            $rawScore = max(0, $rawScore - $burnDampeningPoints);
        }

        $monthsWithData = (int) ($canonical['months_with_data'] ?? 999);
        $confidenceMultiplier = $this->dataConfidenceMultiplier($monthsWithData, $cfg);
        $rawScore = (int) round($rawScore * $confidenceMultiplier);

        $scaleFactor = $this->scaleFactorForExpense($expense, $cfg);
        $rawScore = (int) round($rawScore * $scaleFactor);

        $result = $this->scoreToLevel($rawScore, $strongCashflow, $cfg);

        return [
            'score' => $result['score'],
            'label' => $result['label'],
            'color' => $result['color'],
            'pillars' => [
                self::PILLAR_CASHFLOW => $pillarCashflow,
                self::PILLAR_RUNWAY => $pillarRunway,
                self::PILLAR_DTI => $pillarDti,
                self::PILLAR_LEVERAGE => $pillarLeverage,
                self::PILLAR_DSCR => $pillarDscr,
            ],
            'raw_score' => $rawScore,
        ];
    }

    private function dataConfidenceMultiplier(int $monthsWithData, array $cfg): float
    {
        $bands = $cfg['confidence_bands'] ?? [
            ['max_months' => 1, 'multiplier' => 0.6],
            ['max_months' => 4, 'multiplier' => 0.8],
            ['max_months' => 999, 'multiplier' => 1.0],
        ];
        foreach ($bands as $band) {
            if ($monthsWithData <= (int) ($band['max_months'] ?? 999)) {
                return (float) ($band['multiplier'] ?? 1.0);
            }
        }
        return 1.0;
    }

    private function scaleFactorForExpense(float $monthlyExpense, array $cfg): float
    {
        $tier = $cfg['scale_tier_expense_vnd'] ?? ['small' => 1_000_000, 'medium' => 5_000_000];
        $small = (float) ($tier['small'] ?? 1_000_000);
        $medium = (float) ($tier['medium'] ?? 5_000_000);
        $factorSmall = (float) ($cfg['scale_factor_small'] ?? 0.7);
        $factorMedium = (float) ($cfg['scale_factor_medium'] ?? 0.85);
        if ($monthlyExpense < $small) {
            return $factorSmall;
        }
        if ($monthlyExpense < $medium) {
            return $factorMedium;
        }
        return 1.0;
    }

    private function scoreToLevel(int $rawScore, bool $strongCashflow, array $cfg): array
    {
        $maxWhenStrong = $cfg['max_risk_when_strong_cashflow'] ?? 'warning';
        if ($strongCashflow) {
            if ($maxWhenStrong === 'stable') {
                return ['score' => CashflowProjectionService::RISK_STABLE, 'label' => 'Ổn định', 'color' => 'green'];
            }

            return ['score' => CashflowProjectionService::RISK_WARNING, 'label' => 'Cảnh báo', 'color' => 'yellow'];
        }

        if ($rawScore >= 6) {
            return ['score' => CashflowProjectionService::RISK_CRITICAL, 'label' => 'Cực cao', 'color' => 'red'];
        }
        if ($rawScore >= 4) {
            return ['score' => CashflowProjectionService::RISK_DANGER, 'label' => 'Nguy hiểm', 'color' => 'red'];
        }
        if ($rawScore >= 2) {
            return ['score' => CashflowProjectionService::RISK_WARNING, 'label' => 'Cảnh báo', 'color' => 'yellow'];
        }

        return ['score' => CashflowProjectionService::RISK_STABLE, 'label' => 'Ổn định', 'color' => 'green'];
    }
}
