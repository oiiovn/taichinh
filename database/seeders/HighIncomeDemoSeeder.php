<?php

namespace Database\Seeders;

use App\Models\Pay2sBankAccount;
use App\Models\SystemCategory;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBankAccount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản mẫu: thu nhập cao, hay đầu tư số hoá (chứng khoán, crypto, SaaS).
 * Dùng để test tone growth, semantic layer (investment, platform), runway cao.
 *
 * Đăng nhập: highincome@demo.local / password
 */
class HighIncomeDemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'highincome@demo.local';
    public const DEMO_ACCOUNT = 'DEMO_HIGH_001';

    public function run(): void
    {
        $user = $this->firstOrCreateUser();
        $pay2s = $this->ensureDemoBankAccount($user);
        TransactionHistory::where('user_id', $user->id)->delete();

        $baseDate = Carbon::now()->subMonths(5)->startOfMonth();
        $this->seedTransactions($user->id, $pay2s?->id, $baseDate);
    }

    private function firstOrCreateUser(): User
    {
        $user = User::where('email', self::DEMO_EMAIL)->first();
        if ($user) {
            return $user;
        }
        return User::create([
            'name' => 'Mẫu – Thu cao đầu tư số hoá',
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
                'external_id' => 'demo-high-' . self::DEMO_ACCOUNT,
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

    /** 6 tháng: thu 80–120 tr (lương + đầu tư), chi có subscription, đầu tư, sinh hoạt. */
    private function seedTransactions(int $userId, ?int $pay2sAccountId, Carbon $baseDate): void
    {
        $catIncome = SystemCategory::where('type', 'income')->first()?->id;
        $catInvest = SystemCategory::where('type', 'expense')->where('name', 'Đầu tư')->first()?->id;
        $seq = 0;
        $incomePerMonth = [1 => 85_000_000, 2 => 92_000_000, 3 => 88_000_000, 4 => 105_000_000, 5 => 98_000_000, 6 => 115_000_000];
        $expensePerMonth = [1 => 28_000_000, 2 => 26_000_000, 3 => 32_000_000, 4 => 29_000_000, 5 => 30_000_000, 6 => 27_000_000];
        $incomeDescs = ['Lương tháng', 'Cổ tức cổ phiếu', 'Lương + Thưởng dự án', 'Bán chứng khoán', 'Lương + Lãi tiết kiệm', 'Lương + Thu nhập SaaS'];
        $outDescs = ['Subscription Notion GitHub', 'Chuyển ví Binance', 'Mua cổ phiếu VNM', 'Netflix Spotify ChatGPT', 'Nạp tiền đầu tư SSI', 'Mua USDT'];
        for ($m = 1; $m <= 6; $m++) {
            $monthStart = $baseDate->copy()->addMonths($m - 1)->startOfMonth();
            $income = $incomePerMonth[$m];
            $expense = $expensePerMonth[$m];
            $this->insertTx($userId, $pay2sAccountId, 'IN', $income, $monthStart->copy()->addDays(2), "demo-high-m{$m}-in-" . (++$seq), $catIncome, $incomeDescs[$m - 1] ?? 'Thu nhập');
            $this->insertTx($userId, $pay2sAccountId, 'OUT', (int) floor($expense * 0.5), $monthStart->copy()->addDays(10), "demo-high-m{$m}-out1-" . (++$seq), $catInvest, $outDescs[$m - 1] ?? 'Đầu tư');
            $this->insertTx($userId, $pay2sAccountId, 'OUT', $expense - (int) floor($expense * 0.5), $monthStart->copy()->addDays(20), "demo-high-m{$m}-out2-" . (++$seq), null, 'Sinh hoạt chi tiêu');
        }
    }

    private function insertTx(int $userId, ?int $pay2sAccountId, string $type, int $amount, Carbon $transactionDate, string $externalId, ?int $systemCategoryId = null, string $description = ''): void
    {
        $desc = $description ?: ($type === 'IN' ? 'Thu nhập (mẫu demo)' : 'Chi tiêu (mẫu demo)');
        $group = $type === 'IN' ? 'salary_investment' : 'digital_investment';
        TransactionHistory::create([
            'user_id' => $userId,
            'pay2s_bank_account_id' => $pay2sAccountId,
            'external_id' => 'demo-high-' . $externalId . '-' . uniqid('', true),
            'account_number' => $pay2sAccountId ? self::DEMO_ACCOUNT : null,
            'amount' => $type === 'OUT' ? -abs($amount) : abs($amount),
            'amount_bucket' => TransactionHistory::resolveAmountBucket($amount),
            'type' => $type,
            'description' => $desc,
            'merchant_key' => $group,
            'merchant_group' => $group,
            'classification_source' => 'seed',
            'system_category_id' => $systemCategoryId,
            'user_category_id' => null,
            'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
            'classification_confidence' => 1.0,
            'classification_version' => 1,
            'transaction_date' => $transactionDate,
            'raw_json' => null,
        ]);
    }
}
