<?php

namespace App\Services;

use App\Models\FinancialStateSnapshot;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBrainParam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * So sánh actual vs projected sau ~30 ngày; cập nhật forecast_error trên snapshot.
 * forecast_error = |actual - projected| / projected (theo thu hoặc chi).
 */
class ForecastLearningService
{
    private const DAYS_LOOKBACK = 35;

    private const DAYS_TARGET = 30;

    public function runForAllUsers(): void
    {
        $cutoff = Carbon::today()->subDays(self::DAYS_LOOKBACK);
        $snapshots = FinancialStateSnapshot::where('snapshot_date', '<=', $cutoff)
            ->whereNull('forecast_error')
            ->whereNotNull('projected_income_monthly')
            ->whereNotNull('projected_expense_monthly')
            ->orderBy('snapshot_date')
            ->get();

        foreach ($snapshots as $snapshot) {
            $this->updateForecastError($snapshot);
        }
    }

    public function updateForecastError(FinancialStateSnapshot $snapshot): void
    {
        $start = Carbon::parse($snapshot->snapshot_date)->addDay();
        $end = $start->copy()->addDays(self::DAYS_TARGET);
        $linkedAccountNumbers = $this->getLinkedAccountNumbersForUser($snapshot->user_id);
        $actual = $this->actualIncomeExpenseForPeriod($snapshot->user_id, $start, $end, $linkedAccountNumbers);
        $projIncome = (float) $snapshot->projected_income_monthly;
        $projExpense = (float) $snapshot->projected_expense_monthly;

        $errIncome = $projIncome > 0 ? abs($actual['in'] - $projIncome) / $projIncome : 0.0;
        $errExpense = $projExpense > 0 ? abs($actual['out'] - $projExpense) / $projExpense : 0.0;
        $forecastError = ($errIncome + $errExpense) / 2.0;

        $snapshot->update(['forecast_error' => round(min(1.0, $forecastError), 4)]);

        $this->upsertConservativeBias($snapshot->user_id, $forecastError);
    }

    /** Learning Loop: forecast_error cao → tăng conservative_bias cho DecisionCore (surplus_retention). */
    private function upsertConservativeBias(int $userId, float $forecastError): void
    {
        try {
            $value = $forecastError > 0.25 ? round(min(0.8, 0.15 + $forecastError * 0.5), 4) : 0;
            UserBrainParam::updateOrCreate(
                ['user_id' => $userId, 'param_key' => 'conservative_bias'],
                ['param_value' => $value]
            );
        } catch (\Throwable) {
            // Bảng user_brain_params có thể chưa có
        }
    }

    /**
     * @param  array<string>  $linkedAccountNumbers  Chỉ giao dịch tài khoản đang liên kết. Rỗng = toàn bộ (backward compat).
     * @return array{in: float, out: float}
     */
    private function actualIncomeExpenseForPeriod(int $userId, Carbon $start, Carbon $end, array $linkedAccountNumbers = []): array
    {
        $query = TransactionHistory::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end]);
        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }
        $rows = $query->selectRaw('type, COALESCE(SUM(ABS(amount)), 0) as total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');
        return [
            'in' => (float) ($rows->get('IN')->total ?? 0),
            'out' => (float) ($rows->get('OUT')->total ?? 0),
        ];
    }

    /** @return array<string> */
    private function getLinkedAccountNumbersForUser(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        return $user->userBankAccounts()
            ->pluck('account_number')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
