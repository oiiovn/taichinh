<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodBuffOrder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoodBuffController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403, 'Bạn không có quyền xem thống kê Buff.');
        }

        $from = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->endOfMonth();

        $branchId = $request->input('food_branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        $query = FoodBuffOrder::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

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

        $branches = FoodBranch::query()->where('user_id', $user->id)->orderBy('name')->get();

        return view('pages.food.thong-ke-buff', [
            'title' => 'Thống kê Buff',
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $branches,
            'orders' => $orders,
            'tongDon' => $tongDon,
            'tongBuff' => $tongBuff,
            'tongTienCong' => $tongTienCong,
            'tongChi' => $tongChi,
        ]);
    }
}
