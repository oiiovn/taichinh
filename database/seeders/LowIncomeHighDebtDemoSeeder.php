<?php

namespace Database\Seeders;

use App\Models\LiabilityAccrual;
use App\Models\Pay2sBankAccount;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserLiability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản mẫu: một người lương thấp nhưng nợ lớn.
 * Dùng để test Debt Intelligence (DSI, ưu tiên trả, shock simulation).
 *
 * Đăng nhập: lowincome-highdebt@demo.local / password
 */
class LowIncomeHighDebtDemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'lowincome-highdebt@demo.local';
    public const DEMO_ACCOUNT = 'DEMO_LOW_001';

    public function run(): void
    {
        $user = $this->firstOrCreateUser();
        $pay2s = $this->ensureDemoBankAccount($user);
        TransactionHistory::where('user_id', $user->id)->delete();
        $user->userLiabilities()->delete();

        $baseDate = Carbon::now()->subMonths(5)->startOfMonth();
        $this->seedTransactions($user->id, $pay2s?->id, $baseDate);
        $this->seedLiabilities($user->id);
    }

    private function firstOrCreateUser(): User
    {
        $user = User::where('email', self::DEMO_EMAIL)->first();
        if ($user) {
            return $user;
        }
        return User::create([
            'name' => 'Mẫu – Lương thấp nợ lớn',
            'email' => self::DEMO_EMAIL,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function ensureDemoBankAccount(User $user): ?Pay2sBankAccount
    {
        $pay2s = Pay2sBankAccount::firstOrCreate(
            ['account_number' => self::DEMO_ACCOUNT],
            [
                'external_id' => 'demo-low-' . self::DEMO_ACCOUNT,
                'account_holder_name' => $user->name,
                'bank_code' => 'DEMO',
                'bank_name' => 'Ngân hàng mẫu',
                'balance' => 0,
                'last_synced_at' => now(),
            ]
        );
        UserBankAccount::firstOrCreate(
            ['user_id' => $user->id, 'account_number' => self::DEMO_ACCOUNT],
            ['bank_code' => 'DEMO', 'account_type' => 'checking', 'api_type' => 'demo', 'full_name' => $user->name]
        );
        return $pay2s;
    }

    /** 6 tháng: thu 8–9 tr/tháng, chi 5,5–6,5 tr → dòng tiền mỏng so với nợ. */
    private function seedTransactions(int $userId, ?int $pay2sAccountId, Carbon $baseDate): void
    {
        $incomePerMonth = [1 => 8_000_000, 2 => 8_500_000, 3 => 8_200_000, 4 => 8_800_000, 5 => 8_400_000, 6 => 8_600_000];
        $expensePerMonth = [1 => 6_000_000, 2 => 5_800_000, 3 => 6_200_000, 4 => 5_500_000, 5 => 6_500_000, 6 => 5_900_000];
        $seq = 0;
        for ($m = 1; $m <= 6; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = $incomePerMonth[$m];
            $expense = $expensePerMonth[$m];
            $this->insertTx($userId, $pay2sAccountId, 'IN', $income, $monthStart->copy()->addDays(2), "demo-low-m{$m}-in-" . (++$seq));
            $this->insertTx($userId, $pay2sAccountId, 'OUT', (int) floor($expense * 0.6), $monthStart->copy()->addDays(10), "demo-low-m{$m}-out1-" . (++$seq));
            $this->insertTx($userId, $pay2sAccountId, 'OUT', $expense - (int) floor($expense * 0.6), $monthStart->copy()->addDays(18), "demo-low-m{$m}-out2-" . (++$seq));
        }
    }

    private function insertTx(int $userId, ?int $pay2sAccountId, string $type, int $amount, Carbon $transactionDate, string $externalId): void
    {
        TransactionHistory::create([
            'user_id' => $userId,
            'pay2s_bank_account_id' => $pay2sAccountId,
            'external_id' => 'demo-low-' . $externalId . '-' . uniqid('', true),
            'account_number' => $pay2sAccountId ? self::DEMO_ACCOUNT : null,
            'amount' => $type === 'OUT' ? -abs($amount) : abs($amount),
            'amount_bucket' => TransactionHistory::resolveAmountBucket($amount),
            'type' => $type,
            'description' => $type === 'IN' ? 'Lương (mẫu demo)' : 'Chi tiêu (mẫu demo)',
            'merchant_key' => 'demo_low',
            'merchant_group' => 'demo_low',
            'classification_source' => 'seed',
            'system_category_id' => null,
            'user_category_id' => null,
            'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
            'classification_confidence' => 1.0,
            'classification_version' => 1,
            'transaction_date' => $transactionDate,
            'raw_json' => null,
        ]);
    }

    /** 3 khoản nợ lớn: đáo hạn gần + lãi cao để DSI và Priority Engine bật. */
    private function seedLiabilities(int $userId): void
    {
        $today = Carbon::today();

        $liabs = [
            [
                'name' => 'Nợ bạn – cần trả sớm',
                'principal' => 50_000_000,
                'interest_rate' => 0,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addDays(45),
                'start_date' => $today->copy()->subMonths(2),
            ],
            [
                'name' => 'Vay tín dụng mua xe',
                'principal' => 200_000_000,
                'interest_rate' => 18,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addMonths(3),
                'start_date' => $today->copy()->subMonths(9),
            ],
            [
                'name' => 'Vay tiêu dùng (lãi cao)',
                'principal' => 120_000_000,
                'interest_rate' => 24,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addMonths(8),
                'start_date' => $today->copy()->subMonths(4),
            ],
        ];

        foreach ($liabs as $row) {
            $l = UserLiability::create([
                'user_id' => $userId,
                'direction' => UserLiability::DIRECTION_PAYABLE,
                'name' => $row['name'],
                'principal' => $row['principal'],
                'interest_rate' => $row['interest_rate'],
                'interest_unit' => $row['interest_unit'],
                'interest_calculation' => UserLiability::INTEREST_CALCULATION_SIMPLE,
                'accrual_frequency' => UserLiability::ACCRUAL_FREQUENCY_MONTHLY,
                'start_date' => $row['start_date'],
                'due_date' => $row['due_date'],
                'auto_accrue' => true,
                'status' => UserLiability::STATUS_ACTIVE,
            ]);
            $this->seedAccrualsForLiability($l);
        }
    }

    /** Ghi lãi tích lũy vài kỳ để unpaid_interest > 0. */
    private function seedAccrualsForLiability(UserLiability $liability): void
    {
        $rate = (float) $liability->interest_rate;
        if ($rate <= 0) {
            return;
        }
        $principal = (float) $liability->principal;
        $monthlyRate = $liability->interest_unit === 'yearly' ? $rate / 100 / 12 : ($liability->interest_unit === 'monthly' ? $rate / 100 : $rate / 100 * 30);
        $start = Carbon::parse($liability->start_date)->startOfMonth();
        $end = Carbon::today()->subMonth();
        while ($start->lte($end)) {
            $amount = round($principal * $monthlyRate, 0);
            if ($amount > 0) {
                LiabilityAccrual::create([
                    'liability_id' => $liability->id,
                    'amount' => $amount,
                    'accrued_at' => $start->copy()->endOfMonth(),
                    'source' => 'system',
                ]);
            }
            $start->addMonth();
        }
    }
}
