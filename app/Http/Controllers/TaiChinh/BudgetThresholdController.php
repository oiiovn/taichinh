<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\BudgetThreshold;
use App\Models\BudgetThresholdEvent;
use App\Models\IncomeGoal;
use App\Services\BudgetThresholdService;
use App\Services\IncomeGoalService;
use App\Services\UserFinancialContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetThresholdController extends Controller
{
    public function nguongNganSachTable(Request $request)
    {
        $user = $request->user();
        $budgetThresholds = $user
            ? BudgetThreshold::where('user_id', $user->id)->where('is_active', true)->orderByDesc('created_at')->get()
            : collect();
        $budgetThresholdSummary = [];
        $incomeGoals = $user
            ? IncomeGoal::where('user_id', $user->id)->where('is_active', true)->orderByDesc('created_at')->get()
            : collect();
        $incomeGoalSummary = ['goals' => []];
        if ($user) {
            $linkedAccountNumbers = app(UserFinancialContextService::class)->getLinkedAccountNumbers($user);
            if (! empty($linkedAccountNumbers)) {
                $budgetThresholdSummary = app(BudgetThresholdService::class)->getThresholdSummaryForUser($user->id, $linkedAccountNumbers);
                $incomeGoalSummary = app(IncomeGoalService::class)->getGoalSummaryForUser($user->id, $linkedAccountNumbers);
            }
        }

        return response()->view('pages.tai-chinh.partials.nguong-ngan-sach-list', [
            'budgetThresholds' => $budgetThresholds,
            'budgetThresholdSummary' => $budgetThresholdSummary,
            'incomeGoals' => $incomeGoals,
            'incomeGoalSummary' => $incomeGoalSummary,
            'filter_pct' => $request->input('filter_pct', ''),
            'filter_vuot' => $request->input('filter_vuot', ''),
            'filter_het_han' => $request->input('filter_het_han', ''),
        ]);
    }

    public function editBudgetThresholdJson(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401);
        }
        $t = BudgetThreshold::where('user_id', $user->id)->where('id', $id)->first();
        if (! $t) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy ngưỡng.'], 404);
        }
        $periodStart = $t->period_start;
        $periodEnd = $t->period_end;
        if ($periodStart instanceof \Carbon\Carbon) {
            $periodStart = $periodStart->format('Y-m-d');
        }
        if ($periodEnd instanceof \Carbon\Carbon) {
            $periodEnd = $periodEnd->format('Y-m-d');
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $t->id,
                'name' => $t->name,
                'amount_limit_vnd' => $t->amount_limit_vnd,
                'period_type' => $t->period_type,
                'year' => $t->year,
                'month' => $t->month,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'category_bindings' => $t->category_bindings ?? [],
            ],
        ]);
    }

    public function storeBudgetThreshold(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $wantsJson = $request->wantsJson();
        if (! $user) {
            return $wantsJson ? response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401) : redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $bindingsInput = $request->input('category_bindings');
        if (is_string($bindingsInput)) {
            $decoded = json_decode($bindingsInput, true);
            $request->merge(['category_bindings' => is_array($decoded) ? $decoded : []]);
        }

        $bindings = $request->input('category_bindings');
        if (is_array($bindings)) {
            $bindings = array_values(array_filter($bindings, fn ($b) => isset($b['type'], $b['id']) && $b['type'] === 'user_category' && (int) $b['id'] > 0));
            $request->merge(['category_bindings' => $bindings]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount_limit_vnd' => 'required|integer|min:1000',
            'period_type' => 'required|in:month,custom',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'category_bindings' => 'required|array|min:1',
            'category_bindings.*.type' => 'required|in:user_category',
            'category_bindings.*.id' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->withErrors($validator)->withInput();
        }

        $data = [
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'amount_limit_vnd' => (int) $request->input('amount_limit_vnd'),
            'period_type' => $request->input('period_type'),
            'category_bindings' => $request->input('category_bindings'),
            'is_active' => true,
        ];

        if ($request->input('period_type') === 'month') {
            $now = now();
            $data['year'] = (int) ($request->input('year') ?: $now->year);
            $data['month'] = (int) ($request->input('month') ?: $now->month);
            $data['period_start'] = null;
            $data['period_end'] = null;
        } else {
            $data['year'] = null;
            $data['month'] = null;
            $data['period_start'] = $request->input('period_start');
            $data['period_end'] = $request->input('period_end');
        }

        $threshold = BudgetThreshold::create($data);
        BudgetThresholdEvent::create([
            'user_id' => $user->id,
            'budget_threshold_id' => $threshold->id,
            'event_type' => 'threshold_created',
            'payload' => ['name' => $threshold->name, 'amount_limit_vnd' => $threshold->amount_limit_vnd],
        ]);

        $msg = 'Đã tạo ngưỡng ngân sách.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }

    public function updateBudgetThreshold(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $wantsJson = $request->wantsJson();
        if (! $user) {
            return $wantsJson ? response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401) : redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $bindingsInput = $request->input('category_bindings');
        if (is_string($bindingsInput)) {
            $decoded = json_decode($bindingsInput, true);
            $request->merge(['category_bindings' => is_array($decoded) ? $decoded : []]);
        }

        $bindings = $request->input('category_bindings');
        if (is_array($bindings)) {
            $bindings = array_values(array_filter($bindings, fn ($b) => isset($b['type'], $b['id']) && $b['type'] === 'user_category' && (int) $b['id'] > 0));
            $request->merge(['category_bindings' => $bindings]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount_limit_vnd' => 'required|integer|min:1000',
            'period_type' => 'required|in:month,custom',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'category_bindings' => 'required|array|min:1',
            'category_bindings.*.type' => 'required|in:user_category',
            'category_bindings.*.id' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->withErrors($validator)->withInput();
        }

        $threshold = BudgetThreshold::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        $data = [
            'name' => $request->input('name'),
            'amount_limit_vnd' => (int) $request->input('amount_limit_vnd'),
            'period_type' => $request->input('period_type'),
            'category_bindings' => $request->input('category_bindings'),
        ];

        if ($request->input('period_type') === 'month') {
            $data['year'] = (int) ($request->input('year') ?: now()->year);
            $data['month'] = (int) ($request->input('month') ?: now()->month);
            $data['period_start'] = null;
            $data['period_end'] = null;
        } else {
            $data['year'] = null;
            $data['month'] = null;
            $data['period_start'] = $request->input('period_start');
            $data['period_end'] = $request->input('period_end');
        }

        $threshold->update($data);
        BudgetThresholdEvent::create([
            'user_id' => $user->id,
            'budget_threshold_id' => $threshold->id,
            'event_type' => 'threshold_updated',
            'payload' => ['name' => $threshold->name, 'amount_limit_vnd' => $threshold->amount_limit_vnd],
        ]);

        $msg = 'Đã cập nhật ngưỡng.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }

    public function destroyBudgetThreshold(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $wantsJson = $request->wantsJson();
        if (! $user) {
            return $wantsJson ? response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401) : redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $threshold = BudgetThreshold::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        BudgetThresholdEvent::create([
            'user_id' => $user->id,
            'budget_threshold_id' => $threshold->id,
            'event_type' => 'threshold_deleted',
            'payload' => ['name' => $threshold->name, 'amount_limit_vnd' => $threshold->amount_limit_vnd],
        ]);
        $threshold->delete();

        $msg = 'Đã xóa ngưỡng.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }
}
