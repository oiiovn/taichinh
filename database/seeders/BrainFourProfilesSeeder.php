<?php

namespace Database\Seeders;

use App\Models\BehaviorLog;
use App\Models\FinancialInsightFeedback;
use App\Models\LiabilityAccrual;
use App\Models\Pay2sBankAccount;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserBehaviorProfile;
use App\Models\UserLiability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 4 tài khoản mẫu để test từng lớp Brain:
 * 1. Platform Dependent — economic_context, buffer modifier, concentration.
 * 2. Compliance cao — execution_consistency, aggressive optimization.
 * 3. Compliance thấp — behavioral adaptation, ít ép giảm chi.
 * 4. Debt heavy + concentration + crisis — full stack.
 *
 * Đăng nhập: brain1@demo.local … brain4@demo.local / password
 *
 * Cách test: php artisan db:seed --class=BrainFourProfilesSeeder
 * Sau đó (tùy chọn): php artisan behavior:compliance && php artisan forecast:learn
 * Vào Tài chính → Chiến lược, bấm "Làm mới insight" để so sánh buffer, narrative, DSI.
 */
class BrainFourProfilesSeeder extends Seeder
{
    private const ACCOUNTS = [
        'brain1@demo.local' => ['name' => 'Brain 1 – Platform Dependent', 'account' => 'DEMO_BRAIN_01'],
        'brain2@demo.local' => ['name' => 'Brain 2 – Compliance cao', 'account' => 'DEMO_BRAIN_02'],
        'brain3@demo.local' => ['name' => 'Brain 3 – Compliance thấp', 'account' => 'DEMO_BRAIN_03'],
        'brain4@demo.local' => ['name' => 'Brain 4 – Debt Crisis + Concentration', 'account' => 'DEMO_BRAIN_04'],
    ];

    public function run(): void
    {
        $this->seedMau1PlatformDependent();
        $this->seedMau2ComplianceCao();
        $this->seedMau3ComplianceThap();
        $this->seedMau4DebtHeavy();
    }

