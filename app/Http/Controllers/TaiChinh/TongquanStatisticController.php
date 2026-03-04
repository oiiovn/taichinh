<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Food\FoodController;
use App\Models\UserTongquanStatistic;
use App\Services\TaiChinh\TaiChinhViewCache;
use App\Services\TaiChinh\TongquanStatisticService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TongquanStatisticController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $validPeriods = [
            FoodController::PERIOD_DAY,
            FoodController::PERIOD_WEEK,
            FoodController::PERIOD_MONTH,
            FoodController::PERIOD_3MONTH,
            FoodController::PERIOD_6MONTH,
            FoodController::PERIOD_12MONTH,
        ];
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'period' => 'required|string|in:' . implode(',', $validPeriods),
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'thu_category_ids' => 'nullable|array',
            'thu_category_ids.*' => 'integer',
            'chi_category_ids' => 'nullable|array',
            'chi_category_ids.*' => 'integer',
        ]);
        if ($v->fails()) {
            return redirect()->route('tai-chinh', ['tab' => 'dashboard'])->with('error', $v->errors()->first())->withInput();
        }
        $from = null;
        $to = null;
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = TongquanStatisticService::parseDate($request->input('from_date'));
            $to = TongquanStatisticService::parseDate($request->input('to_date'));
            if ($from && $to) {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }
        if (! $from || ! $to) {
            [$from, $to] = TongquanStatisticService::getDateRangeFromPeriod($request->input('period'));
        }
        $thuIds = array_values(array_filter(array_map('intval', $request->input('thu_category_ids', []))));
        $chiIds = array_values(array_filter(array_map('intval', $request->input('chi_category_ids', []))));
        UserTongquanStatistic::query()->create([
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'period' => $request->input('period'),
            'from_date' => $from,
            'to_date' => $to,
            'thu_category_ids' => $thuIds,
            'chi_category_ids' => $chiIds,
        ]);
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('tai-chinh', ['tab' => 'dashboard'])->with('success', 'Đã thêm thống kê.');
    }

    public function update(Request $request, UserTongquanStatistic $tongquanStatistic): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $tongquanStatistic->user_id !== (int) $user->id) {
            abort(403);
        }
        $validPeriods = [
            FoodController::PERIOD_DAY,
            FoodController::PERIOD_WEEK,
            FoodController::PERIOD_MONTH,
            FoodController::PERIOD_3MONTH,
            FoodController::PERIOD_6MONTH,
            FoodController::PERIOD_12MONTH,
        ];
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'period' => 'required|string|in:' . implode(',', $validPeriods),
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'thu_category_ids' => 'nullable|array',
            'thu_category_ids.*' => 'integer',
            'chi_category_ids' => 'nullable|array',
            'chi_category_ids.*' => 'integer',
        ]);
        if ($v->fails()) {
            return redirect()->route('tai-chinh', ['tab' => 'dashboard'])->with('error', $v->errors()->first())->withInput();
        }
        $from = null;
        $to = null;
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = TongquanStatisticService::parseDate($request->input('from_date'));
            $to = TongquanStatisticService::parseDate($request->input('to_date'));
            if ($from && $to) {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }
        if (! $from || ! $to) {
            [$from, $to] = TongquanStatisticService::getDateRangeFromPeriod($request->input('period'));
        }
        $thuIds = array_values(array_filter(array_map('intval', $request->input('thu_category_ids', []))));
        $chiIds = array_values(array_filter(array_map('intval', $request->input('chi_category_ids', []))));
        $tongquanStatistic->update([
            'name' => $request->input('name'),
            'period' => $request->input('period'),
            'from_date' => $from,
            'to_date' => $to,
            'thu_category_ids' => $thuIds,
            'chi_category_ids' => $chiIds,
        ]);
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('tai-chinh', ['tab' => 'dashboard'])->with('success', 'Đã cập nhật thống kê.');
    }

    public function destroy(Request $request, UserTongquanStatistic $tongquanStatistic): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $tongquanStatistic->user_id !== (int) $user->id) {
            abort(403);
        }
        $tongquanStatistic->delete();
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('tai-chinh', ['tab' => 'dashboard'])->with('success', 'Đã xóa thống kê.');
    }
}
