<?php

namespace App\DTOs;

/**
 * Profile hành vi tài chính (debt style, risk, kỷ luật, tín hiệu) để inject vào payload GPT và Strategic Core.
 */
final class BehavioralProfileDTO
{
    public function __construct(
        public string $debtStyle,
        public string $riskTolerance,
        public float $spendingDisciplineScore,
        public float $executionConsistencyScore,
        public string $surplusUsagePattern,
        public array $behaviorSignals,
        public ?float $executionConsistencyScoreReduceExpense = null,
        public ?float $executionConsistencyScoreDebt = null,
        public ?float $executionConsistencyScoreIncome = null,
    ) {}

    public function toPayloadArray(): array
    {
        $out = [
            'debt_style' => $this->debtStyle,
            'risk_tolerance' => $this->riskTolerance,
            'spending_discipline_score' => round($this->spendingDisciplineScore, 2),
            'execution_consistency_score' => round($this->executionConsistencyScore, 2),
            'surplus_usage_pattern' => $this->surplusUsagePattern,
            'signals' => $this->behaviorSignals,
        ];
        if ($this->executionConsistencyScoreReduceExpense !== null) {
            $out['execution_consistency_score_reduce_expense'] = round($this->executionConsistencyScoreReduceExpense, 2);
        }
        if ($this->executionConsistencyScoreDebt !== null) {
            $out['execution_consistency_score_debt'] = round($this->executionConsistencyScoreDebt, 2);
        }
        if ($this->executionConsistencyScoreIncome !== null) {
            $out['execution_consistency_score_income'] = round($this->executionConsistencyScoreIncome, 2);
        }
        return $out;
    }
}
