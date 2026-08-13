{{-- Desktop table + pagination. Requires parent Alpine: page, perPage, total, rowVisible, rangeFrom, rangeTo, totalPages, stockOpen, stockEdit, priceEdit --}}
<div class="hidden overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 md:block">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50/90 dark:border-gray-700 dark:bg-gray-800/80">
                <tr>
                    <th class="w-14 px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tên</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Loại</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ĐVT</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tồn (CN)</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Điểm ĐH</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Giá/đv</th>
                    <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($materials as $m)
                    @php
                        $stockQty = $m->branchStockQty();
                        $reorder = $m->branchReorderPoint();
                        $isPack = $m->type === \App\Models\FoodMaterial::TYPE_BAO_BI;
                        $stockClass = $stockQty < 0 ? 'text-red-600 dark:text-red-400' : ($stockQty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400');
                    @endphp
                    <tr x-show="rowVisible({{ $loop->index }})" x-cloak @class([
                        'transition hover:bg-gray-50/70 dark:hover:bg-gray-800/30',
                        'bg-amber-50/40 dark:bg-amber-900/10' => $m->needsReorder(),
                    ]) data-row-index="{{ $loop->index }}">
                        <td class="px-4 py-3">
                            <span @class([
                                'flex h-10 w-10 items-center justify-center rounded-xl text-lg ring-1 ring-inset',
                                'bg-violet-50 ring-violet-100 dark:bg-violet-950/40 dark:ring-violet-900/50' => $isPack,
                                'bg-emerald-50 ring-emerald-100 dark:bg-emerald-950/40 dark:ring-emerald-900/50' => ! $isPack,
                            ])>{{ $isPack ? '📦' : '🥬' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900 dark:text-white">
                                @include('pages.food.nguyen-lieu.partials.material-usage-tip', ['material' => $m, 'materialUsages' => $materialUsages ?? []])
                            </div>
                            @if($m->code)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $m->code }}</p>@endif
                            @unless($m->active)<span class="text-xs text-gray-400">Ngưng dùng</span>@endunless
                        </td>
                        <td class="px-4 py-3">
                            @if($isPack)
                                <span class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Bao bì</span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Nguyên liệu</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $m->unit }}</td>
                        <td class="px-4 py-3 tabular-nums font-semibold {{ $stockClass }}">{{ $fmtQty($stockQty) }}</td>
                        <td class="px-4 py-3 tabular-nums text-gray-700 dark:text-gray-300">{{ $fmtQty($reorder) }}</td>
                        <td class="px-4 py-3 tabular-nums text-gray-800 dark:text-gray-200">{{ $m->last_unit_cost !== null ? number_format($m->last_unit_cost, 0, ',', '.').' đ' : '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <button type="button" title="Lưu tồn"
                                    @click="stockEdit = stockEdit === {{ $m->id }} ? null : {{ $m->id }}; priceEdit = null; stockOpen = null"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                </button>
                                <button type="button" title="Lưu giá"
                                    @click="priceEdit = priceEdit === {{ $m->id }} ? null : {{ $m->id }}; stockEdit = null; stockOpen = null"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </button>
                                <button type="button" title="Kho"
                                    @click="stockOpen = stockOpen === {{ $m->id }} ? null : {{ $m->id }}; stockEdit = null; priceEdit = null"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-600 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </button>
                                <button type="button" title="Xóa"
                                    @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nl-d-{{ $m->id }}', message: @js('Xóa '.$m->name.'?') })"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                <form id="form-delete-nl-d-{{ $m->id }}" action="{{ route('food.nguyen-lieu.destroy', $m) }}" method="post" class="hidden">@csrf @method('DELETE')<input type="hidden" name="branch_id" value="{{ $branchId }}"></form>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="rowVisible({{ $loop->index }}) && stockEdit === {{ $m->id }}" x-cloak class="bg-brand-50/30 dark:bg-brand-950/20">
                        <td colspan="8" class="px-4 py-3">
                            <p class="mb-2 text-xs font-semibold uppercase text-brand-700 dark:text-brand-300">Sửa tồn kho</p>
                            @include('pages.food.nguyen-lieu.partials.stock-adjust-form', [
                                'material' => $m,
                                'branchId' => $branchId,
                                'stockQty' => $stockQty,
                                'inputClass' => 'w-40 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900',
                                'formClass' => 'flex flex-wrap items-center gap-2',
                                'buttonClass' => 'rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700',
                            ])
                        </td>
                    </tr>
                    <tr x-show="rowVisible({{ $loop->index }}) && priceEdit === {{ $m->id }}" x-cloak class="bg-emerald-50/30 dark:bg-emerald-950/20">
                        <td colspan="8" class="px-4 py-3">
                            <p class="mb-2 text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-300">Sửa giá/đv</p>
                            @include('pages.food.nguyen-lieu.partials.unit-cost-form', [
                                'material' => $m,
                                'branchId' => $branchId,
                                'inputClass' => 'w-40 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900',
                                'formClass' => 'flex flex-wrap items-center gap-2',
                                'buttonClass' => 'rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700',
                            ])
                        </td>
                    </tr>
                    <tr x-show="rowVisible({{ $loop->index }}) && stockOpen === {{ $m->id }}" x-cloak class="bg-gray-50/80 dark:bg-gray-800/40">
                        <td colspan="8" class="px-4 py-4">
                            <div class="grid gap-3 lg:grid-cols-2">
                                <form action="{{ route('food.nguyen-lieu.stock-in', $m) }}" method="post" class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                    @csrf
                                    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                    <p class="mb-2 text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-300">Nhập kho</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Số lượng" class="{{ $inputClass }}">
                                        <input type="number" name="last_unit_cost" step="1" min="0" placeholder="Tổng tiền lô" class="{{ $inputClass }}">
                                        <input type="text" name="note" placeholder="Ghi chú" class="sm:col-span-2 {{ $inputClass }}">
                                    </div>
                                    <button type="submit" class="mt-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Nhập kho</button>
                                </form>
                                <form action="{{ route('food.nguyen-lieu.stock-out', $m) }}" method="post" class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                    @csrf
                                    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                    <p class="mb-2 text-xs font-semibold uppercase text-orange-700 dark:text-orange-300">Xuất kho</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Số lượng" class="{{ $inputClass }}">
                                        <input type="text" name="note" placeholder="Ghi chú" class="{{ $inputClass }}">
                                    </div>
                                    <button type="submit" class="mt-2 rounded-lg bg-orange-500 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-600">Xuất kho</button>
                                </form>
                            </div>
                            <form action="{{ route('food.nguyen-lieu.update', $m) }}" method="post" class="mt-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                @csrf @method('PUT')
                                <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Thông tin mặt hàng</p>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    <input type="text" name="name" value="{{ $m->name }}" required class="{{ $inputClass }}" placeholder="Tên">
                                    <input type="text" name="code" value="{{ $m->code }}" class="{{ $inputClass }}" placeholder="Mã">
                                    <select name="type" class="{{ $inputClass }}">
                                        @foreach($typeLabels as $k => $label)
                                            <option value="{{ $k }}" @selected($m->type === $k)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="unit" value="{{ $m->unit }}" required class="{{ $inputClass }}" placeholder="ĐVT">
                                    <input type="number" name="reorder_point" step="0.0001" value="{{ $reorder }}" class="{{ $inputClass }}" placeholder="Điểm ĐH">
                                    <input type="number" name="order_qty" step="0.0001" min="0" value="{{ $m->order_qty }}" class="{{ $inputClass }}" placeholder="SL lô đặt">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        <input type="checkbox" name="active" value="1" @checked($m->active) class="rounded border-gray-300"> Đang dùng
                                    </label>
                                </div>
                                <button type="submit" class="mt-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Cập nhật</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($materials->isNotEmpty())
        <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
            <p>Hiển thị <span class="font-semibold text-gray-900 dark:text-white" x-text="rangeFrom"></span> – <span class="font-semibold text-gray-900 dark:text-white" x-text="rangeTo"></span> trong <span class="font-semibold text-gray-900 dark:text-white" x-text="total"></span> mục</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">&lsaquo;</button>
                <template x-for="p in totalPages" :key="p">
                    <button type="button" @click="page = p" :class="page === p ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600'" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-2 text-sm font-medium" x-text="p"></button>
                </template>
                <button type="button" @click="page = Math.min(totalPages, page + 1)" :disabled="page >= totalPages" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">&rsaquo;</button>
                <select x-model.number="perPage" @change="page = 1" class="ml-2 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="10">10 / trang</option>
                    <option value="20">20 / trang</option>
                    <option value="50">50 / trang</option>
                </select>
            </div>
        </div>
    @endif
</div>
