<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodCustomer;
use App\Models\FoodSalesReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KhachHangController extends Controller
{
    private const RETURNING_GAP_DAYS = 30;
    private const CHURN_DAYS = 30;
    private const LOYAL_ORDER_THRESHOLD = 8;

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403, 'Bạn không có quyền xem phân tích khách hàng.');
        }

        FoodCustomer::syncForUser($user->id);

        $from = $request->input('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : now()->copy()->startOfMonth();
        $to = $request->input('to_date')
            ? Carbon::parse($request->to_date)->endOfDay()
            : now()->copy()->endOfMonth();

        $reports = FoodSalesReport::query()
            ->where('user_id', $user->id)
            ->whereBetween('report_date', [$from, $to])
            ->with('items')
            ->orderBy('report_date')
            ->get();

        $prevFrom = $from->copy()->subDays($from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay();
        $reportsPrev = FoodSalesReport::query()
            ->where('user_id', $user->id)
            ->whereBetween('report_date', [$prevFrom, $prevTo])
            ->with('items')
            ->get();

        $allReports = FoodSalesReport::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderBy('report_date')
            ->get();

        $ordersAllTime = $this->buildOrdersFromReports($allReports);
        $ordersInPeriod = $this->buildOrdersFromReports($reports);
        $ordersPrevPeriod = $this->buildOrdersFromReports($reportsPrev);

        $customers = $this->aggregateByCustomer($ordersInPeriod, $from, $to);
        $customersPrev = $this->aggregateByCustomer($ordersPrevPeriod, $prevFrom, $prevTo);
        $customersAllTime = $this->aggregateAllTimeByCustomer($ordersAllTime);
        $customers = $this->enrichCustomersWithLifecycle($customers, $customersAllTime, $from, $to);

        $khachMoiCount = collect($customers)->where('is_new_in_period', true)->count();
        $khachCoDonTu2Count = collect($customers)->where('is_returning_in_period', true)->count();
        $prevReturningCount = collect($customersPrev)->where('is_returning_in_period', true)->count();
        $tangTruongReturning = $prevReturningCount > 0
            ? round((($khachCoDonTu2Count - $prevReturningCount) / $prevReturningCount) * 100, 1)
            : ($khachCoDonTu2Count > 0 ? 100 : 0);

        $doanhThuTuKhachQuayLai = collect($customers)->where('is_returning_in_period', true)->sum('total_revenue');
        $doanhThuPrevReturning = collect($customersPrev)->where('is_returning_in_period', true)->sum('total_revenue');
        $tangTruongDoanhThuReturning = $doanhThuPrevReturning > 0
            ? round((($doanhThuTuKhachQuayLai - $doanhThuPrevReturning) / $doanhThuPrevReturning) * 100, 1)
            : ($doanhThuTuKhachQuayLai > 0 ? 100 : 0);

        $lifecycleMetrics = $this->buildLifecycleMetrics($customersAllTime, $from, $to);
        $repeatRate = $lifecycleMetrics['eligible_prior_customers'] > 0
            ? round(($lifecycleMetrics['returning_in_period'] / $lifecycleMetrics['eligible_prior_customers']) * 100, 1)
            : 0;

        $daysToSecondOrder = collect($customersAllTime)
            ->map(fn ($c) => $this->daysToSecondOrder($c))
            ->filter(fn ($d) => $d !== null)
            ->values();
        $avgTimeToSecondOrder = $daysToSecondOrder->isNotEmpty() ? round($daysToSecondOrder->avg(), 1) : null;
        $pctQuayLaiTrong3Ngay = $daysToSecondOrder->isNotEmpty()
            ? round(($daysToSecondOrder->filter(fn ($d) => $d <= 3)->count() / $daysToSecondOrder->count()) * 100, 1)
            : null;
        $pctQuayLaiTrong7Ngay = $daysToSecondOrder->isNotEmpty()
            ? round(($daysToSecondOrder->filter(fn ($d) => $d <= 7)->count() / $daysToSecondOrder->count()) * 100, 1)
            : null;

        $returningCount = FoodCustomer::query()
            ->where('user_id', $user->id)
            ->where('is_returning_customer', true)
            ->count();

        $cohortBy = $request->input('cohort_by', 'month');
        if (! in_array($cohortBy, ['day', 'week', 'month'], true)) {
            $cohortBy = 'month';
        }
        $cohortRows = $this->buildCohortRows($customersAllTime, $cohortBy);

        return view('pages.food.khach-hang', [
            'title' => 'Phân tích khách hàng',
            'from' => $from,
            'to' => $to,
            'customers' => $customers,
            'khachMoiCount' => $khachMoiCount,
            'khachCoDonTu2Count' => $khachCoDonTu2Count,
            'tangTruongReturning' => $tangTruongReturning,
            'tangTruongDoanhThuReturning' => $tangTruongDoanhThuReturning,
            'doanhThuTuKhachQuayLai' => $doanhThuTuKhachQuayLai,
            'returningCount' => $returningCount,
            'repeatRate' => $repeatRate,
            'avgTimeToSecondOrder' => $avgTimeToSecondOrder,
            'pctQuayLaiTrong3Ngay' => $pctQuayLaiTrong3Ngay,
            'pctQuayLaiTrong7Ngay' => $pctQuayLaiTrong7Ngay,
            'lifecycleMetrics' => $lifecycleMetrics,
            'cohortBy' => $cohortBy,
            'cohortRows' => $cohortRows,
        ]);
    }

    private function buildOrdersFromReports($reports): array
    {
        $orders = [];
        foreach ($reports as $report) {
            $reportDate = $report->report_date;
            $byInvoice = [];
            foreach ($report->items as $item) {
                $don = $item->ma_hoa_don ?? '_'.$item->id;
                if (! isset($byInvoice[$don])) {
                    $byInvoice[$don] = [
                        'report_id' => $report->id,
                        'report_date' => $reportDate,
                        'khach_hang' => $item->khach_hang,
                        'doanh_thu' => 0,
                    ];
                }
                $byInvoice[$don]['doanh_thu'] += (float) ($item->doanh_thu_thuan ?? 0);
            }
            foreach ($byInvoice as $ord) {
                $orders[] = $ord;
            }
        }
        return $orders;
    }

    /** Số ngày từ đơn đầu đến đơn thứ 2 (theo order_dates). Trả null nếu < 2 ngày có đơn. */
    private function daysToSecondOrder(array $customer): ?float
    {
        $dates = $customer['order_dates'] ?? [];
        if (count($dates) < 2) {
            return null;
        }
        sort($dates);
        $first = Carbon::parse($dates[0]);
        $second = Carbon::parse($dates[1]);

        return (float) $first->diffInDays($second);
    }

    /**
     * Phân loại theo HÀNH VI trong kỳ:
     * - Khách mới: order_count = 1 trong kỳ
     * - Khách quay lại: order_count >= 2 trong kỳ
     * - Khách trung thành: order_count >= 4 trong kỳ
     */
    private function aggregateByCustomer(array $orders, $periodStart, $periodEnd): array
    {
        $byCustomer = [];
        foreach ($orders as $ord) {
            $name = trim((string) ($ord['khach_hang'] ?? ''));
            if ($name === '') {
                $name = '— Không tên —';
            }
            $key = mb_strtolower($name);
            if (! isset($byCustomer[$key])) {
                $byCustomer[$key] = [
                    'name' => $name,
                    'order_count' => 0,
                    'total_revenue' => 0,
                    'order_dates' => [],
                    'first_order_date' => null,
                    'last_order_date' => null,
                ];
            }
            $reportDate = $ord['report_date'] ?? null;
            if ($reportDate) {
                $dStr = $reportDate->format('Y-m-d');
                $byCustomer[$key]['order_count']++;
                $byCustomer[$key]['total_revenue'] += (float) ($ord['doanh_thu'] ?? 0);
                if (! in_array($dStr, $byCustomer[$key]['order_dates'], true)) {
                    $byCustomer[$key]['order_dates'][] = $dStr;
                }
                if (! $byCustomer[$key]['first_order_date'] || $reportDate->lt($byCustomer[$key]['first_order_date'])) {
                    $byCustomer[$key]['first_order_date'] = $reportDate;
                }
                if (! $byCustomer[$key]['last_order_date'] || $reportDate->gt($byCustomer[$key]['last_order_date'])) {
                    $byCustomer[$key]['last_order_date'] = $reportDate;
                }
            }
        }

        unset($byCustomer[FoodCustomer::EXCLUDED_CUSTOMER_KEY]);

        $result = [];
        foreach ($byCustomer as $key => $c) {
            sort($c['order_dates']);
            $orderCount = $c['order_count'];
            $isNewInPeriod = $orderCount === 1;
            $isReturningInPeriod = $orderCount >= 2;
            $isLoyalInPeriod = $orderCount >= 4;

            $orderDates = $c['order_dates'];
            $uniqueOrderDays = count($orderDates);
            $n = $uniqueOrderDays;
            $firstDate = $n >= 1 ? Carbon::parse($orderDates[0])->startOfDay() : null;
            $lastDate = $n >= 1 ? Carbon::parse($orderDates[$n - 1])->startOfDay() : null;
            $today = now()->startOfDay();

            // Khoảng cách giữa ngày đơn đầu và ngày đơn cuối (chỉ các ngày có đơn, unique)
            $daysSpanFirstToLast = 0;
            if ($n >= 2 && $firstDate && $lastDate) {
                $daysSpanFirstToLast = max(1, (int) $firstDate->diffInDays($lastDate));
            } elseif ($n === 1) {
                $daysSpanFirstToLast = 1;
            }

            // Recency: số ngày từ đơn gần nhất đến hôm nay (0 = hôm nay)
            $recencyDays = null;
            if ($c['last_order_date']) {
                $lastOrderDay = $c['last_order_date']->copy()->startOfDay();
                $recencyDays = $lastOrderDay->gt($today) ? 0 : (int) $lastOrderDay->diffInDays($today);
            }

            // Nhịp độ theo ngày có đơn (không dùng tổng số đơn — tránh same-day inflation)
            // Công thức: unique_order_days / (hôm nay − đơn đầu) × 30; chỉ khi ≥2 ngày có đơn
            $ordersPerMonth = null;
            if ($uniqueOrderDays >= 2 && $firstDate) {
                $spanToToday = max(1, (int) $firstDate->diffInDays($today));
                $ordersPerMonth = round(($uniqueOrderDays / $spanToToday) * 30, 1);
            }

            $avgDaysBetween = $n >= 2 ? round($daysSpanFirstToLast / ($n - 1), 0) : null;

            $result[] = [
                'name' => $c['name'],
                'order_count' => $c['order_count'],
                'unique_order_days' => $uniqueOrderDays,
                'total_revenue' => $c['total_revenue'],
                'first_order_date' => $c['first_order_date'],
                'last_order_date' => $c['last_order_date'],
                'order_dates' => $c['order_dates'],
                'is_new_in_period' => $isNewInPeriod,
                'is_returning_in_period' => $isReturningInPeriod,
                'is_loyal_in_period' => $isLoyalInPeriod,
                'orders_per_month' => $ordersPerMonth,
                'recency_days' => $recencyDays,
                'avg_days_between_orders' => $avgDaysBetween,
            ];
        }

        usort($result, function ($a, $b) {
            if ($b['order_count'] !== $a['order_count']) {
                return $b['order_count'] <=> $a['order_count'];
            }
            return ($b['total_revenue'] <=> $a['total_revenue']);
        });

        return $result;
    }

    private function aggregateAllTimeByCustomer(array $orders): array
    {
        $byCustomer = [];
        foreach ($orders as $ord) {
            $name = trim((string) ($ord['khach_hang'] ?? ''));
            if ($name === '') {
                $name = '— Không tên —';
            }
            $key = mb_strtolower($name);
            if ($key === FoodCustomer::EXCLUDED_CUSTOMER_KEY) {
                continue;
            }
            if (! isset($byCustomer[$key])) {
                $byCustomer[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'order_count' => 0,
                    'total_revenue' => 0,
                    'order_dates' => [],
                    'first_order_date' => null,
                    'last_order_date' => null,
                ];
            }
            $reportDate = $ord['report_date'] ?? null;
            if (! $reportDate) {
                continue;
            }
            $dStr = $reportDate->format('Y-m-d');
            $byCustomer[$key]['order_count']++;
            $byCustomer[$key]['total_revenue'] += (float) ($ord['doanh_thu'] ?? 0);
            if (! in_array($dStr, $byCustomer[$key]['order_dates'], true)) {
                $byCustomer[$key]['order_dates'][] = $dStr;
            }
            if (! $byCustomer[$key]['first_order_date'] || $reportDate->lt($byCustomer[$key]['first_order_date'])) {
                $byCustomer[$key]['first_order_date'] = $reportDate->copy();
            }
            if (! $byCustomer[$key]['last_order_date'] || $reportDate->gt($byCustomer[$key]['last_order_date'])) {
                $byCustomer[$key]['last_order_date'] = $reportDate->copy();
            }
        }

        foreach ($byCustomer as &$customer) {
            sort($customer['order_dates']);
        }
        unset($customer);

        return $byCustomer;
    }

    private function enrichCustomersWithLifecycle(array $periodCustomers, array $allTimeCustomers, Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

        foreach ($periodCustomers as &$customer) {
            $key = mb_strtolower((string) ($customer['name'] ?? ''));
            $all = $allTimeCustomers[$key] ?? null;
            $customer['first_order_all_time'] = $all['first_order_date'] ?? null;
            $customer['all_time_order_count'] = $all['order_count'] ?? $customer['order_count'];
            $customer['order_segment'] = $this->orderSegment((int) $customer['all_time_order_count']);

            $gapDays = null;
            $hasPriorPurchase = false;
            if ($all && ! empty($all['order_dates'])) {
                $firstInPeriod = collect($all['order_dates'])
                    ->map(fn ($d) => Carbon::parse($d)->startOfDay())
                    ->filter(fn ($d) => $d->betweenIncluded($fromDay, $toDay))
                    ->sortBy(fn ($d) => $d->timestamp)
                    ->first();
                $lastBeforePeriod = collect($all['order_dates'])
                    ->map(fn ($d) => Carbon::parse($d)->startOfDay())
                    ->filter(fn ($d) => $d->lt($fromDay))
                    ->sortByDesc(fn ($d) => $d->timestamp)
                    ->first();

                $hasPriorPurchase = $lastBeforePeriod !== null;
                if ($firstInPeriod && $lastBeforePeriod) {
                    $gapDays = (int) $lastBeforePeriod->diffInDays($firstInPeriod);
                }
            }

            $isNew = $customer['first_order_all_time']
                ? $customer['first_order_all_time']->copy()->betweenIncluded($fromDay, $toDay)
                : false;
            $isReturning = $hasPriorPurchase && $gapDays !== null && $gapDays >= self::RETURNING_GAP_DAYS;
            $isActive = $hasPriorPurchase && ! $isReturning;

            $customer['lifecycle_status'] = $isNew
                ? 'new'
                : ($isReturning ? 'returning' : 'active');
            $customer['returning_gap_days'] = $gapDays;
        }
        unset($customer);

        return $periodCustomers;
    }

    private function buildLifecycleMetrics(array $allTimeCustomers, Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();
        $today = now()->startOfDay();

        $newInPeriod = 0;
        $activeInPeriod = 0;
        $returningInPeriod = 0;
        $churned = 0;
        $loyal = 0;
        $eligiblePriorCustomers = 0;

        foreach ($allTimeCustomers as $customer) {
            $orderDates = collect($customer['order_dates'] ?? [])
                ->map(fn ($d) => Carbon::parse($d)->startOfDay())
                ->sortBy(fn ($d) => $d->timestamp)
                ->values();
            if ($orderDates->isEmpty()) {
                continue;
            }

            $first = $orderDates->first();
            $last = $orderDates->last();
            $hasOrderInPeriod = $orderDates->contains(fn ($d) => $d->betweenIncluded($fromDay, $toDay));
            $lastBeforePeriod = $orderDates->filter(fn ($d) => $d->lt($fromDay))->last();
            $firstInPeriod = $orderDates->filter(fn ($d) => $d->betweenIncluded($fromDay, $toDay))->first();
            $hasPrior = $first->lt($fromDay);

            if ($hasPrior) {
                $eligiblePriorCustomers++;
            }

            if ($first->betweenIncluded($fromDay, $toDay)) {
                $newInPeriod++;
            } elseif ($hasOrderInPeriod && $hasPrior) {
                $gapDays = $lastBeforePeriod && $firstInPeriod ? $lastBeforePeriod->diffInDays($firstInPeriod) : 0;
                if ($gapDays >= self::RETURNING_GAP_DAYS) {
                    $returningInPeriod++;
                } else {
                    $activeInPeriod++;
                }
            }

            if ($last->diffInDays($today) > self::CHURN_DAYS) {
                $churned++;
            }

            if ((int) ($customer['order_count'] ?? 0) >= self::LOYAL_ORDER_THRESHOLD) {
                $loyal++;
            }
        }

        return [
            'new_in_period' => $newInPeriod,
            'active_in_period' => $activeInPeriod,
            'returning_in_period' => $returningInPeriod,
            'churned' => $churned,
            'loyal' => $loyal,
            'eligible_prior_customers' => $eligiblePriorCustomers,
            'returning_gap_days' => self::RETURNING_GAP_DAYS,
            'churn_days' => self::CHURN_DAYS,
            'loyal_order_threshold' => self::LOYAL_ORDER_THRESHOLD,
        ];
    }

    private function buildCohortRows(array $allTimeCustomers, string $cohortBy): array
    {
        $cohorts = [];
        foreach ($allTimeCustomers as $customer) {
            $orderDates = collect($customer['order_dates'] ?? [])
                ->map(fn ($d) => Carbon::parse($d)->startOfDay())
                ->sortBy(fn ($d) => $d->timestamp)
                ->values();
            if ($orderDates->isEmpty()) {
                continue;
            }

            $first = $orderDates->first();
            $cohortKey = $this->cohortKey($first, $cohortBy);
            if (! isset($cohorts[$cohortKey])) {
                $cohorts[$cohortKey] = [
                    'cohort' => $cohortKey,
                    'size' => 0,
                    'd3' => 0,
                    'd7' => 0,
                    'd30' => 0,
                ];
            }

            $cohorts[$cohortKey]['size']++;
            if ($this->hasReturnWithin($orderDates->all(), 3)) {
                $cohorts[$cohortKey]['d3']++;
            }
            if ($this->hasReturnWithin($orderDates->all(), 7)) {
                $cohorts[$cohortKey]['d7']++;
            }
            if ($this->hasReturnWithin($orderDates->all(), 30)) {
                $cohorts[$cohortKey]['d30']++;
            }
        }

        ksort($cohorts);
        $rows = [];
        foreach ($cohorts as $cohort) {
            $size = max(1, (int) $cohort['size']);
            $rows[] = [
                'cohort' => $cohort['cohort'],
                'size' => $cohort['size'],
                'd0_pct' => 100.0,
                'd3_pct' => round(($cohort['d3'] / $size) * 100, 1),
                'd7_pct' => round(($cohort['d7'] / $size) * 100, 1),
                'd30_pct' => round(($cohort['d30'] / $size) * 100, 1),
            ];
        }

        return $rows;
    }

    private function hasReturnWithin(array $dates, int $days): bool
    {
        if (count($dates) < 2) {
            return false;
        }
        $first = $dates[0];
        foreach ($dates as $idx => $date) {
            if ($idx === 0) {
                continue;
            }
            $diff = $first->diffInDays($date);
            if ($diff > 0 && $diff <= $days) {
                return true;
            }
        }

        return false;
    }

    private function cohortKey(Carbon $firstOrderDate, string $cohortBy): string
    {
        if ($cohortBy === 'day') {
            return $firstOrderDate->format('d/m/Y');
        }
        if ($cohortBy === 'week') {
            return $firstOrderDate->format('Y') . '-W' . str_pad((string) $firstOrderDate->weekOfYear, 2, '0', STR_PAD_LEFT);
        }

        return $firstOrderDate->format('m/Y');
    }

    private function orderSegment(int $orderCount): string
    {
        if ($orderCount <= 1) {
            return 'new';
        }
        if ($orderCount <= 3) {
            return 'early-repeat';
        }
        if ($orderCount <= 7) {
            return 'repeat';
        }

        return 'loyal';
    }
}
