<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodReportDebt;
use App\Models\FoodReportDebtPayment;
use App\Models\FoodSalesReport;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Http\RedirectResponse;
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
            $debts = $debts->get()->sortByDesc(fn ($d) => $d->report?->report_date)->values();

            $this->matchPayments($debts, $user);

            $debts = FoodReportDebt::query()
                ->with(['report', 'payment.transaction'])
                ->whereIn('id', $debts->pluck('id'))
                ->get()
                ->sortByDesc(fn ($d) => $d->report?->report_date)
                ->values();
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

    public function storeThanhToanTienMat(Request $request, FoodReportDebt $debt): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $report = $debt->report;
        if (! $report) {
            abort(404);
        }
        $isReportOwner = (int) $report->user_id === (int) $user->id;
        $isDebtor = (int) $debt->debtor_user_id === (int) $user->id;
        if (! $isReportOwner && ! $isDebtor) {
            abort(403);
        }
        if ($debt->payment) {
            return redirect()->route('food.cong-no', ['debtor_user_id' => $debt->debtor_user_id])->with('success', 'Công nợ này đã được thanh toán.');
        }
        $amount = (int) round((float) $debt->debt_amount);
        $adminUser = User::where('email', 'admin@gmail.com')->first() ?? $report->user;
        $foodCategory = $this->resolveFoodExpenseCategory($adminUser);
        $tx = null;
        if ($adminUser && $foodCategory) {
            $tx = TransactionHistory::create([
                'external_id' => 'TIEN_MAT_' . $debt->id . '_' . now()->format('YmdHis'),
                'user_id' => $adminUser->id,
                'pay2s_bank_account_id' => null,
                'account_number' => TransactionHistory::ACCOUNT_TIEN_MAT,
                'type' => 'OUT',
                'amount' => $amount,
                'description' => 'Thanh toán tiền mặt - ' . ($report->report_code ?? ''),
                'transaction_date' => now(),
                'user_category_id' => $foodCategory->id,
                'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_USER_CONFIRMED,
            ]);
        }
        FoodReportDebtPayment::query()->create([
            'food_report_debt_id' => $debt->id,
            'transaction_history_id' => $tx?->id,
            'amount_paid' => $amount,
        ]);
        return redirect()->route('food.cong-no', ['debtor_user_id' => $debt->debtor_user_id])->with('success', 'Đã ghi nhận thanh toán tiền mặt.');
    }

    /**
     * Tìm danh mục chi Food/Ăn uống của user để gán cho giao dịch tiền mặt.
     */
    private function resolveFoodExpenseCategory(User $user): ?UserCategory
    {
        $q = UserCategory::where('user_id', $user->id)->where('type', 'expense');
        $foodCategory = (clone $q)->where('name', 'Food')->first()
            ?? (clone $q)->where('name', 'Ăn uống')->first()
            ?? (clone $q)->whereRaw('LOWER(TRIM(name)) = ?', ['food'])->first()
            ?? (clone $q)->whereRaw('LOWER(TRIM(name)) = ?', ['ăn uống'])->first()
            ?? (clone $q)->whereRaw('LOWER(name) LIKE ?', ['%food%'])->orderBy('name')->first()
            ?? (clone $q)->whereRaw('LOWER(name) LIKE ?', ['%ăn uống%'])->orderBy('name')->first();
        if ($foodCategory) {
            return $foodCategory;
        }
        return $q->orderBy('name')->first();
    }

    private function matchPayments($debts, $user): void
    {
        $usedTxIds = FoodReportDebtPayment::query()->whereNotNull('transaction_history_id')->pluck('transaction_history_id')->all();

        foreach ($debts as $debt) {
            if ($debt->payment) {
                continue;
            }
            $report = $debt->report;
            $debtAmount = (int) round((float) $debt->debt_amount);
            $code = $report->report_code;

            $tx = TransactionHistory::query()
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
