<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryRate;
use App\Models\FoodBranch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NhanVienController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodEmployees()) {
            abort(403);
        }
        $employees = Employee::query()->with(['user', 'foodBranches'])->where('active', true)->orderBy('id')->get();

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
            'foodBranches' => $this->manageableBranches($user),
            'selectedBranchIds' => old('food_branch_ids', []),
            'primaryBranchId' => old('primary_food_branch_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->canManageFoodEmployees()) {
            abort(403);
        }
        $allowedIds = $this->manageableBranches($user)->pluck('id')->all();
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:employees,user_id'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary_type' => ['required', 'string', 'in:hour,day,month'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'apply_late_penalty' => ['boolean'],
            'shift_start_time' => ['nullable', 'date_format:H:i'],
            'food_branch_ids' => ['nullable', 'array'],
            'food_branch_ids.*' => ['integer', Rule::in($allowedIds)],
            'primary_food_branch_id' => ['nullable', 'integer', Rule::in($allowedIds)],
        ]);
        $validated['active'] = true;
        $validated['apply_late_penalty'] = $request->boolean('apply_late_penalty');
        if ($validated['apply_late_penalty']) {
            $request->validate([
                'shift_start_time' => ['required', 'date_format:H:i'],
            ]);
            $validated['shift_start_time'] = $request->input('shift_start_time');
        } else {
            $validated['shift_start_time'] = null;
        }
        $employee = Employee::create(collect($validated)->except(['food_branch_ids', 'primary_food_branch_id'])->all());
        EmployeeSalaryRate::query()->create([
            'employee_id' => $employee->id,
            'effective_from' => $employee->start_date?->toDateString() ?? now()->toDateString(),
            'salary_rate' => $employee->salary_rate,
            'salary_type' => $employee->salary_type,
        ]);
        $this->syncFoodBranches(
            $employee,
            $request->input('food_branch_ids', []),
            $request->input('primary_food_branch_id'),
            $allowedIds
        );

        return redirect()->route('food.nhan-vien')->with('success', 'Đã thêm nhân viên.');
    }

    public function edit(Request $request, Employee $nhanVien): View
    {
        $user = $request->user();
        if (! $user?->canManageFoodEmployees()) {
            abort(403);
        }
        $nhanVien->load(['user', 'foodBranches']);
        $selected = old('food_branch_ids', $nhanVien->foodBranches->pluck('id')->all());
        $primary = old(
            'primary_food_branch_id',
            $nhanVien->foodBranches->firstWhere('pivot.is_primary', true)?->id
                ?? $nhanVien->foodBranches->first()?->id
        );

        return view('pages.food.nhan-vien.edit', [
            'title' => 'Sửa nhân viên',
            'employee' => $nhanVien,
            'salaryTypeLabels' => Employee::salaryTypeLabels(),
            'foodBranches' => $this->manageableBranches($user),
            'selectedBranchIds' => $selected,
            'primaryBranchId' => $primary,
        ]);
    }

    public function update(Request $request, Employee $nhanVien): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->canManageFoodEmployees()) {
            abort(403);
        }
        $allowedIds = $this->manageableBranches($user)->pluck('id')->all();
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:255'],
            'salary_type' => ['required', 'string', 'in:hour,day,month'],
            'salary_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'active' => ['boolean'],
            'salary_effective_from' => ['nullable', 'date'],
            'apply_late_penalty' => ['boolean'],
            'shift_start_time' => ['nullable', 'date_format:H:i'],
            'food_branch_ids' => ['nullable', 'array'],
            'food_branch_ids.*' => ['integer', Rule::in($allowedIds)],
            'primary_food_branch_id' => ['nullable', 'integer', Rule::in($allowedIds)],
        ]);
        $validated['active'] = $request->boolean('active');
        $validated['apply_late_penalty'] = $request->boolean('apply_late_penalty');
        if ($validated['apply_late_penalty']) {
            $request->validate([
                'shift_start_time' => ['required', 'date_format:H:i'],
            ]);
            $validated['shift_start_time'] = $request->input('shift_start_time');
        } else {
            $validated['shift_start_time'] = null;
        }

        $oldRate = round((float) $nhanVien->salary_rate, 2);
        $oldType = (string) $nhanVien->salary_type;
        $newRate = round((float) $validated['salary_rate'], 2);
        $newType = (string) $validated['salary_type'];
        $salaryChanged = $newRate !== $oldRate || $newType !== $oldType;

        if ($salaryChanged) {
            $request->validate([
                'salary_effective_from' => ['required', 'date'],
            ]);
        }

        $eff = $salaryChanged ? Carbon::parse((string) $request->input('salary_effective_from'))->toDateString() : null;

        if ($salaryChanged && $eff !== null) {
            $baselineStr = $nhanVien->start_date?->toDateString()
                ?? $nhanVien->created_at?->toDateString()
                ?? $eff;
            if ($eff > $baselineStr) {
                EmployeeSalaryRate::query()->updateOrCreate(
                    [
                        'employee_id' => $nhanVien->id,
                        'effective_from' => $baselineStr,
                    ],
                    [
                        'salary_rate' => $oldRate,
                        'salary_type' => $oldType,
                    ]
                );
            }
        }

        $nhanVien->update(collect($validated)->except(['food_branch_ids', 'primary_food_branch_id', 'salary_effective_from'])->all());

        if ($salaryChanged && $eff !== null) {
            EmployeeSalaryRate::query()->updateOrCreate(
                [
                    'employee_id' => $nhanVien->id,
                    'effective_from' => $eff,
                ],
                [
                    'salary_rate' => (float) $validated['salary_rate'],
                    'salary_type' => $validated['salary_type'],
                ]
            );
        }

        $this->syncFoodBranches(
            $nhanVien,
            $request->input('food_branch_ids', []),
            $request->input('primary_food_branch_id'),
            $allowedIds
        );

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

    /** @return Collection<int, FoodBranch> */
    protected function manageableBranches(User $user): Collection
    {
        $q = FoodBranch::query()->orderBy('name');
        if (! $user->is_admin) {
            $q->where('user_id', $user->id);
        }

        return $q->get();
    }

    /**
     * @param  array<int|string>  $branchIds
     * @param  array<int>  $allowedIds
     */
    protected function syncFoodBranches(Employee $employee, array $branchIds, mixed $primaryId, array $allowedIds): void
    {
        $ids = collect($branchIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && in_array($id, $allowedIds, true))
            ->unique()
            ->values();

        $primary = (int) $primaryId;
        if ($primary > 0 && ! $ids->contains($primary)) {
            $ids->push($primary);
        }
        if ($ids->isNotEmpty() && ($primary <= 0 || ! $ids->contains($primary))) {
            $primary = (int) $ids->first();
        }

        $sync = [];
        foreach ($ids as $id) {
            $sync[$id] = ['is_primary' => $id === $primary];
        }
        $employee->foodBranches()->sync($sync);
    }
}
