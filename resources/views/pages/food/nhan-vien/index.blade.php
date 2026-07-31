@extends('layouts.food')

@section('foodContent')
@php $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n); @endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quản lý nhân viên</h2>
        <a href="{{ url(route('food.nhan-vien.create')) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thêm nhân viên</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chức vụ</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chi nhánh</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Hình thức lương</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mức lương</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày bắt đầu</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $emp->user->name ?? '—' }}<br><span class="text-xs text-gray-500">{{ $emp->user->email ?? '' }}</span></td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $emp->position ?: '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                            @if($emp->foodBranches->isEmpty())
                                <span class="text-amber-600 dark:text-amber-400">Chưa gán</span>
                            @else
                                {{ $emp->foodBranches->map(fn ($b) => $b->name.($b->pivot->is_primary ? ' ★' : ''))->implode(', ') }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ \App\Models\Employee::salaryTypeLabels()[$emp->salary_type] ?? $emp->salary_type }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $fmt($emp->salary_rate) }} đ</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $emp->start_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('food.nhan-vien.edit', $emp) }}" class="text-brand-600 hover:underline dark:text-brand-400">Sửa</a>
                            <form id="form-delete-nv-{{ $emp->id }}" action="{{ route('food.nhan-vien.destroy', $emp) }}" method="post" class="inline ml-2">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                    @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nv-{{ $emp->id }}', message: 'Ngừng hoạt động nhân viên này?' })"
                                    class="text-red-600 hover:underline dark:text-red-400">Ngừng</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có nhân viên. Nhấn "Thêm nhân viên" để thêm.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
