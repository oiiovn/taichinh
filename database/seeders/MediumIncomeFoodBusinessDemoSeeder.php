<?php

namespace Database\Seeders;

use App\Models\LiabilityAccrual;
use App\Models\Pay2sBankAccount;
use App\Models\SystemCategory;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserLiability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản mẫu: thu nhập trung bình, nợ nhiều, nhiều mục kinh doanh food (Grab, Now, F&B).
 * Dùng để test semantic layer (platform_fnb, merchant_group), DSI, behavioral.
 *
 * Đăng nhập: mediumincome-food@demo.local / password
 */
class MediumIncomeFoodBusinessDemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'mediumincome-food@demo.local';
    public const DEMO_ACCOUNT = 'DEMO_FOOD_002';

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
            'name' => 'Mẫu – Thu trung bình kinh doanh food',
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
                'external_id' => 'demo-food-' . self::DEMO_ACCOUNT,
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

    /** 6 tháng: thu 25–35 tr (đa phần từ platform food), chi nhiều giao dịch Grab/Now/nguyên liệu. */
    private function seedTransactions(int $userId, ?int $pay2sAccountId, Carbon $baseDate): void
    {
        $catBusiness = SystemCategory::where('type', 'income')->where('name', 'Kinh doanh')->first()?->id;
        $catFood = SystemCategory::where('type', 'expense')->where('name', 'Ăn uống')->first()?->id;
        $incomePerMonth = [1 => 26_000_000, 2 => 28_000_000, 3 => 25_500_000, 4 => 32_000_000, 5 => 29_000_000, 6 => 31_000_000];
        $seq = 0;
        for ($m = 1; $m <= 6; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = $incomePerMonth[$m];
            $this->insertTx($userId, $pay2sAccountId, 'IN', (int) ($income * 0.6), $monthStart->copy()->addDays(5), "demo-food-m{$m}-grab-" . (++$seq), $catBusiness, 'Grab thanh toán đơn hàng tháng');
            $this->insertTx($userId, $pay2sAccountId, 'IN', (int) ($income * 0.25), $monthStart->copy()->addDays(12), "demo-food-m{$m}-now-" . (++$seq), $catBusiness, 'Now thanh toán đối soát');
            $this->insertTx($userId, $pay2sAccountId, 'IN', $income - (int) ($income * 0.85), $monthStart->copy()->addDays(20), "demo-food-m{$m}-tienquan-" . (++$seq), $catBusiness, 'Thu quầy bán đồ uống');
            $expense = (int) ($income * 0.75);
            $this->insertTx($userId, $pay2sAccountId, 'OUT', (int) ($expense * 0.35), $monthStart->copy()->addDays(3), "demo-food-m{$m}-nl-" . (++$seq), $catFood, 'Nguyên liệu F&B Metro');
            $this->insertTx($userId, $pay2sAccountId, 'OUT', (int) ($expense * 0.2), $monthStart->copy()->addDays(8), "demo-food-m{$m}-grab-fee-" . (++$seq), $catFood, 'Grab commission phí giao hàng');
            $this->insertTx($userId, $pay2sAccountId, 'OUT', (int) ($expense * 0.25), $monthStart->copy()->addDays(15), "demo-food-m{$m}-shopee-" . (++$seq), $catFood, 'Shopee Food đơn hàng nguyên liệu');
            $this->insertTx($userId, $pay2sAccountId, 'OUT', $expense - (int) ($expense * 0.8), $monthStart->copy()->addDays(22), "demo-food-m{$m}-diennuoc-" . (++$seq), null, 'Điện nước thuê mặt bằng');
        }
    }

    private function insertTx(int $userId, ?int $pay2sAccountId, string $type, int $amount, Carbon $transactionDate, string $externalId, ?int $systemCategoryId = null, string $description = ''): void
    {
        $group = 'platform_fnb';
        TransactionHistory::create([
            'user_id' => $userId,
            'pay2s_bank_account_id' => $pay2sAccountId,
            'external_id' => 'demo-food-' . $externalId . '-' . uniqid('', true),
            'account_number' => $pay2sAccountId ? self::DEMO_ACCOUNT : null,
            'amount' => $type === 'OUT' ? -abs($amount) : abs($amount),
            'amount_bucket' => TransactionHistory::resolveAmountBucket($amount),
            'type' => $type,
            'description' => $description,
            'merchant_key' => $group,
            'merchant_group' => $group,
            'classification_source' => 'seed',
            'system_category_id' => $systemCategoryId,
            'user_category_id' => null,
            'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
            'classification_confidence' => 0.9,
            'classification_version' => 1,
            'transaction_date' => $transactionDate,
            'raw_json' => null,
        ]);
    }

    private function seedLiabilities(int $userId): void
    {
        $today = Carbon::today();
        $liabs = [
            [
                'name' => 'Vay mở quán F&B',
                'principal' => 80_000_000,
                'interest_rate' => 15,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addMonths(6),
                'start_date' => $today->copy()->subMonths(4),
            ],
            [
                'name' => 'Trả góp thiết bị bếp',
                'principal' => 45_000_000,
                'interest_rate' => 12,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addMonths(10),
                'start_date' => $today->copy()->subMonths(2),
            ],
            [
                'name' => 'Nợ nhà cung cấp nguyên liệu',
                'principal' => 25_000_000,
                'interest_rate' => 0,
                'interest_unit' => 'yearly',
                'due_date' => $today->copy()->addDays(30),
                'start_date' => $today->copy()->subMonth(),
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
