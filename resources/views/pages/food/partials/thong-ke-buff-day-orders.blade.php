<div
    x-show="openDay === '{{ $day['day_key'] }}'"
    x-cloak
    x-data="{ visibleMobile: 3, isMobile: window.innerWidth < 768 }"
    x-init="window.addEventListener('resize', () => isMobile = window.innerWidth < 768)"
    class="space-y-2 border-t border-gray-100 bg-gray-50/90 px-2 py-2 dark:border-gray-600 dark:bg-gray-900/30"
>
    @foreach($day['items'] as $itemIndex => $o)
        @php
            $customerRaw = trim((string) ($o->customer_name ?? ''));
            $customerKey = mb_strtolower($customerRaw);
            $customerDisplay = $customerRaw !== '' ? ($foodUserNameMap[$customerKey] ?? $customerRaw) : '—';
        @endphp
        <div
            x-show="!isMobile || {{ (int) $itemIndex }} < visibleMobile"
            x-cloak
        >
            <div
                x-data='{
                    reviewed: @json((bool) ! empty($o->customer_reviewed)),
                    busy: false,
                    toggleReview() {
                        if (this.busy) return;
                        this.busy = true;
                        const tokenEl = document.querySelector("meta[name=csrf-token]");
                        const fd = new FormData();
                        fd.append("_token", tokenEl ? tokenEl.content : "");
                        fd.append("_method", "PATCH");
                        fd.append("from_date", @json($from->format("Y-m-d")));
                        fd.append("to_date", @json($to->format("Y-m-d")));
                        @if($branchId)
                        fd.append("food_branch_id", @json((string) $branchId));
                        @endif
                        fetch(@json(route("food.thong-ke-buff.order.reviewed", $o)), {
                            method: "POST",
                            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" },
                            body: fd,
                            credentials: "same-origin",
                        })
                            .then((r) => {
                                const ct = r.headers.get("content-type") || "";
                                if (ct.includes("application/json")) {
                                    return r.json().then((data) => ({ r, data }));
                                }
                                return { r, data: {} };
                            })
                            .then(({ r, data }) => {
                                if (!r.ok) {
                                    window.alert(data.message || "Không thực hiện được.");
                                    return;
                                }
                                if (data.ok && typeof data.customer_reviewed !== "undefined") {
                                    this.reviewed = !!data.customer_reviewed;
                                }
                            })
                            .catch(() => window.alert("Lỗi mạng."))
                            .finally(() => { this.busy = false; });
                    }
                }'
                class="overflow-hidden rounded-lg border border-gray-200/90 bg-white text-[10px] leading-snug shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
            <div class="flex gap-2 border-b border-gray-100 px-2 py-1.5 dark:border-gray-700">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-mono text-[11px] font-bold text-gray-900 dark:text-gray-100">{{ $o->invoice_code }}</span>
                        <span
                            x-show="reviewed"
                            x-cloak
                            class="inline-flex items-center gap-2 rounded border border-amber-400/80 bg-amber-100 px-1.5 py-px text-[9px] font-semibold text-amber-950 dark:border-amber-400/70 dark:bg-amber-950/80 dark:text-amber-50"
                            role="img"
                            aria-label="Đã đánh giá 5 sao"
                            title="Đơn đã được đánh giá"
                        >
                            <span class="inline-flex items-center gap-px" aria-hidden="true">
                                @for($si = 0; $si < 5; $si++)
                                    <svg class="h-2.5 w-2.5 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </span>
                            <span>Đã đánh giá</span>
                        </span>
                    </div>
                    @if(auth()->user()?->is_admin)
                        <div class="flex flex-wrap gap-1">
                            <button
                                type="button"
                                @click.prevent="toggleReview()"
                                :disabled="busy"
                                :class="{ 'opacity-60': busy }"
                                class="relative z-10 inline-flex min-h-7 shrink-0 items-center justify-center whitespace-nowrap rounded border border-blue-500/90 bg-blue-50 px-2 py-1 text-[9px] font-medium leading-tight text-blue-900 hover:bg-blue-100 disabled:cursor-not-allowed dark:border-blue-400/70 dark:bg-blue-950/40 dark:text-blue-100 dark:hover:bg-blue-900/50"
                                title="Đánh dấu đơn khách đã đánh giá 5 sao"
                            >
                                <span class="inline {{ ! empty($o->customer_reviewed) ? 'hidden' : '' }}" :class="{ 'hidden': reviewed }">Đánh dấu đã đánh giá</span>
                                <span class="inline {{ empty($o->customer_reviewed) ? 'hidden' : '' }}" :class="{ 'hidden': !reviewed }">Bỏ đánh giá</span>
                            </button>
                            <form method="POST" action="{{ route('food.thong-ke-buff.order.destroy', $o) }}" class="inline" onsubmit="return confirm('Xóa đơn {{ $o->invoice_code }}?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="from_date" value="{{ $from->format('Y-m-d') }}">
                                <input type="hidden" name="to_date" value="{{ $to->format('Y-m-d') }}">
                                @if($branchId)
                                    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                @endif
                                <button type="submit" class="rounded border border-red-300/90 bg-red-50 px-1.5 py-px text-[9px] font-medium text-red-800 hover:bg-red-100 dark:border-red-500/50 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950/40">Xóa</button>
                            </form>
                        </div>
                    @endif
                </div>
                <div class="flex shrink-0 items-center justify-end rounded-md border border-emerald-200/90 bg-emerald-100 px-1.5 py-1 text-right dark:border-emerald-700/60 dark:bg-emerald-950/70">
                    <span class="text-[11px] font-bold tabular-nums text-emerald-900 dark:text-emerald-200">+{{ $fmt($o->labor_amount) }}đ</span>
                </div>
            </div>
            <div class="space-y-0.5 px-2 py-1.5 text-gray-900 dark:text-gray-100">
                <p class="truncate"><span class="text-gray-600 dark:text-gray-400">Chi nhánh:</span> <span class="font-semibold text-gray-950 dark:text-white">{{ $o->branch?->name ?? '—' }}</span></p>
                <p class="tabular-nums"><span class="text-gray-600 dark:text-gray-400">Đặt lúc:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ $formatBuffOrderDateTime($o) }}</span></p>
                <p class="break-all"><span class="text-gray-600 dark:text-gray-400">Shopeefood:</span> <span class="font-medium text-gray-950 dark:text-white">{{ $customerDisplay }}</span></p>
            </div>
            @if(!($isOnlyThongKeBuffUser ?? false))
                <div class="border-t border-gray-200 bg-amber-100/80 px-2 py-1 dark:border-gray-600 dark:bg-amber-950/55">
                    <p class="text-[10px] font-semibold text-amber-950 dark:text-amber-100"><span class="font-medium text-amber-900 dark:text-amber-200">Buff:</span> {{ $fmt($o->buff_amount) }} đ</p>
                </div>
            @endif
            </div>
        </div>
    @endforeach
    @if(($day['count'] ?? 0) > 3)
        <button
            type="button"
            class="w-full rounded-lg border border-gray-200 bg-white py-2 text-[11px] font-medium text-brand-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-brand-400 dark:hover:bg-gray-700 md:hidden"
            @click="visibleMobile = Math.min(visibleMobile + 3, {{ (int) $day['count'] }})"
            x-show="isMobile && visibleMobile < {{ (int) $day['count'] }}"
            x-cloak
        >Xem thêm</button>
    @endif
</div>
