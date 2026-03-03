<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Models\UserCategory;
use App\Helpers\BaoCaoHelper;
use App\Services\UserFinancialContextService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FoodController extends Controller
{
    public const PERIOD_DAY = 'ngay';
    public const PERIOD_WEEK = 'tuan';
    public const PERIOD_MONTH = 'thang';
    public const PERIOD_3MONTH = '3-thang';
    public const PERIOD_6MONTH = '6-thang';
    public const PERIOD_12MONTH = '12-thang';

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! $user->is_admin) {
            return redirect()->route('food.cong-no');
        }

        $contextSvc = app(UserFinancialContextService::class);
        $contextSvc->ensureCategoriesAndGetContext($user);
        $danhMucThu = UserCategory::where('user_id', $user->id)->where('type', 'income')->orderBy('name')->get();
        $danhMucChi = UserCategory::where('user_id', $user->id)->where('type', 'expense')->orderBy('name')->get();

        $saved = $user->food_tongquan_settings ?? [];
        $validPeriods = [self::PERIOD_DAY, self::PERIOD_WEEK, self::PERIOD_MONTH, self::PERIOD_3MONTH, self::PERIOD_6MONTH, self::PERIOD_12MONTH];

        $period = $request->input('period');
        if ($period === null) {
            $period = $request->session()->get('food_tongquan_period') ?? $saved['period'] ?? self::PERIOD_MONTH;
        }
        if (! in_array($period, $validPeriods, true)) {
            $period = self::PERIOD_MONTH;
        }

        $from = null;
        $to = null;
        $fromDateInput = $request->input('from_date');
        $toDateInput = $request->input('to_date');
        $hasExplicitDates = $fromDateInput !== null && $fromDateInput !== '' && $toDateInput !== null && $toDateInput !== '';
        $hasExplicitPeriod = $request->has('period');
        if ($hasExplicitDates) {
            $from = $this->parseDate($fromDateInput);
            $to = $this->parseDate($toDateInput);
            if ($from && $to) {
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                $from = $from->copy()->startOfDay();
                $to = $to->copy()->endOfDay();
            }
        }
        if (! $from || ! $to) {
            if ($hasExplicitPeriod) {
                [$from, $to] = $this->getDateRange($period);
            } else {
                $fromDateInput = $request->session()->get('food_tongquan_from_date') ?? $saved['from_date'] ?? null;
                $toDateInput = $request->session()->get('food_tongquan_to_date') ?? $saved['to_date'] ?? null;
                if ($fromDateInput && $toDateInput) {
                    $from = $this->parseDate($fromDateInput);
                    $to = $this->parseDate($toDateInput);
                    if ($from && $to) {
                        if ($from->gt($to)) {
                            [$from, $to] = [$to, $from];
                        }
                        $from = $from->copy()->startOfDay();
                        $to = $to->copy()->endOfDay();
                    }
                }
            }
        }
        if (! $from || ! $to) {
            [$from, $to] = $this->getDateRange($period);
        }

        $hasFilterParams = $request->has('period') || $request->has('from_date') || $request->has('to_date');
        $hasCategoryParams = $request->has('thu_category_ids') || $request->has('chi_category_ids');
        $fromRequest = $hasFilterParams || $hasCategoryParams;

        if ($fromRequest) {
            if ($hasCategoryParams) {
                $thuIds = $request->input('thu_category_ids', []);
                $chiIds = $request->input('chi_category_ids', []);
                if (! is_array($thuIds)) {
                    $thuIds = $thuIds ? [$thuIds] : [];
                }
                if (! is_array($chiIds)) {
                    $chiIds = $chiIds ? [$chiIds] : [];
                }
                $thuIds = array_filter(array_map('intval', $thuIds));
                $chiIds = array_filter(array_map('intval', $chiIds));
            } else {
                $thuIds = $request->session()->get('food_tongquan_thu_category_ids') ?? $saved['thu_category_ids'] ?? [];
                $chiIds = $request->session()->get('food_tongquan_chi_category_ids') ?? $saved['chi_category_ids'] ?? [];
                $thuIds = is_array($thuIds) ? array_filter(array_map('intval', $thuIds)) : [];
                $chiIds = is_array($chiIds) ? array_filter(array_map('intval', $chiIds)) : [];
            }
        } else {
            $thuIds = $request->session()->get('food_tongquan_thu_category_ids') ?? $saved['thu_category_ids'] ?? [];
            $chiIds = $request->session()->get('food_tongquan_chi_category_ids') ?? $saved['chi_category_ids'] ?? [];
            $thuIds = is_array($thuIds) ? array_filter(array_map('intval', $thuIds)) : [];
            $chiIds = is_array($chiIds) ? array_filter(array_map('intval', $chiIds)) : [];
        }

        if ($fromRequest) {
            $request->session()->put('food_tongquan_period', $period);
            $request->session()->put('food_tongquan_from_date', $from ? $from->format('Y-m-d') : null);
            $request->session()->put('food_tongquan_to_date', $to ? $to->format('Y-m-d') : null);
            $request->session()->put('food_tongquan_thu_category_ids', $thuIds);
            $request->session()->put('food_tongquan_chi_category_ids', $chiIds);
            $user->food_tongquan_settings = [
                'period' => $period,
                'from_date' => $from ? $from->format('Y-m-d') : null,
                'to_date' => $to ? $to->format('Y-m-d') : null,
                'thu_category_ids' => $thuIds,
                'chi_category_ids' => $chiIds,
            ];
            $user->save();
        }

        $thuTotal = 0.0;
        $chiTotal = 0.0;

        $baseQuery = TransactionHistory::query()
            ->where('user_id', $user->id)
            ->whereBetween('transaction_date', [$from, $to]);

        $linkedAccounts = $contextSvc->getLinkedAccountNumbers($user);
        if (! empty($linkedAccounts)) {
            $baseQuery->whereIn('account_number', $linkedAccounts);
        }

        if (! empty($thuIds)) {
            $thuTotal = (float) (clone $baseQuery)->where('type', 'IN')->whereIn('user_category_id', $thuIds)->sum('amount');
        }
        if (! empty($chiIds)) {
            $chiTotal = (float) (clone $baseQuery)->where('type', 'OUT')->whereIn('user_category_id', $chiIds)->sum(DB::raw('ABS(amount)'));
        }

        $loiNhuan = $thuTotal - $chiTotal;

        $chartDates = [];
        $chartThu = [];
        $chartChi = [];
        $chartLoiNhuan = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $chartDates[] = $cursor->format('d/m');
            $dayThu = 0.0;
            $dayChi = 0.0;
            if (! empty($thuIds)) {
                $q = TransactionHistory::query()->where('user_id', $user->id)->where('type', 'IN')->whereIn('user_category_id', $thuIds)->whereBetween('transaction_date', [$dayStart, $dayEnd]);
                if (! empty($linkedAccounts)) {
                    $q->whereIn('account_number', $linkedAccounts);
                }
                $dayThu = (float) $q->sum('amount');
            }
            if (! empty($chiIds)) {
                $q = TransactionHistory::query()->where('user_id', $user->id)->where('type', 'OUT')->whereIn('user_category_id', $chiIds)->whereBetween('transaction_date', [$dayStart, $dayEnd]);
                if (! empty($linkedAccounts)) {
                    $q->whereIn('account_number', $linkedAccounts);
                }
                $dayChi = (float) $q->sum(DB::raw('ABS(amount)'));
            }
            $chartThu[] = round($dayThu);
            $chartChi[] = round($dayChi);
            $chartLoiNhuan[] = round($dayThu - $dayChi);
            $cursor->addDay();
        }

        return view('pages.food', [
            'title' => 'Food',
            'danhMucThu' => $danhMucThu,
            'danhMucChi' => $danhMucChi,
            'period' => $period,
            'periodLabel' => $this->getPeriodLabel($period),
            'from' => $from,
            'to' => $to,
            'thuCategoryIds' => $thuIds,
            'chiCategoryIds' => $chiIds,
            'thuTotal' => $thuTotal,
            'chiTotal' => $chiTotal,
            'loiNhuan' => $loiNhuan,
            'chartDates' => $chartDates,
            'chartThu' => $chartThu,
            'chartChi' => $chartChi,
            'chartLoiNhuan' => $chartLoiNhuan,
            'fromDateInput' => $from ? $from->format('Y-m-d') : '',
            'toDateInput' => $to ? $to->format('Y-m-d') : '',
        ]);
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();
        $to = $now->copy()->endOfDay();
        switch ($period) {
            case self::PERIOD_DAY:
                $from = $now->copy()->startOfDay();
                break;
            case self::PERIOD_WEEK:
                $from = $now->copy()->startOfWeek()->startOfDay();
                break;
            case self::PERIOD_MONTH:
                $from = $now->copy()->startOfMonth()->startOfDay();
                break;
            case self::PERIOD_3MONTH:
                $from = $now->copy()->subMonths(2)->startOfMonth()->startOfDay();
                break;
            case self::PERIOD_6MONTH:
                $from = $now->copy()->subMonths(5)->startOfMonth()->startOfDay();
                break;
            case self::PERIOD_12MONTH:
                $from = $now->copy()->subMonths(11)->startOfMonth()->startOfDay();
                break;
            default:
                $from = $now->copy()->startOfMonth()->startOfDay();
        }

        return [$from, $to];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || ! is_string($value)) {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $c = Carbon::createFromFormat($format, $value);
                if ($c instanceof Carbon) {
                    return $c;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    private function getPeriodLabel(string $period): string
    {
        return match ($period) {
            self::PERIOD_DAY => 'Hôm nay',
            self::PERIOD_WEEK => 'Tuần',
            self::PERIOD_MONTH => 'Tháng này',
            self::PERIOD_3MONTH => '3 tháng',
            self::PERIOD_6MONTH => '6 tháng',
            self::PERIOD_12MONTH => '12 tháng',
            default => 'Tháng',
        };
    }
}
