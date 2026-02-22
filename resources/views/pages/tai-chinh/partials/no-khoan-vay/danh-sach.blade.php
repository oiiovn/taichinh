@php
    $unifiedLoans = $unifiedLoans ?? collect();
@endphp
@if($unifiedLoans->isEmpty())
    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-10 text-center shadow-theme-xs dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300">
        <p class="text-theme-sm text-gray-600 dark:text-gray-400">Chưa có khoản nợ, khoản vay hay khoản cho vay nào.</p>
        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Bấm "Thêm khoản nợ / vay" (ghi chép cá nhân) hoặc "Tạo hợp đồng liên kết" (cả hai bên thấy cùng dữ liệu).</p>
    </div>
@else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
        <div class="overflow-x-auto">
            <table class="min-w-full text-theme-sm">
                <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Nguồn</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Loại</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Tên</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Dư nợ gốc</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Lãi phát sinh</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Lãi chưa trả</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700 dark:text-white">Lãi suất</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700 dark:text-white">Trạng thái</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach($unifiedLoans as $item)
                        <tr class="bg-white dark:bg-gray-dark dark:text-white">
                            <td class="px-4 py-3">
                                @if($item->source === 'linked')
                                    <span class="rounded-full bg-brand-500/15 px-2 py-1 text-xs font-medium text-brand-700 dark:text-brand-400">Liên kết</span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">Ghi chép</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($item->is_receivable)
                                    <span class="rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">Cho vay</span>
                                @else
                                    <span class="rounded-full bg-red-500/15 px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400">Nợ</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $item->name }}</span>
                                @if($item->source === 'linked' && $item->counterparty)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item->counterparty }}</span>
                                @endif
                                @if($item->due_date)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Đến hạn: {{ $item->due_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-white">{{ number_format($item->outstanding) }} ₫</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ number_format($item->total_accrued) }} ₫</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ number_format($item->unpaid_interest) }} ₫</td>
                            <td class="px-4 py-3 text-center">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700 dark:text-gray-300">{{ $item->interest_display }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($item->is_active)
                                    <span class="rounded-full bg-green-500/15 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400">Đang hoạt động</span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">Đã đóng</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    @if($item->source === 'linked')
                                        <a href="{{ route('tai-chinh.loans.show', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Chi tiết</a>
                                    @else
                                        <a href="{{ route('tai-chinh.liability.show', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Chi tiết</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
