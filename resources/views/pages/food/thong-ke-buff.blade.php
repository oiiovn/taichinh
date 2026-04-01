@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $foodUserNameMap = collect($payableUsers ?? [])->reduce(function ($carry, $u) {
        $name = trim((string) ($u->name ?? ''));
        $email = trim((string) ($u->email ?? ''));
        if ($name !== '') {
            $carry[mb_strtolower($name)] = $name;
        }
        if ($email !== '') {
            $carry[mb_strtolower($email)] = $name !== '' ? $name : $email;
            $username = strtolower(strtok($email, '@') ?: '');
            if ($username !== '') {
                $carry[$username] = $name !== '' ? $name : $email;
            }
        }

        return $carry;
    }, []);
    $formatBuffOrderDateTime = static function ($order) {
        $timeText = trim((string) ($order->order_time_text ?? ''));
        if ($timeText !== '') {
            foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $cf) {
                try {
                    $dt = \Carbon\Carbon::createFromFormat($cf, $timeText);

                    return $dt->format('d/m/Y H:i:s');
                } catch (\Throwable $e) {
                    // thử định dạng tiếp
                }
            }
            try {
                return \Carbon\Carbon::parse($timeText)->format('d/m/Y H:i:s');
            } catch (\Throwable $e) {
                // ignore
            }
            if ($order->order_date && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $timeText)) {
                return $order->order_date->format('d/m/Y') . ' ' . $timeText;
            }

            return trim(($order->order_date?->format('d/m/Y') ?? '') . ' ' . $timeText) ?: '—';
        }

        return $order->order_date?->format('d/m/Y') ?? '—';
    };
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="hidden md:block space-y-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Thống kê Nhân viên tăng đánh giá</h2>
        <form method="GET" action="{{ route('food.thong-ke-buff') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
            <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
            <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div class="min-w-[220px]">
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Chi nhánh</label>
            <select name="food_branch_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả chi nhánh</option>
                @foreach($branches as $br)
                    <option value="{{ $br->id }}" @selected((int) $branchId === (int) $br->id)>{{ $br->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Xem</button>
        </form>
    </div>

    @if($isOnlyThongKeBuffUser ?? false)
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tổng đơn hàng</p><p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $tongDon }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Thu nhập</p><p class="mt-1 text-xl font-semibold text-sky-600 dark:text-sky-400">{{ $fmt($tongTienCong) }} đ</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Đã thanh toán</p><p class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ $fmt($tongDaTra ?? 0) }} đ</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Còn lại</p><p class="mt-1 text-xl font-semibold {{ ($tongConLai ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : (($tongConLai ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white') }}">{{ $fmt($tongConLai ?? 0) }} đ</p></div>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tổng đơn hàng</p><p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $tongDon }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tiền Buff</p><p class="mt-1 text-xl font-semibold text-amber-600 dark:text-amber-400">{{ $fmt($tongBuff) }} đ</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Thu nhập</p><p class="mt-1 text-xl font-semibold text-sky-600 dark:text-sky-400">{{ $fmt($tongTienCong) }} đ</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tổng chi</p><p class="mt-1 text-xl font-semibold text-red-600 dark:text-red-400">{{ $fmt($tongChi) }} đ</p></div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Đã thanh toán</p>
                <p class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ $fmt($tongDaTra ?? 0) }} đ</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Còn lại</p>
                <p class="mt-1 text-xl font-semibold {{ ($tongConLai ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : (($tongConLai ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white') }}">{{ $fmt($tongConLai ?? 0) }} đ</p>
            </div>
        </div>
    @endif

    @if(!($isOnlyThongKeBuffUser ?? false))
        <form method="POST" action="{{ route('food.thong-ke-buff.thanh-toan-tien-cong') }}" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            @csrf
            <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Thanh toán tiền công (tiền mặt)</p>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">User nhận tiền</label>
                    <select name="paid_user_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Chọn user</option>
                        @foreach(($payableUsers ?? collect()) as $u)
                            <option value="{{ $u->id }}" @selected((int) old('paid_user_id') === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    @error('paid_user_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Số tiền</label>
                    <input type="number" min="1" step="1" name="amount" value="{{ old('amount') }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: 500000">
                    @error('amount')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ghi chú</label>
                    <input type="text" name="note" value="{{ old('note') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Tùy chọn">
                    @error('note')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thanh toán</button>
            </div>
        </form>

    @endif

    @php
        $paymentList = $paymentHistory ?? collect();
        $nowYmPay = \Carbon\Carbon::now()->format('Y-m');
        $payWithDate = $paymentList->filter(fn ($p) => $p->paid_at !== null);
        $payNoDate = $paymentList->filter(fn ($p) => $p->paid_at === null);

        $payInCurrent = $payWithDate->filter(fn ($p) => $p->paid_at->format('Y-m') === $nowYmPay);
        $payOther = $payWithDate->filter(fn ($p) => $p->paid_at->format('Y-m') !== $nowYmPay);

        $payCurrentMonthBlock = null;
        if ($payInCurrent->isNotEmpty()) {
            $payCurrentMonthBlock = [
                'items' => $payInCurrent->sortByDesc(fn ($x) => $x->paid_at?->timestamp ?? 0)->values(),
            ];
        }

        $payPastMonths = $payOther
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))
            ->map(function ($items, $ym) {
                $start = \Carbon\Carbon::createFromFormat('Y-m', $ym)->startOfMonth();

                return [
                    'month_key' => 'pm:'.$ym,
                    'month_text' => 'Tháng '.$start->format('n/Y'),
                    'count' => $items->count(),
                    'total' => (float) $items->sum('amount'),
                    'items' => $items->sortByDesc(fn ($x) => $x->paid_at?->timestamp ?? 0)->values(),
                    'sort_ts' => $start->copy()->endOfMonth()->timestamp,
                ];
            })
            ->sortByDesc('sort_ts')
            ->values();

        if ($payNoDate->isNotEmpty()) {
            $payPastMonths->push([
                'month_key' => 'pm:unknown',
                'month_text' => 'Không rõ ngày',
                'count' => $payNoDate->count(),
                'total' => (float) $payNoDate->sum('amount'),
                'items' => $payNoDate->sortByDesc('id')->values(),
                'sort_ts' => -1,
            ]);
        }

        $paymentTopLevel = collect();
        if ($payCurrentMonthBlock !== null) {
            $paymentTopLevel->push(['type' => 'current_month', 'month' => $payCurrentMonthBlock]);
        }
        foreach ($payPastMonths as $month) {
            $paymentTopLevel->push(['type' => 'past_month', 'month' => $month]);
        }

        $payTopCount = $paymentTopLevel->count();
        $payVisibleStart = $payTopCount > 0 ? min(2, $payTopCount) : 0;
    @endphp
    <div class="space-y-3" x-data="{ visible: {{ $payVisibleStart }}, pmOpenMonth: null }">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Lịch sử thanh toán</p>
        @forelse($paymentTopLevel as $index => $block)
            <div x-show="visible > {{ (int) $index }}" x-cloak class="space-y-0">
                @if($block['type'] === 'current_month')
                    @php $month = $block['month']; @endphp
                    <div class="space-y-2">
                        @foreach($month['items'] as $p)
                            @include('pages.food.partials.thong-ke-buff-payment-item', ['p' => $p])
                        @endforeach
                    </div>
                @else
                    @php $month = $block['month']; @endphp
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                            @click="pmOpenMonth = pmOpenMonth === '{{ $month['month_key'] }}' ? null : '{{ $month['month_key'] }}'"
                        >
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tháng</p>
                                <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $month['month_text'] }}</p>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $month['count'] }} giao dịch</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng thanh toán</p>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-orange-600 dark:text-orange-400">{{ $fmt($month['total']) }} đ</p>
                                <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400" x-text="pmOpenMonth === '{{ $month['month_key'] }}' ? 'Thu gọn' : 'Xem chi tiết'"></p>
                            </div>
                        </button>
                        <div
                            x-show="pmOpenMonth === '{{ $month['month_key'] }}'"
                            x-cloak
                            class="space-y-2 border-t border-gray-100 bg-gray-50 px-2 py-2.5 dark:border-gray-600 dark:bg-gray-900/40"
                        >
                            @foreach($month['items'] as $p)
                                @include('pages.food.partials.thong-ke-buff-payment-item', ['p' => $p])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Chưa có lịch sử thanh toán.</p>
        @endforelse
        @if($payTopCount > 2)
            <button
                type="button"
                class="w-full rounded-lg border border-gray-200 bg-white py-2 text-[11px] font-medium text-brand-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-brand-400 dark:hover:bg-gray-700"
                @click="visible = Math.min(visible + 2, {{ $payTopCount }})"
                x-show="visible < {{ $payTopCount }}"
                x-cloak
            >Xem thêm</button>
        @endif
    </div>

    @php
        $ordersList = $orders ?? collect();
        $nowYm = \Carbon\Carbon::now()->format('Y-m');
        $withDate = $ordersList->filter(fn ($o) => $o->order_date !== null);
        $noDate = $ordersList->filter(fn ($o) => $o->order_date === null);
        $inCurrentMonth = $withDate->filter(fn ($o) => $o->order_date->format('Y-m') === $nowYm);
        $otherWithDate = $withDate->filter(fn ($o) => $o->order_date->format('Y-m') !== $nowYm);

        $buildDayRow = function ($items, $dateKey) {
            if ($dateKey === 'unknown') {
                return [
                    'day_key' => 'd:unknown',
                    'day_text' => '—',
                    'count' => $items->count(),
                    'unreviewed_count' => $items->filter(fn ($o) => empty($o->customer_reviewed))->count(),
                    'labor_total' => (float) $items->sum('labor_amount'),
                    'items' => $items->sortByDesc(fn ($x) => $x->id)->values(),
                    'sort_ts' => 0,
                ];
            }
            $d = \Carbon\Carbon::parse($dateKey);

            return [
                'day_key' => 'd:'.$dateKey,
                'day_text' => $d->format('d/m/Y'),
                'count' => $items->count(),
                'unreviewed_count' => $items->filter(fn ($o) => empty($o->customer_reviewed))->count(),
                'labor_total' => (float) $items->sum('labor_amount'),
                'items' => $items->sortByDesc(fn ($x) => $x->id)->values(),
                'sort_ts' => $d->copy()->endOfDay()->timestamp,
            ];
        };

        $currentMonthDays = $inCurrentMonth
            ->groupBy(fn ($o) => $o->order_date->format('Y-m-d'))
            ->map(fn ($items, $dateKey) => $buildDayRow($items, $dateKey))
            ->sortByDesc('sort_ts')
            ->values();

        $ordersByMonth = $otherWithDate
            ->groupBy(fn ($o) => $o->order_date->format('Y-m'))
            ->map(function ($monthItems, $ym) use ($buildDayRow) {
                $start = \Carbon\Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
                $days = $monthItems
                    ->groupBy(fn ($o) => $o->order_date->format('Y-m-d'))
                    ->map(fn ($items, $dateKey) => $buildDayRow($items, $dateKey))
                    ->sortByDesc('sort_ts')
                    ->values();

                return [
                    'month_key' => 'm:'.$ym,
                    'month_text' => 'Tháng '.$start->format('n/Y'),
                    'count' => $monthItems->count(),
                    'labor_total' => (float) $monthItems->sum('labor_amount'),
                    'days' => $days,
                    'sort_ts' => $start->copy()->endOfMonth()->timestamp,
                ];
            })
            ->sortByDesc('sort_ts')
            ->values();

        if ($noDate->isNotEmpty()) {
            $ordersByMonth->push([
                'month_key' => 'm:unknown',
                'month_text' => 'Không rõ ngày',
                'count' => $noDate->count(),
                'labor_total' => (float) $noDate->sum('labor_amount'),
                'days' => collect([$buildDayRow($noDate, 'unknown')]),
                'sort_ts' => -1,
            ]);
        }

        $buffOrdersListEmpty = $currentMonthDays->isEmpty() && $ordersByMonth->isEmpty();
    @endphp
    <div class="space-y-3" x-data="{ openMonth: null, openDay: null }">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
            Danh sách đơn
            <span class="ml-1 text-[10px] font-normal italic text-blue-600 dark:text-blue-400">(Được cập nhật sau 22h mỗi ngày hoặc trước 10h ngày hôm sau)</span>
        </p>
        @if($buffOrdersListEmpty)
            <p class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Không có đơn trong kỳ đã chọn.</p>
        @else
        @foreach($currentMonthDays as $day)
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800">
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                    @click="openMonth = null; openDay = openDay === '{{ $day['day_key'] }}' ? null : '{{ $day['day_key'] }}'"
                >
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ngày</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $day['day_text'] }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            {{ $day['count'] }} đơn •
                            @if((int) ($day['unreviewed_count'] ?? 0) === 0)
                                <span class="font-medium text-green-600 dark:text-green-400">Đã hoàn thành đánh giá</span>
                            @else
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ (int) ($day['unreviewed_count'] ?? 0) }} chưa đánh giá</span>
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng thu nhập</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-600 dark:text-green-400">+ {{ $fmt($day['labor_total']) }} đ</p>
                        <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400" x-text="openDay === '{{ $day['day_key'] }}' ? 'Thu gọn' : 'Xem chi tiết'"></p>
                    </div>
                </button>
                @include('pages.food.partials.thong-ke-buff-day-orders', ['day' => $day])
            </div>
        @endforeach
        @foreach($ordersByMonth as $month)
            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                    @click="
                        if (openMonth === '{{ $month['month_key'] }}') { openMonth = null; openDay = null; }
                        else { openMonth = '{{ $month['month_key'] }}'; openDay = null; }
                    "
                >
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tháng</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $month['month_text'] }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $month['count'] }} đơn</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng thu nhập</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-600 dark:text-green-400">+ {{ $fmt($month['labor_total']) }} đ</p>
                        <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400" x-text="openMonth === '{{ $month['month_key'] }}' ? 'Thu gọn' : 'Xem các ngày'"></p>
                    </div>
                </button>
                <div
                    x-show="openMonth === '{{ $month['month_key'] }}'"
                    x-cloak
                    class="space-y-2 border-t border-gray-100 bg-gray-50 px-2 py-2.5 dark:border-gray-600 dark:bg-gray-900/40"
                >
                    @foreach($month['days'] as $day)
                        <div class="overflow-hidden rounded-lg border border-gray-200/90 bg-white dark:border-gray-600 dark:bg-gray-800">
                            <button
                                type="button"
                                class="flex w-full items-start justify-between gap-2 px-2.5 py-2 text-left"
                                @click="openDay = openDay === '{{ $day['day_key'] }}' ? null : '{{ $day['day_key'] }}'"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ngày</p>
                                    <p class="mt-0.5 text-xs font-semibold text-gray-900 dark:text-white">{{ $day['day_text'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-400">
                                        {{ $day['count'] }} đơn •
                                        @if((int) ($day['unreviewed_count'] ?? 0) === 0)
                                            <span class="font-medium text-green-600 dark:text-green-400">Đã hoàn thành đánh giá</span>
                                        @else
                                            <span class="font-medium text-amber-600 dark:text-amber-400">{{ (int) ($day['unreviewed_count'] ?? 0) }} chưa đánh giá</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Thu nhập</p>
                                    <p class="mt-0.5 text-xs font-semibold tabular-nums text-green-600 dark:text-green-400">+ {{ $fmt($day['labor_total']) }} đ</p>
                                    <p class="mt-0.5 text-[10px] font-medium text-brand-600 dark:text-brand-400" x-text="openDay === '{{ $day['day_key'] }}' ? 'Thu gọn' : 'Xem đơn'"></p>
                                </div>
                            </button>
                            @include('pages.food.partials.thong-ke-buff-day-orders', ['day' => $day])
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        @endif
    </div>
</div>
@endsection
