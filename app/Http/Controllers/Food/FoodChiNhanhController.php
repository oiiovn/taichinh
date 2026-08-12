<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodMaterial;
use App\Models\FoodMaterialStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoodChiNhanhController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403, 'Bạn không có quyền quản lý chi nhánh.');
        }

        $branches = FoodBranch::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        return view('pages.food.chi-nhanh', [
            'title' => 'Chi nhánh',
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'branch_link' => ['nullable', 'url', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'include_labor_in_settlement' => ['sometimes', 'boolean'],
        ]);

        $branch = FoodBranch::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'branch_link' => $validated['branch_link'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'check_in_radius_meters' => $validated['check_in_radius_meters'] ?? 100,
            'include_labor_in_settlement' => $request->boolean('include_labor_in_settlement', true),
        ]);

        $materialIds = FoodMaterial::query()->where('user_id', $user->id)->pluck('id');
        foreach ($materialIds as $mid) {
            FoodMaterialStock::forMaterialBranch((int) $mid, (int) $branch->id);
        }

        return redirect()->route('food.chi-nhanh')->with('success', 'Đã thêm chi nhánh.');
    }

    public function update(Request $request, FoodBranch $branch): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $branch->user_id !== (int) $user->id) {
            abort(403);
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'branch_link' => ['nullable', 'url', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'include_labor_in_settlement' => ['sometimes', 'boolean'],
        ]);

        $branch->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'branch_link' => $validated['branch_link'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'check_in_radius_meters' => $validated['check_in_radius_meters'] ?? 100,
            'include_labor_in_settlement' => $request->boolean('include_labor_in_settlement'),
        ]);
        return redirect()->route('food.chi-nhanh')->with('success', 'Đã cập nhật chi nhánh.');
    }

    public function destroy(Request $request, FoodBranch $branch): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $branch->user_id !== (int) $user->id) {
            abort(403);
        }
        if (! $user->is_admin && ! $user->canManageFoodBaoCao()) {
            abort(403);
        }

        $branch->delete();

        return redirect()->route('food.chi-nhanh')->with('success', 'Đã xóa chi nhánh. Các báo cáo gắn chi nhánh này sẽ không còn gắn chi nhánh.');
    }
}
