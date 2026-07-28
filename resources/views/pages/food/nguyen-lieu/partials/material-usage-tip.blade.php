@php
    $usages = $materialUsages[$material->id] ?? [];
    $fmtQtyTip = $fmtQty ?? fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
@endphp
<span
    class="relative inline-block max-w-full"
    x-data="{
        show: false,
        style: '',
        hideTimer: null,
        open(e) {
            clearTimeout(this.hideTimer);
            const el = e.currentTarget;
            const r = el.getBoundingClientRect();
            const tipW = 288;
            const headerPad = 72;
            const left = Math.max(8, Math.min(r.left, window.innerWidth - tipW - 8));
            let top = r.bottom + 8;
            this.style = `top:${top}px;left:${left}px;`;
            this.show = true;
            this.$nextTick(() => {
                const tip = this.$refs.tip;
                if (!tip) return;
                const maxH = Math.min(360, window.innerHeight - headerPad - 16);
                tip.style.maxHeight = maxH + 'px';
                const h = tip.offsetHeight;
                if (top + h > window.innerHeight - 8) {
                    top = Math.max(headerPad, r.top - h - 8);
                }
                if (top < headerPad) {
                    top = headerPad;
                }
                this.style = `top:${top}px;left:${left}px;`;
            });
        },
        scheduleClose() {
            clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(() => { this.show = false; }, 120);
        },
        keepOpen() {
            clearTimeout(this.hideTimer);
            this.show = true;
        },
        close() {
            clearTimeout(this.hideTimer);
            this.show = false;
        }
    }"
    @mouseenter="open($event)"
    @mouseleave="scheduleClose()"
    @focusin="open($event)"
    @focusout="scheduleClose()"
>
    <button
        type="button"
        class="cursor-help border-b border-dotted border-gray-400 text-left font-medium text-gray-900 outline-none dark:border-gray-500 dark:text-white"
        aria-describedby="nl-usage-{{ $material->id }}"
    >{{ $material->name }}</button>

    <template x-teleport="body">
        <div
            x-ref="tip"
            x-show="show"
            x-cloak
            x-transition.opacity.duration.100ms
            id="nl-usage-{{ $material->id }}"
            role="tooltip"
            :style="style"
            @mouseenter="keepOpen()"
            @mouseleave="scheduleClose()"
            class="fixed z-[100000] flex w-72 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-2xl dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="shrink-0 border-b border-gray-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                Dùng trong món (định lượng / 1 sp)
            </p>
            @if(count($usages) === 0)
                <p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Chưa gắn trong công thức / sản phẩm nào.</p>
            @else
                <ul class="min-h-0 flex-1 space-y-1.5 overflow-y-auto overscroll-contain px-3 py-2 text-sm" style="max-height: 260px; -webkit-overflow-scrolling: touch;">
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
                <p class="shrink-0 border-t border-gray-100 px-3 py-1.5 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    {{ count($usages) }} món/CT · cuộn để xem thêm
                </p>
            @endif
        </div>
    </template>
</span>
