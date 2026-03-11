@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
@endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Công nợ</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if($canSelectDebtor && $debtors->isEmpty())
        <p class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Chưa có công nợ. Từ trang Báo cáo bán hàng, nhấn "Xử lý công nợ" và chọn user để tạo.</p>
    @else
        @if($canSelectDebtor ?? false)
            {{-- Chọn user (con nợ) — chỉ admin --}}
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Xem công nợ của:</label>
                <select onchange="window.location.href = '{{ route('food.cong-no') }}?debtor_user_id=' + this.value" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach($debtors as $d)
                        <option value="{{ $d->id }}" {{ $debtorUserId == $d->id ? 'selected' : '' }}>{{ $d->name }} ({{ $d->email }})</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($debtor)
            @if(!($canSelectDebtor ?? false) && $debts->isEmpty())
                <p class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Bạn chưa có công nợ nào.</p>
            @else
            {{-- Thẻ thống kê --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng quyết toán</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $fmt($totalQuyetToan) }} đ</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Đã thanh toán</p>
                    <p class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ $fmt($totalDaThanhToan) }} đ</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Còn lại</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $fmt($conLai) }} đ</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Trạng thái</p>
                    <p class="mt-1 text-lg font-semibold {{ $trangThai === 'Đã thanh toán' ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $trangThai ?? '—' }}</p>
                </div>
            </div>

            {{-- Danh sách báo cáo --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Danh sách báo cáo</h3>
                <div class="max-h-[16rem] overflow-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mã báo cáo</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày báo cáo</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tổng đơn</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Quyết toán</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày tải lên</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Trạng thái thanh toán</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debts as $d)
                            @php $r = $d->report; $det = $d->debt_detail; @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">
                                    <span>{{ $r->report_code }}</span>
                                    @if($r && (float) ($r->bonus ?? 0) > 0)
                                        <span class="ml-1.5 inline-flex rounded-full bg-emerald-500 px-1.5 py-0.5 text-xs font-medium text-white dark:bg-emerald-600" title="Thưởng">+{{ $fmt($r->bonus) }} đ</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $r->report_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $r->total_orders }}</td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">
                                    <span>{{ $fmt($d->debt_amount) }} đ</span>
                                    @if((float)($det['deduction'] ?? 0) > 0)
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" title="Tổng − Trừ = Còn">Tổng {{ $fmt($det['base']) }} − Trừ {{ $fmt($det['deduction']) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $r->uploaded_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2">
                                    @if($d->payment)
                                        <span class="text-green-600 dark:text-green-400">Đã thanh toán</span>
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Chưa thanh toán</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('food.bao-cao-ban-hang.show', $r) }}" class="text-brand-600 hover:underline dark:text-brand-400">Chi tiết</a>
                                    @if(!$d->payment)
                                        <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>
                                        <form action="{{ route('food.cong-no.thanh-toan-tien-mat', $d) }}" method="POST" class="inline" onsubmit="return confirm('Ghi nhận thanh toán tiền mặt {{ $fmt($d->debt_amount) }} đ cho báo cáo {{ $r->report_code }}?');">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:underline dark:text-emerald-400">Thanh toán tiền mặt</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Không có báo cáo nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            {{-- Lịch sử thanh toán --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Lịch sử thanh toán</h3>
                <div class="max-h-[16rem] overflow-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="w-full min-w-[500px] text-left text-sm">
                        <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mã báo cáo</th>
                                <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số tiền</th>
                                <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày giao dịch</th>
                                <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nội dung</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentHistory as $p)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">
                                    @php $rep = $p->debt->report ?? null; @endphp
                                    <span>{{ $rep ? $rep->report_code : '—' }}</span>
                                    @if($rep && (float) ($rep->bonus ?? 0) > 0)
                                        <span class="ml-1.5 inline-flex rounded-full bg-emerald-500 px-1.5 py-0.5 text-xs font-medium text-white dark:bg-emerald-600" title="Thưởng">+{{ $fmt($rep->bonus) }} đ</span>
                                    @endif
                                </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $fmt($p->amount_paid) }} đ</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $p->transaction ? $p->transaction->transaction_date?->format('d/m/Y H:i') : $p->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $p->transaction ? Str::limit($p->transaction->description ?? '—', 60) : 'Tiền mặt' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có giao dịch thanh toán nào (khớp từ lịch sử Pay2s).</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        @endif
    @endif
</div>
@endsection
