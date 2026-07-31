<?php

namespace App\Http\Controllers\Admin;

use App\Services\TaiChinh\TaiChinhViewCache;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\PlanConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->orderBy('id');
        $filter = $request->query('filter');
        $now = Carbon::now();
        if ($filter === 'expiring') {
            $query->whereNotNull('plan')
                ->whereNotNull('plan_expires_at')
                ->where('plan_expires_at', '>', $now)
                ->where('plan_expires_at', '<=', $now->copy()->addDays(7)->endOfDay());
        } elseif ($filter === 'expired') {
            $query->whereNotNull('plan')
                ->whereNotNull('plan_expires_at')
                ->where('plan_expires_at', '<', $now);
        }
        $users = $query->paginate(15)->withQueryString();
        return view('pages.admin.users.index', [
            'title' => 'Quản lý user',
            'users' => $users,
            'plansList' => PlanConfig::getList(),
            'filter' => $filter,
        ]);
    }

    public function create()
    {
        return view('pages.admin.users.create', [
            'title' => 'Thêm user',
            'featureList' => config('features.list', []),
            'plansList' => PlanConfig::getList(),
        ]);
    }

    public function store(Request $request)
    {
        $plansList = PlanConfig::getList();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['boolean'],
            'can_manage_food_employees' => ['boolean'],
            'can_manage_food_cham_cong' => ['boolean'],
            'can_manage_food_xin_nghi' => ['boolean'],
            'can_manage_food_ung_luong' => ['boolean'],
            'can_manage_food_luong' => ['boolean'],
            'can_record_food_salary_payment' => ['boolean'],
            'can_manage_food_tong_quan' => ['boolean'],
            'can_manage_food_doanh_so' => ['boolean'],
            'can_manage_food_san_pham' => ['boolean'],
            'can_manage_food_bao_cao' => ['boolean'],
            'can_manage_food_thong_ke_buff' => ['boolean'],
            'can_create_food_buff_order' => ['boolean'],
            'can_manage_food_reviews' => ['boolean'],
            'food_buff_assigned_employees' => ['nullable', 'string'],
            'can_use_food_employee' => ['boolean'],
            'can_use_qr_cham_cong' => ['boolean'],
            'plan' => ['nullable', 'string', Rule::in(array_merge([''], array_keys($plansList)))],
            'plan_expires_at' => ['nullable', 'date'],
        ]);
        $plainForStorage = mb_strtolower($validated['password'], 'UTF-8');
        $validated['password'] = Hash::make($validated['password']);
        $validated['password_plain'] = $plainForStorage;
        $validated['is_admin'] = $request->boolean('is_admin');
        $validated['can_manage_food_employees'] = $request->boolean('can_manage_food_employees');
        $validated['can_manage_food_cham_cong'] = $request->boolean('can_manage_food_cham_cong');
        $validated['can_manage_food_xin_nghi'] = $request->boolean('can_manage_food_xin_nghi');
        $validated['can_manage_food_ung_luong'] = $request->boolean('can_manage_food_ung_luong');
        $validated['can_manage_food_luong'] = $request->boolean('can_manage_food_luong');
        $validated['can_record_food_salary_payment'] = $request->boolean('can_record_food_salary_payment');
        $validated['can_manage_food_tong_quan'] = $request->boolean('can_manage_food_tong_quan');
        $validated['can_manage_food_doanh_so'] = $request->boolean('can_manage_food_doanh_so');
        $validated['can_manage_food_san_pham'] = $request->boolean('can_manage_food_san_pham');
        $validated['can_manage_food_bao_cao'] = $request->boolean('can_manage_food_bao_cao');
        $validated['can_manage_food_thong_ke_buff'] = $request->boolean('can_manage_food_thong_ke_buff');
        $validated['can_create_food_buff_order'] = $request->boolean('can_create_food_buff_order');
        $validated['can_manage_food_reviews'] = $request->boolean('can_manage_food_reviews');
        $validated['food_buff_assigned_employees'] = $this->parseFoodBuffAssignedEmployees($request->input('food_buff_assigned_employees'));
        $validated['can_use_food_employee'] = $request->boolean('can_use_food_employee');
        $validated['can_use_qr_cham_cong'] = $request->boolean('can_use_qr_cham_cong');
        $features = array_values(array_keys($request->input('features', [])));
        $validated['allowed_features'] = $features !== [] ? $features : ['tai_chinh'];
        $validated = $this->syncAllowedFeaturesWithFoodPermissions($validated);
        $validated['plan'] = $request->input('plan') ?: null;
        $validated['plan_expires_at'] = $request->filled('plan_expires_at')
            ? \Carbon\Carbon::parse($request->plan_expires_at)->startOfDay()
            : null;
        $validated = $this->filterValidatedForUserTable($validated);
        User::create($validated);
        return redirect()->route('admin.users.index')->with('success', 'Đã thêm user.');
    }

    public function edit(User $user)
    {
        return view('pages.admin.users.edit', [
            'title' => 'Sửa user',
            'user' => $user,
            'featureList' => config('features.list', []),
            'plansList' => PlanConfig::getList(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $plansList = PlanConfig::getList();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'is_admin' => ['boolean'],
            'can_manage_food_employees' => ['boolean'],
            'can_manage_food_cham_cong' => ['boolean'],
            'can_manage_food_xin_nghi' => ['boolean'],
            'can_manage_food_ung_luong' => ['boolean'],
            'can_manage_food_luong' => ['boolean'],
            'can_record_food_salary_payment' => ['boolean'],
            'can_manage_food_tong_quan' => ['boolean'],
            'can_manage_food_doanh_so' => ['boolean'],
            'can_manage_food_san_pham' => ['boolean'],
            'can_manage_food_bao_cao' => ['boolean'],
            'can_manage_food_thong_ke_buff' => ['boolean'],
            'can_create_food_buff_order' => ['boolean'],
            'can_manage_food_reviews' => ['boolean'],
            'food_buff_assigned_employees' => ['nullable', 'string'],
            'can_use_food_employee' => ['boolean'],
            'can_use_qr_cham_cong' => ['boolean'],
            'plan' => ['nullable', 'string', Rule::in(array_merge([''], array_keys($plansList)))],
            'plan_expires_at' => ['nullable', 'date'],
        ];
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::defaults()];
        }
        $validated = $request->validate($rules);
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
            $validated['password_plain'] = mb_strtolower($request->password, 'UTF-8');
        }
        $validated['is_admin'] = $request->boolean('is_admin');
        $validated['can_manage_food_employees'] = $request->boolean('can_manage_food_employees');
        $validated['can_manage_food_cham_cong'] = $request->boolean('can_manage_food_cham_cong');
        $validated['can_manage_food_xin_nghi'] = $request->boolean('can_manage_food_xin_nghi');
        $validated['can_manage_food_ung_luong'] = $request->boolean('can_manage_food_ung_luong');
        $validated['can_manage_food_luong'] = $request->boolean('can_manage_food_luong');
        $validated['can_record_food_salary_payment'] = $request->boolean('can_record_food_salary_payment');
        $validated['can_manage_food_tong_quan'] = $request->boolean('can_manage_food_tong_quan');
        $validated['can_manage_food_doanh_so'] = $request->boolean('can_manage_food_doanh_so');
        $validated['can_manage_food_san_pham'] = $request->boolean('can_manage_food_san_pham');
        $validated['can_manage_food_bao_cao'] = $request->boolean('can_manage_food_bao_cao');
        $validated['can_manage_food_thong_ke_buff'] = $request->boolean('can_manage_food_thong_ke_buff');
        $validated['can_create_food_buff_order'] = $request->boolean('can_create_food_buff_order');
        $validated['can_manage_food_reviews'] = $request->boolean('can_manage_food_reviews');
        $validated['food_buff_assigned_employees'] = $this->parseFoodBuffAssignedEmployees($request->input('food_buff_assigned_employees'));
        $validated['can_use_food_employee'] = $request->boolean('can_use_food_employee');
        $validated['can_use_qr_cham_cong'] = $request->boolean('can_use_qr_cham_cong');
        $validated['allowed_features'] = array_values(array_keys($request->input('features', [])));
        $validated = $this->syncAllowedFeaturesWithFoodPermissions($validated);
        $validated['plan'] = $request->input('plan') ?: null;
        $validated['plan_expires_at'] = $request->filled('plan_expires_at')
            ? \Carbon\Carbon::parse($request->plan_expires_at)->startOfDay()
            : null;
        $validated = $this->filterValidatedForUserTable($validated);
        $user->update($validated);
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật user.');
    }

    /** Chỉ giữ lại các key tồn tại trên bảng users để tránh lỗi khi migration chưa chạy. */
    private function filterValidatedForUserTable(array $validated): array
    {
        $optionalColumns = [
            'can_manage_food_tong_quan',
            'can_manage_food_doanh_so',
            'can_manage_food_san_pham',
            'can_manage_food_bao_cao',
            'can_manage_food_thong_ke_buff',
            'can_create_food_buff_order',
            'can_manage_food_reviews',
            'food_buff_assigned_employees',
            'can_record_food_salary_payment',
        ];
        foreach ($optionalColumns as $col) {
            if (array_key_exists($col, $validated) && ! Schema::hasColumn('users', $col)) {
                unset($validated[$col]);
            }
        }
        return $validated;
    }

    /** Tự bật tính năng Food khi user được cấp quyền Food (kể cả chỉ đánh giá). */
    private function syncAllowedFeaturesWithFoodPermissions(array $validated): array
    {
        $needsFood = ($validated['is_admin'] ?? false)
            || ($validated['can_manage_food_tong_quan'] ?? false)
            || ($validated['can_manage_food_doanh_so'] ?? false)
            || ($validated['can_manage_food_san_pham'] ?? false)
            || ($validated['can_manage_food_bao_cao'] ?? false)
            || ($validated['can_manage_food_thong_ke_buff'] ?? false)
            || ($validated['can_create_food_buff_order'] ?? false)
            || ($validated['can_manage_food_reviews'] ?? false)
            || ($validated['can_manage_food_employees'] ?? false)
            || ($validated['can_manage_food_cham_cong'] ?? false)
            || ($validated['can_manage_food_xin_nghi'] ?? false)
            || ($validated['can_manage_food_ung_luong'] ?? false)
            || ($validated['can_manage_food_luong'] ?? false)
            || ($validated['can_record_food_salary_payment'] ?? false)
            || ($validated['can_use_food_employee'] ?? false)
            || ($validated['can_use_qr_cham_cong'] ?? false);

        if (! $needsFood) {
            return $validated;
        }

        $features = $validated['allowed_features'] ?? [];
        if (! in_array('food', $features, true)) {
            $features[] = 'food';
        }
        $validated['allowed_features'] = array_values($features);

        return $validated;
    }

    private function parseFoodBuffAssignedEmployees(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;]+/u', $value) ?: [];
        $names = [];
        foreach ($parts as $item) {
            $name = trim((string) $item);
            if ($name === '') {
                continue;
            }
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    public function destroy(User $user)
    {
        $authUserId = Auth::id();
        if ($authUserId !== null && $user->id === $authUserId) {
            return back()->with('error', 'Không thể xóa chính mình.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Đã xóa user.');
    }
}
