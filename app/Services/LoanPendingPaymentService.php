<?php

namespace App\Services;

use App\Models\LoanContract;
use App\Models\LoanLedgerEntry;
use App\Models\LoanPendingPayment;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanPendingPaymentService
{
    public function __construct(
        protected LoanLedgerService $ledgerService
    ) {}

    /**
     * Tạo mã nội dung CK để match: LOAN{id}{6số random}
     */
    public function generateMatchContent(LoanContract $contract): string
    {
        do {
            $num = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $code = 'LOAN' . $contract->id . $num;
        } while (LoanPendingPayment::where('match_content', $code)->where('status', '!=', LoanPendingPayment::STATUS_CONFIRMED)->exists());

        return $code;
    }

    /**
     * Tạo giao dịch chờ thanh toán khi đến ngày.
     */
    public function createPendingForDueDate(LoanContract $contract, Carbon $dueDate): ?LoanPendingPayment
    {
        if (! $contract->payment_schedule_enabled || $contract->status !== LoanContract::STATUS_ACTIVE) {
            return null;
        }
        if (! $contract->isLinked()) {
            return null;
        }

        $exists = LoanPendingPayment::where('loan_contract_id', $contract->id)
            ->where('due_date', $dueDate)
            ->whereIn('status', [LoanPendingPayment::STATUS_AWAITING, LoanPendingPayment::STATUS_MATCHED_BANK, LoanPendingPayment::STATUS_PENDING_CONFIRM, LoanPendingPayment::STATUS_CONFIRMED])
            ->exists();
        if ($exists) {
            return null;
        }

        $expectedPrincipal = $this->ledgerService->getOutstandingPrincipal($contract);
        $expectedInterest = $this->ledgerService->getUnpaidInterest($contract);

        return LoanPendingPayment::create([
            'loan_contract_id' => $contract->id,
            'due_date' => $dueDate,
            'expected_principal' => $expectedPrincipal,
            'expected_interest' => $expectedInterest,
            'match_content' => $this->generateMatchContent($contract),
            'status' => LoanPendingPayment::STATUS_AWAITING,
        ]);
    }

    /**
     * Match giao dịch ngân hàng với pending payment.
     */
    public function tryMatchBankTransaction(TransactionHistory $transaction): ?LoanPendingPayment
    {
        if (strtoupper((string) $transaction->type) !== 'IN') {
            return null;
        }

        $amount = (float) $transaction->amount;
        $descNorm = preg_replace('/[\s\-]/', '', (string) $transaction->description);

        $pending = LoanPendingPayment::where('status', LoanPendingPayment::STATUS_AWAITING)
            ->with('loanContract')
            ->get();

        foreach ($pending as $p) {
            $matchNorm = preg_replace('/[\s\-]/', '', $p->match_content);
            if ($matchNorm === '' || ! str_contains($descNorm, $matchNorm)) {
                continue;
            }
            $expected = (float) $p->expected_principal + (float) $p->expected_interest;
            if ($expected <= 0 || abs($amount - $expected) < 100) {
                return $this->confirmMatchedBank($p, $transaction);
            }
        }
        return null;
    }

    protected function confirmMatchedBank(LoanPendingPayment $pending, TransactionHistory $tx): LoanPendingPayment
    {
        return DB::transaction(function () use ($pending, $tx) {
            $contract = $pending->loanContract;
            $principal = (float) $pending->expected_principal;
            $interest = (float) $pending->expected_interest;

            $entry = $this->ledgerService->addPayment(
                $contract,
                $principal,
                $interest,
                null,
                LoanLedgerEntry::SOURCE_SYSTEM,
                Carbon::parse($tx->transaction_date ?? now())
            );

            $pending->update([
                'status' => LoanPendingPayment::STATUS_CONFIRMED,
                'transaction_history_id' => $tx->id,
                'bank_transaction_ref' => $tx->external_id,
                'payment_method' => LoanPendingPayment::PAYMENT_METHOD_BANK,
                'confirmed_by_user_id' => null,
                'confirmed_at' => now(),
                'loan_ledger_entry_id' => $entry->id,
                'meta' => array_merge($pending->meta ?? [], ['auto_matched' => true]),
            ]);

            return $pending->fresh();
        });
    }

    /**
     * Bên vay/cho vay ghi đã thanh toán (bank hoặc tiền mặt) -> chờ đối phương xác nhận.
     */
    public function recordManualPayment(
        LoanPendingPayment $pending,
        int $userId,
        string $paymentMethod,
        ?string $bankRef = null,
        float $principalPortion = 0,
        float $interestPortion = 0
    ): LoanPendingPayment {
        if ($pending->status !== LoanPendingPayment::STATUS_AWAITING) {
            throw new \InvalidArgumentException('Chỉ ghi thanh toán khi đang chờ thanh toán.');
        }

        $contract = $pending->loanContract;
        $principal = $principalPortion > 0 ? $principalPortion : (float) $pending->expected_principal;
        $interest = $interestPortion > 0 ? $interestPortion : (float) $pending->expected_interest;

        $source = $contract->lender_user_id === $userId ? LoanLedgerEntry::SOURCE_LENDER : LoanLedgerEntry::SOURCE_BORROWER;

        return DB::transaction(function () use ($pending, $userId, $paymentMethod, $bankRef, $principal, $interest, $source) {
            $entry = $this->ledgerService->addPayment(
                $pending->loanContract,
                $principal,
                $interest,
                $userId,
                $source,
                $pending->due_date,
                LoanLedgerEntry::STATUS_PENDING
            );

            $pending->update([
                'status' => LoanPendingPayment::STATUS_PENDING_CONFIRM,
                'payment_method' => $paymentMethod,
                'bank_transaction_ref' => $bankRef,
                'recorded_by_user_id' => $userId,
                'recorded_at' => now(),
                'loan_ledger_entry_id' => $entry->id,
            ]);

            return $pending->fresh();
        });
    }

    /**
     * Đối phương xác nhận giao dịch -> chuyển trạng thái confirmed.
     */
    public function confirmByCounterparty(LoanPendingPayment $pending, int $userId): LoanPendingPayment
    {
        if (! $pending->needsCounterpartyConfirm($userId)) {
            throw new \InvalidArgumentException('Bạn không phải đối phương để xác nhận.');
        }

        return DB::transaction(function () use ($pending) {
            $entry = LoanLedgerEntry::findOrFail($pending->loan_ledger_entry_id);
            $entry->update(['status' => LoanLedgerEntry::STATUS_CONFIRMED]);

            $pending->update([
                'status' => LoanPendingPayment::STATUS_CONFIRMED,
                'confirmed_by_user_id' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            return $pending->fresh();
        });
    }
}
