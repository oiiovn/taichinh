<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodBuffLaborPayment;
use App\Models\FoodBuffOrder;
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
            : now()->startOfMonth();
        $to = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->endOfMonth();

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
        ]);
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
