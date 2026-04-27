@extends('layouts.food')

@section('foodContent')
<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch đã xác nhận</h2>

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
