@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    $branchId = $branch?->id;
@endphp
<div class="space-y-3 md:space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="hidden text-lg font-semibold text-gray-900 dark:text-white md:block">Gợi ý đặt hàng</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Thiếu hụt = mục tiêu tồn − tồn hiện tại; có «SL mỗi lần đặt» thì làm tròn theo lô</p>
        </div>
        <a href="{{ route('food.nguyen-lieu', array_filter(['branch_id' => $branchId])) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">← Danh mục NL</a>
    </div>

    @if($branches->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Cần có chi nhánh. <a href="{{ route('food.chi-nhanh') }}" class="font-semibold underline">Tạo chi nhánh</a>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <form method="get" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="{{ $labelClass }}">Chi nhánh</label>
                    <select name="branch_id" class="{{ $inputClass }}">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected((int) $branchId === (int) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Từ ngày</label>
                    <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Đến ngày</label>
                    <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="{{ $inputClass }}">
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Xem</button>
            </form>
        </div>

        @if($needOrderRows->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                <h3 class="mb-2 text-sm font-semibold text-amber-900 dark:text-amber-200">Cần chú ý / nên đặt ({{ $needOrderRows->count() }})</h3>
                <ul class="space-y-1.5 text-sm">
                    @foreach($needOrderRows as $row)
                        @php $m = $row['material']; @endphp
                        <li class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $m->name }} <span class="text-xs font-normal text-gray-500">({{ $typeLabels[$m->type] ?? '' }})</span></span>
                            <span class="tabular-nums text-amber-800 dark:text-amber-200">
                                @if($row['need_order'] > 0)
                                    Đặt {{ $fmtQty($row['need_order']) }} {{ $m->unit }}
                                    @if($row['order_qty'] && (float) $row['need_raw'] !== (float) $row['need_order'])
                                        <span class="text-[11px] font-normal text-amber-700/80">(thiếu {{ $fmtQty($row['need_raw']) }}, lô {{ $fmtQty($row['order_qty']) }})</span>
                                    @endif
                                @else
                                    Dưới điểm ĐH
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2.5 font-medium">Nguyên liệu</th>
                        <th class="px-3 py-2.5 font-medium">Tồn CN</th>
                        <th class="px-3 py-2.5 font-medium">Tiêu thụ kỳ</th>
                        <th class="px-3 py-2.5 font-medium">TB/ngày</th>
                        <th class="px-3 py-2.5 font-medium">Còn ~ngày</th>
                        <th class="px-3 py-2.5 font-medium">Điểm ĐH</th>
                        <th class="px-3 py-2.5 font-medium">Lô đặt</th>
                        <th class="px-3 py-2.5 font-medium">Gợi ý đặt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php $m = $row['material']; @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 {{ $row['below_reorder'] || $row['need_order'] > 0 ? 'bg-amber-50/40 dark:bg-amber-900/10' : '' }}">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $m->name }}</div>
                                <div class="text-[11px] text-gray-500">{{ $typeLabels[$m->type] ?? '' }} · {{ $m->unit }}</div>
                            </td>
                            <td class="px-3 py-2 tabular-nums font-semibold">{{ $fmtQty($row['stock']) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $fmtQty($row['consumed']) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $fmtQty($row['avg_daily']) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $row['days_left'] !== null ? $row['days_left'] : '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $fmtQty($row['reorder_point']) }}</td>
                            <td class="px-3 py-2 tabular-nums text-gray-500">{{ $row['order_qty'] ? $fmtQty($row['order_qty']) : '—' }}</td>
                            <td class="px-3 py-2 tabular-nums font-semibold {{ $row['need_order'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500' }}">
                                @if($row['need_order'] > 0)
                                    {{ $fmtQty($row['need_order']) }}
                                    @if($row['order_qty'] && (float) $row['need_raw'] !== (float) $row['need_order'])
                                        <div class="text-[10px] font-normal text-gray-500">thiếu {{ $fmtQty($row['need_raw']) }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500">Chưa có nguyên liệu. Thêm danh mục trước, gắn công thức món, rồi xem lại.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">CN: {{ $branch->name }} · Kỳ: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}. Mục tiêu tồn = max(điểm ĐH × 1.5, điểm ĐH + 7 ngày tiêu thụ). Gán «SL mỗi lần đặt» ở Nguyên liệu để làm tròn theo lô.</p>
    @endif
</div>
@endsection
