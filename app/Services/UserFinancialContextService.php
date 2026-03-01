<?php

namespace App\Services;

use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserFinancialContextService
{
    public function __construct(
        protected BankBalanceService $bankBalanceService,
        protected UserCategorySyncService $userCategorySyncService
    ) {}

    /**
     * Danh sách tài khoản ngân hàng user đã liên kết (collection).
     */
    public function getUserBankAccounts(?User $user): \Illuminate\Support\Collection
    {
        if (! $user) {
            return collect();
        }
        return $user->userBankAccounts()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Số tài khoản ngân hàng user đã liên kết (đã trim, unique).
     *
     * @return array<string>
     */
    public function getLinkedAccountNumbers(?User $user): array
    {
        $userBankAccounts = $this->getUserBankAccounts($user);
        return $userBankAccounts->pluck('account_number')->map(fn ($n) => trim((string) $n))->filter()->unique()->values()->all();
    }

    /**
     * Đảm bảo user có user_categories từ system_categories; trả về context (userBankAccounts, linkedAccountNumbers, accounts, accountBalances).
     */
    public function ensureCategoriesAndGetContext(User $user): array
    {
        $this->userCategorySyncService->ensureUserHasSystemCategories($user);
        return $this->getContext($user);
    }

    /**
     * Context tài chính: userBankAccounts, linkedAccountNumbers, accounts (Pay2s), accountBalances.
     */
    public function getContext(?User $user): array
    {
        $userBankAccounts = $this->getUserBankAccounts($user);
        $linked = $userBankAccounts->pluck('account_number')->map(fn ($n) => trim((string) $n))->filter()->unique()->values()->all();
        if (empty($linked)) {
            return [
                'userBankAccounts' => $userBankAccounts,
                'linkedAccountNumbers' => [],
                'accounts' => collect(),
                'accountBalances' => [],
            ];
        }
        $accounts = $this->bankBalanceService->getPay2sAccountsForAccountNumbers($linked);
        $accountBalances = $this->bankBalanceService->getBalancesForPay2sAccounts($accounts);
        return [
            'userBankAccounts' => $userBankAccounts,
            'linkedAccountNumbers' => $linked,
            'accounts' => $accounts,
            'accountBalances' => $accountBalances,
        ];
    }

    /**
     * Giao dịch phân trang theo user + tài khoản liên kết, áp dụng bộ lọc từ Request (stk, loai, pending, q, category_id).
     */
    public function getPaginatedTransactions(User $user, array $linkedAccountNumbers, Request $request, int $perPage = 50): LengthAwarePaginator
    {
        $query = TransactionHistory::with(['bankAccount', 'userCategory', 'systemCategory', 'depositor'])
            ->where(function ($q) use ($user, $linkedAccountNumbers) {
                $q->where('user_id', $user->id);
                if (! empty($linkedAccountNumbers)) {
                    $q->where(function ($q2) use ($linkedAccountNumbers) {
                        $q2->whereIn('account_number', $linkedAccountNumbers)
                            ->orWhereHas('bankAccount', fn ($q3) => $q3->whereIn('account_number', $linkedAccountNumbers));
                    });
                }
                if (! empty($linkedAccountNumbers)) {
                    $q->orWhere(function ($q2) use ($linkedAccountNumbers) {
                        $q2->whereNull('user_id')->whereIn('account_number', $linkedAccountNumbers);
                    });
                }
            });

        $query->when($request->filled('stk'), fn ($q) => $q->where('account_number', $request->input('stk')))
            ->when($request->filled('loai') && in_array($request->input('loai'), ['IN', 'OUT'], true), fn ($q) => $q->where('type', $request->input('loai')))
            ->when($request->boolean('pending'), fn ($q) => $q->where('classification_status', 'pending'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('user_category_id', $request->input('category_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . mb_strtolower(trim($request->input('q'))) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(CAST(COALESCE(account_number, \'\') AS CHAR)) LIKE ?', [$term]);
                });
            })
            ->orderBy('transaction_date', 'desc');

        return $query->paginate($perPage)->withQueryString();
    }
}
