@php
    $usages = $materialUsages[$material->id] ?? [];
    $fmtQtyTip = $fmtQty ?? fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    $usageSummary = collect($usages)->map(
        fn (array $u) => $u['label'].' '.$fmtQtyTip($u['qty']).' '.$material->unit
    )->implode(' · ');
@endphp
@if(count($usages) === 0)
    <span class="font-medium text-gray-900 dark:text-white">{{ $material->name }}</span>
@else
    <span class="group relative inline-block max-w-full">
        <span
            class="cursor-help border-b border-dotted border-gray-400 font-medium text-gray-900 dark:border-gray-500 dark:text-white"
            title="{{ $usageSummary }}"
        >{{ $material->name }}</span>
        <div class="pointer-events-none absolute left-0 top-full z-50 mt-1 hidden w-72 rounded-xl border border-gray-200 bg-white p-2 text-left shadow-xl group-hover:block dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dùng trong món / CT</p>
            <ul class="max-h-48 space-y-1 overflow-y-auto text-sm">
                @foreach($usages as $u)
                    <li class="flex items-start justify-between gap-2">
                        <span class="min-w-0 flex-1 text-gray-800 dark:text-gray-100">
                            {{ $u['label'] }}
                            @if(($u['kind'] ?? '') === 'template')
                                <span class="text-[10px] text-amber-600 dark:text-amber-400">(chưa gán SP)</span>
                            @endif
                        </span>
                        <span class="shrink-0 tabular-nums font-semibold text-brand-700 dark:text-brand-300">
                            {{ $fmtQtyTip($u['qty']) }} {{ $material->unit }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </span>
@endif
