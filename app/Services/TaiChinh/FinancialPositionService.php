<?php

namespace App\Services\TaiChinh;

use Illuminate\Support\Collection;

class FinancialPositionService
{
    public function __construct(
        protected PositionRiskScoringService $riskScoring
    ) {}

    public function build(array $summary, Collection $unifiedLoans, float $liquidBalance = 0): array
    {
        $net = (float) $summary['total_receivable'] - (float) $summary['total_payable'];
        $debt = (float) $summary['total_payable'];
        $receivable = (float) $summary['total_receivable'];

        $risk = $this->riskScoring->score($summary, $unifiedLoans);

        return [
            'net_leverage' => $net,
            'debt_exposure' => $debt,
            'receivable_exposure' => $receivable,
            'liquid_balance' => $liquidBalance,
            'risk_level' => $risk['risk_level'],
            'risk_label' => $risk['risk_label'],
            'risk_color' => $risk['risk_color'],
        ];
    }
}
