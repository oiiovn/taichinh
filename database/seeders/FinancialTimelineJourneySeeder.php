<?php

namespace Database\Seeders;

use App\Models\FinancialStateSnapshot;
use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed 1 tài khoản có 6 tháng dữ liệu + timeline hành trình từ cấp thấp nhất (crisis) đến cao nhất (stable_growth).
 * Chạy: php artisan db:seed --class=FinancialTimelineJourneySeeder
 */
class FinancialTimelineJourneySeeder extends Seeder
{
    private const USER_EMAIL = 'timeline-6m@fi-test.local';

    public function run(): void
    {
        $user = $this->firstOrCreateUser();
        $this->seedSixMonthsTransactions($user->id);
        $this->seedTimelineSnapshots($user->id);
    }

    private function firstOrCreateUser(): User
    {
        $user = User::where('email', self::USER_EMAIL)->first();
        if ($user) {
            TransactionHistory::where('user_id', $user->id)->delete();
            FinancialStateSnapshot::where('user_id', $user->id)->delete();
            return $user;
        }
        return User::create([
            'name' => 'Timeline 6 tháng – Hành trình tài chính',
            'email' => self::USER_EMAIL,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 6 tháng giao dịch: thu tăng dần, chi ổn → months_with_data = 6 (Data Maturity mature).
     */
    private function seedSixMonthsTransactions(int $userId): void
    {
        $base = Carbon::now()->subMonths(5)->startOfMonth();
        $incomeByMonth = [15_000_000, 18_000_000, 21_000_000, 24_000_000, 26_000_000, 28_000_000];
        $expenseByMonth = [18_000_000, 18_000_000, 18_500_000, 18_000_000, 18_000_000, 18_000_000];
        $seq = 0;

        for ($m = 0; $m < 6; $m++) {
            $monthStart = $base->copy()->addMonths($m)->startOfMonth();
            $income = $incomeByMonth[$m];
            $expense = $expenseByMonth[$m];

            TransactionHistory::create([
                'user_id' => $userId,
                'pay2s_bank_account_id' => null,
                'external_id' => 'timeline-seed-' . $userId . '-m' . ($m + 1) . '-in-' . (++$seq) . '-' . uniqid('', true),
                'account_number' => null,
                'amount' => $income,
                'amount_bucket' => TransactionHistory::resolveAmountBucket($income),
                'type' => 'IN',
                'description' => 'Thu nhập (Timeline seed)',
                'merchant_key' => 'timeline_seed',
                'merchant_group' => 'timeline_seed',
                'classification_source' => 'seed',
                'system_category_id' => null,
                'user_category_id' => null,
                'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
                'classification_confidence' => 1.0,
                'classification_version' => 1,
                'transaction_date' => $monthStart->copy()->addDays(2),
                'raw_json' => null,
            ]);

            $part1 = (int) floor($expense * 0.6);
            $part2 = $expense - $part1;
            TransactionHistory::create([
                'user_id' => $userId,
                'pay2s_bank_account_id' => null,
                'external_id' => 'timeline-seed-' . $userId . '-m' . ($m + 1) . '-out1-' . (++$seq) . '-' . uniqid('', true),
                'account_number' => null,
                'amount' => -$part1,
                'amount_bucket' => TransactionHistory::resolveAmountBucket($part1),
                'type' => 'OUT',
                'description' => 'Chi tiêu (Timeline seed)',
                'merchant_key' => 'timeline_seed',
                'merchant_group' => 'timeline_seed',
                'classification_source' => 'seed',
                'system_category_id' => null,
                'user_category_id' => null,
                'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
                'classification_confidence' => 1.0,
                'classification_version' => 1,
                'transaction_date' => $monthStart->copy()->addDays(10),
                'raw_json' => null,
            ]);
            TransactionHistory::create([
                'user_id' => $userId,
                'pay2s_bank_account_id' => null,
                'external_id' => 'timeline-seed-' . $userId . '-m' . ($m + 1) . '-out2-' . (++$seq) . '-' . uniqid('', true),
                'account_number' => null,
                'amount' => -$part2,
                'amount_bucket' => TransactionHistory::resolveAmountBucket($part2),
                'type' => 'OUT',
                'description' => 'Chi tiêu (Timeline seed)',
                'merchant_key' => 'timeline_seed',
                'merchant_group' => 'timeline_seed',
                'classification_source' => 'seed',
                'system_category_id' => null,
                'user_category_id' => null,
                'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_AUTO,
                'classification_confidence' => 1.0,
                'classification_version' => 1,
                'transaction_date' => $monthStart->copy()->addDays(20),
                'raw_json' => null,
            ]);
        }
    }

    /**
     * 6 snapshot: từ crisis → fragile → accumulation → stable_growth (cấp thấp → cao).
     */
    private function seedTimelineSnapshots(int $userId): void
    {
        $baseDate = Carbon::now()->subMonths(5)->startOfMonth();

        $journey = [
            [
                'brain_mode_key' => 'crisis_directive',
                'structural_state' => ['key' => 'debt_spiral_risk', 'label' => 'Rủi ro xoáy nợ', 'description' => 'Trả nợ chiếm trên 40% thu.'],
                'objective' => ['key' => 'survival', 'label' => 'Sinh tồn', 'description' => 'Ưu tiên tiền mặt và tạo dòng tiền.'],
                'buffer_months' => 0,
                'recommended_buffer' => 4,
                'dsi' => 88,
                'debt_exposure' => 180_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -180_000_000,
                'income_volatility' => 0.25,
                'spending_discipline_score' => 0.35,
                'execution_consistency_score' => 25,
                'liquidity_status' => 'positive',
            ],
            [
                'brain_mode_key' => 'fragile_coaching',
                'structural_state' => ['key' => 'fragile_liquidity', 'label' => 'Thanh khoản mỏng', 'description' => 'Có thu nhưng buffer thấp.'],
                'objective' => ['key' => 'safety', 'label' => 'Giữ an toàn', 'description' => 'Ưu tiên bảo vệ buffer và thanh khoản.'],
                'buffer_months' => 1,
                'recommended_buffer' => 4,
                'dsi' => 72,
                'debt_exposure' => 150_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -150_000_000,
                'income_volatility' => 0.18,
                'spending_discipline_score' => 0.48,
                'execution_consistency_score' => 42,
                'liquidity_status' => 'positive',
            ],
            [
                'brain_mode_key' => 'fragile_coaching',
                'structural_state' => ['key' => 'fragile_liquidity', 'label' => 'Thanh khoản mỏng', 'description' => 'Có thu nhưng buffer thấp.'],
                'objective' => ['key' => 'safety', 'label' => 'Giữ an toàn', 'description' => 'Ưu tiên bảo vệ buffer và thanh khoản.'],
                'buffer_months' => 2,
                'recommended_buffer' => 4,
                'dsi' => 58,
                'debt_exposure' => 120_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -120_000_000,
                'income_volatility' => 0.14,
                'spending_discipline_score' => 0.55,
                'execution_consistency_score' => 52,
                'liquidity_status' => 'positive',
            ],
            [
                'brain_mode_key' => 'fragile_coaching',
                'structural_state' => ['key' => 'accumulation_phase', 'label' => 'Pha tích lũy', 'description' => 'Thu vượt chi, nợ trong tầm kiểm soát.'],
                'objective' => ['key' => 'debt_repayment', 'label' => 'Trả nợ', 'description' => 'Ưu tiên ổn định nợ và thanh khoản.'],
                'buffer_months' => 3,
                'recommended_buffer' => 4,
                'dsi' => 42,
                'debt_exposure' => 90_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -90_000_000,
                'income_volatility' => 0.12,
                'spending_discipline_score' => 0.62,
                'execution_consistency_score' => 58,
                'liquidity_status' => 'positive',
            ],
            [
                'brain_mode_key' => 'disciplined_accelerator',
                'structural_state' => ['key' => 'accumulation_phase', 'label' => 'Pha tích lũy', 'description' => 'Thu vượt chi, nợ trong tầm kiểm soát.'],
                'objective' => ['key' => 'accumulation', 'label' => 'Tích lũy', 'description' => 'Ưu tiên tối ưu nợ và tích lũy.'],
                'buffer_months' => 5,
                'recommended_buffer' => 4,
                'dsi' => 28,
                'debt_exposure' => 50_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -50_000_000,
                'income_volatility' => 0.09,
                'spending_discipline_score' => 0.72,
                'execution_consistency_score' => 68,
                'liquidity_status' => 'positive',
            ],
            [
                'brain_mode_key' => 'stable_growth',
                'structural_state' => ['key' => 'stable_conservative', 'label' => 'Ổn định bảo thủ', 'description' => 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư.'],
                'objective' => ['key' => 'investment', 'label' => 'Đầu tư', 'description' => 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư.'],
                'buffer_months' => 6,
                'recommended_buffer' => 4,
                'dsi' => 18,
                'debt_exposure' => 20_000_000,
                'receivable_exposure' => 0,
                'net_leverage' => -20_000_000,
                'income_volatility' => 0.06,
                'spending_discipline_score' => 0.82,
                'execution_consistency_score' => 78,
                'liquidity_status' => 'positive',
            ],
        ];

        foreach ($journey as $i => $row) {
            $snapshotDate = $baseDate->copy()->addMonths($i)->addDay(); // 1st of month + 1 day to avoid edge
            FinancialStateSnapshot::create([
                'user_id' => $userId,
                'snapshot_date' => $snapshotDate,
                'structural_state' => $row['structural_state'],
                'buffer_months' => $row['buffer_months'],
                'recommended_buffer' => $row['recommended_buffer'],
                'liquidity_status' => $row['liquidity_status'],
                'dsi' => $row['dsi'],
                'debt_exposure' => $row['debt_exposure'],
                'receivable_exposure' => $row['receivable_exposure'],
                'net_leverage' => $row['net_leverage'],
                'income_volatility' => $row['income_volatility'],
                'spending_discipline_score' => $row['spending_discipline_score'],
                'execution_consistency_score' => $row['execution_consistency_score'],
                'objective' => $row['objective'],
                'priority_alignment' => ['aligned' => true],
                'narrative_hash' => 'timeline-seed-' . $userId . '-m' . ($i + 1),
                'brain_mode_key' => $row['brain_mode_key'],
                'decision_bundle_snapshot' => [
                    'required_runway_months' => $row['recommended_buffer'],
                    'surplus_retention_pct' => $i >= 4 ? 30 : 50,
                ],
                'total_feedback_count' => 0,
                'projected_income_monthly' => 15_000_000 + $i * 2_500_000,
                'projected_expense_monthly' => 18_000_000,
            ]);
        }
    }
}
