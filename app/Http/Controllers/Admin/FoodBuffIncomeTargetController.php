<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodBuffIncomeTarget;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FoodBuffIncomeTargetController extends Controller
{
    public function upsert(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'target_amount' => ['required', 'integer', 'min:1'],
            'target_month' => ['required', 'date_format:Y-m'],
        ], [
            'user_id.required' => 'Vui lòng chọn user.',
            'target_amount.required' => 'Vui lòng nhập số tiền mục tiêu.',
            'target_month.required' => 'Vui lòng chọn tháng.',
        ]);

        $targetMonth = Carbon::createFromFormat('Y-m', (string) $validated['target_month'])->startOfMonth()->toDateString();

        FoodBuffIncomeTarget::query()->updateOrCreate(
            [
                'user_id' => (int) $validated['user_id'],
                'target_month' => $targetMonth,
            ],
            [
                'target_amount' => (int) $validated['target_amount'],
                'created_by_user_id' => $user->id,
            ]
        );

        return back()->with('success', 'Đã lưu mục tiêu thu nhập tháng.');
    }
}
