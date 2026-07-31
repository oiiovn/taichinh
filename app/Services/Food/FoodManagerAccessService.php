<?php

namespace App\Services\Food;

use App\Exceptions\Food\AttendanceException;
use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Phạm vi dữ liệu manager Food: chi nhánh thuộc owner (hoặc tất cả nếu admin).
 */
class FoodManagerAccessService
{
    public function managedBranchesQuery(User $user): Builder
    {
        $q = FoodBranch::query()->orderBy('name');
        if (! $user->is_admin) {
            $q->where('user_id', $user->id);
        }

        return $q;
    }

    /** @return Collection<int, FoodBranch> */
    public function managedBranches(User $user): Collection
    {
        return $this->managedBranchesQuery($user)->get();
    }

    public function assertManagedBranch(User $user, int $branchId): FoodBranch
    {
        $branch = $this->managedBranchesQuery($user)->find($branchId);
        if (! $branch) {
            throw AttendanceException::make(
                AttendanceException::BRANCH_NOT_FOUND,
                'Không tìm thấy chi nhánh hoặc bạn không có quyền.',
                404
            );
        }

        return $branch;
    }

    /** @return list<int> */
    public function managedBranchIds(User $user, ?int $branchId = null): array
    {
        if ($branchId !== null) {
            $this->assertManagedBranch($user, $branchId);

            return [$branchId];
        }

        return $this->managedBranchesQuery($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Nhân viên được gán vào các chi nhánh manager quản lý.
     *
     * @return Builder<Employee>
     */
    public function managedEmployeesQuery(User $user, ?int $branchId = null): Builder
    {
        $branchIds = $this->managedBranchIds($user, $branchId);
        if ($branchIds === []) {
            return Employee::query()->whereRaw('1 = 0');
        }

        return Employee::query()
            ->where('active', true)
            ->whereHas('foodBranches', function (Builder $q) use ($branchIds) {
                $q->whereIn('food_branches.id', $branchIds);
            })
            ->with(['user', 'foodBranches' => function ($q) use ($branchIds) {
                $q->whereIn('food_branches.id', $branchIds);
            }])
            ->orderBy('id');
    }
}
