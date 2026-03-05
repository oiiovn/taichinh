<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodReportBonusTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FoodReportBonusTierController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'min_total_cost' => ['required', 'numeric', 'min:0'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
        ], [
            'min_total_cost.required' => 'Nhập chỉ tiêu tổng vốn (VNĐ).',
            'bonus_amount.required' => 'Nhập mức thưởng (VNĐ).',
        ]);

        FoodReportBonusTier::create([
            'min_total_cost' => (int) round((float) $v['min_total_cost']),
            'bonus_amount' => (int) round((float) $v['bonus_amount']),
            'sort_order' => FoodReportBonusTier::max('sort_order') + 1,
        ]);

        return redirect()->route('admin.he-thong')->with('success', 'Đã thêm mức thưởng.');
    }

    public function update(Request $request, FoodReportBonusTier $bonusTier): RedirectResponse
    {
        $v = $request->validate([
            'min_total_cost' => ['required', 'numeric', 'min:0'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $bonusTier->update([
            'min_total_cost' => (int) round((float) $v['min_total_cost']),
            'bonus_amount' => (int) round((float) $v['bonus_amount']),
        ]);

        return redirect()->route('admin.he-thong')->with('success', 'Đã cập nhật mức thưởng.');
    }

    public function destroy(FoodReportBonusTier $bonusTier): RedirectResponse
    {
        $bonusTier->delete();

        return redirect()->route('admin.he-thong')->with('success', 'Đã xóa mức thưởng.');
    }
}
