<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NhanVienController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodEmployees()) {
            abort(403);
        }
        $employees = Employee::query()->with('user')->where('active', true)->orderBy('id')->get();

        return view('pages.food.nhan-vien.index', [
            'title' => 'Quản lý nhân viên',
            'employees' => $employees,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        if (! $user?->canManageFoodEmployees()) {
            abort(403);
        }
        $existingIds = Employee::query()->pluck('user_id')->all();
        $users = User::query()->whereNotIn('id', $existingIds)->orderBy('name')->get();

        return view('pages.food.nhan-vien.create', [
            'title' => 'Thêm nhân viên',
            'users' => $users,
            'salaryTypeLabels' => Employee::salaryTypeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->canManageFoodEmployees()) {
            abort(403);
        }
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:employees,user_id'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary_type' => ['required', 'string', 'in:hour,day,month'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
        ]);
        $validated['active'] = true;
        Employee::create($validated);

        return redirect()->route('food.nhan-vien')->with('success', 'Đã thêm nhân viên.');
    }

    public function edit(Request $request, Employee $nhanVien): View
    {
        if (! $request->user()?->canManageFoodEmployees()) {
            abort(403);
        }
        $nhanVien->load('user');

        return view('pages.food.nhan-vien.edit', [
            'title' => 'Sửa nhân viên',
            'employee' => $nhanVien,
            'salaryTypeLabels' => Employee::salaryTypeLabels(),
        ]);
    }

    public function update(Request $request, Employee $nhanVien): RedirectResponse
    {
        if (! $request->user()?->canManageFoodEmployees()) {
            abort(403);
        }
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:255'],
            'salary_type' => ['required', 'string', 'in:hour,day,month'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'active' => ['boolean'],
        ]);
        $validated['active'] = $request->boolean('active');
        $nhanVien->update($validated);

        return redirect()->route('food.nhan-vien')->with('success', 'Đã cập nhật nhân viên.');
    }

    public function destroy(Request $request, Employee $nhanVien): RedirectResponse
    {
        if (! $request->user()?->canManageFoodEmployees()) {
            abort(403);
        }
        $nhanVien->update(['active' => false]);

        return redirect()->route('food.nhan-vien')->with('success', 'Đã ngừng hoạt động nhân viên.');
    }
}
