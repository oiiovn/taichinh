<?php

namespace App\Services;

use App\Models\TransactionHistory;

/**
 * Intelligence Layer: gán income_source_id cho giao dịch IN từ user_income_sources (keyword/merchant/category).
 * Gọi sau bước classify category; không thay đổi system_category hay financial_role, chỉ bổ sung identity nguồn thu.
 */
class IncomeSourceResolverService
{
    public function __construct(
        protected FinancialRoleClassifier $roleClassifier
    ) {}

    /**
     * Resolve và gán income_source_id cho giao dịch IN. Trả về true nếu đã cập nhật (hoặc giữ nguyên đúng).
     */
    public function resolveAndAssign(TransactionHistory $transaction): bool
    {
        if (strtoupper((string) ($transaction->type ?? '')) !== 'IN') {
            return false;
        }

        $userId = (int) ($transaction->user_id ?? 0);
        if ($userId === 0) {
            return false;
        }

        $median = 0.0;
        $roleResult = $this->roleClassifier->classify($transaction, $median, $userId);
        $incomeSourceId = $roleResult['income_source_id'] ?? null;

        $current = $transaction->income_source_id;
        $newId = $incomeSourceId !== null ? (int) $incomeSourceId : null;

        if ($current !== $newId) {
            $transaction->income_source_id = $newId;
            $transaction->save();
            return true;
        }

        return false;
    }

    /**
     * Chỉ resolve, không ghi DB. Trả về income_source_id hoặc null.
     */
    public function resolveOnly(TransactionHistory $transaction): ?int
    {
        if (strtoupper((string) ($transaction->type ?? '')) !== 'IN') {
            return null;
        }
        $userId = (int) ($transaction->user_id ?? 0);
        if ($userId === 0) {
            return null;
        }
        $roleResult = $this->roleClassifier->classify($transaction, 0.0, $userId);

        $id = $roleResult['income_source_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }
}
