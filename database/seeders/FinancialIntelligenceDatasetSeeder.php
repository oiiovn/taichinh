<?php

namespace Database\Seeders;

use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FinancialIntelligenceDatasetSeeder extends Seeder
{
    /**
     * Seed 3 dataset mẫu để test trí tuệ tài chính: stable, volatile, high-debt.
     */
    public function run(): void
    {
        $datasets = require database_path('data/financial_intelligence_datasets.php');

        $keys = [
            'dataset_1_stable_salaried',
            'dataset_2_volatile_income',
            'dataset_3_high_debt_tight_cashflow',
        ];

        $baseDate = Carbon::now()->subMonths(5)->startOfMonth();

        foreach ($keys as $index => $key) {
            if (! isset($datasets[$key])) {
                continue;
            }
            $config = $datasets[$key];
            $user = $this->firstOrCreateUser($index + 1, $config['name']);
            $this->seedTransactionsForUser($user->id, $config, $baseDate, $key);
            if ($index === 0) {
                $this->setPlanExpiringSoon($user);
            }
        }
    }

    private function firstOrCreateUser(int $datasetIndex, string $profileName): User
    {
        $email = "dataset{$datasetIndex}@fi-test.local";
        $user = User::where('email', $email)->first();
        if ($user) {
            TransactionHistory::where('user_id', $user->id)->delete();
            return $user;
        }
        return User::create([
            'name' => "FI Test – {$profileName}",
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    /** Đặt gói dataset1 sắp hết hạn (trong 2 ngày) để test nút gia hạn & thông báo Dashboard. */
    private function setPlanExpiringSoon(User $user): void
    {
        $user->update([
            'plan' => 'basic',
            'plan_expires_at' => Carbon::now()->addDays(2)->endOfDay(),
        ]);
    }

    private function seedTransactionsForUser(int $userId, array $config, Carbon $baseDate, string $datasetKey): void
    {
        $months = $config['months'];
        $incomePerMonth = $config['income_per_month'];
        $expensePerMonth = $config['expense_per_month'];
        $debtPayment = $config['debt_payment_per_month'] ?? 0;
        $prefix = str_replace('_', '-', $datasetKey);
        $seq = 0;

        for ($m = 1; $m <= $months; $m++) {
            $date = $baseDate->copy()->addMonths($m - 1);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $income = $incomePerMonth[$m] ?? 0;
            $expense = $expensePerMonth[$m] ?? 0;
            $totalOut = $expense + $debtPayment;

            if ($income > 0) {
                $this->insertTx($userId, 'IN', $income, $monthStart->copy()->addDays(2), "{$prefix}-u{$userId}-m{$m}-in-" . (++$seq));
            }
            if ($totalOut > 0) {
                if ($expense > 0) {
                    $part1 = (int) floor($expense * 0.6);
                    $part2 = $expense - $part1;
                    $this->insertTx($userId, 'OUT', $part1, $monthStart->copy()->addDays(10), "{$prefix}-u{$userId}-m{$m}-out-exp-" . (++$seq));
                    $this->insertTx($userId, 'OUT', $part2, $monthStart->copy()->addDays(18), "{$prefix}-u{$userId}-m{$m}-out-exp2-" . (++$seq));
                }
                if ($debtPayment > 0) {
                    $this->insertTx($userId, 'OUT', $debtPayment, $monthStart->copy()->addDays(15), "{$prefix}-u{$userId}-m{$m}-out-debt-" . (++$seq));
                }
            }
        }
    }

    private function insertTx(int $userId, string $type, int $amount, Carbon $transactionDate, string $externalId): void
    {
        TransactionHistory::create([
            'user_id' => $userId,
            'pay2s_bank_account_id' => null,
            'external_id' => 'fi-seed-' . $externalId . '-' . uniqid('', true),
            'account_number' => null,
            'amount' => $type === 'OUT' ? -abs($amount) : abs($amount),
            'amount_bucket' => TransactionHistory::resolveAmountBucket($amount),
            'type' => $type,
            'description' => $type === 'IN' ? 'Lương / Thu nhập (FI seed)' : 'Chi tiêu / Trả nợ (FI seed)',
            'merchant_key' => 'fi_seed',
            'merchant_group' => 'fi_seed',
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
}
