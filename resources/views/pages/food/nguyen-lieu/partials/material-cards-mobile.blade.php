{{-- Mobile cards. Requires parent Alpine: page, perPage, rowVisible, stockOpen, stockEdit, priceEdit --}}
<div class="space-y-3 md:hidden">
    @forelse($materials as $m)
        @php
            $stockQty = $m->branchStockQty();
            $reorder = $m->branchReorderPoint();
            $isPack = $m->type === \App\Models\FoodMaterial::TYPE_BAO_BI;
            $stockClass = $stockQty < 0 ? 'text-red-600 dark:text-red-400' : ($stockQty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400');
        @endphp
        <article x-show="rowVisible({{ $loop->index }})" x-cloak @class([
            'rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-900',
            'border-brand-300 dark:border-brand-700' => $m->isStockChecked(),
            'border-amber-300 dark:border-amber-700' => $m->needsReorder() && ! $m->isStockChecked(),
            'border-gray-200/80 dark:border-gray-700' => ! $m->needsReorder() && ! $m->isStockChecked(),
        ])>
            <div class="flex items-start gap-3">
                <span @class([
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg ring-1 ring-inset',
                    'bg-violet-50 ring-violet-100 dark:bg-violet-950/40 dark:ring-violet-900/50' => $isPack,
                    'bg-emerald-50 ring-emerald-100 dark:bg-emerald-950/40 dark:ring-emerald-900/50' => ! $isPack,
                ])>{{ $isPack ? '📦' : '🥬' }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            @if($isPack)
                                <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Bao bì</span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Nguyên liệu</span>
                            @endif
                            @if($m->isStockChecked())
                                <span class="ml-1 inline-flex rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">Đã kiểm tồn</span>
                            @endif
                            <h3 class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                                @include('pages.food.nguyen-lieu.partials.material-usage-tip', ['material' => $m, 'materialUsages' => $materialUsages ?? []])
                            </h3>
                            @if($m->code)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $m->code }}</p>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @include('pages.food.nguyen-lieu.partials.stock-checked-toggle', ['material' => $m, 'branchId' => $branchId])
                            @if($m->needsReorder())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">Cần đặt</span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-gray-50 px-2 py-2 dark:bg-gray-800/80">
                            <p class="text-[10px] font-medium uppercase text-gray-500">Tồn</p>
                            <p class="text-sm font-bold tabular-nums {{ $stockClass }}">{{ $fmtQty($stockQty) }}</p>
                            <p class="text-[10px] text-gray-400">{{ $m->unit }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-2 py-2 dark:bg-gray-800/80">
                            <p class="text-[10px] font-medium uppercase text-gray-500">Điểm ĐH</p>
                            <p class="text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">{{ $fmtQty($reorder) }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-2 py-2 dark:bg-gray-800/80">
                            <p class="text-[10px] font-medium uppercase text-gray-500">Giá/đv</p>
                            <p class="text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">{{ $m->last_unit_cost !== null ? number_format($m->last_unit_cost, 0, ',', '.').' đ' : '—' }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="stockEdit = stockEdit === {{ $m->id }} ? null : {{ $m->id }}; priceEdit = null; stockOpen = null"
                            class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">Lưu tồn</button>
                        <button type="button" @click="priceEdit = priceEdit === {{ $m->id }} ? null : {{ $m->id }}; stockEdit = null; stockOpen = null"
                            class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Lưu giá</button>
                        <button type="button" @click="stockOpen = stockOpen === {{ $m->id }} ? null : {{ $m->id }}; stockEdit = null; priceEdit = null"
                            class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300">Kho</button>
                        <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nl-m-{{ $m->id }}', message: @js('Xóa '.$m->name.'?') })"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                        <form id="form-delete-nl-m-{{ $m->id }}" action="{{ route('food.nguyen-lieu.destroy', $m) }}" method="post" class="hidden">@csrf @method('DELETE')<input type="hidden" name="branch_id" value="{{ $branchId }}"></form>
                    </div>
                    <div x-show="stockEdit === {{ $m->id }}" x-cloak class="mt-3 rounded-xl border border-brand-100 bg-brand-50/50 p-3 dark:border-brand-900 dark:bg-brand-950/20">
                        @include('pages.food.nguyen-lieu.partials.stock-adjust-form', [
                            'material' => $m, 'branchId' => $branchId, 'stockQty' => $stockQty,
                            'inputClass' => $inputClass, 'formClass' => 'flex flex-wrap items-center gap-2',
                            'buttonClass' => 'rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700',
                        ])
                    </div>
                    <div x-show="priceEdit === {{ $m->id }}" x-cloak class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3 dark:border-emerald-900 dark:bg-emerald-950/20">
                        @include('pages.food.nguyen-lieu.partials.unit-cost-form', [
                            'material' => $m, 'branchId' => $branchId,
                            'inputClass' => $inputClass, 'formClass' => 'flex flex-wrap items-center gap-2',
                            'buttonClass' => 'rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700',
                        ])
                    </div>
                    <div x-show="stockOpen === {{ $m->id }}" x-cloak class="mt-3 space-y-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <form action="{{ route('food.nguyen-lieu.stock-in', $m) }}" method="post" class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                            @csrf
                            <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                            <p class="mb-2 text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-300">Nhập kho</p>
                            <div class="grid gap-2">
                                <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Số lượng" class="{{ $inputClass }}">
                                <input type="number" name="last_unit_cost" step="1" min="0" placeholder="Tổng tiền lô" class="{{ $inputClass }}">
                                <input type="text" name="note" placeholder="Ghi chú" class="{{ $inputClass }}">
                            </div>
                            <button type="submit" class="mt-2 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white">Nhập kho</button>
                        </form>
                        <form action="{{ route('food.nguyen-lieu.stock-out', $m) }}" method="post" class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                            @csrf
                            <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                            <p class="mb-2 text-xs font-semibold uppercase text-orange-700 dark:text-orange-300">Xuất kho</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Số lượng" class="{{ $inputClass }}">
                                <input type="text" name="note" placeholder="Ghi chú" class="{{ $inputClass }}">
                            </div>
                            <button type="submit" class="mt-2 w-full rounded-lg bg-orange-500 py-2 text-sm font-semibold text-white">Xuất kho</button>
                        </form>
                        <details class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <summary class="cursor-pointer text-xs font-semibold text-gray-600 dark:text-gray-400">Sửa thông tin mặt hàng</summary>
                            <form action="{{ route('food.nguyen-lieu.update', $m) }}" method="post" class="mt-3 grid gap-2">
                                @csrf @method('PUT')
                                <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                <input type="text" name="name" value="{{ $m->name }}" required class="{{ $inputClass }}" placeholder="Tên">
                                <input type="text" name="code" value="{{ $m->code }}" class="{{ $inputClass }}" placeholder="Mã">
                                <select name="type" class="{{ $inputClass }}">
                                    @foreach($typeLabels as $k => $label)
                                        <option value="{{ $k }}" @selected($m->type === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="unit" value="{{ $m->unit }}" required class="{{ $inputClass }}" placeholder="ĐVT">
                                <input type="number" name="reorder_point" step="0.0001" value="{{ $reorder }}" class="{{ $inputClass }}" placeholder="Điểm đặt hàng">
                                <input type="number" name="order_qty" step="0.0001" min="0" value="{{ $m->order_qty }}" class="{{ $inputClass }}" placeholder="SL lô đặt">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="active" value="1" @checked($m->active) class="rounded border-gray-300"> Đang dùng
                                </label>
                                <button type="submit" class="rounded-lg bg-brand-600 py-2 text-sm font-semibold text-white">Cập nhật</button>
                            </form>
                        </details>
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Chưa có nguyên liệu. Bấm + Thêm để bắt đầu.</div>
    @endforelse
    @if($materials->isNotEmpty())
        <div class="flex flex-col gap-3 rounded-2xl border border-gray-200/80 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            <p>Hiển thị <span class="font-semibold text-gray-900 dark:text-white" x-text="rangeFrom"></span> – <span class="font-semibold text-gray-900 dark:text-white" x-text="rangeTo"></span> trong <span class="font-semibold text-gray-900 dark:text-white" x-text="total"></span> mục</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-gray-600">&lsaquo;</button>
                <template x-for="p in totalPages" :key="p">
                    <button type="button" @click="page = p" :class="page === p ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600'" class="rounded-lg border px-3 py-1.5 text-sm font-medium" x-text="p"></button>
                </template>
                <button type="button" @click="page = Math.min(totalPages, page + 1)" :disabled="page >= totalPages" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-gray-600">&rsaquo;</button>
                <select x-model.number="perPage" @change="page = 1" class="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="10">10 / trang</option>
                    <option value="20">20 / trang</option>
                    <option value="50">50 / trang</option>
                </select>
            </div>
        </div>
    @endif
</div>
