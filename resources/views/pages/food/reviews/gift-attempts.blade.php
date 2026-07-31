@extends('layouts.food')

@section('foodContent')
@php
    $resultBadge = [
        'page_open' => 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-500/40 dark:bg-blue-900/30 dark:text-blue-200',
        'success' => 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200',
        'already_rewarded' => 'border-red-300 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-900/30 dark:text-red-200',
        'expired' => 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200',
        'not_found' => 'border-gray-300 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ];
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch sử QR nhận quà</h2>
        <a href="{{ route('food.reviews.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">← Đánh giá</a>
    </div>

    <form method="GET" class="grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 md:grid-cols-5 dark:border-gray-700 dark:bg-gray-800/50">
        <input type="text" name="q" value="{{ $q }}" placeholder="Mã đơn, FR-..., IP..." class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm md:col-span-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <select name="result" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả kết quả</option>
            @foreach($resultLabels as $key => $label)
                <option value="{{ $key }}" @selected($result === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from_date" value="{{ $fromDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <input type="date" name="to_date" value="{{ $toDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <div class="md:col-span-5">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Lọc</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800/80">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-3 py-2">Thời gian</th>
                    <th class="px-3 py-2">Mã nhập</th>
                    <th class="px-3 py-2">Kết quả</th>
                    <th class="px-3 py-2">Mã quà</th>
                    <th class="px-3 py-2">Chi nhánh</th>
                    <th class="px-3 py-2">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                @forelse($attempts as $a)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                            {{ $a->created_at?->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $a->order_code_input }}</span>
                            @if($a->result_message)
                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $a->result_message }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded border px-2 py-0.5 text-[11px] font-medium {{ $resultBadge[$a->result] ?? $resultBadge['not_found'] }}">
                                {{ $a->resultLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-800 dark:text-gray-200">{{ $a->gift_code ?: '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $a->review?->branch?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $a->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-xs text-gray-500 dark:text-gray-400">Chưa có lịch sử nhập mã.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $attempts->links() }}</div>
</div>
@endsection
