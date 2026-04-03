<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodBuffLaborPayment;
use App\Models\FoodBuffOrder;
use App\Models\FoodBuffOrderSchedule;
use App\Models\FoodBuffOrderScheduleAcknowledgment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoodBuffController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodThongKeBuff()) {
            abort(403, 'Bạn không có quyền xem thống kê seeding.');
        }

        $from = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::parse('2026-03-01')->startOfDay();
        $to = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->copy()->endOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $branchId = $request->input('food_branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        $isOnlyThongKeBuffUser = $this->isOnlyThongKeBuffUser($user);

        $query = FoodBuffOrder::query()
            ->with('branch')
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

        if (! $isOnlyThongKeBuffUser) {
            $query->where('user_id', $user->id);
        }

        if (! $user->is_admin) {
            $assignedEmployees = $user->getFoodBuffAssignedEmployees();
            if ($assignedEmployees !== []) {
                $normalizedNames = array_map(
                    fn ($name) => mb_strtolower(trim((string) $name)),
                    $assignedEmployees
                );
                $normalizedNames = array_values(array_unique(array_filter($normalizedNames, fn ($name) => $name !== '')));
                if ($normalizedNames !== []) {
                    $query->whereRaw('LOWER(TRIM(customer_name)) IN ('.implode(',', array_fill(0, count($normalizedNames), '?')).')', $normalizedNames);
                }
            }
        }

        if ($branchId) {
            $query->where('food_branch_id', $branchId);
        }

        $orders = $query
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $tongDon = $orders->count();
        $tongBuff = (float) $orders->sum('buff_amount');
        $tongTienCong = (float) $orders->sum('labor_amount');
        $tongChi = $tongBuff + $tongTienCong;

        if ($isOnlyThongKeBuffUser) {
            $branchIds = $orders->pluck('food_branch_id')->filter()->unique()->values();
            $branches = $branchIds->isNotEmpty()
                ? FoodBranch::query()->whereIn('id', $branchIds)->orderBy('name')->get()
                : collect();
        } else {
            $branches = FoodBranch::query()->where('user_id', $user->id)->orderBy('name')->get();
        }

        $payableUsers = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => (object) ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        $paymentHistoryQuery = FoodBuffLaborPayment::query()
            ->with(['paidUser', 'payer', 'creator']);
        if ($isOnlyThongKeBuffUser) {
            $paymentHistoryQuery->where('paid_user_id', $user->id);
        } else {
            $paymentHistoryQuery->where('payer_user_id', $user->id);
        }
        $paymentHistory = $paymentHistoryQuery->orderByDesc('paid_at')->orderByDesc('id')->limit(100)->get();
        $tongDaTra = (float) $paymentHistory->sum('amount');
        $tongConLai = $tongTienCong - $tongDaTra;

        $branchNameById = FoodBranch::query()->pluck('name', 'id')->all();
        $today = Carbon::today();

        $pendingBuffOrderSchedulesForPopup = FoodBuffOrderSchedule::query()
            ->whereDate('schedule_date', $today)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereDoesntHave('acknowledgments', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->get()
            ->map(function (FoodBuffOrderSchedule $sched) use ($branchNameById) {
                $lines = collect($sched->branch_targets ?? [])->map(function (array $row) use ($branchNameById) {
                    $bid = (int) ($row['food_branch_id'] ?? 0);

                    return [
                        'branch_name' => $branchNameById[$bid] ?? ('Chi nhánh #'.$bid),
                        'order_count' => (int) ($row['order_count'] ?? 0),
                    ];
                })->values()->all();

                return [
                    'id' => $sched->id,
                    'date_label' => $sched->schedule_date->format('d/m/Y'),
                    'lines' => $lines,
                ];
            });

        $allScheduleBranches = $user->is_admin
            ? FoodBranch::query()->orderBy('name')->get()
            : collect();

        $scheduleAssignableUsers = $user->is_admin
            ? User::query()
                ->where(function ($q) {
                    $q->where('is_admin', true)
                        ->orWhere('can_manage_food_thong_ke_buff', true);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        $recentScheduleAcknowledgments = $user->is_admin
            ? FoodBuffOrderScheduleAcknowledgment::query()
                ->with(['user:id,name,email', 'schedule'])
                ->orderByDesc('acknowledged_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
            : collect();

        return view('pages.food.thong-ke-buff', [
            'title' => 'Thống kê seeding',
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $branches,
            'orders' => $orders,
            'tongDon' => $tongDon,
            'tongBuff' => $tongBuff,
            'tongTienCong' => $tongTienCong,
            'tongChi' => $tongChi,
            'isOnlyThongKeBuffUser' => $isOnlyThongKeBuffUser,
            'payableUsers' => $payableUsers,
            'paymentHistory' => $paymentHistory,
            'tongDaTra' => $tongDaTra,
            'tongConLai' => $tongConLai,
            'pendingBuffOrderSchedulesForPopup' => $pendingBuffOrderSchedulesForPopup,
            'allScheduleBranches' => $allScheduleBranches,
            'scheduleAssignableUsers' => $scheduleAssignableUsers,
            'recentScheduleAcknowledgments' => $recentScheduleAcknowledgments,
            'isBuffAdmin' => (bool) $user->is_admin,
            'branchNameById' => $branchNameById,
        ]);
    }

    public function storeOrderSchedule(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            abort(403, 'Chỉ quản trị viên mới được tạo lịch đặt đơn.');
        }

        $validated = $request->validate([
            'schedule_date' => ['required', 'date'],
            'assignee_user_ids' => ['required', 'array', 'min:1'],
            'assignee_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.food_branch_id' => ['required', 'integer', Rule::exists('food_branches', 'id')],
            'targets.*.order_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $assigneeIds = array_values(array_unique(array_map('intval', $validated['assignee_user_ids'])));
        $assignees = User::query()->whereIn('id', $assigneeIds)->get();
        foreach ($assignees as $a) {
            if (! $a->is_admin && ! $a->canManageFoodThongKeBuff()) {
                return redirect()->route('food.thong-ke-buff')->with('error', 'Tài khoản '.$a->name.' không có quyền trang thống kê seeding.');
            }
        }

        $targets = collect($validated['targets'])
            ->map(fn (array $t) => [
                'food_branch_id' => (int) $t['food_branch_id'],
                'order_count' => (int) $t['order_count'],
            ])
            ->values()
            ->all();

        $schedule = FoodBuffOrderSchedule::query()->create([
            'schedule_date' => Carbon::parse($validated['schedule_date'])->toDateString(),
            'branch_targets' => $targets,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->assignees()->sync($assigneeIds);

        return redirect()->route('food.thong-ke-buff')->with('success', 'Đã tạo lịch đặt đơn và gửi tới người được chọn.');
    }

    public function acknowledgeOrderSchedules(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer', Rule::exists('food_buff_order_schedules', 'id')],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['schedule_ids'])));
        $schedules = FoodBuffOrderSchedule::query()->whereIn('id', $ids)->get();
        if ($schedules->count() !== count($ids)) {
            return redirect()->route('food.thong-ke-buff')->with('error', 'Không tìm thấy đủ lịch đặt đơn.');
        }

        foreach ($schedules as $schedule) {
            if (! $schedule->schedule_date->isToday()) {
                return redirect()->route('food.thong-ke-buff')->with('error', 'Chỉ xác nhận được lịch trong ngày hôm nay.');
            }
            if (! $schedule->assignees()->where('users.id', $user->id)->exists()) {
                abort(403, 'Bạn không thuộc danh sách nhận một số lịch này.');
            }
        }

        foreach ($schedules as $schedule) {
            FoodBuffOrderScheduleAcknowledgment::query()->firstOrCreate(
                [
                    'food_buff_order_schedule_id' => $schedule->id,
                    'user_id' => $user->id,
                ],
                ['acknowledged_at' => now()]
            );
        }

        return redirect()->route('food.thong-ke-buff')->with('success', 'Đã xác nhận đã nắm lịch đặt đơn.');
    }

    public function storeLaborCashPayment(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodThongKeBuff()) {
            abort(403, 'Bạn không có quyền thanh toán tiền công.');
        }
        if ($this->isOnlyThongKeBuffUser($user)) {
            abort(403, 'Tài khoản này chỉ được xem thống kê.');
        }

        $validated = $request->validate([
            'paid_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (int) round((float) $validated['amount']);
        if ($amount <= 0) {
            return redirect()->route('food.thong-ke-buff')->with('error', 'Số tiền không hợp lệ.');
        }

        FoodBuffLaborPayment::query()->create([
            'payer_user_id' => $user->id,
            'paid_user_id' => (int) $validated['paid_user_id'],
            'amount' => $amount,
            'payment_method' => FoodBuffLaborPayment::METHOD_CASH,
            'note' => $validated['note'] ?? null,
            'paid_at' => now(),
            'created_by_user_id' => $user->id,
        ]);

        return redirect()->route('food.thong-ke-buff')->with('success', 'Đã ghi nhận thanh toán tiền công.');
    }

    public function toggleCustomerReviewed(Request $request, FoodBuffOrder $foodBuffOrder): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Vui lòng đăng nhập.'], 401);
            }

            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin) {
            abort(403, 'Chỉ quản trị viên mới đánh dấu đánh giá.');
        }
        if ((int) $foodBuffOrder->user_id !== (int) $user->id) {
            abort(403, 'Bạn không có quyền thao tác đơn này.');
        }

        $foodBuffOrder->update([
            'customer_reviewed' => ! $foodBuffOrder->customer_reviewed,
        ]);
        $foodBuffOrder->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'customer_reviewed' => (bool) $foodBuffOrder->customer_reviewed,
                'message' => $foodBuffOrder->customer_reviewed
                    ? 'Đã đánh dấu đơn đã được đánh giá.'
                    : 'Đã bỏ đánh dấu đánh giá.',
            ]);
        }

        return redirect()->route(
            'food.thong-ke-buff',
            array_filter(
                $request->only(['from_date', 'to_date', 'food_branch_id']),
                fn ($v) => $v !== null && $v !== ''
            )
        )->with('success', $foodBuffOrder->customer_reviewed ? 'Đã đánh dấu đơn đã được đánh giá.' : 'Đã bỏ đánh dấu đánh giá.');
    }

    public function destroyBuffOrder(Request $request, FoodBuffOrder $foodBuffOrder): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin) {
            abort(403, 'Chỉ quản trị viên mới được xóa đơn.');
        }
        if ((int) $foodBuffOrder->user_id !== (int) $user->id) {
            abort(403, 'Bạn không có quyền xóa đơn này.');
        }

        $code = (string) ($foodBuffOrder->invoice_code ?? '');
        $foodBuffOrder->delete();

        return redirect()->route(
            'food.thong-ke-buff',
            array_filter(
                $request->only(['from_date', 'to_date', 'food_branch_id']),
                fn ($v) => $v !== null && $v !== ''
            )
        )->with('success', 'Đã xóa đơn '.$code.'.');
    }

    private function isOnlyThongKeBuffUser(User $user): bool
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
            && ! $user->canUseFoodEmployee()
            && ! $user->canUseQrChamCong();
    }
}
