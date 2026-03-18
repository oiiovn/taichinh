@extends('layouts.food')

@section('foodContent')
@php $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n); @endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ứng lương</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    @if($employee)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Gửi đơn ứng lương</p>
            <form action="{{ route('food.ung-luong.store') }}" method="post" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">Số tiền (đ)</label>
                    <input type="number" name="amount" min="1000" step="1000" required placeholder="500000" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-xs text-gray-500">Lý do (tùy chọn)</label>
                    <input type="text" name="reason" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Gửi đơn</button>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    @if($isManager)<th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>@endif
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số tiền</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lý do</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày gửi</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Trạng thái</th>
                    @if($isManager)<th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($advances as $adv)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        @if($isManager)
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $adv->employee?->user?->name ?? ('NV #' . $adv->employee_id) }}</td>
                        @endif
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $fmt($adv->amount) }} đ</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $adv->reason ?: '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $adv->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">
                            @if($adv->status === 'pending')
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">Chờ duyệt</span>
                            @elseif($adv->status === 'approved')
                                <span class="rounded bg-blue-100 px-1.5 py-0.5 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">Đã duyệt</span>
                            @elseif($adv->status === 'paid')
                                <span class="rounded bg-green-100 px-1.5 py-0.5 text-green-800 dark:bg-green-900/30 dark:text-green-200">Đã thanh toán</span>
                            @else
                                <span class="rounded bg-red-100 px-1.5 py-0.5 text-red-800 dark:bg-red-900/30 dark:text-red-200">Từ chối</span>
                            @endif
                        </td>
                        @if($isManager)
                            <td class="px-4 py-2">
                                @if($adv->status === 'pending')
                                    <form action="{{ route('food.ung-luong.approve', $adv) }}" method="post" class="inline">@csrf<button type="submit" class="text-green-600 hover:underline dark:text-green-400">Duyệt</button></form>
                                    <form action="{{ route('food.ung-luong.reject', $adv) }}" method="post" class="inline ml-2">@csrf<button type="submit" class="text-red-600 hover:underline dark:text-red-400">Từ chối</button></form>
                                @elseif($adv->status === 'approved')
                                    <form action="{{ route('food.ung-luong.paid', $adv) }}" method="post" class="inline">@csrf<button type="submit" class="text-brand-600 hover:underline">Đã thanh toán</button></form>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isManager ? 6 : 4 }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có đơn ứng lương.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
