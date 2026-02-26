<?php

namespace App\Services\TaiChinh;

use App\Models\LoanContract;
use App\Models\User;
use App\Models\UserLiability;
use App\Services\LoanLedgerService;
use Illuminate\Support\Collection;

class UnifiedLoansBuilderService
{
    public function __construct(
        protected LoanLedgerService $ledgerService
    ) {}

    public function getLoanContractsForUser(User $user): Collection
    {
        $asLender = $user->loanContractsAsLender()->with(['borrower'])->get();
        $asBorrower = $user->loanContractsAsBorrower()->with(['lender'])->get();
        return $asLender->merge($asBorrower)->unique('id')->sortByDesc('created_at')->values();
    }

    public function build(
        Collection $userLiabilities,
        Collection $loanContracts,
        int $userId
    ): Collection {
        $items = collect();
        foreach ($userLiabilities as $l) {
            $principalStart = (float) $l->principal + (float) $l->payments()->sum('principal_portion');
            $principalStart = $principalStart > 0 ? $principalStart : (float) $l->principal;
            $outstanding = (float) $l->outstandingPrincipal();
            $progress = $principalStart > 0 ? min(100, round((1 - $outstanding / $principalStart) * 100, 1)) : 0;
            $items->push((object) [
                'source' => 'personal',
                'entity' => $l,
                'name' => $l->name,
                'counterparty' => null,
                'outstanding' => $outstanding,
                'principal_start' => $principalStart,
                'progress_percent' => $progress,
                'total_accrued' => $l->totalAccruedInterest(),
                'unpaid_interest' => $l->unpaidAccruedInterest(),
                'is_receivable' => $l->isReceivable(),
                'interest_display' => number_format((float) $l->interest_rate, 1) . '% / ' . ($l->interest_unit === 'yearly' ? 'năm' : ($l->interest_unit === 'monthly' ? 'tháng' : 'ngày')) . ' · ' . ($l->interest_calculation === 'compound' ? 'lãi kép' : 'lãi đơn'),
                'status' => $l->status,
                'is_active' => $l->status === UserLiability::STATUS_ACTIVE,
                'due_date' => $l->due_date,
                'created_at' => $l->created_at,
            ]);
        }
        foreach ($loanContracts as $c) {
            $outstanding = $this->ledgerService->getOutstandingPrincipal($c);
            $totalAccrued = $this->ledgerService->getTotalAccruedInterest($c);
            $unpaid = $this->ledgerService->getUnpaidInterest($c);
            $isLender = (int) $c->lender_user_id === (int) $userId;
            $principalStart = (float) $c->principal_at_start;
            $progress = $principalStart > 0 ? min(100, round(max(0, ($principalStart - $outstanding) / $principalStart) * 100, 1)) : 0;
            $items->push((object) [
                'source' => 'linked',
                'entity' => $c,
                'name' => $c->name,
                'counterparty' => $c->borrowerDisplayName(),
                'outstanding' => $outstanding,
                'principal_start' => $principalStart,
                'progress_percent' => $progress,
                'total_accrued' => $totalAccrued,
                'unpaid_interest' => $unpaid,
                'is_receivable' => $isLender,
                'interest_display' => number_format((float) $c->interest_rate, 1) . '% / ' . ($c->interest_unit === 'yearly' ? 'năm' : ($c->interest_unit === 'monthly' ? 'tháng' : 'ngày')) . ' · ' . strtolower($c->interestCalculationLabel()),
                'status' => $c->status,
                'is_active' => $c->status === LoanContract::STATUS_ACTIVE,
                'due_date' => $c->due_date,
                'created_at' => $c->created_at,
            ]);
        }
        return $items->sortByDesc('created_at')->values();
    }
}
