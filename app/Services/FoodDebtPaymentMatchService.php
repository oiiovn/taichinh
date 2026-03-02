<?php

namespace App\Services;

use App\Models\FoodReportDebt;
use App\Models\FoodReportDebtPayment;
use App\Models\TransactionHistory;

class FoodDebtPaymentMatchService
{
    /**
     * Khớp giao dịch OUT với công nợ (báo cáo Food): nếu description chứa mã báo cáo và số tiền đúng thì tạo thanh toán.
     * Gọi sau khi Pay2s lưu giao dịch mới.
     */
    public function tryMatch(TransactionHistory $tx): ?FoodReportDebtPayment
    {
        if ($tx->type !== 'OUT') {
            return null;
        }

        $txAmount = (int) round(abs((float) $tx->amount));
        $description = mb_strtolower((string) ($tx->description ?? ''));

        if ($description === '') {
            return null;
        }

        if (FoodReportDebtPayment::where('transaction_history_id', $tx->id)->exists()) {
            return null;
        }

        $debts = FoodReportDebt::query()
            ->with('report')
            ->whereDoesntHave('payment')
            ->get();

        foreach ($debts as $debt) {
            $code = $debt->report->report_code ?? '';
            if ($code === '') {
                continue;
            }
            if (mb_strpos($description, mb_strtolower($code)) === false) {
                continue;
            }
            $debtAmount = (int) round((float) $debt->debt_amount);
            if ($txAmount !== $debtAmount) {
                continue;
            }

            return FoodReportDebtPayment::query()->create([
                'food_report_debt_id' => $debt->id,
                'transaction_history_id' => $tx->id,
                'amount_paid' => $debtAmount,
            ]);
        }

        return null;
    }
}
