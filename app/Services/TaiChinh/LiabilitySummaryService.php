<?php

namespace App\Services\TaiChinh;

use App\Models\LoanContract;
use App\Models\UserLiability;
use App\Services\LoanLedgerService;
use Illuminate\Support\Collection;

class LiabilitySummaryService
{
    public function __construct(
        protected LoanLedgerService $ledgerService
    ) {}

    public function build(
        Collection $userLiabilities,
        Collection $loanContracts,
        int $userId
    ): array {
        $activeLiabilities = $userLiabilities->where('status', UserLiability::STATUS_ACTIVE);
        $payable = $activeLiabilities->where('direction', UserLiability::DIRECTION_PAYABLE)->sum(fn ($l) => $l->outstandingPrincipal() + $l->unpaidAccruedInterest());
        $receivable = $activeLiabilities->where('direction', UserLiability::DIRECTION_RECEIVABLE)->sum(fn ($l) => $l->outstandingPrincipal() + $l->unpaidAccruedInterest());
        $principal = $activeLiabilities->sum(fn ($l) => (float) $l->principal);
        $accrued = $activeLiabilities->sum(fn ($l) => $l->totalAccruedInterest());

        $activeContracts = $loanContracts->where('status', LoanContract::STATUS_ACTIVE);
        foreach ($activeContracts as $c) {
            $outstanding = $this->ledgerService->getOutstandingPrincipal($c);
            $unpaid = $this->ledgerService->getUnpaidInterest($c);
            $total = $outstanding + $unpaid;
            $accrued += $this->ledgerService->getTotalAccruedInterest($c);
            $principal += $outstanding;
            if ($c->lender_user_id === $userId) {
                $receivable += $total;
            } else {
                $payable += $total;
            }
        }

        return [
            'total_principal' => $principal,
            'total_accrued' => $accrued,
            'total_outstanding' => $payable + $receivable,
            'total_payable' => $payable,
            'total_receivable' => $receivable,
        ];
    }
}
