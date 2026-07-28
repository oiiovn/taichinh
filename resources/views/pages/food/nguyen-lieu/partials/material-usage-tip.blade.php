@php
    $usages = $materialUsages[$material->id] ?? [];
    $fmtQtyTip = $fmtQty ?? fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
@endphp
<span
    class="relative inline-block max-w-full"
    x-data="{
        show: false,
        style: '',
        open(e) {
            const el = e.currentTarget;
            const r = el.getBoundingClientRect();
            const left = Math.max(8, Math.min(r.left, window.innerWidth - 300));
            let top = r.bottom + 8;
            this.style = `top:${top}px;left:${left}px;`;
            this.show = true;
            this.$nextTick(() => {
                const tip = this.$refs.tip;
                if (!tip) return;
                const h = tip.offsetHeight;
                if (top + h > window.innerHeight - 8) {
                    top = Math.max(8, r.top - h - 8);
                    this.style = `top:${top}px;left:${left}px;`;
                }
            });
        },
        close() { this.show = false; }
    }"
    @mouseenter="open($event)"
    @mouseleave="close()"
    @focusin="open($event)"
    @focusout="close()"
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
            id="nl-usage-{{ $material->id }}"
            role="tooltip"
            :style="style"
            class="pointer-events-none fixed z-[300] w-72 rounded-xl border border-gray-200 bg-white p-3 text-left shadow-xl dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dùng trong món (định lượng / 1 sp)</p>
            @if(count($usages) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa gắn trong công thức / sản phẩm nào.</p>
            @else
                <ul class="max-h-56 space-y-1.5 overflow-y-auto text-sm">
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
                <p class="mt-2 border-t border-gray-100 pt-1.5 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    {{ count($usages) }} món/CT
                </p>
            @endif
        </div>
    </template>
</span>
