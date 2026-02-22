<?php

namespace App\Services\TaiChinh;

use App\Services\BankBalanceService;
use App\Services\UserFinancialContextService;
use App\Models\User;

class LiquidBalanceService
{
    public function __construct(
        protected UserFinancialContextService $contextService,
        protected BankBalanceService $bankBalanceService
    ) {}

    public function forUser(?User $user): float
    {
        $linked = $this->contextService->getLinkedAccountNumbers($user);
        if (empty($linked)) {
            return 0.0;
        }
        $balances = $this->bankBalanceService->getBalancesForAccountNumbers($linked);
        return (float) array_sum($balances);
    }
}
