@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    $branchId = $branch?->id;
@endphp
<div class="space-y-3 md:space-y-5" x-data="{ addOpen: {{ $errors->any() || old('name') ? 'true' : 'false' }}, stockOpen: null }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="hidden text-lg font-semibold text-gray-900 dark:text-white md:block">Nguyên liệu & bao bì</h2>
            @if($branch)
                <p class="text-sm text-gray-500 dark:text-gray-400">Kho chi nhánh: <span class="font-medium text-gray-800 dark:text-gray-200">{{ $branch->name }}</span></p>
            @endif
            @if($lowCount > 0)
                <p class="text-sm text-amber-700 dark:text-amber-300">{{ $lowCount }} mục dưới điểm đặt hàng (CN này)</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('food.nguyen-lieu.dat-hang', array_filter(['branch_id' => $branchId])) }}" class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">Gợi ý đặt hàng</a>
            <a href="{{ route('food.cong-thuc') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">Công thức</a>
            @if($branch)
                <button type="button" @click="addOpen = !addOpen" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ Thêm</button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    @if($branches->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Cần có chi nhánh trước khi quản lý tồn NL.
            <a href="{{ route('food.chi-nhanh') }}" class="font-semibold underline">Tạo chi nhánh</a>
        </div>
    @else
        <form method="get" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div>
                <label class="{{ $labelClass }}">Chi nhánh</label>
                <select name="branch_id" class="{{ $inputClass }}" onchange="this.form.submit()">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected((int) $branchId === (int) $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelClass }}">Loại</label>
                <select name="type" class="{{ $inputClass }}">
                    <option value="">Tất cả</option>
                    @foreach($typeLabels as $k => $label)
                        <option value="{{ $k }}" @selected($typeFilter === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="low_only" value="1" @checked($lowOnly) class="rounded border-gray-300">
                Chỉ dưới điểm đặt hàng
            </label>
            <button type="submit" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white dark:bg-white dark:text-gray-900">Lọc</button>
        </form>

        <div x-show="addOpen" x-cloak class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-2.5 text-sm font-semibold text-gray-900 dark:text-white">Thêm nguyên liệu / bao bì</h3>
            <form action="{{ route('food.nguyen-lieu.store') }}" method="post" class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                <div>
                    <label class="{{ $labelClass }}">Tên *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Mã (tuỳ chọn)</label>
                    <input type="text" name="code" value="{{ old('code') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Loại *</label>
                    <select name="type" required class="{{ $inputClass }}">
                        @foreach($typeLabels as $k => $label)
                            <option value="{{ $k }}" @selected(old('type', 'nguyen_lieu') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Đơn vị *</label>
                    <input type="text" name="unit" value="{{ old('unit', 'cái') }}" required placeholder="kg, g, cái, hộp…" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Tồn đầu (CN đang chọn)</label>
                    <input type="number" name="stock_on_hand" step="0.0001" min="0" value="{{ old('stock_on_hand', 0) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Điểm đặt hàng (CN này)</label>
                    <input type="number" name="reorder_point" step="0.0001" min="0" value="{{ old('reorder_point', 0) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">SL mỗi lần đặt (lô)</label>
                    <input type="number" name="order_qty" step="0.0001" min="0" value="{{ old('order_qty') }}" placeholder="vd 1000 — để trống = theo gợi ý" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Giá/đv (đ) — nếu có tồn đầu thì nhập tổng tiền lô</label>
                    <input type="number" name="last_unit_cost" step="1" min="0" value="{{ old('last_unit_cost') }}" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="{{ $labelClass }}">Ghi chú</label>
                    <input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Lưu</button>
                </div>
            </form>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-2 md:hidden">
            @forelse($materials as $m)
                @php
                    $stockQty = $m->branchStockQty();
                    $reorder = $m->branchReorderPoint();
                @endphp
                <article class="rounded-xl border {{ $m->needsReorder() ? 'border-amber-300 dark:border-amber-700' : 'border-gray-200 dark:border-gray-700' }} bg-white p-3 shadow-sm dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $typeLabels[$m->type] ?? $m->type }}</p>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $m->name }}
                            </h3>
                            @if($m->code)<p class="text-[11px] text-gray-500">{{ $m->code }}</p>@endif
                        </div>
                        @if($m->needsReorder())
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Cần đặt</span>
                        @endif
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                        <div class="rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/80">
                            <p class="text-[10px] uppercase text-gray-500">Tồn</p>
                            <p class="text-sm font-semibold tabular-nums">{{ $fmtQty($stockQty) }} <span class="text-[10px] font-normal">{{ $m->unit }}</span></p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/80">
                            <p class="text-[10px] uppercase text-gray-500">Đặt khi ≤</p>
                            <p class="text-sm font-semibold tabular-nums">{{ $fmtQty($reorder) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/80">
                            <p class="text-[10px] uppercase text-gray-500">Giá/đv</p>
                            <p class="text-sm font-semibold tabular-nums">{{ $m->last_unit_cost !== null ? number_format($m->last_unit_cost, 0, ',', '.').' đ' : '—' }}</p>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 border-t border-gray-100 pt-2 dark:border-gray-800">
                        <button type="button" @click="stockOpen = stockOpen === {{ $m->id }} ? null : {{ $m->id }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">Nhập/Xuất/Sửa</button>
                        <form id="form-delete-nl-m-{{ $m->id }}" action="{{ route('food.nguyen-lieu.destroy', $m) }}" method="post">
                            @csrf @method('DELETE')
                            <input type="hidden" name="branch_id" value="{{ $branchId }}">
                            <button type="button"
                                @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nl-m-{{ $m->id }}', message: @js('Xóa '.$m->name.'?') })"
                                class="text-xs font-medium text-red-600 dark:text-red-400">Xóa</button>
                        </form>
                    </div>
                    <div x-show="stockOpen === {{ $m->id }}" x-cloak class="mt-2 space-y-2 border-t border-gray-100 pt-2 dark:border-gray-800">
                        <div class="rounded-lg border border-brand-100 bg-brand-50/50 p-2 dark:border-brand-900 dark:bg-brand-950/20">
                            <p class="mb-1 text-[11px] font-semibold uppercase text-brand-700 dark:text-brand-300">Sửa giá/đv thủ công</p>
                            @include('pages.food.nguyen-lieu.partials.unit-cost-form', ['material' => $m, 'branchId' => $branchId, 'inputClass' => $inputClass, 'formClass' => 'grid grid-cols-[1fr_auto] gap-2'])
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-2 dark:border-gray-700 dark:bg-gray-800/50">
                            <p class="mb-1 text-[11px] font-semibold uppercase text-gray-600 dark:text-gray-300">Sửa tồn kho</p>
                            @include('pages.food.nguyen-lieu.partials.stock-adjust-form', ['material' => $m, 'branchId' => $branchId, 'stockQty' => $stockQty, 'inputClass' => $inputClass, 'formClass' => 'grid grid-cols-[1fr_auto] gap-2'])
                        </div>
                        <form action="{{ route('food.nguyen-lieu.stock-in', $m) }}" method="post" class="grid grid-cols-2 gap-2">
                            @csrf
                            <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                            <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Nhập SL" class="{{ $inputClass }}">
                            <input type="number" name="last_unit_cost" step="1" min="0" placeholder="Tổng tiền" class="{{ $inputClass }}">
                            <input type="text" name="note" placeholder="Ghi chú nhập" class="col-span-2 {{ $inputClass }}">
                            <button type="submit" class="col-span-2 rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white">Nhập kho</button>
                        </form>
                        <form action="{{ route('food.nguyen-lieu.stock-out', $m) }}" method="post" class="grid grid-cols-2 gap-2">
                            @csrf
                            <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                            <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Xuất SL" class="{{ $inputClass }}">
                            <input type="text" name="note" placeholder="Ghi chú xuất" class="{{ $inputClass }}">
                            <button type="submit" class="col-span-2 rounded-lg bg-orange-500 py-2 text-sm font-medium text-white">Xuất kho</button>
                        </form>
                        <details class="rounded-lg border border-gray-100 p-2 dark:border-gray-800">
                            <summary class="cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-400">Sửa thông tin</summary>
                            <form action="{{ route('food.nguyen-lieu.update', $m) }}" method="post" class="mt-2 grid gap-2">
                                @csrf @method('PUT')
                                <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                <input type="text" name="name" value="{{ $m->name }}" required class="{{ $inputClass }}">
                                <input type="text" name="code" value="{{ $m->code }}" class="{{ $inputClass }}" placeholder="Mã">
                                <select name="type" class="{{ $inputClass }}">
                                    @foreach($typeLabels as $k => $label)
                                        <option value="{{ $k }}" @selected($m->type === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="unit" value="{{ $m->unit }}" required class="{{ $inputClass }}">
                                <input type="number" name="reorder_point" step="0.0001" min="0" value="{{ $reorder }}" class="{{ $inputClass }}" placeholder="Điểm đặt hàng CN này">
                                <input type="number" name="order_qty" step="0.0001" min="0" value="{{ $m->order_qty }}" class="{{ $inputClass }}" placeholder="SL mỗi lần đặt (lô)">
                                <input type="number" name="last_unit_cost" step="1" min="0" value="{{ $m->last_unit_cost }}" class="{{ $inputClass }}" placeholder="Giá/đv">
                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="active" value="1" @checked($m->active) class="rounded"> Đang dùng</label>
                                <button type="submit" class="rounded-lg bg-brand-600 py-2 text-sm text-white">Cập nhật</button>
                            </form>
                        </details>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-3 py-8 text-center text-sm text-gray-500 dark:border-gray-700">Chưa có nguyên liệu. Bấm + Thêm để bắt đầu.</div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 md:block">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Tên</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Loại</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">ĐVT</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Tồn (CN)</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Điểm ĐH</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Giá/đv</th>
                        <th class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materials as $m)
                        @php
                            $stockQty = $m->branchStockQty();
                            $reorder = $m->branchReorderPoint();
                        @endphp
                        <tr class="border-b border-gray-100 align-top dark:border-gray-800 {{ $m->needsReorder() ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                            <td class="px-3 py-2">
                                <div>
                                    @include('pages.food.nguyen-lieu.partials.material-usage-tip', ['material' => $m, 'materialUsages' => $materialUsages ?? []])
                                </div>
                                @if($m->code)<div class="text-xs text-gray-500">{{ $m->code }}</div>@endif
                                @unless($m->active)<span class="text-xs text-gray-400">Ngưng</span>@endunless
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $typeLabels[$m->type] ?? $m->type }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $m->unit }}</td>
                            <td class="px-3 py-2 tabular-nums">
                                <div class="font-semibold">{{ $fmtQty($stockQty) }}</div>
                                @include('pages.food.nguyen-lieu.partials.stock-adjust-form', [
                                    'material' => $m,
                                    'branchId' => $branchId,
                                    'stockQty' => $stockQty,
                                    'inputClass' => 'w-24 rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-900',
                                    'formClass' => 'mt-1 flex flex-wrap items-center gap-1',
                                ])
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ $fmtQty($reorder) }}</td>
                            <td class="px-3 py-2 tabular-nums">
                                <div class="font-medium">{{ $m->last_unit_cost !== null ? number_format($m->last_unit_cost, 0, ',', '.').' đ' : '—' }}</div>
                                @include('pages.food.nguyen-lieu.partials.unit-cost-form', [
                                    'material' => $m,
                                    'branchId' => $branchId,
                                    'inputClass' => 'w-24 rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-900',
                                    'formClass' => 'mt-1 flex flex-wrap items-center gap-1',
                                ])
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="stockOpen = stockOpen === {{ $m->id }} ? null : {{ $m->id }}" class="text-brand-600 hover:underline dark:text-brand-400">Kho</button>
                                    <form id="form-delete-nl-d-{{ $m->id }}" action="{{ route('food.nguyen-lieu.destroy', $m) }}" method="post">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                        <button type="button"
                                            @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nl-d-{{ $m->id }}', message: @js('Xóa '.$m->name.'?') })"
                                            class="text-red-600 hover:underline dark:text-red-400">Xóa</button>
                                    </form>
                                </div>
                                <div x-show="stockOpen === {{ $m->id }}" x-cloak class="mt-2 max-w-md space-y-2 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                    <div class="rounded border border-brand-100 bg-brand-50/50 p-2 dark:border-brand-900 dark:bg-brand-950/20">
                                        <p class="mb-1 text-[10px] font-semibold uppercase text-brand-700 dark:text-brand-300">Giá/đv thủ công</p>
                                        @include('pages.food.nguyen-lieu.partials.unit-cost-form', [
                                            'material' => $m,
                                            'branchId' => $branchId,
                                            'inputClass' => 'w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-900',
                                            'formClass' => 'flex flex-wrap gap-1',
                                        ])
                                    </div>
                                    <div class="rounded border border-gray-200 bg-gray-50/80 p-2 dark:border-gray-700 dark:bg-gray-800/50">
                                        <p class="mb-1 text-[10px] font-semibold uppercase text-gray-600 dark:text-gray-300">Sửa tồn kho</p>
                                        @include('pages.food.nguyen-lieu.partials.stock-adjust-form', [
                                            'material' => $m,
                                            'branchId' => $branchId,
                                            'stockQty' => $stockQty,
                                            'inputClass' => 'w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-900',
                                            'formClass' => 'flex flex-wrap gap-1',
                                        ])
                                    </div>
                                    <form action="{{ route('food.nguyen-lieu.stock-in', $m) }}" method="post" class="flex flex-wrap gap-1">
                                        @csrf
                                        <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                        <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Nhập" class="w-24 {{ $inputClass }}">
                                        <input type="number" name="last_unit_cost" step="1" min="0" placeholder="Tổng tiền" class="w-24 {{ $inputClass }}">
                                        <button type="submit" class="rounded bg-emerald-600 px-2 py-1 text-xs text-white">Nhập</button>
                                    </form>
                                    <form action="{{ route('food.nguyen-lieu.stock-out', $m) }}" method="post" class="flex flex-wrap gap-1">
                                        @csrf
                                        <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                        <input type="number" name="qty" step="0.0001" min="0.0001" required placeholder="Xuất" class="w-24 {{ $inputClass }}">
                                        <button type="submit" class="rounded bg-orange-500 px-2 py-1 text-xs text-white">Xuất</button>
                                    </form>
                                    <form action="{{ route('food.nguyen-lieu.update', $m) }}" method="post" class="grid grid-cols-2 gap-1">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                        <input type="hidden" name="type" value="{{ $m->type }}">
                                        <input type="hidden" name="active" value="{{ $m->active ? '1' : '0' }}">
                                        <input type="text" name="name" value="{{ $m->name }}" required class="{{ $inputClass }}">
                                        <input type="text" name="code" value="{{ $m->code }}" class="{{ $inputClass }}" placeholder="Mã">
                                        <input type="text" name="unit" value="{{ $m->unit }}" required class="{{ $inputClass }}">
                                        <input type="number" name="reorder_point" step="0.0001" value="{{ $reorder }}" class="{{ $inputClass }}" placeholder="Điểm ĐH">
                                        <input type="number" name="order_qty" step="0.0001" min="0" value="{{ $m->order_qty }}" class="{{ $inputClass }}" placeholder="Lô đặt">
                                        <button type="submit" class="col-span-2 rounded bg-brand-600 px-2 py-1 text-xs text-white">Cập nhật TT</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">Chưa có dữ liệu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">Danh mục NL chung; tồn kho theo từng chi nhánh. BC bán gắn CN nào sẽ trừ kho CN đó.</p>
</div>
@endsection
