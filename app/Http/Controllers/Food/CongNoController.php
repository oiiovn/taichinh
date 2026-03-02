<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodReportDebt;
use App\Models\FoodReportDebtPayment;
use App\Models\FoodSalesReport;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CongNoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAdmin = $user->is_admin;
        $debtors = collect();
        $debtorUserId = 0;
        $debtor = null;

        if ($isAdmin) {
            $debtorUserIds = FoodReportDebt::query()
                ->whereHas('report', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('debtor_user_id')
                ->unique()
                ->values();
            $debtors = User::query()->whereIn('id', $debtorUserIds)->orderBy('name')->get();
            $debtorUserId = (int) $request->input('debtor_user_id');
            if ($debtorUserId && ! $debtors->contains('id', $debtorUserId)) {
                $debtorUserId = $debtors->first()?->id ?? 0;
            }
            if (! $debtorUserId && $debtors->isNotEmpty()) {
                $debtorUserId = $debtors->first()->id;
            }
            $debtor = $debtorUserId ? User::find($debtorUserId) : null;
        } else {
            $debtorUserId = $user->id;
            $debtor = $user;
        }

        $debts = collect();
        $paymentHistory = collect();
        $totalQuyetToan = 0;
        $totalDaThanhToan = 0;

        if ($debtorUserId) {
            $debts = FoodReportDebt::query()
                ->with(['report', 'payment.transaction'])
                ->where('debtor_user_id', $debtorUserId);
            if ($isAdmin) {
                $debts = $debts->whereHas('report', fn ($q) => $q->where('user_id', $user->id));
            }
            $debts = $debts->get();

            $this->matchPayments($debts);

            $debts = FoodReportDebt::query()
                ->with(['report', 'payment.transaction'])
                ->whereIn('id', $debts->pluck('id'))
                ->get();
            foreach ($debts as $d) {
                $totalQuyetToan += (float) $d->debt_amount;
                if ($d->payment) {
                    $totalDaThanhToan += (float) $d->payment->amount_paid;
                }
            }

            $paymentHistory = FoodReportDebtPayment::query()
                ->with(['debt.report', 'transaction'])
                ->whereHas('debt', fn ($q) => $q->where('debtor_user_id', $debtorUserId));
            if ($isAdmin) {
                $paymentHistory = $paymentHistory->whereHas('debt.report', fn ($q2) => $q2->where('user_id', $user->id));
            }
            $paymentHistory = $paymentHistory->orderByDesc('created_at')->get();
        }

        $conLai = $totalQuyetToan - $totalDaThanhToan;
        $trangThai = $debts->isEmpty() ? null : ($conLai <= 0 ? 'Đã thanh toán' : 'Còn nợ');

        return view('pages.food.cong-no', [
            'title' => 'Công nợ',
            'debtors' => $debtors,
            'debtor' => $debtor,
            'debtorUserId' => $debtorUserId,
            'debts' => $debts,
            'paymentHistory' => $paymentHistory,
            'totalReports' => $debts->count(),
            'totalQuyetToan' => $totalQuyetToan,
            'totalDaThanhToan' => $totalDaThanhToan,
            'conLai' => $conLai,
            'trangThai' => $trangThai,
            'canSelectDebtor' => $isAdmin,
        ]);
    }

    private function matchPayments($debts): void
    {
        $usedTxIds = FoodReportDebtPayment::query()->pluck('transaction_history_id')->all();

        foreach ($debts as $debt) {
            if ($debt->payment) {
                continue;
            }
            $report = $debt->report;
            $debtAmount = (int) round((float) $debt->debt_amount);
            $code = $report->report_code;

            $tx = TransactionHistory::query()
                ->where('user_id', $debt->debtor_user_id)
                ->where('type', 'OUT')
                ->whereNotIn('id', $usedTxIds)
                ->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($code).'%'])
                ->get()
                ->first(fn ($t) => (int) round(abs((float) $t->amount)) === $debtAmount);

            if ($tx) {
                FoodReportDebtPayment::query()->create([
                    'food_report_debt_id' => $debt->id,
                    'transaction_history_id' => $tx->id,
                    'amount_paid' => $debtAmount,
                ]);
                $usedTxIds[] = $tx->id;
            }
        }
    }
}