    /** Mẫu 1: 12 tháng, 82% ShopeeFood, 10% GrabFood, 8% lặt vặt; 2 tháng giảm thu 30%; chi nguyên liệu + phí nền tảng. */
    private function seedMau1PlatformDependent(): void
    {
        $email = 'brain1@demo.local';
        $user = $this->firstOrCreateUser($email, self::ACCOUNTS[$email]['name']);
        $pay2s = $this->ensureDemoBankAccount($user, self::ACCOUNTS[$email]['account']);
        $this->clearUserData($user);

        $baseDate = Carbon::now()->subMonths(12)->startOfMonth();
        $seq = 0;
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $incomeBase = 45_000_000;
            if ($m === 4 || $m === 9) {
                $incomeBase = (int) ($incomeBase * 0.70);
            }
            $shopee = (int) ($incomeBase * 0.82);
            $grab = (int) ($incomeBase * 0.10);
            $other = $incomeBase - $shopee - $grab;
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $shopee, $monthStart->copy()->addDays(5), "b1-m{$m}-shopee-" . (++$seq), 'ShopeeFood');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $grab, $monthStart->copy()->addDays(12), "b1-m{$m}-grab-" . (++$seq), 'GrabFood');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $other, $monthStart->copy()->addDays(20), "b1-m{$m}-other-" . (++$seq), 'other_income');
            $expense = (int) ($incomeBase * 0.72);
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', (int) ($expense * 0.40), $monthStart->copy()->addDays(3), "b1-m{$m}-nl-" . (++$seq), 'nguyen_lieu');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', (int) ($expense * 0.25), $monthStart->copy()->addDays(10), "b1-m{$m}-platform-" . (++$seq), 'platform_fee');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', $expense - (int) ($expense * 0.65), $monthStart->copy()->addDays(18), "b1-m{$m}-sinhhoat-" . (++$seq), 'sinh_hoat');
        }
    }

    /** Mẫu 2: 12 tháng; chi giảm dần (để compliance “sau 30 ngày” đạt); 5 log accepted + action_taken true. */
    private function seedMau2ComplianceCao(): void
    {
        $email = 'brain2@demo.local';
        $user = $this->firstOrCreateUser($email, self::ACCOUNTS[$email]['name']);
        $pay2s = $this->ensureDemoBankAccount($user, self::ACCOUNTS[$email]['account']);
        $this->clearUserData($user);

        $baseDate = Carbon::now()->subMonths(12)->startOfMonth();
        $seq = 0;
        $expenseByMonth = [28_000_000, 27_500_000, 26_000_000, 25_500_000, 24_500_000, 24_000_000, 23_500_000, 23_000_000, 22_500_000, 22_000_000, 21_500_000, 21_000_000];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = 30_000_000;
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $income, $monthStart->copy()->addDays(5), "b2-m{$m}-in-" . (++$seq), 'salary');
            $exp = $expenseByMonth[$m - 1];
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', (int) ($exp * 0.6), $monthStart->copy()->addDays(10), "b2-m{$m}-out1-" . (++$seq), 'expense');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', $exp - (int) ($exp * 0.6), $monthStart->copy()->addDays(20), "b2-m{$m}-out2-" . (++$seq), 'expense');
        }
        $now = Carbon::now();
        for ($i = 0; $i < 5; $i++) {
            $loggedAt = $now->copy()->subDays(35 + $i * 30);
            BehaviorLog::create([
                'user_id' => $user->id,
                'suggestion_type' => 'reduce_expense',
                'accepted' => true,
                'action_taken' => true,
                'logged_at' => $loggedAt,
            ]);
        }
        UserBehaviorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'execution_consistency_score' => 85,
                'execution_consistency_score_reduce_expense' => 90,
                'execution_consistency_score_income' => null,
                'execution_consistency_score_debt' => null,
            ]
        );
    }

    /** Mẫu 3: 12 tháng chi phẳng hoặc tăng; 5 feedback “Không khả thi” (giảm chi) + BehaviorLog accepted=false. */
    private function seedMau3ComplianceThap(): void
    {
        $email = 'brain3@demo.local';
        $user = $this->firstOrCreateUser($email, self::ACCOUNTS[$email]['name']);
        $pay2s = $this->ensureDemoBankAccount($user, self::ACCOUNTS[$email]['account']);
        $this->clearUserData($user);

        $baseDate = Carbon::now()->subMonths(12)->startOfMonth();
        $seq = 0;
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = 25_000_000;
            $expense = 24_000_000 + ($m * 100_000);
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $income, $monthStart->copy()->addDays(5), "b3-m{$m}-in-" . (++$seq), 'salary');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', (int) ($expense * 0.55), $monthStart->copy()->addDays(10), "b3-m{$m}-out1-" . (++$seq), 'expense');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', $expense - (int) ($expense * 0.55), $monthStart->copy()->addDays(20), "b3-m{$m}-out2-" . (++$seq), 'expense');
        }
        $hash = str_repeat('a', 64);
        for ($i = 0; $i < 5; $i++) {
            FinancialInsightFeedback::create([
                'user_id' => $user->id,
                'insight_hash' => $hash,
                'feedback_type' => FinancialInsightFeedback::TYPE_INFEASIBLE,
                'reason_code' => FinancialInsightFeedback::REASON_CANNOT_REDUCE_EXPENSE,
                'root_cause' => null,
                'suggested_action_type' => null,
            ]);
            BehaviorLog::create([
                'user_id' => $user->id,
                'suggestion_type' => 'reduce_expense',
                'accepted' => false,
                'action_taken' => null,
                'logged_at' => Carbon::now()->subDays(20 + $i * 15),
            ]);
        }
        UserBehaviorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'execution_consistency_score' => 25,
                'execution_consistency_score_reduce_expense' => 20,
                'execution_consistency_score_income' => null,
                'execution_consistency_score_debt' => null,
            ]
        );
    }

    /** Mẫu 4: 75% thu từ 1 merchant; 3 nợ lớn; 2 tháng gần đây “stress”; compliance thấp. */
    private function seedMau4DebtHeavy(): void
    {
        $email = 'brain4@demo.local';
        $user = $this->firstOrCreateUser($email, self::ACCOUNTS[$email]['name']);
        $pay2s = $this->ensureDemoBankAccount($user, self::ACCOUNTS[$email]['account']);
        $this->clearUserData($user);

        $baseDate = Carbon::now()->subMonths(12)->startOfMonth();
        $seq = 0;
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = 40_000_000;
            $mainClient = (int) ($income * 0.75);
            $other = $income - $mainClient;
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $mainClient, $monthStart->copy()->addDays(5), "b4-m{$m}-client1-" . (++$seq), 'khach_lon');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'IN', $other, $monthStart->copy()->addDays(15), "b4-m{$m}-other-" . (++$seq), 'other_income');
            $expense = 35_000_000;
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', (int) ($expense * 0.5), $monthStart->copy()->addDays(8), "b4-m{$m}-out1-" . (++$seq), 'expense');
            $this->insertTx($user->id, $pay2s?->id, self::ACCOUNTS[$email]['account'], 'OUT', $expense - (int) ($expense * 0.5), $monthStart->copy()->addDays(22), "b4-m{$m}-out2-" . (++$seq), 'expense');
        }
        $today = Carbon::today();
        $liabilities = [
            ['name' => 'Nợ vay kinh doanh', 'principal' => 200_000_000, 'rate' => 18, 'due_months' => 6],
            ['name' => 'Nợ thiết bị', 'principal' => 120_000_000, 'rate' => 14, 'due_months' => 12],
            ['name' => 'Nợ ngắn hạn NCC', 'principal' => 80_000_000, 'rate' => 0, 'due_days' => 45],
        ];
        foreach ($liabilities as $row) {
            $due = isset($row['due_days']) ? $today->copy()->addDays($row['due_days']) : $today->copy()->addMonths($row['due_months']);
            $l = UserLiability::create([
                'user_id' => $user->id,
                'direction' => UserLiability::DIRECTION_PAYABLE,
                'name' => $row['name'],
                'principal' => $row['principal'],
                'interest_rate' => $row['rate'],
                'interest_unit' => 'yearly',
                'interest_calculation' => UserLiability::INTEREST_CALCULATION_SIMPLE,
                'accrual_frequency' => UserLiability::ACCRUAL_FREQUENCY_MONTHLY,
                'start_date' => $today->copy()->subMonths(3),
                'due_date' => $due,
                'auto_accrue' => true,
                'status' => UserLiability::STATUS_ACTIVE,
            ]);
            if ($row['rate'] > 0) {
                $this->seedAccrualsForLiability($l);
            }
        }
        BehaviorLog::create([
            'user_id' => $user->id,
            'suggestion_type' => 'reduce_expense',
            'accepted' => false,
            'action_taken' => false,
            'logged_at' => Carbon::now()->subDays(40),
        ]);
        UserBehaviorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'execution_consistency_score' => 30,
                'execution_consistency_score_reduce_expense' => 25,
                'execution_consistency_score_income' => null,
                'execution_consistency_score_debt' => null,
            ]
        );
    }

    private function firstOrCreateUser(string $email, string $name): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function ensureDemoBankAccount(User $user, string $accountNumber): ?Pay2sBankAccount
    {
        $pay2s = Pay2sBankAccount::firstOrCreate(
            ['account_number' => $accountNumber],
            [
                'external_id' => 'demo-brain-' . $accountNumber,
                'account_holder_name' => $user->name,
                'bank_code' => 'DEMO',
                'bank_name' => 'Ngân hàng mẫu',
                'balance' => 0,
                'last_synced_at' => now(),
            ]
        );
        UserBankAccount::firstOrCreate(
            ['user_id' => $user->id, 'account_number' => $accountNumber],
            ['bank_code' => 'DEMO', 'account_type' => 'checking', 'api_type' => 'demo', 'full_name' => $user->name]
        );
        return $pay2s;
    }

    private function clearUserData(User $user): void
    {
        TransactionHistory::where('user_id', $user->id)->delete();
        BehaviorLog::where('user_id', $user->id)->delete();
        FinancialInsightFeedback::where('user_id', $user->id)->delete();
        $user->userLiabilities()->each(function (UserLiability $l) {
            LiabilityAccrual::where('liability_id', $l->id)->delete();
            $l->delete();
        });
    }

    private function insertTx(int $userId, ?int $pay2sId, string $accountNumber, string $type, int $amount, Carbon $transactionDate, string $externalId, string $merchantGroup): void
    {
        TransactionHistory::create([
            'user_id' => $userId,
            'pay2s_bank_account_id' => $pay2sId,
            'external_id' => 'brain-seed-' . $externalId . '-' . uniqid('', true),
            'account_number' => $pay2sId ? $accountNumber : null,
            'amount' => $type === 'OUT' ? -abs($amount) : abs($amount),
            'amount_bucket' => TransactionHistory::resolveAmountBucket($amount),
            'type' => $type,
            'description' => $merchantGroup,
            'merchant_key' => $merchantGroup,
            'merchant_group' => $merchantGroup,
            'classification_source' => 'seed',
            'system_category_id' => null,
            'user_category_id' => null,
            'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
            'classification_confidence' => 0.95,
            'classification_version' => 1,
            'transaction_date' => $transactionDate,
            'raw_json' => null,
        ]);
    }

    private function seedAccrualsForLiability(UserLiability $liability): void
    {
        $rate = (float) $liability->interest_rate;
        if ($rate <= 0) {
            return;
        }
        $principal = (float) $liability->principal;
        $monthlyRate = $liability->interest_unit === 'yearly' ? $rate / 100 / 12 : $rate / 100;
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
