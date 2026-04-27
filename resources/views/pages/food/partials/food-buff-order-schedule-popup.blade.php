@php
    $popup = $foodBuffOrderSchedulePopup ?? ['pending_blocks' => collect(), 'other_blocks' => collect()];
    $pendingBlocks = $popup['pending_blocks'] ?? collect();
    $otherBlocks = $popup['other_blocks'] ?? collect();
    $receiveBlocks = $pendingBlocks->concat($otherBlocks)->sortBy('id')->values();
    $dayGroups = $receiveBlocks->groupBy('date_label')->map(fn ($g) => $g->sortBy('id')->values());
@endphp
@if($pendingBlocks->isNotEmpty())
    <div
        x-data="{ open: true }"
        x-show="open"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed inset-0 z-[9999] flex min-h-[100dvh] min-h-screen w-full items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        style="-webkit-overflow-scrolling: touch;"
    >
        <div
            class="absolute inset-0 bg-black/70 backdrop-blur-xl backdrop-saturate-150 transition-opacity dark:bg-black/80"
            style="backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);"
            @click="open = false"
            aria-hidden="true"
        ></div>
        <form
            method="POST"
            action="{{ route('food.lich-dat-don.acknowledge') }}"
            class="relative z-10 my-auto max-h-[min(90dvh,44rem)] w-full max-w-2xl shrink-0 overflow-y-auto rounded-2xl border-2 border-amber-400 bg-white p-6 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.45)] ring-4 ring-amber-400/35 dark:border-amber-500 dark:bg-gray-900 dark:ring-amber-500/25"
            role="dialog"
            aria-modal="true"
            aria-labelledby="food-buff-schedule-ack-title"
            @click.stop
        >
            @csrf
            @foreach($pendingBlocks as $block)
                <input type="hidden" name="schedule_ids[]" value="{{ $block['id'] }}">
            @endforeach
            <div class="flex items-start gap-3 rounded-xl bg-amber-100/90 px-3 py-2 dark:bg-amber-950/50">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">!</span>
                <div class="min-w-0">
                    <h3 id="food-buff-schedule-ack-title" class="text-base font-bold leading-snug tracking-tight text-amber-950 sm:text-lg dark:text-amber-100">Thông tin lịch seeding đánh giá food Bánh mì &amp; Bánh tráng</h3>
                </div>
            </div>
            <div class="mt-5 space-y-4">
                @foreach($dayGroups as $dateLabel => $dayBlocks)
                    @php
                        $giverVariants = $dayBlocks->pluck('giver_line')->filter(fn ($v) => filled($v))->unique()->values();
                        $singleGiver = $giverVariants->count() === 1;
                    @endphp
                    <div class="rounded-xl border-2 border-amber-200 bg-gradient-to-b from-amber-50 to-white p-4 dark:border-amber-800 dark:from-amber-950/80 dark:to-gray-900">
                        <div class="border-b border-amber-200/80 pb-3 dark:border-amber-900">
                            <p class="text-base font-bold text-gray-900 dark:text-white">Ngày {{ $dateLabel }}</p>
                        </div>
                        @if($singleGiver)
                            <p class="mt-3 text-sm text-gray-800 dark:text-gray-100">
                                <span class="text-gray-500 dark:text-gray-400">Người giao đơn ·</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $giverVariants->first() }}</span>
                            </p>
                        @endif
                        <div class="mt-3 space-y-3">
                            @foreach($dayBlocks as $block)
                                <div class="rounded-lg border border-amber-200/90 bg-white/80 px-3 py-3 shadow-sm dark:border-amber-900/60 dark:bg-gray-900/50">
                                    @if(! $singleGiver && filled($block['giver_line'] ?? null))
                                        <p class="mb-2 text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">Người giao đơn ·</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $block['giver_line'] ?? '—' }}</span>
                                        </p>
                                    @endif
                                    <div class="space-y-1.5">
                                        @foreach($block['assignees'] as $as)
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $as['name'] }}</p>
                                        @endforeach
                                    </div>
                                    <ul class="mt-3 space-y-1.5 border-t border-amber-100 pt-3 dark:border-amber-900/50">
                                        @foreach($block['lines'] as $line)
                                            <li class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                                <span class="text-gray-700 dark:text-gray-200">{{ $line['branch_name'] }}</span>
                                                <span class="tabular-nums font-bold text-amber-700 dark:text-amber-400">{{ $line['order_count'] }} đơn</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 flex justify-end sm:justify-end">
                <button type="submit" class="w-full rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white shadow-md hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-500/40 sm:w-auto">Đồng ý, đã nắm lịch</button>
            </div>
        </form>
    </div>
@endif
