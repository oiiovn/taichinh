@php
    $schedules = $paymentSchedules ?? collect();
    $obligation30 = $paymentScheduleObligation30 ?? ['total' => 0, 'items' => []];
    $obligation90 = $paymentScheduleObligation90 ?? ['total' => 0, 'items' => []];
    $executionStatus = $paymentScheduleExecutionStatus ?? [];
    $scheduleObligation30dAmount = $scheduleObligation30dAmount ?? [];
    $liquidBalance = (float) ($position['liquid_balance'] ?? 0);
@endphp
<div class="space-y-6" x-data="{ showConfirmDelete: false, formIdToSubmit: null }" @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId" @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch thanh toán</h2>
        </div>
        <button type="button" @click="$dispatch('open-them-lich-modal')" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 dark:border-brand-500 dark:bg-brand-500 dark:hover:bg-brand-600">
            <span class="[&_svg]:h-4 [&_svg]:w-4">{!! \App\Helpers\MenuHelper::getIconSvg('calendar') !!}</span>
            Thêm lịch
        </button>
    </div>

    @if($schedules->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nghĩa vụ 30 ngày tới</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ number_format($obligation30['total'], 0, ',', '.') }} ₫</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nghĩa vụ 90 ngày tới</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ number_format($obligation90['total'], 0, ',', '.') }} ₫</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Thanh khoản hiện tại</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ number_format($liquidBalance, 0, ',', '.') }} ₫</p>
            </div>
        </div>
        @if($obligation30['total'] > 0 && $liquidBalance > 0 && $obligation30['total'] > $liquidBalance)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                <strong>Cảnh báo:</strong> Tổng nghĩa vụ 30 ngày tới ({{ number_format($obligation30['total'], 0, ',', '.') }} ₫) lớn hơn thanh khoản hiện tại. Có thể sắp âm dòng tiền.
            </div>
        @endif
    @endif

    @if($schedules->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 pt-12 pb-12 dark:border-gray-700 dark:bg-gray-800/50">
            <span class="mb-4 mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400 [&_svg]:h-7 [&_svg]:w-7">{!! \App\Helpers\MenuHelper::getIconSvg('calendar') !!}</span>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có lịch thanh toán nào</p>
            <p class="mt-1 pb-2 text-center text-sm text-gray-500 dark:text-gray-400">Thêm lịch để theo dõi các khoản trả định kỳ.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tên</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Số tiền dự kiến</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Chu kỳ</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Hạn kế tiếp</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nội dung thanh toán</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Ghi chú nội bộ</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Trạng thái</th>
                        <th scope="col" class="w-20 px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach($schedules as $schedule)
                        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                @php
                                    $amount30 = (float) ($scheduleObligation30dAmount[$schedule->id] ?? 0);
                                    $totalAfter = $obligation30['total'] - $amount30;
                                @endphp
                                <span title="{{ $amount30 > 0 ? 'Nếu hủy lịch này: tổng nghĩa vụ 30 ngày còn ' . number_format($totalAfter, 0, ',', '.') . ' ₫ (giảm ' . number_format($amount30, 0, ',', '.') . ' ₫).' : '' }}">{{ $schedule->name }}</span>
                                @if($amount30 > 0)
                                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400" title="Nếu hủy: 30 ngày còn {{ number_format($totalAfter, 0, ',', '.') }} ₫">(hủy → −{{ number_format($amount30, 0, ',', '.') }} ₫)</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($schedule->expected_amount, 0, ',', '.') }} ₫</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                @if($schedule->frequency === 'monthly')
                                    Hàng tháng
                                @elseif($schedule->frequency === 'every_2_months')
                                    2 tháng
                                @elseif($schedule->frequency === 'quarterly')
                                    3 tháng
                                @elseif($schedule->frequency === 'yearly')
                                    Hàng năm
                                @else
                                    Mỗi {{ $schedule->interval_value }} ngày
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $schedule->next_due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="max-w-[200px] px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-data="{ copied: false, content: {{ json_encode($schedule->transfer_note_pattern ?? (is_array($schedule->keywords) && count($schedule->keywords) > 0 ? implode(', ', $schedule->keywords) : '—')) }} }">
                                <button type="button"
                                    @click="if (content && content !== '—' && navigator.clipboard) { navigator.clipboard.writeText(content); copied = true; setTimeout(() => copied = false, 1500); }"
                                    class="w-full truncate text-left rounded px-2 py-1 -mx-2 -my-1 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-copy"
                                    :title="content && content !== '—' ? 'Nhấn để copy' : 'Chưa có nội dung'">
                                    <span x-text="copied ? 'Đã copy!' : content" class="block truncate"></span>
                                </button>
                            </td>
                            <td class="max-w-[180px] px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                <span class="block truncate" title="{{ e($schedule->internal_note ?? '') }}">{{ $schedule->internal_note ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @php $exec = $executionStatus[$schedule->id] ?? 'active'; @endphp
                                @if($exec === 'overdue')
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">Trễ hạn</span>
                                @elseif($exec === 'due_soon')
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Sắp đến hạn</span>
                                @elseif($exec === 'paused')
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Tạm dừng</span>
                                @elseif($exec === 'ended')
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">Kết thúc</span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">Đang dùng</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="flex shrink-0 items-center justify-end gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button type="button" @click="$dispatch('open-edit-lich-modal', { id: {{ $schedule->id }} })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                    </button>
                                    <form id="form-delete-lich-{{ $schedule->id }}" action="{{ route('tai-chinh.payment-schedules.destroy', $schedule->id) }}" method="post" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-lich-{{ $schedule->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-ui.modal :isOpen="false" @open-them-lich-modal.window="open = true" class="w-full max-w-xl">
        @include('pages.tai-chinh.partials.lich-thanh-toan-form')
    </x-ui.modal>

    <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa lịch thanh toán" defaultMessage="Bạn có chắc muốn xóa lịch này? Hành động không thể hoàn tác." />
</div>
