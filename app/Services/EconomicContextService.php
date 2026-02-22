<?php

namespace App\Services;

use App\Data\SemanticTransactionCollection;

/**
 * Aggregate từ semantic → economic_context (income_concentration, platform_dependency, recurring_ratio, volatility…).
 */
class EconomicContextService
{
    public function compute(SemanticTransactionCollection $semantic): array
    {
        $items = $semantic->all();
        $incomeByMonth = $semantic->incomeByMonth();
        $expenseByMonth = $semantic->expenseByMonth();

        $totalIn = array_sum($incomeByMonth);
        $totalOut = array_sum($expenseByMonth);

        $bySourceIn = [];
        foreach ($items as $dto) {
            if (! $dto->isInflow()) {
                continue;
            }
            $key = $dto->merchantGroup ?? $dto->systemCategory ?? 'unknown';
            $bySourceIn[$key] = ($bySourceIn[$key] ?? 0) + $dto->amount;
        }
        $incomeConcentration = $this->concentrationRatio($bySourceIn, $totalIn);

        $platformsIn = [];
        foreach ($items as $dto) {
            if (! $dto->isInflow()) {
                continue;
            }
            $key = $dto->merchantGroup ?? $dto->systemCategory ?? 'unknown';
            $platformsIn[$key] = true;
        }
        $platformDependency = $totalIn > 0 ? (count($platformsIn) <= 2 ? 'high' : (count($platformsIn) <= 5 ? 'medium' : 'low')) : 'unknown';

        $recurringOut = 0.0;
        foreach ($items as $dto) {
            if ($dto->isOutflow() && $dto->recurringFlag) {
                $recurringOut += $dto->amount;
            }
        }
        $recurringRatio = $totalOut > 0 ? round($recurringOut / $totalOut, 4) : 0.0;

        $volatilityIncome = $this->volatilityFromMonthly($incomeByMonth);
        $volatilityExpense = $this->volatilityFromMonthly($expenseByMonth);
        $volatilityCluster = $this->volatilityCluster($volatilityIncome, $volatilityExpense);

        $merchantCount = [];
        foreach ($items as $dto) {
            $key = $dto->merchantGroup ?? $dto->systemCategory ?? 'unknown';
            $merchantCount[$key] = ($merchantCount[$key] ?? 0) + 1;
        }
        $merchantEntropy = $this->entropyFromCounts($merchantCount);

        return [
            'income_concentration' => round($incomeConcentration, 4),
            'platform_dependency' => $platformDependency,
            'recurring_ratio' => $recurringRatio,
            'volatility_income' => round($volatilityIncome, 4),
            'volatility_expense' => round($volatilityExpense, 4),
            'volatility_cluster' => $volatilityCluster,
            'merchant_entropy' => round($merchantEntropy, 4),
        ];
    }

    private function concentrationRatio(array $bySource, float $total): float
    {
        if ($total <= 0 || empty($bySource)) {
            return 0.0;
        }
        arsort($bySource);
        $top = (float) array_values($bySource)[0];

        return $top / $total;
    }

    private function volatilityFromMonthly(array $byMonth): float
    {
        $values = array_values($byMonth);
        if (count($values) < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / count($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $variance /= count($values);

        return $variance > 0 ? sqrt($variance) : 0.0;
    }

    private function volatilityCluster(float $volIn, float $volOut): string
    {
        $max = max($volIn, $volOut);
        if ($max <= 0) {
            return 'stable';
        }
        $mean = ($volIn + $volOut) / 2;
        if ($mean <= 0) {
            return 'stable';
        }
        $cv = $max / $mean;
        if ($cv >= 0.5) {
            return 'high';
        }
        if ($cv >= 0.2) {
            return 'medium';
        }

        return 'low';
    }

    private function entropyFromCounts(array $counts): float
    {
        $total = array_sum($counts);
        if ($total <= 0) {
            return 0.0;
        }
        $entropy = 0.0;
        foreach ($counts as $c) {
            $p = $c / $total;
            if ($p > 0) {
                $entropy -= $p * log($p, 2);
            }
        }

        return $entropy;
    }
}
