@extends('layouts.food')

@section('foodContent')
<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch đã xác nhận</h2>

    <form method="GET" action="{{ route('food.lich-da-xac-nhan') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
            <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
            <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        </div>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700">Lọc</button>
    </form>

    <div class="space-y-3">
        @forelse($schedules as $block)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $block['date_label'] }}</p>
                    <p class="text-xs text-green-600 dark:text-green-400">Đã xác nhận</p>
                </div>
                <div class="mt-2 space-y-1">
                    @foreach($block['lines'] as $line)
                        <p class="text-sm text-gray-700 dark:text-gray-300">- {{ $line['branch_name'] }}: {{ $line['order_count'] }} đơn</p>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $block['giver_line'] }}</p>
            </div>
        @empty
            <p class="rounded-xl border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Không có lịch đã xác nhận trong kỳ lọc.</p>
        @endforelse
    </div>
</div>
@endsection
