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

        $ordersInPeriod = $this->buildOrdersFromReports($reports);
        $ordersPrevPeriod = $this->buildOrdersFromReports($reportsPrev);

        $customers = $this->aggregateByCustomer($ordersInPeriod, $from, $to);
        $customersPrev = $this->aggregateByCustomer($ordersPrevPeriod, $prevFrom, $prevTo);

        $khachMoiCount = collect($customers)->where('is_new_in_period', true)->count();
        $khachCoDonTu2Count = collect($customers)->where('is_returning_in_period', true)->count();
        $tongKhachCoDonTrongKy = count($customers);
        $prevReturningCount = collect($customersPrev)->where('is_returning_in_period', true)->count();
        $tangTruongReturning = $prevReturningCount > 0
            ? round((($khachCoDonTu2Count - $prevReturningCount) / $prevReturningCount) * 100, 1)
            : ($khachCoDonTu2Count > 0 ? 100 : 0);

        $doanhThuTuKhachQuayLai = collect($customers)->where('is_returning_in_period', true)->sum('total_revenue');
        $doanhThuPrevReturning = collect($customersPrev)->where('is_returning_in_period', true)->sum('total_revenue');
        $tangTruongDoanhThuReturning = $doanhThuPrevReturning > 0
            ? round((($doanhThuTuKhachQuayLai - $doanhThuPrevReturning) / $doanhThuPrevReturning) * 100, 1)
            : ($doanhThuTuKhachQuayLai > 0 ? 100 : 0);

        $trueRepeatRate = $tongKhachCoDonTrongKy > 0
            ? round(($khachCoDonTu2Count / $tongKhachCoDonTrongKy) * 100, 1)
            : 0;

        $returningCustomers = collect($customers)->where('is_returning_in_period', true)->values();
        $daysToSecondOrder = $returningCustomers->map(fn ($c) => $this->daysToSecondOrder($c))->filter(fn ($d) => $d !== null)->values();
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
            'trueRepeatRate' => $trueRepeatRate,
            'avgTimeToSecondOrder' => $avgTimeToSecondOrder,
            'pctQuayLaiTrong3Ngay' => $pctQuayLaiTrong3Ngay,
            'pctQuayLaiTrong7Ngay' => $pctQuayLaiTrong7Ngay,
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
            $n = count($orderDates);
            $daysDiff = 0;
            if ($n >= 1) {
                $first = Carbon::parse($orderDates[0]);
                $last = Carbon::parse($orderDates[$n - 1]);
                $daysDiff = max(1, $first->diffInDays($last));
            }
            $ordersPerMonth = $orderCount > 0 && $daysDiff > 0 ? round(($orderCount / $daysDiff) * 30, 1) : ($orderCount > 0 ? (float) $orderCount : 0);
            $avgDaysBetween = $n >= 2 ? round($daysDiff / ($n - 1), 0) : null;

            $result[] = [
                'name' => $c['name'],
                'order_count' => $c['order_count'],
                'total_revenue' => $c['total_revenue'],
                'first_order_date' => $c['first_order_date'],
                'last_order_date' => $c['last_order_date'],
                'order_dates' => $c['order_dates'],
                'is_new_in_period' => $isNewInPeriod,
                'is_returning_in_period' => $isReturningInPeriod,
                'is_loyal_in_period' => $isLoyalInPeriod,
                'orders_per_month' => $ordersPerMonth,
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
}
