@php
    $selectedBranchIds = collect($selectedBranchIds ?? [])->map(fn ($id) => (int) $id)->all();
    $primaryBranchId = $primaryBranchId !== null && $primaryBranchId !== '' ? (int) $primaryBranchId : null;
@endphp
<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chi nhánh được phân công</p>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bắt buộc để chấm công trên app mobile. Chọn chi nhánh chính (is_primary).</p>
    @if(($foodBranches ?? collect())->isEmpty())
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Chưa có chi nhánh. Tạo tại mục Chi nhánh trước.</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach($foodBranches as $branch)
                <li class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-700">
                    <label class="flex flex-1 items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input
                            type="checkbox"
                            name="food_branch_ids[]"
                            value="{{ $branch->id }}"
                            {{ in_array((int) $branch->id, $selectedBranchIds, true) ? 'checked' : '' }}
                            class="rounded border-gray-300"
                        >
                        <span>{{ $branch->name }}</span>
                        @if($branch->latitude === null || $branch->longitude === null)
                            <span class="text-xs text-amber-600 dark:text-amber-400">(chưa GPS)</span>
                        @endif
                    </label>
                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                        <input
                            type="radio"
                            name="primary_food_branch_id"
                            value="{{ $branch->id }}"
                            {{ $primaryBranchId === (int) $branch->id ? 'checked' : '' }}
                            class="border-gray-300"
                        >
                        Chính
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
    @error('food_branch_ids')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    @error('food_branch_ids.*')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    @error('primary_food_branch_id')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
