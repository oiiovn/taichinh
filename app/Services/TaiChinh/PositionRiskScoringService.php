<?php

namespace App\Services\TaiChinh;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tính điểm rủi ro vị thế tài chính từ summary + danh sách khoản vay.
 * Ngưỡng và điểm lấy từ config financial_brain.position_risk (DSCR dùng financial_structure.dscr).
 */
class PositionRiskScoringService
{
    public function score(array $summary, Collection $unifiedLoans): array
    {
        $cfg = config('financial_brain.position_risk', []);
        $lev = $cfg['leverage_vnd'] ?? [];
        $rateCfg = $cfg['interest_rate_annual_pct'] ?? [];
        $ratePoints = $cfg['interest_rate_points'] ?? ['high' => 2, 'medium' => 1];
        $dueCfg = $cfg['due_date'] ?? [];
        $recvDebt = $cfg['receivable_debt_ratio'] ?? [];
        $debtOnlyPoints = (int) ($cfg['debt_only_no_receivable_points'] ?? 2);
        $bands = $cfg['bands'] ?? [
            ['min_score' => 5, 'level' => 'high', 'label' => 'Cao', 'color' => 'red'],
            ['min_score' => 2, 'level' => 'medium', 'label' => 'Trung bình', 'color' => 'yellow'],
            ['min_score' => 0, 'level' => 'low', 'label' => 'Thấp', 'color' => 'green'],
        ];

        $net = (float) $summary['total_receivable'] - (float) $summary['total_payable'];
        $debt = (float) $summary['total_payable'];
        $receivable = (float) $summary['total_receivable'];

        $riskScore = 0;
        $today = now()->startOfDay();
        $rateHigh = (float) ($rateCfg['high'] ?? 24);
        $rateMedium = (float) ($rateCfg['medium'] ?? 12);
        $overduePoints = (int) ($dueCfg['overdue_points'] ?? 3);
        $withinDays = (int) ($dueCfg['within_days'] ?? 30);
        $withinDaysPoints = (int) ($dueCfg['within_days_points'] ?? 1);

        foreach ($unifiedLoans as $item) {
            if (! $item->is_active) {
                continue;
            }
            $rate = LoanItemRateHelper::annualRate($item->entity);
            if ($rate > $rateHigh) {
                $riskScore += (int) ($ratePoints['high'] ?? 2);
            } elseif ($rate > $rateMedium) {
                $riskScore += (int) ($ratePoints['medium'] ?? 1);
            }
            if ($item->due_date) {
                $due = $item->due_date instanceof Carbon ? $item->due_date->copy()->startOfDay() : Carbon::parse($item->due_date)->startOfDay();
                if ($due->lt($today)) {
                    $riskScore += $overduePoints;
                } elseif ($due->diffInDays($today, false) > -$withinDays) {
                    $riskScore += $withinDaysPoints;
                }
            }
        }

        $warningBelow = (float) ($recvDebt['warning_below'] ?? 0.5);
        $recvDebtPoints = (int) ($recvDebt['points'] ?? 2);
        if ($debt > 0 && $receivable > 0 && $receivable / $debt < $warningBelow) {
            $riskScore += $recvDebtPoints;
        }

        $levCritical = (float) ($lev['critical'] ?? 500_000_000);
        $levDanger = (float) ($lev['danger'] ?? 100_000_000);
        $levWarning = (float) ($lev['warning'] ?? 10_000_000);
        $absNet = abs($net);
        if ($net < 0) {
            if ($absNet >= $levCritical) {
                $riskScore += 3;
            } elseif ($absNet >= $levDanger) {
                $riskScore += 2;
            } elseif ($absNet > $levWarning) {
                $riskScore += 1;
            }
        }

        if ($debt > 0 && $receivable <= 0) {
            $riskScore += $debtOnlyPoints;
        }

        usort($bands, fn ($a, $b) => ($b['min_score'] ?? 0) <=> ($a['min_score'] ?? 0));
        $level = 'low';
        $label = 'Thấp';
        $color = 'green';
        foreach ($bands as $band) {
            if ($riskScore >= (int) ($band['min_score'] ?? 0)) {
                $level = $band['level'] ?? 'low';
                $label = $band['label'] ?? 'Thấp';
                $color = $band['color'] ?? 'green';
                break;
            }
        }

        return [
            'risk_level' => $level,
            'risk_label' => $label,
            'risk_color' => $color,
            'raw_score' => $riskScore,
        ];
    }
}
