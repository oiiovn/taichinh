@extends('layouts.food')

@section('foodContent')
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Xin nghỉ</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    @if($employee)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Gửi đơn xin nghỉ</p>
            <form action="{{ route('food.xin-nghi.store') }}" method="post" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[160px]">
                    <x-form.date-picker name="from_date" label="Từ ngày" placeholder="Chọn ngày" :required="true" />
                </div>
                <div class="min-w-[160px]">
                    <x-form.date-picker name="to_date" label="Đến ngày" placeholder="Chọn ngày" :required="true" />
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-xs text-gray-500">Lý do</label>
                    <input type="text" name="reason" placeholder="Tùy chọn" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
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
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Từ – Đến</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số ngày</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lý do</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Trạng thái</th>
                    @if($isManager)<th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        @if($isManager)
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $req->employee?->user?->name ?? ('NV #' . $req->employee_id) }}</td>
                        @endif
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $req->from_date->format('d/m/Y') }} – {{ $req->to_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $req->days_count }} ngày</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $req->reason ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @if($req->status === 'pending')
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">Chờ duyệt</span>
                            @elseif($req->status === 'approved')
                                <span class="rounded bg-green-100 px-1.5 py-0.5 text-green-800 dark:bg-green-900/30 dark:text-green-200">Đã duyệt</span>
                            @else
                                <span class="rounded bg-red-100 px-1.5 py-0.5 text-red-800 dark:bg-red-900/30 dark:text-red-200">Từ chối</span>
                            @endif
                        </td>
                        @if($isManager)
                            @if($req->status === 'pending')
                                <td class="px-4 py-2">
                                    <form action="{{ route('food.xin-nghi.approve', $req) }}" method="post" class="inline">@csrf<button type="submit" class="text-green-600 hover:underline dark:text-green-400">Duyệt</button></form>
                                    <form action="{{ route('food.xin-nghi.reject', $req) }}" method="post" class="inline ml-2">@csrf<button type="submit" class="text-red-600 hover:underline dark:text-red-400">Từ chối</button></form>
                                </td>
                            @else
                                <td class="px-4 py-2">—</td>
                            @endif
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isManager ? 6 : 4 }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có đơn xin nghỉ.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
