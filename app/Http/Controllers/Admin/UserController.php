<?php

namespace App\Http\Controllers\Admin;

use App\Services\TaiChinh\TaiChinhViewCache;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\PlanConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'plan' => ['nullable', 'string', Rule::in(array_merge([''], array_keys($plansList)))],
            'plan_expires_at' => ['nullable', 'date'],
        ]);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_admin'] = $request->boolean('is_admin');
        $features = array_values(array_keys($request->input('features', [])));
        $validated['allowed_features'] = $features !== [] ? $features : ['tai_chinh'];
        $validated['plan'] = $request->input('plan') ?: null;
        $validated['plan_expires_at'] = $request->filled('plan_expires_at')
            ? \Carbon\Carbon::parse($request->plan_expires_at)->startOfDay()
            : null;
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
            'plan' => ['nullable', 'string', Rule::in(array_merge([''], array_keys($plansList)))],
            'plan_expires_at' => ['nullable', 'date'],
        ];
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::defaults()];
        }
        $validated = $request->validate($rules);
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }
        $validated['is_admin'] = $request->boolean('is_admin');
        $validated['allowed_features'] = array_values(array_keys($request->input('features', [])));
        $validated['plan'] = $request->input('plan') ?: null;
        $validated['plan_expires_at'] = $request->filled('plan_expires_at')
            ? \Carbon\Carbon::parse($request->plan_expires_at)->startOfDay()
            : null;
        $user->update($validated);
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật user.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể xóa chính mình.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Đã xóa user.');
    }
}
