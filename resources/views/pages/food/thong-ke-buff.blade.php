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
    <h2 class="hidden text-lg font-semibold text-gray-900 dark:text-white md:block">Thống kê Nhân viên tăng đánh giá</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('food.thong-ke-buff') }}" class="hidden flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50 md:flex md:flex-wrap">
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

    @php $paymentList = $paymentHistory ?? collect(); $paymentTotal = $paymentList->count(); $paymentVisibleStart = $paymentTotal > 0 ? min(2, $paymentTotal) : 0; @endphp
    <div class="space-y-3" x-data="{ visible: {{ $paymentVisibleStart }} }">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Lịch sử thanh toán</p>
        @forelse($paymentList as $index => $p)
            <div
                x-show="visible > {{ (int) $index }}"
                x-cloak
                class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50"
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-200/80 pb-2 dark:border-gray-600/80">
                    <span class="min-w-0 text-sm font-medium text-gray-900 dark:text-white">{{ $p->paid_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    <span class="shrink-0 text-sm font-semibold tabular-nums text-orange-600 dark:text-orange-400">{{ $fmt($p->amount) }} đ</span>
                </div>
                <div class="mt-2 space-y-2 text-xs text-gray-700 dark:text-gray-300">
                    <div class="flex flex-wrap gap-x-3 gap-y-1">
                        <span><span class="font-medium text-gray-500 dark:text-gray-400">Nhận tiền:</span> {{ $p->paidUser?->name ?? '—' }}</span>
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <span><span class="font-medium text-gray-500 dark:text-gray-400">Chi trả:</span> {{ $p->payer?->name ?? '—' }}</span>
                    </div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $p->payment_method === 'cash' ? 'Tiền mặt' : strtoupper((string) $p->payment_method) }}@if($p->note)<span class="text-gray-400 dark:text-gray-500"> · </span>{{ $p->note }}@endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Chưa có lịch sử thanh toán.</p>
        @endforelse
        @if($paymentTotal > 2)
            <button
                type="button"
                class="w-full rounded-lg border border-gray-200 bg-white py-2 text-[11px] font-medium text-brand-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-brand-400 dark:hover:bg-gray-700"
                @click="visible = Math.min(visible + 2, {{ $paymentTotal }})"
                x-show="visible < {{ $paymentTotal }}"
                x-cloak
            >Xem thêm</button>
        @endif
    </div>

    @php
        $ordersList = $orders ?? collect();
        $ordersByDate = $ordersList
            ->groupBy(function ($o) {
                return $o->order_date?->format('Y-m-d') ?? 'unknown';
            })
            ->map(function ($items, $dateKey) use ($fmt) {
                $dateText = $dateKey !== 'unknown'
                    ? \Carbon\Carbon::parse($dateKey)->format('d/m/Y')
                    : 'Không rõ ngày';

                return [
                    'date_key' => (string) $dateKey,
                    'date_text' => $dateText,
                    'count' => $items->count(),
                    'labor_total' => (float) $items->sum('labor_amount'),
                    'items' => $items->values(),
                ];
            })
            ->values();
    @endphp
    <div class="space-y-3" x-data="{ openDate: null }">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
            Danh sách đơn
            <span class="ml-1 text-[10px] font-normal italic text-blue-600 dark:text-blue-400">(Được cập nhật sau 22h mỗi ngày hoặc trước 10h ngày hôm sau)</span>
        </p>
        @forelse($ordersByDate as $group)
            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                    @click="openDate = openDate === '{{ $group['date_key'] }}' ? null : '{{ $group['date_key'] }}'"
                >
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ngày</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $group['date_text'] }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $group['count'] }} đơn</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng thu nhập</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-600 dark:text-green-400">+ {{ $fmt($group['labor_total']) }} đ</p>
                        <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400" x-text="openDate === '{{ $group['date_key'] }}' ? 'Thu gọn' : 'Xem chi tiết'"></p>
                    </div>
                </button>
                <div
                    x-show="openDate === '{{ $group['date_key'] }}'"
                    x-cloak
                    class="space-y-2 border-t border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900/40"
                >
                    @foreach($group['items'] as $o)
                        @php
                            $customerRaw = trim((string) ($o->customer_name ?? ''));
                            $customerKey = mb_strtolower($customerRaw);
                            $customerDisplay = $customerRaw !== '' ? ($foodUserNameMap[$customerKey] ?? $customerRaw) : '—';
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-white px-2.5 py-2 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2 dark:border-gray-600">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mã đơn hàng</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $o->invoice_code }}</p>
                                        @if(auth()->user()?->is_admin)
                                            <form
                                                method="POST"
                                                action="{{ route('food.thong-ke-buff.order.destroy', $o) }}"
                                                class="inline"
                                                onsubmit="return confirm('Xóa đơn {{ $o->invoice_code }}?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="from_date" value="{{ $from->format('Y-m-d') }}">
                                                <input type="hidden" name="to_date" value="{{ $to->format('Y-m-d') }}">
                                                @if($branchId)
                                                    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                                                @endif
                                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Xóa</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tiền công</p>
                                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-600 dark:text-green-400">+ {{ $fmt($o->labor_amount) }} đ</p>
                                </div>
                            </div>
                            <div class="mt-2 space-y-2 text-xs text-gray-700 dark:text-gray-200">
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Chi nhánh</p>
                                    <p class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $o->branch?->name ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Thời gian đặt</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $formatBuffOrderDateTime($o) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tài khoản Shopeefood</p>
                                    <p class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $customerDisplay }}</p>
                                </div>
                            </div>
                            @if(!($isOnlyThongKeBuffUser ?? false))
                                <div class="mt-2 border-t border-gray-100 pt-2 text-xs font-medium text-amber-600 dark:border-gray-600 dark:text-amber-300">Buff: {{ $fmt($o->buff_amount) }} đ</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Không có đơn trong kỳ đã chọn.</p>
        @endforelse
    </div>
</div>
@endsection
