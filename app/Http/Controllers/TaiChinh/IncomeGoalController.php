<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\IncomeGoal;
use App\Models\IncomeGoalEvent;
use App\Services\GoalIncomeSourceSyncService;
use App\Services\IncomeGoalService;
use App\Services\UserFinancialContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncomeGoalController extends Controller
{
    public function editIncomeGoalJson(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401);
        }
        $g = IncomeGoal::where('user_id', $user->id)->where('id', $id)->first();
        if (! $g) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy mục tiêu.'], 404);
        }
        $periodStart = $g->period_start;
        $periodEnd = $g->period_end;
        if ($periodStart instanceof \Carbon\Carbon) {
            $periodStart = $periodStart->format('Y-m-d');
        }
        if ($periodEnd instanceof \Carbon\Carbon) {
            $periodEnd = $periodEnd->format('Y-m-d');
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $g->id,
                'name' => $g->name,
                'amount_target_vnd' => $g->amount_target_vnd,
                'period_type' => $g->period_type,
                'year' => $g->year,
                'month' => $g->month,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'category_bindings' => $g->category_bindings ?? [],
                'expense_category_bindings' => $g->expense_category_bindings ?? [],
                'income_source_keywords' => $g->income_source_keywords ?? [],
            ],
        ]);
    }

    public function storeIncomeGoal(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
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
        $expenseBindingsInput = $request->input('expense_category_bindings');
        if (is_string($expenseBindingsInput)) {
            $decoded = json_decode($expenseBindingsInput, true);
            $request->merge(['expense_category_bindings' => is_array($decoded) ? $decoded : []]);
        }
        $expenseBindings = $request->input('expense_category_bindings');
        if (is_array($expenseBindings)) {
            $expenseBindings = array_values(array_filter($expenseBindings, fn ($b) => isset($b['type'], $b['id']) && $b['type'] === 'user_category' && (int) $b['id'] > 0));
            $request->merge(['expense_category_bindings' => $expenseBindings]);
        }

        $incomeSourceKeywords = $this->normalizeIncomeSourceKeywordsInput($request->input('income_source_keywords'));

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount_target_vnd' => 'required|integer|min:1000',
            'period_type' => 'required|in:month,custom',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'category_bindings' => 'required|array|min:1',
            'category_bindings.*.type' => 'required|in:user_category',
            'category_bindings.*.id' => 'required|integer|min:1',
            'expense_category_bindings' => 'nullable|array',
            'expense_category_bindings.*.type' => 'required_with:expense_category_bindings.*|in:user_category',
            'expense_category_bindings.*.id' => 'required_with:expense_category_bindings.*|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->withErrors($validator)->withInput();
        }

        $data = [
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'amount_target_vnd' => (int) $request->input('amount_target_vnd'),
            'period_type' => $request->input('period_type'),
            'category_bindings' => $request->input('category_bindings'),
            'expense_category_bindings' => $request->input('expense_category_bindings') ?: null,
            'income_source_keywords' => $incomeSourceKeywords,
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

        $goal = IncomeGoal::create($data);
        IncomeGoalEvent::create([
            'user_id' => $user->id,
            'income_goal_id' => $goal->id,
            'event_type' => 'goal_created',
            'payload' => ['name' => $goal->name, 'amount_target_vnd' => $goal->amount_target_vnd],
        ]);

        app(GoalIncomeSourceSyncService::class)->syncForGoal($goal);

        $msg = 'Đã tạo mục tiêu thu.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }

    public function updateIncomeGoal(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
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
        $expenseBindingsInput = $request->input('expense_category_bindings');
        if (is_string($expenseBindingsInput)) {
            $decoded = json_decode($expenseBindingsInput, true);
            $request->merge(['expense_category_bindings' => is_array($decoded) ? $decoded : []]);
        }
        $expenseBindings = $request->input('expense_category_bindings');
        if (is_array($expenseBindings)) {
            $expenseBindings = array_values(array_filter($expenseBindings, fn ($b) => isset($b['type'], $b['id']) && $b['type'] === 'user_category' && (int) $b['id'] > 0));
            $request->merge(['expense_category_bindings' => $expenseBindings]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount_target_vnd' => 'required|integer|min:1000',
            'period_type' => 'required|in:month,custom',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'category_bindings' => 'required|array|min:1',
            'category_bindings.*.type' => 'required|in:user_category',
            'category_bindings.*.id' => 'required|integer|min:1',
            'expense_category_bindings' => 'nullable|array',
            'expense_category_bindings.*.type' => 'required_with:expense_category_bindings.*|in:user_category',
            'expense_category_bindings.*.id' => 'required_with:expense_category_bindings.*|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->withErrors($validator)->withInput();
        }

        $goal = IncomeGoal::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        $incomeSourceKeywords = $this->normalizeIncomeSourceKeywordsInput($request->input('income_source_keywords'));

        $data = [
            'name' => $request->input('name'),
            'amount_target_vnd' => (int) $request->input('amount_target_vnd'),
            'period_type' => $request->input('period_type'),
            'category_bindings' => $request->input('category_bindings'),
            'expense_category_bindings' => $request->input('expense_category_bindings') ?: null,
            'income_source_keywords' => $incomeSourceKeywords,
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

        $goal->update($data);
        IncomeGoalEvent::create([
            'user_id' => $user->id,
            'income_goal_id' => $goal->id,
            'event_type' => 'goal_updated',
            'payload' => ['name' => $goal->name, 'amount_target_vnd' => $goal->amount_target_vnd],
        ]);

        app(GoalIncomeSourceSyncService::class)->syncForGoal($goal->fresh());

        $msg = 'Đã cập nhật mục tiêu thu.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }

    public function destroyIncomeGoal(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $wantsJson = $request->wantsJson();
        if (! $user) {
            return $wantsJson ? response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401) : redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $goal = IncomeGoal::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        IncomeGoalEvent::create([
            'user_id' => $user->id,
            'income_goal_id' => $goal->id,
            'event_type' => 'goal_deleted',
            'payload' => ['name' => $goal->name, 'amount_target_vnd' => $goal->amount_target_vnd],
        ]);
        $goal->delete();

        $msg = 'Đã xóa mục tiêu thu.';
        return $wantsJson ? response()->json(['success' => true, 'message' => $msg]) : redirect()->route('tai-chinh', ['tab' => 'nguong-ngan-sach'])->with('success', $msg);
    }

    /**
     * Chuẩn hóa input income_source_keywords: string (phân tách dấu phẩy) hoặc array → array.
     *
     * @param  mixed  $input
     * @return array<int, string>
     */
    private function normalizeIncomeSourceKeywordsInput($input): array
    {
        if (is_array($input)) {
            return array_values(array_filter(array_map(function ($v) {
                return is_string($v) ? trim($v) : '';
            }, $input), fn ($v) => $v !== ''));
        }
        if (is_string($input)) {
            return array_values(array_filter(array_map('trim', explode(',', $input)), fn ($v) => $v !== ''));
        }

        return [];
    }

    public function mucTieuThuTable(Request $request)
    {
        $user = $request->user();
        $incomeGoals = $user
            ? IncomeGoal::where('user_id', $user->id)->where('is_active', true)->orderByDesc('created_at')->get()
            : collect();
        $incomeGoalSummary = [];
        if ($user) {
            $linkedAccountNumbers = app(UserFinancialContextService::class)->getLinkedAccountNumbers($user);
            if (! empty($linkedAccountNumbers)) {
                $incomeGoalSummary = app(IncomeGoalService::class)->getGoalSummaryForUser($user->id, $linkedAccountNumbers);
            }
        }

        return response()->view('pages.tai-chinh.partials.muc-tieu-thu-list', [
            'incomeGoals' => $incomeGoals,
            'incomeGoalSummary' => $incomeGoalSummary,
        ]);
    }
}
