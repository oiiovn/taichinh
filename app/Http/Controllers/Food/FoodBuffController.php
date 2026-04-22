<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodBuffIncomeTarget;
use App\Models\FoodBuffLaborPayment;
use App\Models\FoodBuffOrder;
use App\Models\FoodBuffOrderSchedule;
use App\Models\FoodBuffOrderScheduleAcknowledgment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoodBuffController extends Controller
{
    public function datDonPage(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canCreateFoodBuffOrder()) {
            abort(403, 'Bạn không có quyền tạo đơn Food thủ công.');
        }

        $from = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : now()->copy()->startOfDay();
        $to = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->copy()->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $orders = FoodBuffOrder::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $branches = FoodBranch::query()->orderBy('name')->get();
        $customerOptions = $user->getFoodBuffAssignedEmployees();
        $todayDate = now()->toDateString();
        $todaySchedules = FoodBuffOrderSchedule::query()
            ->whereDate('schedule_date', $todayDate)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $quotaMap = [];
        foreach ($todaySchedules as $schedule) {
            foreach (($schedule->branch_targets ?? []) as $target) {
                $branchId = (int) ($target['food_branch_id'] ?? 0);
                $count = max(0, (int) ($target['order_count'] ?? 0));
                if ($branchId <= 0 || $count <= 0) {
                    continue;
                }
                $quotaMap[$branchId] = ($quotaMap[$branchId] ?? 0) + $count;
            }
        }

        $todayScheduleQuotaByBranch = collect($quotaMap)
            ->map(fn ($count, $branchId) => [
                'branch_id' => (int) $branchId,
                'branch_name' => (string) ($branches->firstWhere('id', (int) $branchId)?->name ?? ('Chi nhánh #'.$branchId)),
                'order_count' => (int) $count,
            ])
            ->sortByDesc('order_count')
            ->values();
        $lastForm = $request->session()->get('food_dat_don_last_form', []);
        if (! is_array($lastForm)) {
            $lastForm = [];
        }
        $cooldownUntil = $request->session()->get('food_dat_don_cooldown_until');
        $cooldownRemaining = 0;
        if (is_string($cooldownUntil) && $cooldownUntil !== '') {
            try {
                $remain = now()->diffInSeconds(Carbon::parse($cooldownUntil), false);
                $cooldownRemaining = max(0, (int) $remain);
            } catch (\Throwable $e) {
                $cooldownRemaining = 0;
            }
        }

        return view('pages.food.dat-don', [
            'title' => 'Đặt đơn ShopeeFood',
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'branches' => $branches,
            'defaultProductName' => 'Quán Ship Bù',
            'customerOptions' => $customerOptions,
            'todayScheduleQuotaByBranch' => $todayScheduleQuotaByBranch,
            'lastForm' => $lastForm,
            'cooldownRemaining' => $cooldownRemaining,
        ]);
    }

    public function storeDatDon(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canCreateFoodBuffOrder()) {
            abort(403, 'Bạn không có quyền tạo đơn Food thủ công.');
        }
        $cooldownUntil = $request->session()->get('food_dat_don_cooldown_until');
        if (is_string($cooldownUntil) && $cooldownUntil !== '') {
            try {
                $remain = now()->diffInSeconds(Carbon::parse($cooldownUntil), false);
                if ($remain > 0) {
                    return redirect()->back()->with('success', 'Đã tạo đơn thành công');
                }
            } catch (\Throwable $e) {
                // ignore invalid value
            }
        }

        $customerOptions = $user->getFoodBuffAssignedEmployees();
        if ($customerOptions === []) {
            return redirect()->back()->with('error', 'Chưa có danh sách Shopeefood để chọn. Vui lòng nhờ admin cấu hình.');
        }

        $validated = $request->validate([
            'food_branch_id' => ['required', 'integer', Rule::exists('food_branches', 'id')],
            'order_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', Rule::in($customerOptions)],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'product_name' => ['required', 'string', Rule::in(['Quán Ship Bù'])],
        ]);

        $orderDate = Carbon::parse($validated['order_date'])->toDateString();
        $branchId = (int) $validated['food_branch_id'];
        if (! $user->is_admin) {
            $allowedCount = $this->confirmedOrderQuotaForBranch($user->id, $orderDate, $branchId);
            if ($allowedCount <= 0) {
                return redirect()->back()->with('error', 'Bạn chưa có lịch đặt đơn đã xác nhận cho ngày/chi nhánh này.');
            }
            $createdCount = FoodBuffOrder::query()
                ->where('user_id', $user->id)
                ->whereDate('order_date', $orderDate)
                ->where('food_branch_id', $branchId)
                ->count();
            if ($createdCount >= $allowedCount) {
                return redirect()->back()->with('error', 'Bạn đã tạo đủ số đơn theo lịch đã xác nhận cho ngày/chi nhánh này.');
            }
        }
        $invoiceCode = $this->nextManualInvoiceCode($user->id, $orderDate);

        FoodBuffOrder::query()->create([
            'user_id' => $user->id,
            'food_branch_id' => $branchId,
            'invoice_code' => $invoiceCode,
            'order_date' => $orderDate,
            'order_time_text' => now()->format('H:i:s'),
            'receiver_name' => trim((string) ($validated['receiver_name'] ?? '')) ?: null,
            'customer_name' => trim((string) $validated['customer_name']),
            'product_name' => 'Quán Ship Bù',
            'buff_amount' => 20000,
            'labor_amount' => 10000,
        ]);

        $request->session()->put('food_dat_don_last_form', [
            'food_branch_id' => $branchId,
            'order_date' => $orderDate,
            'customer_name' => trim((string) $validated['customer_name']),
            'labor_amount' => 10000,
            'product_name' => 'Quán Ship Bù',
        ]);
        $request->session()->put('food_dat_don_cooldown_until', now()->addSeconds(30)->toDateTimeString());

        return redirect()->route('food.dat-don')->with('success', 'Đã tạo đơn Food thủ công.');
    }

    public function destroyDatDon(Request $request, FoodBuffOrder $foodBuffOrder): RedirectResponse
    {
        abort(403, 'Không có quyền xóa đơn ở mục này.');
    }

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

        $targetMonthStart = now()->copy()->startOfMonth();
        $targetMonthEnd = now()->copy()->endOfMonth();
        $incomeTarget = FoodBuffIncomeTarget::query()
            ->where('user_id', $user->id)
            ->whereDate('target_month', $targetMonthStart->toDateString())
            ->first();
        $incomeTargetProgress = null;
        if ($incomeTarget && (int) $incomeTarget->target_amount > 0) {
            $targetMonthOrdersQuery = FoodBuffOrder::query()
                ->whereBetween('order_date', [$targetMonthStart->toDateString(), $targetMonthEnd->toDateString()]);
            if (! $isOnlyThongKeBuffUser && ! $user->is_admin) {
                $targetMonthOrdersQuery->where('user_id', $user->id);
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
                        $targetMonthOrdersQuery->whereRaw('LOWER(TRIM(customer_name)) IN ('.implode(',', array_fill(0, count($normalizedNames), '?')).')', $normalizedNames);
                    }
                }
            }
            $actualAmount = (float) $targetMonthOrdersQuery->sum('labor_amount');
            $targetAmount = (float) $incomeTarget->target_amount;
            $percent = $targetAmount > 0 ? min(100, ($actualAmount / $targetAmount) * 100) : 0;
            $incomeTargetProgress = [
                'month_label' => 'Tháng '.$targetMonthStart->format('n/Y'),
                'actual_amount' => $actualAmount,
                'target_amount' => $targetAmount,
                'percent' => $percent,
                'done' => $actualAmount >= $targetAmount,
            ];
        }

        $foodBuffOrderSchedulePopup = $this->foodBuffOrderSchedulePopupData($user);

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
            'incomeTargetProgress' => $incomeTargetProgress,
            'foodBuffOrderSchedulePopup' => $foodBuffOrderSchedulePopup,
        ]);
    }

    public function orderSchedulePage(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin) {
            abort(403, 'Chỉ quản trị viên mới quản lý lịch đặt đơn.');
        }

        $branchNameById = $this->branchNameByIdArray();
        $foodBuffOrderSchedulePopup = $this->foodBuffOrderSchedulePopupData($user);
        $allScheduleBranches = FoodBranch::query()->orderBy('name')->get();
        $scheduleAssignableUsers = User::query()
            ->where(function ($q) {
                $q->where('is_admin', true)
                    ->orWhere('can_manage_food_thong_ke_buff', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $buffSchedulesAdminMonitor = $this->buffSchedulesAdminMonitorCollection($branchNameById);

        return view('pages.food.lich-dat-don', [
            'title' => 'Lịch đặt đơn',
            'branchNameById' => $branchNameById,
            'foodBuffOrderSchedulePopup' => $foodBuffOrderSchedulePopup,
            'allScheduleBranches' => $allScheduleBranches,
            'scheduleAssignableUsers' => $scheduleAssignableUsers,
            'buffSchedulesAdminMonitor' => $buffSchedulesAdminMonitor,
        ]);
    }

    public function confirmedSchedulesPage(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canCreateFoodBuffOrder() && ! $user->canManageFoodThongKeBuff()) {
            abort(403, 'Bạn không có quyền xem lịch đã xác nhận.');
        }

        $from = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : now()->copy()->subDays(30)->startOfDay();
        $to = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->copy()->addDays(30)->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $branchNameById = $this->branchNameByIdArray();
        $schedules = FoodBuffOrderSchedule::query()
            ->with([
                'creator:id,name,is_admin',
                'acknowledgments' => fn ($q) => $q->where('user_id', $user->id),
                'assignees:id,name,email',
            ])
            ->whereDate('schedule_date', '>=', $from->toDateString())
            ->whereDate('schedule_date', '<=', $to->toDateString())
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('schedule_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (FoodBuffOrderSchedule $s) => $this->mapFoodBuffOrderScheduleBlock($s, $branchNameById, $user))
            ->values();

        return view('pages.food.lich-da-xac-nhan', [
            'title' => 'Lịch đã xác nhận',
            'from' => $from,
            'to' => $to,
            'schedules' => $schedules,
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
                return redirect()->back()->with('error', 'Tài khoản '.$a->name.' không có quyền trang thống kê seeding.');
            }
        }

        $targets = collect($validated['targets'])
            ->map(fn (array $t) => [
                'food_branch_id' => (int) $t['food_branch_id'],
                'order_count' => (int) $t['order_count'],
            ])
            ->values()
            ->all();

        $dateStr = Carbon::parse($validated['schedule_date'])->toDateString();
        $existing = $this->findBuffScheduleSameDayAndAssignees($dateStr, $assigneeIds);

        if ($existing) {
            $existing->update([
                'branch_targets' => $targets,
                'created_by_user_id' => $user->id,
            ]);
            $existing->assignees()->sync($assigneeIds);
            FoodBuffOrderScheduleAcknowledgment::query()
                ->where('food_buff_order_schedule_id', $existing->id)
                ->delete();

            return redirect()->back()->with(
                'success',
                'Đã cập nhật lịch đặt đơn (cùng ngày và cùng người nhận). Người nhận cần xác nhận lại.'
            );
        }

        $schedule = FoodBuffOrderSchedule::query()->create([
            'schedule_date' => $dateStr,
            'branch_targets' => $targets,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->assignees()->sync($assigneeIds);

        return redirect()->back()->with('success', 'Đã tạo lịch đặt đơn và gửi tới người được chọn.');
    }

    public function destroyOrderSchedule(Request $request, FoodBuffOrderSchedule $foodBuffOrderSchedule): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            abort(403, 'Chỉ quản trị viên mới được xóa lịch đặt đơn.');
        }

        $dateLabel = $foodBuffOrderSchedule->schedule_date->format('d/m/Y');
        $foodBuffOrderSchedule->delete();

        return redirect()->back()->with('success', 'Đã xóa lịch đặt đơn ngày '.$dateLabel.'.');
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
            return redirect()->back()->with('error', 'Không tìm thấy đủ lịch đặt đơn.');
        }

        $todayStr = now()->toDateString();
        foreach ($schedules as $schedule) {
            if ($schedule->schedule_date->format('Y-m-d') !== $todayStr) {
                return redirect()->back()->with('error', 'Chỉ xác nhận được lịch trong ngày hôm nay.');
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

        return redirect()->back()->with('success', 'Đã xác nhận đã nắm lịch đặt đơn.');
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
            'payment_method' => ['required', Rule::in([FoodBuffLaborPayment::METHOD_CASH, FoodBuffLaborPayment::METHOD_BANK])],
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
            'payment_method' => (string) $validated['payment_method'],
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

    /**
     * @return array<int, string>
     */
    private function branchNameByIdArray(): array
    {
        return FoodBranch::query()->pluck('name', 'id')->all();
    }

    /**
     * @return array{pending_blocks: Collection<int, array>, other_blocks: Collection<int, array>}
     */
    private function foodBuffOrderSchedulePopupData(User $user): array
    {
        $todayStr = now()->toDateString();
        $branchNameById = $this->branchNameByIdArray();
        $with = [
            'assignees:id,name,email',
            'creator:id,name,is_admin',
            'acknowledgments' => fn ($q) => $q->select('id', 'food_buff_order_schedule_id', 'user_id', 'acknowledged_at'),
        ];

        $pendingSchedules = FoodBuffOrderSchedule::query()
            ->with($with)
            ->whereDate('schedule_date', $todayStr)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereDoesntHave('acknowledgments', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->get();

        $otherSchedules = FoodBuffOrderSchedule::query()
            ->with($with)
            ->whereDate('schedule_date', $todayStr)
            ->whereDoesntHave('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('id')
            ->get();

        $mapper = fn (FoodBuffOrderSchedule $s) => $this->mapFoodBuffOrderScheduleBlock($s, $branchNameById, $user);

        return [
            'pending_blocks' => $pendingSchedules->map($mapper)->values(),
            'other_blocks' => $otherSchedules->map($mapper)->values(),
        ];
    }

    /**
     * @param  array<int, string>  $branchNameById
     * @return array{id: int, date_label: string, lines: list<array{branch_name: string, order_count: int}>, assignees: list<array{name: string, email: string, is_me: bool, has_acknowledged: bool, acknowledged_at_label: string|null}>, giver_line: string}
     */
    private function mapFoodBuffOrderScheduleBlock(FoodBuffOrderSchedule $sched, array $branchNameById, User $viewer): array
    {
        $byAck = $sched->acknowledgments->keyBy('user_id');
        $lines = collect($sched->branch_targets ?? [])->map(function (array $row) use ($branchNameById) {
            $bid = (int) ($row['food_branch_id'] ?? 0);

            return [
                'branch_name' => $branchNameById[$bid] ?? ('Chi nhánh #'.$bid),
                'order_count' => (int) ($row['order_count'] ?? 0),
            ];
        })->values()->all();

        $assignees = $sched->assignees->map(function (User $a) use ($byAck, $viewer) {
            $ack = $byAck->get($a->id);

            return [
                'name' => $a->name,
                'email' => $a->email,
                'is_me' => (int) $a->id === (int) $viewer->id,
                'has_acknowledged' => $ack !== null,
                'acknowledged_at_label' => $ack ? $ack->acknowledged_at->format('d/m/Y H:i') : null,
            ];
        })->values()->all();

        $creator = $sched->creator;
        $giverLine = '—';
        if ($creator) {
            $giverLine = $creator->is_admin
                ? 'Quản lý: Hạnh Nhân'
                : 'Người tạo: '.$creator->name;
        }

        return [
            'id' => $sched->id,
            'date_label' => $sched->schedule_date->format('d/m/Y'),
            'lines' => $lines,
            'assignees' => $assignees,
            'giver_line' => $giverLine,
        ];
    }

    /**
     * @param  array<int, string>  $branchNameById
     */
    private function buffSchedulesAdminMonitorCollection(array $branchNameById): Collection
    {
        $todayStrMonitor = now()->toDateString();

        return FoodBuffOrderSchedule::query()
            ->with(['assignees:id,name,email', 'acknowledgments'])
            ->whereDate('schedule_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('schedule_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (FoodBuffOrderSchedule $s) use ($branchNameById, $todayStrMonitor) {
                $summary = collect($s->branch_targets ?? [])->map(function (array $row) use ($branchNameById) {
                    $bid = (int) ($row['food_branch_id'] ?? 0);
                    $bn = $branchNameById[$bid] ?? ('#'.$bid);
                    $n = (int) ($row['order_count'] ?? 0);

                    return $bn.': '.$n.' đơn';
                })->implode('; ');
                $byUserAck = $s->acknowledgments->keyBy('user_id');
                $dayStr = $s->schedule_date->format('Y-m-d');
                $assigneeRows = $s->assignees->map(function (User $assignee) use ($byUserAck, $dayStr, $todayStrMonitor) {
                    $ack = $byUserAck->get($assignee->id);
                    if ($ack) {
                        $status = 'done';
                        $statusLabel = $ack->acknowledged_at->format('d/m/Y H:i');
                    } elseif ($dayStr > $todayStrMonitor) {
                        $status = 'future';
                        $statusLabel = 'Chờ đến ngày lịch';
                    } else {
                        $status = 'pending';
                        $statusLabel = 'Chưa xác nhận';
                    }

                    return [
                        'name' => $assignee->name,
                        'email' => $assignee->email,
                        'status' => $status,
                        'status_label' => $statusLabel,
                    ];
                });

                return [
                    'id' => $s->id,
                    'date_label' => $s->schedule_date->format('d/m/Y'),
                    'summary' => $summary,
                    'assignees' => $assigneeRows,
                    'has_pending' => $assigneeRows->contains(fn (array $r) => $r['status'] === 'pending'),
                ];
            });
    }

    /**
     * @param  array<int>  $assigneeIds
     */
    private function findBuffScheduleSameDayAndAssignees(string $dateStr, array $assigneeIds): ?FoodBuffOrderSchedule
    {
        $want = $this->normalizedAssigneeIdList($assigneeIds);
        $candidates = FoodBuffOrderSchedule::query()
            ->whereDate('schedule_date', $dateStr)
            ->get();

        foreach ($candidates as $candidate) {
            $have = $this->normalizedAssigneeIdList($candidate->assignees()->pluck('id')->all());
            if ($have === $want) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    private function normalizedAssigneeIdList(array $ids): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    private function nextManualInvoiceCode(int $userId, string $orderDate): string
    {
        $maxCurrent = FoodBuffOrder::query()
            ->where('user_id', $userId)
            ->where('invoice_code', 'like', 'HDS_____')
            ->pluck('invoice_code')
            ->map(function (string $code): int {
                if (preg_match('/^HDS(\d{5})$/', $code, $m)) {
                    return (int) $m[1];
                }

                return 0;
            })
            ->max() ?? 0;

        for ($i = 1; $i <= 99999; $i++) {
            $next = ($maxCurrent + $i) % 100000;
            $candidate = 'HDS'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = FoodBuffOrder::query()
                ->where('user_id', $userId)
                ->whereDate('order_date', $orderDate)
                ->where('invoice_code', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        return 'HDS'.now()->format('His');
    }

    private function confirmedOrderQuotaForBranch(int $userId, string $orderDate, int $branchId): int
    {
        $schedules = FoodBuffOrderSchedule::query()
            ->whereDate('schedule_date', $orderDate)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
            ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $total = 0;
        foreach ($schedules as $schedule) {
            foreach (($schedule->branch_targets ?? []) as $target) {
                if ((int) ($target['food_branch_id'] ?? 0) === $branchId) {
                    $total += max(0, (int) ($target['order_count'] ?? 0));
                }
            }
        }

        return $total;
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
