<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use App\Services\TaiChinh\TaiChinhViewCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PaymentScheduleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('error', 'Vui lòng đăng nhập.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'amount_is_variable' => ['boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'frequency' => ['required', Rule::in(['monthly', 'every_2_months', 'quarterly', 'yearly', 'custom_days'])],
            'interval_value' => ['required', 'integer', 'min:1', 'max:366'],
            'next_due_date' => ['required', 'date'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'reminder_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'grace_window_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'transfer_note_pattern' => ['nullable', 'string', 'max:255'],
            'amount_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'auto_update_amount' => ['boolean'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'ended'])],
            'reliability_tracking' => ['boolean'],
            'overdue_alert' => ['boolean'],
            'auto_advance_on_match' => ['boolean'],
        ];

        $data = $request->validate($rules);

        $keywords = null;
        if (! empty($data['keywords'] ?? '')) {
            $parts = array_map('trim', explode(',', $data['keywords']));
            $keywords = array_values(array_filter($parts));
        }

        $schedule = new PaymentSchedule();
        $schedule->user_id = $user->id;
        $schedule->name = $data['name'];
        $schedule->expected_amount = $data['expected_amount'];
        $schedule->amount_is_variable = $request->boolean('amount_is_variable');
        $schedule->currency = $data['currency'] ?? 'VND';
        $schedule->internal_note = $data['internal_note'] ?? null;
        $schedule->frequency = $data['frequency'];
        $schedule->interval_value = (int) $data['interval_value'];
        $schedule->next_due_date = Carbon::parse($data['next_due_date']);
        $schedule->day_of_month = isset($data['day_of_month']) ? (int) $data['day_of_month'] : null;
        $schedule->reminder_days = isset($data['reminder_days']) && $data['reminder_days'] !== '' ? (int) $data['reminder_days'] : null;
        $schedule->grace_window_days = isset($data['grace_window_days']) ? (int) $data['grace_window_days'] : 7;
        $schedule->keywords = $keywords;
        $schedule->bank_account_number = $data['bank_account_number'] ?? null;
        $schedule->transfer_note_pattern = $data['transfer_note_pattern'] ?? null;
        $schedule->amount_tolerance_pct = isset($data['amount_tolerance_pct']) && $data['amount_tolerance_pct'] !== '' ? $data['amount_tolerance_pct'] : null;
        $schedule->auto_update_amount = $request->boolean('auto_update_amount');
        $schedule->status = $data['status'] ?? 'active';
        $schedule->reliability_tracking = $request->boolean('reliability_tracking', true);
        $schedule->overdue_alert = $request->boolean('overdue_alert', true);
        $schedule->auto_advance_on_match = $request->boolean('auto_advance_on_match', true);
        $schedule->save();
        TaiChinhViewCache::forget($user->id);

        return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('success', 'Đã thêm lịch thanh toán.');
    }

    public function edit(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401);
        }
        $schedule = PaymentSchedule::where('user_id', $user->id)->where('id', $id)->first();
        if (! $schedule) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy lịch thanh toán.'], 404);
        }
        $keywordsStr = $schedule->keywords && is_array($schedule->keywords) ? implode(', ', $schedule->keywords) : '';
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'expected_amount' => (float) $schedule->expected_amount,
                'amount_is_variable' => (bool) $schedule->amount_is_variable,
                'currency' => $schedule->currency ?? 'VND',
                'internal_note' => $schedule->internal_note ?? '',
                'frequency' => $schedule->frequency ?? 'monthly',
                'interval_value' => (int) $schedule->interval_value,
                'next_due_date' => $schedule->next_due_date?->format('Y-m-d'),
                'day_of_month' => $schedule->day_of_month,
                'reminder_days' => $schedule->reminder_days,
                'grace_window_days' => (int) ($schedule->grace_window_days ?? 7),
                'keywords' => $keywordsStr,
                'bank_account_number' => $schedule->bank_account_number ?? '',
                'transfer_note_pattern' => $schedule->transfer_note_pattern ?? '',
                'amount_tolerance_pct' => $schedule->amount_tolerance_pct,
                'auto_update_amount' => (bool) $schedule->auto_update_amount,
                'status' => $schedule->status ?? 'active',
                'reliability_tracking' => (bool) $schedule->reliability_tracking,
                'overdue_alert' => (bool) $schedule->overdue_alert,
                'auto_advance_on_match' => (bool) $schedule->auto_advance_on_match,
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('error', 'Vui lòng đăng nhập.');
        }
        $schedule = PaymentSchedule::where('user_id', $user->id)->where('id', $id)->first();
        if (! $schedule) {
            return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('error', 'Không tìm thấy lịch thanh toán.');
        }
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'amount_is_variable' => ['boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'frequency' => ['required', Rule::in(['monthly', 'every_2_months', 'quarterly', 'yearly', 'custom_days'])],
            'interval_value' => ['required', 'integer', 'min:1', 'max:366'],
            'next_due_date' => ['required', 'date'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'reminder_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'grace_window_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'transfer_note_pattern' => ['nullable', 'string', 'max:255'],
            'amount_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'auto_update_amount' => ['boolean'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'ended'])],
            'reliability_tracking' => ['boolean'],
            'overdue_alert' => ['boolean'],
            'auto_advance_on_match' => ['boolean'],
        ];
        $data = $request->validate($rules);
        $keywords = null;
        if (! empty($data['keywords'] ?? '')) {
            $parts = array_map('trim', explode(',', $data['keywords']));
            $keywords = array_values(array_filter($parts));
        }
        $schedule->name = $data['name'];
        $schedule->expected_amount = $data['expected_amount'];
        $schedule->amount_is_variable = $request->boolean('amount_is_variable');
        $schedule->currency = $data['currency'] ?? 'VND';
        $schedule->internal_note = $data['internal_note'] ?? null;
        $schedule->frequency = $data['frequency'];
        $schedule->interval_value = (int) $data['interval_value'];
        $schedule->next_due_date = Carbon::parse($data['next_due_date']);
        $schedule->day_of_month = isset($data['day_of_month']) ? (int) $data['day_of_month'] : null;
        $schedule->reminder_days = isset($data['reminder_days']) && $data['reminder_days'] !== '' ? (int) $data['reminder_days'] : null;
        $schedule->grace_window_days = isset($data['grace_window_days']) ? (int) $data['grace_window_days'] : 7;
        $schedule->keywords = $keywords;
        $schedule->bank_account_number = $data['bank_account_number'] ?? null;
        $schedule->transfer_note_pattern = $data['transfer_note_pattern'] ?? null;
        $schedule->amount_tolerance_pct = isset($data['amount_tolerance_pct']) && $data['amount_tolerance_pct'] !== '' ? $data['amount_tolerance_pct'] : null;
        $schedule->auto_update_amount = $request->boolean('auto_update_amount');
        $schedule->status = $data['status'] ?? 'active';
        $schedule->reliability_tracking = $request->boolean('reliability_tracking', true);
        $schedule->overdue_alert = $request->boolean('overdue_alert', true);
        $schedule->auto_advance_on_match = $request->boolean('auto_advance_on_match', true);
        $schedule->save();
        return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('success', 'Đã cập nhật lịch thanh toán.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('error', 'Vui lòng đăng nhập.');
        }
        $schedule = PaymentSchedule::where('user_id', $user->id)->where('id', $id)->first();
        if (! $schedule) {
            return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('error', 'Không tìm thấy lịch thanh toán.');
        }
        $schedule->delete();
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('tai-chinh', ['tab' => 'lich-thanh-toan'])->with('success', 'Đã xóa lịch thanh toán.');
    }
}
