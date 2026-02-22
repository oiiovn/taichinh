<?php

namespace App\Services;

use App\Models\TransactionHistory;

/**
 * Semantic Layer: gán vai trò dòng tiền cho từng giao dịch.
 * Transaction → Financial Role → Income/Expense Purity chỉ dùng operating.
 * Pending đi qua behavior inference (keywords, amount) thay vì hardcode weight.
 */
class FinancialRoleClassifier
{
    public const OPERATING_INCOME = 'OPERATING_INCOME';
    public const FINANCING_INFLOW = 'FINANCING_INFLOW';
    public const INTERNAL_TRANSFER = 'INTERNAL_TRANSFER';
    public const ONE_OFF_INCOME = 'ONE_OFF_INCOME';
    public const DEBT_RETURN = 'DEBT_RETURN';
    public const OPERATING_EXPENSE = 'OPERATING_EXPENSE';
    public const DEBT_PAYMENT = 'DEBT_PAYMENT';
    public const INVESTMENT = 'INVESTMENT';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Phân loại giao dịch → role + confidence [0,1]. Pending dùng behavior inference.
     *
     * @return array{role: string, confidence: float}
     */
    public function classify(TransactionHistory $t, float $medianMonthlyAmount = 0): array
    {
        $isIn = strtoupper((string) ($t->type ?? 'IN')) === 'IN';
        $cfg = config('financial_roles', []);
        $desc = strtolower((string) ($t->description ?? ''));
        $categoryName = $t->systemCategory?->name;
        $isPending = ($t->classification_status ?? '') === TransactionHistory::CLASSIFICATION_STATUS_PENDING;

        if ($isIn) {
            $roleConf = $this->classifyInflow($categoryName, $desc, $isPending, $t, $medianMonthlyAmount, $cfg);
        } else {
            $roleConf = $this->classifyOutflow($categoryName, $desc, $isPending, $t, $medianMonthlyAmount, $cfg);
        }

        return $roleConf;
    }

    private function classifyInflow(?string $categoryName, string $desc, bool $isPending, TransactionHistory $t, float $median, array $cfg): array
    {
        $inflow = $cfg['inflow_roles'] ?? [];

        if ($categoryName !== null) {
            foreach ($inflow as $role => $data) {
                $cats = $data['categories'] ?? [];
                if (in_array($categoryName, $cats, true)) {
                    $conf = $this->dynamicConfidence($t, $median, true);
                    return ['role' => $role, 'confidence' => $conf];
                }
            }
        }

        foreach (['FINANCING_INFLOW', 'INTERNAL_TRANSFER', 'DEBT_RETURN', 'ONE_OFF_INCOME'] as $role) {
            $data = $inflow[$role] ?? [];
            $keywords = $data['keywords'] ?? [];
            foreach ($keywords as $kw) {
                if (str_contains($desc, strtolower($kw))) {
                    $conf = $isPending ? 0.7 : 0.95;
                    return ['role' => $role, 'confidence' => $conf];
                }
            }
        }

        if ($isPending) {
            $spikeMult = (float) ($cfg['behavior_inference']['spike_median_multiplier'] ?? 5);
            $amount = abs((float) $t->amount);
            if ($median > 0 && $amount > $spikeMult * $median) {
                return ['role' => self::ONE_OFF_INCOME, 'confidence' => 0.3];
            }
            return ['role' => self::OPERATING_INCOME, 'confidence' => 0.5];
        }

        if ($categoryName !== null) {
            $operatingCats = $inflow['OPERATING_INCOME']['categories'] ?? [];
            if (in_array($categoryName, $operatingCats, true)) {
                return ['role' => self::OPERATING_INCOME, 'confidence' => $this->dynamicConfidence($t, $median, true)];
            }
        }

        return ['role' => self::UNKNOWN, 'confidence' => 0.3];
    }

    private function classifyOutflow(?string $categoryName, string $desc, bool $isPending, TransactionHistory $t, float $median, array $cfg): array
    {
        $outflow = $cfg['outflow_roles'] ?? [];

        if ($categoryName !== null) {
            foreach ($outflow as $role => $data) {
                $cats = $data['categories'] ?? [];
                if (in_array($categoryName, $cats, true)) {
                    $conf = $this->dynamicConfidence($t, $median, false);
                    return ['role' => $role, 'confidence' => $conf];
                }
            }
        }

        foreach (['DEBT_PAYMENT', 'INTERNAL_TRANSFER', 'INVESTMENT'] as $role) {
            $data = $outflow[$role] ?? [];
            $keywords = $data['keywords'] ?? [];
            foreach ($keywords as $kw) {
                if (str_contains($desc, strtolower($kw))) {
                    $conf = $isPending ? 0.7 : 0.95;
                    return ['role' => $role, 'confidence' => $conf];
                }
            }
        }

        if ($isPending) {
            $spikeMult = (float) ($cfg['behavior_inference']['spike_median_multiplier'] ?? 5);
            $amount = abs((float) $t->amount);
            if ($median > 0 && $amount > $spikeMult * $median) {
                return ['role' => self::DEBT_PAYMENT, 'confidence' => 0.4];
            }
            return ['role' => self::OPERATING_EXPENSE, 'confidence' => 0.5];
        }

        $operatingCats = $outflow['OPERATING_EXPENSE']['categories'] ?? [];
        if ($categoryName !== null && in_array($categoryName, $operatingCats, true)) {
            return ['role' => self::OPERATING_EXPENSE, 'confidence' => $this->dynamicConfidence($t, $median, false)];
        }

        return ['role' => self::UNKNOWN, 'confidence' => 0.3];
    }

    /**
     * confidence = classification_confidence * anomaly_score (spike → giảm).
     */
    private function dynamicConfidence(TransactionHistory $t, float $median, bool $isInflow): float
    {
        $base = (float) ($t->classification_confidence ?? 0.8);
        $base = max(0.2, min(1, $base));

        if ($median <= 0) {
            return $base;
        }
        $amount = abs((float) $t->amount);
        $ratio = $amount / $median;
        if ($ratio > 3) {
            $base *= max(0.2, 1 - ($ratio - 3) * 0.15);
        }

        return max(0.1, min(1, $base));
    }
}
