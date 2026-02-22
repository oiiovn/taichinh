<?php

namespace App\Services;

use App\Models\Pay2sBankAccount;
use App\Models\TransactionHistory;
use Illuminate\Support\Collection;

class BankBalanceService
{
    /**
     * Số dư theo từng tài khoản: balance Pay2s tại last_synced_at + delta giao dịch sau cutoff đến endOfDay.
     *
     * @param  array<string>  $accountNumbers
     * @return array<string, float> account_number => balance
     */
    public function getBalancesForAccountNumbers(array $accountNumbers): array
    {
        if (empty($accountNumbers)) {
            return [];
        }
        $accounts = Pay2sBankAccount::orderBy('last_synced_at', 'desc')
            ->whereIn('account_number', $accountNumbers)
            ->get();
        return $this->getBalancesForPay2sAccounts($accounts);
    }

    /**
     * Số dư theo từng Pay2s account: balance + delta giao dịch sau last_synced_at.
     *
     * @return array<string, float> account_number => balance
     */
    public function getBalancesForPay2sAccounts(Collection $pay2sAccounts): array
    {
        $result = [];
        foreach ($pay2sAccounts as $pay2s) {
            $cutoff = $pay2s->last_synced_at;
            $base = (float) $pay2s->balance;
            if (! $cutoff) {
                $result[$pay2s->account_number] = $base;
                continue;
            }
            $delta = (float) (TransactionHistory::where('pay2s_bank_account_id', $pay2s->id)
                ->where('transaction_date', '>', $cutoff)
                ->where('transaction_date', '<=', now()->endOfDay())
                ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE -amount END) as delta")
                ->value('delta') ?? 0);
            $result[$pay2s->account_number] = $base + $delta;
        }
        return $result;
    }

    /**
     * Pay2sBankAccount collection cho danh sách số tài khoản (user đã liên kết).
     *
     * @param  array<string>  $accountNumbers
     * @return Collection<int, Pay2sBankAccount>
     */
    public function getPay2sAccountsForAccountNumbers(array $accountNumbers): Collection
    {
        if (empty($accountNumbers)) {
            return collect();
        }
        return Pay2sBankAccount::orderBy('last_synced_at', 'desc')
            ->whereIn('account_number', $accountNumbers)
            ->get();
    }
}
