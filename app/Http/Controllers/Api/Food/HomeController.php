<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\FoodBuffOrder;
use App\Services\Food\FoodNavService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang chủ app mobile — dữ liệu thật theo quyền user.
 */
class HomeController extends Controller
{
    public function __construct(
        protected FoodNavService $navService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $menuItems = $this->navService->itemsFor($user);
        $menuIds = collect($menuItems)->pluck('id')->all();

        $canDatDon = in_array('dat-don', $menuIds, true);
        $canThongKeBuff = in_array('thong-ke-buff', $menuIds, true);
        $canOrders = $canDatDon || $canThongKeBuff;

        $quickActions = $this->buildQuickActions($menuIds);
        $recentOrders = $canOrders ? $this->recentBuffOrders($user) : [];
        $summary = $canOrders ? $this->orderSummary($user) : null;

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
            'unread_notifications' => $this->unreadNotificationsCount($user),
            'quick_actions' => $quickActions,
            'summary' => $summary,
            'recent_orders' => $recentOrders,
            'recent_orders_title' => $canThongKeBuff && ! $canDatDon
                ? 'Đơn seeding gần đây'
                : 'Đơn hàng gần đây',
            'can_view_orders' => $canOrders,
        ]);
    }

    private function unreadNotificationsCount($user): int
    {
        $laravelUnread = $user->unreadNotifications()->count();
        $broadcastUnread = Broadcast::forUser($user)
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('users', fn ($q2) => $q2->where('user_id', $user->id))
                    ->orWhereHas('users', fn ($q2) => $q2->where('user_id', $user->id)->whereNull('broadcast_user.read_at'));
            })
            ->count();

        return $laravelUnread + $broadcastUnread;
    }

    /**
     * @param  list<string>  $menuIds
     * @return list<array{id: string, label: string, icon: string, route_id: string}>
     */
    private function buildQuickActions(array $menuIds): array
    {
        $map = [
            [
                'id' => 'orders',
                'label' => 'Đơn hàng',
                'icon' => 'order',
                'candidates' => ['dat-don', 'lich-da-xac-nhan', 'thong-ke-buff'],
            ],
            [
                'id' => 'products',
                'label' => 'Sản phẩm',
                'icon' => 'box',
                'candidates' => ['san-pham', 'nguyen-lieu', 'cong-thuc'],
            ],
            [
                'id' => 'stats',
                'label' => 'Thống kê',
                'icon' => 'stats',
                'candidates' => ['thong-ke-buff', 'doanh-so', 'bao-cao-ban-hang', 'tong-quan'],
            ],
            [
                'id' => 'reviews',
                'label' => 'Đánh giá',
                'icon' => 'star',
                'candidates' => ['food-reviews'],
            ],
            [
                'id' => 'attendance',
                'label' => 'Chấm công',
                'icon' => 'fingerprint',
                'candidates' => ['cham-cong', 'qr-cham-cong'],
            ],
        ];

        $actions = [];
        foreach ($map as $item) {
            foreach ($item['candidates'] as $candidate) {
                if (in_array($candidate, $menuIds, true)) {
                    $actions[] = [
                        'id' => $item['id'],
                        'label' => $item['label'],
                        'icon' => $item['icon'],
                        'route_id' => $candidate,
                    ];
                    break;
                }
            }
        }

        return array_slice($actions, 0, 4);
    }

    /**
     * Phạm vi đơn giống FoodBuffController::thongKe (web).
     */
    private function scopedBuffOrdersQuery($user)
    {
        $query = FoodBuffOrder::query()->with('branch');
        $isOnlyThongKeBuffUser = $this->isOnlyThongKeBuffUser($user);

        if (! $isOnlyThongKeBuffUser && ! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        if (! $user->is_admin) {
            $assignedEmployees = $user->getFoodBuffAssignedEmployees();
            if ($assignedEmployees !== []) {
                $normalizedNames = array_map(
                    fn ($name) => mb_strtolower(trim((string) $name)),
                    $assignedEmployees
                );
                $normalizedNames = array_values(array_unique(array_filter(
                    $normalizedNames,
                    fn ($name) => $name !== ''
                )));
                if ($normalizedNames !== []) {
                    $query->whereRaw(
                        'LOWER(TRIM(customer_name)) IN ('.implode(',', array_fill(0, count($normalizedNames), '?')).')',
                        $normalizedNames
                    );
                }
            }
        }

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentBuffOrders($user): array
    {
        $orders = $this->scopedBuffOrdersQuery($user)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $orders->map(function (FoodBuffOrder $order) {
            $reviewed = (bool) $order->customer_reviewed;
            $buff = (float) ($order->buff_amount ?? 0);
            $labor = (float) ($order->labor_amount ?? 0);
            $total = $buff + $labor;
            $product = trim((string) ($order->product_name ?: 'Đơn seeding'));
            $branch = $order->branch?->name;
            $customer = trim((string) ($order->customer_name ?: $order->receiver_name ?: ''));

            return [
                'id' => $order->id,
                'code' => (string) ($order->invoice_code ?: ('#'.$order->id)),
                'title' => $product,
                'subtitle' => trim(implode(' · ', array_filter([$branch, $customer !== '' ? $customer : null]))),
                'status' => $reviewed ? 'Đã đánh giá' : 'Chưa đánh giá',
                'status_key' => $reviewed ? 'done' : 'pending',
                'amount' => $total,
                'amount_formatted' => number_format($total, 0, ',', '.').'đ',
                'order_date' => $order->order_date?->toDateString(),
                'order_date_label' => $order->order_date?->format('d/m/Y'),
                'order_time' => $order->order_time_text,
            ];
        })->values()->all();
    }

    private function orderSummary($user): array
    {
        $today = now()->toDateString();
        $monthStart = now()->copy()->startOfMonth()->toDateString();
        $monthEnd = now()->copy()->endOfMonth()->toDateString();
        $base = $this->scopedBuffOrdersQuery($user);

        $todayCount = (clone $base)->whereDate('order_date', $today)->count();
        $todayBuff = (float) (clone $base)->whereDate('order_date', $today)->sum('buff_amount');
        $monthCount = (clone $base)->whereBetween('order_date', [$monthStart, $monthEnd])->count();
        $monthBuff = (float) (clone $base)->whereBetween('order_date', [$monthStart, $monthEnd])->sum('buff_amount');

        return [
            'orders_today' => $todayCount,
            'buff_today' => $todayBuff,
            'buff_today_formatted' => number_format($todayBuff, 0, ',', '.').'đ',
            'orders_month' => $monthCount,
            'buff_month' => $monthBuff,
            'buff_month_formatted' => number_format($monthBuff, 0, ',', '.').'đ',
            'today_label' => Carbon::parse($today)->format('d/m/Y'),
            'month_label' => 'tháng '.(int) now()->format('n').' năm '.now()->format('Y'),
        ];
    }

    private function isOnlyThongKeBuffUser($user): bool
    {
        return ! $user->is_admin
            && $user->canManageFoodThongKeBuff()
            && ! $user->canManageFoodTongQuan()
            && ! $user->canManageFoodDoanhSo()
            && ! $user->canManageFoodSanPham()
            && ! $user->canManageFoodBaoCao()
            && ! $user->canManageFoodEmployees()
            && ! $user->canManageFoodChamCong()
            && ! $user->canManageFoodXinNghi()
            && ! $user->canManageFoodUngLuong()
            && ! $user->canManageFoodLuong()
            && ! $user->canUseQrChamCong();
    }
}
