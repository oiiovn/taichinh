<?php

namespace App\Services;

use App\Models\LoanContract;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanLedgerService
{
    public function getOutstandingPrincipal(LoanContract $contract): float
    {
        return $this->getOutstandingPrincipalAsOf($contract, Carbon::today()->endOfDay());
    }

    public function getOutstandingPrincipalAsOf(LoanContract $contract, Carbon $asOf): float
    {
        $asOfDate = $asOf->copy()->startOfDay();
        $sum = $contract->ledgerEntries()
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $asOfDate);
            })
            ->sum('principal_delta');
        return (float) $contract->principal_at_start + (float) $sum;
    }

    public function getTotalAccruedInterest(LoanContract $contract): float
    {
        return $this->getTotalAccruedInterestAsOf($contract, Carbon::today()->endOfDay());
    }

    public function getTotalAccruedInterestAsOf(LoanContract $contract, Carbon $asOf): float
    {
        $asOfDate = $asOf->copy()->startOfDay();
        return (float) $contract->ledgerEntries()
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->where('type', LoanLedgerEntry::TYPE_ACCRUAL)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $asOfDate);
            })
            ->sum('interest_delta');
    }

    public function getTotalPaidInterest(LoanContract $contract): float
    {
        return $this->getTotalPaidInterestAsOf($contract, Carbon::today()->endOfDay());
    }

    public function getTotalPaidInterestAsOf(LoanContract $contract, Carbon $asOf): float
    {
        $asOfDate = $asOf->copy()->startOfDay();
        return abs((float) $contract->ledgerEntries()
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->where('type', LoanLedgerEntry::TYPE_PAYMENT)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $asOfDate);
            })
            ->sum('interest_delta'));
    }

    public function getUnpaidInterest(LoanContract $contract): float
    {
        return $this->getUnpaidInterestAsOf($contract, Carbon::today()->endOfDay());
    }

    public function getUnpaidInterestAsOf(LoanContract $contract, Carbon $asOf): float
    {
        return $this->getTotalAccruedInterestAsOf($contract, $asOf) - $this->getTotalPaidInterestAsOf($contract, $asOf);
    }

    public function addAccrual(LoanContract $contract, float $interestAmount, Carbon $effectiveDate): LoanLedgerEntry
    {
        $principalDelta = 0.0;
        $interestDelta = $interestAmount;
        if ($contract->interest_calculation === LoanContract::INTEREST_CALCULATION_COMPOUND && $interestAmount > 0) {
            $principalDelta = $interestAmount;
        }
        return LoanLedgerEntry::create([
            'loan_contract_id' => $contract->id,
            'type' => LoanLedgerEntry::TYPE_ACCRUAL,
            'principal_delta' => $principalDelta,
            'interest_delta' => $interestDelta,
            'created_by_user_id' => null,
            'source' => LoanLedgerEntry::SOURCE_SYSTEM,
            'status' => LoanLedgerEntry::STATUS_CONFIRMED,
            'effective_date' => $effectiveDate,
            'meta' => [],
        ]);
    }

    /**
     * Thêm ledger entry thanh toán. Nếu truyền idempotency_key và đã tồn tại entry cùng (contract_id, key) thì trả về entry cũ (không tạo mới).
     */
    public function addPayment(
        LoanContract $contract,
        float $principalPortion,
        float $interestPortion,
        ?int $createdByUserId,
        string $source,
        Carbon $effectiveDate,
        string $status = LoanLedgerEntry::STATUS_CONFIRMED,
        ?string $idempotencyKey = null
    ): LoanLedgerEntry {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            return DB::transaction(function () use ($contract, $principalPortion, $interestPortion, $createdByUserId, $source, $effectiveDate, $status, $idempotencyKey) {
                $existing = LoanLedgerEntry::where('loan_contract_id', $contract->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('type', LoanLedgerEntry::TYPE_PAYMENT)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
                return LoanLedgerEntry::create([
                    'loan_contract_id' => $contract->id,
                    'type' => LoanLedgerEntry::TYPE_PAYMENT,
                    'principal_delta' => -$principalPortion,
                    'interest_delta' => -$interestPortion,
                    'created_by_user_id' => $createdByUserId,
                    'source' => $source,
                    'status' => $status,
                    'effective_date' => $effectiveDate,
                    'meta' => ['principal_portion' => $principalPortion, 'interest_portion' => $interestPortion],
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        }
        return LoanLedgerEntry::create([
            'loan_contract_id' => $contract->id,
            'type' => LoanLedgerEntry::TYPE_PAYMENT,
            'principal_delta' => -$principalPortion,
            'interest_delta' => -$interestPortion,
            'created_by_user_id' => $createdByUserId,
            'source' => $source,
            'status' => $status,
            'effective_date' => $effectiveDate,
            'meta' => ['principal_portion' => $principalPortion, 'interest_portion' => $interestPortion],
        ]);
    }

    public function addAdjustment(
        LoanContract $contract,
        float $principalDelta,
        float $interestDelta,
        ?int $createdByUserId,
        string $source,
        string $status = LoanLedgerEntry::STATUS_CONFIRMED,
        array $meta = []
    ): LoanLedgerEntry {
        return LoanLedgerEntry::create([
            'loan_contract_id' => $contract->id,
            'type' => LoanLedgerEntry::TYPE_ADJUSTMENT,
            'principal_delta' => $principalDelta,
            'interest_delta' => $interestDelta,
            'created_by_user_id' => $createdByUserId,
            'source' => $source,
            'status' => $status,
            'effective_date' => Carbon::today(),
            'meta' => $meta,
        ]);
    }

    public function getLastAccrualDate(LoanContract $contract): ?Carbon
    {
        $entry = $contract->ledgerEntries()
            ->where('type', LoanLedgerEntry::TYPE_ACCRUAL)
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->orderBy('effective_date', 'desc')
            ->first();
        return $entry?->effective_date?->copy();
    }

    public function getLastAccrualDateAsOf(LoanContract $contract, Carbon $asOf): ?Carbon
    {
        $asOfDate = $asOf->copy()->startOfDay();
        $entry = $contract->ledgerEntries()
            ->where('type', LoanLedgerEntry::TYPE_ACCRUAL)
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $asOfDate);
            })
            ->orderBy('effective_date', 'desc')
            ->first();
        return $entry?->effective_date?->copy();
    }
}
