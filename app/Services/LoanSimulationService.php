<?php

namespace App\Services;

use App\Models\LoanContract;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;

/**
 * Mô phỏng ledger in-memory, không ghi DB.
 * Snapshot = bản sao trạng thái ledger tại asOf; có thể áp dụng thanh toán giả định rồi tính lại dư nợ.
 */
class LoanSimulationService
{
    public function __construct(
        private LoanLedgerService $ledgerService
    ) {}

    /**
     * Lấy snapshot ledger của hợp đồng tại thời điểm asOf (mảng in-memory, không persist).
     *
     * @return array<int, object{effective_date: Carbon, type: string, principal_delta: float, interest_delta: float, status: string}>
     */
    public function getLedgerSnapshotAsOf(LoanContract $contract, Carbon $asOf): array
    {
        $asOfDate = $asOf->copy()->startOfDay();
        $entries = $contract->ledgerEntries()
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $asOfDate);
            })
            ->orderBy('effective_date')
            ->get();

        $snapshot = [];
        foreach ($entries as $e) {
            $snapshot[] = (object) [
                'effective_date' => $e->effective_date ? $e->effective_date->copy() : null,
                'type' => $e->type,
                'principal_delta' => (float) $e->principal_delta,
                'interest_delta' => (float) $e->interest_delta,
                'status' => $e->status,
            ];
        }
        return $snapshot;
    }

    /**
     * Áp dụng một thanh toán giả định vào snapshot (thêm phần tử vào mảng, không ghi DB).
     *
     * @param array<int, object> $snapshot
     * @return array<int, object>
     */
    public function applyPaymentToSnapshot(
        array $snapshot,
        float $principalPortion,
        float $interestPortion,
        Carbon $effectiveDate
    ): array {
        $snapshot[] = (object) [
            'effective_date' => $effectiveDate->copy(),
            'type' => LoanLedgerEntry::TYPE_PAYMENT,
            'principal_delta' => -$principalPortion,
            'interest_delta' => -$interestPortion,
            'status' => LoanLedgerEntry::STATUS_CONFIRMED,
        ];
        return $snapshot;
    }

    /**
     * Tính dư nợ gốc từ snapshot (cùng công thức getOutstandingPrincipalAsOf: principal_at_start + sum principal_delta confirmed).
     */
    public function getOutstandingPrincipalFromSnapshot(float $principalAtStart, array $snapshot): float
    {
        $sum = 0.0;
        foreach ($snapshot as $e) {
            if (($e->status ?? '') === LoanLedgerEntry::STATUS_CONFIRMED) {
                $sum += (float) ($e->principal_delta ?? 0);
            }
        }
        return (float) $principalAtStart + $sum;
    }

    /**
     * Tính lãi đã tích lũy từ snapshot (chỉ type accrual, confirmed).
     */
    public function getAccruedInterestFromSnapshot(array $snapshot): float
    {
        $sum = 0.0;
        foreach ($snapshot as $e) {
            if (($e->status ?? '') === LoanLedgerEntry::STATUS_CONFIRMED && ($e->type ?? '') === LoanLedgerEntry::TYPE_ACCRUAL) {
                $sum += (float) ($e->interest_delta ?? 0);
            }
        }
        return $sum;
    }

    /**
     * Tính lãi đã trả từ snapshot (chỉ type payment, confirmed).
     */
    public function getPaidInterestFromSnapshot(array $snapshot): float
    {
        $sum = 0.0;
        foreach ($snapshot as $e) {
            if (($e->status ?? '') === LoanLedgerEntry::STATUS_CONFIRMED && ($e->type ?? '') === LoanLedgerEntry::TYPE_PAYMENT) {
                $sum += abs((float) ($e->interest_delta ?? 0));
            }
        }
        return $sum;
    }

    /**
     * Mô phỏng: snapshot tại asOf, áp dụng thanh toán giả định, trả về dư nợ gốc sau thanh toán.
     */
    public function simulateOutstandingAfterPayment(
        LoanContract $contract,
        Carbon $asOf,
        float $principalPortion,
        float $interestPortion,
        Carbon $paymentEffectiveDate
    ): float {
        $snapshot = $this->getLedgerSnapshotAsOf($contract, $asOf);
        $snapshot = $this->applyPaymentToSnapshot($snapshot, $principalPortion, $interestPortion, $paymentEffectiveDate);
        return $this->getOutstandingPrincipalFromSnapshot((float) $contract->principal_at_start, $snapshot);
    }
}
