@php
    $sortedOwe = $sortedOwe ?? collect();
    $annualRate = $annualRate ?? fn($item) => 0;
    $oweSortReason = $oweSortReason ?? 'highest_interest';
@endphp
<section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
    <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">Kế hoạch trả nợ</h3>
    @if($sortedOwe->isEmpty())
        <p class="text-theme-sm text-gray-500 dark:text-gray-400">Bạn không có khoản nợ nào. Khi có, hệ thống sẽ gợi ý thứ tự ưu tiên trả theo lãi suất hoặc kỳ hạn, số dư.</p>
    @else
        @if($oweSortReason === 'nearest_due')
            <p class="mb-4 text-theme-sm text-gray-500 dark:text-gray-400">Sắp xếp theo kỳ hạn gần nhất (các khoản lãi 0% được ưu tiên theo hạn trả).</p>
        @elseif($oweSortReason === 'largest_balance')
            <p class="mb-4 text-theme-sm text-gray-500 dark:text-gray-400">Sắp xếp theo số dư lớn nhất (giảm áp lực dòng tiền).</p>
        @else
            <p class="mb-4 text-theme-sm text-gray-500 dark:text-gray-400">Ưu tiên trả các khoản lãi cao trước để giảm tổng lãi phải trả.</p>
        @endif
        <ul class="space-y-3">
            @foreach($sortedOwe as $index => $item)
                @php $rate = $annualRate($item); @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800 dark:text-white">{{ $item->name }}</p>
                        <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $item->interest_display }} · Dư nợ {{ number_format($item->outstanding) }} ₫</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($index === 0)
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-theme-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Ưu tiên trả trước</span>
                        @endif
                        @if($item->source === 'linked')
                            <a href="{{ route('tai-chinh.loans.show', $item->entity->id) }}" class="text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Chi tiết</a>
                        @else
                            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Xem</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
